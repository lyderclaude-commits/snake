/**
 * Les canaux, mis à l'épreuve d'un vrai serveur d'en face.
 *
 * Rien de tout cela ne se voit à l'écran : ce qui décide, c'est ce que
 * PHP dit vraiment sur le fil. On dresse donc un faux Telegram et un faux
 * Meta — minimaux, mais qui NOTENT tout — et l'on inspecte l'adresse
 * appelée, la charge envoyée, et ce que le produit fait de la réponse.
 *
 * Trois choses qu'aucun test de navigateur ne peut attraper :
 *   — l'en-tête `List-Unsubscribe` que Gmail exige, et le POST en un clic
 *     qui doit passer SANS jeton anti-CSRF ;
 *   — un refus de Telegram qui vise UNE personne, contre un refus qui
 *     vise la conversation : le premier se note, le second se reprend ;
 *   — les dates d'un rappel, déduites de celle de l'événement.
 *
 *   npx tsx scripts/verifier-canaux.ts
 */
import { createServer, type IncomingMessage, type ServerResponse } from 'node:http';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const lancerPhp = promisify(execFile);

let pass = 0;
let fail = 0;
const ok = (label: string, cond: boolean, detail = '') => {
  cond ? pass++ : fail++;
  console.log(`  ${cond ? '✓' : '✗'} ${label}${detail ? ' — ' + detail : ''}`);
};

interface Appel {
  chemin: string;
  entetes: Record<string, string>;
  charge: any;
}

/**
 * Un faux Telegram, et un faux Meta.
 *
 * Il répond « oui » par défaut, et « non » quand le chemin le demande :
 * c'est ainsi qu'on éprouve les DEUX branches sans dépendre d'un incident.
 */
function serveurFactice(port: number): { appels: Appel[]; fermer: () => Promise<void> } {
  const appels: Appel[] = [];
  const serveur = createServer((req: IncomingMessage, res: ServerResponse) => {
    let corps = '';
    req.on('data', (c) => (corps += c));
    req.on('end', () => {
      const chemin = req.url ?? '';
      let charge: any = {};
      try {
        charge = corps ? JSON.parse(corps) : {};
      } catch {
        charge = { brut: corps };
      }
      appels.push({
        chemin,
        entetes: req.headers as Record<string, string>,
        charge,
      });
      res.setHeader('Content-Type', 'application/json');

      /* --- Telegram --- */
      if (chemin.includes('/getMe')) {
        return res.end(JSON.stringify({ ok: true, result: { username: 'wakabi_test_bot' } }));
      }
      if (chemin.includes('/getChat')) {
        return res.end(JSON.stringify({ ok: true, result: { type: 'channel', title: 'Wakabi Lomé' } }));
      }
      if (chemin.includes('/getChatMemberCount')) {
        return res.end(JSON.stringify({ ok: true, result: 4210 }));
      }
      if (chemin.includes('/sendMessage')) {
        const cible = String(charge.chat_id ?? '');
        // Une personne qui a bloqué le bot : refus DÉFINITIF.
        if (cible === 'bloque') {
          return res.end(JSON.stringify({ ok: false, error_code: 403,
            description: 'Forbidden: bot was blocked by the user' }));
        }
        // Un excès de débit : refus PASSAGER, à reprendre.
        if (cible === 'trop') {
          return res.end(JSON.stringify({ ok: false, error_code: 429,
            description: 'Too Many Requests: retry after 12' }));
        }
        return res.end(JSON.stringify({ ok: true, result: { message_id: 42 } }));
      }
      /* --- WhatsApp --- */
      if (chemin.includes('/messages')) {
        if (!charge?.template?.name) {
          res.statusCode = 400;
          return res.end(JSON.stringify({ error: { message: 'template does not exist' } }));
        }
        return res.end(JSON.stringify({ messages: [{ id: 'wamid.TEST' }] }));
      }
      res.statusCode = 404;
      res.end(JSON.stringify({ error: { message: 'inconnu' } }));
    });
  });
  serveur.listen(port, '127.0.0.1');
  return {
    appels,
    fermer: () => new Promise<void>((r) => serveur.close(() => r())),
  };
}

const PORT = 3942;

const main = async () => {
  const dossier = mkdtempSync(join(tmpdir(), 'wakabi-canaux-'));
  const fichier = join(dossier, 'essai.php');
  const env = {
    ...process.env,
    WAKABI_DONNEES: dossier,
    WAKABI_SGBD: 'sqlite',
    // La couture : les deux plateformes pointent sur le serveur de
    // complaisance, et l'on inspecte ce qu'elles reçoivent vraiment.
    WAKABI_TELEGRAM_API: `http://127.0.0.1:${PORT}`,
    WAKABI_WHATSAPP_API: `http://127.0.0.1:${PORT}`,
  };

  const PREAMBULE = `<?php
    require ${JSON.stringify(process.cwd() + '/php')} . '/app/bootstrap.php';
    require RACINE . '/app/schema.php';
    require RACINE . '/app/gabarit.php';
    require RACINE . '/app/auth.php';
    require RACINE . '/app/depot.php';
    require RACINE . '/app/courriel.php';
    require RACINE . '/app/push.php';
    require RACINE . '/app/carnet.php';
    require RACINE . '/app/canaux.php';
    require RACINE . '/app/regie.php';
    creer_schema(db(), false);
  `;

  const lancer = async (corps: string): Promise<string> => {
    writeFileSync(fichier, PREAMBULE + corps);
    try {
      const r = await lancerPhp('php', ['-d', 'error_reporting=E_ALL', fichier], { env });
      return r.stdout + r.stderr;
    } catch (e: any) {
      return (e.stdout ?? '') + (e.stderr ?? '');
    }
  };

  console.log('\n━━ Ce que le produit dit vraiment sur le fil ━━\n');

  /* ================= 1. Les en-têtes que Gmail exige ================= */

  const entetes = await lancer(`
    $e = entetes_desabonnement('JETONDETEST');
    foreach ($e as $c => $v) { echo $c, '=', $v ?? '(retiré)', "\\n"; }
  `);
  ok('List-Unsubscribe est présent et entre chevrons',
     /List-Unsubscribe=<https?:\/\/[^>]+>/.test(entetes),
     entetes.match(/List-Unsubscribe=.*/)?.[0]?.slice(0, 70) ?? entetes.slice(0, 70));
  ok('il porte la marque du clic unique',
     /List-Unsubscribe=<[^>]*clic=1>/.test(entetes));
  ok('List-Unsubscribe-Post dit exactement ce que la norme attend',
     entetes.includes('List-Unsubscribe-Post=List-Unsubscribe=One-Click'));
  ok('la lettre d’information cesse de se déclarer « réponse automatique »',
     /Auto-Submitted=\(retiré\)/.test(entetes));
  ok('et elle s’annonce comme un envoi de masse',
     entetes.includes('Precedence=bulk') && entetes.includes('List-Id='));

  /* ================= 2. Les cibles, telles qu’on les colle ================= */

  const cibles = await lancer(`
    foreach ([
      '@wakabi_lome', 'wakabi_lome', 'https://t.me/wakabi_lome', 'https://t.me/s/wakabi_lome',
      '-1001234567890', 'ab', '  @wakabi_lome  ', 'https://exemple.tg/pas-telegram',
    ] as $s) { echo '[', $s, '] => [', telegram_cible_propre($s), "]\\n"; }
    foreach (['+228 90 12 34 56', '22890123456', '90-12', 'abc'] as $n) {
      echo '[', $n, '] => [', whatsapp_numero_propre($n), "]\\n";
    }
  `);
  ok('un @nom passe tel quel', cibles.includes('[@wakabi_lome] => [@wakabi_lome]'));
  ok('un nom sans arobase en gagne une', cibles.includes('[wakabi_lome] => [@wakabi_lome]'));
  ok('un lien t.me est reconnu', cibles.includes('[https://t.me/wakabi_lome] => [@wakabi_lome]'));
  ok('un lien t.me/s/ aussi — c’est celui qu’on copie depuis le web',
     cibles.includes('[https://t.me/s/wakabi_lome] => [@wakabi_lome]'));
  ok('un identifiant numérique de groupe est gardé',
     cibles.includes('[-1001234567890] => [-1001234567890]'));
  ok('un nom trop court est refusé', cibles.includes('[ab] => []'));
  ok('les espaces autour ne comptent pas', cibles.includes('[  @wakabi_lome  ] => [@wakabi_lome]'));
  ok('une adresse qui n’est pas Telegram est refusée',
     cibles.includes('[https://exemple.tg/pas-telegram] => []'));
  ok('un numéro se réduit à ses chiffres', cibles.includes('[+228 90 12 34 56] => [22890123456]'));
  ok('un numéro trop court est refusé', cibles.includes('[90-12] => []'));

  /* ================= 3. Le texte, échappé pour Telegram ================= */

  const texte = await lancer(`
    echo telegram_texte([
      'titre' => 'Nuit <Blanche> & Cie',
      'corps' => "Rendez-vous à 21 h.\\n\\nCode_promo *ETE* < 10",
    ]), "\\n";
  `);
  ok('le titre est en gras', texte.includes('<b>'));
  ok('les chevrons du texte sont échappés — sinon Telegram refuse le message',
     texte.includes('&lt;Blanche&gt;') && texte.includes('&lt; 10'),
     texte.split('\n')[0].slice(0, 60));
  ok('mais le tiret bas et l’astérisque passent intacts',
     texte.includes('Code_promo *ETE*'));

  /* ================= 4. Un envoi, sur un faux Telegram ================= */

  const faux = serveurFactice(PORT);
  await new Promise((r) => setTimeout(r, 120));

  const envoi = await lancer(`
    $id = canal_creer('u1', 'telegram', 'bot', '123:JETON', '@bot');
    $c = canal_par_id($id);
    foreach ([['@wakabi_lome', 'chaîne'], ['bloque', 'bloqué'], ['trop', 'débit']] as [$cible, $quoi]) {
      $r = canal_remettre($c, $cible, [
        'titre' => 'Nuit Blanche', 'corps' => 'On ouvre à 21 h.',
        'lien' => 'https://boost.wakabileguide.com/x', 'libelle' => 'Mon badge',
      ]);
      printf("%s|ok=%s|reprendre=%s|%s\\n", $quoi, $r['ok'] ? 'oui' : 'non',
             $r['reprendre'] ? 'oui' : 'non', $r['message']);
    }
  `, );

  await new Promise((r) => setTimeout(r, 200));
  ok('un envoi normal est accepté', envoi.includes('chaîne|ok=oui|reprendre=non'),
     envoi.split('\n')[0]);
  ok('un bot bloqué est un échec DÉFINITIF — on n’insiste pas trois fois',
     envoi.includes('bloqué|ok=non|reprendre=non'),
     envoi.split('\n')[1] ?? '');
  ok('un excès de débit se REPREND — c’est le seul refus qui s’arrange seul',
     envoi.includes('débit|ok=non|reprendre=oui'),
     envoi.split('\n')[2] ?? '');

  const envoye = faux.appels.find((a) => a.chemin.includes('/sendMessage'));
  ok('le jeton voyage dans le chemin, jamais dans la charge',
     !!envoye && envoye.chemin.includes('123%3AJETON')
     && !JSON.stringify(envoye.charge).includes('JETON'),
     envoye?.chemin ?? '(aucun appel)');
  ok('le lien devient un bouton, pas une adresse nue',
     !!envoye?.charge?.reply_markup?.inline_keyboard?.[0]?.[0]?.url,
     JSON.stringify(envoye?.charge?.reply_markup ?? {}).slice(0, 60));
  ok('et le mode HTML est annoncé, faute de quoi les balises s’affichent',
     envoye?.charge?.parse_mode === 'HTML');

  /* ================= 5. WhatsApp : pas de modèle, pas d’envoi ================= */

  const wa = await lancer(`
    $id = canal_creer('u1', 'whatsapp', 'wa', 'EAAG', '999');
    $c = canal_par_id($id);
    $sans = canal_remettre($c, '22890000000', ['titre' => 'T', 'corps' => 'C']);
    printf("sans|%s|%s\\n", $sans['ok'] ? 'oui' : 'non', $sans['message']);
    $avec = canal_remettre($c, '22890000000',
      ['titre' => 'T', 'corps' => "deux\\nlignes", 'modele' => 'rappel_evenement_fr']);
    printf("avec|%s|%s\\n", $avec['ok'] ? 'oui' : 'non', $avec['message']);
  `);
  ok('sans modèle, WhatsApp est refusé AVANT d’appeler Meta',
     wa.includes('sans|non') && wa.includes('modèle'),
     wa.split('\n')[0]?.slice(0, 80));
  ok('avec un modèle, l’appel part', wa.includes('avec|oui'), wa.split('\n')[1]?.slice(0, 60));

  const appelWa = faux.appels.find((a) => a.chemin.includes('/messages'));
  ok('le jeton Meta voyage en en-tête Authorization, pas dans l’adresse',
     !!appelWa && String(appelWa.entetes.authorization ?? '').startsWith('Bearer EAAG')
     && !appelWa.chemin.includes('EAAG'));
  ok('le message est bien un modèle, avec sa langue',
     appelWa?.charge?.type === 'template' && !!appelWa?.charge?.template?.language?.code);
  ok('les sauts de ligne sont ôtés des variables — Meta les refuse',
     !JSON.stringify(appelWa?.charge?.template?.components ?? []).includes('\\n'),
     JSON.stringify(appelWa?.charge?.template?.components ?? []).slice(0, 70));

  /* ================= 6. Les dates d’un rappel ================= */

  const dates = await lancer(`
    /* La VRAIE fonction du produit, et le VRAI tableau des cinq moments :
       une copie ici finirait par diverger, et c'est justement l'écart
       qu'un vérifieur doit attraper. */
    echo 'J-7=', rappel_quand('2026-03-14', 24 * 7), "\\n";
    echo 'veille=', rappel_quand('2026-03-14', 26), "\\n";
    echo 'portes=', rappel_quand('2026-03-14', 2), "\\n";
    echo 'apres=', rappel_quand('2026-03-14', -15), "\\n";
    echo 'sansdate=', var_export(rappel_quand(null, 24), true), "\\n";
    echo 'moments=', count(RAPPELS_MODELE), "\\n";
    $cles = array_column(RAPPELS_MODELE, 'cle');
    echo 'uniques=', count(array_unique($cles)) === count($cles) ? 'oui' : 'non', "\\n";
  `);
  ok('J−7 tombe sept jours avant, à la même heure',
     dates.includes('J-7=2026-03-07T20:00:00Z'), dates.split('\n')[0]);
  ok('la veille tombe la veille au soir, pas à minuit',
     dates.includes('veille=2026-03-13T18:00:00Z'), dates.split('\n')[1]);
  ok('« deux heures avant » veut dire deux heures avant',
     dates.includes('portes=2026-03-14T18:00:00Z'), dates.split('\n')[2]);
  ok('et le lendemain tombe APRÈS l’événement',
     dates.includes('apres=2026-03-15T11:00:00Z'), dates.split('\n')[3]);
  ok('sans date d’événement, aucun rappel ne se calcule',
     dates.includes('sansdate=NULL'), dates.split('\n')[4]);
  ok('les cinq moments sont là, et sans doublon',
     dates.includes('moments=5') && dates.includes('uniques=oui'),
     dates.match(/moments=\d+/)?.[0] ?? '');

  /* ================= 7. La file, tous canaux confondus ================= */

  const file = await lancer(`
    $id = canal_creer('u1', 'telegram', 'bot', '123:JETON', '@bot');
    destination_poser($id, 'chaine', 'Wakabi Lomé', '@wakabi_lome', 4210);
    abonne_canal_poser($id, '111', 'Awa');
    abonne_canal_poser($id, '222', 'Kossi');
    abonne_canal_poser($id, '111', 'Awa');   // deux fois : un seul abonné
    $d = db()->query("SELECT id FROM destinations LIMIT 1")->fetchColumn();

    $c = ['cible' => 'mes-invites', 'canaux' => json_encode([
      ['canal' => 'telegram', 'canal_id' => $id, 'destination_id' => $d],
      ['canal' => 'telegram', 'canal_id' => $id],
    ])];
    $p = regie_portee($c, ['id' => 'u1']);
    foreach ($p['lignes'] as $l) { printf("%s=%d\\n", $l['genre'], $l['n']); }
    echo 'destinations=', $p['destinations'], "\\n";
    echo 'payant=', $p['payant'], "\\n";
  `);
  ok('une chaîne de 4 210 abonnés ne compte QUE pour une destination',
     /telegram=1\n/.test(file), file.split('\n')[0]);
  ok('le tête-à-tête compte une destination par personne',
     file.includes('destinations=3'), file.match(/destinations=\d+/)?.[0] ?? '');
  ok('et le même abonné inscrit deux fois n’en fait qu’un',
     file.includes('telegram=2'), file.split('\n')[1] ?? '');
  ok('rien de payant tant que WhatsApp n’est pas coché',
     file.includes('payant=0'), file.match(/payant=\d+/)?.[0] ?? '');

  await faux.fermer();
  rmSync(dossier, { recursive: true, force: true });

  console.log(`\n━━ ${pass} réussis, ${fail} échoués ━━\n`);
  process.exit(fail ? 1 : 0);
};

main().catch((e) => {
  console.error(e);
  process.exit(1);
});
