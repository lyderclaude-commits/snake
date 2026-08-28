/**
 * La sauvegarde, mise à l'épreuve de la seule question qui compte : est-ce
 * qu'elle se RESTAURE ?
 *
 * Une archive qu'on n'a jamais remise en place n'est pas une sauvegarde,
 * c'est un fichier. On en fabrique donc une pour de bon, on la décompresse,
 * on la recharge dans une base VIDE, et on compare table par table ce qu'on
 * retrouve à ce qu'on avait mis. Les deux moteurs sont éprouvés : SQLite
 * toujours, MySQL si une base d'essai répond.
 *
 *   npx tsx scripts/verifier-sauvegarde.ts
 */
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { mkdtempSync, writeFileSync, rmSync, existsSync, readdirSync, statSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const lancer = promisify(execFile);
const PHP = join(process.cwd(), 'php');

/**
 * Les bases d'essai sont créées et détruites en tant qu'administrateur.
 *
 * Le compte applicatif n'a de droits que sur SA base — c'est ainsi qu'il
 * doit être en production. Lui en donner davantage pour la commodité d'un
 * test reviendrait à tester une configuration que personne n'aura.
 */
const ADMIN_SQL = ['-uroot'];
const sql = (requete: string) => lancer('mysql', [...ADMIN_SQL, '-e', requete]);

/** Le compte de l'application : celui qu'aura la production. */
const COMPTE = { utilisateur: 'wakabi', motdepasse: 'wakabi-2026' };

let pass = 0;
let fail = 0;
const ok = (label: string, cond: boolean, detail = '') => {
  cond ? pass++ : fail++;
  console.log(`  ${cond ? '✓' : '✗'} ${label}${detail ? ' — ' + detail : ''}`);
};

/** Le préambule commun : charger l'application avec une config donnée. */
const preambule = `<?php
  require ${JSON.stringify(PHP)} . '/app/bootstrap.php';
  require RACINE . '/app/schema.php';
  require RACINE . '/app/gabarit.php';
  require RACINE . '/app/auth.php';
  require RACINE . '/app/depot.php';
  require RACINE . '/app/qr.php';
  require RACINE . '/app/zip.php';
  require RACINE . '/app/sauvegarde.php';
`;

async function php(dossier: string, corps: string, config: string): Promise<string> {
  const f = join(dossier, 'x.php');
  writeFileSync(f, preambule + corps);
  const env = { ...process.env, WAKABI_CONFIG: config };
  try {
    const r = await lancer('php', [f], { env, maxBuffer: 32 * 1024 * 1024 });
    return r.stdout + r.stderr;
  } catch (e: any) {
    return (e.stdout ?? '') + (e.stderr ?? '');
  }
}

/** Remplit une installation neuve de quoi être reconnaissable ensuite. */
const GARNIR = `
  creer_schema(db(), est_mysql());
  $id = creer_utilisateur([
    'email' => 'ama@exemple.tg', 'mot_de_passe' => 'un-mot-de-passe-solide',
    'nom' => "Ama Koffi — accents, apostrophe ', guillemet \\" et antislash \\\\\\\\",
    'role' => 'equipe',
  ]);
  for ($i = 0; $i < 3; $i++) {
    db()->prepare('INSERT INTO notifications (id, utilisateur_id, genre, titre, corps, lien, cree_le)
                   VALUES (?,?,?,?,?,?,?)')
        ->execute([nouvel_id(), $id, 'essai', "Ligne $i", "Un corps\\navec un saut de ligne", null, maintenant()]);
  }
  reglages_bdd_poser(['courriel_nom' => 'Wakabi « Boost »']);
  file_put_contents(dossier_cadres() . '/faux-cadre.png', str_repeat("\\x89PNG donnees binaires \\x00\\xff", 400));
  echo 'CADRE ', filesize(dossier_cadres() . '/faux-cadre.png'), "\\n";
`;

/** L'empreinte d'une base : de quoi comparer avant et après. */
const EMPREINTE = `
  $tables = est_mysql()
    ? db()->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)
    : db()->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
  sort($tables);
  $out = [];
  foreach ($tables as $t) {
    $out[$t] = (int) db()->query('SELECT COUNT(*) FROM ' . $t)->fetchColumn();
  }
  $u = utilisateur_par_email('ama@exemple.tg');
  echo json_encode([
    'tables' => $out,
    'nom' => $u['nom'] ?? null,
    'reglage' => reglages_bdd(['courriel_nom'])['courriel_nom'] ?? null,
  ], JSON_UNESCAPED_UNICODE), "\\n";
`;

interface Empreinte {
  tables: Record<string, number>;
  nom: string | null;
  reglage: string | null;
}

function config(dossier: string, mysql: { base: string } | null): string {
  const chemin = join(dossier, 'config.php');
  writeFileSync(chemin, mysql
    ? `<?php return ['sgbd' => 'mysql', 'hote' => '127.0.0.1', 'port' => 3306,
        'base' => ${JSON.stringify(mysql.base)}, 'utilisateur' => ${JSON.stringify(COMPTE.utilisateur)},
        'motdepasse' => ${JSON.stringify(COMPTE.motdepasse)},
        'dossier_donnees' => ${JSON.stringify(dossier)}, 'base_url' => 'https://exemple.tg'];`
    : `<?php return ['sgbd' => 'sqlite', 'dossier_donnees' => ${JSON.stringify(dossier)},
        'fichier' => ${JSON.stringify(join(dossier, 'wakabi.sqlite'))}, 'base_url' => 'https://exemple.tg'];`);
  return chemin;
}

/* ------------------------------------------------------------------ */

async function eprouver(nom: string, mysqlBases: [string, string] | null) {
  console.log(`\n━━ ${nom} ━━\n`);

  const source = mkdtempSync(join(tmpdir(), 'wakabi-sauv-src-'));
  const cible = mkdtempSync(join(tmpdir(), 'wakabi-sauv-dst-'));
  const cfgSource = config(source, mysqlBases ? { base: mysqlBases[0] } : null);
  const cfgCible = config(cible, mysqlBases ? { base: mysqlBases[1] } : null);

  // Les deux bases repartent de zéro : l'épreuve n'a de sens que si la
  // cible est VIDE, comme l'est celle de quelqu'un qui vient de tout perdre.
  if (mysqlBases) {
    await sql(mysqlBases.map((b) =>
      `DROP DATABASE IF EXISTS \`${b}\`; CREATE DATABASE \`${b}\` DEFAULT CHARSET utf8mb4;`
      + ` GRANT ALL ON \`${b}\`.* TO '${COMPTE.utilisateur}'@'localhost';`
      + ` GRANT ALL ON \`${b}\`.* TO '${COMPTE.utilisateur}'@'127.0.0.1';`).join(' ')
      + ' FLUSH PRIVILEGES;');
  }

  const garni = await php(source, GARNIR, cfgSource);
  // La taille attendue vient du fichier écrit, pas d'un calcul recopié dans
  // le test : deux arithmétiques, c'est une de trop, et c'est celle du test
  // qui se trompe.
  const tailleCadre = Number(garni.match(/CADRE (\d+)/)?.[1] ?? 0);
  const avant: Empreinte = JSON.parse((await php(source, EMPREINTE, cfgSource)).trim().split('\n').pop()!);
  ok('la base de départ est garnie', (avant.tables['notifications'] ?? 0) === 3,
     `${avant.tables['notifications'] ?? 0} notifications`);

  /* --- fabriquer l'archive --- */
  const archive = join(source, 'sauvegarde.zip');
  const sortie = await php(source, `
    try {
      $f = ecrire_sauvegarde(${JSON.stringify(archive)});
      echo 'TAILLE ', filesize($f), "\\n";
    } catch (Throwable $e) { echo 'ECHEC ', $e->getMessage(), "\\n"; }
  `, cfgSource);
  ok('l’archive s’écrit', sortie.includes('TAILLE'), sortie.trim().split('\n').pop() ?? '');
  if (!existsSync(archive)) {
    console.log('  ✗ pas d’archive : la suite n’a plus de sens');
    fail++;
    return;
  }

  /* --- un vrai unzip la relit --- */
  const extrait = join(source, 'extrait');
  try {
    await lancer('unzip', ['-tq', archive]);
    ok('unzip la déclare intacte', true);
  } catch (e: any) {
    ok('unzip la déclare intacte', false, (e.stdout ?? e.message ?? '').slice(0, 90));
  }
  await lancer('unzip', ['-qo', archive, '-d', extrait]);
  const dedans = readdirSync(extrait);
  ok('la notice de restauration est jointe', dedans.includes('LISEZ-MOI.txt'));
  const cadreRestaure = existsSync(join(extrait, 'cadres/faux-cadre.png'))
    ? statSync(join(extrait, 'cadres/faux-cadre.png')).size : 0;
  ok('les cadres téléversés sont dedans, au dernier octet',
     tailleCadre > 0 && cadreRestaure === tailleCadre,
     `${cadreRestaure} sur ${tailleCadre} octets`);
  ok('config.php n’est PAS dans l’archive', !dedans.includes('config.php'),
     'il décrit le serveur et n’a rien à faire dans un fichier qui circule');

  /* --- restaurer dans une installation VIDE --- */
  if (mysqlBases) {
    ok('le dump MySQL est joint', dedans.includes('base.sql'));
    // Comme phpMyAdmin le ferait : le fichier tel quel, dans une base vide.
    // Importé par le compte de l'application, pas par l'administrateur :
    // c'est lui qui fera le geste depuis phpMyAdmin.
    const importe = await lancer('bash', ['-c',
      `mysql -h127.0.0.1 -u${COMPTE.utilisateur} -p${COMPTE.motdepasse} ${mysqlBases[1]}`
      + ` < ${JSON.stringify(join(extrait, 'base.sql'))} 2>&1`]);
    ok('l’import ne rend aucune erreur', importe.stdout.trim() === '', importe.stdout.trim().slice(0, 90));
  } else {
    ok('la base SQLite est jointe', dedans.includes('wakabi.sqlite'));
    await lancer('cp', [join(extrait, 'wakabi.sqlite'), join(cible, 'wakabi.sqlite')]);
  }

  const apres: Empreinte = JSON.parse((await php(cible, EMPREINTE, cfgCible)).trim().split('\n').pop()!);

  ok('toutes les tables sont revenues',
     JSON.stringify(Object.keys(avant.tables)) === JSON.stringify(Object.keys(apres.tables)),
     `${Object.keys(apres.tables).length} sur ${Object.keys(avant.tables).length}`);
  const manquantes = Object.entries(avant.tables)
    .filter(([t, n]) => (apres.tables[t] ?? -1) !== n)
    .map(([t, n]) => `${t} ${apres.tables[t] ?? '?'}≠${n}`);
  ok('chaque table a retrouvé ses lignes', manquantes.length === 0, manquantes.join(', '));
  ok('les accents et les guillemets ont survécu', apres.nom === avant.nom,
     JSON.stringify(apres.nom)?.slice(0, 70));
  ok('les réglages sont restaurés', apres.reglage === 'Wakabi « Boost »', String(apres.reglage));

  rmSync(source, { recursive: true, force: true });
  rmSync(cible, { recursive: true, force: true });
}

const main = async () => {
  await eprouver('SQLite — l’archive porte la base entière', null);

  // MySQL n'est pas toujours là : on l'éprouve s'il répond, on le dit sinon.
  let mysql = false;
  try {
    await sql('SELECT 1');
    mysql = true;
  } catch { /* pas de serveur MySQL sous la main */ }

  if (mysql) {
    await eprouver('MySQL — le dump se réimporte dans une base vide', ['wakabi_sauv_src', 'wakabi_sauv_dst']);
    await sql('DROP DATABASE IF EXISTS wakabi_sauv_src; DROP DATABASE IF EXISTS wakabi_sauv_dst;').catch(() => {});
  } else {
    console.log('\n  · MySQL injoignable : seule la sauvegarde SQLite a été éprouvée.');
  }

  console.log(`\n━━ ${pass} réussis, ${fail} échoués ━━\n`);
  process.exit(fail ? 1 : 0);
};

main();
