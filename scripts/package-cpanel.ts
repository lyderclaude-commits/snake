/**
 * Fabrique le paquet à téléverser sur un cPanel.
 *
 * Pourquoi un paquet à part plutôt que « décompresser et construire » :
 *
 *   - `next build` demande ~850 Mo de mémoire. Un mutualisé plafonne souvent
 *     à 1 Go, parfois 512 Mo. Le build est donc fait ICI, une fois.
 *   - Les binaires natifs (better-sqlite3, sharp) sont liés à la glibc de la
 *     machine qui les a produits. Les expédier depuis un conteneur récent vers
 *     un CloudLinux plus ancien donnerait « GLIBC_2.38 not found ». Ils sont
 *     donc installés SUR le serveur, où npm télécharge la bonne variante —
 *     sans compilateur, ces paquets fournissent des binaires précompilés.
 *
 * Le paquet contient donc : l'application déjà construite, le nécessaire à
 * l'exécution, et rien d'autre.
 */

import { cpSync, existsSync, mkdirSync, rmSync, writeFileSync, readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { join } from 'node:path';
import { buildSync } from 'esbuild';

const SORTIE = 'dist-cpanel';
const RACINE = join(SORTIE, 'wakabi-boost');

function copier(source: string, cible = source) {
  if (!existsSync(source)) return false;
  cpSync(source, join(RACINE, cible), { recursive: true });
  return true;
}

/* ---------- 1. l'application construite ---------- */

if (!existsSync('.next/BUILD_ID')) {
  console.error('✗ Aucun build trouvé. Lancez `npm run build` d’abord.');
  process.exit(1);
}

rmSync(SORTIE, { recursive: true, force: true });
mkdirSync(RACINE, { recursive: true });

// `.next/standalone` ne sert pas ici : Passenger charge app.js, qui utilise
// l'installation normale de node_modules faite sur le serveur.
copier('.next');
rmSync(join(RACINE, '.next/standalone'), { recursive: true, force: true });
rmSync(join(RACINE, '.next/cache'), { recursive: true, force: true });

/* ---------- 2. le nécessaire à l'exécution ---------- */

copier('public');
copier('src');           // les Server Actions et les routes vivent ici
copier('deploy/cpanel/app.js', 'app.js');
copier('deploy/cpanel/charger-env.js', 'charger-env.js');
copier('package-lock.json');

/* ---------- 2 bis. les outils d'administration, en JavaScript ---------- */

// `tsx` est une dépendance de développement : absente du serveur. Les deux
// scripts dont on a besoin en production sont donc compilés ici, en un seul
// fichier chacun. La condition `react-server` fait résoudre `server-only`
// vers sa variante inerte — sans elle, l'import lève une exception.
for (const [source, cible] of [
  ['scripts/creer-admin.ts', 'creer-admin.js'],
  ['scripts/seed.ts', 'seed.js'],
] as const) {
  buildSync({
    entryPoints: [source],
    outfile: join(RACINE, cible),
    bundle: true,
    platform: 'node',
    target: 'node20',
    format: 'cjs',
    conditions: ['react-server', 'node'],
    // Modules natifs : ils sont installés sur le serveur, pas empaquetés.
    external: ['better-sqlite3', 'sharp'],
    // Le .env doit être lu AVANT que db.ts n'ouvre le fichier : sans ce
    // préambule, l'outil écrirait dans le dossier de l'application.
    banner: { js: "require('./charger-env.js')(__dirname);" },
    logLevel: 'error',
  });
}

/* ---------- 3. package.json allégé ---------- */

const pkg = JSON.parse(readFileSync('package.json', 'utf8'));
delete pkg.devDependencies;
pkg.scripts = {
  start: 'node app.js',
  'creer-admin': 'node creer-admin.js',
  seed: 'node seed.js',
};
writeFileSync(join(RACINE, 'package.json'), JSON.stringify(pkg, null, 2) + '\n');

/* ---------- 4. next.config en CommonJS ---------- */

// Le fichier d'origine est en TypeScript. Sans les devDependencies, rien ne
// garantit qu'il soit lisible à l'exécution : on écrit l'équivalent en JS.
writeFileSync(
  join(RACINE, 'next.config.js'),
  `/** Généré par scripts/package-cpanel.ts — équivalent JS de next.config.ts. */
module.exports = {
  reactStrictMode: true,
  serverExternalPackages: ['better-sqlite3'],
};
`,
);

/* ---------- 5. le fichier de réglages ---------- */

writeFileSync(
  join(RACINE, '.env.exemple'),
  `# Renommez ce fichier en .env, puis complétez-le.
#
# L'adresse publique est incrustée dans le QR de CHAQUE badge, donc dans un
# fichier que l'invité garde et partage. Une erreur ici est définitive :
# l'application refuse de démarrer plutôt que d'émettre des badges faux.
WAKABI_BASE_URL=https://boost.wakabileguide.com

# Chemins ABSOLUS, hors du dossier de l'application, pour que la base et les
# cadres survivent au prochain téléversement. Remplacez « VOTRE_COMPTE ».
WAKABI_DB=/home/VOTRE_COMPTE/wakabi-donnees/wakabi.sqlite
WAKABI_UPLOADS=/home/VOTRE_COMPTE/wakabi-donnees/uploads

NODE_ENV=production
`,
);

/* ---------- 6. le mode d'emploi, dans le paquet ---------- */

copier('docs/10-LWS-CPANEL.md', 'LISEZ-MOI.md');

/* ---------- 7. l'archive ---------- */

execFileSync('zip', ['-qr', 'wakabi-boost-cpanel.zip', 'wakabi-boost'], { cwd: SORTIE });

const taille = execFileSync('du', ['-sh', join(SORTIE, 'wakabi-boost-cpanel.zip')])
  .toString()
  .split('\t')[0];

console.log(`\n  ✓ ${SORTIE}/wakabi-boost-cpanel.zip — ${taille}`);
console.log('    à téléverser tel quel dans le gestionnaire de fichiers cPanel.\n');
