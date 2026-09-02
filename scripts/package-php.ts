/**
 * Fabrique le zip de la version PHP.
 *
 * Le bundle JavaScript du Studio est reconstruit à chaque fois depuis
 * src/core : c'est la garantie que les deux versions dessinent la même chose.
 */

import { cpSync, existsSync, mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { join } from 'node:path';

const SORTIE = 'dist-php';
const RACINE = join(SORTIE, 'wakabi-boost');

console.log('→ bundles du Studio et de l’aperçu depuis src/core');
for (const [source, sortie] of [
  ['php/studio/entry.ts', 'php/public/studio.js'],
  ['php/studio/apercu.ts', 'php/public/apercu.js'],
  ['php/studio/scanner.ts', 'php/public/scanner.js'],
  ['php/studio/push.ts', 'php/public/push.js'],
  ['php/studio/editeur.ts', 'php/public/editeur.js'],
]) {
  execFileSync('npx', [
    'esbuild', source,
    '--bundle', '--format=iife', '--target=es2019', '--minify',
    `--outfile=${sortie}`, '--alias:@=./src',
  ], { stdio: 'inherit' });
}

rmSync(SORTIE, { recursive: true, force: true });
mkdirSync(RACINE, { recursive: true });

/**
 * `sw.js` part À LA RACINE, et pas dans public/.
 *
 * La portée d'un service worker est celle de son dossier : servi depuis
 * public/, il ne pourrait rien faire pour les pages du site. C'est la
 * seule raison pour laquelle ce fichier n'est pas rangé avec les autres.
 */
for (const source of ['app', 'public', 'index.php', 'install.php', 'sw.js', '.htaccess']) {
  const chemin = join('php', source);
  if (existsSync(chemin)) {
    cpSync(chemin, join(RACINE, source), { recursive: true });
  }
}

// Le dossier de données part vide, avec son garde-fou.
mkdirSync(join(RACINE, 'donnees/cadres'), { recursive: true });
// Les vignettes de partage : créées à la demande, mais un hébergement aux
// droits stricts refuse parfois un mkdir. Le dossier part donc avec le zip.
mkdirSync(join(RACINE, 'donnees/og'), { recursive: true });
// Idem pour les sauvegardes : le cron ne doit pas échouer sur un mkdir.
mkdirSync(join(RACINE, 'donnees/sauvegardes'), { recursive: true });
// Les couvertures d'articles, et le cache des images redimensionnées.
mkdirSync(join(RACINE, 'donnees/medias'), { recursive: true });
mkdirSync(join(RACINE, 'donnees/vignettes'), { recursive: true });
writeFileSync(join(RACINE, 'donnees/.htaccess'), 'Deny from all\nRequire all denied\n');
writeFileSync(join(RACINE, 'donnees/cadres/.gitkeep'), '');
writeFileSync(join(RACINE, 'donnees/og/.gitkeep'), '');
writeFileSync(join(RACINE, 'donnees/sauvegardes/.gitkeep'), '');
writeFileSync(join(RACINE, 'donnees/medias/.gitkeep'), '');
writeFileSync(join(RACINE, 'donnees/vignettes/.gitkeep'), '');

// `studio/` contient les sources TypeScript : inutiles en production.
rmSync(join(RACINE, 'studio'), { recursive: true, force: true });

cpSync('docs/11-VERSION-PHP.md', join(RACINE, 'LISEZ-MOI.md'));

execFileSync('zip', ['-qr', 'wakabi-boost-php.zip', 'wakabi-boost'], { cwd: SORTIE });
const taille = execFileSync('du', ['-sh', join(SORTIE, 'wakabi-boost-php.zip')]).toString().split('\t')[0];

console.log(`\n  ✓ ${SORTIE}/wakabi-boost-php.zip — ${taille}`);
console.log('    à décompresser dans le dossier du sous-domaine, puis ouvrir install.php\n');
