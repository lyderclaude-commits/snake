/**
 * Des captures d'écran des espaces connectés, pour les regarder.
 *
 * Une recette dit qu'un écran répond ; elle ne dit pas s'il est lisible. Un
 * bloc qui laisse un vide d'un demi-écran, un menu où deux entrées se
 * ressemblent, une carte qui pousse l'essentiel sous la ligne de flottaison :
 * rien de tout cela ne fait échouer un test, et tout se voit en une image.
 *
 * Les identifiants viennent de l'environnement — le fichier ne contient
 * aucun mot de passe, et il tourne sur l'installation de développement.
 *
 *   npm run php:apercus                 # dans ./apercus
 *   npm run php:apercus -- /tmp/sortie  # ailleurs
 */
import { chromium, type Browser, type Page } from 'playwright-core';
import { mkdirSync } from 'node:fs';

const EXE = process.env.CHROMIUM ?? '/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
const BASE = process.env.BASE_URL ?? 'http://127.0.0.1:3600';
const OUT = process.argv[2] ?? 'apercus';

const EQUIPE = {
  email: process.env.WAKABI_ADMIN ?? 'lyder@wakabileguide.com',
  mdp: process.env.WAKABI_ADMIN_MDP ?? 'un-mot-de-passe-solide-2026',
};
const ORGA = {
  email: process.env.WAKABI_ORGA ?? 'demo@wakabileguide.com',
  mdp: process.env.WAKABI_ORGA_MDP ?? 'apercu-2026-solide',
};

async function connexion(p: Page, email: string, mdp: string): Promise<void> {
  await p.goto(`${BASE}/index.php?p=connexion`, { waitUntil: 'domcontentloaded' });
  await p.fill('input[name=email]', email);
  await p.fill('input[name=mot_de_passe]', mdp);
  await p.click('main button[type=submit]');
  await p.waitForLoadState('domcontentloaded');
  if (p.url().includes('p=connexion')) {
    throw new Error(`connexion refusée pour ${email} — vérifiez WAKABI_ADMIN_MDP / WAKABI_ORGA_MDP`);
  }
}

/**
 * La souris reste où elle a cliqué.
 *
 * Sans ce recentrage, le survol colore un menu qui n'a rien d'actif : la
 * capture montre alors un défaut qui n'existe pas, et on part le corriger.
 */
async function ecarterLaSouris(p: Page): Promise<void> {
  await p.mouse.move(10, 600);
  await p.waitForTimeout(120);
}

const run = async () => {
  mkdirSync(OUT, { recursive: true });
  const b: Browser = await chromium.launch({ executablePath: EXE });

  const grand = { viewport: { width: 1360, height: 1000 }, deviceScaleFactor: 2 };
  const telephone = {
    viewport: { width: 390, height: 844 }, deviceScaleFactor: 3,
    isMobile: true, hasTouch: true,
  };

  /* ---------------- l'équipe ---------------- */
  const c1 = await b.newContext(grand);
  const p1 = await c1.newPage();
  await connexion(p1, EQUIPE.email, EQUIPE.mdp);

  for (const [page, nom] of [
    ['admin', '1-equipe-tableau-de-bord'],
    ['regie', '3-regie-liste'],
    ['blog-relecture', '5-blog-relecture'],
    ['comptes', '7-comptes'],
  ] as const) {
    await p1.goto(`${BASE}/index.php?p=${page}`, { waitUntil: 'domcontentloaded' });
    await ecarterLaSouris(p1);
    await p1.screenshot({ path: `${OUT}/${nom}.png`, fullPage: true });
  }

  // Le menu déplié : c'est le rangement qu'on veut voir, pas la page.
  await p1.goto(`${BASE}/index.php?p=admin`, { waitUntil: 'domcontentloaded' });
  await p1.click('.deroulant:has(summary:has-text("Audience")) > summary');
  await p1.waitForTimeout(250);
  await p1.screenshot({ path: `${OUT}/2-equipe-menu.png`, clip: { x: 0, y: 0, width: 1360, height: 430 } });

  /* ---------------- l'organisateur ---------------- */
  const c2 = await b.newContext(grand);
  const p2 = await c2.newPage();
  await connexion(p2, ORGA.email, ORGA.mdp);

  for (const [page, nom] of [
    ['partenaire', '4-organisateur-tableau-de-bord'],
    ['regie-ecrire', '6-regie-ecrire'],
    ['blog-admin', '8-organisateur-articles'],
  ] as const) {
    await p2.goto(`${BASE}/index.php?p=${page}`, { waitUntil: 'domcontentloaded' });
    await ecarterLaSouris(p2);
    await p2.screenshot({ path: `${OUT}/${nom}.png`, fullPage: true });
  }

  /* ---------------- le téléphone ---------------- */
  const c3 = await b.newContext(telephone);
  const p3 = await c3.newPage();
  await connexion(p3, EQUIPE.email, EQUIPE.mdp);
  await p3.goto(`${BASE}/index.php?p=admin`, { waitUntil: 'domcontentloaded' });
  await p3.click('.menu > summary');
  await p3.waitForTimeout(300);
  await p3.screenshot({ path: `${OUT}/9-menu-telephone.png` });
  await p3.click('.menu > summary');
  await p3.screenshot({ path: `${OUT}/10-tableau-de-bord-telephone.png`, fullPage: true });

  await b.close();
  console.log(`\n  ✓ captures dans ${OUT}/\n`);
};

run().catch((e) => {
  console.error('  ✗', e instanceof Error ? e.message : e);
  process.exitCode = 1;
});
