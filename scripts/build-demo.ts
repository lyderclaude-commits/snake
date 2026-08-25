/**
 * Assemble la démonstration en UN SEUL fichier HTML autonome.
 *
 * Le cœur (renderScene, imagePipeline, fitPhoto, le schéma Zod) est bundlé
 * tel quel depuis src/core — aucune réécriture, aucune duplication. Si la
 * démo rend juste, c'est que la fonction pure l'est vraiment.
 *
 * Les cadres sont incorporés en data URI : la page fonctionne hors ligne,
 * sans aucune requête réseau (hors polices).
 */
import { build } from 'esbuild';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';

const FRAME_FILES = ['jy-serai.png', 'bon-plan.png', 'story.png'];

const generateFrames = () => {
  const entries = FRAME_FILES.map((f) => {
    const b64 = readFileSync(`public/frames/${f}`).toString('base64');
    return `  ${JSON.stringify(f)}: 'data:image/png;base64,${b64}'`;
  });
  mkdirSync('src/demo', { recursive: true });
  writeFileSync(
    'src/demo/frames.generated.ts',
    `/* Généré par scripts/build-demo.ts — ne pas éditer à la main. */\n` +
      `export const FRAMES: Record<string, string> = {\n${entries.join(',\n')},\n};\n`,
  );
  const kb = entries.reduce((a, e) => a + e.length, 0) / 1024;
  console.log(`  ✓ ${FRAME_FILES.length} cadres incorporés (${kb.toFixed(0)} Ko en base64)`);
};

const run = async () => {
  generateFrames();

  const out = await build({
    entryPoints: ['src/demo/entry.ts'],
    bundle: true,
    format: 'iife',
    target: 'es2020',
    minify: true,
    write: false,
    define: { 'process.env.NODE_ENV': '"production"' },
    alias: { '@': './src' },
  });
  const js = out.outputFiles[0].text;
  const css = readFileSync('scripts/demo.css', 'utf8');
  const shell = readFileSync('scripts/demo.html', 'utf8');

  // ATTENTION : passer une FONCTION de remplacement, jamais une chaîne.
  // String.replace interprète `$&`, `$'` et `` $` `` dans le remplacement,
  // et du JS minifié en contient — le script inliné devenait invalide.
  const html = shell
    .replace('/*STYLE*/', () => css)
    .replace('//SCRIPT', () => js);

  mkdirSync('public/demo', { recursive: true });
  writeFileSync('public/demo/index.html', html);
  console.log(`  ✓ public/demo/index.html — ${(html.length / 1024).toFixed(0)} Ko`);
  console.log(`     dont ${(js.length / 1024).toFixed(0)} Ko de code (cœur partagé compris)`);
};

run().catch((e) => { console.error(e); process.exit(1); });
