/**
 * Le rapport : ce qu'il compte, ce qu'il refuse de dire, et son PDF.
 *
 * Un rapport est le seul écran du produit dont personne ne peut vérifier
 * les chiffres à l'œil : mille lignes de file ne se recomptent pas à la
 * main. Il faut donc une base dont on connaît le contenu au message près,
 * et c'est ce que fabrique ce vérifieur — puis il recompte.
 *
 * Trois familles de pièges y sont tendues exprès :
 *
 *  1. **Les bornes.** Un envoi du dernier jour à 23 h 50 est DANS la
 *     période ; un envoi du lendemain à 00 h 10 n'y est pas. C'est l'écart
 *     qu'on ne voit qu'en recomptant, et il fait mentir un rapport de
 *     bonne foi.
 *  2. **Le cloisonnement.** Deux organisateurs, et l'un demande le décor
 *     de l'autre par son adresse. Il doit obtenir SON rapport, sans erreur
 *     ni message — et surtout pas les chiffres du voisin.
 *  3. **Le PDF.** Relu ici comme un lecteur le relit : table des
 *     références croisées, décalages, flux décompressé, texte. Un PDF
 *     cassé ne proteste pas, il refuse de s'ouvrir chez le client.
 *
 *   npx tsx scripts/verifier-rapport.ts
 */

import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { mkdtempSync, writeFileSync, rmSync, readFileSync } from 'node:fs';
import { inflateSync } from 'node:zlib';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const lancer = promisify(execFile);
const RACINE = process.cwd() + '/php';

let pass = 0;
let fail = 0;
const ok = (label: string, cond: boolean, detail = '') => {
  cond ? pass++ : fail++;
  console.log(`  ${cond ? '✓' : '✗'} ${label}${detail ? ' — ' + detail : ''}`);
};

/* ------------------------------------------------------------------ */
/* Un lecteur de PDF, écrit contre l'écrivain                          */
/* ------------------------------------------------------------------ */

interface Lu {
  version: string;
  objets: Map<number, { debut: number; corps: string }>;
  xref: number[];
  pages: number;
  textes: string[];
}

/**
 * Relit un PDF sans rien supposer de qui l'a écrit.
 *
 * On part de la FIN, comme le fait un vrai lecteur : `startxref` donne le
 * décalage de la table, la table celui de chaque objet, et le `trailer`
 * désigne le catalogue. Un octet d'écart et le fichier est refusé.
 */
function lirePdf(buf: Buffer): Lu {
  const brut = buf.toString('latin1');
  const version = (/^%PDF-(\d\.\d)/.exec(brut) ?? [, ''])[1] ?? '';

  const dernier = brut.lastIndexOf('startxref');
  const depart = parseInt((/startxref\s+(\d+)/.exec(brut.slice(dernier)) ?? [, '0'])[1] ?? '0', 10);
  const table = brut.slice(depart);
  const xref = [...table.matchAll(/(\d{10}) (\d{5}) ([nf])\s/g)].map((m) => parseInt(m[1]!, 10));

  const objets = new Map<number, { debut: number; corps: string }>();
  for (const m of brut.matchAll(/(\d+) 0 obj\n([\s\S]*?)\nendobj\n/g)) {
    objets.set(parseInt(m[1]!, 10), { debut: m.index!, corps: m[2]! });
  }

  const trailer = brut.slice(brut.lastIndexOf('trailer'));
  const racine = parseInt((/\/Root (\d+) 0 R/.exec(trailer) ?? [, '0'])[1] ?? '0', 10);
  const cat = objets.get(racine)?.corps ?? '';
  const arbre = parseInt((/\/Pages (\d+) 0 R/.exec(cat) ?? [, '0'])[1] ?? '0', 10);
  const pages = [...(objets.get(arbre)?.corps ?? '').matchAll(/(\d+) 0 R/g)]
    .map((m) => parseInt(m[1]!, 10));

  const textes: string[] = [];
  for (const n of pages) {
    const page = objets.get(n)?.corps ?? '';
    const contenu = parseInt((/\/Contents (\d+) 0 R/.exec(page) ?? [, '0'])[1] ?? '0', 10);
    const o = objets.get(contenu);
    if (!o) { continue; }
    const deb = o.corps.indexOf('stream\n') + 7;
    const fin = o.corps.lastIndexOf('\nendstream');
    const flux = Buffer.from(o.corps.slice(deb, fin), 'latin1');
    const clair = /FlateDecode/.test(o.corps)
      ? inflateSync(flux).toString('latin1') : flux.toString('latin1');
    for (const m of clair.matchAll(/\(((?:[^()\\]|\\.)*)\)\s*Tj/g)) {
      textes.push(
        m[1]!.replace(/\\(\d{3})/g, (_, o8: string) => String.fromCharCode(parseInt(o8, 8)))
             .replace(/\\([()\\])/g, '$1')
      );
    }
  }

  const haut: Record<number, string> = {
    0x92: '’', 0x93: '“', 0x94: '”', 0x85: '…',
    0x96: '–', 0x97: '—', 0x9C: 'œ', 0x80: '€', 0xAB: '«', 0xBB: '»',
  };
  const decode = (s: string): string => [...s].map((c) => {
    const o = c.charCodeAt(0);
    return haut[o] ?? (o >= 0xA0 ? Buffer.from([o]).toString('latin1') : c);
  }).join('');

  return { version, objets, xref, pages: pages.length, textes: textes.map(decode) };
}

/* ------------------------------------------------------------------ */
/* Le scénario PHP                                                     */
/* ------------------------------------------------------------------ */

const SCENARIO = `<?php
require ${JSON.stringify(RACINE)} . '/app/bootstrap.php';
foreach (['schema','auth','gabarit','depot','prevol','courriel','og','zip','sauvegarde',
          'texte','regie','carnet','canaux','images','push','qr','icones','avatars',
          'journal','abonnement','pdf','facture','rapport','api'] as $m) {
    require RACINE . "/app/$m.php";
}
assurer_schema();
$sortie = [];

/* ---- les bornes de l'essai : une période fermée, connue au jour près ---- */
$j = static fn(int $jours, string $heure = '12:00:00'): string
    => gmdate('Y-m-d', time() - $jours * 86400) . 'T' . $heure . 'Z';
$du = gmdate('Y-m-d', time() - 10 * 86400);
$au = gmdate('Y-m-d', time() - 1 * 86400);
$sortie['bornes'] = ['du' => $du, 'au' => $au];

/* ---- deux organisateurs, deux décors ---- */
$idA = creer_utilisateur(['email' => 'ama@gala-akwaba.tg', 'mot_de_passe' => 'un-mot-de-passe-solide',
    'nom' => 'Ama Kodjo', 'role' => 'partenaire', 'formule' => 'croissance',
    'organisation' => 'Gala Akwaba']);
$idB = creer_utilisateur(['email' => 'bruno@forum-tech.bj', 'mot_de_passe' => 'un-mot-de-passe-solide',
    'nom' => 'Bruno Sossou', 'role' => 'partenaire', 'formule' => 'croissance',
    'organisation' => 'Forum Tech']);
$A = utilisateur_par_id($idA);
$B = utilisateur_par_id($idB);

$gabarit = json_encode(['version' => 1, 'calques' => []]);
$decor = static function (string $slug, string $titre, string $auteur) use ($gabarit): string {
    $id = nouvel_id();
    db()->prepare('INSERT INTO decors (id, slug, titre, ville, rubrique, statut, cree_par,
                   auteur_id, gabarit, publie_le, evenement_le, cree_le, maj_le)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$id, $slug, $titre, 'lome', 'campagne', 'publie', 'partenaire', $auteur,
                   $gabarit, maintenant(), gmdate('Y-m-d', time() - 2 * 86400),
                   maintenant(), maintenant()]);
    return $id;
};
$decorA = $decor('gala-akwaba-essai', 'Gala Akwaba 2026', $idA);
$decorB = $decor('forum-tech-essai', 'Forum Tech Cotonou', $idB);

/* ---- l'activité du public ---- */
$evenement = static function (string $decor, string $genre, int $n, string $quand): void {
    $s = db()->prepare('INSERT INTO evenements (decor_id, genre, cree_le) VALUES (?,?,?)');
    for ($i = 0; $i < $n; $i++) { $s->execute([$decor, $genre, $quand]); }
};
$evenement($decorA, 'vue', 40, $j(5));
$evenement($decorA, 'telechargement', 8, $j(5));
$evenement($decorA, 'vue', 12, $j(30));      // hors période, avant
$evenement($decorB, 'vue', 7, $j(5));

$badge = static function (string $decor, int $n, string $quand, int $scannes = 0) use ($j): void {
    $s = db()->prepare('INSERT INTO badges (jeton, decor_id, cree_le, scanne_le) VALUES (?,?,?,?)');
    for ($i = 0; $i < $n; $i++) {
        $s->execute([bin2hex(random_bytes(8)), $decor, $quand, $i < $scannes ? $quand : null]);
    }
};
$badge($decorA, 10, $j(5), 5);
/* deux heures distinctes à la porte : la frise doit les distinguer et
   garder l'heure creuse entre les deux. */
db()->prepare('UPDATE badges SET scanne_le = ? WHERE decor_id = ? AND scanne_le IS NOT NULL
               AND jeton IN (SELECT jeton FROM badges WHERE decor_id = ?
                             AND scanne_le IS NOT NULL LIMIT 2)')
    ->execute([gmdate('Y-m-d', time() - 5 * 86400) . 'T21:30:00Z', $decorA, $decorA]);
$badge($decorB, 3, $j(5), 0);

/* ---- les campagnes, et une file dont on connaît chaque ligne ---- */
$campagne = static function (string $titre, string $auteur, ?string $decor,
                             ?string $rappel, string $quand): string {
    $id = nouvel_id();
    db()->prepare('INSERT INTO campagnes_email (id, auteur_id, sujet, titre, corps, cible,
                   decor_id, rappel, statut, envoye_le, cree_le, maj_le)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$id, $auteur, $titre, $titre, 'Bonjour.', 'mes-invites', $decor, $rappel,
                   'envoye', $quand, $quand, $quand]);
    return $id;
};
$envoi = static function (string $campagne, string $canal, string $statut, string $quand,
                          ?string $message = null, ?string $ouvert = null): void {
    db()->prepare('INSERT INTO envois_email (id, campagne_id, email, nom, jeton, statut,
                   message, canal, ouvert_le, envoye_le, cree_le) VALUES (?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([nouvel_id(), $campagne, 'x@exemple.tg', null, bin2hex(random_bytes(12)),
                   $statut, $message, $canal, $ouvert, $quand, $quand]);
};

$cJ1 = $campagne('C’est demain, voici votre badge', $idA, $decorA, 'veille', $j(5));

/* e-mail : 6 partis dont 2 ouverts, 3 échecs de deux natures, 1 écarté, 1 en attente */
for ($i = 0; $i < 6; $i++) { $envoi($cJ1, 'email', 'envoye', $j(5), null, $i < 2 ? $j(5) : null); }
$envoi($cJ1, 'email', 'echec', $j(5), 'Le serveur SMTP a répondu : 550 5.1.1 No such user here.');
$envoi($cJ1, 'email', 'echec', $j(5), 'Le serveur SMTP a répondu : 550 Unknown recipient.');
$envoi($cJ1, 'email', 'echec', $j(5), 'Le serveur SMTP a répondu : 421 Too many connections.');
$envoi($cJ1, 'email', 'desabonne', $j(5));
$envoi($cJ1, 'email', 'attente', $j(5));

/* push, telegram, whatsapp */
for ($i = 0; $i < 4; $i++) { $envoi($cJ1, 'push', 'envoye', $j(5)); }
$envoi($cJ1, 'push', 'echec', $j(5), 'Le service de push a répondu 410.');
$envoi($cJ1, 'push', 'echec', $j(5), 'Le service de push a répondu 410.');
for ($i = 0; $i < 3; $i++) { $envoi($cJ1, 'telegram', 'envoye', $j(5)); }
$envoi($cJ1, 'telegram', 'echec', $j(5), 'Forbidden: bot was blocked by the user');
for ($i = 0; $i < 2; $i++) { $envoi($cJ1, 'whatsapp', 'envoye', $j(5)); }

/* ---- les deux pièges des bornes ---- */
$cBords = $campagne('Aux bornes', $idA, $decorA, null, $j(1, '23:50:00'));
$envoi($cBords, 'email', 'envoye', $du . 'T00:00:10Z');      // premier jour, juste après minuit
$envoi($cBords, 'email', 'envoye', $au . 'T23:50:00Z');      // dernier jour, presque minuit
$envoi($cBords, 'email', 'envoye', gmdate('Y-m-d\\T00:10:00\\Z'));  // aujourd'hui : DEHORS
$envoi($cBords, 'email', 'envoye', $j(30));                  // le mois d'avant : DEHORS

/* ---- l'autre organisateur, dont personne ne doit voir les chiffres ---- */
$cB = $campagne('Forum Tech, c’est parti', $idB, $decorB, null, $j(5));
for ($i = 0; $i < 9; $i++) { $envoi($cB, 'email', 'envoye', $j(5)); }
$envoi($cB, 'email', 'echec', $j(5), 'Le serveur SMTP a répondu : 552 Mailbox full.');

/* ---- un brouillon : il ne doit apparaître nulle part ---- */
db()->prepare('INSERT INTO campagnes_email (id, auteur_id, sujet, titre, corps, cible,
               statut, cree_le, maj_le) VALUES (?,?,?,?,?,?,?,?,?)')
    ->execute([nouvel_id(), $idA, 'Brouillon', 'Un brouillon jamais parti', '.', 'mes-invites',
               'brouillon', $j(5), $j(5)]);

/* ---- un lien court ---- */
db()->prepare('INSERT INTO liens (id, code, cible, titre, auteur_id, decor_id, clics, cree_le)
               VALUES (?,?,?,?,?,?,?,?)')
    ->execute([nouvel_id(), 'GaLa26', 'https://exemple.tg/gala', 'Le gala', $idA, $decorA, 1204, $j(20)]);

/* ================= ce qu'on interroge ================= */

$equipe = db()->query("SELECT * FROM utilisateurs WHERE role = 'super_admin' LIMIT 1")->fetch();
if (!$equipe) {
    $equipe = utilisateur_par_id(creer_utilisateur(['email' => 'chef@wakabileguide.com',
        'mot_de_passe' => 'un-mot-de-passe-solide', 'nom' => 'Chef', 'role' => 'super_admin']));
}

$q = ['periode' => 'libre', 'du' => $du, 'au' => $au];

/* 1. la plateforme entière */
$plateforme = rapport($equipe, $q);
$sortie['plateforme'] = [
    'total' => $plateforme['envois']['total'],
    'canaux' => array_map(static fn(array $l): array => [
        'programmes' => $l['programmes'], 'envoyes' => $l['envoyes'],
        'echecs' => $l['echecs'], 'ecartes' => $l['ecartes'], 'attente' => $l['attente'],
        'ouverts' => $l['ouverts'], 'lecture' => $l['lecture'], 'preuve' => $l['preuve'],
    ], $plateforme['envois']['lignes']),
    'activite' => array_map(static fn(array $a): int => $a['valeur'], $plateforme['activite']),
    'entonnoir' => $plateforme['entonnoir'],
    'echecs' => $plateforme['echecs'],
    'campagnes' => array_map(static fn(array $c): array => [
        'titre' => $c['titre'], 'programmes' => (int) $c['programmes'],
        'moment' => $c['moment'],
    ], $plateforme['campagnes']),
    'decors' => array_map(static fn(array $d): array => [
        'titre' => $d['titre'], 'vues' => (int) $d['vues'], 'badges' => (int) $d['badges'],
        'presences' => (int) $d['presences'],
    ], $plateforme['decors']),
    'libelle' => $plateforme['periode']['libelle'],
];

/* 2. la portée d'un décor */
$sortie['decor'] = (static function () use ($equipe, $q, $decorA) {
    $r = rapport($equipe, $q + ['decor' => 'gala-akwaba-essai']);
    return ['titre' => $r['portee']['titre'], 'cle' => $r['portee']['cle'],
            'total' => $r['envois']['total'], 'decors' => count($r['decors']),
            'liens' => count($r['liens']), 'vues' => $r['activite']['vues']['valeur'],
            'entree' => $r['entree']];
})();

/* 3. la portée d'une campagne */
$sortie['campagne'] = (static function () use ($equipe, $q, $cJ1) {
    $r = rapport($equipe, $q + ['campagne' => $cJ1]);
    return ['cle' => $r['portee']['cle'], 'total' => $r['envois']['total'],
            'entonnoir' => count($r['entonnoir']), 'vues' => $r['activite']['vues']['valeur']];
})();

/* 4. le cloisonnement : B ne voit que B, même en demandant le décor de A */
$rB = rapport($B, $q);
$sortie['entree_plateforme'] = rapport($equipe, $q)['entree'];
$sortie['b_seul'] = ['titre' => $rB['portee']['titre'], 'cle' => $rB['portee']['cle'],
                     'total' => $rB['envois']['total'],
                     'vues' => $rB['activite']['vues']['valeur'],
                     'decors' => array_map(static fn(array $d): string => (string) $d['titre'], $rB['decors'])];
$rBvole = rapport($B, $q + ['decor' => 'gala-akwaba-essai']);
$sortie['b_vole_decor'] = ['titre' => $rBvole['portee']['titre'], 'cle' => $rBvole['portee']['cle'],
                           'total' => $rBvole['envois']['total']['programmes']];
$rBvoleC = rapport($B, $q + ['campagne' => $cJ1]);
$sortie['b_vole_campagne'] = ['cle' => $rBvoleC['portee']['cle'],
                              'total' => $rBvoleC['envois']['total']['programmes']];

/* 5. les périodes toutes faites, et les dates à l'envers */
$sortie['periodes'] = [];
foreach (['mois', 'mois-dernier', 'annee', '12-mois'] as $cle) {
    $p = rapport_periode($cle);
    $sortie['periodes'][$cle] = ['du' => $p['du'], 'au' => $p['au'], 'libelle' => $p['libelle'],
                                 'jours' => $p['jours']];
}
$envers = rapport_periode('libre', $au, $du);
$sortie['envers'] = ['du' => $envers['du'], 'au' => $envers['au']];
$p = rapport_periode('libre', $du, $au);
$sortie['fin_exclusive'] = ['fin' => $p['fin'], 'avant_fin' => $p['avant_fin'],
                            'avant_debut' => $p['avant_debut']];

/* 6. les sorties */
$sortie['csv'] = rapport_csv($plateforme);
file_put_contents(getenv('PDF_RAPPORT'), rapport_pdf($plateforme));
file_put_contents(getenv('PDF_DECOR'), rapport_pdf(rapport($equipe, $q + ['decor' => 'gala-akwaba-essai'])));

echo json_encode($sortie, JSON_UNESCAPED_UNICODE), "\\n";
`;

/* ------------------------------------------------------------------ */

const main = async () => {
  console.log('\n━━ Le rapport : ce qui est parti, et ce qui est arrivé ━━\n');

  const dossier = mkdtempSync(join(tmpdir(), 'wakabi-rapport-'));
  try {
    writeFileSync(join(dossier, 'config.php'), `<?php return ['sgbd' => 'sqlite',
      'dossier_donnees' => ${JSON.stringify(join(dossier, 'donnees'))},
      'fichier' => ${JSON.stringify(join(dossier, 'donnees', 'wakabi.sqlite'))}];`);
    const script = join(dossier, 'scenario.php');
    writeFileSync(script, SCENARIO);

    const chemins = { rapport: join(dossier, 'rapport.pdf'), decor: join(dossier, 'decor.pdf') };
    const { stdout } = await lancer('php', [script], {
      env: { ...process.env, WAKABI_CONFIG: join(dossier, 'config.php'),
             PDF_RAPPORT: chemins.rapport, PDF_DECOR: chemins.decor },
      maxBuffer: 20 * 1024 * 1024,
    });
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const r = JSON.parse(stdout.trim().split('\n').pop() ?? '{}') as Record<string, any>;

    /* ---------------- les bornes ---------------- */
    console.log('  ── la période, au jour près ──');
    const t = r.plateforme?.total ?? {};
    const bords = (r.plateforme?.campagnes ?? []).find((x: any) => x.titre === 'Aux bornes');
    ok('le dernier jour à 23 h 50 est DANS la période, le lendemain n’y est pas',
       bords?.programmes === 2, `la campagne des bornes compte ${bords?.programmes} lignes sur 2`);
    ok('la file entière de la période est retenue, sans une ligne de plus',
       t.programmes === 35, `${t.programmes} lignes retenues au lieu de 35`);
    ok('la borne haute est exclusive et tombe au lendemain minuit',
       typeof r.fin_exclusive?.fin === 'string' && r.fin_exclusive.fin.endsWith('T00:00:00Z'),
       r.fin_exclusive?.fin);
    ok('la période d’avant finit où celle-ci commence, sans trou ni recouvrement',
       r.fin_exclusive?.avant_fin === r.bornes?.du + 'T00:00:00Z', r.fin_exclusive?.avant_fin);
    ok('deux dates à l’envers se remettent dans l’ordre',
       r.envers?.du === r.bornes?.du && r.envers?.au === r.bornes?.au,
       `${r.envers?.du} → ${r.envers?.au}`);
    ok('les périodes toutes faites commencent toutes un 1er',
       ['mois', 'mois-dernier', '12-mois'].every((c) => /-01$/.test(r.periodes?.[c]?.du ?? '')),
       Object.entries(r.periodes ?? {}).map(([c, p]: [string, any]) => `${c}:${p.du}`).join(' '));
    ok('un mois entier se nomme par son mois, pas par ses dates',
       /^[A-ZÉ]/.test(r.periodes?.['mois-dernier']?.libelle ?? '')
       && !/^Du /.test(r.periodes?.['mois-dernier']?.libelle ?? ''),
       r.periodes?.['mois-dernier']?.libelle);
    ok('l’année se nomme par son année', /^Année \d{4}$/.test(r.periodes?.annee?.libelle ?? ''),
       r.periodes?.annee?.libelle);

    /* ---------------- le compte, canal par canal ---------------- */
    console.log('\n  ── ce que chaque canal a fait ──');
    const c = r.plateforme?.canaux ?? {};
    ok('l’e-mail : 23 lignes, 17 parties, 4 échecs, 1 écarté, 1 en attente',
       c.email?.programmes === 23 && c.email?.envoyes === 17 && c.email?.echecs === 4
       && c.email?.ecartes === 1 && c.email?.attente === 1,
       JSON.stringify(c.email));
    ok('le push : 6 lignes, 4 parties, 2 échecs',
       c.push?.programmes === 6 && c.push?.envoyes === 4 && c.push?.echecs === 2);
    ok('Telegram : 4 lignes, 3 parties, 1 échec',
       c.telegram?.programmes === 4 && c.telegram?.envoyes === 3 && c.telegram?.echecs === 1);
    ok('WhatsApp : 2 lignes, 2 parties, aucun échec',
       c.whatsapp?.programmes === 2 && c.whatsapp?.envoyes === 2 && c.whatsapp?.echecs === 0);
    ok('le total recoupe la somme des quatre canaux',
       t.programmes === (c.email?.programmes ?? 0) + (c.push?.programmes ?? 0)
         + (c.telegram?.programmes ?? 0) + (c.whatsapp?.programmes ?? 0));
    ok('les états d’une file s’additionnent au nombre de lignes',
       t.envoyes + t.echecs + t.ecartes + t.attente === t.programmes,
       `${t.envoyes}+${t.echecs}+${t.ecartes}+${t.attente} ≠ ${t.programmes}`);
    ok('un désabonné n’est ni un envoi réussi ni un échec',
       t.ecartes === 1 && t.echecs === 7, `${t.ecartes} écarté(s), ${t.echecs} échec(s)`);
    ok('les ouvertures ne comptent que les lignes qui portent une date',
       t.ouverts === 2 && c.email?.ouverts === 2);

    /* ---------------- ce qu'un canal prouve ---------------- */
    console.log('\n  ── « parti » n’est pas « reçu » ──');
    ok('seul Telegram prouve la remise, et il le dit',
       c.telegram?.preuve?.includes('publié dans la conversation') === true,
       c.telegram?.preuve);
    ok('l’e-mail s’arrête à l’acceptation par le serveur d’en face',
       c.email?.preuve?.includes('accepté par le serveur') === true, c.email?.preuve);
    ok('trois canaux sur quatre n’ont aucune colonne de lecture à remplir',
       c.email?.lecture === 'ouvertures' && c.push?.lecture === null
       && c.telegram?.lecture === null && c.whatsapp?.lecture === null);

    /* ---------------- les échecs ---------------- */
    console.log('\n  ── ce qui n’est pas arrivé ──');
    const ech: any[] = r.plateforme?.echecs ?? [];
    const cinqCinquante = ech.find((e) => e.code === '550');
    ok('deux serveurs qui disent la même chose autrement font UN motif',
       cinqCinquante?.n === 2, `550 compté ${cinqCinquante?.n ?? 0} fois`);
    ok('une adresse qui n’existe pas est dite mortelle, et traduite',
       cinqCinquante?.mortel === true
       && cinqCinquante?.explication === 'L’adresse n’existe pas',
       cinqCinquante?.explication);
    const quatreVingtUn = ech.find((e) => e.code === '421');
    ok('un refus temporaire se relance, et le rapport le dit',
       quatreVingtUn?.mortel === false && quatreVingtUn?.reprenable === true
       && /temporaire|occupé/i.test(quatreVingtUn?.explication ?? ''),
       quatreVingtUn?.explication);
    const pousse = ech.find((e) => e.canal === 'push');
    ok('un abonnement push expiré est expliqué en français',
       /expiré/i.test(pousse?.explication ?? ''), pousse?.explication);
    const tg = ech.find((e) => e.canal === 'telegram');
    ok('un bot bloqué est nommé comme tel',
       /bloqué/i.test(tg?.explication ?? ''), tg?.explication);
    ok('les motifs sont classés du plus nombreux au moins nombreux',
       ech.every((e, i) => i === 0 || ech[i - 1].n >= e.n),
       ech.map((e) => `${e.code}:${e.n}`).join(' '));
    ok('le message brut du serveur est conservé, pas seulement traduit',
       (cinqCinquante?.message ?? '').includes('550'), cinqCinquante?.message);

    /* ---------------- l'activité ---------------- */
    console.log('\n  ── ce que le public a fait ──');
    const a = r.plateforme?.activite ?? {};
    ok('les vues hors période ne sont pas comptées',
       a.vues === 47, `${a.vues} vues au lieu de 47`);
    ok('les badges et les présences se comptent sur leur propre date',
       a.badges === 13 && a.presences === 5, `${a.badges} badges, ${a.presences} présences`);
    const ent: any[] = r.plateforme?.entonnoir ?? [];
    ok('l’entonnoir a ses quatre étapes, de la vue à la porte',
       ent.length === 4 && ent[0].n === 47 && ent[3].n === 5);
    ok('le taux de passage se lit depuis l’étape d’avant',
       Math.abs((ent[3]?.passage ?? 0) - 5 / 8) < 0.001, String(ent[3]?.passage));
    ok('aucune barre ne dépasse sa piste',
       ent.every((e) => e.part <= 1), ent.map((e) => e.part.toFixed(2)).join(' '));

    /* ---------------- campagnes et décors ---------------- */
    console.log('\n  ── les campagnes et les décors ──');
    const camps: any[] = r.plateforme?.campagnes ?? [];
    ok('un brouillon jamais parti n’apparaît pas dans un rapport',
       !camps.some((x) => x.titre.includes('brouillon')),
       camps.map((x) => x.titre).join(' · '));
    ok('les trois campagnes qui ont produit quelque chose sont là',
       camps.length === 3, `${camps.length} campagnes`);
    ok('la somme des campagnes tombe juste sur le total du rapport',
       camps.reduce((n: number, x: any) => n + x.programmes, 0) === t.programmes,
       `${camps.reduce((n: number, x: any) => n + x.programmes, 0)} contre ${t.programmes}`);
    ok('le moment d’un rappel se lit « J − 1 », comme sur un rétroplanning',
       camps.some((x) => x.moment === 'J − 1'), camps.map((x) => x.moment).join(' '));
    const decs: any[] = r.plateforme?.decors ?? [];
    ok('les décors sont classés sur la présence, pas sur les vues',
       decs[0]?.titre === 'Gala Akwaba 2026' && decs[0]?.presences === 5,
       decs.map((d) => `${d.titre}:${d.presences}`).join(' '));
    ok('un décor sans la moindre trace ne fait pas une ligne de zéros',
       decs.length === 2, `${decs.length} décors listés`);

    /* ---------------- les portées ---------------- */
    console.log('\n  ── une portée ne déborde jamais sur une autre ──');
    ok('la portée « décor » ne retient que les campagnes de ce décor',
       r.decor?.cle === 'decor' && r.decor?.total?.programmes === 25,
       `${r.decor?.total?.programmes} lignes au lieu de 25`);
    ok('elle ne relit pas la liste des décors : on est déjà dedans',
       r.decor?.decors === 0);
    ok('elle montre les liens courts du décor', r.decor?.liens === 1);
    const heures: any[] = r.decor?.entree ?? [];
    ok('elle raconte l’entrée heure par heure, de la première à la dernière',
       heures[0]?.heure === '12 h' && heures[0]?.n === 3
       && heures.at(-1)?.heure === '21 h' && heures.at(-1)?.n === 2,
       heures.map((h) => `${h.heure}:${h.n}`).join(' '));
    ok('les heures creuses entre deux heures pleines restent à zéro, elles ne disparaissent pas',
       heures.length === 10 && heures.slice(1, -1).every((h) => h.n === 0),
       `${heures.length} heures listées`);
    ok('agrégée sur toute la plateforme, l’heure d’entrée ne veut rien dire : elle se tait',
       (r.entree_plateforme ?? []).length === 0);
    ok('la portée « campagne » ne parle ni de vues ni d’entonnoir',
       r.campagne?.cle === 'campagne' && r.campagne?.entonnoir === 0
       && r.campagne?.vues === 0);
    ok('elle compte exactement les lignes de sa file',
       r.campagne?.total?.programmes === 23, `${r.campagne?.total?.programmes} au lieu de 23`);

    console.log('\n  ── le cloisonnement ──');
    ok('un organisateur n’obtient que son propre compte',
       r.b_seul?.cle === 'organisateur' && r.b_seul?.titre === 'Forum Tech', r.b_seul?.titre);
    ok('et seulement ses chiffres à lui',
       r.b_seul?.total?.programmes === 10 && r.b_seul?.vues === 7,
       `${r.b_seul?.total?.programmes} lignes, ${r.b_seul?.vues} vues`);
    ok('il ne voit que ses décors', (r.b_seul?.decors ?? []).join('|') === 'Forum Tech Cotonou',
       (r.b_seul?.decors ?? []).join('|'));
    ok('demander le décor d’un autre ne donne pas le décor d’un autre',
       r.b_vole_decor?.cle === 'organisateur' && r.b_vole_decor?.titre === 'Forum Tech'
       && r.b_vole_decor?.total === 10,
       `${r.b_vole_decor?.cle} · ${r.b_vole_decor?.total} lignes`);
    ok('demander la campagne d’un autre non plus',
       r.b_vole_campagne?.cle === 'organisateur' && r.b_vole_campagne?.total === 10,
       `${r.b_vole_campagne?.cle} · ${r.b_vole_campagne?.total} lignes`);

    /* ---------------- le CSV ---------------- */
    console.log('\n  ── l’export du tableur ──');
    const csv: string = r.csv ?? '';
    ok('le fichier commence par le BOM qu’Excel attend', csv.startsWith('﻿'));
    ok('les colonnes sont séparées par des points-virgules',
       csv.includes('Canal;Programmés;Partis;'), csv.split('\r\n')[6] ?? '');
    ok('il porte la période et la portée en tête',
       csv.includes('Rapport;Toute la plateforme') && csv.includes('Du;' + r.bornes?.du));
    ok('il porte les mêmes totaux que l’écran',
       csv.includes('Total;35;26;7;1;1;2;'),
       (csv.split('\r\n').find((l) => l.startsWith('Total')) ?? ''));
    ok('il détaille les échecs avec leur message brut',
       csv.includes('550') && csv.includes('No such user here'));
    ok('une colonne de lecture vide reste vide, elle n’invente pas un zéro',
       (csv.split('\r\n').find((l) => l.startsWith('Telegram;')) ?? '').split(';')[6] === '',
       csv.split('\r\n').find((l) => l.startsWith('Telegram;')));

    /* ---------------- le PDF ---------------- */
    console.log('\n  ── le document qu’on transmet ──');
    const pdf = lirePdf(readFileSync(chemins.rapport));
    ok('c’est un PDF, et il s’annonce comme tel', pdf.version === '1.4', pdf.version);
    ok('il porte plusieurs pages', pdf.pages >= 1, `${pdf.pages} page(s)`);
    ok('chaque décalage de la table tombe sur son objet',
       pdf.xref.slice(1).every((d, i) => pdf.objets.get(i + 1)?.debut === d),
       `${pdf.xref.length - 1} objets`);
    const texte = pdf.textes.join(' | ');
    ok('il nomme la portée et la période',
       texte.includes('Toute la plateforme') && texte.includes(r.bornes?.du?.slice(8) ?? '??'),
       pdf.textes.slice(0, 4).join(' · '));
    ok('il porte le total des messages programmés', texte.includes('35'),
       pdf.textes.filter((x) => /^\d+$/.test(x)).join(' '));
    ok('il nomme les quatre canaux employés',
       ['E-mail', 'Notifications', 'Telegram', 'WhatsApp'].every((n) => texte.includes(n)));
    ok('il écrit, noir sur blanc, qu’aucun nombre ne dit « reçu »',
       texte.includes('ne dit') && texte.includes('reçu'),
       pdf.textes.find((x) => x.includes('reçu')) ?? '—');
    ok('il dit ce que « parti » prouve sur chaque canal',
       texte.includes('publié dans la conversation') && texte.includes('accepté par Meta'));
    ok('il liste les motifs d’échec traduits',
       texte.includes('L’adresse n’existe pas'));
    ok('ses pages sont numérotées, total compris',
       pdf.textes.some((x) => new RegExp(`^Page \\d+ / ${pdf.pages}$`).test(x)),
       pdf.textes.filter((x) => x.startsWith('Page ')).join(' · '));
    ok('chaque page porte le nom du fichier en pied',
       pdf.textes.filter((x) => x.includes('rapport-plateforme')).length === pdf.pages,
       `${pdf.textes.filter((x) => x.includes('rapport-plateforme')).length} pieds`);

    const pdfD = lirePdf(readFileSync(chemins.decor));
    const texteD = pdfD.textes.join(' | ');
    ok('le rapport d’un décor porte son nom et sa date d’événement',
       texteD.includes('Gala Akwaba 2026') && texteD.includes('Événement du'),
       pdfD.textes.slice(0, 5).join(' · '));
    ok('il parle de rappels, pas de campagnes',
       texteD.includes('Les rappels de ce décor'));
    ok('et il ne nomme jamais le décor du voisin',
       !texteD.includes('Forum Tech'));
  } finally {
    rmSync(dossier, { recursive: true, force: true });
  }

  console.log(`\n━━ ${pass} réussis, ${fail} échoués ━━\n`);
  process.exit(fail === 0 ? 0 : 1);
};

void main();
