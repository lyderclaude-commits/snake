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

  /* ------------------------------------------------------------------ */
  /* Une base NEUVE doit avoir les mêmes colonnes qu'une base migrée     */
  /* ------------------------------------------------------------------ */

  /**
   * Le piège que ce contrôle referme.
   *
   * Deux fonctions bâtissent le schéma : `creer_schema()` pour une
   * installation neuve, `migrer_schema()` pour une installation déjà en
   * service. Une colonne ajoutée à la seconde et oubliée dans la première
   * donne une base qui marche chez tous les anciens — donc en
   * développement, où la base traîne depuis des mois — et qui casse à la
   * PREMIÈRE installation propre. C'est-à-dire chez le client qui
   * découvre le produit, sur son premier décor.
   *
   * C'est arrivé en v1.1 : `decors.evenement_le` et les quatre colonnes de
   * campagne n'existaient que dans les ALTER. Le contrôle ci-dessous
   * n'aurait pas laissé partir le paquet.
   */
  const dossierNeuf = mkdtempSync(join(tmpdir(), 'wakabi-schema-'));
  const fichierNeuf = join(dossierNeuf, 'controle.php');
  writeFileSync(fichierNeuf, `<?php
    require ${JSON.stringify(RACINE)} . '/app/bootstrap.php';
    require RACINE . '/app/schema.php';
    // Les migrations de DONNEES appellent le reste de l'application :
    // sans elles, migrer_schema() s'arrete avant d'avoir tout tente, et
    // le controle passerait pour de mauvaises raisons.
    require RACINE . '/app/gabarit.php';
    require RACINE . '/app/auth.php';
    require RACINE . '/app/depot.php';
    require RACINE . '/app/carnet.php';

    // Une base VIERGE, bâtie par le seul chemin des installations neuves.
    creer_schema(db(), false);

    $avant = [];
    foreach (db()->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN) as $t) {
      foreach (db()->query('PRAGMA table_info(' . $t . ')')->fetchAll() as $c) {
        $avant[] = $t . '.' . $c['name'];
      }
    }

    // Puis on lui passe TOUTES les migrations. Sur une base neuve, aucune
    // ne doit avoir quoi que ce soit à ajouter.
    migrer_schema(db(), false);

    $apres = [];
    foreach (db()->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN) as $t) {
      foreach (db()->query('PRAGMA table_info(' . $t . ')')->fetchAll() as $c) {
        $apres[] = $t . '.' . $c['name'];
      }
    }
    echo json_encode(['manquantes' => array_values(array_diff($apres, $avant))]), "\n";
  `);
  writeFileSync(join(dossierNeuf, 'config.php'), `<?php return [
    'sgbd' => 'sqlite',
    'dossier_donnees' => ${JSON.stringify(dossierNeuf)},
    'fichier' => ${JSON.stringify(join(dossierNeuf, 'neuve.sqlite'))},
  ];`);
  const envNeuf = { ...process.env, WAKABI_CONFIG: join(dossierNeuf, 'config.php') };
  let manquantes: string[] = [];
  try {
    const brut = (await lancer('php', [fichierNeuf], { env: envNeuf })).stdout.trim().split('\n').pop() ?? '{}';
    manquantes = (JSON.parse(brut) as { manquantes: string[] }).manquantes ?? ['<illisible>'];
  } catch (e: any) {
    manquantes = ['<le contrôle a échoué : ' + String(e?.message ?? e).slice(0, 60) + '>'];
  }
  ok('une installation NEUVE a déjà toutes les colonnes que les migrations ajoutent',
     manquantes.length === 0, manquantes.slice(0, 4).join(', '));

  /* ------------------------------------------------------------------ */
  /* La montée en v1.2 ne doit pas vider l'audience d'un client          */
  /* ------------------------------------------------------------------ */

  /**
   * Ce que cette migration protège.
   *
   * À partir de la v1.2, une adresse jamais confirmée ne reçoit plus de
   * campagne. Appliquée telle quelle à une installation en service, la
   * règle ferait disparaître l'audience du jour au lendemain : des
   * milliers de comptes créés avant qu'une confirmation existe, et qui
   * reçoivent du courrier depuis des mois.
   *
   * Une adresse qui a DÉJÀ reçu un message sans rebondir est prouvée
   * bonne — c'est exactement ce que la confirmation cherche à établir. La
   * migration la crédite donc de sa preuve, une fois. Celles qui n'ont
   * jamais rien reçu et n'ont jamais confirmé sortent, et c'est le but.
   */
  const dossierMaj = mkdtempSync(join(tmpdir(), 'wakabi-maj-'));
  writeFileSync(join(dossierMaj, 'config.php'), `<?php return [
    'sgbd' => 'sqlite',
    'dossier_donnees' => ${JSON.stringify(dossierMaj)},
    'fichier' => ${JSON.stringify(join(dossierMaj, 'ancienne.sqlite'))},
  ];`);
  const fichierMaj = join(dossierMaj, 'controle.php');
  writeFileSync(fichierMaj, `<?php
    require ${JSON.stringify(RACINE)} . '/app/bootstrap.php';
    require RACINE . '/app/schema.php';
    require RACINE . '/app/gabarit.php';
    require RACINE . '/app/auth.php';
    require RACINE . '/app/depot.php';
    require RACINE . '/app/carnet.php';
    creer_schema(db(), false);

    // Une base d'avant : personne n'a confirmé quoi que ce soit.
    db()->exec("UPDATE utilisateurs SET email_verifie_le = NULL");
    $now = maintenant();
    $poser = static function (string $email) use ($now) {
      db()->prepare('INSERT INTO utilisateurs (id, nom, email, mot_de_passe, role, formule,
                     email_verifie_le, suspendu, cree_le) VALUES (?,?,?,?,?,?,NULL,0,?)')
        ->execute([nouvel_id(), 'Ancien', $email, 'x', 'participant', 'decouverte', $now]);
    };
    $poser('servie@exemple.tg');
    $poser('rebondie@exemple.tg');
    $poser('jamais-touchee@exemple.tg');

    $camp = nouvel_id();
    db()->prepare('INSERT INTO campagnes_email (id, sujet, titre, corps, cible, statut, cree_le, maj_le)
                   VALUES (?,?,?,?,?,?,?,?)')
      ->execute([$camp, 'Ancienne', 'Ancienne', 'Corps', 'tous', 'envoye', $now, $now]);
    $ins = db()->prepare('INSERT INTO envois_email (id, campagne_id, email, jeton, statut, canal, cree_le)
                          VALUES (?,?,?,?,?,?,?)');
    $ins->execute([nouvel_id(), $camp, 'servie@exemple.tg', bin2hex(random_bytes(16)), 'envoye', 'email', $now]);
    $ins->execute([nouvel_id(), $camp, 'rebondie@exemple.tg', bin2hex(random_bytes(16)), 'echec', 'email', $now]);

    migrer_schema(db(), false);

    $lire = static function (string $email) {
      $s = db()->prepare('SELECT email_verifie_le FROM utilisateurs WHERE email = ?');
      $s->execute([$email]);
      return (string) ($s->fetchColumn() ?: '');
    };
    echo json_encode([
      'servie' => $lire('servie@exemple.tg') !== '',
      'rebondie' => $lire('rebondie@exemple.tg') !== '',
      'jamais' => $lire('jamais-touchee@exemple.tg') !== '',
    ]), "\n";
  `);
  const envMaj = { ...process.env, WAKABI_CONFIG: join(dossierMaj, 'config.php') };
  let maj: Record<string, boolean> = {};
  try {
    maj = JSON.parse(
      (await lancer('php', [fichierMaj], { env: envMaj })).stdout.trim().split('\n').pop() ?? '{}',
    ) as Record<string, boolean>;
  } catch (e: any) {
    ok('la migration v1.2 s’exécute', false, String(e?.message ?? e).slice(0, 70));
  }
  ok('une adresse déjà servie sans rebond est créditée de sa preuve', maj.servie === true);
  ok('une adresse qui a rebondi ne l’est pas', maj.rebondie === false);
  ok('une adresse jamais touchée non plus — et c’est le but', maj.jamais === false);

  rmSync(dossierMaj, { recursive: true, force: true });
  rmSync(dossierNeuf, { recursive: true, force: true });
  rmSync(dossier, { recursive: true, force: true });

  console.log(`\n━━ ${pass} réussis, ${fail} échoués ━━\n`);
  process.exit(fail === 0 ? 0 : 1);
};

void main();
