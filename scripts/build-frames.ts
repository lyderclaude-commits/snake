/**
 * Rasterise les cadres SVG en PNG à canal alpha.
 *
 * Les cadres livrés ici sont des PLACEHOLDERS aux couleurs de la charte,
 * destinés à faire tourner la chaîne de bout en bout. L'équipe Wakabi les
 * remplacera par ses propres visuels de campagne via le back-office.
 */
import { chromium } from 'playwright-core';
import { readFileSync, writeFileSync, readdirSync } from 'node:fs';
import { join } from 'node:path';

const SRC = 'scripts/frames-src';
/**
 * Deux destinations, un seul rendu.
 *
 * `public/frames` sert la version Next.js, `php/public/cadres` la version
 * PHP. Rasteriser deux fois, c'est prendre le risque que les deux versions
 * n'aient pas le même cadre — donc pas le même badge.
 */
const OUT = ['public/frames', 'php/public/cadres'];
const EXE = '/opt/pw-browsers/chromium-1194/chrome-linux/chrome';

/**
 * La police de la charte, embarquée en base64 dans la page de rendu.
 *
 * Chromium reçoit le SVG par `setContent` : sa base est `about:blank`, donc
 * aucun chemin relatif n'y résout. Un `@font-face` vers un fichier local
 * échouerait en silence et le cadre sortirait dans la police système — ce
 * qui ne se voit qu'en comparant deux exports côte à côte.
 */
const POLICES = ['600', '800']
  .map((graisse) => {
    const fichier = `php/public/polices/plus-jakarta-sans-latin-${graisse}-normal.woff2`;
    const b64 = readFileSync(fichier).toString('base64');
    return `@font-face{font-family:"Plus Jakarta Sans";font-style:normal;font-weight:${graisse};`
         + `src:url(data:font/woff2;base64,${b64}) format("woff2")}`;
  })
  .join('');

const run = async () => {
  const browser = await chromium.launch({ executablePath: EXE });
  for (const file of readdirSync(SRC).filter((f) => f.endsWith('.svg'))) {
    const svg = readFileSync(join(SRC, file), 'utf8');
    const w = Number(/width="(\d+)"/.exec(svg)![1]);
    const h = Number(/height="(\d+)"/.exec(svg)![1]);
    const page = await browser.newPage({
      viewport: { width: w, height: h },
      deviceScaleFactor: 1,
    });
    await page.setContent(
      `<style>${POLICES}html,body{margin:0;padding:0;background:transparent}svg{display:block}</style>${svg}`,
    );
    // Sans cette attente, un cadre peut être capturé avant que la police ne
    // soit prête : le texte sort alors dans le repli système.
    await page.evaluate(() => (document as unknown as { fonts: FontFaceSet }).fonts.ready);
    const buf = await page.screenshot({ omitBackground: true, type: 'png' });
    for (const dossier of OUT) {
      writeFileSync(join(dossier, file.replace(/\.svg$/, '.png')), buf);
    }
    console.log(`  ✓ ${file.replace(/\.svg$/, '.png')}  ${w}×${h}  ${(buf.length / 1024).toFixed(0)} Ko`);
    await page.close();
  }
  await browser.close();
};

run().catch((e) => {
  console.error(e);
  process.exit(1);
});
