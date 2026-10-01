/**
 * Les captures des écrans ajoutés : les deux pages légales et les adresses.
 *
 * Un écran se juge en le regardant, pas en lisant son HTML : c'est ainsi
 * qu'on a vu la bande blanche du pied de page et les vignettes qui
 * débordaient. Ce script n'affirme rien, il photographie.
 */
import { chromium } from 'playwright-core';
import { mkdirSync } from 'node:fs';

const EXE = '/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
const BASE = process.env.BASE_URL ?? 'http://127.0.0.1:3600';
const SORTIE = process.env.SORTIE ?? '/tmp/claude-0/apercus';
const ADMIN = {
  email: process.env.WAKABI_ADMIN ?? 'lyder@wakabileguide.com',
  mdp: process.env.WAKABI_ADMIN_MDP ?? 'un-mot-de-passe-solide-2026',
};

const main = async () => {
  mkdirSync(SORTIE, { recursive: true });
  const nav = await chromium.launch({ executablePath: EXE });
  const page = await nav.newPage({ viewport: { width: 1280, height: 900 } });

  for (const [nom, p] of [['cgu', 'cgu'], ['confidentialite', 'confidentialite']]) {
    await page.goto(`${BASE}/index.php?p=${p}`, { waitUntil: 'domcontentloaded' });
    await page.waitForTimeout(400);
    await page.screenshot({ path: `${SORTIE}/${nom}.png`, fullPage: true });
    console.log(`  ${nom}.png`);
  }

  /* L'écran des réglages demande une session : sans connexion, on
     photographierait la page de connexion en croyant tenir l'écran. */
  await page.goto(`${BASE}/index.php?p=connexion`, { waitUntil: 'domcontentloaded' });
  await page.fill('input[name="email"]', ADMIN.email);
  await page.fill('input[name="mot_de_passe"]', ADMIN.mdp);
  await page.click('button[type="submit"]');
  await page.waitForLoadState('domcontentloaded');

  for (const [nom, p] of [['adresses', 'reglages-permaliens'], ['reglages', 'reglages']]) {
    const r = await page.goto(`${BASE}/index.php?p=${p}`, { waitUntil: 'domcontentloaded' });
    if (!r || r.status() >= 400) { throw new Error(`${p} a répondu ${r?.status()}`); }
    if (page.url().includes('p=connexion')) { throw new Error('connexion refusée'); }
    await page.waitForTimeout(300);
    await page.screenshot({ path: `${SORTIE}/${nom}.png`, fullPage: true });
    console.log(`  ${nom}.png`);
  }

  await nav.close();
};

main().catch((e) => { console.error(e); process.exit(1); });
