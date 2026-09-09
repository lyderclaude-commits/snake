/**
 * Le client SMTP, mis à l'épreuve d'un vrai dialogue.
 *
 * Un envoi d'e-mail ne se teste pas par la recette du navigateur : rien
 * n'est visible à l'écran, et le seul verdict honnête est celui du serveur
 * d'en face. On en ouvre donc un — minimal, mais qui parle le protocole —
 * et on vérifie ce que PHP lui dit vraiment : l'ordre des commandes,
 * l'authentification, les en-têtes, l'encodage d'un sujet accentué et la
 * protection des points en début de ligne.
 *
 *   npx tsx scripts/verifier-courriel.ts
 */
import { createServer, type Socket } from 'node:net';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

/**
 * PHP tourne de façon ASYNCHRONE, et ce n'est pas un détail de style.
 *
 * Avec `execFileSync`, Node bloque sa boucle d'événements pendant tout
 * l'appel : le noyau accepte bien la connexion TCP, mais le serveur
 * factice ne peut pas envoyer sa bannière avant la fin du processus PHP —
 * qui, lui, l'attend. Les deux se regardent jusqu'au délai d'attente.
 */
const lancerPhp = promisify(execFile);

let pass = 0;
let fail = 0;
const ok = (label: string, cond: boolean, detail = '') => {
  cond ? pass++ : fail++;
  console.log(`  ${cond ? '✓' : '✗'} ${label}${detail ? ' — ' + detail : ''}`);
};

interface Session {
  commandes: string[];
  message: string;
}

/**
 * Un serveur SMTP de complaisance.
 *
 * Il accepte tout, mais il NOTE tout : c'est la trace qu'on inspecte
 * ensuite. `AUTH LOGIN` est annoncé pour que le client s'authentifie —
 * sans annonce, il aurait raison de s'en passer.
 */
function serveurFactice(port: number): { sessions: Session[]; fermer: () => Promise<void> } {
  const sessions: Session[] = [];
  const serveur = createServer((socket: Socket) => {
    const session: Session = { commandes: [], message: '' };
    sessions.push(session);
    let tampon = '';
    let dansData = false;
    // AUTH LOGIN est un dialogue en DEUX temps : « Username: », la réponse,
    // « Password: », la réponse, et seulement alors le 235. Répondre 235 dès
    // le premier envoi ferait passer un client qui n'a pas donné son mot de
    // passe — un serveur de complaisance qui valide trop ne prouve rien.
    let attenduAuth: '' | 'utilisateur' | 'motdepasse' = '';

    socket.write('220 wakabi-essai ESMTP\r\n');
    socket.on('data', (bloc) => {
      tampon += bloc.toString('utf8');
      let i: number;
      while ((i = tampon.indexOf('\r\n')) >= 0) {
        const ligne = tampon.slice(0, i);
        tampon = tampon.slice(i + 2);

        if (dansData) {
          if (ligne === '.') {
            dansData = false;
            socket.write('250 2.0.0 Ok\r\n');
          } else {
            session.message += ligne + '\n';
          }
          continue;
        }

        session.commandes.push(ligne);
        const haut = ligne.toUpperCase();
        if (haut.startsWith('EHLO')) socket.write('250-wakabi-essai\r\n250-SIZE 10240000\r\n250 AUTH LOGIN PLAIN\r\n');
        else if (haut.startsWith('HELO')) socket.write('250 wakabi-essai\r\n');
        else if (haut === 'AUTH LOGIN') { attenduAuth = 'utilisateur'; socket.write('334 VXNlcm5hbWU6\r\n'); }
        else if (attenduAuth === 'utilisateur') { attenduAuth = 'motdepasse'; socket.write('334 UGFzc3dvcmQ6\r\n'); }
        else if (attenduAuth === 'motdepasse') { attenduAuth = ''; socket.write('235 2.7.0 Authentication successful\r\n'); }
        else if (haut.startsWith('MAIL FROM')) socket.write('250 2.1.0 Ok\r\n');
        else if (haut.startsWith('RCPT TO')) socket.write('250 2.1.5 Ok\r\n');
        else if (haut === 'DATA') { dansData = true; socket.write('354 End data with <CR><LF>.<CR><LF>\r\n'); }
        else if (haut === 'QUIT') { socket.write('221 2.0.0 Bye\r\n'); socket.end(); }
        else socket.write('502 5.5.2 Commande inconnue\r\n');
      }
    });
    socket.on('error', () => { /* le client raccroche : sans intérêt ici */ });
  });

  serveur.listen(port, '127.0.0.1');
  return {
    sessions,
    fermer: () => new Promise((r) => serveur.close(() => r())),
  };
}

const PORT = 3925;

const main = async () => {
  console.log('\n━━ Le client SMTP face à un serveur ━━\n');
  const faux = serveurFactice(PORT);
  await new Promise((r) => setTimeout(r, 250));

  // Une installation jetable : la table `reglages` vit dans une base, et on
  // ne veut surtout pas écrire dans celle de développement.
  const dossier = mkdtempSync(join(tmpdir(), 'wakabi-smtp-'));
  writeFileSync(join(dossier, 'config.php'), `<?php return [
    'sgbd' => 'sqlite',
    'dossier_donnees' => ${JSON.stringify(dossier)},
    'fichier' => ${JSON.stringify(join(dossier, 'essai.sqlite'))},
    'base_url' => 'https://boost.wakabileguide.com',
  ];`);

  // `WAKABI_CONFIG` : l'essai a sa propre base, jamais celle du développement.
  const env = { ...process.env, WAKABI_CONFIG: join(dossier, 'config.php') };

  /** Le même préambule pour chaque petit script : charger l'application. */
  const PREAMBULE = `<?php
    require ${JSON.stringify(process.cwd() + '/php')} . '/app/bootstrap.php';
    require RACINE . '/app/schema.php';
    require RACINE . '/app/gabarit.php';
    require RACINE . '/app/auth.php';
    require RACINE . '/app/depot.php';
    require RACINE . '/app/courriel.php';
  `;

  const script = PREAMBULE + `
    creer_schema(db(), false);
    reglages_bdd_poser([
      'smtp_hote' => '127.0.0.1',
      'smtp_port' => '${PORT}',
      'smtp_securite' => 'aucune',
      'smtp_utilisateur' => 'boost@wakabileguide.com',
      'smtp_motdepasse' => 'secret-2026',
      'courriel_expediteur' => 'boost@wakabileguide.com',
      'courriel_nom' => 'Wakabi Boost',
    ]);
    var_dump(courriel_branche());
    $r = courriel_mis_en_page(
      'ama@exemple.tg', 'Ama Koffi',
      'Décor approuvé — soirée à Lomé',
      'Votre décor est en ligne',
      "Bravo !\\n\\n. Une ligne qui commence par un point.",
      'https://boost.wakabileguide.com/index.php?p=partenaire',
      'Voir mes campagnes'
    );
    echo $r['ok'] ? 'ENVOI-OK' : 'ENVOI-KO:' . $r['message'], "\\n";
  `;
  const fichier = join(dossier, 'essai.php');
  writeFileSync(fichier, script);

  let sortie = '';
  try {
    const r = await lancerPhp('php', ['-d', 'error_reporting=E_ALL', fichier], { env });
    sortie = r.stdout + r.stderr;
  } catch (e: any) {
    sortie = (e.stdout ?? '') + (e.stderr ?? '');
  }

  ok('le transport se déclare branché', sortie.includes('bool(true)'), sortie.split('\n')[0]);
  ok('PHP annonce un envoi réussi', sortie.includes('ENVOI-OK'),
     sortie.match(/ENVOI-KO:.*/)?.[0] ?? '');

  await new Promise((r) => setTimeout(r, 250));
  const s = faux.sessions[0];
  ok('le serveur a bien vu une session', !!s, `${faux.sessions.length} session(s)`);

  if (s) {
    const cmds = s.commandes;
    const verbes = cmds.map((c) => c.split(' ')[0].toUpperCase());
    ok('EHLO ouvre le dialogue', verbes[0] === 'EHLO', cmds[0]);
    ok('EHLO annonce un nom de domaine', /EHLO \S+\.\S+/.test(cmds[0] ?? ''), cmds[0]);
    ok('l’authentification a lieu', cmds.includes('AUTH LOGIN'));
    ok('l’identifiant part en base64',
       cmds.some((c) => c === Buffer.from('boost@wakabileguide.com').toString('base64')));
    ok('le mot de passe part en base64',
       cmds.some((c) => c === Buffer.from('secret-2026').toString('base64')));
    ok('MAIL FROM porte l’expéditeur',
       cmds.includes('MAIL FROM:<boost@wakabileguide.com>'));
    ok('RCPT TO porte le destinataire', cmds.includes('RCPT TO:<ama@exemple.tg>'));
    ok('le dialogue se termine par QUIT', verbes[verbes.length - 1] === 'QUIT');

    const m = s.message;
    ok('le sujet accentué est encodé', /^Subject: =\?UTF-8\?B\?/m.test(m),
       m.match(/^Subject:.*/m)?.[0]?.slice(0, 46) ?? 'absent');
    ok('le sujet décodé est le bon',
       Buffer.from(m.match(/^Subject: =\?UTF-8\?B\?(.+)\?=$/m)?.[1] ?? '', 'base64').toString('utf8')
         === 'Décor approuvé — soirée à Lomé');
    ok('From porte le nom affiché', /^From: "Wakabi Boost" <boost@wakabileguide\.com>$/m.test(m),
       m.match(/^From:.*/m)?.[0] ?? 'absent');
    ok('To porte le nom du destinataire', /^To: "Ama Koffi" <ama@exemple\.tg>$/m.test(m));
    ok('le message est multipart', /^Content-Type: multipart\/alternative; boundary=/m.test(m));
    ok('la version texte est présente', m.includes('Content-Type: text/plain; charset=UTF-8'));
    ok('la version HTML est présente', m.includes('Content-Type: text/html; charset=UTF-8'));
    ok('le lien figure dans le message', m.includes('https://boost.wakabileguide.com/index.php?p=partenaire'));
    ok('Message-ID est posé', /^Message-ID: <[0-9a-f]+@boost\.wakabileguide\.com>$/m.test(m));
    ok('la réponse automatique est découragée', /^Auto-Submitted: auto-generated$/m.test(m));
    // Le point doublé est retiré par le serveur : ce qu'on lit est le texte
    // d'origine. S'il ne l'avait pas été, le message se serait arrêté là.
    ok('un point en début de ligne ne coupe pas le message',
       m.includes('. Une ligne qui commence par un point.') && m.includes('</html>'));
  }

  /* ---- transport éteint : on le dit, on ne fait pas semblant ---- */

  writeFileSync(fichier, PREAMBULE + `
    reglages_bdd_poser(['smtp_hote' => '']);
    var_dump(courriel_branche(), verification_exigee());
  `);
  const eteint = (await lancerPhp('php', [fichier], { env })).stdout;
  ok('sans serveur, le transport se déclare éteint',
     (eteint.match(/bool\(false\)/g) ?? []).length === 2, eteint.replace(/\n/g, ' '));

  /* ---- un serveur injoignable rend un message utile ---- */

  writeFileSync(fichier, PREAMBULE + `
    reglages_bdd_poser(['smtp_hote' => '127.0.0.1', 'smtp_port' => '3926']);
    $r = envoyer_courriel('ama@exemple.tg', 'Ama', 'Essai', 'Corps');
    echo ($r['ok'] ? 'OK' : 'KO'), '|', $r['message'], "\\n";
  `);
  const injoignable = (await lancerPhp('php', [fichier], { env })).stdout;
  ok('un port fermé est signalé sans jargon',
     injoignable.startsWith('KO|') && /Connexion impossible/.test(injoignable)
       && /l’hébergeur laisse sortir ce port/.test(injoignable),
     injoignable.trim().slice(0, 76));

  /* ------------------------------------------------------------------ */
  /* Le lien de confirmation, ouvert plusieurs fois                      */
  /* ------------------------------------------------------------------ */

  /**
   * Un lien de courriel n'est presque jamais ouvert une seule fois.
   *
   * Les filtres anti-hameçonnage des messageries **suivent** les liens
   * avant de livrer le message, pour vérifier où ils mènent ; Android les
   * précharge ; un double-tap ou un rafraîchissement font le même effet.
   * Tant que le jeton mourait au premier appel, la personne trouvait « ce
   * lien a déjà servi » à son PREMIER clic — sur une adresse qui était,
   * elle, bel et bien confirmée.
   *
   * Ce bloc rejoue exactement cette séquence, et vérifie au passage que la
   * souplesse s'arrête là : un jeton inventé, un jeton périmé jamais
   * utilisé, un jeton remplacé et le jeton d'un mot de passe restent tous
   * refusés.
   */
  writeFileSync(fichier, PREAMBULE + `
    $id = creer_utilisateur(['email' => 'neuf@essai.tg', 'mot_de_passe' => 'un-mot-de-passe-solide',
        'nom' => 'Compte neuf', 'role' => 'partenaire', 'formule' => 'decouverte']);
    $jeton = creer_jeton_verification($id);
    $out = ['depart' => (bool) utilisateur_par_id($id)['email_verifie_le']];

    // 1. l'antivirus de la messagerie suit le lien AVANT la personne
    $out['scanner'] = consommer_jeton_verification($jeton);
    // 2. la personne clique ensuite sur le MÊME lien
    $out['personne'] = consommer_jeton_verification($jeton);
    // 3. et rafraîchit la page
    $out['encore'] = consommer_jeton_verification($jeton)['ok'];
    $out['confirmee'] = (bool) utilisateur_par_id($id)['email_verifie_le'];

    // 4. un jeton inventé
    $out['invente'] = consommer_jeton_verification(str_repeat('a', 48))['ok'];

    // 5. un jeton périmé, JAMAIS utilisé
    $id2 = creer_utilisateur(['email' => 'perime@essai.tg', 'mot_de_passe' => 'un-mot-de-passe-solide',
        'nom' => 'Périmé', 'role' => 'partenaire', 'formule' => 'decouverte']);
    $j2 = creer_jeton_verification($id2);
    db()->prepare('UPDATE utilisateurs SET verif_expire_le = ? WHERE id = ?')
        ->execute([maintenant(time() - 3600), $id2]);
    $r5 = consommer_jeton_verification($j2);
    $out['perime'] = ['ok' => $r5['ok'], 'message' => $r5['message']];

    // 6. un lien neuf annule le précédent
    $j3 = creer_jeton_verification($id2);
    $out['remplace'] = ['ancien' => consommer_jeton_verification($j2)['ok'],
                        'neuf' => consommer_jeton_verification($j3)['ok']];

    // 7. changer d'adresse redemande une confirmation
    db()->prepare('UPDATE utilisateurs SET email = ?, email_verifie_le = NULL, verif_jeton = NULL,
                   verif_expire_le = NULL WHERE id = ?')->execute(['autre@essai.tg', $id]);
    $out['change'] = ['jeton_mort' => !consommer_jeton_verification($jeton)['ok'],
                      'confirmee' => (bool) utilisateur_par_id($id)['email_verifie_le']];

    // 8. le jeton d'un MOT DE PASSE, lui, reste à usage unique
    $jo = creer_jeton_oubli($id);
    $out['mdp'] = ['premier' => consommer_jeton_oubli($jo, 'un-nouveau-mot-de-passe'),
                   'second' => consommer_jeton_oubli($jo, 'encore-un-autre-mot-de-passe')];

    echo json_encode($out, JSON_UNESCAPED_UNICODE), "\\n";
  `);
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const v = JSON.parse(
    (await lancerPhp('php', [fichier], { env })).stdout.trim().split('\n').pop() ?? '{}',
  ) as Record<string, any>;

  ok('au départ, l’adresse n’est pas confirmée', v.depart === false);
  ok('le premier appel — fût-il celui d’un antivirus — confirme l’adresse',
     v.scanner?.ok === true && v.scanner?.deja === false, String(v.scanner?.message));
  ok('le clic de la personne, ensuite, est une RÉUSSITE et non une panne',
     v.personne?.ok === true && v.personne?.deja === true, String(v.personne?.message));
  ok('un rafraîchissement de plus ne casse rien', v.encore === true);
  ok('et l’adresse est bien confirmée en base', v.confirmee === true);

  ok('un jeton inventé reste refusé', v.invente === false);
  ok('un jeton périmé jamais utilisé reste refusé',
     v.perime?.ok === false && /expiré/.test(String(v.perime?.message)),
     String(v.perime?.message).slice(0, 46));
  ok('demander un lien neuf annule le précédent',
     v.remplace?.ancien === false && v.remplace?.neuf === true);
  ok('changer d’adresse tue l’ancien lien ET la confirmation',
     v.change?.jeton_mort === true && v.change?.confirmee === false);

  /**
   * La distinction qui justifie tout le reste.
   *
   * Un lien de confirmation CONSTATE un fait — le rejouer ne donne rien à
   * personne. Un lien de mot de passe DONNE un pouvoir : il ouvre la
   * porte, et doit mourir au premier usage. Si cette assertion tombe un
   * jour, c'est que la souplesse a débordé sur le mauvais jeton.
   */
  ok('le jeton d’un MOT DE PASSE, lui, ne sert qu’une fois',
     v.mdp?.premier === true && v.mdp?.second === false,
     `${v.mdp?.premier} puis ${v.mdp?.second}`);

  /* ------------------------------------------------------------------ */
  /* Les échecs : ce qui se retente, et ce qui ne se retente jamais      */
  /* ------------------------------------------------------------------ */

  /**
   * La distinction 4xx / 5xx ne se voit nulle part à l'écran.
   *
   * Elle décide pourtant du seul geste qui compte après une campagne
   * ratée : relancer, ou ranger. S'acharner sur une adresse qui n'existe
   * pas est exactement ce que les fournisseurs comptent contre le
   * domaine — et une régression ici serait invisible jusqu'au jour où
   * Gmail commence à classer tout le courrier du guide en indésirable.
   */
  writeFileSync(fichier, PREAMBULE + `
    require RACINE . '/app/carnet.php';
    require RACINE . '/app/push.php';
    require RACINE . '/app/canaux.php';
    require RACINE . '/app/regie.php';

    $cas = [
      ['email', 'Le serveur SMTP a répondu : 421 4.7.0 Too many connections'],
      ['email', 'Le serveur SMTP a coupé la connexion.'],
      ['email', 'Le serveur SMTP a répondu : 450 4.2.0 Mailbox busy'],
      ['email', 'Le serveur SMTP a répondu : 550 5.1.1 No such user here'],
      ['email', 'Le serveur SMTP a répondu : 553 5.1.3 Bad recipient address'],
      ['email', 'Le serveur SMTP a répondu : 552 5.2.2 Mailbox full'],
      ['email', 'Adresse destinataire invalide.'],
      ['telegram', 'Le bot a été bloqué par cette personne.'],
      ['telegram', 'Trop de messages : Telegram demande d’attendre.'],
      ['whatsapp', 'Le modèle n’existe pas ou n’est pas approuvé.'],
    ];
    $out = [];
    foreach ($cas as [$canal, $m]) {
      $out[] = ['canal' => $canal, 'code' => echec_code($m),
                'reprend' => echec_reprenable($canal, $m),
                'mort' => echec_mortel($canal, $m)];
    }
    echo json_encode($out), "\n";
  `);
  const verdicts = JSON.parse(
    (await lancerPhp('php', [fichier], { env })).stdout.trim().split('\n').pop() ?? '[]',
  ) as { canal: string; code: string; reprend: boolean; mort: boolean }[];

  const [c421, coupure, c450, c550, c553, c552, invalide, bloque, debit, modele] = verdicts;
  ok('un 421 se retente : le relais était occupé, il ne le sera plus',
     c421?.reprend === true && c421?.mort === false && c421?.code === '421');
  ok('une connexion coupée sans code se retente aussi',
     coupure?.reprend === true && coupure?.code === '');
  ok('un 450 se retente', c450?.reprend === true);
  ok('un 550 est un verdict : l’adresse n’existe pas',
     c550?.mort === true && c550?.reprend === false, `code ${c550?.code}`);
  ok('un 553 aussi', c553?.mort === true && c553?.reprend === false);
  ok('une boîte pleine (552) n’est PAS une adresse morte : elle se vide',
     c552?.mort === false, `reprend ? ${c552?.reprend}`);
  ok('une adresse manifestement invalide est morte', invalide?.mort === true);
  ok('un bot bloqué ne se retente pas', bloque?.mort === true && bloque?.reprend === false);
  ok('une limite de débit Telegram, si', debit?.reprend === true && debit?.mort === false);
  ok('un modèle WhatsApp refusé est définitif', modele?.mort === true);

  /* ------------------------------------------------------------------ */
  /* Les adresses non confirmées, écartées — sauf celles du carnet       */
  /* ------------------------------------------------------------------ */

  /**
   * Le carnet est l'exception, et elle est voulue.
   *
   * Ces adresses n'ont pas été laissées sur un formulaire : elles ont été
   * APPORTÉES par l'organisateur, souvent depuis sa billetterie. Lui
   * demander de faire confirmer sept cents adresses qu'il possède déjà
   * reviendrait à lui interdire sa propre base — et il repartirait
   * l'envoyer ailleurs, sans aucune des règles qu'on tient ici.
   */
  writeFileSync(fichier, PREAMBULE + `
    require RACINE . '/app/carnet.php';
    require RACINE . '/app/push.php';
    require RACINE . '/app/canaux.php';
    require RACINE . '/app/regie.php';

    $orga = ['id' => nouvel_id(), 'nom' => 'Organisateur', 'email' => 'orga@exemple.tg'];
    $now = maintenant();
    $poser = static function (string $email, ?string $confirme) use ($now) {
      db()->prepare('INSERT INTO utilisateurs (id, nom, email, mot_de_passe, role, formule, ville,
                     email_verifie_le, suspendu, cree_le) VALUES (?,?,?,?,?,?,?,?,0,?)')
        ->execute([nouvel_id(), 'Qui', $email, 'x', 'participant', 'decouverte', 'lome', $confirme, $now]);
    };
    $poser('confirme@exemple.tg', $now);
    $poser('jamais@exemple.tg', null);
    $poser('vide@exemple.tg', '');

    $liste = carnet_liste_poser($orga['id'], 'Clients de la billetterie');
    carnet_importer($orga['id'], $liste, "apportee@exemple.tg\nautre@exemple.tg");

    $mesure = static function (array $c, array $u): array {
      $ecartes = 0;
      $n = count(regie_destinataires($c, $u, $ecartes));
      return ['n' => $n, 'ecartes' => $ecartes];
    };
    $out = [];

    // 1. sans transport, on ne peut pas exiger ce qu'on ne sait pas envoyer
    reglages_bdd_poser(['smtp_hote' => '']);
    $out['sans_transport'] = $mesure(['cible' => 'participants'], $orga);

    // 2. avec transport, la confirmation devient exigible
    reglages_bdd_poser(['smtp_hote' => '127.0.0.1', 'smtp_port' => '${PORT}',
                        'courriel_expediteur' => 'boost@wakabileguide.com']);
    $out['avec_transport'] = $mesure(['cible' => 'participants'], $orga);

    // 3. le carnet, lui, passe entier
    $out['carnet'] = $mesure(['cible' => 'liste', 'liste_id' => $liste, 'liste' => ''], $orga);

    // 4. et le compteur rapide dit la même chose que la liste complète
    $out['compte'] = regie_compte_cible('participants', $orga);
    echo json_encode($out), "\n";
  `);
  const tri = JSON.parse(
    (await lancerPhp('php', [fichier], { env })).stdout.trim().split('\n').pop() ?? '{}',
  ) as Record<string, { n: number; ecartes: number }>;

  ok('sans transport réglé, personne n’est écarté — on ne punit pas l’absence d’un lien qu’on ne sait pas envoyer',
     tri.sans_transport?.n === 3 && tri.sans_transport?.ecartes === 0,
     JSON.stringify(tri.sans_transport));
  ok('avec transport, seules les adresses confirmées reçoivent',
     tri.avec_transport?.n === 1, JSON.stringify(tri.avec_transport));
  ok('et l’écran peut dire combien sont écartées', tri.avec_transport?.ecartes === 2);
  ok('une adresse confirmée à la chaîne vide compte comme non confirmée',
     tri.avec_transport?.n === 1);
  ok('le carnet reste exempt : ces adresses ont été apportées, pas ramassées',
     tri.carnet?.n === 2 && tri.carnet?.ecartes === 0, JSON.stringify(tri.carnet));
  ok('le compteur rapide rend exactement ce que rend la liste complète',
     tri.compte?.n === tri.avec_transport?.n && tri.compte?.ecartes === tri.avec_transport?.ecartes,
     `${JSON.stringify(tri.compte)} vs ${JSON.stringify(tri.avec_transport)}`);

  await faux.fermer();
  rmSync(dossier, { recursive: true, force: true });

  console.log(`\n━━ ${pass} réussis, ${fail} échoués ━━\n`);
  process.exit(fail ? 1 : 0);
};

main();
