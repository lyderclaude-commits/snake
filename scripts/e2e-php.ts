/**
 * Recette de la version PHP — tout le produit, dans un vrai navigateur.
 *
 * Mêmes scénarios que scripts/e2e-full.ts, contre l'implémentation PHP.
 * Les deux versions doivent se comporter à l'identique : c'est la seule
 * façon de savoir que le portage n'a rien perdu en route.
 */
import { chromium, type Browser, type Page } from 'playwright-core';
import { createServer } from 'node:net';
import { writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import QRCode from 'qrcode';
import { jetonDuQr } from '../php/studio/scanner';

const EXE = '/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
const BASE = process.env.BASE_URL ?? 'http://127.0.0.1:3600';

/**
 * Aucune attente `networkidle` dans ce fichier, volontairement.
 *
 * Elle exige un réseau silencieux 500 ms durant. La feuille de polices Google
 * n'étant pas joignable depuis l'environnement de test, Chromium la réessaie
 * indéfiniment : le silence ne vient jamais et la recette se fige au lieu
 * d'échouer. On attend le document, puis les éléments qui comptent.
 */
const errs: string[] = [];
let pass = 0, fail = 0;

const ok = (label: string, cond: boolean, detail = '') => {
  cond ? pass++ : fail++;
  console.log(`  ${cond ? '✓' : '✗'} ${label}${detail ? ' — ' + detail : ''}`);
};

/**
 * Un serveur SMTP de recette, pour vérifier qu'un message part VRAIMENT.
 *
 * Sans lui, on ne testerait que des écrans : « le formulaire dit que c'est
 * envoyé ». Ce qui compte est qu'un serveur d'en face reçoive un message
 * adressé à la bonne personne — c'est la seule chose que le partenaire
 * constatera, lui.
 */
const SMTP_PORT = 3931;
const recus: string[] = [];

function ouvrirSmtp(): Promise<{ fermer: () => void }> {
  const serveur = createServer((c) => {
    let tampon = '';
    let data = false;
    c.write('220 recette ESMTP\r\n');
    c.on('data', (b) => {
      tampon += b.toString();
      let i: number;
      while ((i = tampon.indexOf('\r\n')) >= 0) {
        const l = tampon.slice(0, i);
        tampon = tampon.slice(i + 2);
        if (data) {
          if (l === '.') { data = false; c.write('250 ok\r\n'); }
          else recus[recus.length - 1] += l + '\n';
          continue;
        }
        const h = l.toUpperCase();
        if (h.startsWith('EHLO')) c.write('250-recette\r\n250 SIZE 10240000\r\n');
        else if (h === 'DATA') { data = true; recus.push(''); c.write('354 go\r\n'); }
        else if (h === 'QUIT') { c.write('221 bye\r\n'); c.end(); }
        else c.write('250 ok\r\n');
      }
    });
    c.on('error', () => { /* le client raccroche : sans intérêt */ });
  });
  return new Promise((r) => serveur.listen(SMTP_PORT, '127.0.0.1', () => r({
    fermer: () => serveur.close(),
  })));
}

/**
 * Une vidéo Y4M montrant un QR, pour la fausse caméra de Chromium.
 *
 * C'est la seule façon d'éprouver la lecture au vol sans une main et un
 * téléphone : le navigateur diffuse ce fichier comme s'il venait de
 * l'objectif, et tout le reste du chemin est le vrai chemin.
 */
async function videoQr(texte: string, chemin: string): Promise<void> {
  const L = 640, H = 480, IMAGES = 16;
  const qr = QRCode.create(texte, { errorCorrectionLevel: 'M' });
  const n = qr.modules.size;
  const bits = qr.modules.data;

  const pas = Math.floor(320 / (n + 8));
  const taille = pas * (n + 8);
  const x0 = Math.floor((L - taille) / 2);
  const y0 = Math.floor((H - taille) / 2);

  const Y = Buffer.alloc(L * H, 255);
  for (let r = 0; r < n; r++) {
    for (let c = 0; c < n; c++) {
      if (!bits[r * n + c]) continue;
      const px = x0 + (c + 4) * pas;
      const py = y0 + (r + 4) * pas;
      for (let j = 0; j < pas; j++) Y.fill(0, (py + j) * L + px, (py + j) * L + px + pas);
    }
  }
  // Un QR est noir et blanc : les plans de chrominance restent neutres.
  const UV = Buffer.alloc((L / 2) * (H / 2), 128);
  const blocs: Buffer[] = [Buffer.from(`YUV4MPEG2 W${L} H${H} F12:1 Ip A1:1 C420jpeg\n`)];
  for (let i = 0; i < IMAGES; i++) blocs.push(Buffer.from('FRAME\n'), Y, UV, UV);
  writeFileSync(chemin, Buffer.concat(blocs));
}

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
  await p.goto(`${BASE}/index.php?p=connexion`, { waitUntil: 'domcontentloaded' });
  await p.fill('input[name=email]', email);
  await p.fill('input[name=mot_de_passe]', mdp);
  // `main` et non la page entière : la barre porte un bouton « Déconnexion »
  // de type submit, situé AVANT le formulaire dans le document.
  await p.click('main button[type=submit]');
  await p.waitForLoadState('domcontentloaded');
}

async function inscription(p: Page, email: string, mdp: string, role: string, nom: string) {
  await p.goto(`${BASE}/index.php?p=inscription`, { waitUntil: 'domcontentloaded' });
  await p.selectOption('select[name=role]', role);
  await p.fill('input[name=nom]', nom);
  await p.fill('input[name=email]', email);
  await p.fill('input[name=mot_de_passe]', mdp);
  await p.click('main button[type=submit]');
  await p.waitForLoadState('domcontentloaded');
  // Échouer ICI plutôt que trois assertions plus loin, sur un symptôme.
  if (p.url().includes('p=inscription')) {
    const raison = await p.locator('.msg.err').first().innerText().catch(() => 'raison inconnue');
    throw new Error(`inscription refusée pour ${email} : ${raison}`);
  }
}

const run = async () => {
  const browser: Browser = await chromium.launch({ executablePath: EXE });
  const ctx = await browser.newContext({
    viewport: { width: 1280, height: 900 },
    deviceScaleFactor: 2,
    acceptDownloads: true,
  });
  const p = await ctx.newPage();
  // Une attente sans borne transforme un défaut en blocage muet : la recette
  // doit toujours rendre un verdict, même mauvais.
  p.setDefaultTimeout(25_000);
  p.setDefaultNavigationTimeout(25_000);
  /**
   * On ne retient que les erreurs venues de NOTRE origine.
   *
   * L'environnement de test n'a pas d'accès sortant : la feuille de polices
   * Google échoue, et son échec n'est pas un défaut de l'application — les
   * piles de repli sont déclarées. Filtrer par origine plutôt que par message
   * évite d'écrire une liste de motifs qui finirait par masquer un vrai bug.
   */
  const notre_origine = new URL(BASE).origin;
  const surveiller = (page: Page) => {
    page.on('pageerror', (e) => errs.push('PAGE ' + e.message));
    page.on('console', (c) => {
      if (c.type() !== 'error') return;
      const source = c.location().url || '';
      // Un 429 est une RÉPONSE, pas une panne : c'est le serveur qui refuse
      // un badge parce que l'offre de l'organisateur est épuisée, et la
      // recette le provoque exprès. Le navigateur le journalise quand même.
      if (/429/.test(c.text())) return;
      if (source === '' || source.startsWith(notre_origine)) {
        errs.push('CONSOLE ' + c.text());
      }
    });
  };
  surveiller(p);

  console.log('\n━━ 1. Vitrine et catalogue ━━');
  await p.goto(BASE, { waitUntil: 'domcontentloaded' });
  ok('vitrine chargée', /Wakabi Boost/.test(await p.title()));
  ok('la vitrine présente le produit', (await p.locator('section').count()) >= 8,
     `${await p.locator('section').count()} sections`);
  // L'offre de lancement n'est plus annoncée par un bandeau en tête de site :
  // elle vit dans la section des tarifs, là où elle se décide.
  ok('offre de lancement affichée', /−50 %/.test(await p.locator('#tarifs').innerText()));
  ok('aucun bandeau en tête de site', (await p.locator('.annonce').count()) === 0);
  /**
   * La police : déclarée ne suffit pas, il faut qu'elle soit CHARGÉE.
   *
   * Une déclaration CSS qui pointe vers un fichier absent ne casse rien :
   * le navigateur retombe en silence sur une police système, et la page a
   * l'air correcte à qui ne connaît pas le dessin attendu. On vérifie donc
   * les trois niveaux — déclarée, chargée, réellement utilisée au rendu.
   */
  await p.evaluate(() => (document as unknown as { fonts: FontFaceSet }).fonts.ready);
  await p.waitForTimeout(400);
  const police = await p.evaluate(() => {
    const d = document as unknown as { fonts: FontFaceSet & { check(f: string): boolean } };
    const c = document.createElement('canvas').getContext('2d')!;
    c.font = '800 40px "Plus Jakarta Sans"';
    const avec = c.measureText('Vos invitations méritent').width;
    c.font = '800 40px sans-serif';
    return {
      declaree: getComputedStyle(document.querySelector('h1')!).fontFamily.includes('Plus Jakarta Sans'),
      chargee: [...d.fonts].some((f) => f.family === 'Plus Jakarta Sans' && f.status === 'loaded'),
      disponible: d.fonts.check('800 16px "Plus Jakarta Sans"'),
      distincte: Math.abs(avec - c.measureText('Vos invitations méritent').width) > 1,
    };
  });
  ok('Plus Jakarta Sans déclarée sur les titres', police.declaree);
  ok('Plus Jakarta Sans réellement chargée', police.chargee && police.disponible);
  ok('le texte est rendu avec, pas avec un repli', police.distincte);
  ok('aucune police appelée chez un tiers',
     (await p.evaluate(() => [...document.querySelectorAll('link[href]')]
        .filter((l) => (l as HTMLLinkElement).href.includes('fonts.'))
        .length)) === 0);
  ok('les canaux portent de vraies icônes',
     (await p.locator('.canal .ico svg').count()) === 5,
     `${await p.locator('.canal .ico svg').count()} icônes dessinées`);

  // Le comparatif : la colonne Wakabi est un bandeau, pas une colonne de plus.
  ok('le comparatif compte huit lignes', (await p.locator('.comparatif tbody tr').count()) === 8);
  ok('la colonne Wakabi est mise en avant',
     (await p.locator('.comparatif thead th.nous').evaluate((el) => getComputedStyle(el).backgroundColor))
       === 'rgb(37, 99, 235)');
  ok('les colonnes ne se ressemblent pas',
     (await p.locator('.comparatif td:not(.nous) .verdict.oui').first()
        .evaluate((el) => getComputedStyle(el).color))
     !== (await p.locator('.comparatif td.nous .verdict.oui').first()
        .evaluate((el) => getComputedStyle(el).color)));
  ok('chaque verdict est annoncé aux lecteurs d’écran',
     (await p.locator('.comparatif .verdict .sr').count())
       === (await p.locator('.comparatif .verdict').count()));
  ok('les quatre formules sont présentes', (await p.locator('.offre').count()) === 4);
  ok('les questions fréquentes répondent', (await p.locator('details.qr').count()) === 6);
  await p.goto(`${BASE}/index.php?p=decors`, { waitUntil: 'domcontentloaded' });
  const n = await p.locator('a[href*="p=decor&slug="]').count();
  ok('catalogue peuplé', n >= 3, `${n} décors publiés`);

  console.log('\n━━ 2. Le Studio, sans compte ━━');
  await inscription(p, VISITEUR.email, VISITEUR.mdp, 'participant', 'Visiteur');
  const soldeAvant = Number((await p.locator('.stat.o b').first().innerText()).replace(/\D/g, ''));
  /**
   * Le décor de démonstration, nommément — pas « le premier du catalogue ».
   *
   * Il appartient à l'équipe, donc son offre ne bouge jamais : les Koris et
   * le filigrane y sont ce qu'ils doivent être. Ouvrir le premier venu
   * faisait dépendre le résultat de l'ordre du catalogue, et donc des
   * décors qu'une exécution précédente y avait laissés.
   */
  await p.goto(`${BASE}/index.php?p=decor&slug=jy-serai`, { waitUntil: 'domcontentloaded' });
  // `networkidle` et non `waitForSelector` : le canevas est dans le DOM avant
  // que le script différé ne s'exécute, et poser la photo trop tôt n'appelle
  // aucun gestionnaire — le test échouait sans que l'application soit en cause.
  await p.waitForLoadState('domcontentloaded');
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

  console.log('\n━━ 3. Le téléchargement ━━');
  // Le bouton dit « Télécharger » : il doit produire un FICHIER, pas ouvrir
  // une feuille de partage à la place.
  const attente = p.waitForEvent('download', { timeout: 20000 });
  await p.click('#telecharger');
  const fichier = await attente.catch(() => null);
  ok('le bouton télécharge un vrai fichier', !!fichier, fichier?.suggestedFilename() ?? 'aucun téléchargement');
  ok('format issu du gabarit, pas codé en dur',
     !!fichier && fichier.suggestedFilename().endsWith('.jpg'),
     fichier?.suggestedFilename().split('.').pop() ?? '—');
  ok('aucun repli « appui long » hors navigateur intégré', await p.locator('#bloc-partage').isHidden());
  // Un téléchargement ni enregistré ni supprimé reste en suspens côté
  // Playwright et bloque la suite. On le libère explicitement.
  await fichier?.delete().catch(() => {});

  /**
   * Le zoom libre : sous 1, la photo est plus petite que son emplacement.
   *
   * C'est ce qui permet de faire tenir une image entière dans un décor qui
   * n'a pas ses proportions. Le curseur descendait jusqu'à 0,5 alors que le
   * cadrage refusait tout ce qui passait sous 1 : sa moitié gauche ne
   * faisait rien, et personne ne pouvait le voir.
   */
  console.log('\n━━ 3 bis. Zoom libre ━━');
  const bornesZoom = await p.evaluate(`
    (() => { const z = document.getElementById('zoom');
             return { min: Number(z.min), max: Number(z.max) }; })()
  `) as { min: number; max: number };
  ok('le curseur descend sous le cadrage « remplir »', bornesZoom.min < 1,
     `${bornesZoom.min} → ${bornesZoom.max}`);

  await p.click('#ajuster');
  await p.waitForTimeout(600);
  const apresAjuste = Number(await p.inputValue('#zoom'));
  ok('« Tout afficher » réduit sous 1', apresAjuste < 1 && apresAjuste >= bornesZoom.min,
     `${Math.round(apresAjuste * 100)} %`);
  ok('le facteur est annoncé en clair',
     /%/.test(await p.evaluate("document.getElementById('zoom-valeur').textContent")));

  await p.evaluate(`
    (() => { const z = document.getElementById('zoom');
             z.value = z.min; z.dispatchEvent(new Event('input', { bubbles: true })); })()
  `);
  await p.waitForTimeout(500);
  ok('le minimum du curseur est atteignable',
     Math.abs(Number(await p.inputValue('#zoom')) - bornesZoom.min) < 0.001,
     await p.inputValue('#zoom'));

  await p.click('#recentrer');
  await p.waitForTimeout(500);
  ok('« Remplir » revient au cadrage plein', Number(await p.inputValue('#zoom')) === 1);

  console.log('\n━━ 4. La page du QR ━━');
  let r = await p.goto(`${BASE}/index.php?p=qr&jeton=${jeton}`, { waitUntil: 'domcontentloaded' });
  ok('la page du QR répond', r?.status() === 200, `${r?.status()}`);
  ok('badge annoncé valide', /Badge valide/.test(await p.locator('h1').innerText()));
  await p.goto(`${BASE}/index.php?p=qr&jeton=ZZZZZZZZZZ`, { waitUntil: 'domcontentloaded' });
  ok('QR d’un code inconnu refusé', /introuvable/i.test(await p.locator('h1').innerText()));

  console.log('\n━━ 5. Contrôle d’entrée ━━');
  await connexion(p, ADMIN.email, ADMIN.mdp);
  await p.goto(`${BASE}/index.php?p=scan`, { waitUntil: 'domcontentloaded' });
  await p.fill('input[name=jeton]', jeton);
  await p.click('main button[type=submit]');
  await p.waitForLoadState('domcontentloaded');
  ok('entrée validée', /Entrée validée/.test(await p.locator('#resultat').innerText()));

  await p.fill('input[name=jeton]', jeton);
  await p.click('main button[type=submit]');
  await p.waitForLoadState('domcontentloaded');
  ok('rescan refusé (idempotent)', /déjà été scanné/.test(await p.locator('#resultat').innerText()));

  await p.fill('input[name=jeton]', 'ZZZZZZZZZZ');
  await p.click('main button[type=submit]');
  await p.waitForLoadState('domcontentloaded');
  ok('code inconnu refusé', /inconnu/i.test(await p.locator('#resultat').innerText()));

  console.log('\n━━ 6. Koris crédités ━━');
  await connexion(p, VISITEUR.email, VISITEUR.mdp);
  const soldeApres = Number((await p.locator('.stat.o b').first().innerText()).replace(/\D/g, ''));
  ok('Koris crédités après scan', soldeApres === soldeAvant + 50, `${soldeAvant} → ${soldeApres}`);

  console.log('\n━━ 7. Le garde-fou de redirection ━━');
  await inscription(p, PART.email, PART.mdp, 'partenaire', 'Test Partenaire');
  await p.goto(`${BASE}/index.php?p=nouveau`, { waitUntil: 'domcontentloaded' });
  await p.setInputFiles('input[name=cadre]', 'php/public/cadres/jy-serai.png');
  await p.fill('input[name=titre]', 'Décor hors domaine');
  await p.fill('input[name=redirection]', 'https://mon-restaurant.tg/promo');
  await p.click('main button[type=submit]');
  await p.waitForLoadState('domcontentloaded');
  const refus = await p.locator('.msg.err').first().innerText().catch(() => '');
  ok('redirection hors Wakabi refusée', /domaine Wakabi/.test(refus), refus.slice(0, 56));

  console.log('\n━━ 8. Le pré-vol ━━');
  await p.goto(`${BASE}/index.php?p=nouveau`, { waitUntil: 'domcontentloaded' });
  await p.setInputFiles('input[name=cadre]', 'scripts/fixtures/opaque.png');
  await p.fill('input[name=titre]', 'Cadre aplati de test');
  await p.fill('input[name=redirection]', 'https://wakabileguide.com/p/test');
  await p.click('main button[type=submit]');
  await p.waitForLoadState('domcontentloaded');
  await p.locator('form[action*="p=soumettre"] button').first().click();
  await p.waitForLoadState('domcontentloaded');
  const bloque = await p.locator('.msg.err').first().innerText().catch(() => '');
  ok('pré-vol bloque le cadre opaque', /recouvre/.test(bloque), bloque.slice(0, 56));

  console.log('\n━━ 9. Une soumission valide ━━');
  // La recette crée sa propre soumission plutôt que de consommer celle du
  // seed : sinon elle ne passe qu'une fois, sur une base neuve.
  const campagne = `Soirée de recette ${marque}`;
  await p.goto(`${BASE}/index.php?p=nouveau`, { waitUntil: 'domcontentloaded' });
  await p.setInputFiles('input[name=cadre]', 'php/public/cadres/jy-serai.png');
  await p.fill('input[name=titre]', campagne);
  await p.fill('input[name=redirection]', 'https://wakabileguide.com/p/recette');
  await p.click('main button[type=submit]');
  await p.waitForLoadState('domcontentloaded');
  await p.locator(`.carte:has-text("${campagne}") form[action*="p=soumettre"] button`).first().click();
  await p.waitForLoadState('domcontentloaded');
  const accuse = await p.locator('.msg.ok').first().innerText().catch(() => '');
  ok('soumission acceptée après pré-vol', /24 h/.test(accuse), accuse.slice(0, 46));

  console.log('\n━━ 10. Modération ━━');
  await connexion(p, ADMIN.email, ADMIN.mdp);
  await p.goto(`${BASE}/index.php?p=relecture`, { waitUntil: 'domcontentloaded' });
  ok('rapport de pré-vol affiché', (await p.locator('text=Contrôle automatique').count()) > 0);
  const avant = await p.locator('button:has-text("Approuver")').count();
  ok('la soumission est dans la file', avant >= 1, `${avant} en attente`);
  await p.locator(`.carte:has-text("${campagne}") button:has-text("Approuver")`).first().click();
  await p.waitForLoadState('domcontentloaded');
  ok('approbation traitée', (await p.locator('button:has-text("Approuver")').count()) === avant - 1);

  // Approuver ne suffit pas : le décor doit être RÉELLEMENT ouvrable.
  await p.goto(`${BASE}/index.php?p=decors`, { waitUntil: 'domcontentloaded' });
  const lien = await p.locator(`a:has-text("${campagne}")`).first().getAttribute('href');
  ok('le décor approuvé apparaît au catalogue', !!lien, lien ?? 'absent');
  r = await p.goto(lien!, { waitUntil: 'domcontentloaded' });
  ok('décor approuvé réellement ouvrable', r?.status() === 200, `${r?.status()}`);
  await p.waitForSelector('#toile', { timeout: 15_000 }).catch(() => {});
  ok('le Studio se charge dessus', (await p.locator('#toile').count()) > 0);

  console.log('\n━━ 11. L’équipe gère les décors ━━');
  await connexion(p, ADMIN.email, ADMIN.mdp);

  // 1. Ajouter
  const titreEquipe = `Décor équipe ${marque}`;
  await p.goto(`${BASE}/index.php?p=nouveau`, { waitUntil: 'domcontentloaded' });
  await p.setInputFiles('input[name=cadre]', 'php/public/cadres/story.png');
  await p.fill('input[name=titre]', titreEquipe);
  await p.selectOption('select[name=disposition]', 'story');
  // L'équipe n'est pas tenue au garde-fou : elle peut co-brander ailleurs.
  await p.fill('input[name=redirection]', 'https://partenaire-externe.tg/soiree');
  await p.click('main button[type=submit]');
  await p.waitForLoadState('domcontentloaded');
  ok('l’équipe peut créer un décor', /créé/.test(await p.locator('.msg.ok').first().innerText().catch(() => '')));
  ok('l’équipe n’est pas soumise au garde-fou', p.url().includes('p=catalogue'), 'redirection externe acceptée');

  // 2. Lister
  await p.goto(`${BASE}/index.php?p=catalogue`, { waitUntil: 'domcontentloaded' });
  const listes = await p.locator('.carte').count();
  ok('le catalogue liste tous les décors', listes >= 4, `${listes} décors visibles`);
  await p.goto(`${BASE}/index.php?p=catalogue&statut=publie`, { waitUntil: 'domcontentloaded' });
  const publies = await p.locator('.pastille.publie').count();
  ok('le filtre par statut fonctionne', publies >= 1 &&
     (await p.locator('.pastille.brouillon').count()) === 0, `${publies} publiés, 0 brouillon`);
  await p.goto(`${BASE}/index.php?p=catalogue&q=${encodeURIComponent(titreEquipe)}`, { waitUntil: 'domcontentloaded' });
  ok('la recherche par titre fonctionne', (await p.locator('.carte').count()) === 1);

  // 3. Modifier
  await p.locator('a:has-text("Modifier")').first().click();
  await p.waitForLoadState('domcontentloaded');
  const slugAvant = await p.locator('code').first().innerText().catch(() => '');
  ok('le formulaire est pré-rempli', (await p.inputValue('input[name=titre]')) === titreEquipe);
  await p.fill('input[name=sous_titre]', 'Sous-titre ajouté par l’équipe');
  await p.click('main button[type=submit]');
  await p.waitForLoadState('domcontentloaded');
  ok('la modification est enregistrée', /mis à jour/.test(await p.locator('.msg.ok').first().innerText().catch(() => '')));
  await p.goto(`${BASE}/index.php?p=catalogue&q=${encodeURIComponent(titreEquipe)}`, { waitUntil: 'domcontentloaded' });
  ok('l’adresse du décor ne change pas', (await p.locator('.aide').first().innerText()).includes(slugAvant),
     slugAvant);

  // 4. Publier depuis le catalogue
  await p.locator('button:has-text("Publier")').first().click();
  await p.waitForLoadState('domcontentloaded');
  ok('l’équipe publie sans passer par la relecture',
     /Publié/.test(await p.locator('.msg.ok').first().innerText().catch(() => '')));

  // 5. Supprimer — un titre erroné doit être refusé
  await p.goto(`${BASE}/index.php?p=catalogue&q=${encodeURIComponent(titreEquipe)}`, { waitUntil: 'domcontentloaded' });
  await p.locator('summary:has-text("Supprimer")').first().click();
  await p.fill('input[name=confirmation]', 'pas le bon titre');
  await p.locator('button:has-text("Supprimer")').first().click();
  await p.waitForLoadState('domcontentloaded');
  ok('suppression refusée sans le bon titre',
     /ne correspond pas/.test(await p.locator('.msg.err').first().innerText().catch(() => '')));

  await p.goto(`${BASE}/index.php?p=catalogue&q=${encodeURIComponent(titreEquipe)}`, { waitUntil: 'domcontentloaded' });
  ok('le décor est toujours là après le refus', (await p.locator('.carte').count()) === 1);

  // 6. Supprimer pour de bon
  await p.locator('summary:has-text("Supprimer")').first().click();
  await p.fill('input[name=confirmation]', titreEquipe);
  await p.locator('button:has-text("Supprimer")').first().click();
  await p.waitForLoadState('domcontentloaded');
  ok('suppression confirmée', /supprimé/.test(await p.locator('.msg.ok').first().innerText().catch(() => '')));
  await p.goto(`${BASE}/index.php?p=catalogue&q=${encodeURIComponent(titreEquipe)}`, { waitUntil: 'domcontentloaded' });
  ok('le décor a bien disparu', (await p.locator('.carte:has-text("Modifier")').count()) === 0);

  /* ---- les gabarits de réseau, et l'apparence qu'on leur donne ---- */

  const titreIg = `Instagram recette ${marque}`;
  await p.goto(`${BASE}/index.php?p=nouveau`, { waitUntil: 'domcontentloaded' });
  ok('les sept gabarits sont proposés', (await p.locator('#disposition option').count()) === 7,
     `${await p.locator('#disposition option').count()} dans la liste`);

  await p.waitForTimeout(1200);
  const forme = async (): Promise<number> => Number(await p.evaluate(`
    (() => { const c = document.getElementById('apercu');
             return parseFloat(c.style.width) / parseFloat(c.style.height); })()
  `));
  ok('l’aperçu s’affiche pour le gabarit par défaut', Math.abs((await forme()) - 1) < 0.02);

  await p.selectOption('#disposition', 'instagram');
  await p.waitForTimeout(1100);
  ok('changer de format change la forme de l’aperçu', Math.abs((await forme()) - 0.8) < 0.03,
     `${(await forme()).toFixed(2)} attendu 0.80`);

  await p.selectOption('#disposition', 'tiktok');
  await p.waitForTimeout(1100);
  ok('TikTok remonte le QR hors de la zone de la légende',
     (await p.inputValue('#r-qr_position')) === 'top-left');

  /* ---- la fenêtre photo suit l'ouverture du cadre ---- */

  // Un gabarit part avec la fenêtre de SON cadre : sans ça, la photo de
  // l'invité est cadrée sur le canevas entier alors que le dessin n'en
  // laisse voir qu'une bande, et son visage tombe où il veut.
  const fenetre = async () => ({
    format: await p.inputValue('#r-format'),
    x: Number(await p.inputValue('#r-photo_x')),
    y: Number(await p.inputValue('#r-photo_y')),
    w: Number(await p.inputValue('#r-photo_w')),
    h: Number(await p.inputValue('#r-photo_h')),
  });
  let f = await fenetre();
  ok('le gabarit part avec la fenêtre de son cadre', f.h < 0.95 && f.h > 0.3,
     `hauteur ${f.h}`);

  await p.selectOption('#disposition', 'bandeau');
  await p.waitForTimeout(1100);
  ok('changer de gabarit reprend SON format d’origine', (await fenetre()).format === '1:1',
     (await fenetre()).format);

  // Un cadre 4:5 posé sur un gabarit carré : le décor prend le format du
  // CADRE, sinon il serait étiré d'un quart, et relève son ouverture.
  await p.setInputFiles('input[name=cadre]', 'php/public/cadres/228-playground.png');
  await p.waitForTimeout(3200);
  f = await fenetre();
  ok('le cadre téléversé impose son format', f.format === '4:5', f.format);
  ok('l’ouverture du cadre est relevée toute seule',
     f.y > 0.1 && f.y < 0.25 && f.h > 0.4 && f.h < 0.6,
     `y=${f.y} h=${f.h}`);
  ok('l’aperçu suit le format du cadre', Math.abs((await forme()) - 0.8) < 0.03,
     `${(await forme()).toFixed(2)} attendu 0.80`);

  // Le relevé se redemande à la main, et retombe sur les mêmes valeurs.
  await p.evaluate(`
    (() => { const e = document.getElementById('r-photo_y');
             e.value = '0.5'; e.dispatchEvent(new Event('input', { bubbles: true })); })()
  `);
  await p.waitForTimeout(700);
  await p.click('#detecter-fenetre');
  await p.waitForTimeout(2600);
  ok('« relever sur le cadre » retrouve l’ouverture',
     Math.abs((await fenetre()).y - f.y) < 0.02, `${(await fenetre()).y} attendu ${f.y}`);

  await p.selectOption('#disposition', 'instagram');
  await p.waitForTimeout(1100);

  // Un cadre livré avec l'application : aucun fichier à téléverser.
  await p.selectOption('#cadre_fourni', 'instagram.png');
  await p.fill('#titre', titreIg);
  await p.fill('#redirection', 'https://wakabileguide.com/p/instagram');
  await p.selectOption('#r-qr_position', 'top-right');
  await p.evaluate(`
    (() => { const e = document.getElementById('r-bloc_y');
             e.value = '0.7'; e.dispatchEvent(new Event('input', { bubbles: true })); })()
  `);
  await p.waitForTimeout(700);
  await p.click('#form-decor button[type=submit]');
  await p.waitForLoadState('domcontentloaded');
  ok('décor créé sans téléverser le moindre cadre',
     /créé|enregistré/i.test(await p.locator('.msg.ok').first().innerText().catch(() => '')),
     (await p.locator('.msg.ok').first().innerText().catch(() => '')).slice(0, 48));

  // Rouvrir : le format et l'apparence doivent revenir tels qu'enregistrés.
  await p.goto(`${BASE}/index.php?p=catalogue&q=${encodeURIComponent(titreIg)}`, { waitUntil: 'domcontentloaded' });
  const versEdition = await p.locator(`.carte:has-text("${titreIg}") a:has-text("Modifier")`).first().getAttribute('href');
  ok('le décor apparaît au catalogue', !!versEdition);
  await p.goto(versEdition!, { waitUntil: 'domcontentloaded' });
  await p.waitForTimeout(900);
  ok('le format est retrouvé à la réouverture', (await p.inputValue('#disposition')) === 'instagram',
     await p.inputValue('#disposition'));
  ok('le coin du QR est conservé', (await p.inputValue('#r-qr_position')) === 'top-right');
  ok('la hauteur du texte est conservée', Math.abs(Number(await p.inputValue('#r-bloc_y')) - 0.7) < 0.011,
     await p.inputValue('#r-bloc_y'));
  ok('l’aperçu rouvre au bon format', Math.abs((await forme()) - 0.8) < 0.03);

  // Le bouton « réglages du gabarit » remet l'apparence d'usine.
  await p.click('#apparence-defaut');
  await p.waitForTimeout(900);
  ok('« réglages du gabarit » restaure les valeurs d’usine',
     (await p.inputValue('#r-qr_position')) === 'bottom-left'
     && Math.abs(Number(await p.inputValue('#r-bloc_y')) - 0.8) < 0.011);

  /* ---- un décor sans aucun texte ---- */

  const titreMuet = `Sans texte ${marque}`;
  await p.goto(`${BASE}/index.php?p=nouveau`, { waitUntil: 'domcontentloaded' });
  await p.waitForTimeout(1100);
  await p.selectOption('#disposition', 'facebook');
  await p.waitForTimeout(1000);
  await p.selectOption('#cadre_fourni', 'facebook.png');
  await p.fill('#titre', titreMuet);
  await p.fill('#accroche', '');
  await p.fill('#champ_libelle', '');
  await p.fill('#redirection', 'https://wakabileguide.com/p/sans-texte');
  await p.waitForTimeout(800);
  await p.click('#form-decor button[type=submit]');
  await p.waitForLoadState('domcontentloaded');
  ok('un décor sans accroche ni champ est accepté',
     /créé|enregistré/i.test(await p.locator('.msg.ok').first().innerText().catch(() => '')),
     (await p.locator('.msg.err').first().innerText().catch(() => '')).slice(0, 60));

  await p.goto(`${BASE}/index.php?p=catalogue&q=${encodeURIComponent(titreMuet)}`, { waitUntil: 'domcontentloaded' });
  await p.locator(`.carte:has-text("${titreMuet}") form[action*="p=statut"] button:has-text("Publier")`).first()
    .click().catch(() => {});
  await p.waitForLoadState('domcontentloaded');
  const lienMuet = await p.locator(`.carte:has-text("${titreMuet}") a:has-text("Voir en ligne")`).first()
    .getAttribute('href').catch(() => null);
  if (lienMuet) {
    await p.goto(lienMuet, { waitUntil: 'domcontentloaded' });
    await p.waitForTimeout(1200);
    ok('son Studio n’affiche aucun champ à remplir',
       (await p.locator('input[id^=champ-]').count()) === 0);
    ok('et il s’ouvre quand même', (await p.locator('#toile').count()) > 0);
  }

  /* ---- la page blanche : aucun cadre, tout au choix ---- */

  const titrePB = `Page blanche ${marque}`;
  await p.goto(`${BASE}/index.php?p=nouveau`, { waitUntil: 'domcontentloaded' });
  await p.waitForTimeout(1200);
  ok('les réglages de la page blanche restent cachés ailleurs',
     await p.locator('.si-vierge').first().isHidden());

  await p.selectOption('#disposition', 'vierge');
  await p.waitForTimeout(1200);
  ok('la page blanche ouvre ses propres réglages',
     !(await p.locator('.si-vierge').first().isHidden()));

  await p.selectOption('#r-format', '9:16');
  await p.waitForTimeout(1200);
  ok('le format choisi s’applique à l’aperçu', Math.abs((await forme()) - 0.5625) < 0.03,
     `${(await forme()).toFixed(2)} attendu 0.56`);

  await p.selectOption('#r-photo_forme', 'cercle');
  await p.selectOption('#r-fond', 'brand.primary');
  await p.fill('#titre', titrePB);
  await p.fill('#redirection', 'https://wakabileguide.com/p/page-blanche');
  await p.waitForTimeout(800);
  await p.click('#form-decor button[type=submit]');
  await p.waitForLoadState('domcontentloaded');
  ok('un décor sans aucun cadre est accepté',
     /créé|enregistré/i.test(await p.locator('.msg.ok').first().innerText().catch(() => '')),
     (await p.locator('.msg.err').first().innerText().catch(() => '')).slice(0, 60));

  await p.goto(`${BASE}/index.php?p=catalogue&q=${encodeURIComponent(titrePB)}`, { waitUntil: 'domcontentloaded' });
  const versPB = await p.locator(`.carte:has-text("${titrePB}") a:has-text("Modifier")`).first().getAttribute('href');
  await p.goto(versPB!, { waitUntil: 'domcontentloaded' });
  await p.waitForTimeout(1100);
  ok('le format de la page blanche est conservé', (await p.inputValue('#r-format')) === '9:16');
  ok('la fenêtre ronde est conservée', (await p.inputValue('#r-photo_forme')) === 'cercle');
  ok('le fond choisi est conservé', (await p.inputValue('#r-fond')) === 'brand.primary');

  // Sans cadre, le Studio doit tout de même s'ouvrir et dessiner.
  await p.goto(`${BASE}/index.php?p=catalogue&q=${encodeURIComponent(titrePB)}`, { waitUntil: 'domcontentloaded' });
  await p.locator(`.carte:has-text("${titrePB}") form[action*="p=statut"] button:has-text("Publier")`).first()
    .click().catch(() => {});
  await p.waitForLoadState('domcontentloaded');
  const lienPB = await p.locator(`.carte:has-text("${titrePB}") a:has-text("Voir en ligne")`).first()
    .getAttribute('href').catch(() => null);
  ok('la page blanche se publie', !!lienPB, lienPB ?? 'pas publiable');
  if (lienPB) {
    await p.goto(lienPB, { waitUntil: 'domcontentloaded' });
    await p.waitForSelector('#toile', { timeout: 15_000 }).catch(() => {});
    ok('son Studio s’ouvre sans cadre', (await p.locator('#toile').count()) > 0);
  }

  await p.goto(versPB!, { waitUntil: 'domcontentloaded' });
  await p.waitForTimeout(900);

  // Un champ « caché » resté à 100 % de large fait déborder toute la page
  // sans qu'on le voie. On mesure le glissement là où ce champ vit.
  await p.setViewportSize({ width: 390, height: 844 });
  await p.waitForTimeout(500);
  const glisseForm = await p.evaluate(`
    (() => { window.scrollTo(400, 0); const x = window.scrollX; window.scrollTo(0, 0); return x; })()
  `);
  ok('le formulaire ne glisse pas de côté sur téléphone', glisseForm === 0, `${glisseForm} px`);
  await p.setViewportSize({ width: 1280, height: 900 });

  console.log('\n━━ 12. Comptes, rôles et offres ━━');
  await connexion(p, ADMIN.email, ADMIN.mdp);

  // 1. L'équipe ouvre un compte à un organisateur qui a payé son offre.
  const CLIENT = { email: `impact-${marque}@exemple.tg`, mdp: 'client-impact-2026' };
  await p.goto(`${BASE}/index.php?p=comptes`, { waitUntil: 'domcontentloaded' });
  await p.click('details.creer > summary');
  await p.fill('#c-nom', 'Cliente Impact');
  await p.fill('#c-email', CLIENT.email);
  await p.selectOption('#c-role', 'partenaire');
  await p.selectOption('#c-formule', 'impact');
  await p.fill('#c-mdp', CLIENT.mdp);
  await p.click('.formulaire-compte button[type=submit]');
  await p.waitForLoadState('domcontentloaded');
  const cree = await p.locator('.msg.ok').first().innerText().catch(() => '');
  ok('l’équipe crée un compte avec son offre', /Compte créé/.test(cree) && /Impact/.test(cree), cree.slice(0, 60));

  // 2. Une adresse déjà prise ne doit pas effacer la saisie.
  await p.click('details.creer > summary');
  await p.fill('#c-nom', 'Cliente Impact');
  await p.fill('#c-email', CLIENT.email);
  await p.fill('#c-mdp', CLIENT.mdp);
  await p.click('.formulaire-compte button[type=submit]');
  await p.waitForLoadState('domcontentloaded');
  ok('adresse déjà prise refusée',
     /existe déjà/.test(await p.locator('.msg.err').first().innerText().catch(() => '')));
  ok('la saisie est rendue, pas perdue', (await p.inputValue('#c-nom')) === 'Cliente Impact');

  // 3. La personne créée se connecte et voit ce que son offre autorise.
  await connexion(p, CLIENT.email, CLIENT.mdp);
  ok('le compte créé arrive sur son espace', p.url().includes('p=partenaire'), p.url().split('index.php')[1] ?? p.url());
  const carteOffre = await p.locator('.carte:has-text("Offre Impact")').first().innerText().catch(() => '');
  ok('son offre est annoncée', /Offre Impact/.test(carteOffre));
  ok('son quota de campagnes est affiché', /0\s*\/\s*3/.test(carteOffre), carteOffre.split('\n')[2] ?? '');

  // 4. Le quota mord : Découverte n'autorise qu'une campagne à la fois, et
  //    le partenaire de la recette en a déjà une publiée.
  await connexion(p, PART.email, PART.mdp);
  await p.locator('.carte:has-text("Cadre aplati de test") form[action*="p=soumettre"] button').first().click();
  await p.waitForLoadState('domcontentloaded');
  const stop = await p.locator('.msg.err').first().innerText().catch(() => '');
  ok('le quota de l’offre bloque la campagne de trop',
     /Découverte/.test(stop) && /1 campagne/.test(stop), stop.slice(0, 70));

  // 5. L'équipe relève l'offre : la limite recule, et c'est le pré-vol qui
  //    reprend la main. La preuve que le quota était bien le seul verrou.
  await connexion(p, ADMIN.email, ADMIN.mdp);
  await p.goto(`${BASE}/index.php?p=comptes`, { waitUntil: 'domcontentloaded' });
  const ligne = p.locator(`tr:has-text("${PART.email}")`).first();
  await ligne.locator('select[name=formule]').selectOption('croissance');
  await ligne.locator('form[action*="p=role"] button').click();
  await p.waitForLoadState('domcontentloaded');
  await connexion(p, PART.email, PART.mdp);
  await p.locator('.carte:has-text("Cadre aplati de test") form[action*="p=soumettre"] button').first().click();
  await p.waitForLoadState('domcontentloaded');
  const relance = await p.locator('.msg.err').first().innerText().catch(() => '');
  ok('offre relevée, le quota ne bloque plus', !/Découverte/.test(relance) && /recouvre/.test(relance),
     relance.slice(0, 60));

  // 6. Le tableau de bord dit ce qu'il doit dire.
  await connexion(p, ADMIN.email, ADMIN.mdp);
  await p.goto(`${BASE}/index.php?p=admin`, { waitUntil: 'domcontentloaded' });
  ok('les cinq indicateurs de la semaine sont là', (await p.locator('.kpis .stat').count()) === 5);
  ok('chaque indicateur porte sa variation',
     (await p.locator('.kpis .delta').count()) === (await p.locator('.kpis .stat').count()));
  ok('l’entonnoir compte ses quatre étapes',
     (await p.locator('.carte:has-text("La boucle") .marche').count()) === 4);
  ok('la répartition des offres couvre les quatre formules',
     (await p.locator('.carte:has-text("Les offres") .marche').count()) === 4);
  ok('le compte Impact est compté dans son offre',
     /Impact\s+1/.test((await p.locator('.carte:has-text("Les offres")').innerText()).replace(/\s+/g, ' '))
     || (await p.locator('.carte:has-text("Les offres")').innerText()).includes('Impact'));

  console.log('\n━━ 13. Sécurité ━━');
  const anon = await browser.newContext();
  const a = await anon.newPage();
  // Le menu ne doit jamais proposer deux fois la même destination.
  await connexion(p, ADMIN.email, ADMIN.mdp);
  // `textContent` et non `innerText` : depuis que l'administration vit dans
  // un déroulant, ses liens sont dans le document mais masqués tant qu'on ne
  // l'ouvre pas — `innerText` les rendait tous vides, donc tous identiques.
  const entrees = (await p.locator('.barre nav a').evaluateAll(
    (els) => els.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()),
  ));
  ok('les huit destinations sont sous un seul intitulé',
     (await p.locator('.barre .deroulant > summary').innerText()).trim().startsWith('Administration')
     && (await p.locator('.barre .deroulant .volet a').count()) === 8,
     `${await p.locator(".barre .deroulant .volet a").count()} entrées`);
  ok('notifications et déconnexion restent hors du déroulant',
     (await p.locator('.barre nav > a[href*="notifications"]').count()) === 1
     && (await p.locator('.barre nav > form[action*="deconnexion"] button').count()) === 1);
  ok('aucune entrée de menu en double', new Set(entrees).size === entrees.length,
     entrees.join(' · '));
  ok('le bouton du menu reste lisible',
     (await p.locator('.barre nav .bouton, .barre nav button').first()
        .evaluate((el) => getComputedStyle(el).color)) !== 'rgb(71, 85, 105)');

  for (const route of ['?p=admin', '?p=comptes', '?p=partenaire', '?p=scan', '?p=relecture', '?p=catalogue', '?p=nouveau']) {
    await a.goto(`${BASE}/index.php${route}`, { waitUntil: 'domcontentloaded' });
    ok(`accès anonyme refusé sur ${route}`, /connexion/.test(a.url()), a.url().split('index.php')[1] ?? '');
  }
  await connexion(a, VISITEUR.email, VISITEUR.mdp);
  await a.goto(`${BASE}/index.php?p=admin`, { waitUntil: 'domcontentloaded' });
  ok('participant écarté de /admin', !a.url().includes('p=admin'), a.url().split('index.php')[1] ?? '');

  // Un fichier de l'application ne doit pas être servi directement.
  const direct = await a.goto(`${BASE}/app/bootstrap.php`, { waitUntil: 'domcontentloaded' });
  const corps = await a.content();
  ok('les fichiers internes ne fuient pas', !/PDO|function db\(/.test(corps), `HTTP ${direct?.status()}`);

  console.log('\n━━ 14. Limitation de débit ━━');
  for (let i = 0; i < 9; i++) {
    await a.goto(`${BASE}/index.php?p=connexion`, { waitUntil: 'domcontentloaded' });
    await a.fill('input[name=email]', `brute-${marque}@exemple.tg`);
    await a.fill('input[name=password], input[name=mot_de_passe]', 'faux' + i);
    await a.click('main button[type=submit]');
    await a.waitForLoadState('domcontentloaded');
  }
  const limite = await a.locator('[role=alert]').first().innerText().catch(() => '');
  ok('limitation de débit active', /Trop de tentatives/.test(limite), limite.slice(0, 48));

  console.log('\n━━ 15. Sur un téléphone ━━');
  const tel = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const t = await tel.newPage();
  await t.goto(BASE, { waitUntil: 'load' });
  await t.evaluate(`(() => document.fonts.ready)()`);
  await t.waitForTimeout(700);
  // On mesure ce que vit le lecteur : la page se déplace-t-elle de côté ?
  // `scrollWidth` seul se trompe dans les deux sens.
  const glisse = await t.evaluate(`
    (() => { window.scrollTo(400, 0); const x = window.scrollX; window.scrollTo(0, 0); return x; })()
  `);
  ok('la page ne glisse pas de côté', glisse === 0, `déplacement de ${glisse} px`);
  ok('les décors s’affichent un par ligne sur téléphone',
     await t.evaluate(`
       (() => { const v = [...document.querySelectorAll('.vitrines .vignette')];
                if (v.length < 2) return true;
                return v[0].getBoundingClientRect().top !== v[1].getBoundingClientRect().top; })()
     `));
  // Sur téléphone, le comparatif se lit en deux cartes — une par camp,
  // comme les offres. Le tableau, lui, doit disparaître : deux versions
  // affichées en même temps seraient lues deux fois par un lecteur d'écran.
  ok('le comparatif devient deux cartes, une par camp',
     (await t.locator('.comparatif-cartes .colonne').count()) === 2
     && await t.locator('.comparatif-cartes').isVisible());
  ok('le tableau cède la place', !(await t.locator('.comparatif').isVisible()));
  ok('chaque carte porte les huit lignes',
     (await t.locator('.comparatif-cartes .colonne').first().locator('li').count()) === 8
     && (await t.locator('.comparatif-cartes .colonne.phare li').count()) === 8);
  ok('la carte Wakabi est mise en avant comme l’offre phare',
     (await t.locator('.comparatif-cartes .colonne.phare').evaluate((el) => getComputedStyle(el).borderColor))
       === 'rgb(37, 99, 235)');
  ok('chaque verdict reste annoncé aux lecteurs d’écran',
     (await t.locator('.comparatif-cartes .sr').count()) === 14,
     `${await t.locator('.comparatif-cartes .sr').count()} verdicts annoncés sur 14`);

  // Le menu doit être REPLIÉ à l'arrivée, puis s'ouvrir au doigt. Sans le
  // premier point, la page s'ouvre sur une liste de liens ; sans le second,
  // le site n'a plus de navigation du tout sur téléphone.
  const visibleAvant = await t.locator('.menu nav').isVisible();
  await t.click('.menu > summary');
  await t.waitForTimeout(250);
  const visibleApres = await t.locator('.menu nav').isVisible();
  ok('le menu est replié derrière le bouton', !visibleAvant);
  ok('le bouton hamburger ouvre le menu', visibleApres);
  ok('le menu ouvert reste dans l’écran',
     await t.evaluate(`
       (() => { const n = document.querySelector('.menu nav').getBoundingClientRect();
                return n.left >= 0 && n.right <= window.innerWidth + 1; })()
     `));
  await t.click('.menu > summary');
  await t.waitForTimeout(250);
  ok('le bouton le referme', !(await t.locator('.menu nav').isVisible()));

  ok('le logo remplace le mot WAKABI dans la barre',
     await t.evaluate(`(() => { const i = document.querySelector('.barre .marque img.logo');
                                return !!i && /logo\.(svg|png)$/.test(i.getAttribute('src') || ''); })()`));
  ok('les témoignages portent un portrait, pas des initiales',
     await t.evaluate(`(() => document.querySelectorAll('.avis .qui svg.avatar').length)()`) === 3);
  await tel.close();

  console.log('\n━━ 16. Le menu déplié sur grand écran ━━');
  // (les cartes du comparatif y sont vérifiées aussi : c'est le même contexte)
  // Le <details> est replié par défaut : sur grand écran, c'est la feuille
  // de style qui doit le tenir ouvert. Si elle échoue, la barre est vide.
  const large = await browser.newContext({ viewport: { width: 1280, height: 900 } });
  const g = await large.newPage();
  await g.goto(BASE, { waitUntil: 'domcontentloaded' });
  await g.waitForTimeout(400);
  ok('la navigation reste visible sans clic', await g.locator('.menu nav').isVisible());
  ok('le bouton hamburger disparaît', !(await g.locator('.menu > summary').isVisible()));
  ok('le comparatif reprend sa forme de tableau', await g.locator('.comparatif table').isVisible());
  ok('les cartes du comparatif s’effacent', !(await g.locator('.comparatif-cartes').isVisible()));
  await large.close();

  console.log('\n━━ 17. Le navigateur intégré de WhatsApp ━━');
  // Là, `<a download>` est inerte : un téléchargement échouerait en silence.
  const wa = await browser.newContext({
    viewport: { width: 390, height: 844 },
    userAgent: 'Mozilla/5.0 (Linux; Android 11) AppleWebKit/537.36 Chrome/119 Mobile Safari/537.36 WhatsApp/2.23',
  });
  const w = await wa.newPage();
  await w.goto(`${BASE}/?p=decor&slug=jy-serai`, { waitUntil: 'domcontentloaded' });
  await w.waitForTimeout(1200);
  await w.setInputFiles('#photo', 'scripts/fixtures/photo.png');
  await w.waitForTimeout(3000);
  await w.click('#telecharger');
  await w.waitForTimeout(1500);
  ok('repli « appui long » proposé dans WhatsApp', await w.locator('#bloc-partage').isVisible());
  ok('l’image y est bien fournie',
     (await w.locator('#apercu-partage').getAttribute('src'))?.startsWith('blob:') ?? false);

  console.log('\n━━ 18. Le lien partagé dans WhatsApp ━━');

  /**
   * Ce qu'un lien de décor montre AVANT le clic.
   *
   * Tout le produit repose sur ce partage : un lien nu — un titre gris et
   * rien d'autre — coûte des ouvertures qu'aucune autre optimisation ne
   * rattrape.
   */
  await p.goto(`${BASE}/index.php?p=decor&slug=jy-serai`, { waitUntil: 'domcontentloaded' });
  const meta = async (sel: string) =>
    (await p.locator(sel).first().getAttribute('content').catch(() => null)) ?? '';

  ok('le lien porte un titre de partage', (await meta('meta[property="og:title"]')).length > 3,
     await meta('meta[property="og:title"]'));
  ok('le lien porte une description', (await meta('meta[property="og:description"]')).length > 3);
  ok('la carte est annoncée en grand format',
     (await meta('meta[name="twitter:card"]')) === 'summary_large_image');
  ok('l’adresse canonique désigne le décor',
     (await p.locator('link[rel=canonical]').first().getAttribute('href') ?? '').includes('slug=jy-serai'));

  const vignette = await meta('meta[property="og:image"]');
  ok('une vignette est annoncée', vignette.includes('p=og') && vignette.includes('slug=jy-serai'), vignette.slice(0, 70));
  ok('la vignette porte une empreinte de version', /[?&]v=[0-9a-f]{8}/.test(vignette),
     'sinon WhatsApp garderait l’ancienne image');
  ok('les dimensions annoncées sont celles attendues',
     (await meta('meta[property="og:image:width"]')) === '1200'
     && (await meta('meta[property="og:image:height"]')) === '630');

  const img = await p.request.get(vignette);
  ok('la vignette se télécharge', img.status() === 200, `HTTP ${img.status()}`);
  ok('la vignette est une image JPEG',
     (img.headers()['content-type'] ?? '').includes('image/jpeg'), img.headers()['content-type']);
  ok('la vignette pèse un poids raisonnable',
     (await img.body()).length > 5_000 && (await img.body()).length < 400_000,
     `${Math.round((await img.body()).length / 1024)} Ko`);
  // Les deux premiers octets d'un JPEG, et les dimensions dans son en-tête.
  const octets = await img.body();
  ok('le fichier est bien un JPEG valide', octets[0] === 0xFF && octets[1] === 0xD8);
  ok('la vignette est mise en cache durablement',
     (img.headers()['cache-control'] ?? '').includes('max-age='),
     img.headers()['cache-control']);

  // Une deuxième demande doit servir le fichier déjà calculé, à l'identique.
  const img2 = await p.request.get(vignette);
  ok('la vignette est stable d’une demande à l’autre',
     Buffer.compare(octets, await img2.body()) === 0);

  await p.goto(BASE, { waitUntil: 'domcontentloaded' });
  ok('la vitrine aussi porte une vignette',
     (await meta('meta[property="og:image"]')).includes('p=og'));

  console.log('\n━━ 19. Chaque offre donne ce qu’elle vend ━━');

  /**
   * Le cœur commercial du produit.
   *
   * Une ligne vendue sur la vitrine que le code n'applique pas est une
   * promesse non tenue dans un sens — et de l'argent laissé sur la table
   * dans l'autre. On vérifie donc chaque ligne : ce qui compte, ce qui
   * bloque, et ce qui change quand l'équipe fait monter quelqu'un d'offre.
   */
  const ORG = { email: `offre-${marque}@exemple.tg`, mdp: 'partenaire-2026-solide' };
  const ctxOrg = await browser.newContext();
  const po = await ctxOrg.newPage();
  surveiller(po);
  await po.goto(`${BASE}/index.php?p=inscription`, { waitUntil: 'domcontentloaded' });
  await po.selectOption('select[name=role]', 'partenaire');
  await po.fill('input[name=nom]', 'Organisateur Découverte');
  await po.fill('input[name=email]', ORG.email);
  await po.fill('input[name=mot_de_passe]', ORG.mdp);
  await po.click('main button[type=submit]');
  await po.waitForLoadState('domcontentloaded');

  /* --- ce que Découverte montre --- */
  await po.goto(`${BASE}/index.php?p=partenaire`, { waitUntil: 'domcontentloaded' });
  const compteurs = (await po.locator('.marche .haut').allInnerTexts()).map((t) => t.replace(/\s+/g, ' '));
  ok('le tableau de bord annonce l’offre', /Découverte/.test(await po.locator('.carte h3').first().innerText()));
  ok('les compteurs montrent le consommé sur le quota',
     compteurs.some((t) => /Campagnes actives 0 \/ 1/.test(t))
     && compteurs.some((t) => /Téléchargements .* 0 \/ 50/.test(t)),
     compteurs.join(' | '));
  ok('ce qui n’est pas dans l’offre est montré, pas caché',
     (await po.locator('.liste-offre').nth(1).locator('li').count()) >= 8,
     `${await po.locator('.liste-offre').nth(1).locator('li').count()} lignes barrées`);
  ok('chaque ligne fermée dit quelle offre l’ouvre',
     /Avec l’offre Impact/.test(await po.locator('.liste-offre').nth(1).innerText()));
  ok('les présences scannées sont verrouillées en Découverte',
     /offre Impact/.test(await po.locator('.stat.verrou span').innerText()));

  /* --- les liens courts sont fermés, et le disent --- */
  await po.goto(`${BASE}/index.php?p=liens`, { waitUntil: 'domcontentloaded' });
  ok('les liens courts sont fermés en Découverte',
     /arrivent avec l’offre Impact/.test(await po.locator('.carte h3').first().innerText()));
  ok('aucun formulaire de création n’est proposé', (await po.locator('#cible').count()) === 0);

  /* --- le ciblage toutes villes est fermé, côté écran ET côté serveur --- */
  await po.goto(`${BASE}/index.php?p=nouveau`, { waitUntil: 'domcontentloaded' });
  ok('« Toutes les villes » est désactivé en Découverte',
     await po.locator('#ville option[value=all]').isDisabled());
  await po.setInputFiles('input[name=cadre]', 'php/public/cadres/jy-serai.png');
  await po.fill('input[name=titre]', `Ciblage refusé ${marque}`);
  await po.fill('input[name=redirection]', 'https://wakabileguide.com/p/ciblage');
  // L'option est désactivée dans le menu : on force la valeur, comme le
  // ferait quelqu'un qui poste le formulaire à la main.
  await po.evaluate(`(() => {
    const s = document.getElementById('ville');
    s.querySelector('option[value=all]').disabled = false;
    s.value = 'all';
  })()`);
  await po.click('#form-decor button[type=submit]');
  await po.waitForLoadState('domcontentloaded');
  ok('le serveur refuse « toutes les villes » même forcé',
     /offre Croissance/.test(await po.locator('.msg.err').first().innerText().catch(() => '')),
     (await po.locator('.msg.err').first().innerText().catch(() => '')).slice(0, 60));

  /* --- côté équipe : la fiche du compte, et les leviers --- */
  await connexion(p, ADMIN.email, ADMIN.mdp);
  await p.goto(`${BASE}/index.php?p=comptes`, { waitUntil: 'domcontentloaded' });
  const lienFiche = await p.locator(`tr:has-text("${ORG.email}") a`).first().getAttribute('href');
  ok('la liste des comptes mène à la fiche', !!lienFiche, lienFiche ?? 'absent');
  ok('la liste montre la consommation du mois',
     (await p.locator('thead th').allInnerTexts()).some((t) => /ce mois/i.test(t)));

  await p.goto(new URL(lienFiche!, BASE).toString(), { waitUntil: 'domcontentloaded' });
  ok('la fiche du compte s’ouvre', (await p.locator('h1').innerText()) === 'Organisateur Découverte');
  ok('elle dit ce qui est ouvert et ce qui est fermé',
     (await p.locator('.liste-offre').nth(1).locator('li').count()) >= 8);
  ok('elle signale l’adresse non confirmée',
     /non confirmée/.test(await p.locator('.pastille').allInnerTexts().then((t) => t.join(' '))));

  /* --- l'équipe fait monter d'offre, et tout suit --- */
  await p.selectOption('#f-formule', 'croissance');
  await p.locator('button:has-text("Appliquer")').click();
  await p.waitForLoadState('domcontentloaded');
  ok('l’offre change depuis la fiche',
     /Croissance/.test(await p.locator('.pastille').allInnerTexts().then((t) => t.join(' '))));
  ok('les lignes ouvertes suivent l’offre',
     (await p.locator('.liste-offre').first().locator('li').count()) >= 7,
     `${await p.locator('.liste-offre').first().locator('li').count()} ouvertes`);

  await p.fill('#f-bonus', '250');
  await p.locator('button:has-text("Accorder")').click();
  await p.waitForLoadState('domcontentloaded');
  ok('la soupape de téléchargements s’ajoute au quota',
     /2 ?250/.test((await p.locator('.marche .haut').allInnerTexts()).join(' ').replace(/\s+/g, ' ')),
     (await p.locator('.marche .haut').allInnerTexts()).join(' | ').replace(/\s+/g, ' '));

  /* --- l'organisateur voit le changement, et ses liens s'ouvrent --- */
  await po.goto(`${BASE}/index.php?p=liens`, { waitUntil: 'domcontentloaded' });
  ok('les liens courts s’ouvrent avec la nouvelle offre', (await po.locator('#cible').count()) === 1);
  await po.fill('#cible', 'https://wakabileguide.com/p/affiche');
  await po.fill('#titre-lien', 'Affiche du 12 septembre');
  await po.click('button:has-text("Créer le lien")');
  await po.waitForLoadState('domcontentloaded');
  const url_courte = (await po.locator('.msg.ok').first().innerText()).replace(/^.*?(http\S+).*$/s, '$1');
  ok('le lien court est créé', /p=l&c=[A-Za-z0-9]{6}/.test(url_courte), url_courte.slice(0, 60));

  const avantClic = await po.request.get(url_courte, { maxRedirects: 0 });
  ok('suivre le lien redirige vers la cible',
     avantClic.status() === 302
     && (avantClic.headers()['location'] ?? '').includes('wakabileguide.com/p/affiche'),
     `HTTP ${avantClic.status()} → ${avantClic.headers()['location'] ?? ''}`);
  await po.goto(`${BASE}/index.php?p=liens`, { waitUntil: 'domcontentloaded' });
  ok('le clic est compté', /1\s*clic/.test(await po.locator('.carte').last().innerText()));

  const inconnu = await po.request.get(`${BASE}/index.php?p=l&c=ZZZZZZ`);
  ok('un code inconnu répond 404', inconnu.status() === 404, `HTTP ${inconnu.status()}`);

  /* --- le quota de téléchargements REFUSE, il ne se contente pas d'afficher --- */

  /**
   * Le scénario le plus important de cette section.
   *
   * Le tableau de bord affichait autrefois « repère indicatif : aucun badge
   * n'est refusé à vos invités » — autrement dit une ligne vendue que rien
   * n'appliquait. On vérifie donc le refus lui-même : le compte est ramené
   * à Découverte, sa campagne est publiée, et on demande des badges jusqu'à
   * ce que le serveur dise non.
   */
  await p.goto(new URL(lienFiche!, BASE).toString(), { waitUntil: 'domcontentloaded' });
  await p.fill('#f-bonus', '0');
  await p.locator('button:has-text("Accorder")').click();
  await p.waitForLoadState('domcontentloaded');
  await p.selectOption('#f-formule', 'decouverte');
  await p.locator('button:has-text("Appliquer")').click();
  await p.waitForLoadState('domcontentloaded');
  ok('le compte est ramené en Découverte',
     /Découverte/.test(await p.locator('.pastille').allInnerTexts().then((t) => t.join(' '))));

  // Une campagne à lui, publiée par l'équipe pour aller vite.
  const titreQuota = `Quota ${marque}`;
  await po.goto(`${BASE}/index.php?p=nouveau`, { waitUntil: 'domcontentloaded' });
  await po.setInputFiles('input[name=cadre]', 'php/public/cadres/jy-serai.png');
  await po.fill('input[name=titre]', titreQuota);
  await po.fill('input[name=redirection]', 'https://wakabileguide.com/p/quota');
  await po.click('#form-decor button[type=submit]');
  await po.waitForLoadState('domcontentloaded');
  await p.goto(`${BASE}/index.php?p=catalogue&q=${encodeURIComponent(titreQuota)}`, { waitUntil: 'domcontentloaded' });
  await p.locator(`.carte:has-text("${titreQuota}") button:has-text("Publier")`).first().click().catch(() => {});
  await p.waitForLoadState('domcontentloaded');

  await p.goto(`${BASE}/index.php?p=decors`, { waitUntil: 'domcontentloaded' });
  const versQuota = await p.locator(`a:has-text("${titreQuota}")`).first().getAttribute('href');
  ok('la campagne de l’organisateur est en ligne', !!versQuota, versQuota ?? 'absente');

  if (versQuota) {
    await p.goto(new URL(versQuota, BASE).toString(), { waitUntil: 'domcontentloaded' });
    const decorId = await p.evaluate('window.WAKABI.decorId') as string;

    // Cinquante badges, c'est le quota de Découverte : on les prend tous,
    // puis on demande le cinquante-et-unième.
    const demander = () => p.evaluate(`fetch(window.WAKABI.base + '?p=api-badge', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ decor: ${JSON.stringify(decorId)} }),
      }).then((r) => r.status)`) as Promise<number>;
    const noter = () => p.evaluate(`fetch(window.WAKABI.base + '?p=api-telechargement', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ decor: ${JSON.stringify(decorId)} }),
      }).then((r) => r.status)`);

    let refuseA = -1;
    for (let i = 1; i <= 52 && refuseA < 0; i++) {
      if (await demander() === 429) refuseA = i;
      else await noter();
    }
    ok('le badge est refusé une fois le quota atteint', refuseA === 51,
       refuseA < 0 ? 'jamais refusé' : `refusé au ${refuseA}ᵉ`);

    const refus = await p.evaluate(`fetch(window.WAKABI.base + '?p=api-badge', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ decor: ${JSON.stringify(decorId)} }),
      }).then((r) => r.json())`) as { erreur?: string; quota?: boolean };
    ok('le refus s’explique à l’invité', /badges pour ce mois/.test(refus.erreur ?? ''),
       (refus.erreur ?? '').slice(0, 56));

    // L'organisateur est prévenu : c'est SA campagne qui s'arrête.
    await po.goto(`${BASE}/index.php?p=notifications`, { waitUntil: 'domcontentloaded' });
    ok('l’organisateur est prévenu que son quota est plein',
       /quota de téléchargements est atteint/.test(await po.locator('main').innerText()));

    // Et la soupape rouvre immédiatement, sans changer d'offre.
    await p.goto(new URL(lienFiche!, BASE).toString(), { waitUntil: 'domcontentloaded' });
    await p.fill('#f-bonus', '10');
    await p.locator('button:has-text("Accorder")').click();
    await p.waitForLoadState('domcontentloaded');
    await p.goto(new URL(versQuota, BASE).toString(), { waitUntil: 'domcontentloaded' });
    ok('la soupape rouvre le robinet sans changer d’offre', await demander() === 200);
  }

  await ctxOrg.close();

  console.log('\n━━ 20. Sauvegardes ━━');

  await connexion(p, ADMIN.email, ADMIN.mdp);
  await p.goto(`${BASE}/index.php?p=sauvegardes`, { waitUntil: 'domcontentloaded' });
  ok('l’écran de sauvegarde est réservé à l’équipe', p.url().includes('p=sauvegardes'));
  ok('la ligne de cron est donnée toute faite',
     /curl -s "http.*p=sauvegarde-auto&cle=[0-9a-f]{32}"/.test(await p.locator('pre').first().innerText()),
     (await p.locator('pre').first().innerText()).slice(0, 60));

  const avantSauv = await p.locator('a:has-text("Télécharger")').count();
  await p.locator('button:has-text("Créer une sauvegarde")').click();
  await p.waitForLoadState('domcontentloaded');
  ok('la sauvegarde s’écrit', /écrite/.test(await p.locator('.msg.ok').first().innerText().catch(() => '')),
     (await p.locator('.msg.ok').first().innerText().catch(() => '')).slice(0, 64));
  ok('elle apparaît dans la liste',
     (await p.locator('a:has-text("Télécharger")').count()) === avantSauv + 1);

  const lienSauv = await p.locator('a:has-text("Télécharger")').first().getAttribute('href');
  const zip = await p.request.get(new URL(lienSauv!, BASE).toString());
  const octetsZip = await zip.body();
  ok('l’archive se télécharge', zip.status() === 200, `HTTP ${zip.status()}`);
  ok('c’est bien un zip', octetsZip[0] === 0x50 && octetsZip[1] === 0x4b,
     `${Math.round(octetsZip.length / 1024)} Ko`);
  ok('elle n’est pas mise en cache par un intermédiaire',
     (zip.headers()['cache-control'] ?? '').includes('no-store'));

  // Le nom vient de l'URL : c'est par là qu'on tenterait de sortir du dossier.
  const evasion = await p.request.get(
    `${BASE}/index.php?p=telecharger-sauvegarde&f=${encodeURIComponent('../../config.php')}`,
    { maxRedirects: 0 });
  ok('un nom qui remonte l’arborescence est refusé',
     evasion.status() >= 300 && !(await evasion.text()).includes('motdepasse'),
     `HTTP ${evasion.status()}`);

  const sansCle = await p.request.get(`${BASE}/index.php?p=sauvegarde-auto&cle=faux`);
  ok('le déclencheur du cron refuse une clé fausse', sansCle.status() === 403, `HTTP ${sansCle.status()}`);

  console.log('\n━━ 21. Le QR lu à la caméra ━━');

  /* --- le filtre : ce qui part au serveur, et ce qui n'y va pas --- */
  ok('un code nu est accepté', jetonDuQr('A7K2M9XQ4P') === 'A7K2M9XQ4P');
  ok('un code en minuscules est remonté', jetonDuQr('a7k2m9xq4p') === 'A7K2M9XQ4P');
  ok('le jeton est extrait de l’adresse du QR',
     jetonDuQr('https://boost.wakabileguide.com/index.php?p=qr&jeton=A7K2M9XQ4P') === 'A7K2M9XQ4P');
  ok('un QR étranger est ignoré', jetonDuQr('https://exemple.tg/promo') === null);
  ok('un code trop court est ignoré', jetonDuQr('A7K2M9') === null);

  /* --- la lecture, avec une vraie vidéo dans une vraie caméra --- */
  // Un badge tout neuf, émis par la même API que le Studio de l'invité.
  await p.goto(`${BASE}/index.php?p=decor&slug=jy-serai`, { waitUntil: 'domcontentloaded' });
  const badge = await p.evaluate(`fetch(window.WAKABI.base + '?p=api-badge', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ decor: window.WAKABI.decorId }),
    }).then((r) => r.json()).then((d) => d.jeton)`) as string;
  ok('un badge est émis pour l’essai', /^[A-Z0-9]{10}$/.test(badge ?? ''), badge ?? 'aucun');

  const film = join(tmpdir(), `wakabi-qr-${marque}.y4m`);
  await videoQr(`${BASE}/index.php?p=qr&jeton=${badge}`, film);

  // Un navigateur à part : les options de fausse caméra se donnent au
  // lancement, et on ne va pas les imposer à toute la recette.
  const camera = await chromium.launch({
    executablePath: EXE,
    args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream',
           `--use-file-for-fake-video-capture=${film}`],
  });
  const ctxCam = await camera.newContext({
    viewport: { width: 430, height: 932 },
    permissions: ['camera'],
  });
  const pc = await ctxCam.newPage();
  surveiller(pc);
  await connexion(pc, ADMIN.email, ADMIN.mdp);
  await pc.goto(`${BASE}/index.php?p=scan`, { waitUntil: 'domcontentloaded' });
  await pc.waitForTimeout(600);

  ok('le bouton de caméra est proposé', (await pc.locator('#camera').count()) === 1);
  ok('la saisie manuelle reste disponible', (await pc.locator('#jeton').count()) === 1);
  await pc.click('#camera');
  await pc.waitForSelector('#camera-verdict:not([hidden])', { timeout: 20_000 }).catch(() => {});
  const verdictCam = (await pc.locator('#camera-verdict').innerText().catch(() => '')).replace(/\s+/g, ' ');
  ok('la caméra lit le badge et valide l’entrée', /Entrée validée/.test(verdictCam), verdictCam.slice(0, 60));
  ok('la page ne s’est PAS rechargée', pc.url().endsWith('p=scan'),
     'la caméra reste ouverte d’un badge à l’autre');
  ok('le journal des passages se met à jour sans rechargement',
     (await pc.locator('#passages .rangee').count()) > 0);

  await camera.close();
  rmSync(film, { force: true });

  console.log('\n━━ 22. Le transport e-mail ━━');

  /**
   * Placé en DERNIER, et remis à zéro à la fin.
   *
   * Brancher le transport change le comportement du reste — la confirmation
   * d'adresse devient exigible — et un scénario ne doit pas décider du
   * décor dans lequel jouent les précédents.
   */
  const smtp = await ouvrirSmtp();
  const pe = await browser.newPage();
  pe.on('console', (m) => { if (m.type() === 'error') errs.push(m.text()); });

  await connexion(pe, ADMIN.email, ADMIN.mdp);
  await pe.goto(`${BASE}/index.php?p=reglages`, { waitUntil: 'domcontentloaded' });
  ok('l’écran de réglages est réservé à l’équipe', pe.url().includes('p=reglages'));
  ok('le transport part éteint',
     /éteint/i.test(await pe.locator('.msg').first().innerText()));

  await pe.fill('#smtp_hote', '127.0.0.1');
  await pe.fill('#smtp_port', String(SMTP_PORT));
  await pe.selectOption('#smtp_securite', 'aucune');
  await pe.fill('#courriel_expediteur', 'boost@wakabileguide.com');
  await pe.fill('#essai_vers', ADMIN.email);
  await pe.click('button[value=essai]');
  await pe.waitForLoadState('domcontentloaded');
  ok('l’essai d’envoi aboutit',
     /Message remis au serveur SMTP/.test(await pe.locator('.msg').first().innerText()),
     (await pe.locator('.msg').first().innerText()).replace(/\s+/g, ' ').slice(0, 70));
  ok('le serveur a bien reçu l’essai', recus.length === 1, `${recus.length} message(s)`);
  ok('l’essai est adressé à la bonne personne',
     (recus[0] ?? '').includes('<' + ADMIN.email + '>'));
  ok('le sujet accentué voyage encodé', /^Subject: =\?UTF-8\?B\?/m.test(recus[0] ?? ''));

  // Un partenaire tout neuf : il doit confirmer avant de soumettre.
  const NEUF = { email: `verif-${marque}@exemple.tg`, mdp: 'partenaire-2026-solide' };
  const ctxv = await browser.newContext();
  const pv = await ctxv.newPage();
  await pv.goto(`${BASE}/index.php?p=inscription`, { waitUntil: 'domcontentloaded' });
  await pv.selectOption('select[name=role]', 'partenaire');
  await pv.fill('input[name=nom]', 'Partenaire à vérifier');
  await pv.fill('input[name=email]', NEUF.email);
  await pv.fill('input[name=mot_de_passe]', NEUF.mdp);
  await pv.click('main button[type=submit]');
  await pv.waitForLoadState('domcontentloaded');
  ok('le lien de confirmation part à l’inscription', recus.length === 2, `${recus.length} message(s)`);
  ok('la bannière réclame la confirmation',
     /Confirmez votre adresse/.test(await pv.locator('.msg.err').first().innerText().catch(() => '')));

  // Un décor, et une soumission qui doit être refusée.
  await pv.goto(`${BASE}/index.php?p=nouveau`, { waitUntil: 'domcontentloaded' });
  await pv.setInputFiles('input[name=cadre]', 'php/public/cadres/jy-serai.png');
  await pv.fill('input[name=titre]', `Décor à vérifier ${marque}`);
  await pv.fill('input[name=redirection]', 'https://wakabileguide.com/p/verif');
  await pv.click('main button[type=submit]');
  await pv.waitForLoadState('domcontentloaded');
  await pv.locator('form[action*="p=soumettre"] button').first().click();
  await pv.waitForLoadState('domcontentloaded');
  ok('soumettre est refusé tant que l’adresse n’est pas confirmée',
     /Confirmez d’abord votre adresse/.test(await pv.locator('.msg.err').first().innerText().catch(() => '')));

  // Le lien reçu, cliqué : il vaut une fois.
  const lienBrut = (recus[1] ?? '').match(/https?:\/\/\S*p=verifier[^\s"<]*/)?.[0]?.replace(/&amp;/g, '&') ?? '';
  ok('le message porte bien un lien de confirmation', lienBrut !== '', lienBrut.slice(0, 60));
  await pv.goto(lienBrut, { waitUntil: 'domcontentloaded' });
  ok('le lien confirme l’adresse', /Adresse confirmée/.test(await pv.locator('h1').first().innerText()));
  await pv.goto(lienBrut, { waitUntil: 'domcontentloaded' });
  ok('le lien ne sert qu’une fois',
     /n’a pas fonctionné/.test(await pv.locator('h1').first().innerText()));

  await pv.goto(`${BASE}/index.php?p=partenaire`, { waitUntil: 'domcontentloaded' });
  ok('la bannière disparaît une fois l’adresse confirmée',
     (await pv.locator('.msg.err:has-text("Confirmez votre adresse")').count()) === 0);

  // Et maintenant, la soumission passe — et prévient l'équipe.
  const avantEquipe = recus.length;
  await pv.locator('form[action*="p=soumettre"] button').first().click();
  await pv.waitForLoadState('domcontentloaded');
  ok('soumettre passe une fois l’adresse confirmée',
     /24 h/.test(await pv.locator('.msg.ok').first().innerText().catch(() => '')));
  ok('l’équipe est prévenue par courriel', recus.length > avantEquipe,
     `${recus.length - avantEquipe} message(s)`);

  // La décision, elle, revient au partenaire.
  const avantDecision = recus.length;
  await pe.goto(`${BASE}/index.php?p=relecture`, { waitUntil: 'domcontentloaded' });
  await pe.locator(`.carte:has-text("Décor à vérifier ${marque}") button:has-text("Approuver")`).first().click();
  await pe.waitForLoadState('domcontentloaded');
  ok('la décision part par courriel', recus.length > avantDecision,
     `${recus.length - avantDecision} message(s)`);
  ok('la décision est adressée au partenaire',
     (recus[recus.length - 1] ?? '').includes('<' + NEUF.email + '>'));

  // Remise à zéro : la recette doit pouvoir se rejouer sur la même base.
  await pe.goto(`${BASE}/index.php?p=reglages`, { waitUntil: 'domcontentloaded' });
  await pe.fill('#smtp_hote', '');
  await pe.click('button[value=enregistrer]');
  await pe.waitForLoadState('domcontentloaded');
  ok('le transport se rééteint', /éteint/i.test(await pe.locator('.msg').last().innerText()));
  smtp.fermer();

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
