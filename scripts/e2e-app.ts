/** Parcours complet : visiteur → utilisateur → partenaire → administration. */
import { chromium, type Browser, type Page } from 'playwright-core';
import { writeFileSync } from 'node:fs';

const EXE = '/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
// Le port suit `npm run dev` (3000). Surchargeable : BASE_URL=… npm run e2e:full
const BASE = process.env.BASE_URL ?? 'http://127.0.0.1:3000';
// Les captures atterrissent dans .recette/ (ignoré par git), sauf SP= imposé.
const SHOT = process.env.SP ? `${process.env.SP}/shots` : '.recette';
const errs: string[] = [];

const shot = async (p: Page, n: string, full = true) =>
  writeFileSync(`${SHOT}/app-${n}.png`, await p.screenshot({ fullPage: full }));

async function login(p: Page, email: string) {
  await p.goto(`${BASE}/connexion`, { waitUntil: 'networkidle' });
  await p.fill('input[name=email]', email);
  await p.fill('input[name=password]', 'wakabi2026');
  await Promise.all([p.waitForURL(/compte|partenaire|admin/, { timeout: 15000 }), p.click('button[type=submit]')]);
}

const run = async () => {
  const browser: Browser = await chromium.launch({ executablePath: EXE });

  /* ---- 1. la vitrine, sur mobile ---- */
  const mob = await browser.newContext({ viewport: { width: 390, height: 844 }, deviceScaleFactor: 2, isMobile: true, hasTouch: true });
  const m = await mob.newPage();
  m.on('pageerror', (e) => errs.push('LANDING ' + e.message));
  await m.goto(BASE, { waitUntil: 'networkidle' });
  await m.waitForTimeout(900);
  await shot(m, '1-landing-mobile');
  console.log('  ✓ landing mobile —', await m.locator('section').count(), 'sections');
  await mob.close();

  /* ---- 2. la vitrine, sur desktop ---- */
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 2 });
  const p = await ctx.newPage();
  p.on('pageerror', (e) => errs.push('PAGE ' + e.message));
  p.on('console', (c) => c.type() === 'error' && errs.push('CONSOLE ' + c.text()));

  await p.goto(BASE, { waitUntil: 'networkidle' });
  await p.waitForTimeout(900);
  await shot(p, '2-landing');
  const priceVisible = await p.locator('#tarifs').isVisible();
  console.log('  ✓ landing desktop — tarifs affichés :', priceVisible);

  // le simulateur de crédits WhatsApp
  await p.locator('#msg').fill('60000');
  await p.waitForTimeout(250);
  const total = (await p.locator('#tarifs').innerText()).replace(/[\s\u202f\u00a0]/g, '');
  console.log('  ✓ simulateur WhatsApp — 60 000 msg à tarif dégressif :', /51000/.test(total) ? '51 000 FCFA ✓' : '⚠ ' + (/(\d{5,6})FCFA/.exec(total)?.[1] ?? '?'));

  /* ---- 3. catalogue public ---- */
  await p.goto(`${BASE}/decors`, { waitUntil: 'networkidle' });
  await p.waitForTimeout(500);
  const cards = await p.locator('a[href^="/decor/"]').count();
  await shot(p, '3-catalogue');
  console.log('  ✓ catalogue —', cards, 'décors publiés');

  /* ---- 4. compte participant ---- */
  await login(p, 'kossi@exemple.tg');
  await p.waitForTimeout(600);
  await shot(p, '4-compte');
  console.log('  ✓ espace participant :', await p.locator('h1').innerText());

  /* ---- 5. l'éditeur, connecté : compteur + historique ---- */
  await p.goto(`${BASE}/decors`, { waitUntil: 'networkidle' });
  await p.locator('a[href^="/decor/"]').first().click();
  await p.waitForSelector('input[type=file]', { state: 'attached' });
  await p.waitForTimeout(1200);
  await p.setInputFiles('input[type=file]', 'scripts/fixtures/photo.png');
  await p.waitForTimeout(1000);
  await p.getByRole('button', { name: /Télécharger mon visuel/i }).click();
  await p.waitForTimeout(2200);
  await shot(p, '5-editeur');
  await p.goto(`${BASE}/compte`, { waitUntil: 'networkidle' });
  await p.waitForTimeout(600);
  const hasCreation = (await p.locator('a[href^="/decor/"]').count()) > 0;
  console.log('  ✓ création enregistrée dans « mes créations » :', hasCreation);

  /* ---- 6. espace partenaire ---- */
  await login(p, 'partenaire@chezlena.tg');
  await p.waitForTimeout(600);
  await shot(p, '6-partenaire');
  console.log('  ✓ espace partenaire :', await p.locator('h1').innerText());

  // création d'un décor
  await p.goto(`${BASE}/partenaire/nouveau`, { waitUntil: 'networkidle' });
  await p.waitForTimeout(500);
  await p.setInputFiles('input[name=frame]', 'public/frames/bon-plan.png');
  await p.waitForFunction(() => (document.querySelector('input[name=frameUrl]') as HTMLInputElement)?.value.startsWith('/api/frames/'), null, { timeout: 20000 });
  await p.fill('input[name=title]', 'Brunch coloré du dimanche');
  await p.fill('input[name=subtitle]', 'Chaque dimanche dès 11h');
  await p.fill('input[name=claim]', "J'Y SERAI");
  await p.waitForTimeout(300);
  await shot(p, '7-nouveau-decor');

  // d'abord une redirection hors domaine : le garde-fou doit refuser
  await p.fill('input[name=redirectUrl]', 'https://mon-restaurant.tg/promo');
  await p.getByRole('button', { name: /Créer et enregistrer/i }).click();
  await p.waitForTimeout(1800);
  const alertP = p.locator('p[role=alert]');
  const blocked = await alertP.count();
  const msg = blocked ? await alertP.first().innerText() : '';
  console.log('  ✓ garde-fou redirection :', blocked ? 'REFUSÉ — ' + msg.slice(0, 70) : '⚠ NON APPLIQUÉ');
  await shot(p, '8-garde-fou');

  // puis une adresse Wakabi valide
  await p.fill('input[name=redirectUrl]', 'https://wakabileguide.com/p/chez-lena');
  await p.getByRole('button', { name: /Créer et enregistrer/i }).click();
  // /partenaire/nouveau contient déjà « partenaire » : on attend de QUITTER le formulaire.
  await p.waitForURL((u) => !u.pathname.includes('/nouveau'), { timeout: 20000 });
  await p.waitForTimeout(800);
  console.log('  ✓ décor créé en brouillon —', new URL(p.url()).pathname);

  // soumission à la relecture
  await p.getByRole('button', { name: /Soumettre/i }).first().click();
  await p.waitForTimeout(1600);
  await shot(p, '9-partenaire-soumis');
  console.log('  ✓ soumis à la relecture');

  /* ---- 7. administration ---- */
  await login(p, 'admin@wakabileguide.com');
  await p.waitForTimeout(800);
  await shot(p, '10-admin');
  console.log('  ✓ tableau de bord admin');

  await p.goto(`${BASE}/admin/moderation`, { waitUntil: 'networkidle' });
  await p.waitForTimeout(700);
  const queue = await p.locator('form button:has-text("Approuver")').count();
  await shot(p, '11-moderation');
  console.log('  ✓ file de relecture —', queue, 'décor(s)');

  // refus sans motif impossible : le champ est requis
  await p.getByRole('button', { name: /Demander des corrections/i }).first().click();
  await p.waitForTimeout(300);
  const required = await p.locator('textarea[name=note]').getAttribute('required');
  console.log('  ✓ motif obligatoire :', required !== null);

  // approbation
  await p.locator('form button:has-text("Approuver")').first().click();
  await p.waitForTimeout(1800);
  const left = await p.locator('form button:has-text("Approuver")').count();
  console.log('  ✓ après approbation — file :', left, 'restant(s)');
  await shot(p, '12-moderation-vide');

  await p.goto(`${BASE}/admin/comptes`, { waitUntil: 'networkidle' });
  await p.waitForTimeout(600);
  await shot(p, '13-comptes');
  console.log('  ✓ gestion des comptes —', await p.locator('tbody tr').count(), 'comptes');

  await browser.close();
  console.log(errs.length ? '\n  ⚠ ' + errs.length + ' erreurs :\n     ' + errs.slice(0, 5).join('\n     ') : '\n  ✓ aucune erreur console');
};

run().catch((e) => { console.error(String(e).slice(0, 500)); process.exit(1); });
