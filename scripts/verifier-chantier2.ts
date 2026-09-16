/**
 * Les segments, le sponsor, le sondage : ce qu'ils comptent vraiment.
 *
 * Trois fonctionnalités qui n'ont qu'un défaut possible, et c'est le même :
 * annoncer un nombre que la base ne porte pas. « 114 destinataires » puis
 * 96 messages, « 486 badges portant votre logo » quand le logo est sur la
 * page, « 4,3 de moyenne » calculée sur des réponses comptées deux fois.
 * Aucun de ces écarts ne se voit à l'œil : il faut une base dont on connaît
 * le contenu à la personne près, puis recompter.
 *
 * Les pièges tendus exprès :
 *
 *  1. **Deux badges, une personne.** Quelqu'un qui refait son badge depuis
 *     un autre téléphone compte pour UN destinataire, pas deux.
 *  2. **Un désabonné dans le segment.** Il correspond à la règle et ne
 *     doit pas être joignable : un segment ne rouvre pas une porte que la
 *     régie a fermée.
 *  3. **Un badge sans compte.** Il compte dans « correspondent » et dans
 *     aucun canal : c'est l'écart que l'écran doit nommer.
 *  4. **Le même jeton deux fois.** Un lien de sondage transféré à toute la
 *     famille ne vote qu'une fois.
 *  5. **Le décor d'un autre.** Demandé par son slug, il ne se donne pas.
 *
 *   npx tsx scripts/verifier-chantier2.ts
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

/**
 * Le texte d'un PDF, relu sans rien supposer de qui l'a écrit.
 *
 * Les flux sont compressés : on les décompresse, puis on relève ce que les
 * opérateurs `Tj` posent sur la page. C'est le seul moyen de vérifier
 * qu'une phrase est bien DANS le document, et pas seulement dans le code
 * qui prétend l'y mettre.
 */
function texteDuPdf(buf: Buffer): string {
  const brut = buf.toString('latin1');
  const morceaux: string[] = [];
  for (const m of brut.matchAll(/stream\r?\n([\s\S]*?)\r?\nendstream/g)) {
    let flux = m[1]!;
    try {
      flux = inflateSync(Buffer.from(flux, 'latin1')).toString('latin1');
    } catch {
      /* flux non compressé, ou image : on le lit tel quel */
    }
    for (const t of flux.matchAll(/\(((?:[^()\\]|\\.)*)\)\s*Tj/g)) {
      morceaux.push(
        t[1]!
          .replace(/\\([0-7]{3})/g, (_, o: string) => String.fromCharCode(parseInt(o, 8)))
          .replace(/\\([()\\])/g, '$1'),
      );
    }
  }
  // Le PDF écrit en Windows-1252 ; on revient en UTF-8 pour comparer des
  // phrases françaises avec leurs accents.
  return Buffer.from(morceaux.join(' '), 'latin1').toString('utf8')
    .replace(//g, '’').replace(/é/g, 'é');
}

const SCENARIO = `<?php
declare(strict_types=1);
define('RACINE', ${JSON.stringify(RACINE)});
require ${JSON.stringify(RACINE)} . '/app/bootstrap.php';
foreach (['schema','auth','gabarit','depot','prevol','courriel','og','zip','sauvegarde',
          'texte','regie','carnet','canaux','images','push','qr','icones','avatars',
          'journal','abonnement','pdf','facture','rapport','segment','sponsor','sondage',
          'api'] as $m) {
    require RACINE . "/app/$m.php";
}
assurer_schema();
$sortie = [];
$j = static fn(int $jours, string $heure = '12:00:00'): string
    => gmdate('Y-m-d', time() - $jours * 86400) . 'T' . $heure . 'Z';

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

/* ------------------------------------------------------------------ */
/* Les invités, un par un : c'est d'eux que tout se recompte            */
/* ------------------------------------------------------------------ */

$invite = static function (string $nom, bool $confirme = true) use ($j): string {
    $id = creer_utilisateur([
        'email' => $nom . '@invite.tg', 'mot_de_passe' => 'un-mot-de-passe-solide',
        'nom' => ucfirst($nom), 'role' => 'participant',
    ]);
    db()->prepare('UPDATE utilisateurs SET email_verifie_le = ? WHERE id = ?')
        ->execute([$confirme ? $j(20) : null, $id]);
    return $id;
};
$badge = static function (string $decor, ?string $qui, ?string $emporte,
                          ?string $scanne) use ($j): string {
    $jeton = strtoupper(bin2hex(random_bytes(5)));
    db()->prepare('INSERT INTO badges (jeton, decor_id, utilisateur_id, cree_le,
                   telecharge_le, scanne_le) VALUES (?,?,?,?,?,?)')
        ->execute([$jeton, $decor, $qui, $j(9), $emporte, $scanne]);
    return $jeton;
};

/* dix comptes qui ont créé un badge sans jamais l'emporter */
$jamais = [];
for ($i = 1; $i <= 10; $i++) {
    $u = $invite('jamais' . $i);
    $jamais[] = $u;
    $badge($decorA, $u, null, null);
}

/* PIÈGE 1 — le dixième refait son badge depuis un autre téléphone :
   deux lignes, une seule personne. */
$badge($decorA, $jamais[9], null, null);

/* PIÈGE 2 — le neuvième s'est désabonné : il correspond, il n'est pas joignable. */
db()->prepare('INSERT INTO desabonnements (email, motif, cree_le) VALUES (?,?,?)')
    ->execute(['jamais9@invite.tg', 'essai', $j(3)]);

/* PIÈGE 3 — quatre badges sans compte du tout : hors d'atteinte. */
for ($i = 0; $i < 4; $i++) { $badge($decorA, null, null, null); }

/* six qui ont emporté leur badge et ne sont pas venus */
for ($i = 1; $i <= 6; $i++) {
    $badge($decorA, $invite('parti' . $i), $j(8), null);
}
/* cinq qui sont venus */
$venus = [];
for ($i = 1; $i <= 5; $i++) {
    $u = $invite('venu' . $i);
    $venus[] = $u;
    $badge($decorA, $u, $j(8), $j(2, '20:30:00'));
}
/* le décor du voisin, qu'aucun segment de A ne doit voir */
for ($i = 1; $i <= 7; $i++) { $badge($decorB, $invite('voisin' . $i), null, null); }

/* un abonnement aux notifications pour deux des « jamais emporté » */
$abonner = static function (?string $qui, ?string $decor) use ($j): void {
    db()->prepare('INSERT INTO push (id, empreinte, utilisateur_id, decor_id, endpoint,
                   p256dh, auth, cree_le) VALUES (?,?,?,?,?,?,?,?)')
        ->execute([nouvel_id(), bin2hex(random_bytes(10)), $qui, $decor,
                   'https://push.exemple/x', 'cle', 'auth', $j(9)]);
};
$abonner($jamais[0], $decorA);
$abonner($jamais[1], $decorA);
/* un abonnement ANONYME pris sous le décor : il n'entre dans aucun segment. */
$abonner(null, $decorA);

/* ------------------------------------------------------------------ */
/* Ce que les segments comptent                                        */
/* ------------------------------------------------------------------ */

foreach (array_keys(SEGMENTS) as $cle) {
    $sortie['segments'][$cle] = segment_compte($cle, $decorA);
    $sortie['emails'][$cle] = count(segment_emails($cle, $decorA));
}
$sortie['push_segment'] = count(segment_push('non-emporte', $decorA));
$sortie['liste'] = count(segment_liste('non-emporte', $decorA));
$sortie['csv'] = segment_csv(decor_par_id($decorA), 'non-emporte',
    segment_liste('non-emporte', $decorA));

/* PIÈGE 5 — le décor du voisin, demandé par son slug. */
$sortie['cloison'] = [
    'sien' => segment_decor($A, 'gala-akwaba-essai') !== null,
    'autre' => segment_decor($A, 'forum-tech-essai') !== null,
    'equipe' => segment_decor(['id' => 'x', 'role' => 'super_admin'], 'forum-tech-essai') !== null,
];

/* Une campagne de segment : la règle est rejouée au moment de figer. */
$idCamp = campagne_email_creer([
    'auteur_id' => $idA, 'sujet' => 'Il vous manque un clic', 'titre' => 'Votre badge vous attend',
    'corps' => 'Vous avez commencé votre badge : il ne vous reste qu’un clic pour l’emporter.',
    'lien' => '', 'lien_libelle' => '', 'cible' => 'segment', 'liste' => '', 'liste_id' => '',
    'canaux' => '[{"canal":"email"}]', 'decor_id' => $decorA, 'segment' => 'non-emporte',
]);
$camp = campagne_email($idCamp);
$sortie['campagne_avant'] = count(regie_destinataires($camp, $A));
/* Quelqu'un emporte son badge entre le brouillon et l'envoi. */
db()->prepare('UPDATE badges SET telecharge_le = ? WHERE utilisateur_id = ? AND decor_id = ?')
    ->execute([maintenant(), $jamais[0], $decorA]);
$sortie['campagne_apres'] = count(regie_destinataires($camp, $A));
$sortie['campagne_autre_decor'] = count(regie_destinataires(
    ['cible' => 'segment', 'decor_id' => $decorB, 'segment' => 'non-emporte'], $A));
/* On remet ce badge comme il était : la suite compte dessus. */
db()->prepare('UPDATE badges SET telecharge_le = NULL WHERE utilisateur_id = ? AND decor_id = ?')
    ->execute([$jamais[0], $decorA]);

/* ------------------------------------------------------------------ */
/* Le sponsor                                                          */
/* ------------------------------------------------------------------ */

$vue = db()->prepare('INSERT INTO evenements (decor_id, genre, cree_le) VALUES (?,?,?)');
for ($i = 0; $i < 40; $i++) { $vue->execute([$decorA, 'vue', $j(9)]); }

$d = decor_par_id($decorA);
$r1 = sponsor_enregistrer($d, $A, ['nom' => 'Brasserie du Golfe',
    'lien' => 'https://brasseriedugolfe.tg', 'logo' => '']);
$d = decor_par_id($decorA);
$sortie['sponsor_relire'] = ['message' => $r1['relire'], 'statut' => $d['sponsor_statut'],
                             'url' => (string) (sponsor_de($d)['url'] ?? '')];

/* Un lien chez nous ne demande aucune relecture : le garde-fou l'autorise déjà. */
$d2 = decor_par_id($decorB);
sponsor_enregistrer($d2, $B, ['nom' => 'Wakabi', 'lien' => 'https://wakabileguide.com/x', 'logo' => '']);
$sortie['sponsor_maison'] = decor_par_id($decorB)['sponsor_statut'];

/* La maison approuve celui de A. */
sponsor_valider(decor_par_id($decorA), ['id' => 'equipe', 'nom' => 'Lyder', 'role' => 'super_admin']);
$d = decor_par_id($decorA);
$s = sponsor_de($d);
$sortie['sponsor_valide'] = ['statut' => $s['statut'], 'url_non_vide' => $s['url'] !== ''];

/* Trois clics sur son lien court. */
for ($i = 0; $i < 3; $i++) { suivre_lien((string) $d['sponsor_code']); }

/* Le code ne change pas quand on corrige le nom : le compteur non plus. */
$avant = (string) $d['sponsor_code'];
sponsor_enregistrer($d, $A, ['nom' => 'Brasserie du Golfe SA',
    'lien' => 'https://brasseriedugolfe.tg', 'logo' => '']);
$d = decor_par_id($decorA);
$sortie['sponsor_code_stable'] = $avant === (string) $d['sponsor_code'];
sponsor_valider($d, ['id' => 'equipe', 'nom' => 'Lyder', 'role' => 'super_admin']);
$d = decor_par_id($decorA);

$sortie['sponsor_chiffres'] = sponsor_chiffres($d);
$sortie['sponsor_fichier'] = sponsor_nom_fichier($d);
file_put_contents(getenv('PDF_SPONSOR'), sponsor_pdf($d, sponsor_chiffres($d)));

/* ------------------------------------------------------------------ */
/* Le sondage du lendemain                                             */
/* ------------------------------------------------------------------ */

db()->prepare('UPDATE decors SET sondage = 1 WHERE id = ?')->execute([$decorA]);

$campSondage = static function (string $decor, string $auteur, ?string $rappel,
                                int $marque) use ($j): string {
    $id = nouvel_id();
    db()->prepare('INSERT INTO campagnes_email (id, auteur_id, sujet, titre, corps, cible,
                   decor_id, rappel, sondage, statut, envoye_le, cree_le, maj_le)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([$id, $auteur, 'Merci', 'Merci d’être venu', 'C’était une belle soirée.',
                   'mes-invites', $decor, $rappel, $marque, 'envoye', $j(1), $j(1), $j(1)]);
    return $id;
};
$cMerci = $campSondage($decorA, $idA, 'merci', 0);
$cAutre = $campSondage($decorA, $idA, 'veille', 0);   /* pas le lendemain : pas de sondage */

$poser = static function (string $campagne, string $email, string $jeton,
                          string $statut = 'envoye') use ($j): void {
    db()->prepare('INSERT INTO envois_email (id, campagne_id, email, nom, jeton, statut,
                   canal, envoye_le, cree_le) VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([nouvel_id(), $campagne, $email, null, $jeton, $statut, 'email',
                   $statut === 'envoye' ? $j(1) : null, $j(1)]);
};
/* huit partis, dont cinq répondront ; un échec qui ne compte pas au dénominateur */
for ($i = 1; $i <= 8; $i++) { $poser($cMerci, 'venu' . min($i, 5) . '@invite.tg', 'JET' . $i); }
$poser($cMerci, 'rate@invite.tg', 'JETRATE', 'echec');
$poser($cAutre, 'venu1@invite.tg', 'JETVEILLE');

/* Le corps du message porte le lien, et seulement sur l'e-mail. */
$sortie['lien_mail'] = str_contains(
    regie_corps_pour(campagne_email($cMerci), ['jeton' => 'JET1', 'canal' => 'email']), 'p=sondage');
$sortie['lien_push'] = str_contains(
    regie_corps_pour(campagne_email($cMerci), ['jeton' => 'JET1', 'canal' => 'push']), 'p=sondage');
$sortie['lien_veille'] = str_contains(
    regie_corps_pour(campagne_email($cAutre), ['jeton' => 'JETVEILLE', 'canal' => 'email']), 'p=sondage');

/* Les réponses : deux « venus », trois non. */
$reponses = [
    ['JET1', 5, 'oui', 'Belle soirée, la musique était juste.'],
    ['JET2', 4, 'oui', ''],
    ['JET3', 4, 'non', 'Trop de monde à l’entrée.'],
    ['JET4', 2, 'non', ''],
    ['JET5', 5, 'oui', 'On revient l’an prochain, c’est sûr.'],
];
foreach ($reponses as [$jeton, $note, $revient, $mot]) {
    $ctx = sondage_contexte($jeton);
    $sortie['ouverture'][$jeton] = $ctx !== null;
    if ($ctx) { sondage_repondre($ctx, ['note' => $note, 'revient' => $revient, 'mot' => $mot]); }
}

/* PIÈGE 4 — le même jeton, une seconde fois. */
$ctx = sondage_contexte('JET1');
$sortie['deja'] = $ctx !== null && $ctx['deja'] !== null;
$sortie['double'] = sondage_repondre($ctx, ['note' => 1, 'revient' => 'non', 'mot' => 'encore']);

/* Une note hors barème est refusée. */
$sortie['hors_bareme'] = sondage_repondre(sondage_contexte('JET6'),
    ['note' => 9, 'revient' => 'oui', 'mot' => ''])['ok'];

/* Un rappel qui n'est pas celui du lendemain n'ouvre rien. */
$sortie['veille_fermee'] = sondage_contexte('JETVEILLE') === null;
/* Un jeton inconnu non plus. */
$sortie['inconnu_ferme'] = sondage_contexte('PAS-UN-JETON') === null;
/* Sondage fermé sur le décor : les jetons se ferment avec lui. */
db()->prepare('UPDATE decors SET sondage = 0 WHERE id = ?')->execute([$decorA]);
$sortie['ferme'] = sondage_contexte('JET7') === null;
db()->prepare('UPDATE decors SET sondage = 1 WHERE id = ?')->execute([$decorA]);

$portee = ['cle' => 'decor', 'decor' => decor_par_id($decorA), 'auteur_id' => $idA,
           'campagne' => null, 'plateforme' => false, 'titre' => 'x', 'detail' => 'x'];
$sortie['sondage'] = rapport_sondage($portee);
$sortie['sondage_voisin'] = rapport_sondage(
    ['cle' => 'decor', 'decor' => decor_par_id($decorB), 'auteur_id' => $idB,
     'campagne' => null, 'plateforme' => false, 'titre' => 'x', 'detail' => 'x'])['reponses'];

/* Le rapport du décor, avec son bloc de sondage, en PDF. */
$equipe = ['id' => 'equipe', 'nom' => 'Lyder', 'role' => 'super_admin',
           'organisation' => 'Wakabi', 'formule' => 'impact'];
file_put_contents(getenv('PDF_RAPPORT'),
    rapport_pdf(rapport($equipe, ['periode' => '12-mois', 'decor' => 'gala-akwaba-essai'])));

echo json_encode($sortie, JSON_UNESCAPED_UNICODE), "\\n";
`;

/* ------------------------------------------------------------------ */

const main = async () => {
  console.log('\n━━ Les segments, le sponsor, le sondage ━━\n');

  const dossier = mkdtempSync(join(tmpdir(), 'wakabi-chantier2-'));
  try {
    writeFileSync(join(dossier, 'config.php'), `<?php return ['sgbd' => 'sqlite',
      'dossier_donnees' => ${JSON.stringify(join(dossier, 'donnees'))},
      'fichier' => ${JSON.stringify(join(dossier, 'donnees', 'wakabi.sqlite'))}];`);
    const script = join(dossier, 'scenario.php');
    writeFileSync(script, SCENARIO);

    const chemins = { sponsor: join(dossier, 'sponsor.pdf'), rapport: join(dossier, 'rapport.pdf') };
    const { stdout } = await lancer('php', [script], {
      env: { ...process.env, WAKABI_CONFIG: join(dossier, 'config.php'),
             PDF_SPONSOR: chemins.sponsor, PDF_RAPPORT: chemins.rapport },
      maxBuffer: 20 * 1024 * 1024,
    });
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const r = JSON.parse(stdout.trim().split('\n').pop() ?? '{}') as Record<string, any>;

    /* ---------------- les segments ---------------- */
    console.log('  ── un segment : qui correspond, qui est joignable ──');
    const sg = r.segments ?? {};

    ok('« jamais emporté » compte les personnes, pas les lignes de badge',
       sg['non-emporte']?.correspondent === 14,
       `${sg['non-emporte']?.correspondent} au lieu de 14 : 10 comptes et 4 sans compte, `
       + `le onzième badge étant une seconde ligne du même invité`);
    ok('les quatre badges sans compte ne sont joignables par aucun canal',
       sg['non-emporte']?.hors === 5,
       `${sg['non-emporte']?.hors} hors d’atteinte au lieu de 5 (4 sans compte + 1 désabonné)`);
    ok('un désabonné correspond à la règle et ne reçoit rien',
       sg['non-emporte']?.email === 9, `${sg['non-emporte']?.email} adresses au lieu de 9`);
    ok('les notifications comptent les comptes abonnés, pas les abonnements anonymes',
       sg['non-emporte']?.push === 2, `${sg['non-emporte']?.push} au lieu de 2`);
    ok('joignables = ceux qu’au moins un canal atteint',
       sg['non-emporte']?.joignables === 9,
       `${sg['non-emporte']?.joignables} au lieu de 9`);
    ok('correspondent = joignables + hors d’atteinte, sans reste',
       sg['non-emporte']?.correspondent === sg['non-emporte']?.joignables + sg['non-emporte']?.hors);

    ok('« emporté, pas venu » ne retient que les badges emportés et non scannés',
       sg['emporte-absent']?.correspondent === 6, `${sg['emporte-absent']?.correspondent} au lieu de 6`);
    ok('« est venu » ne retient que les badges scannés',
       sg['venu']?.correspondent === 5, `${sg['venu']?.correspondent} au lieu de 5`);
    ok('« tous les invités » est la somme des trois autres',
       sg['tous']?.correspondent === 25,
       `${sg['tous']?.correspondent} au lieu de 25 (14 + 6 + 5)`);
    ok('le décor du voisin n’entre dans aucun segment',
       sg['tous']?.correspondent === 25 && (sg['tous']?.correspondent ?? 0) < 32);

    ok('la liste exportable porte AUSSI les non-joignables',
       r.liste === 15, `${r.liste} lignes au lieu de 15 (les badges, pas les personnes)`);
    ok('le CSV sort avec le BOM et les points-virgules qu’attend un tableur',
       typeof r.csv === 'string' && r.csv.startsWith('﻿')
       && r.csv.includes('Nom;Adresse;Joignable par e-mail;'),
       (r.csv ?? '').slice(1, 60));
    ok('le CSV nomme le segment en toutes lettres',
       (r.csv ?? '').includes('A créé un badge, ne l’a jamais téléchargé'));

    console.log('\n  ── un segment n’est pas une liste ──');
    ok('la règle est rejouée au moment de figer la liste, pas à l’écriture',
       r.campagne_avant === 9 && r.campagne_apres === 8,
       `${r.campagne_avant} avant, ${r.campagne_apres} après que quelqu’un a emporté son badge`);
    ok('une campagne qui désigne le décor d’un autre ne touche personne',
       r.campagne_autre_decor === 0, `${r.campagne_autre_decor} destinataire(s)`);
    ok('les notifications d’un segment suivent les mêmes comptes',
       r.push_segment === 2, `${r.push_segment} abonnement(s) au lieu de 2`);

    console.log('\n  ── le cloisonnement ──');
    ok('un organisateur ouvre son décor', r.cloison?.sien === true);
    ok('il n’ouvre pas celui d’un autre, même par son slug', r.cloison?.autre === false);
    ok('l’équipe, elle, les ouvre tous', r.cloison?.equipe === true);

    /* ---------------- le sponsor ---------------- */
    console.log('\n  ── le sponsor, et ce que son document promet ──');
    ok('un lien hors de nos domaines part en relecture',
       r.sponsor_relire?.message === true && r.sponsor_relire?.statut === 'a_relire',
       `statut « ${r.sponsor_relire?.statut} »`);
    ok('tant qu’il n’est pas relu, il n’a pas d’adresse cliquable',
       r.sponsor_relire?.url === '');
    ok('un lien qui mène chez nous n’attend personne',
       r.sponsor_maison === 'valide', `statut « ${r.sponsor_maison} »`);
    ok('une fois relu, le lien court devient l’adresse du sponsor',
       r.sponsor_valide?.statut === 'valide' && r.sponsor_valide?.url_non_vide === true);
    ok('corriger le nom du sponsor ne refabrique pas son lien, donc ne remet pas son compteur à zéro',
       r.sponsor_code_stable === true);

    const ch = r.sponsor_chiffres ?? {};
    ok('les vues de la page sont celles du décor', ch.vues === 40, `${ch.vues} au lieu de 40`);
    ok('les badges créés sont ceux du décor', ch.badges === 26, `${ch.badges} au lieu de 26`);
    ok('les badges emportés ne comptent que ceux qui portent une date',
       ch.emportes === 11, `${ch.emportes} au lieu de 11`);
    ok('les présences sont celles de la porte', ch.presences === 5, `${ch.presences} au lieu de 5`);
    ok('les clics sont ceux du lien court, et eux seuls',
       ch.clics === 3, `${ch.clics} au lieu de 3`);
    ok('l’entonnoir ne dépasse jamais sa piste',
       (ch.entonnoir ?? []).every((p: any) => p.part <= 1),
       JSON.stringify((ch.entonnoir ?? []).map((p: any) => p.part)));
    ok('le fichier se nomme d’après le sponsor et le décor',
       r.sponsor_fichier === 'exposition-brasserie-du-golfe-sa-gala-akwaba-essai.pdf',
       r.sponsor_fichier);

    const pdfSponsor = readFileSync(chemins.sponsor);
    const texteSponsor = texteDuPdf(pdfSponsor);
    ok('le document s’annonce comme un PDF',
       pdfSponsor.subarray(0, 5).toString() === '%PDF-', `${pdfSponsor.length} octets`);
    ok('il porte le nom du sponsor', texteSponsor.includes('Brasserie du Golfe'));
    ok('il dit lui-même qu’il compte des occasions de voir, pas des regards',
       texteSponsor.includes('occasions de voir'));
    ok('il ne prétend pas que les badges portent le logo',
       !/badges? portant/i.test(texteSponsor));
    ok('il est numéroté', /Page 1 \/ 1/.test(texteSponsor));

    /* ---------------- le sondage ---------------- */
    console.log('\n  ── le sondage du lendemain ──');
    ok('le lien part sur l’e-mail', r.lien_mail === true);
    ok('il ne part pas sur les notifications, où un lien serait partagé',
       r.lien_push === false);
    ok('un rappel qui n’est pas celui du lendemain n’en porte pas',
       r.lien_veille === false);
    ok('les cinq jetons ouvrent le sondage',
       Object.values(r.ouverture ?? {}).filter(Boolean).length === 5,
       JSON.stringify(r.ouverture));
    ok('un jeton déjà servi se reconnaît', r.deja === true);
    ok('et une seconde réponse est refusée',
       r.double?.ok === false, r.double?.message);
    ok('une note hors barème est refusée', r.hors_bareme === false);
    ok('un rappel de la veille n’ouvre rien', r.veille_fermee === true);
    ok('un jeton inconnu n’ouvre rien, et ne dit pas qu’il est inconnu',
       r.inconnu_ferme === true);
    ok('fermer le sondage ferme les jetons avec lui', r.ferme === true);

    const so = r.sondage ?? {};
    ok('cinq réponses comptées, pas six', so.reponses === 5, `${so.reponses} réponses`);
    ok('la moyenne est celle des notes données',
       so.moyenne === 4, `${so.moyenne} au lieu de 4 ((5+4+4+2+5)/5)`);
    ok('« reviendront » se calcule sur ceux qui ont répondu à cette question-là',
       Math.round((so.revient ?? 0) * 100) === 60, `${Math.round((so.revient ?? 0) * 100)} % au lieu de 60`);
    ok('le taux de réponse a pour dénominateur les messages PARTIS, pas les programmés',
       so.partis === 8 && Math.round((so.taux ?? 0) * 100) === 63,
       `${so.reponses}/${so.partis} = ${Math.round((so.taux ?? 0) * 100)} %`);
    ok('la distribution porte les cinq notes, y compris celles à zéro',
       Object.keys(so.distribution ?? {}).length === 5
       && so.distribution?.['3'] === 0 && so.distribution?.['4'] === 2,
       JSON.stringify(so.distribution));
    ok('la distribution s’additionne au nombre de réponses',
       Object.values(so.distribution ?? {}).reduce((a: number, b) => a + (b as number), 0)
         === so.reponses);
    ok('les mots libres sont là, et seulement ceux qui ont été écrits',
       (so.mots ?? []).length === 3, `${(so.mots ?? []).length} mots au lieu de 3`);
    ok('« venu » est recopié à la réponse, depuis le badge scanné',
       (so.mots ?? []).every((m: any) => m.venu === 1),
       JSON.stringify((so.mots ?? []).map((m: any) => m.venu)));
    ok('chaque mot sait si son auteur est venu, sans porter son nom',
       (so.mots ?? []).every((m: any) => 'venu' in m && !('email' in m) && !('nom' in m)),
       Object.keys((so.mots ?? [])[0] ?? {}).join(', '));
    ok('le sondage d’un décor ne déborde pas sur celui du voisin',
       r.sondage_voisin === 0, `${r.sondage_voisin} réponse(s) chez le voisin`);

    const pdfRapport = readFileSync(chemins.rapport);
    const texteRapport = texteDuPdf(pdfRapport);
    ok('le rapport en PDF porte le bloc du sondage',
       texteRapport.includes('Ce qu') && texteRapport.includes('en ont pens'));
    ok('il porte les mots libres, tous',
       texteRapport.includes('Trop de monde') && texteRapport.includes('Belle soir'));
    ok('il ne porte le nom de personne',
       !texteRapport.includes('venu1@invite.tg') && !texteRapport.includes('Venu1'));
  } finally {
    rmSync(dossier, { recursive: true, force: true });
  }

  console.log(`\n━━ Résultat : ${pass} réussis, ${fail} échoués ━━\n`);
  process.exitCode = fail ? 1 : 0;
};

main();
