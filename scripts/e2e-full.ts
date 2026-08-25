/**
 * Recette complète — tout le produit, dans un vrai navigateur.
 * Vitrine · comptes · QR · présence · pré-vol · modération · sécurité.
 *
 * Suppose une base fraîchement semée (`npm run seed` sur une base vide) : la
 * modération approuve le décor en attente, ce qui ne se rejoue pas deux fois.
 */
import { chromium, type Browser, type Page } from 'playwright-core';
import { writeFileSync } from 'node:fs';

const EXE = '/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
// Le port suit `npm run dev` (3000). Surchargeable : BASE_URL=… npm run e2e:full
const BASE = process.env.BASE_URL ?? 'http://127.0.0.1:3000';
// Les captures atterrissent dans .recette/ (ignoré par git), sauf SP= imposé.
const SHOT = process.env.SP ? `${process.env.SP}/shots` : '.recette';
const errs: string[] = [];
let pass = 0, fail = 0;

const ok = (label: string, cond: boolean, detail = '') => {
  cond ? pass++ : fail++;
  console.log(`  ${cond ? '✓' : '✗'} ${label}${detail ? ' — ' + detail : ''}`);
};
const shot = async (p: Page, n: string) =>
  writeFileSync(`${SHOT}/full-${n}.png`, await p.screenshot({ fullPage: true }));

async function login(p: Page, email: string, pwd = 'wakabi2026') {
  await p.goto(`${BASE}/connexion`, { waitUntil: 'networkidle' });
  await p.fill('input[name=email]', email);
  await p.fill('input[name=password]', pwd);
  await p.click('button[type=submit]');
  await p.waitForURL(/compte|partenaire|admin/, { timeout: 15000 }).catch(() => {});
  await p.waitForTimeout(500);
}

const run = async () => {
  const browser: Browser = await chromium.launch({ executablePath: EXE });
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 2 });
  const p = await ctx.newPage();
  p.on('pageerror', (e) => errs.push('PAGE ' + e.message));
  p.on('console', (c) => c.type() === 'error' && errs.push('CONSOLE ' + c.text()));

  console.log('\n━━ 1. Vitrine ━━');
  await p.goto(BASE, { waitUntil: 'networkidle' });
  await p.waitForTimeout(800);
  ok('vitrine chargée', (await p.locator('section').count()) >= 9);
  ok('offre de lancement affichée', await p.locator('text=−50 %').first().isVisible());
  await p.locator('#msg').fill('60000');
  await p.waitForTimeout(250);
  const t = (await p.locator('#tarifs').innerText()).replace(/[\s  ]/g, '');
  ok('simulateur WhatsApp dégressif', /51000/.test(t), '60 000 msg → 51 000 FCFA');
  await shot(p, '01-vitrine');

  console.log('\n━━ 2. Le QR qui rapporte ━━');
  await login(p, 'kossi@exemple.tg');
  const koriBefore = Number((await p.locator('text=/\\d+ ₵/').first().innerText()).replace(/\D/g, ''));
  await p.goto(`${BASE}/decors`, { waitUntil: 'networkidle' });
  await p.locator('a[href^="/decor/"]').first().click();
  await p.waitForSelector('input[type=file]', { state: 'attached' });
  await p.waitForTimeout(1200);
  await p.setInputFiles('input[type=file]', 'scripts/fixtures/photo.png');
  await p.waitForTimeout(2800);

  // Le QR doit être visible DANS L'APERÇU, pas seulement à l'export :
  // sinon l'utilisateur le découvre en ouvrant son fichier.
  const qrDrawn = await p.evaluate(() => {
    const c = document.querySelector('canvas') as HTMLCanvasElement;
    const g = c.getContext('2d')!;
    const d = g.getImageData(Math.round(c.width * 0.07), Math.round(c.height * 0.86), 60, 60).data;
    let white = 0;
    for (let i = 0; i < d.length; i += 4) if (d[i] > 240 && d[i+1] > 240 && d[i+2] > 240) white++;
    return white / (d.length / 4);
  });
  ok('QR visible dans l’aperçu', qrDrawn > 0.15, `${Math.round(qrDrawn * 100)} % de blanc au coin bas-gauche`);

  await p.getByRole('button', { name: /Télécharger mon visuel/i }).click();
  await p.waitForTimeout(2600);
  const codeEl = p.locator('p.font-mono');
  const token = (await codeEl.count()) ? (await codeEl.first().innerText()).trim() : '';
  ok('jeton du badge affiché', /^[2-9A-Z]{10}$/.test(token), token);
  await shot(p, '02-badge-emis');

  console.log('\n━━ 3. Contrôle d’entrée ━━');
  await login(p, 'admin@wakabileguide.com');
  await p.goto(`${BASE}/scan`, { waitUntil: 'networkidle' });
  const valider = p.getByRole('button', { name: /^Valider$|^…$/ });
  await p.fill('#token', token);
  await valider.click();
  await p.waitForTimeout(1800);
  const msg1 = await p.locator('[role=status]').innerText();
  ok('entrée validée', /Entrée validée/.test(msg1), msg1.split('\n')[1] ?? '');
  await shot(p, '03-scan-ok');

  await p.fill('#token', token);
  await valider.click();
  await p.waitForTimeout(1800);
  ok('rescan refusé (idempotent)', /déjà été scanné/.test(await p.locator('[role=status]').innerText()));

  await p.fill('#token', 'ZZZZZZZZZZ');
  await valider.click();
  await p.waitForTimeout(1500);
  ok('code inconnu refusé', /inconnu/.test(await p.locator('[role=status]').innerText()));

  console.log('\n━━ 4. Koris crédités ━━');
  await login(p, 'kossi@exemple.tg');
  await p.waitForTimeout(600);
  const koriAfter = Number((await p.locator('text=/\\d+ ₵/').first().innerText()).replace(/\D/g, ''));
  ok('Koris crédités après scan', koriAfter === koriBefore + 50, `${koriBefore} → ${koriAfter}`);
  await shot(p, '04-koris');

  console.log('\n━━ 5. Pré-vol ━━');
  await login(p, 'partenaire@chezlena.tg');
  await p.goto(`${BASE}/partenaire/nouveau`, { waitUntil: 'networkidle' });
  // cadre volontairement opaque : la photo ne passerait pas
  await p.setInputFiles('input[name=frame]', 'scripts/fixtures/opaque.png');
  await p.waitForFunction(() => (document.querySelector('input[name=frameUrl]') as HTMLInputElement)?.value.startsWith('/api/frames/'), null, { timeout: 20000 });
  await p.fill('input[name=title]', 'Cadre aplati de test');
  await p.fill('input[name=claim]', "J'Y SERAI");
  await p.fill('input[name=redirectUrl]', 'https://wakabileguide.com/p/test');
  await p.getByRole('button', { name: /Créer et enregistrer/i }).click();
  await p.waitForURL((u) => !u.pathname.includes('/nouveau'), { timeout: 20000 });
  await p.waitForTimeout(800);
  await p.getByRole('button', { name: /Soumettre/i }).first().click();
  await p.waitForTimeout(2500);
  const blocked = await p.locator('[role=alert]').first().innerText().catch(() => '');
  ok('pré-vol bloque le cadre opaque', /recouvre/.test(blocked), blocked.split('\n').pop()?.slice(0, 60));
  await shot(p, '05-prevol-bloque');

  console.log('\n━━ 6. Modération ━━');
  await login(p, 'admin@wakabileguide.com');
  await p.goto(`${BASE}/admin/moderation`, { waitUntil: 'networkidle' });
  await p.waitForTimeout(700);
  ok('rapport de pré-vol affiché', (await p.locator('text=Contrôle automatique').count()) > 0);
  const before = await p.locator('form button:has-text("Approuver")').count();
  await p.locator('form button:has-text("Approuver")').first().click();
  await p.waitForTimeout(2000);
  ok('approbation traitée', (await p.locator('form button:has-text("Approuver")').count()) === before - 1);
  await shot(p, '06-moderation');

  // Approuver ne suffit pas : le décor doit être RÉELLEMENT ouvrable. Le statut vit
  // à deux endroits — la colonne et le gabarit JSON — et c'est le gabarit que la page
  // publique relit. Tant qu'on ne l'ouvre pas, une divergence passe inaperçue.
  // Le seed ne met qu'un décor en attente ; celui créé plus haut est bloqué au pré-vol.
  const approuve = '/decor/soiree-maquis-akwaba';
  const pub = await p.goto(`${BASE}${approuve}`, { waitUntil: 'networkidle' });
  ok('décor approuvé réellement ouvrable', pub?.status() === 200, `${approuve} → ${pub?.status()}`);
  ok('le Studio se charge sur le décor approuvé', (await p.locator('canvas').count()) > 0);
  await shot(p, '06b-decor-publie');

  console.log('\n━━ 7. Notifications ━━');
  await login(p, 'partenaire@chezlena.tg');
  await p.waitForTimeout(600);
  const bell = await p.locator('summary:has-text("Notifications")').innerText();
  ok('notification reçue par le partenaire', /\d/.test(bell), bell.replace(/\s+/g, ' '));
  await p.locator('summary:has-text("Notifications")').click();
  await p.waitForTimeout(400);
  ok('publication annoncée', (await p.locator('text=publié').count()) > 0);
  await shot(p, '07-notifications');

  console.log('\n━━ 8. Sécurité ━━');
  const anon = await browser.newContext({ viewport: { width: 1100, height: 800 } });
  const a = await anon.newPage();
  for (const route of ['/admin', '/admin/comptes', '/partenaire', '/scan']) {
    await a.goto(BASE + route, { waitUntil: 'networkidle' });
    ok(`accès anonyme refusé sur ${route}`, /connexion/.test(a.url()));
  }
  await login(a, 'kossi@exemple.tg');
  await a.goto(`${BASE}/admin`, { waitUntil: 'networkidle' });
  ok('participant redirigé hors de /admin', !a.url().includes('/admin'), a.url().replace(BASE, ''));

  // Limitation de débit — sur une adresse jetable : marteler un compte réel
  // le bloquerait 15 minutes et fausserait la suite de la recette.
  const victim = `intrus-${Date.now()}@exemple.tg`;
  for (let i = 0; i < 9; i++) {
    await a.goto(`${BASE}/connexion`, { waitUntil: 'domcontentloaded' });
    await a.fill('input[name=email]', victim);
    await a.fill('input[name=password]', 'faux-' + i);
    await a.getByRole('button', { name: /Se connecter|Connexion…/ }).click();
    await a.waitForTimeout(350);
  }
  const limited = await a.locator('[role=alert]').first().innerText().catch(() => '');
  ok('limitation de débit active', /Trop de tentatives/.test(limited), limited.slice(0, 48));
  await anon.close();

  await browser.close();
  console.log(`\n━━ Résultat : ${pass} réussis, ${fail} échoués ━━`);
  console.log(errs.length ? `  ⚠ ${errs.length} erreurs console :\n     ${errs.slice(0, 4).join('\n     ')}` : '  ✓ aucune erreur console');
  if (fail || errs.length) process.exitCode = 1;
};

run().catch((e) => { console.error(String(e).slice(0, 600)); process.exit(1); });
