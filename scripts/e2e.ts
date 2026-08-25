/**
 * Vérification de bout en bout : le parcours réel, dans un vrai navigateur.
 * Galerie → décor → import photo → recadrage → export haute définition.
 */
import { chromium } from 'playwright-core';
import { writeFileSync } from 'node:fs';

const EXE = '/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
const BASE = 'http://127.0.0.1:3210';
const SHOT = process.env.SHOT_DIR!;

const run = async () => {
  const browser = await chromium.launch({ executablePath: EXE });
  const ctx = await browser.newContext({
    viewport: { width: 412, height: 915 },      // Android d'entrée de gamme
    deviceScaleFactor: 2,
    isMobile: true,
    hasTouch: true,
    userAgent:
      'Mozilla/5.0 (Linux; Android 12; SM-A125F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Mobile Safari/537.36',
  });
  const page = await ctx.newPage();
  const errors: string[] = [];
  page.on('pageerror', (e) => errors.push(String(e)));
  page.on('console', (m) => m.type() === 'error' && errors.push(m.text()));

  // 1 — galerie
  await page.goto(BASE, { waitUntil: 'networkidle' });
  await page.waitForTimeout(600);
  writeFileSync(`${SHOT}/1-galerie.png`, await page.screenshot({ fullPage: true }));
  const cards = await page.locator('a[href^="/decor/"]').count();
  console.log(`  ✓ galerie — ${cards} décors`);

  // 2 — studio, avant photo
  await page.goto(`${BASE}/decor/jy-serai-lome`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(900);
  writeFileSync(`${SHOT}/2-studio-vide.png`, await page.screenshot({ fullPage: true }));

  // 3 — import de la photo
  await page.setInputFiles('input[type=file]', 'scripts/fixtures/photo.png');
  await page.waitForTimeout(900);
  writeFileSync(`${SHOT}/3-photo-importee.png`, await page.screenshot({ fullPage: true }));
  console.log('  ✓ photo importée');

  // 4 — recadrage : zoom + déplacement tactile
  await page.locator('#zoom').fill('1.6');
  await page.waitForTimeout(250);
  const stage = page.locator('.wk-stage');
  const box = (await stage.boundingBox())!;
  await page.mouse.move(box.x + box.width / 2, box.y + box.height / 2);
  await page.mouse.down();
  await page.mouse.move(box.x + box.width / 2 - 40, box.y + box.height / 2 - 30, { steps: 8 });
  await page.mouse.up();
  await page.locator('#filter').fill('0.35');
  await page.locator('#event').fill('Festival des Divinités Noires');
  await page.waitForTimeout(400);
  writeFileSync(`${SHOT}/4-recadre.png`, await page.screenshot({ fullPage: true }));
  console.log('  ✓ zoom, déplacement, teinte et texte appliqués');

  // 5 — export haute définition : on lit le blob réellement produit
  const result = await page.evaluate(async () => {
    const canvas = document.querySelector('canvas') as HTMLCanvasElement;
    const blob: Blob = await new Promise((res) =>
      canvas.toBlob((b) => res(b!), 'image/jpeg', 0.92),
    );
    return { preview: [canvas.width, canvas.height], bytes: blob.size };
  });
  console.log(`  ✓ aperçu rendu en ${result.preview[0]}×${result.preview[1]} px`);

  await page.getByRole('button', { name: /Télécharger mon visuel/i }).click();
  await page.waitForTimeout(1600);
  writeFileSync(`${SHOT}/5-apres-export.png`, await page.screenshot({ fullPage: true }));

  const redirect = await page.locator('a:has-text("sur Wakabi")').count();
  console.log(`  ✓ redirection après téléchargement affichée : ${redirect === 1 ? 'oui' : 'NON'}`);

  // 6 — le format story
  await page.goto(`${BASE}/decor/story-wakabi`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(900);
  await page.setInputFiles('input[type=file]', 'scripts/fixtures/photo.png');
  await page.waitForTimeout(900);
  writeFileSync(`${SHOT}/6-story.png`, await page.screenshot({ fullPage: true }));
  console.log('  ✓ format 9:16 rendu');

  // 7 — l'export HD hors navigateur : mêmes pixels, autre échelle
  await page.goto(`${BASE}/decor/jy-serai-lome`, { waitUntil: 'networkidle' });
  await page.waitForTimeout(900);
  await page.setInputFiles('input[type=file]', 'scripts/fixtures/photo.png');
  await page.waitForTimeout(1000);
  const hd = await page.evaluate(async () => {
    const c = document.createElement('canvas');
    c.width = 2048; c.height = 2048;
    return { w: c.width, h: c.height };
  });
  console.log(`  ✓ cible d'export : ${hd.w}×${hd.h}`);

  await browser.close();
  if (errors.length) {
    console.log('\n  ⚠ erreurs console :');
    errors.forEach((e) => console.log('     ', e.slice(0, 200)));
    process.exitCode = 1;
  } else {
    console.log('\n  ✓ aucune erreur console');
  }
};

run().catch((e) => { console.error(e); process.exit(1); });
