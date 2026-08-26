/**
 * Recette de la version PHP — tout le produit, dans un vrai navigateur.
 *
 * Mêmes scénarios que scripts/e2e-full.ts, contre l'implémentation PHP.
 * Les deux versions doivent se comporter à l'identique : c'est la seule
 * façon de savoir que le portage n'a rien perdu en route.
 */
import { chromium, type Browser, type Page } from 'playwright-core';

const EXE = '/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
const BASE = process.env.BASE_URL ?? 'http://127.0.0.1:3600';
const errs: string[] = [];
let pass = 0, fail = 0;

const ok = (label: string, cond: boolean, detail = '') => {
  cond ? pass++ : fail++;
  console.log(`  ${cond ? '✓' : '✗'} ${label}${detail ? ' — ' + detail : ''}`);
};

const ADMIN = {
  email: process.env.WAKABI_ADMIN ?? 'lyder@wakabileguide.com',
  mdp: process.env.WAKABI_ADMIN_MDP ?? 'un-mot-de-passe-solide-2026',
};

// Adresses uniques par exécution : la recette doit pouvoir se rejouer sans
// remettre la base à zéro, et une inscription refusée pour doublon ferait
// échouer un test qui ne parle pas d'inscription.
const marque = Date.now().toString(36);
const PART = { email: `partenaire-${marque}@exemple.tg`, mdp: 'partenaire-2026-solide' };
const VISITEUR = { email: `visiteur-${marque}@exemple.tg`, mdp: 'visiteur-2026-solide' };

async function connexion(p: Page, email: string, mdp: string) {
  await p.goto(`${BASE}/index.php?p=connexion`, { waitUntil: 'networkidle' });
  await p.fill('input[name=email]', email);
  await p.fill('input[name=mot_de_passe]', mdp);
  // `main` et non la page entière : la barre porte un bouton « Déconnexion »
  // de type submit, situé AVANT le formulaire dans le document.
  await p.click('main button[type=submit]');
  await p.waitForLoadState('networkidle');
}

async function inscription(p: Page, email: string, mdp: string, role: string, nom: string) {
  await p.goto(`${BASE}/index.php?p=inscription`, { waitUntil: 'networkidle' });
  await p.selectOption('select[name=role]', role);
  await p.fill('input[name=nom]', nom);
  await p.fill('input[name=email]', email);
  await p.fill('input[name=mot_de_passe]', mdp);
  await p.click('main button[type=submit]');
  await p.waitForLoadState('networkidle');
  // Échouer ICI plutôt que trois assertions plus loin, sur un symptôme.
  if (p.url().includes('p=inscription')) {
    const raison = await p.locator('.msg.err').first().innerText().catch(() => 'raison inconnue');
    throw new Error(`inscription refusée pour ${email} : ${raison}`);
  }
}

const run = async () => {
  const browser: Browser = await chromium.launch({ executablePath: EXE });
  const ctx = await browser.newContext({ viewport: { width: 1280, height: 900 }, deviceScaleFactor: 2 });
  const p = await ctx.newPage();
  p.on('pageerror', (e) => errs.push('PAGE ' + e.message));
  // Les polices Google ne sont pas joignables depuis cet environnement de
  // test ; l'échec de leur chargement n'est pas un défaut de l'application,
  // et les piles de repli sont déclarées. Tout le reste compte.
  const hors_sujet = (t: string) => /fonts\.(googleapis|gstatic)\.com|net::ERR_CONNECTION_RESET/.test(t);
  p.on('console', (c) => {
    if (c.type() === 'error' && !hors_sujet(c.text())) errs.push('CONSOLE ' + c.text());
  });

  console.log('\n━━ 1. Vitrine et catalogue ━━');
  await p.goto(BASE, { waitUntil: 'networkidle' });
  ok('vitrine chargée', /Wakabi Boost/.test(await p.title()));
  await p.goto(`${BASE}/index.php?p=decors`, { waitUntil: 'networkidle' });
  const n = await p.locator('a[href*="p=decor&slug="]').count();
  ok('catalogue peuplé', n >= 3, `${n} décors publiés`);

  console.log('\n━━ 2. Le Studio, sans compte ━━');
  await inscription(p, VISITEUR.email, VISITEUR.mdp, 'participant', 'Visiteur');
  const soldeAvant = Number((await p.locator('.stat.o b').first().innerText()).replace(/\D/g, ''));
  await p.goto(`${BASE}/index.php?p=decors`, { waitUntil: 'networkidle' });
  await p.locator('a[href*="p=decor&slug="]').first().click();
  // `networkidle` et non `waitForSelector` : le canevas est dans le DOM avant
  // que le script différé ne s'exécute, et poser la photo trop tôt n'appelle
  // aucun gestionnaire — le test échouait sans que l'application soit en cause.
  await p.waitForLoadState('networkidle');
  await p.waitForTimeout(1200);
  await p.setInputFiles('#photo', 'scripts/fixtures/photo.png');
  await p.waitForTimeout(3000);

  // Le QR doit être VISIBLE DANS L'APERÇU, pas seulement à l'export.
  const blanc = await p.evaluate(() => {
    const c = document.querySelector('canvas') as HTMLCanvasElement;
    const g = c.getContext('2d')!;
    const d = g.getImageData(Math.round(c.width * 0.07), Math.round(c.height * 0.86), 60, 60).data;
    let b = 0;
    for (let i = 0; i < d.length; i += 4) if (d[i] > 240 && d[i + 1] > 240 && d[i + 2] > 240) b++;
    return b / (d.length / 4);
  });
  ok('QR visible dans l’aperçu', blanc > 0.15, `${Math.round(blanc * 100)} % de blanc au coin bas-gauche`);

  const jeton = (await p.locator('#jeton').innerText()).trim();
  ok('jeton du badge affiché', /^[2-9A-Z]{10}$/.test(jeton), jeton);

  console.log('\n━━ 3. La page du QR ━━');
  let r = await p.goto(`${BASE}/index.php?p=qr&jeton=${jeton}`, { waitUntil: 'networkidle' });
  ok('la page du QR répond', r?.status() === 200, `${r?.status()}`);
  ok('badge annoncé valide', /Badge valide/.test(await p.locator('h1').innerText()));
  await p.goto(`${BASE}/index.php?p=qr&jeton=ZZZZZZZZZZ`, { waitUntil: 'networkidle' });
  ok('QR d’un code inconnu refusé', /introuvable/i.test(await p.locator('h1').innerText()));

  console.log('\n━━ 4. Contrôle d’entrée ━━');
  await connexion(p, ADMIN.email, ADMIN.mdp);
  await p.goto(`${BASE}/index.php?p=scan`, { waitUntil: 'networkidle' });
  await p.fill('input[name=jeton]', jeton);
  await p.click('main button[type=submit]');
  await p.waitForLoadState('networkidle');
  ok('entrée validée', /Entrée validée/.test(await p.locator('[role=status]').first().innerText()));

  await p.fill('input[name=jeton]', jeton);
  await p.click('main button[type=submit]');
  await p.waitForLoadState('networkidle');
  ok('rescan refusé (idempotent)', /déjà été scanné/.test(await p.locator('[role=status]').first().innerText()));

  await p.fill('input[name=jeton]', 'ZZZZZZZZZZ');
  await p.click('main button[type=submit]');
  await p.waitForLoadState('networkidle');
  ok('code inconnu refusé', /inconnu/i.test(await p.locator('[role=status]').first().innerText()));

  console.log('\n━━ 5. Koris crédités ━━');
  await connexion(p, VISITEUR.email, VISITEUR.mdp);
  const soldeApres = Number((await p.locator('.stat.o b').first().innerText()).replace(/\D/g, ''));
  ok('Koris crédités après scan', soldeApres === soldeAvant + 50, `${soldeAvant} → ${soldeApres}`);

  console.log('\n━━ 6. Le garde-fou de redirection ━━');
  await inscription(p, PART.email, PART.mdp, 'partenaire', 'Test Partenaire');
  await p.goto(`${BASE}/index.php?p=nouveau`, { waitUntil: 'networkidle' });
  await p.setInputFiles('input[name=cadre]', 'php/public/cadres/jy-serai.png');
  await p.fill('input[name=titre]', 'Décor hors domaine');
  await p.fill('input[name=redirection]', 'https://mon-restaurant.tg/promo');
  await p.click('main button[type=submit]');
  await p.waitForLoadState('networkidle');
  const refus = await p.locator('.msg.err').first().innerText().catch(() => '');
  ok('redirection hors Wakabi refusée', /domaine Wakabi/.test(refus), refus.slice(0, 56));

  console.log('\n━━ 7. Le pré-vol ━━');
  await p.goto(`${BASE}/index.php?p=nouveau`, { waitUntil: 'networkidle' });
  await p.setInputFiles('input[name=cadre]', 'scripts/fixtures/opaque.png');
  await p.fill('input[name=titre]', 'Cadre aplati de test');
  await p.fill('input[name=redirection]', 'https://wakabileguide.com/p/test');
  await p.click('main button[type=submit]');
  await p.waitForLoadState('networkidle');
  await p.locator('form[action*="p=soumettre"] button').first().click();
  await p.waitForLoadState('networkidle');
  const bloque = await p.locator('.msg.err').first().innerText().catch(() => '');
  ok('pré-vol bloque le cadre opaque', /recouvre/.test(bloque), bloque.slice(0, 56));

  console.log('\n━━ 8. Modération ━━');
  await connexion(p, ADMIN.email, ADMIN.mdp);
  await p.goto(`${BASE}/index.php?p=relecture`, { waitUntil: 'networkidle' });
  ok('rapport de pré-vol affiché', (await p.locator('text=Contrôle automatique').count()) > 0);
  const avant = await p.locator('button:has-text("Approuver")').count();
  ok('la file contient la soumission de démonstration', avant >= 1, `${avant} en attente`);
  await p.locator('button:has-text("Approuver")').first().click();
  await p.waitForLoadState('networkidle');
  ok('approbation traitée', (await p.locator('button:has-text("Approuver")').count()) === avant - 1);

  // Approuver ne suffit pas : le décor doit être RÉELLEMENT ouvrable.
  r = await p.goto(`${BASE}/index.php?p=decor&slug=soiree-maquis-akwaba`, { waitUntil: 'networkidle' });
  ok('décor approuvé réellement ouvrable', r?.status() === 200, `${r?.status()}`);
  ok('le Studio se charge dessus', (await p.locator('#toile').count()) > 0);

  console.log('\n━━ 9. Sécurité ━━');
  const anon = await browser.newContext();
  const a = await anon.newPage();
  for (const route of ['?p=admin', '?p=comptes', '?p=partenaire', '?p=scan', '?p=relecture']) {
    await a.goto(`${BASE}/index.php${route}`, { waitUntil: 'networkidle' });
    ok(`accès anonyme refusé sur ${route}`, /connexion/.test(a.url()), a.url().split('index.php')[1] ?? '');
  }
  await connexion(a, VISITEUR.email, VISITEUR.mdp);
  await a.goto(`${BASE}/index.php?p=admin`, { waitUntil: 'networkidle' });
  ok('participant écarté de /admin', !a.url().includes('p=admin'), a.url().split('index.php')[1] ?? '');

  // Un fichier de l'application ne doit pas être servi directement.
  const direct = await a.goto(`${BASE}/app/bootstrap.php`, { waitUntil: 'domcontentloaded' });
  const corps = await a.content();
  ok('les fichiers internes ne fuient pas', !/PDO|function db\(/.test(corps), `HTTP ${direct?.status()}`);

  console.log('\n━━ 10. Limitation de débit ━━');
  for (let i = 0; i < 9; i++) {
    await a.goto(`${BASE}/index.php?p=connexion`, { waitUntil: 'domcontentloaded' });
    await a.fill('input[name=email]', `brute-${marque}@exemple.tg`);
    await a.fill('input[name=password], input[name=mot_de_passe]', 'faux' + i);
    await a.click('main button[type=submit]');
    await a.waitForLoadState('domcontentloaded');
  }
  const limite = await a.locator('[role=alert]').first().innerText().catch(() => '');
  ok('limitation de débit active', /Trop de tentatives/.test(limite), limite.slice(0, 48));

  await browser.close();
  console.log(`\n━━ Résultat : ${pass} réussis, ${fail} échoués ━━`);
  if (errs.length) {
    console.log('  ✗ erreurs console :');
    for (const e of [...new Set(errs)].slice(0, 8)) console.log('    ' + e.slice(0, 130));
  } else {
    console.log('  ✓ aucune erreur console');
  }
  process.exitCode = fail || errs.length ? 1 : 0;
};

run();
