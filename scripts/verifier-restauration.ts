/**
 * La restauration d'une sauvegarde, éprouvée pour de vrai.
 *
 * On ne peut pas le faire dans la recette : remettre une archive en place
 * remplacerait la base sous les pieds des autres scénarios. On monte donc
 * une installation à part, on la garnit, on l'abîme volontairement, et
 * l'on vérifie que la restauration la ramène — comptes, décors, cadres,
 * médias, et le cadrage écrit dans les articles.
 *
 * C'est le seul geste du produit qui détruit des données. Le vérifier
 * autrement qu'en le faisant serait se raconter une histoire : une
 * sauvegarde qu'on ne sait pas restaurer sous pression n'est qu'à moitié
 * une sauvegarde.
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
 * Le scénario, écrit en PHP et joué dans un processus à lui.
 *
 * Il rend une ligne JSON par étape : c'est le seul moyen d'observer une
 * base qu'on est en train de remplacer, sans que l'observateur fasse
 * partie de ce qu'il observe.
 */
const SCENARIO = `<?php
require ${JSON.stringify(RACINE)} . '/app/bootstrap.php';
foreach (['schema','auth','gabarit','depot','prevol','courriel','og','zip','sauvegarde',
          'texte','regie','images','push','qr','icones','avatars','journal','abonnement','api'] as $m) {
    require RACINE . "/app/$m.php";
}
assurer_schema();
$sortie = [];

/* ---- 1. un état de départ reconnaissable ---- */
$admin = creer_utilisateur(['email' => 'fondateur@essai.tg', 'mot_de_passe' => 'un-mot-de-passe-solide',
    'nom' => 'Fondateur AVANT', 'role' => 'equipe', 'formule' => 'decouverte']);
$cadre = 'aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee.png';
file_put_contents(dossier_cadres() . '/' . $cadre, 'CADRE-ORIGINAL');
$media = 'bbbbbbbb-cccc-4ddd-8eee-ffffffffffff.png';
file_put_contents(dossier_medias() . '/' . $media, 'MEDIA-ORIGINAL');
$article = article_creer([
    'slug' => 'article-avant', 'titre' => 'Article AVANT', 'chapo' => '',
    'corps' => "Un texte.\\n\\n![La photo](?p=media&f=$media&c=100-100-800-600&t=60)",
    'couverture' => url('?p=media&f=' . $media),
    'auteur_id' => null, 'auteur_nom' => 'Recette',
]);
$sortie['depart'] = [
    'comptes' => (int) db()->query('SELECT COUNT(*) FROM utilisateurs')->fetchColumn(),
    'articles' => (int) db()->query('SELECT COUNT(*) FROM articles')->fetchColumn(),
];

/* ---- 2. l'archive ---- */
$archive = ecrire_sauvegarde(dossier_sauvegardes() . '/instantane.zip');
$sortie['archive'] = ['octets' => (int) filesize($archive)];

/* ---- 3. ce que l'inspection annonce, SANS rien toucher ---- */
$vu = inspecter_sauvegarde($archive);
$sortie['inspection'] = $vu;
$sortie['inspection_sans_effet'] = [
    'comptes' => (int) db()->query('SELECT COUNT(*) FROM utilisateurs')->fetchColumn(),
];

/* ---- 4. on abîme l'installation, comme le ferait un accident ---- */
db()->exec('DELETE FROM utilisateurs');
db()->exec('DELETE FROM articles');
creer_utilisateur(['email' => 'intrus@essai.tg', 'mot_de_passe' => 'un-mot-de-passe-solide',
    'nom' => 'Compte APRÈS', 'role' => 'equipe', 'formule' => 'decouverte']);
@unlink(dossier_cadres() . '/' . $cadre);
file_put_contents(dossier_medias() . '/' . $media, 'MEDIA-ABIME');
$sortie['abime'] = [
    'comptes' => (int) db()->query('SELECT COUNT(*) FROM utilisateurs')->fetchColumn(),
    'nom' => (string) db()->query('SELECT nom FROM utilisateurs LIMIT 1')->fetchColumn(),
    'articles' => (int) db()->query('SELECT COUNT(*) FROM articles')->fetchColumn(),
];

/* ---- 5. la restauration ---- */
$bilan = restaurer_sauvegarde($archive);
$sortie['bilan'] = $bilan;
$sortie['apres'] = [
    'comptes' => (int) db()->query('SELECT COUNT(*) FROM utilisateurs')->fetchColumn(),
    'nom' => (string) db()->query('SELECT nom FROM utilisateurs LIMIT 1')->fetchColumn(),
    'articles' => (int) db()->query('SELECT COUNT(*) FROM articles')->fetchColumn(),
    'corps' => (string) db()->query("SELECT corps FROM articles LIMIT 1")->fetchColumn(),
    'cadre' => is_file(dossier_cadres() . '/' . $cadre)
        ? file_get_contents(dossier_cadres() . '/' . $cadre) : null,
    'media' => is_file(dossier_medias() . '/' . $media)
        ? file_get_contents(dossier_medias() . '/' . $media) : null,
    'filet_present' => is_file(dossier_sauvegardes() . '/' . $bilan['filet']),
];

/* ---- 6. une archive qui n'en est pas une ---- */
$faux = dossier_sauvegardes() . '/pas-une-archive.zip';
file_put_contents($faux, 'ceci n’est pas un zip');
try {
    inspecter_sauvegarde($faux);
    $sortie['faux'] = 'ACCEPTÉ';
} catch (Throwable $e) {
    $sortie['faux'] = $e->getMessage();
}

/* ---- 7. une archive d'un AUTRE moteur ---- */
$sortie['moteur_courant'] = est_mysql() ? 'mysql' : 'sqlite';

echo json_encode($sortie, JSON_UNESCAPED_UNICODE), "\\n";
`;

const main = async () => {
  console.log('\n━━ Restaurer une sauvegarde, pour de vrai ━━\n');

  const dossier = mkdtempSync(join(tmpdir(), 'wakabi-resto-'));
  const config = join(dossier, 'config.php');
  writeFileSync(config, `<?php return ['sgbd' => 'sqlite',
    'dossier_donnees' => ${JSON.stringify(join(dossier, 'donnees'))},
    'fichier' => ${JSON.stringify(join(dossier, 'donnees', 'wakabi.sqlite'))}];`);

  const script = join(dossier, 'scenario.php');
  writeFileSync(script, SCENARIO);

  const { stdout } = await lancer('php', [script], {
    env: { ...process.env, WAKABI_CONFIG: config },
    maxBuffer: 20 * 1024 * 1024,
  });
  const derniere = stdout.trim().split('\n').pop() ?? '{}';
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  const r = JSON.parse(derniere) as Record<string, any>;

  /* ---- ce que l'archive contient ---- */
  ok('l’archive s’écrit', (r.archive?.octets ?? 0) > 1000,
     `${Math.round((r.archive?.octets ?? 0) / 1024)} Ko`);
  ok('l’inspection annonce le bon moteur', r.inspection?.moteur === 'sqlite',
     String(r.inspection?.moteur));
  ok('elle compte les comptes sans les restaurer',
     r.inspection?.comptes === r.depart?.comptes,
     `${r.inspection?.comptes} annoncé(s), ${r.depart?.comptes} en base`);
  ok('elle compte les articles', r.inspection?.articles === r.depart?.articles,
     `${r.inspection?.articles}`);
  ok('elle ne touche à rien', r.inspection_sans_effet?.comptes === r.depart?.comptes);
  ok('elle joint la notice', typeof r.inspection?.notice === 'string'
     && r.inspection.notice.includes('RESTAURER'));

  /* ---- l'installation abîmée ---- */
  ok('l’installation a bien été abîmée',
     r.abime?.nom === 'Compte APRÈS' && r.abime?.articles === 0,
     `${r.abime?.comptes} compte(s), ${r.abime?.articles} article(s)`);

  /* ---- et la restauration ---- */
  ok('la restauration prend un filet d’abord',
     typeof r.bilan?.filet === 'string' && r.bilan.filet.startsWith('avant-restauration-')
     && r.apres?.filet_present === true, String(r.bilan?.filet));
  ok('les tables sont revenues', (r.bilan?.tables ?? 0) >= 15, `${r.bilan?.tables} table(s)`);
  ok('les comptes sont ceux de l’archive',
     r.apres?.nom === 'Fondateur AVANT' && r.apres?.comptes === r.depart?.comptes,
     `${r.apres?.comptes} × « ${r.apres?.nom} »`);
  ok('les articles aussi', r.apres?.articles === r.depart?.articles);
  ok('le cadre effacé est revenu, au dernier octet',
     r.apres?.cadre === 'CADRE-ORIGINAL', String(r.apres?.cadre));
  ok('le média écrasé est revenu à sa version d’avant',
     r.apres?.media === 'MEDIA-ORIGINAL', String(r.apres?.media));
  ok('le cadrage écrit dans l’article a survécu',
     typeof r.apres?.corps === 'string' && r.apres.corps.includes('&c=100-100-800-600&t=60'));

  /* ---- et ce qui doit être refusé ---- */
  ok('un fichier qui n’est pas une archive est refusé',
     typeof r.faux === 'string' && r.faux !== 'ACCEPTÉ'
     && /archive|lisible|incomplète/i.test(r.faux), String(r.faux).slice(0, 60));

  rmSync(dossier, { recursive: true, force: true });

  console.log(`\n━━ ${pass} réussis, ${fail} échoués ━━\n`);
  process.exit(fail === 0 ? 0 : 1);
};

void main();
