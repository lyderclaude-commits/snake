/**
 * Le carnet d'adresses, éprouvé du collage jusqu'au destinataire.
 *
 * Ce vérifieur ne regarde pas des écrans : il joue le trajet complet d'une
 * adresse — collée dans une campagne, rangée dans une liste, corrigée,
 * archivée, sortie de la liste — et vérifie à chaque étape QUI recevrait le
 * message si la campagne partait maintenant. C'est la seule question qui
 * compte, et c'est celle qu'un test d'écran ne pose jamais vraiment.
 *
 * Il tourne sur une installation SQLite jetable, comme le vérifieur de
 * restauration : les gestes qu'on y fait — archiver, supprimer, migrer un
 * ancien collage — abîmeraient les données de la recette.
 */

import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { mkdtempSync, writeFileSync, rmSync } from 'node:fs';
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
 * Le collage d'épreuve : tout ce qu'un vrai utilisateur colle vraiment.
 *
 * Un export de tableur, un copier-coller depuis un client de messagerie,
 * une ligne écrite à la main, un doublon en majuscules, une adresse déjà
 * désabonnée, et deux lignes qui ne sont pas des adresses du tout. Un
 * corpus propre ne prouverait rien : ce sont ces lignes-là qui décident si
 * l'utilisateur recommence sa saisie ou non.
 */
const COLLAGE = [
  'ama@exemple.tg',
  'Kossi Mensah <kossi@exemple.tg>',
  '"Afi Doe" <afi@exemple.tg>',
  'yao@exemple.tg (Yao Adjo)',
  'AMA@EXEMPLE.TG',                       // doublon, casse différente
  'Koffi Ade\tkoffi@exemple.tg',          // colonne de tableur
  'zed@exemple.tg; nina@exemple.tg',      // deux sur une ligne
  'pas une adresse du tout',
  '',
  'desabonne@exemple.tg',
].join('\n');

const SCENARIO = `<?php
require ${JSON.stringify(RACINE)} . '/app/bootstrap.php';
foreach (['schema','auth','gabarit','depot','prevol','courriel','og','zip','sauvegarde',
          'texte','regie','carnet','images','push','qr','icones','avatars','journal',
          'abonnement','api'] as $m) {
    require RACINE . "/app/$m.php";
}
assurer_schema();
$sortie = [];

$org = creer_utilisateur(['email' => 'organisateur@essai.tg', 'mot_de_passe' => 'un-mot-de-passe-solide',
    'nom' => 'Organisateur', 'role' => 'partenaire', 'formule' => 'mouvement']);
$autre = creer_utilisateur(['email' => 'autre@essai.tg', 'mot_de_passe' => 'un-mot-de-passe-solide',
    'nom' => 'Un Autre', 'role' => 'partenaire', 'formule' => 'mouvement']);
$moi = utilisateur_par_id($org);

/* ---- 1. ce que le lecteur tire d'un collage réel ---- */
$collage = ${JSON.stringify(COLLAGE)};
$lues = adresses_du_texte($collage);
$sortie['lecture'] = ['adresses' => array_keys($lues), 'noms' => $lues];

/* ---- 2. l'import : les adresses deviennent des fiches ---- */
desabonner('desabonne@exemple.tg', 'recette');
$liste = carnet_liste_poser($org, 'Invités du Gala');
$sortie['import'] = carnet_importer($org, $liste, $collage);

/* ---- 3. ré-importer le MÊME collage n'ajoute rien ---- */
$sortie['reimport'] = carnet_importer($org, $liste, $collage);
$sortie['apres_reimport'] = carnet_combien($org, ['etat' => 'toutes']);

/* ---- 4. une campagne qui vise cette liste ---- */
$camp = campagne_email_creer([
    'auteur_id' => $org, 'sujet' => 'On remet ça', 'titre' => 'On remet ça',
    'corps' => str_repeat('Un texte assez long pour passer la validation. ', 3),
    'lien' => '', 'lien_libelle' => '', 'cible' => 'liste', 'liste' => '', 'liste_id' => $liste,
]);
$sortie['vises'] = array_keys(regie_destinataires(campagne_email($camp), $moi));

/* ---- 5. corriger une fiche corrige la campagne ---- */
$fiche = carnet_par_email($org, 'kossi@exemple.tg');
carnet_contact_maj((string) $fiche['id'], ['email' => 'kossi.mensah@exemple.tg', 'nom' => 'Kossi Mensah']);
$sortie['apres_correction'] = array_keys(regie_destinataires(campagne_email($camp), $moi));

/* ---- 6. archiver n'efface pas, mais retire de l'envoi ---- */
carnet_contact_archiver((string) carnet_par_email($org, 'ama@exemple.tg')['id'], true);
$sortie['apres_archive'] = [
    'vises' => array_keys(regie_destinataires(campagne_email($camp), $moi)),
    'au_carnet' => carnet_combien($org, ['etat' => 'toutes']),
    'actives' => carnet_combien($org, ['etat' => 'actives']),
];

/* ---- 7. sortir de la liste garde la fiche ---- */
$afi = carnet_par_email($org, 'afi@exemple.tg');
carnet_detacher((string) $afi['id'], $liste);
$sortie['apres_retrait'] = [
    'vises' => array_keys(regie_destinataires(campagne_email($camp), $moi)),
    'fiche_encore_la' => carnet_par_email($org, 'afi@exemple.tg') !== null,
    'listes_de_la_fiche' => count(carnet_listes_du_contact((string) $afi['id'])),
];

/* ---- 8. supprimer la liste rend les fiches au carnet ---- */
$avant_suppression = carnet_combien($org, ['etat' => 'toutes']);
$liste2 = carnet_liste_poser($org, 'À jeter');
carnet_attacher((string) $afi['id'], $liste2);
carnet_liste_supprimer($liste2);
$sortie['liste_supprimee'] = [
    'fiches_gardees' => carnet_combien($org, ['etat' => 'toutes']) === $avant_suppression,
    'listes_restantes' => count(carnet_listes($org)),
];

/* ---- 9. le carnet d'un compte n'est pas celui d'un autre ---- */
carnet_importer($autre, carnet_liste_poser($autre, 'Chez moi'), 'ama@exemple.tg');
$sortie['cloison'] = [
    'chez_lui' => carnet_combien($autre, ['etat' => 'toutes']),
    'chez_moi' => carnet_combien($org, ['etat' => 'toutes']),
    'liste_a_lui_vue_par_moi' => carnet_liste_de(carnet_listes($autre)[0]['id'], $org) === null,
];

/* ---- 10. deux fiches ne peuvent pas porter la même adresse ---- */
try {
    carnet_contact_maj((string) carnet_par_email($org, 'yao@exemple.tg')['id'],
        ['email' => 'zed@exemple.tg']);
    $sortie['doublon'] = 'ACCEPTÉ';
} catch (Throwable $e) {
    $sortie['doublon'] = $e->getMessage();
}

/* ---- 11. la reprise v12 : un vieux collage devient une liste ---- */
$vieille = campagne_email_creer([
    'auteur_id' => $org, 'sujet' => 'Ancienne campagne', 'titre' => 'Ancienne',
    'corps' => 'Un texte.', 'lien' => '', 'lien_libelle' => '',
    'cible' => 'liste', 'liste' => "vieux1@exemple.tg\\nVieux Deux <vieux2@exemple.tg>", 'liste_id' => '',
]);
db()->prepare('UPDATE campagnes_email SET liste_id = NULL WHERE id = ?')->execute([$vieille]);
sauver_listes_collees(db());
$reprise = campagne_email($vieille);
$sortie['reprise'] = [
    'pointe_une_liste' => ($reprise['liste_id'] ?? '') !== '',
    'nom' => $reprise['liste_id'] ? (string) carnet_liste((string) $reprise['liste_id'])['nom'] : '',
    'vises' => array_keys(regie_destinataires($reprise, $moi)),
];
// Idempotente : la rejouer ne fabrique pas une deuxième liste.
$listes_avant = count(carnet_listes($org));
sauver_listes_collees(db());
$sortie['reprise']['idempotente'] = count(carnet_listes($org)) === $listes_avant;

/* ---- 12. alimenter une liste depuis une audience calculée ---- */
$depuis = carnet_liste_poser($org, 'Mes invités, figés');
$sortie['alimenter'] = carnet_alimenter($moi, $depuis, 'mes-invites');
try {
    carnet_alimenter($moi, $depuis, 'tous');
    $sortie['alimenter_interdit'] = 'ACCEPTÉ';
} catch (Throwable $e) {
    $sortie['alimenter_interdit'] = $e->getMessage();
}

echo json_encode($sortie, JSON_UNESCAPED_UNICODE), "\\n";
`;

const main = async () => {
  console.log('\n━━ Le carnet d’adresses, du collage au destinataire ━━\n');

  const dossier = mkdtempSync(join(tmpdir(), 'wakabi-carnet-'));
  writeFileSync(join(dossier, 'config.php'), `<?php return ['sgbd' => 'sqlite',
    'dossier_donnees' => ${JSON.stringify(join(dossier, 'donnees'))},
    'fichier' => ${JSON.stringify(join(dossier, 'donnees', 'wakabi.sqlite'))}];`);
  const script = join(dossier, 'scenario.php');
  writeFileSync(script, SCENARIO);

  const { stdout } = await lancer('php', [script], {
    env: { ...process.env, WAKABI_CONFIG: join(dossier, 'config.php') },
    maxBuffer: 20 * 1024 * 1024,
  });
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const r = JSON.parse(stdout.trim().split('\n').pop() ?? '{}') as Record<string, any>;

  /* ---- ce que le lecteur comprend ---- */
  const lues: string[] = r.lecture?.adresses ?? [];
  ok('les huit adresses distinctes du collage sont lues', lues.length === 8, lues.join(' '));
  ok('« Nom <adresse> » rend le nom', r.lecture?.noms?.['kossi@exemple.tg'] === 'Kossi Mensah');
  ok('les guillemets autour du nom sont retirés', r.lecture?.noms?.['afi@exemple.tg'] === 'Afi Doe');
  ok('« adresse (Nom) » est reconnu', r.lecture?.noms?.['yao@exemple.tg'] === 'Yao Adjo');
  ok('une colonne de tableur est reconnue', r.lecture?.noms?.['koffi@exemple.tg'] === 'Koffi Ade');
  ok('deux adresses sur une ligne sont séparées',
     lues.includes('zed@exemple.tg') && lues.includes('nina@exemple.tg'));
  ok('la même adresse en majuscules ne compte qu’une fois',
     lues.filter((a) => a === 'ama@exemple.tg').length === 1);
  ok('une ligne illisible est sautée, pas refusée', !lues.some((a) => a.includes(' ')));

  /* ---- l'import ---- */
  ok('l’import range huit fiches', r.import?.neuves === 8, `${r.import?.neuves} nouvelle(s)`);
  ok('il compte les lignes illisibles', (r.import?.illisibles ?? 0) >= 1, `${r.import?.illisibles}`);
  ok('ré-importer le même collage n’ajoute rien',
     r.reimport?.neuves === 0 && r.reimport?.connues === 8,
     `${r.reimport?.neuves} nouvelle(s), ${r.reimport?.connues} connue(s)`);
  ok('le carnet en garde bien huit', r.apres_reimport === 8, String(r.apres_reimport));

  /* ---- qui recevrait le message ---- */
  const vises: string[] = r.vises ?? [];
  ok('la campagne vise les sept adresses non désabonnées', vises.length === 7, `${vises.length}`);
  ok('la désabonnée est écartée', !vises.includes('desabonne@exemple.tg'));
  ok('corriger une adresse corrige la campagne',
     (r.apres_correction ?? []).includes('kossi.mensah@exemple.tg')
     && !(r.apres_correction ?? []).includes('kossi@exemple.tg'));

  /* ---- les trois gestes, et leurs trois effets ---- */
  ok('archiver retire de l’envoi', !(r.apres_archive?.vises ?? []).includes('ama@exemple.tg'));
  ok('archiver ne supprime pas la fiche', r.apres_archive?.au_carnet === 8,
     `${r.apres_archive?.au_carnet} au carnet, ${r.apres_archive?.actives} active(s)`);
  ok('sortir de la liste retire de l’envoi',
     !(r.apres_retrait?.vises ?? []).includes('afi@exemple.tg'));
  ok('sortir de la liste garde la fiche au carnet',
     r.apres_retrait?.fiche_encore_la === true && r.apres_retrait?.listes_de_la_fiche === 0);
  ok('supprimer une liste rend ses fiches au carnet',
     r.liste_supprimee?.fiches_gardees === true, `${r.liste_supprimee?.listes_restantes} liste(s) restante(s)`);

  /* ---- ce qui doit être refusé ---- */
  ok('le carnet d’un compte est invisible à l’autre',
     r.cloison?.chez_lui === 1 && r.cloison?.chez_moi === 8
     && r.cloison?.liste_a_lui_vue_par_moi === true,
     `${r.cloison?.chez_lui} chez lui, ${r.cloison?.chez_moi} chez moi`);
  ok('deux fiches ne peuvent pas porter la même adresse',
     typeof r.doublon === 'string' && r.doublon !== 'ACCEPTÉ' && /déjà/.test(r.doublon),
     String(r.doublon).slice(0, 54));
  ok('un organisateur ne peut pas se servir dans la base du guide',
     typeof r.alimenter_interdit === 'string' && r.alimenter_interdit !== 'ACCEPTÉ',
     String(r.alimenter_interdit).slice(0, 44));

  /* ---- la reprise des anciens collages ---- */
  ok('un ancien collage devient une liste nommée',
     r.reprise?.pointe_une_liste === true && r.reprise?.nom === 'Ancienne campagne',
     String(r.reprise?.nom));
  ok('la campagne reprise vise les mêmes gens qu’avant',
     (r.reprise?.vises ?? []).length === 2,
     (r.reprise?.vises ?? []).join(' '));
  ok('rejouer la reprise ne fabrique pas de doublon', r.reprise?.idempotente === true);

  /* ---- alimenter depuis une audience ---- */
  ok('alimenter depuis une audience répond sans erreur',
     typeof r.alimenter?.total === 'number', `${r.alimenter?.total} adresse(s)`);

  rmSync(dossier, { recursive: true, force: true });

  console.log(`\n━━ ${pass} réussis, ${fail} échoués ━━\n`);
  process.exit(fail === 0 ? 0 : 1);
};

void main();
