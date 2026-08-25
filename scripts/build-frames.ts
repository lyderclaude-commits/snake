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
const OUT = 'public/frames';
const EXE = '/opt/pw-browsers/chromium-1194/chrome-linux/chrome';

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
      `<style>html,body{margin:0;padding:0;background:transparent}svg{display:block}</style>${svg}`,
    );
    const buf = await page.screenshot({ omitBackground: true, type: 'png' });
    const out = join(OUT, file.replace(/\.svg$/, '.png'));
    writeFileSync(out, buf);
    console.log(`  ✓ ${out}  ${w}×${h}  ${(buf.length / 1024).toFixed(0)} Ko`);
    await page.close();
  }
  await browser.close();
};

run().catch((e) => {
  console.error(e);
  process.exit(1);
});
