/**
 * Recette de la version PHP — tout le produit, dans un vrai navigateur.
 *
 * Mêmes scénarios que scripts/e2e-full.ts, contre l'implémentation PHP.
 * Les deux versions doivent se comporter à l'identique : c'est la seule
 * façon de savoir que le portage n'a rien perdu en route.
 */
import { chromium, type Browser, type Page } from 'playwright-core';
import { createServer } from 'node:net';
import { createServer as createServeurHttp } from 'node:http';
import { createECDH, createHmac, randomBytes } from 'node:crypto';
import { deflateSync } from 'node:zlib';
import { readFileSync, writeFileSync, rmSync } from 'node:fs';
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
/**
 * Un faux service de push, pour éprouver ce qui arrive quand ça rate.
 *
 * Le cas intéressant n'est pas l'envoi qui marche — c'est celui qui ne
 * marche pas, parce que c'est le seul dont l'utilisateur ne verra jamais
 * rien si l'application ne le dit pas. Ce serveur répond 410 comme le fait
 * un vrai service pour un abonnement périmé.
 */
function ouvrirPush(port: number): { clePublique: string; auth: string; fermer: () => void } {
  const ecdh = createECDH('prime256v1');
  ecdh.generateKeys();
  const serveur = createServeurHttp((_req, res) => { res.writeHead(410); res.end('expired'); });
  serveur.listen(port, '127.0.0.1');
  return {
    clePublique: ecdh.getPublicKey().toString('base64url'),
    auth: randomBytes(16).toString('base64url'),
    fermer: () => serveur.close(),
  };
}

/**
 * Le code à six chiffres, calculé comme le ferait l'application du
 * téléphone — et non par le code qu'on éprouve.
 *
 * C'est tout l'intérêt : si les deux implémentations divergent, le test
 * échoue. Une vérification qui appellerait `otp_code()` de PHP ne
 * prouverait que sa cohérence avec elle-même.
 */
function totp(secret32: string): string {
  const A = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  const bits = [...secret32].map((c) => A.indexOf(c).toString(2).padStart(5, '0')).join('');
  const cle = Buffer.from((bits.match(/.{8}/g) ?? []).map((o) => parseInt(o, 2)));
  const tranche = Buffer.alloc(8);
  tranche.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 1000 / 30)));
  const h = createHmac('sha1', cle).update(tranche).digest();
  const d = h[19] & 0x0f;
  const n = ((h[d] & 0x7f) << 24) | (h[d + 1] << 16) | (h[d + 2] << 8) | h[d + 3];
  return String(n % 1000000).padStart(6, '0');
}

/**
 * Un PNG uni, écrit à la main.
 *
 * Plutôt qu'un fichier d'appoint dans le dépôt : une recette qui dépend
 * d'un binaire versionné finit par échouer le jour où quelqu'un le
 * déplace, et l'erreur ne parle alors plus du tout du sujet testé.
 */
function imagePng(w: number, h: number): Buffer {
  const brut = Buffer.concat(
    Array.from({ length: h }, () =>
      Buffer.concat([Buffer.from([0]), Buffer.alloc(w * 3).fill(Buffer.from([80, 120, 200]))])),
  );
  const bloc = (type: string, data: Buffer): Buffer => {
    const corps = Buffer.concat([Buffer.from(type, 'ascii'), data]);
    const len = Buffer.alloc(4); len.writeUInt32BE(data.length);
    const crc = Buffer.alloc(4); crc.writeUInt32BE(crc32(corps));
    return Buffer.concat([len, corps, crc]);
  };
  const ihdr = Buffer.alloc(13);
  ihdr.writeUInt32BE(w, 0); ihdr.writeUInt32BE(h, 4);
  ihdr[8] = 8; ihdr[9] = 2;
  return Buffer.concat([
    Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]),
    bloc('IHDR', ihdr), bloc('IDAT', deflateSync(brut)), bloc('IEND', Buffer.alloc(0)),
  ]);
}

function crc32(buf: Buffer): number {
  let c = 0xffffffff;
  for (const octet of buf) {
    c ^= octet;
    for (let k = 0; k < 8; k++) c = (c >>> 1) ^ (0xedb88320 & -(c & 1));
  }
  return (c ^ 0xffffffff) >>> 0;
}

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
 * Les objets des messages reçus, décodés.
 *
 * Un objet français part encodé — `Subject: =?UTF-8?B?Vm90cmUgcsO0bGU…?=` —
 * et chercher « Confirmez votre adresse » dans le flux brut ne trouve donc
 * jamais rien. Une assertion qui échoue est gênante ; une assertion qui
 * PASSE parce qu'elle ne peut rien trouver l'est bien davantage : c'est ce
 * que faisait un `!/Votre offre/` posé sur du base64.
 */
function objets(brut: string): string {
  return (brut.match(/^Subject:.*$/gim) ?? [])
    .map((l) => l.replace(/=\?UTF-8\?B\?([^?]*)\?=/gi,
      (_, b64: string) => Buffer.from(b64, 'base64').toString('utf8')))
    .join('\n');
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
  // Déjà connecté ? L'écran de connexion renvoie désormais chez soi — on
  // se déconnecte donc d'abord, comme le ferait quelqu'un qui change de
  // compte pour de bon.
  if (!p.url().includes('p=connexion')) {
    await p.locator('form[action*="deconnexion"] button').first().click().catch(() => {});
    await p.waitForLoadState('domcontentloaded');
    await p.goto(`${BASE}/index.php?p=connexion`, { waitUntil: 'domcontentloaded' });
  }
  await p.fill('input[name=email]', email);
  await p.fill('input[name=mot_de_passe]', mdp);
  // `main` et non la page entière : la barre porte un bouton « Déconnexion »
  // de type submit, situé AVANT le formulaire dans le document.
  await p.click('main button[type=submit]');
  await p.waitForLoadState('domcontentloaded');
}

async function inscription(p: Page, email: string, mdp: string, role: string, nom: string) {
  await p.goto(`${BASE}/index.php?p=inscription`, { waitUntil: 'domcontentloaded' });
  // On ne propose plus de créer un compte à qui en a déjà un d'ouvert :
  // le formulaire renvoie chez soi, comme dans la vraie vie.
  if (!p.url().includes('p=inscription')) {
    await p.locator('form[action*="deconnexion"] button').first().click().catch(() => {});
    await p.waitForLoadState('domcontentloaded');
    await p.goto(`${BASE}/index.php?p=inscription`, { waitUntil: 'domcontentloaded' });
  }
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
  // Dans la CARTE du décor : l'en-tête du catalogue porte lui aussi une
  // ligne d'aide depuis qu'il annonce combien de décors le filtre décrit.
  ok('l’adresse du décor ne change pas',
     (await p.locator('.carte .aide').first().innerText()).includes(slugAvant),
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
  await p.locator('details.creer').first().locator('summary').click();
  await p.fill('#c-nom', 'Cliente Impact');
  await p.fill('#c-email', CLIENT.email);
  await p.selectOption('#c-role', 'partenaire');
  await p.selectOption('#c-formule', 'impact');
  await p.fill('#c-mdp', CLIENT.mdp);
  await p.click('form[action*="creer-compte"] button[type=submit]');
  await p.waitForLoadState('domcontentloaded');
  const cree = await p.locator('.msg.ok').first().innerText().catch(() => '');
  ok('l’équipe crée un compte avec son offre', /Compte créé/.test(cree) && /Impact/.test(cree), cree.slice(0, 60));

  // 2. Une adresse déjà prise ne doit pas effacer la saisie.
  await p.locator('details.creer').first().locator('summary').click();
  await p.fill('#c-nom', 'Cliente Impact');
  await p.fill('#c-email', CLIENT.email);
  await p.fill('#c-mdp', CLIENT.mdp);
  await p.click('form[action*="creer-compte"] button[type=submit]');
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
  // Le rangement lui-même est éprouvé à la section 29 ; ici on vérifie
  // seulement que rien n'a débordé des groupes ni disparu.
  ok('toutes les destinations d’administration sont rangées dans un groupe',
     (await p.locator('.barre .deroulant .volet a').count()) === 12,
     `${await p.locator(".barre .deroulant .volet a").count()} entrées en groupe`);
  ok('notifications et déconnexion restent hors des déroulants',
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
  // Depuis un contexte ANONYME : c'est là qu'on essaie des mots de passe
  // en boucle, et l'écran de connexion renvoie chez lui qui en a déjà un.
  const ctxBrute = await browser.newContext();
  const brute = await ctxBrute.newPage();
  surveiller(brute);
  for (let i = 0; i < 9; i++) {
    await brute.goto(`${BASE}/index.php?p=connexion`, { waitUntil: 'domcontentloaded' });
    await brute.fill('input[name=email]', `brute-${marque}@exemple.tg`);
    await brute.fill('input[name=password], input[name=mot_de_passe]', 'faux' + i);
    await brute.click('main button[type=submit]');
    await brute.waitForLoadState('domcontentloaded');
  }
  const limite = await brute.locator('[role=alert]').first().innerText().catch(() => '');
  await ctxBrute.close();
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
  /* Le détail de l'offre se replie depuis la refonte du tableau de bord :
     `innerText` d'un élément masqué rend une chaîne vide. On l'ouvre, comme
     le ferait quelqu'un qui veut comparer. */
  await po.locator('.detail-offre > summary').click();
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

  await p.locator('button:has-text("Créer une sauvegarde")').click();
  await p.waitForLoadState('domcontentloaded');
  const avis = await p.locator('.msg.ok').first().innerText().catch(() => '');
  ok('la sauvegarde s’écrit', /écrite/.test(avis), avis.slice(0, 64));
  /**
   * On cherche LE FICHIER, pas un compteur.
   *
   * Sept archives sont gardées : au-delà, en créer une en efface une autre,
   * et le total ne bouge plus. Un test qui compte finirait donc par échouer
   * le jour où l'installation a servi — c'est-à-dire toujours.
   */
  const nomSauv = (/wakabi-[0-9-]+\.zip/.exec(avis) ?? [''])[0];
  ok('elle apparaît dans la liste',
     nomSauv !== '' && (await p.locator(`a[href*="${nomSauv}"]:has-text("Télécharger")`).count()) === 1,
     nomSauv);

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

  /* ================================================================== */
  console.log('\n━━ 23. L’espace profil ━━');

  /**
   * Un compte n'appartient pas à l'application.
   *
   * Sans ces écrans, chaque changement de nom ou de numéro passe par
   * l'équipe — un humain sur le chemin d'un changement d'adresse.
   */
  const PROFIL = { email: `profil-${marque}@exemple.tg`, mdp: 'profil-2026-solide' };
  const ctxp = await browser.newContext();
  const pp = await ctxp.newPage();
  surveiller(pp);
  await inscription(pp, PROFIL.email, PROFIL.mdp, 'participant', 'Ancien Nom');

  await pp.goto(`${BASE}/index.php?p=profil`, { waitUntil: 'domcontentloaded' });
  ok('le profil est accessible à un participant', pp.url().includes('p=profil'));
  ok('le profil affiche ce qu’on est', (await pp.inputValue('#p-nom')) === 'Ancien Nom');

  await pp.fill('#p-nom', 'Nouveau Nom');
  await pp.fill('#p-telephone', '+228 90 11 22 33');
  await pp.selectOption('#p-ville', 'lome');
  await pp.click('form[action*="p=profil-identite"] button[type=submit]');
  await pp.waitForLoadState('domcontentloaded');
  ok('le nom se corrige sans passer par l’équipe',
     /Profil enregistré/.test(await pp.locator('.msg.ok').first().innerText().catch(() => '')));
  ok('la correction est bien enregistrée', (await pp.inputValue('#p-nom')) === 'Nouveau Nom');
  ok('le téléphone aussi', (await pp.inputValue('#p-telephone')) === '+228 90 11 22 33');

  await pp.fill('#p-telephone', 'pas-un-numero');
  await pp.click('form[action*="p=profil-identite"] button[type=submit]');
  await pp.waitForLoadState('domcontentloaded');
  ok('un numéro qui n’en est pas un est refusé',
     /numéro de téléphone/.test(await pp.locator('.msg.err').first().innerText().catch(() => '')));

  // Le mot de passe : l'ancien est exigé.
  await pp.goto(`${BASE}/index.php?p=profil`, { waitUntil: 'domcontentloaded' });
  await pp.fill('#p-actuel', 'ce-n-est-pas-le-bon');
  await pp.fill('#p-nouveau', 'nouveau-mot-de-passe-2026');
  await pp.click('form[action*="p=profil-motdepasse"] button[type=submit]');
  await pp.waitForLoadState('domcontentloaded');
  ok('changer de mot de passe SANS l’ancien est refusé',
     /mot de passe actuel est faux/.test(await pp.locator('.msg.err').first().innerText().catch(() => '')));

  await pp.fill('#p-actuel', PROFIL.mdp);
  await pp.fill('#p-nouveau', 'nouveau-mot-de-passe-2026');
  await pp.click('form[action*="p=profil-motdepasse"] button[type=submit]');
  await pp.waitForLoadState('domcontentloaded');
  ok('avec l’ancien, il change',
     /Mot de passe changé/.test(await pp.locator('.msg.ok').first().innerText().catch(() => '')));
  await connexion(pp, PROFIL.email, 'nouveau-mot-de-passe-2026');
  ok('le nouveau mot de passe ouvre la session', !pp.url().includes('p=connexion'));

  // La suppression : il faut recopier son adresse, exactement.
  await pp.goto(`${BASE}/index.php?p=profil`, { waitUntil: 'domcontentloaded' });
  await pp.click('details:has(summary:has-text("Supprimer mon compte")) > summary');
  await pp.fill('#p-confirmation', 'nimporte-quoi@exemple.tg');
  await pp.click('form[action*="p=profil-supprimer"] button[type=submit]');
  await pp.waitForLoadState('domcontentloaded');
  ok('supprimer sans recopier son adresse est refusé',
     /recopiez votre adresse/i.test(await pp.locator('.msg.err').first().innerText().catch(() => '')));

  await pp.click('details:has(summary:has-text("Supprimer mon compte")) > summary');
  await pp.fill('#p-confirmation', PROFIL.email);
  await pp.click('form[action*="p=profil-supprimer"] button[type=submit]');
  await pp.waitForLoadState('domcontentloaded');
  ok('avec son adresse exacte, le compte part',
     pp.url().includes('p=connexion')
     && /supprimé/.test(await pp.locator('.msg.ok').first().innerText().catch(() => '')),
     pp.url().split('index.php')[1]?.slice(0, 40) ?? pp.url());
  await connexion(pp, PROFIL.email, 'nouveau-mot-de-passe-2026');
  ok('le compte supprimé ne se reconnecte plus', pp.url().includes('p=connexion'));

  // L'équipe, elle, ne se supprime pas elle-même.
  const pq = await browser.newPage();
  surveiller(pq);
  await connexion(pq, ADMIN.email, ADMIN.mdp);
  await pq.goto(`${BASE}/index.php?p=profil`, { waitUntil: 'domcontentloaded' });
  ok('un compte de l’équipe n’a pas de bouton de suppression',
     (await pq.locator('form[action*="p=profil-supprimer"]').count()) === 0);
  await ctxp.close();

  /* ================================================================== */
  console.log('\n━━ 24. Les notifications du navigateur ━━');

  /**
   * On ne peut pas faire recevoir une VRAIE notification push dans une
   * recette : il faudrait un service de push joignable et un navigateur
   * enregistré chez lui. Ce qui se vérifie ici est tout ce qui est de
   * notre côté — la clé publique servie, l'abonnement enregistré, le
   * segment respecté, et surtout : qu'un organisateur sans l'offre ne
   * puisse pas écrire à la base du guide.
   */
  await pq.goto(`${BASE}/index.php?p=profil`, { waitUntil: 'domcontentloaded' });
  const ctxPush = await pq.locator('#push-contexte').innerText().catch(() => '{}');
  const cles = JSON.parse(ctxPush || '{}') as { cle?: string; csrf?: string; base?: string };
  ok('la clé publique VAPID est servie à la page',
     typeof cles.cle === 'string' && cles.cle.length === 87, `${(cles.cle ?? '').length} caractères`);
  ok('le bouton d’abonnement est là', (await pq.locator('#push-bouton').count()) === 1);
  ok('le service worker est servi à la racine',
     (await pq.request.get(`${BASE}/sw.js`)).status() === 200);

  // Un abonnement, posé directement : c'est ce que ferait le navigateur.
  const FAUX_ENDPOINT = `https://fcm.googleapis.com/fcm/send/recette-${marque}`;
  const abo = await pq.request.post(`${BASE}/index.php?p=api-push-abonner`, {
    form: {
      csrf: cles.csrf ?? '',
      endpoint: FAUX_ENDPOINT,
      p256dh: 'BLc4xRzKlKORKWlbdgFaBrrPK3ydWAHo4M0gs0i1oEKgPpWC5cW8OCzVrOQRv-1npXRWk8udNW3ZulJC2rLKzlI',
      auth: 'aUdiN0dyMkFtbFJIcTBQTw',
    },
  });
  ok('un abonnement est accepté', abo.ok(), `HTTP ${abo.status()}`);

  const aboMauvais = await pq.request.post(`${BASE}/index.php?p=api-push-abonner`, {
    form: { csrf: cles.csrf ?? '', endpoint: 'http://ailleurs.example/x', p256dh: 'a', auth: 'b' },
  });
  ok('une adresse qui n’est pas en https est refusée', aboMauvais.status() === 400,
     `HTTP ${aboMauvais.status()}`);

  await pq.goto(`${BASE}/index.php?p=diffusion`, { waitUntil: 'domcontentloaded' });
  ok('l’équipe atteint l’écran de diffusion', pq.url().includes('p=diffusion'));
  ok('le nombre d’abonnés est affiché', /abonné\(s\)/.test(await pq.locator('#d-segment').innerText()));

  // Un organisateur sur Découverte : la fonction ne lui est pas vendue.
  // CLIENT et non PART : PART a été passé en Croissance à la section 12,
  // et Croissance comprend justement cette fonction. CLIENT est sur Impact.
  const pd = await browser.newPage();
  surveiller(pd);
  await connexion(pd, CLIENT.email, CLIENT.mdp);
  await pd.goto(`${BASE}/index.php?p=diffusion`, { waitUntil: 'domcontentloaded' });
  ok('un organisateur sans l’offre voit un écran d’explication, pas un refus sec',
     /ne comprend pas cette fonction/.test(await pd.locator('.carte').first().innerText().catch(() => '')));
  ok('l’écran dit avec quelle offre elle arrive',
     /offre Croissance/i.test(await pd.locator('.carte').first().innerText().catch(() => '')));
  ok('le menu ne propose pas une page qu’il ne peut pas ouvrir',
     (await pd.locator('nav a[href*="p=diffusion"]').count()) === 0);
  await pd.close();

  // Désabonner : la ligne s'en va vraiment.
  const desabo = await pq.request.post(`${BASE}/index.php?p=api-push-desabonner`, {
    form: { csrf: cles.csrf ?? '', endpoint: FAUX_ENDPOINT },
  });
  ok('le désabonnement est accepté', desabo.ok(), `HTTP ${desabo.status()}`);

  /* ================================================================== */
  console.log('\n━━ 25. Le blog ━━');

  const TITRE_ART = `Remplir une salle sans affiche ${marque}`;
  await pq.goto(`${BASE}/index.php?p=blog-editer`, { waitUntil: 'domcontentloaded' });
  await pq.fill('#a-titre', TITRE_ART);
  await pq.fill('#a-chapo', 'Une soirée de 400 personnes, et pas une affiche imprimée.');
  // Le champ brut est masqué par l'éditeur : on passe par la bascule,
  // qui est le chemin réel de qui préfère taper ses marques.
  await pq.locator('#a-bascule').click();
  await pq.fill('#a-corps',
    '## Ce qui a marché\n\n'
    + 'À Lomé, **400 personnes** sont venues sans une seule affiche.\n\n'
    + '- 1 200 badges créés en 9 jours\n'
    + '- 62 % présentés à l’entrée\n\n'
    + '> On a su qui venait avant d’ouvrir les portes.\n\n'
    + 'Tout est là : [le guide](https://wakabileguide.com/).\n\n'
    + 'Et ceci ne doit PAS s’exécuter : <script>window.__injecte = 1;</script>');
  await pq.click('button[value=enregistrer]');
  await pq.waitForLoadState('domcontentloaded');
  ok('un article s’enregistre sans être publié',
     /enregistré/i.test(await pq.locator('.msg.ok').first().innerText().catch(() => ''))
     && /Brouillon/.test(await pq.locator(`tr:has-text("${TITRE_ART}")`).innerText().catch(() => '')));

  // Un brouillon n'est PAS public : c'est le point de la relecture.
  const slugArt = (await pq.locator(`tr:has-text("${TITRE_ART}") code`).first().innerText()).trim();
  const ctxAnon = await browser.newContext();
  const pa = await ctxAnon.newPage();
  surveiller(pa);
  const brouillonPublic = await pa.request.get(`${BASE}/index.php?p=blog&a=${slugArt}`);
  ok('un brouillon renvoie 404 à un visiteur', brouillonPublic.status() === 404,
     `HTTP ${brouillonPublic.status()}`);

  await pq.goto(`${BASE}/index.php?p=blog-admin`, { waitUntil: 'domcontentloaded' });
  await pq.click(`tr:has-text("${TITRE_ART}") a[href*="p=blog-editer"]`);
  await pq.waitForLoadState('domcontentloaded');
  await pq.click('button[value=publier]');
  await pq.waitForLoadState('domcontentloaded');
  ok('l’article se publie',
     /en ligne/.test(await pq.locator('.msg.ok').first().innerText().catch(() => '')));

  await pa.goto(`${BASE}/index.php?p=blog`, { waitUntil: 'domcontentloaded' });
  ok('le blog est lisible SANS compte', pa.url().includes('p=blog'));
  ok('l’article publié apparaît dans la liste',
     (await pa.locator(`a:has-text("${TITRE_ART}")`).count()) >= 1);

  await pa.goto(`${BASE}/index.php?p=blog&a=${slugArt}`, { waitUntil: 'domcontentloaded' });
  ok('l’article s’ouvre pour tout le monde',
     (await pa.locator('h1').first().innerText()) === TITRE_ART);
  ok('les intertitres sont mis en forme',
     (await pa.locator('.corps-article h3').count()) === 1);
  ok('le gras est mis en forme',
     (await pa.locator('.corps-article strong').first().innerText()) === '400 personnes');
  ok('la liste est une vraie liste', (await pa.locator('.corps-article li').count()) === 2);
  ok('la citation est une citation', (await pa.locator('.corps-article blockquote').count()) === 1);
  /**
   * On éprouve les JETONS, pas la chaîne exacte : l'ordre et le nombre de
   * valeurs de `rel` peuvent changer sans que la garantie change, et une
   * assertion sur le libellé exact tomberait pour la mauvaise raison.
   */
  const sortant = pa.locator('.corps-article a[href^="http"]:not([href*="127.0.0.1"])').first();
  const relSortant = (await sortant.getAttribute('rel')) ?? '';
  ok('un lien sortant s’ouvre à part et ne nous engage pas',
     (await sortant.getAttribute('target')) === '_blank'
     && ['noopener', 'noreferrer', 'nofollow'].every((j) => relSortant.includes(j)),
     `target="${await sortant.getAttribute('target')}" rel="${relSortant}"`);

  /**
   * Le scénario qui compte : le corps est du TEXTE.
   *
   * Une balise saisie dans le champ ne doit jamais devenir une balise de la
   * page — sans quoi n'importe quel article poserait un `<script>` sur une
   * page lue par tout le monde.
   */
  ok('une balise saisie reste du texte et ne s’exécute pas',
     (await pa.evaluate(() => (window as unknown as { __injecte?: number }).__injecte)) === undefined
       && (await pa.locator('.corps-article script').count()) === 0
       && /<script>/.test(await pa.locator('.corps-article').innerText()));

  await pa.goto(`${BASE}/index.php?p=accueil`, { waitUntil: 'domcontentloaded' });
  ok('la vitrine montre le blog',
     (await pa.locator(`a:has-text("${TITRE_ART}")`).count()) >= 1);
  ok('la vitrine mène au blog complet',
     (await pa.locator('a[href*="p=blog"]').count()) >= 2);

  // L'adresse est figée une fois publié : les liens partagés doivent tenir.
  await pq.goto(`${BASE}/index.php?p=blog-admin`, { waitUntil: 'domcontentloaded' });
  await pq.click(`tr:has-text("${TITRE_ART}") a[href*="p=blog-editer"]`);
  await pq.waitForLoadState('domcontentloaded');
  ok('l’adresse d’un article publié n’est plus modifiable',
     await pq.locator('#a-slug').getAttribute('readonly') !== null);

  /**
   * Un organisateur ÉCRIT, mais ne PUBLIE pas.
   *
   * La distinction est tout le circuit : ce qui doit rester fermé n'est pas
   * l'accès à l'écriture — c'est la mise en ligne, et la file de relecture
   * qui la précède.
   */
  const ctxRedac = await browser.newContext();
  const pRedac = await ctxRedac.newPage();
  surveiller(pRedac);
  await connexion(pRedac, PART.email, PART.mdp);
  await pRedac.goto(`${BASE}/index.php?p=blog-editer`, { waitUntil: 'domcontentloaded' });
  ok('un organisateur peut écrire, mais pas publier',
     (await pRedac.locator('button[value=soumettre]').count()) === 1
     && (await pRedac.locator('button[value=publier]').count()) === 0);
  const fileFermee = await pRedac.request.get(`${BASE}/index.php?p=blog-relecture`);
  ok('la file de relecture du blog reste fermée aux organisateurs',
     fileFermee.url().includes('p=connexion') || fileFermee.status() >= 400
     || !(await fileFermee.text()).includes('Relecture du blog'),
     `HTTP ${fileFermee.status()}`);
  await ctxRedac.close();

  /* ================================================================== */
  console.log('\n━━ 26. Les images allégées ━━');

  /**
   * La vraie mesure, pas la présence d'un attribut : ce qui compte est le
   * nombre d'octets qu'un visiteur télécharge pour voir le catalogue.
   */
  await pa.goto(`${BASE}/index.php?p=decors`, { waitUntil: 'domcontentloaded' });
  const imgs = await pa.locator('.vignette img').evaluateAll(
    (n) => n.map((e) => ({
      src: (e as HTMLImageElement).getAttribute('src') ?? '',
      srcset: (e as HTMLImageElement).getAttribute('srcset') ?? '',
      w: (e as HTMLImageElement).getAttribute('width'),
      h: (e as HTMLImageElement).getAttribute('height'),
      lazy: (e as HTMLImageElement).getAttribute('loading'),
    })));
  ok('le catalogue a des vignettes', imgs.length > 0, `${imgs.length} images`);
  ok('elles passent toutes par le redimensionneur',
     imgs.every((i) => i.src.includes('p=vignette')));
  ok('chacune propose plusieurs tailles au navigateur',
     imgs.every((i) => i.srcset.split(',').length >= 2));
  ok('chacune annonce ses dimensions — la page ne saute pas au chargement',
     imgs.every((i) => i.w && i.h));
  ok('elles ne sont chargées qu’en arrivant à l’écran',
     imgs.every((i) => i.lazy === 'lazy'));

  const rVig = await pa.request.get(imgs[0].src);
  ok('la vignette est servie en WebP',
     (rVig.headers()['content-type'] ?? '') === 'image/webp',
     rVig.headers()['content-type']);
  ok('elle est mise en cache pour de bon',
     /immutable/.test(rVig.headers()['cache-control'] ?? ''));

  const poidsVignette = (await rVig.body()).length;
  ok('une vignette pèse moins de 40 Ko', poidsVignette < 40 * 1024,
     `${Math.round(poidsVignette / 1024)} Ko`);

  // Le catalogue entier, en octets réellement transférés.
  let totalVignettes = 0;
  for (const i of imgs.slice(0, 12)) {
    totalVignettes += (await (await pa.request.get(i.src)).body()).length;
  }
  ok('douze décors tiennent sous 200 Ko d’images',
     totalVignettes < 200 * 1024, `${Math.round(totalVignettes / 1024)} Ko pour ${Math.min(12, imgs.length)}`);

  // Une clé bricolée ne doit pas ouvrir un fichier du serveur.
  for (const mauvaise of ['p:../../config.php', 'c:../config.php', 'x:jy-serai.png', '../../config.php']) {
    const r = await pa.request.get(`${BASE}/index.php?p=vignette&f=${encodeURIComponent(mauvaise)}&l=320`);
    ok(`« ${mauvaise} » est refusé`, r.status() === 404, `HTTP ${r.status()}`);
  }

  await ctxAnon.close();
  await pq.close();

  /* ================================================================== */
  console.log('\n━━ 27. Le blog proposé par un organisateur ━━');

  /**
   * Le circuit qui compte : un organisateur PROPOSE, l'équipe DÉCIDE.
   *
   * Sans lui, écrire sur le blog du guide demandait d'être de l'équipe —
   * et l'article sponsorisé vendu avec l'offre Mouvement restait un
   * service qu'il fallait réclamer par courriel.
   */
  const pw = await browser.newPage();
  surveiller(pw);
  await connexion(pw, PART.email, PART.mdp);

  const ART = `Ma soirée du samedi ${marque}`;
  await pw.goto(`${BASE}/index.php?p=blog-admin`, { waitUntil: 'domcontentloaded' });
  ok('un organisateur atteint ses articles', pw.url().includes('p=blog-admin'));
  ok('l’écran explique le circuit',
     /rédaction relit/i.test(await pw.locator('.carte').last().innerText().catch(() => '')));

  await pw.goto(`${BASE}/index.php?p=blog-editer`, { waitUntil: 'domcontentloaded' });
  ok('le bouton dit « proposer », pas « publier »',
     (await pw.locator('button[value=soumettre]').count()) === 1
     && (await pw.locator('button[value=publier]').count()) === 0);

  await pw.fill('#a-titre', ART);
  await pw.fill('#a-chapo', 'Quatre semaines pour faire d’un mardi mort un rendez-vous.');
  await pw.locator('#a-bascule').click();
  await pw.fill('#a-corps',
    '## Ce qu’on a essayé\n\nUn nom à part, un visuel à part, et le décor publié le vendredi.\n\n'
    + '- Semaine 1 : 40 badges\n- Semaine 4 : 180 badges\n\nLe reste a suivi tout seul.');
  await pw.click('button[value=soumettre]');
  await pw.waitForLoadState('domcontentloaded');
  ok('l’article part chez la rédaction',
     /rédaction/i.test(await pw.locator('.msg.ok').first().innerText().catch(() => '')));
  ok('il apparaît en relecture dans sa liste',
     /En relecture/.test(await pw.locator(`tr:has-text("${ART}")`).innerText().catch(() => '')));

  // Il n'est pas public tant qu'il n'est pas publié.
  const ctxLect = await browser.newContext();
  const pl = await ctxLect.newPage();
  surveiller(pl);
  await pl.goto(`${BASE}/index.php?p=blog`, { waitUntil: 'domcontentloaded' });
  ok('un article proposé n’est PAS encore sur le blog public',
     (await pl.locator(`a:has-text("${ART}")`).count()) === 0);

  // Et son auteur ne peut plus le retoucher sous le nez du relecteur.
  await pw.goto(`${BASE}/index.php?p=blog-admin`, { waitUntil: 'domcontentloaded' });
  const lienArt = await pw.locator(`tr:has-text("${ART}") a[href*="p=blog-editer"]`).first().getAttribute('href');
  await pw.goto(lienArt!, { waitUntil: 'domcontentloaded' });
  ok('un article en relecture ne se modifie plus',
     pw.url().includes('p=blog-admin')
     && /chez la rédaction/i.test(await pw.locator('.msg.err').first().innerText().catch(() => '')));

  // L'équipe le renvoie, avec un motif.
  const pe2 = await browser.newPage();
  surveiller(pe2);
  await connexion(pe2, ADMIN.email, ADMIN.mdp);
  await pe2.goto(`${BASE}/index.php?p=blog-relecture`, { waitUntil: 'domcontentloaded' });
  ok('la file de relecture montre l’article proposé',
     (await pe2.locator(`.carte:has-text("${ART}")`).count()) === 1);
  ok('elle nomme l’organisateur qui l’a proposé',
     /Proposé par/.test(await pe2.locator(`.carte:has-text("${ART}")`).innerText()));

  await pe2.locator(`.carte:has-text("${ART}") button[value=corrections]`).click();
  await pe2.waitForLoadState('domcontentloaded');
  ok('renvoyer SANS motif est refusé',
     /motif est obligatoire/i.test(await pe2.locator('.msg.err').first().innerText().catch(() => '')));

  await pe2.goto(`${BASE}/index.php?p=blog-relecture`, { waitUntil: 'domcontentloaded' });
  await pe2.locator(`.carte:has-text("${ART}") textarea[name=motif]`).fill('Ajoutez les chiffres réels.');
  await pe2.locator(`.carte:has-text("${ART}") button[value=corrections]`).click();
  await pe2.waitForLoadState('domcontentloaded');
  ok('avec un motif, il repart chez l’auteur',
     /Décision enregistrée/.test(await pe2.locator('.msg.ok').first().innerText().catch(() => '')));

  await pw.goto(`${BASE}/index.php?p=blog-admin`, { waitUntil: 'domcontentloaded' });
  ok('l’auteur voit le motif sans avoir à le demander',
     /Ajoutez les chiffres réels/.test(await pw.locator(`tr:has-text("${ART}")`).innerText()));

  await pw.locator(`tr:has-text("${ART}") form:has(input[value=soumettre]) button`).click();
  await pw.waitForLoadState('domcontentloaded');
  await pe2.goto(`${BASE}/index.php?p=blog-relecture`, { waitUntil: 'domcontentloaded' });
  await pe2.locator(`.carte:has-text("${ART}") button[value=publier]`).click();
  await pe2.waitForLoadState('domcontentloaded');

  await pl.goto(`${BASE}/index.php?p=blog`, { waitUntil: 'domcontentloaded' });
  ok('une fois publié, il est sur le blog public',
     (await pl.locator(`a:has-text("${ART}")`).count()) >= 1);
  const lienPub = await pl.locator(`a:has-text("${ART}")`).first().getAttribute('href');
  await pl.goto(lienPub!, { waitUntil: 'domcontentloaded' });
  ok('il est signé du nom de l’organisateur',
     /Test Partenaire/.test(await pl.locator('.signature').innerText().catch(() => '')));

  // Un organisateur ne retire pas seul un article déjà en ligne.
  await pw.goto(`${BASE}/index.php?p=blog-admin`, { waitUntil: 'domcontentloaded' });
  ok('un article en ligne n’a plus de bouton « supprimer » chez son auteur',
     (await pw.locator(`tr:has-text("${ART}") button:has-text("Supprimer")`).count()) === 0);

  /* ================================================================== */
  console.log('\n━━ 28. La régie e-mail ━━');

  /**
   * L'écran de la régie, et surtout ses trois garde-fous : l'offre, la
   * relecture, et le désabonnement. Le transport SMTP de la recette est
   * branché à la section 22 ; on le rallume ici le temps d'un envoi réel.
   */
  await pe2.goto(`${BASE}/index.php?p=regie`, { waitUntil: 'domcontentloaded' });
  ok('l’équipe atteint la régie', pe2.url().includes('p=regie'));

  // PART est en Croissance depuis la section 12 : il y a droit.
  await pw.goto(`${BASE}/index.php?p=regie`, { waitUntil: 'domcontentloaded' });
  ok('un organisateur en Croissance atteint la régie', pw.url().includes('p=regie'));
  ok('son quota mensuel d’envois est affiché',
     /E-mails envoyés ce mois/.test(await pw.locator('.carte').first().innerText().catch(() => '')));

  // CLIENT est en Impact : la régie ne lui est pas vendue.
  const pi = await browser.newPage();
  surveiller(pi);
  await connexion(pi, CLIENT.email, CLIENT.mdp);
  await pi.goto(`${BASE}/index.php?p=regie`, { waitUntil: 'domcontentloaded' });
  ok('sans l’offre, un écran d’explication et non un refus sec',
     /ne comprend pas cette fonction/.test(await pi.locator('.carte').first().innerText().catch(() => '')));
  ok('le menu ne propose pas la régie à qui n’y a pas droit',
     (await pi.locator('nav a[href*="p=regie"]').count()) === 0);
  await pi.close();

  // Le garde-fou du lien : un organisateur ne renvoie que chez Wakabi.
  await pw.goto(`${BASE}/index.php?p=regie-ecrire`, { waitUntil: 'domcontentloaded' });
  await pw.selectOption('#r-cible', 'mes-invites');
  await pw.fill('#r-sujet', `Offre du samedi ${marque}`);
  await pw.fill('#r-titre', 'On remet ça samedi');
  await pw.fill('#r-corps', 'Vous étiez là en mars. On recommence samedi, même endroit, même heure.');
  await pw.fill('#r-lien', 'https://exemple-pirate.tg/');
  await pw.click('main button[type=submit]');
  await pw.waitForLoadState('domcontentloaded');
  ok('un lien hors domaine Wakabi est refusé à un organisateur',
     /domaine|wakabileguide/i.test(await pw.locator('.msg.err').first().innerText().catch(() => '')));

  await pw.fill('#r-lien', 'https://wakabileguide.com/maquis');
  await pw.click('main button[type=submit]');
  await pw.waitForLoadState('domcontentloaded');
  ok('avec un lien Wakabi, la campagne s’enregistre',
     pw.url().includes('p=regie-campagne'));
  ok('l’aperçu montre ce que la personne recevra',
     (await pw.locator('.apercu-courriel').count()) === 1
     && /On remet ça samedi/.test(await pw.locator('.apercu-courriel').innerText()));
  ok('un organisateur ne peut pas envoyer lui-même',
     (await pw.locator('form:has(input[value=envoyer])').count()) === 0);

  const urlCamp = pw.url();
  await pw.locator('form:has(input[value=soumettre]) button').click();
  await pw.waitForLoadState('domcontentloaded');
  const soumis = await pw.locator('.msg.ok, .msg.err').first().innerText().catch(() => '');
  ok('soumettre annonce le nombre de destinataires ou dit pourquoi c’est zéro',
     /destinataire|ne toucherait personne/.test(soumis), soumis.replace(/\s+/g, ' ').slice(0, 74));

  /* L'équipe reprend la main, avec un vrai SMTP en face. */
  const smtpRegie = await ouvrirSmtp();
  await pe2.goto(`${BASE}/index.php?p=reglages`, { waitUntil: 'domcontentloaded' });
  await pe2.fill('#smtp_hote', '127.0.0.1');
  await pe2.fill('#smtp_port', String(SMTP_PORT));
  await pe2.selectOption('#smtp_securite', 'aucune');
  await pe2.fill('#courriel_expediteur', 'boost@wakabileguide.com');
  await pe2.click('button[value=enregistrer]');
  await pe2.waitForLoadState('domcontentloaded');

  // Une campagne de l'équipe, vers un segment réel, envoyée pour de vrai.
  await pe2.goto(`${BASE}/index.php?p=regie-ecrire`, { waitUntil: 'domcontentloaded' });
  await pe2.selectOption('#r-cible', 'participants');
  await pe2.fill('#r-sujet', `Nouveautés du guide ${marque}`);
  await pe2.fill('#r-titre', 'Trois décors de plus cette semaine');
  await pe2.fill('#r-corps', 'Trois nouvelles campagnes sont en ligne. Faites votre badge avant samedi.');
  await pe2.click('main button[type=submit]');
  await pe2.waitForLoadState('domcontentloaded');

  const avantRegie = recus.length;
  await pe2.locator('form:has(input[value=soumettre]) button').click();
  await pe2.waitForLoadState('domcontentloaded');
  ok('l’équipe se relit elle-même et la campagne devient prête',
     /prête|destinataire/i.test(await pe2.locator('.msg.ok').first().innerText().catch(() => '')));

  await pe2.locator('form:has(input[value=envoyer]) button').click();
  await pe2.waitForLoadState('domcontentloaded');
  const rapport = await pe2.locator('.msg.ok, .msg.err').first().innerText().catch(() => '');
  ok('le premier lot part vraiment', /parti\(s\)/.test(rapport), rapport.replace(/\s+/g, ' ').slice(0, 60));
  ok('un vrai serveur SMTP a reçu les messages', recus.length > avantRegie,
     `${recus.length - avantRegie} message(s)`);

  /**
   * Le scénario le plus important de la section : le désabonnement.
   *
   * Un message marketing sans lien de désabonnement est illégal en Europe
   * et signalé partout ailleurs — et un signalement abîme la
   * délivrabilité de TOUS les envois du guide, liens de confirmation
   * compris.
   */
  const dernier = recus[recus.length - 1] ?? '';
  const lienDesab = /(https?:\/\/\S*p=desabonnement&(?:amp;)?j=[0-9a-f]{32})/.exec(dernier);
  ok('chaque message porte un lien de désabonnement', lienDesab !== null);

  if (lienDesab) {
    const ctxD = await browser.newContext();
    const pd2 = await ctxD.newPage();
    surveiller(pd2);
    await pd2.goto(lienDesab[1].replace(/&amp;/g, '&'), { waitUntil: 'domcontentloaded' });
    ok('le lien s’ouvre SANS être connecté', /Ne plus recevoir/.test(await pd2.locator('h1').innerText()));
    const adresse = (/[\w.+-]+@[\w.-]+/.exec(await pd2.locator('.carte').innerText()) ?? [''])[0];
    await pd2.click('button[type=submit]');
    await pd2.waitForLoadState('domcontentloaded');
    ok('un clic suffit à partir', /C’est fait/.test(await pd2.locator('h1').innerText()));

    // Et l'adresse sort des campagnes suivantes.
    await pe2.goto(`${BASE}/index.php?p=regie-ecrire`, { waitUntil: 'domcontentloaded' });
    await pe2.selectOption('#r-cible', 'participants');
    await pe2.fill('#r-sujet', `Après le départ ${marque}`);
    await pe2.fill('#r-titre', 'Un autre message');
    await pe2.fill('#r-corps', 'Ce message ne doit pas atteindre celui qui vient de se désabonner.');
    await pe2.click('main button[type=submit]');
    await pe2.waitForLoadState('domcontentloaded');
    await pe2.locator('form:has(input[value=soumettre]) button').click();
    await pe2.waitForLoadState('domcontentloaded');

    const avantSecond = recus.length;
    for (let i = 0; i < 4 && (await pe2.locator('form:has(input[value=envoyer]) button').count()); i++) {
      await pe2.locator('form:has(input[value=envoyer]) button').first().click();
      await pe2.waitForLoadState('domcontentloaded');
    }
    const vises = recus.slice(avantSecond).join('\n');
    ok('le désabonné ne reçoit plus rien', adresse !== '' && !vises.includes(adresse),
       adresse || 'adresse introuvable');
  }

  smtpRegie.fermer();
  await pe2.goto(`${BASE}/index.php?p=reglages`, { waitUntil: 'domcontentloaded' });
  await pe2.fill('#smtp_hote', '');
  await pe2.click('button[value=enregistrer]');
  await pe2.waitForLoadState('domcontentloaded');
  await pw.close();
  await pe2.close();
  await ctxLect.close();

  /* ================================================================== */
  console.log('\n━━ 29. Les menus rangés par intention ━━');

  const pm = await browser.newPage();
  surveiller(pm);
  await connexion(pm, ADMIN.email, ADMIN.mdp);
  await pm.goto(`${BASE}/index.php?p=admin`, { waitUntil: 'domcontentloaded' });

  const groupes = await pm.locator('.barre .deroulant > summary').evaluateAll(
    (n) => n.map((e) => (e.textContent ?? '').replace(/\s+/g, ' ').trim()));
  ok('l’administration tient en trois groupes nommés',
     groupes.length === 3 && groupes.join('|') === 'Contenus|Audience|Système',
     groupes.join(' · '));
  ok('le tableau de bord et l’entrée restent hors des groupes — un clic',
     (await pm.locator('.barre nav > a[href*="p=admin"]').count()) === 1
     && (await pm.locator('.barre nav > a[href*="p=scan"]').count()) === 1);
  ok('aucun groupe ne dépasse quatre destinations',
     (await pm.locator('.barre .deroulant').evaluateAll(
       (n) => n.every((e) => e.querySelectorAll('.volet a').length <= 4))));

  ok('le tableau de bord mène partout en un clic',
     (await pm.locator('.raccourci').count()) === 12,
     `${await pm.locator('.raccourci').count()} raccourcis`);
  ok('les raccourcis reprennent les familles du menu',
     (await pm.locator('.raccourcis .pas').evaluateAll(
       (n) => n.map((e) => (e.textContent ?? '').trim()).join('|'))) === 'Contenus|Audience|Système');

  // Les files du tableau de bord montrent bien les trois natures d'attente.
  ok('la file du tableau de bord compte aussi les articles et les campagnes',
     /article\(s\) à relire|campagne\(s\) e-mail à relire|décor\(s\) à relire|Rien n’attend/
       .test(await pm.locator('.files, .msg.ok').first().innerText().catch(() => '')));

  // Sur téléphone, les groupes deviennent des intitulés, pas des tiroirs.
  const ctxTel = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true });
  const pt2 = await ctxTel.newPage();
  surveiller(pt2);
  await connexion(pt2, ADMIN.email, ADMIN.mdp);
  await pt2.goto(`${BASE}/index.php?p=admin`, { waitUntil: 'domcontentloaded' });
  await pt2.click('.menu > summary');
  ok('sur téléphone, tous les liens sont atteignables sans second tiroir',
     (await pt2.locator('.menu[open] nav .volet a:visible').count()) === 12,
     `${await pt2.locator('.menu[open] nav .volet a:visible').count()} liens visibles`);
  ok('les intitulés de groupe restent lisibles, et ne se cliquent pas',
     (await pt2.locator('.menu[open] .deroulant > summary').first().isVisible())
     && (await pt2.locator('.menu[open] .deroulant > summary').first()
          .evaluate((e) => getComputedStyle(e).pointerEvents)) === 'none');
  await ctxTel.close();
  await pm.close();

  /* ================================================================== */
  console.log('\n━━ 30. Ce qui avait été signalé ━━');

  const pfix = await browser.newPage();
  surveiller(pfix);
  await connexion(pfix, ADMIN.email, ADMIN.mdp);
  await pfix.goto(`${BASE}/index.php?p=admin`, { waitUntil: 'domcontentloaded' });

  /**
   * 1. La feuille de style porte une empreinte de sa version.
   *
   * Sans elle, une mise à jour n'atteint pas qui a déjà visité le site :
   * le navigateur garde sa copie, le nouveau HTML arrive sur l'ancien
   * style, et les cartes redeviennent des liens bleus soulignés. Le défaut
   * est invisible depuis un navigateur neuf — donc pendant qu'on développe.
   */
  const feuille = await pfix.locator('link[rel=stylesheet]').first().getAttribute('href');
  ok('la feuille de style porte une empreinte de version',
     /wakabi\.css\?v=[0-9a-f]+$/.test(feuille ?? ''), feuille?.split('/').pop() ?? '');
  ok('et elle se télécharge toujours',
     (await pfix.request.get(feuille!)).ok());

  const bundles = await pfix.request.get(`${BASE}/index.php?p=profil`);
  ok('les bundles JavaScript aussi',
     /push\.js\?v=[0-9a-f]+/.test(await bundles.text()));

  // Et le style s'applique VRAIMENT : c'est ce que le rendu doit prouver,
  // pas la présence d'un attribut.
  const style = await pfix.locator('.raccourci').first().evaluate((e) => {
    const c = getComputedStyle(e);
    return { display: c.display, decoration: c.textDecorationLine };
  });
  ok('les raccourcis sont des blocs, pas du texte au fil de l’eau',
     style.display === 'block' && style.decoration === 'none',
     `${style.display} / ${style.decoration}`);

  /* 2. Le déroulant se referme quand on clique ailleurs. */
  const ouverts = () => pfix.locator('details.deroulant[open]').count();
  await pfix.click('.deroulant:has(summary:has-text("Contenus")) > summary');
  ok('un déroulant s’ouvre au clic', (await ouverts()) === 1);
  await pfix.mouse.click(200, 700);
  ok('il se referme quand on clique ailleurs', (await ouverts()) === 0);

  await pfix.click('.deroulant:has(summary:has-text("Contenus")) > summary');
  await pfix.click('.deroulant:has(summary:has-text("Audience")) > summary');
  ok('un seul déroulant reste ouvert à la fois', (await ouverts()) === 1);
  await pfix.keyboard.press('Escape');
  ok('Échap referme aussi', (await ouverts()) === 0);

  /* 3. L'envoi push dit ce qui s'est passé, au lieu de rester muet. */
  await pfix.goto(`${BASE}/index.php?p=diffusion`, { waitUntil: 'domcontentloaded' });
  ok('l’écran propose de s’envoyer un essai',
     (await pfix.locator('button[value=essai]').count()) === 1);
  ok('il dit combien de navigateurs sont abonnés à ce compte',
     /navigateur\(s\) abonné\(s\)|aucun navigateur abonné/.test(
       await pfix.locator('.carte:has-text("Vérifier que ça marche")').innerText()));

  /**
   * Le scénario qui compte : un abonnement PÉRIMÉ.
   *
   * C'est le cas le plus fréquent en production — le navigateur fait
   * tourner ses clés, l'abonnement enregistré devient muet, et rien ne le
   * dit. On en pose un vers un service qui répond 410, et l'écran doit le
   * nommer puis le nettoyer.
   */
  const faux = await ouvrirPush(3952);
  const pose = await pfix.request.post(`${BASE}/index.php?p=api-push-abonner`, {
    form: {
      csrf: JSON.parse(await pfix.locator('#push-contexte').innerText().catch(() => '{}') || '{}').csrf
        ?? (await (async () => {
          await pfix.goto(`${BASE}/index.php?p=profil`, { waitUntil: 'domcontentloaded' });
          return JSON.parse(await pfix.locator('#push-contexte').innerText()).csrf as string;
        })()),
      endpoint: `https://127.0.0.1:3952/fcm/send/mort-${marque}`,
      p256dh: faux.clePublique,
      auth: faux.auth,
    },
  });
  ok('un abonnement d’essai est enregistré', pose.ok(), `HTTP ${pose.status()}`);

  await pfix.goto(`${BASE}/index.php?p=diffusion`, { waitUntil: 'domcontentloaded' });
  await pfix.locator('button[value=essai]').click();
  await pfix.waitForLoadState('domcontentloaded');
  const verdict = await pfix.locator('.carte:has-text("Vérifier que ça marche")').innerText();
  ok('l’essai rend un verdict par navigateur, pas un silence',
     /Remise|HTTP \d{3}|Échec/.test(verdict),
     verdict.replace(/\s+/g, ' ').slice(0, 90));
  ok('un abonnement injoignable est nommé et non deviné',
     /HTTP \d{3}|Échec/.test(verdict));

  faux.fermer();
  await pfix.close();

  /* ================================================================== */
  console.log('\n━━ 31. L’éditeur d’article ━━');

  /**
   * L'éditeur est éprouvé au clavier, dans un vrai navigateur.
   *
   * `verifier-editeur.ts` prouve que la traduction aller-retour est
   * fidèle ; ici on vérifie qu'on peut réellement ÉCRIRE avec — c'est
   * une autre question, et c'est celle que se pose la personne qui rédige.
   */
  const ped = await browser.newPage();
  surveiller(ped);
  await connexion(ped, ADMIN.email, ADMIN.mdp);
  await ped.goto(`${BASE}/index.php?p=blog-editer`, { waitUntil: 'domcontentloaded' });
  await ped.waitForSelector('.editeur-zone');

  ok('l’éditeur remplace le champ de texte',
     (await ped.locator('.editeur-zone').isVisible())
     && (await ped.locator('#a-corps').isHidden()));
  ok('la barre propose les huit mises en forme',
     (await ped.locator('.editeur-outil').count()) === 8,
     `${await ped.locator('.editeur-outil').count()} outils`);
  ok('un éditeur vide affiche une invite', (await ped.locator('.editeur-zone[data-vide]').count()) === 1);

  const TITRE_ED = `Écrit à l’éditeur ${marque}`;
  await ped.fill('#a-titre', TITRE_ED);
  await ped.locator('.editeur-zone').click();
  await ped.keyboard.type('Ce qui a marché');
  await ped.locator('.editeur-outil[aria-label="Intertitre"]').click();
  await ped.keyboard.press('End');
  await ped.keyboard.press('Enter');
  await ped.keyboard.type('À Lomé, ');
  await ped.locator('.editeur-outil[aria-label="Gras"]').click();
  await ped.keyboard.type('400 personnes');
  await ped.locator('.editeur-outil[aria-label="Gras"]').click();
  await ped.keyboard.type(' sont venues.');
  await ped.keyboard.press('Enter');
  await ped.keyboard.type('1 200 badges en 9 jours');
  await ped.locator('.editeur-outil[aria-label="Liste à puces"]').click();

  const marques = await ped.locator('#a-corps').inputValue();
  ok('le champ envoyé contient les marques, pas du HTML',
     marques.includes('## Ce qui a marché')
     && marques.includes('**400 personnes**')
     && marques.includes('- 1 200 badges')
     && !/[<>]/.test(marques),
     marques.replace(/\n/g, ' ⏎ ').slice(0, 76));

  /**
   * Le scénario qui compte : coller depuis ailleurs.
   *
   * C'est par là qu'arrive tout ce qu'on ne veut pas — mise en page de
   * traitement de texte, styles en dur, et le reste. Le collage passe par
   * le texte brut, donc rien de tout cela n'entre.
   */
  await ped.evaluate(() => {
    const z = document.querySelector('.editeur-zone') as HTMLElement;
    const dt = new DataTransfer();
    dt.setData('text/html', '<p style="color:red">Rouge<script>window.__colle = 1;</script></p>');
    dt.setData('text/plain', 'Du texte collé.');
    z.focus();
    z.dispatchEvent(new ClipboardEvent('paste', { clipboardData: dt, bubbles: true, cancelable: true }));
  });
  await ped.waitForTimeout(150);
  const apresCollage = await ped.locator('#a-corps').inputValue();
  ok('un collage riche n’apporte que son texte',
     apresCollage.includes('Du texte collé.') && !/style|script|Rouge/.test(apresCollage));
  ok('et rien ne s’exécute au passage',
     (await ped.evaluate(() => (window as unknown as { __colle?: number }).__colle)) === undefined);

  /* L'article s'enregistre, se relit à l'identique, et se publie juste. */
  await ped.click('button[value=enregistrer]');
  await ped.waitForLoadState('domcontentloaded');
  const lienEd = await ped.locator(`tr:has-text("${TITRE_ED}") a[href*="p=blog-editer"]`)
    .first().getAttribute('href');
  await ped.goto(lienEd!, { waitUntil: 'domcontentloaded' });
  await ped.waitForSelector('.editeur-zone');
  const relu = await ped.locator('#a-corps').inputValue();
  ok('rouvrir un article ne le modifie pas', relu === apresCollage,
     relu === apresCollage ? '' : relu.slice(0, 60));
  ok('l’éditeur montre le titre en titre, et le gras en gras',
     (await ped.locator('.editeur-zone h3').count()) === 1
     && (await ped.locator('.editeur-zone strong').count()) === 1
     && (await ped.locator('.editeur-zone li').count()) >= 1);

  /* La bascule vers le texte brut, pour qui préfère taper ses marques. */
  await ped.locator('#a-bascule').click();
  ok('on peut repasser en texte brut', await ped.locator('#a-corps').isVisible());
  await ped.locator('#a-bascule').click();
  ok('et revenir à l’éditeur sans rien perdre',
     (await ped.locator('.editeur-zone').isVisible())
     && (await ped.locator('#a-corps').inputValue()) === relu);

  await ped.click('button[value=publier]');
  await ped.waitForLoadState('domcontentloaded');
  const slugEd = /p=blog&a=([a-z0-9-]+)/.exec(
    await ped.locator('.msg.ok').first().innerText().catch(() => ''))?.[1] ?? '';
  ok('l’article écrit à l’éditeur se publie', slugEd !== '', slugEd);
  if (slugEd) {
    const ctxLu = await browser.newContext();
    const plu = await ctxLu.newPage();
    surveiller(plu);
    await plu.goto(`${BASE}/index.php?p=blog&a=${slugEd}`, { waitUntil: 'domcontentloaded' });
    ok('la page publiée porte la même mise en forme que l’éditeur',
       (await plu.locator('.corps-article h3').count()) === 1
       && (await plu.locator('.corps-article strong').count()) === 1
       && (await plu.locator('.corps-article li').count()) >= 1);
    await ctxLu.close();
  }
  await ped.close();

  /* ================================================================== */
  console.log('\n━━ 32. Les rôles internes ━━');

  /**
   * Ce que chaque rôle OUVRE, et ce qu'il refuse.
   *
   * Le test le plus utile n'est pas « le menu montre la bonne chose » — un
   * menu se contourne en tapant l'adresse. C'est le serveur qui doit
   * refuser, et c'est ce qu'on éprouve ici : route par route, rôle par
   * rôle.
   */
  const pr = await browser.newPage();
  surveiller(pr);
  await connexion(pr, ADMIN.email, ADMIN.mdp);

  const INTERNES = [
    ['scanner', 'Kossi à la porte', ['scan', 'profil']],
    ['editeur', 'Ama à la rédaction', ['partenaire', 'nouveau', 'blog-admin', 'liens', 'profil']],
    ['coordinateur', 'Yao coordinateur',
     ['admin', 'scan', 'catalogue', 'relecture', 'partenaire', 'nouveau',
      'blog-admin', 'blog-relecture', 'regie', 'diffusion', 'liens', 'profil']],
  ] as const;
  const ROUTES = ['admin', 'scan', 'catalogue', 'relecture', 'partenaire', 'nouveau',
                  'blog-admin', 'blog-relecture', 'regie', 'diffusion', 'liens',
                  'comptes', 'reglages', 'sauvegardes', 'profil'];
  const MDP_INTERNE = 'interne-2026-solide';

  await pr.goto(`${BASE}/index.php?p=comptes`, { waitUntil: 'domcontentloaded' });
  const csrfComptes = await pr.locator('.formulaire-compte input[name=csrf]').first().inputValue();

  for (const [role, nom, attendues] of INTERNES) {
    const email = `${role}-${marque}@wakabileguide.com`;
    const cree = await pr.request.post(`${BASE}/index.php?p=creer-equipier`, {
      form: { csrf: csrfComptes, nom, email, role, mot_de_passe: MDP_INTERNE },
    });
    ok(`l’équipe crée un compte « ${role} »`, /Compte créé/.test(await cree.text()));

    const ctxR = await browser.newContext();
    const px = await ctxR.newPage();
    surveiller(px);
    await connexion(px, email, MDP_INTERNE);

    const ouvertes: string[] = [];
    for (const r of ROUTES) {
      await px.goto(`${BASE}/index.php?p=${r}`, { waitUntil: 'domcontentloaded' });
      if (px.url().includes(`p=${r}`)) ouvertes.push(r);
    }
    ok(`« ${role} » n’ouvre QUE ce que son rôle donne`,
       ouvertes.join(',') === [...attendues].join(','), ouvertes.join(', '));
    await ctxR.close();
  }

  /* Aucun rôle interne ne s'obtient tout seul. */
  const ctxPub = await browser.newContext();
  const ppub = await ctxPub.newPage();
  surveiller(ppub);
  await ppub.goto(`${BASE}/index.php?p=inscription`, { waitUntil: 'domcontentloaded' });
  const proposes = await ppub.locator('select[name=role] option')
    .evaluateAll((n) => n.map((e) => (e as HTMLOptionElement).value));
  ok('l’inscription publique ne propose que les rôles clients',
     proposes.join(',') === 'participant,partenaire', proposes.join(', '));

  const emailForce = `force-${marque}@exemple.tg`;
  await ppub.request.post(`${BASE}/index.php?p=inscription`, {
    form: {
      csrf: await ppub.locator('input[name=csrf]').inputValue(),
      role: 'coordinateur', nom: 'Rôle forcé', email: emailForce,
      mot_de_passe: 'un-mot-de-passe-solide', ville: 'lome',
    },
  });
  await connexion(ppub, emailForce, 'un-mot-de-passe-solide');
  ok('un formulaire trafiqué ne fabrique pas un coordinateur',
     ppub.url().includes('p=connexion'), ppub.url().split('index.php')[1] ?? '');
  await ctxPub.close();

  /* ================================================================== */
  console.log('\n━━ 33. Le super-administrateur ━━');

  /**
   * Ce qui sépare l'équipe du super-administrateur tient en une ligne de
   * la table — et c'est tout ce qui sépare une équipe d'une porte ouverte.
   * Sans elle, n'importe quel membre se promeut, promeut un ami, ou
   * suspend le dernier administrateur.
   */
  ok('le compte fondateur est super-administrateur',
     /Super administrateur/.test(
       await pr.locator(`tr:has-text("${ADMIN.email}")`).first().innerText().catch(() => '')));

  /**
   * L'équipe a sa propre liste, et elle n'est jamais tronquée.
   *
   * Une liste unique plafonnée et rangée du plus récent au plus ancien
   * finit par pousser le fondateur derrière deux cents organisateurs :
   * il devient injoignable au moment précis où l'on en a besoin.
   */
  ok('l’équipe et les clients font deux listes',
     (await pr.locator('.bloc-comptes').count()) === 2,
     `${await pr.locator('.bloc-comptes').count()} bloc(s)`);
  ok('le fondateur est dans la liste de l’équipe',
     (await pr.locator('.bloc-comptes').first()
        .locator(`tr:has-text("${ADMIN.email}")`).count()) === 1);
  const rolesEquipe = await pr.locator('.bloc-comptes').first()
    .locator('tbody tr select[name=role]').evaluateAll((n) => n.map((e) => (e as HTMLSelectElement).value));
  ok('aucun client ne s’est glissé dans la liste de l’équipe',
     rolesEquipe.every((r) => ['scanner', 'editeur', 'coordinateur', 'equipe', 'super_admin'].includes(r)),
     rolesEquipe.join(', ') || 'aucun rôle modifiable');

  /* La longue liste, elle, se cherche — sinon rien n'y est retrouvable. */
  await pr.goto(`${BASE}/index.php?p=comptes&q=${encodeURIComponent(PART.email)}`,
                { waitUntil: 'domcontentloaded' });
  const trouves = pr.locator('.bloc-comptes').nth(1).locator('tbody tr');
  ok('la recherche retrouve un client par son adresse',
     (await trouves.count()) === 1
     && (await trouves.first().innerText()).includes(PART.email),
     `${await trouves.count()} résultat(s)`);
  await pr.goto(`${BASE}/index.php?p=comptes&q=zzz-personne-${marque}`,
                { waitUntil: 'domcontentloaded' });
  ok('une recherche sans résultat le dit',
     (await pr.locator('.bloc-comptes').nth(1).innerText()).includes('Aucun compte client'));
  await pr.goto(`${BASE}/index.php?p=comptes`, { waitUntil: 'domcontentloaded' });

  const EQ = { email: `equipe-${marque}@wakabileguide.com`, mdp: MDP_INTERNE };
  const creeEq = await pr.request.post(`${BASE}/index.php?p=creer-equipier`, {
    form: { csrf: csrfComptes, nom: 'Membre équipe', email: EQ.email,
            role: 'equipe', mot_de_passe: EQ.mdp },
  });
  ok('un super-administrateur crée un compte d’équipe', /Compte créé/.test(await creeEq.text()));

  const ctxEq = await browser.newContext();
  const peq = await ctxEq.newPage();
  surveiller(peq);
  await connexion(peq, EQ.email, EQ.mdp);
  await peq.goto(`${BASE}/index.php?p=comptes`, { waitUntil: 'domcontentloaded' });

  /**
   * Le formulaire de l'équipe n'existe PAS pour un compte « equipe ».
   *
   * Ce n'est pas une liste grisée ni un champ désactivé : la porte n'est
   * pas là. Un champ désactivé se rouvre depuis la console du navigateur —
   * ce que le serveur refuse ensuite, mais autant ne pas l'exposer.
   */
  ok('un compte d’équipe n’a qu’un seul formulaire, celui des clients',
     (await peq.locator('details.creer').count()) === 1
     && (await peq.locator('form[action*="creer-equipier"]').count()) === 0,
     `${await peq.locator('details.creer').count()} formulaire(s)`);
  const rolesOfferts = await peq.locator('#c-role option')
    .evaluateAll((n) => n.map((e) => (e as HTMLOptionElement).value));
  ok('et ce formulaire ne propose aucun rôle interne',
     rolesOfferts.every((r) => ['participant', 'partenaire'].includes(r)),
     rolesOfferts.join(' · '));

  const csrfEq = await peq.locator('.formulaire-compte input[name=csrf]').first().inputValue();
  for (const role of ['coordinateur', 'equipe', 'super_admin']) {
    // Les DEUX portes sont essayées : celle des clients doit renvoyer à
    // l'autre formulaire, celle de l'équipe doit renvoyer au grade.
    const parClient = await peq.request.post(`${BASE}/index.php?p=creer-compte`, {
      form: { csrf: csrfEq, nom: 'Complice ' + role, email: `complice-${role}-${marque}@exemple.tg`,
              role, formule: 'decouverte', mot_de_passe: 'un-mot-de-passe-solide' },
    });
    const parEquipe = await peq.request.post(`${BASE}/index.php?p=creer-equipier`, {
      form: { csrf: csrfEq, nom: 'Complice ' + role, email: `complice2-${role}-${marque}@exemple.tg`,
              role, mot_de_passe: 'un-mot-de-passe-solide' },
    });
    ok(`l’équipe ne peut pas se fabriquer un « ${role} », par aucune des deux portes`,
       /crée des comptes clients/.test(await parClient.text())
       && /super-administrateur crée un compte/.test(await parEquipe.text()));
  }

  /* Ni toucher au super-administrateur en place. */
  const idSA = await peq.locator(`tr:has-text("${ADMIN.email}") input[name=id]`)
    .first().inputValue().catch(() => '');
  const suspendre = await peq.request.post(`${BASE}/index.php?p=suspendre`,
    { form: { csrf: csrfEq, id: idSA } });
  ok('l’équipe ne suspend pas un super-administrateur',
     /ne se modifie que par un super-administrateur/.test(await suspendre.text())
     || suspendre.url().includes('err='));
  await ctxEq.close();

  /* Et le dernier super-administrateur ne se défait pas lui-même. */
  const csrfSA = await pr.locator('.formulaire-compte input[name=csrf]').first().inputValue();
  const auto = await pr.request.post(`${BASE}/index.php?p=role`,
    { form: { csrf: csrfSA, id: idSA, role: 'equipe', formule: 'decouverte' } });
  ok('le dernier super-administrateur ne se rétrograde pas',
     /propre compte|dernier super-administrateur/.test(await auto.text())
     || auto.url().includes('err='));
  await pr.close();

  /* ================================================================== */
  console.log('\n━━ 34. Les images dans un article ━━');

  const pim = await browser.newPage();
  surveiller(pim);
  pim.on('dialog', (d) => { void d.accept('La salle au complet'); });
  await connexion(pim, ADMIN.email, ADMIN.mdp);
  await pim.goto(`${BASE}/index.php?p=blog-editer`, { waitUntil: 'domcontentloaded' });
  await pim.waitForSelector('.editeur-zone');

  const TITRE_IM = `Article illustré ${marque}`;
  await pim.fill('#a-titre', TITRE_IM);
  await pim.locator('.editeur-zone').click();
  await pim.keyboard.type('Voici ce qu’on a vu.');

  const [choix] = await Promise.all([
    pim.waitForEvent('filechooser'),
    pim.locator('.editeur-outil[aria-label="Image"]').click(),
  ]);
  await choix.setFiles({ name: 'salle.png', mimeType: 'image/png', buffer: imagePng(600, 400) });
  await pim.waitForSelector('.editeur-zone figure img', { timeout: 20_000 });

  const marquesIm = await pim.locator('#a-corps').inputValue();
  ok('l’image devient une marque, pas une balise',
     /^!\[La salle au complet\]\(\S+\)$/m.test(marquesIm) && !/[<>]/.test(marquesIm),
     marquesIm.split('\n').filter(Boolean).pop()?.slice(0, 64) ?? '');

  await pim.click('button[value=publier]');
  await pim.waitForLoadState('domcontentloaded');
  const slugIm = /p=blog&a=([a-z0-9-]+)/.exec(
    await pim.locator('.msg.ok').first().innerText().catch(() => ''))?.[1] ?? '';
  ok('l’article illustré se publie', slugIm !== '', slugIm);

  const ctxIm = await browser.newContext();
  const pv2 = await ctxIm.newPage();
  surveiller(pv2);
  await pv2.goto(`${BASE}/index.php?p=blog&a=${slugIm}`, { waitUntil: 'domcontentloaded' });
  const figure = pv2.locator('.corps-article .image-article');
  ok('la page publiée porte la figure et sa légende',
     (await figure.count()) === 1
     && (await figure.locator('figcaption').innerText()) === 'La salle au complet');
  const src = await figure.locator('img').getAttribute('src');
  ok('l’image passe par le redimensionneur', /p=vignette/.test(src ?? ''), src?.slice(-40) ?? '');
  const rep = await pv2.request.get(src!);
  ok('et elle se télécharge', rep.ok() && (rep.headers()['content-type'] ?? '') === 'image/webp',
     `HTTP ${rep.status()} ${rep.headers()['content-type']}`);
  ok('la légende sert aussi de texte de remplacement',
     (await figure.locator('img').getAttribute('alt')) === 'La salle au complet');

  /**
   * Le garde-fou : une image d'AILLEURS ne s'affiche pas.
   *
   * Une adresse extérieure serait un mouchard posé sur la page de
   * quelqu'un d'autre, une image qui disparaît le jour où le site
   * d'origine ferme, et du contenu mixte sur une page en HTTPS.
   */
  await pim.goto(`${BASE}/index.php?p=blog-editer`, { waitUntil: 'domcontentloaded' });
  await pim.waitForSelector('.editeur-zone');
  await pim.fill('#a-titre', `Image d’ailleurs ${marque}`);
  await pim.locator('#a-bascule').click();
  await pim.fill('#a-corps',
    'Avant l’image.\n\n![Pirate](https://ailleurs.example/pixel.gif)\n\nAprès l’image.');
  await pim.click('button[value=publier]');
  await pim.waitForLoadState('domcontentloaded');
  const slugAil = /p=blog&a=([a-z0-9-]+)/.exec(
    await pim.locator('.msg.ok').first().innerText().catch(() => ''))?.[1] ?? '';
  await pv2.goto(`${BASE}/index.php?p=blog&a=${slugAil}`, { waitUntil: 'domcontentloaded' });
  const corpsAil = await pv2.locator('.corps-article').innerHTML();
  ok('une image hébergée ailleurs n’est pas rendue',
     !/ailleurs\.example|<img/.test(corpsAil) && /Après l’image/.test(corpsAil));

  await ctxIm.close();
  await pim.close();

  /* ================================================================== */
  console.log('\n━━ 35. Recadrer, redimensionner, et ne rien perdre ━━');

  const pre = await browser.newPage();
  surveiller(pre);
  pre.on('dialog', (d) => { void d.accept('Le podium'); });
  await connexion(pre, ADMIN.email, ADMIN.mdp);
  await pre.goto(`${BASE}/index.php?p=blog-editer`, { waitUntil: 'domcontentloaded' });
  await pre.waitForSelector('.editeur-zone');

  const TITRE_RE = `Recadré ${marque}`;
  await pre.fill('#a-titre', TITRE_RE);
  await pre.setInputFiles('#a-image',
    { name: 'couv.png', mimeType: 'image/png', buffer: imagePng(900, 600) });
  await pre.locator('.editeur-zone').click();
  await pre.keyboard.type('Avant la photo.');
  const [choixRe] = await Promise.all([
    pre.waitForEvent('filechooser'),
    pre.locator('.editeur-outil[aria-label="Image"]').click(),
  ]);
  await choixRe.setFiles({ name: 'podium.png', mimeType: 'image/png', buffer: imagePng(900, 600) });
  await pre.waitForSelector('.editeur-zone figure img', { timeout: 20_000 });

  ok('le poids obtenu est annoncé, hors du texte',
     /après compression/.test(await pre.locator('.editeur-note').innerText())
     && !/compression/.test(await pre.locator('#a-corps').inputValue()));

  /* --- la largeur --- */
  await pre.locator('.editeur-zone figure img').click();
  ok('cliquer l’image ouvre ses réglages', await pre.locator('.editeur-image').isVisible());
  await pre.locator('.editeur-image input[type=range]').fill('60');
  await pre.waitForTimeout(150);
  ok('la largeur choisie s’écrit dans la marque',
     /&t=60\)$/m.test(await pre.locator('#a-corps').inputValue()),
     (await pre.locator('#a-corps').inputValue()).split('\n').pop()?.slice(-30) ?? '');

  /* --- le recadrage --- */
  await pre.locator('.editeur-image [data-i=cadrer]').click();
  await pre.waitForSelector('.recadrage-scene img');
  await pre.waitForTimeout(300);
  const scene = await pre.locator('.recadrage-scene').boundingBox();
  const coin = await pre.locator('.recadrage-cadre [data-coin=ho]').boundingBox();
  await pre.mouse.move(coin!.x + 9, coin!.y + 9);
  await pre.mouse.down();
  await pre.mouse.move(scene!.x + scene!.width * 0.3, scene!.y + scene!.height * 0.25, { steps: 10 });
  await pre.mouse.up();
  await pre.locator('.recadrage [data-r=ok]').click();
  await pre.waitForTimeout(400);

  const marquesRe = await pre.locator('#a-corps').inputValue();
  const cadrage = /&c=(\d+)-(\d+)-(\d+)-(\d+)/.exec(marquesRe);
  ok('le cadrage s’écrit en millièmes dans la marque',
     cadrage !== null && Number(cadrage[1]) > 200 && Number(cadrage[1]) < 400,
     cadrage?.[0] ?? marquesRe.split('\n').pop() ?? '');

  const srcRe = await pre.locator('.editeur-zone figure img').getAttribute('src') ?? '';
  const servie = await pre.request.get(srcRe);
  ok('le serveur sert bien l’image recadrée',
     servie.ok() && (servie.headers()['content-type'] ?? '').startsWith('image/'),
     `HTTP ${servie.status()} ${servie.headers()['content-type']}`);

  /* --- publier, et vérifier que TOUT a survécu --- */
  await pre.click('button[value=publier]');
  await pre.waitForLoadState('domcontentloaded');
  const slugRe = /p=blog&a=([a-z0-9-]+)/.exec(
    await pre.locator('.msg.ok').first().innerText().catch(() => ''))?.[1] ?? '';
  ok('l’article recadré se publie', slugRe !== '', slugRe);

  const ctxRe = await browser.newContext();
  const pvRe = await ctxRe.newPage();
  surveiller(pvRe);
  await pvRe.goto(`${BASE}/index.php?p=blog&a=${slugRe}`, { waitUntil: 'domcontentloaded' });
  const figRe = pvRe.locator('.corps-article .image-article');
  ok('la largeur choisie se retrouve sur la page publiée',
     /width:\s*60%/.test(await figRe.getAttribute('style') ?? ''),
     await figRe.getAttribute('style') ?? '');
  const imgRe = figRe.locator('img');
  const hRe = Number(await imgRe.getAttribute('height'));
  const lRe = Number(await imgRe.getAttribute('width'));
  /* 900×600 rogné à ~70 % × ~75 % : la proportion CHANGE, et les
     dimensions annoncées doivent suivre, sinon la page saute. */
  ok('les dimensions annoncées sont celles de l’image recadrée',
     lRe > 0 && hRe > 0 && Math.abs(hRe / lRe - 0.75) > 0.02,
     `${lRe} × ${hRe}`);

  /**
   * Le défaut qui a motivé tout ceci : modifier un article effaçait sa
   * couverture. Le champ n'était pas renvoyé, l'enregistrement écrivait
   * NULL, et personne ne fait le lien entre « j'ai corrigé une faute » et
   * « l'image a disparu ».
   */
  ok('la couverture a survécu à la publication',
     (await pvRe.locator('.corps-article').locator('xpath=preceding::img').count()) > 0
     || /p=vignette|p=media/.test(await pvRe.locator('main').innerHTML()));

  await pre.goto(`${BASE}/index.php?p=blog-admin`, { waitUntil: 'domcontentloaded' });
  await pre.locator(`tr:has-text("${TITRE_RE}") a[href*="blog-editer"]`).first().click();
  await pre.waitForSelector('.editeur-zone figure img');
  ok('la couverture est encore là à la réouverture',
     (await pre.locator('input[name=effacer_image]').count()) === 1);
  ok('le champ caché la renvoie au serveur',
     (await pre.locator('input[name=couverture]').inputValue()).includes('p=media'));

  const avantEnr = await pre.locator('input[name=couverture]').inputValue();
  await pre.fill('#a-titre', TITRE_RE + ' (corrigé)');
  await pre.click('main button[value=enregistrer]');
  await pre.waitForLoadState('domcontentloaded');
  await pre.locator(`tr:has-text("${TITRE_RE}") a[href*="blog-editer"]`).first().click();
  await pre.waitForSelector('.editeur-zone');
  ok('corriger le titre n’efface pas la couverture',
     (await pre.locator('input[name=couverture]').inputValue()) === avantEnr,
     (await pre.locator('input[name=couverture]').inputValue()).slice(-24));
  ok('ni l’image du corps',
     /!\[Le podium\]/.test(await pre.locator('#a-corps').inputValue()));
  ok('ni son cadrage, ni sa largeur',
     /&c=\d+-\d+-\d+-\d+/.test(await pre.locator('#a-corps').inputValue())
     && /&t=60/.test(await pre.locator('#a-corps').inputValue()));

  /**
   * Une figure EMBALLÉE dans un conteneur reste une image.
   *
   * C'est ce que produit un collage depuis la page publiée. Sans la
   * descente dans les conteneurs, la sérialisation jetait la figure avec
   * son `<div>` et l'image disparaissait en silence.
   */
  const survit = await pre.evaluate(() => {
    const zone = document.querySelector('.editeur-zone') as HTMLElement;
    // On recopie l'adresse d'une image RÉELLE : c'est ce que fait un
    // collage depuis la page publiée, et cela évite d'aller chercher un
    // fichier qui n'existe pas.
    const src = zone.querySelector('figure img')?.getAttribute('src') ?? '';
    const enveloppe = document.createElement('div');
    enveloppe.innerHTML = '<p>Collé.</p><figure class="image-article">'
      + '<img src="' + src + '">'
      + '<figcaption>Venue d’ailleurs</figcaption></figure>';
    zone.appendChild(enveloppe);
    zone.dispatchEvent(new Event('input'));
    return (document.getElementById('a-corps') as HTMLTextAreaElement).value;
  });
  ok('une figure collée dans un conteneur n’est pas perdue',
     /!\[Venue d’ailleurs\]/.test(survit), survit.split('\n').pop()?.slice(0, 48) ?? '');

  /**
   * La passe de maintenance couvre AUSSI les images d'articles.
   *
   * Une couverture téléversée avant que la compression n'existe pèse
   * encore ses trois mégaoctets ; et quand l'allègement change
   * l'extension du fichier, l'adresse doit être réécrite jusque dans les
   * marques du corps, sans quoi l'article se retrouve avec une image
   * morte au milieu du texte.
   */
  await pre.goto(`${BASE}/index.php?p=reglages`, { waitUntil: 'domcontentloaded' });
  await pre.locator('button[value=images]').click();
  await pre.waitForLoadState('domcontentloaded');
  const bilan = await pre.locator('.msg.ok, .msg.err').first().innerText().catch(() => '');
  ok('la maintenance des images rend un bilan lisible',
     /image\(s\) traitée\(s\)|déjà optimisées/.test(bilan), bilan.replace(/\s+/g, ' ').slice(0, 90));

  await pvRe.goto(`${BASE}/index.php?p=blog&a=${slugRe}`, { waitUntil: 'domcontentloaded' });
  const apres = await pvRe.request.get(
    (await figRe.locator('img').getAttribute('src')) ?? '');
  ok('après la maintenance, l’image de l’article se télécharge toujours',
     apres.ok(), `HTTP ${apres.status()}`);

  await ctxRe.close();
  await pre.close();

  /* ================================================================== */
  console.log('\n━━ 36. Ce que l’audit a corrigé ━━');

  const pau = await browser.newPage();
  surveiller(pau);
  await connexion(pau, ADMIN.email, ADMIN.mdp);

  /* --- nº1 : le catalogue ne tronque plus en silence --- */
  await pau.goto(`${BASE}/index.php?p=catalogue`, { waitUntil: 'domcontentloaded' });
  const totalCat = Number(/(\d+) au total/.exec(await pau.locator('.entete').innerText())?.[1] ?? 0);
  const cartesCat = await pau.locator('main .carte').count();
  const barreCat = await pau.locator('main').innerText();
  ok('le catalogue dit combien de pages il a',
     totalCat <= cartesCat || /Page \d+ sur \d+/.test(barreCat),
     `${cartesCat} carte(s) sur ${totalCat}`);
  if (totalCat > cartesCat) {
    await pau.locator('a:has-text("Suivants")').click();
    await pau.waitForLoadState('domcontentloaded');
    ok('la page suivante montre d’autres décors',
       (await pau.locator('main').innerText()).includes('Page 2 sur'),
       (/Page \d+ sur \d+/.exec(await pau.locator('main').innerText()) ?? [''])[0]);
  }

  /* --- nº5 : connexion et inscription renvoient chez soi --- */
  await pau.goto(`${BASE}/index.php?p=connexion`, { waitUntil: 'domcontentloaded' });
  ok('l’écran de connexion renvoie qui est déjà connecté',
     !pau.url().includes('p=connexion'), pau.url().split('index.php')[1] ?? '');
  await pau.goto(`${BASE}/index.php?p=inscription`, { waitUntil: 'domcontentloaded' });
  ok('l’inscription aussi', !pau.url().includes('p=inscription'),
     pau.url().split('index.php')[1] ?? '');

  /* --- nº3 : l'API existe --- */
  await pau.goto(`${BASE}/index.php?p=api-doc`, { waitUntil: 'domcontentloaded' });
  ok('la documentation de l’API est servie',
     (await pau.locator('main').innerText()).includes('Authorization: Bearer'));
  await pau.locator('form[action*="api-cle"] button').first().click();
  await pau.waitForLoadState('domcontentloaded');
  const cleApi = (await pau.locator('.bloc-code').first().innerText()).trim();
  ok('une clé se fabrique depuis l’écran', /^wkb_[0-9a-f]{48}$/.test(cleApi),
     cleApi.slice(0, 12) + '…');

  const api = (r: string) => `${BASE}/index.php?p=api&r=${r}`;
  const apiSansCle = await pau.request.get(api('moi'));
  ok('l’API refuse une requête sans clé', apiSansCle.status() === 401,
     `HTTP ${apiSansCle.status()}`);
  const mauvaise = await pau.request.get(api('moi'), { headers: { 'X-Api-Cle': 'wkb_faux' } });
  ok('et distingue une clé inconnue', (await mauvaise.json()).genre === 'cle_inconnue');

  const entetes = { Authorization: 'Bearer ' + cleApi };
  const moi = await (await pau.request.get(api('moi'), { headers: entetes })).json();
  ok('« moi » rend le compte et ses compteurs',
     moi.ok === true && moi.compte.email === ADMIN.email && 'campagnes' in moi.compteurs,
     moi.compte?.offre_libelle ?? '');
  const camps = await (await pau.request.get(api('campagnes'),
    { headers: { 'X-Api-Cle': cleApi } })).json();
  ok('« campagnes » rend les chiffres de chacune',
     camps.ok === true && Array.isArray(camps.campagnes)
     && (camps.campagnes.length === 0 || 'chiffres' in camps.campagnes[0]),
     `${camps.campagnes?.length ?? 0} campagne(s)`);
  const inconnue = await pau.request.get(api('zzz'), { headers: entetes });
  ok('une ressource inconnue rend 404 et non du HTML',
     inconnue.status() === 404
     && (inconnue.headers()['content-type'] ?? '').includes('application/json'));

  /**
   * Le garde-fou tient AUSSI par l'API.
   *
   * C'est le scénario qui compte : une porte dérobée est d'autant plus
   * tentante qu'elle est en JSON. Le compte de la recette a le droit de
   * valider, on éprouve donc le refus avec un lien qui n'est pas une
   * adresse du tout.
   */
  const lienFaux = await pau.request.post(api('liens'), {
    headers: entetes, data: { cible: 'pas-une-adresse' },
  });
  ok('l’API refuse une cible qui n’est pas une adresse',
     lienFaux.status() === 422 && (await lienFaux.json()).genre === 'cible_invalide');
  const lienBon = await pau.request.post(api('liens'), {
    headers: entetes, data: { cible: 'https://wakabileguide.com/p/api-' + marque, titre: 'API' },
  });
  ok('et en crée un quand elle est bonne',
     lienBon.status() === 201 && /^[A-Za-z0-9]{6}$/.test((await lienBon.json()).code));

  /* --- le journal --- */
  await pau.goto(`${BASE}/index.php?p=journal`, { waitUntil: 'domcontentloaded' });
  ok('le journal est servi à qui gère les comptes',
     pau.url().includes('p=journal') && (await pau.locator('main').innerText()).includes('Journal'));

  /* --- l'échéance, la facture --- */
  const ABONNE = { email: `abonne-${marque}@exemple.tg`, mdp: 'abonne-2026-solide' };
  const csrfC = await pau.goto(`${BASE}/index.php?p=comptes`, { waitUntil: 'domcontentloaded' })
    .then(() => pau.locator('.formulaire-compte input[name=csrf]').first().inputValue());
  await pau.request.post(`${BASE}/index.php?p=creer-compte`, {
    form: { csrf: csrfC, nom: 'Abonné payant', email: ABONNE.email, role: 'partenaire',
            formule: 'croissance', mot_de_passe: ABONNE.mdp },
  });
  await pau.goto(`${BASE}/index.php?p=comptes&q=${encodeURIComponent(ABONNE.email)}`,
                 { waitUntil: 'domcontentloaded' });
  await pau.locator('.bloc-comptes').nth(1).locator('a[href*="organisateur"]').first().click();
  await pau.waitForLoadState('domcontentloaded');
  ok('une offre payante ouvre une carte d’abonnement',
     (await pau.locator('main').innerText()).includes('Abonnement'));
  await pau.locator('button:has-text("Enregistrer le paiement")').click();
  await pau.waitForLoadState('domcontentloaded');
  const apresPaiement = (await pau.locator('main').innerText()).replace(/\s+/g, ' ');
  ok('un paiement repousse l’échéance', /Payée jusqu’au \d{2}\/\d{2}\/\d{4}/.test(apresPaiement),
     (/Payée jusqu’au \d{2}\/\d{2}\/\d{4}/.exec(apresPaiement) ?? [''])[0]);
  const numero = (/WB-\d{4}-\d{4}/.exec(apresPaiement) ?? [''])[0];
  ok('et émet une facture numérotée', numero !== '', numero);
  await pau.locator('a[href*="p=facture"]').first().click();
  await pau.waitForLoadState('domcontentloaded');
  const facture = (await pau.locator('.facture').innerText()).replace(/\s+/g, ' ');
  ok('la facture porte le client, la période et le total',
     facture.includes('Abonné payant') && facture.includes('Total réglé')
     && facture.includes(numero), facture.slice(0, 60));

  /* --- ce que le client voit de son côté --- */
  const ctxCli = await browser.newContext({ acceptDownloads: true });
  const pcli = await ctxCli.newPage();
  surveiller(pcli);
  await connexion(pcli, ABONNE.email, ABONNE.mdp);
  await pcli.goto(`${BASE}/index.php?p=profil`, { waitUntil: 'domcontentloaded' });
  const profilCli = (await pcli.locator('main').innerText()).replace(/\s+/g, ' ');
  ok('le client voit son échéance sans écrire à personne',
     profilCli.includes('Mon abonnement') && profilCli.includes(numero));

  const expo = await pcli.request.get(`${BASE}/index.php?p=profil-export`);
  const donnees = await expo.json();
  ok('il emporte ses données en un fichier',
     expo.ok() && donnees.compte.email === ABONNE.email && Array.isArray(donnees.campagnes),
     Object.keys(donnees).join(', ').slice(0, 60));
  ok('l’export ne contient ni mot de passe ni clé d’API',
     !JSON.stringify(donnees).includes('mot_de_passe')
     && !JSON.stringify(donnees).includes('cle_api'));

  /* --- l'API est fermée à qui n'a pas l'offre --- */
  await pcli.goto(`${BASE}/index.php?p=api-doc`, { waitUntil: 'domcontentloaded' });
  ok('sans l’offre, la documentation le dit au lieu de refuser sèchement',
     (await pcli.locator('main').innerText()).includes('n’ouvre pas l’API'));

  /* --- un compte interne n'a pas d'offre commerciale --- */
  const INT = { email: `editeur-${marque}@wakabileguide.com`, mdp: 'interne-2026-solide' };
  await pau.goto(`${BASE}/index.php?p=comptes`, { waitUntil: 'domcontentloaded' });
  await pau.request.post(`${BASE}/index.php?p=creer-equipier`, {
    form: { csrf: await pau.locator('.formulaire-compte input[name=csrf]').first().inputValue(),
            nom: 'Éditeur audit', email: INT.email, mot_de_passe: INT.mdp, role: 'editeur' },
  });
  const ctxInt = await browser.newContext();
  const pint = await ctxInt.newPage();
  surveiller(pint);
  await connexion(pint, INT.email, INT.mdp);
  await pint.goto(`${BASE}/index.php?p=partenaire`, { waitUntil: 'domcontentloaded' });
  const bordInt = (await pint.locator('main').innerText()).replace(/\s+/g, ' ');
  ok('un compte de l’équipe ne se voit pas attribuer d’offre commerciale',
     !/offre Découverte/.test(bordInt) && bordInt.includes('aucun quota'),
     bordInt.slice(0, 80));

  /* --- la double authentification --- */
  await pint.goto(`${BASE}/index.php?p=profil`, { waitUntil: 'domcontentloaded' });
  ok('la double authentification est proposée à l’équipe',
     (await pint.locator('#otp').count()) === 1);
  await pint.locator('#otp button:has-text("Mettre en place")').click();
  await pint.waitForLoadState('domcontentloaded');
  const secret = (await pint.locator('#otp .bloc-code').innerText()).replace(/\s/g, '');
  ok('un secret et un QR sont proposés',
     /^[A-Z2-7]{32}$/.test(secret) && (await pint.locator('#otp img').count()) === 1,
     `${secret.length} caractères`);
  await pint.fill('#otp-code', totp(secret));
  await pint.locator('#otp button:has-text("Activer")').click();
  await pint.waitForLoadState('domcontentloaded');
  ok('un code calculé comme le ferait un téléphone est accepté',
     (await pint.locator('.msg.ok').first().innerText().catch(() => '')).includes('en service'));

  const ctx2f = await browser.newContext();
  const p2f = await ctx2f.newPage();
  surveiller(p2f);
  await p2f.goto(`${BASE}/index.php?p=connexion`, { waitUntil: 'domcontentloaded' });
  await p2f.fill('input[name=email]', INT.email);
  await p2f.fill('input[name=mot_de_passe]', INT.mdp);
  await p2f.click('main button[type=submit]');
  await p2f.waitForLoadState('domcontentloaded');
  ok('le mot de passe seul ne connecte plus',
     p2f.url().includes('p=connexion') && (await p2f.locator('#code').count()) === 1);
  await p2f.fill('#code', '000000');
  await p2f.click('main button[type=submit]');
  await p2f.waitForLoadState('domcontentloaded');
  ok('un mauvais code est refusé',
     (await p2f.locator('.msg.err').first().innerText().catch(() => '')).includes('pas le bon'));
  await p2f.fill('#code', totp(secret));
  await p2f.click('main button[type=submit]');
  await p2f.waitForLoadState('domcontentloaded');
  ok('le bon code ouvre la session', !p2f.url().includes('p=connexion'),
     p2f.url().split('index.php')[1] ?? '');
  await ctx2f.close();

  /* --- confier une campagne, et rien d'autre --- */
  const ctxGra = await browser.newContext();
  const pgra = await ctxGra.newPage();
  surveiller(pgra);
  const GRA = { email: `graphiste-${marque}@exemple.tg`, mdp: 'graphiste-2026-solide' };
  await inscription(pgra, GRA.email, GRA.mdp, 'partenaire', 'Graphiste invité');

  // N'importe lequel des décors du catalogue fait l'affaire : ce qu'on
  // éprouve est le partage, pas ce décor-là. Le prendre au vol évite de
  // dépendre d'un titre qu'une autre section a pu archiver entre-temps.
  // Un BROUILLON, forcément : un décor publié ne se modifie plus, par
  // personne — et confier une campagne qu'on ne peut pas ouvrir
  // n'éprouverait rien.
  await pau.goto(`${BASE}/index.php?p=catalogue&statut=brouillon`, { waitUntil: 'domcontentloaded' });
  const hrefDecor = await pau.locator('a[href*="p=modifier"]').first().getAttribute('href') ?? '';
  const idDecor = decodeURIComponent(/[?&]id=([^&]+)/.exec(hrefDecor)?.[1] ?? '');
  await pau.goto(`${BASE}/index.php?p=modifier&id=${encodeURIComponent(idDecor)}`,
                 { waitUntil: 'domcontentloaded' });
  await pau.fill('#eq-email', GRA.email);
  await pau.locator('button:has-text("Lui confier")').click();
  await pau.waitForLoadState('domcontentloaded');
  // Le message est lu dans l'ADRESSE : l'écran du décor porte déjà des
  // encarts « .msg.ok » qui lui sont propres, et le premier n'est pas
  // celui qu'on vient de provoquer.
  ok('une campagne se confie à quelqu’un',
     decodeURIComponent(pau.url()).replace(/\+/g, ' ').includes('peut maintenant travailler'),
     decodeURIComponent(pau.url()).split('ok=')[1]?.slice(0, 50) ?? pau.url().slice(-50));

  await pgra.goto(`${BASE}/index.php?p=partenaire&t=${Date.now()}`, { waitUntil: 'domcontentloaded' });
  // On cherche le LIEN vers la campagne confiée, pas une phrase : c'est ce
  // qui prouve qu'elle est atteignable depuis chez lui.
  ok('elle apparaît chez l’invité, à part de ses propres campagnes',
     (await pgra.locator(`a[href*="p=modifier"][href*="${idDecor}"]`).count()) === 1,
     `${await pgra.locator('a[href*="p=modifier"]').count()} lien(s) vers une campagne`);
  await pgra.goto(`${BASE}/index.php?p=modifier&id=${encodeURIComponent(idDecor)}`,
                  { waitUntil: 'domcontentloaded' });
  ok('l’invité peut l’ouvrir', pgra.url().includes('p=modifier'),
     pgra.url().split('index.php')[1]?.slice(0, 40) ?? '');
  ok('mais il ne peut pas inviter à son tour',
     (await pgra.locator('#eq-email').count()) === 0);
  for (const interdit of ['comptes', 'catalogue', 'journal', 'reglages']) {
    await pgra.goto(`${BASE}/index.php?p=${interdit}`, { waitUntil: 'domcontentloaded' });
    ok(`et ${interdit} lui reste fermé`, !pgra.url().includes('p=' + interdit),
       pgra.url().split('index.php')[1]?.split('&')[0] ?? '');
  }
  await ctxGra.close();
  await ctxInt.close();
  await ctxCli.close();

  /* --- la recherche dans le blog public --- */
  const ctxBlog = await browser.newContext();
  const pblog = await ctxBlog.newPage();
  surveiller(pblog);
  await pblog.goto(`${BASE}/index.php?p=blog&q=badge`, { waitUntil: 'domcontentloaded' });
  ok('le blog public se cherche',
     (await pblog.locator('main').innerText()).includes('pour « badge »'));
  await pblog.goto(`${BASE}/index.php?p=blog&q=zzz-introuvable-${marque}`,
                   { waitUntil: 'domcontentloaded' });
  ok('et dit quand il ne trouve rien',
     (await pblog.locator('main').innerText()).includes('Aucun article ne parle'));
  await ctxBlog.close();

  /* --- le cache d'images se vide --- */
  await pau.goto(`${BASE}/index.php?p=reglages`, { waitUntil: 'domcontentloaded' });
  await pau.locator('button[value=images]').click();
  await pau.waitForLoadState('domcontentloaded');
  ok('la maintenance rend toujours un bilan',
     /image\(s\) traitée\(s\)|déjà optimisées/.test(
       await pau.locator('.msg').first().innerText().catch(() => '')));

  /* --- une sauvegarde s'inspecte avant d'être restaurée --- */
  await pau.goto(`${BASE}/index.php?p=sauvegardes`, { waitUntil: 'domcontentloaded' });
  await pau.locator('button:has-text("Créer une sauvegarde")').click();
  await pau.waitForLoadState('domcontentloaded');
  await pau.locator('button:has-text("Restaurer…")').first().click();
  await pau.waitForLoadState('domcontentloaded');
  const inspection = (await pau.locator('main').innerText()).replace(/\s+/g, ' ');
  ok('l’écran montre ce que l’archive contient avant d’y toucher',
     /Comptes dans l’archive/.test(inspection) && /Recopiez le nom du fichier/.test(inspection),
     (/Comptes dans l’archive \d+/.exec(inspection) ?? [''])[0]);
  /**
   * On ne restaure PAS pour de vrai ici : cela remplacerait la base sous
   * les pieds de la recette. Le cycle complet — abîmer, restaurer,
   * retrouver — est éprouvé par `verifier-restauration.ts`, sur une
   * installation isolée.
   */
  const refusResto = await pau.request.post(`${BASE}/index.php?p=restaurer`, {
    form: {
      csrf: await pau.locator('input[name=csrf]').first().inputValue(),
      f: await pau.locator('input[name=f]').first().inputValue(),
      quoi: 'restaurer', confirmation: 'pas-le-bon-nom',
    },
  });
  ok('un nom mal recopié annule la restauration',
     /Restauration annulée/.test(await refusResto.text()));

  await pau.close();

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
  /**
   * `recus` est un journal PARTAGÉ par toute la recette.
   *
   * Le compter en absolu supposait que cette section soit la seule à
   * envoyer. Depuis la régie, elle ne l'est plus — et un test qui casse
   * parce qu'une AUTRE section a fait son travail ne dit rien sur celle-ci.
   */
  const avantEssai = recus.length;
  await pe.click('button[value=essai]');
  await pe.waitForLoadState('domcontentloaded');
  ok('l’essai d’envoi aboutit',
     /Message remis au serveur SMTP/.test(await pe.locator('.msg').first().innerText()),
     (await pe.locator('.msg').first().innerText()).replace(/\s+/g, ' ').slice(0, 70));
  ok('le serveur a bien reçu l’essai', recus.length === avantEssai + 1,
     `${recus.length - avantEssai} message(s)`);
  const essai = recus[recus.length - 1] ?? '';
  ok('l’essai est adressé à la bonne personne', essai.includes('<' + ADMIN.email + '>'));
  ok('le sujet accentué voyage encodé', /^Subject: =\?UTF-8\?B\?/m.test(essai));

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
  ok('le lien de confirmation part à l’inscription', recus.length === avantEssai + 2,
     `${recus.length - avantEssai - 1} message(s) depuis l’essai`);
  const messageVerif = recus[recus.length - 1] ?? '';
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

  /**
   * Le lien reçu, cliqué — puis recliqué.
   *
   * Il est volontairement REJOUABLE : un lien de courriel n'est presque
   * jamais ouvert une seule fois (les filtres anti-hameçonnage le suivent
   * avant la personne, Android le précharge, un pouce tape deux fois).
   * Tant qu'il mourait au premier appel, la personne trouvait « ce lien
   * n'a pas fonctionné » à son PREMIER clic, sur une adresse pourtant
   * confirmée. Le second appel doit donc annoncer une réussite.
   */
  const lienBrut = messageVerif.match(/https?:\/\/\S*p=verifier[^\s"<]*/)?.[0]?.replace(/&amp;/g, '&') ?? '';
  ok('le message porte bien un lien de confirmation', lienBrut !== '', lienBrut.slice(0, 60));
  await pv.goto(lienBrut, { waitUntil: 'domcontentloaded' });
  ok('le lien confirme l’adresse', /Adresse confirmée/.test(await pv.locator('h1').first().innerText()));
  await pv.goto(lienBrut, { waitUntil: 'domcontentloaded' });
  ok('rouvrir le même lien reste une réussite, pas une panne',
     /déjà confirmée/.test(await pv.locator('h1').first().innerText()),
     (await pv.locator('h1').first().innerText()).replace(/\s+/g, ' '));

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

  /**
   * Le mot de passe oublié — ici, parce qu'il lui faut un vrai transport.
   *
   * C'était le manque le plus coûteux du produit : sans cet écran, celui
   * qui perd son mot de passe écrit à l'équipe, un samedi soir.
   */
  const ctxOubli = await browser.newContext();
  const poubli = await ctxOubli.newPage();
  surveiller(po);
  const avantOubli = recus.length;
  await poubli.goto(`${BASE}/index.php?p=oubli`, { waitUntil: 'domcontentloaded' });
  await poubli.fill('input[name=email]', NEUF.email);
  await poubli.click('main button[type=submit]');
  await poubli.waitForLoadState('domcontentloaded');
  ok('la demande répond la même chose quoi qu’il arrive',
     (await poubli.locator('main').innerText()).includes('Si un compte existe à cette adresse'));
  ok('et le message part', recus.length === avantOubli + 1,
     `${recus.length - avantOubli} message(s)`);

  const messageOubli = recus[recus.length - 1] ?? '';
  const lienOubli = /(https?:\/\/\S*p=reinitialiser&j=[0-9a-f]+)/.exec(
    messageOubli.replace(/=\r?\n/g, ''))?.[1] ?? '';
  ok('il porte un lien de réinitialisation', lienOubli !== '', lienOubli.slice(0, 62));

  /* Une adresse inconnue reçoit la même réponse, et aucun message. */
  const avantInconnue = recus.length;
  await poubli.goto(`${BASE}/index.php?p=oubli`, { waitUntil: 'domcontentloaded' });
  await poubli.fill('input[name=email]', `personne-${marque}@exemple.tg`);
  await poubli.click('main button[type=submit]');
  await poubli.waitForLoadState('domcontentloaded');
  ok('une adresse inconnue reçoit la même réponse',
     (await poubli.locator('main').innerText()).includes('Si un compte existe à cette adresse'));
  ok('mais aucun message ne part', recus.length === avantInconnue,
     `${recus.length - avantInconnue} message(s)`);

  /* Le lien, et le nouveau mot de passe. */
  const MDP_NEUF = 'un-mot-de-passe-tout-neuf-2026';
  await poubli.goto(lienOubli, { waitUntil: 'domcontentloaded' });
  ok('le lien ouvre le formulaire du nouveau mot de passe',
     (await poubli.locator('input[name=mot_de_passe]').count()) === 1);
  await poubli.fill('input[name=mot_de_passe]', MDP_NEUF);
  await poubli.fill('input[name=confirmation]', 'pas-le-meme-2026');
  await poubli.click('main button[type=submit]');
  await poubli.waitForLoadState('domcontentloaded');
  ok('deux mots de passe différents sont refusés',
     (await poubli.locator('.msg.err').first().innerText().catch(() => '')).includes('identiques'));

  await poubli.fill('input[name=mot_de_passe]', MDP_NEUF);
  await poubli.fill('input[name=confirmation]', MDP_NEUF);
  await poubli.click('main button[type=submit]');
  await poubli.waitForLoadState('domcontentloaded');
  ok('le mot de passe est enregistré',
     poubli.url().includes('p=connexion')
     && decodeURIComponent(poubli.url()).replace(/\+/g, ' ').includes('Mot de passe enregistré'));

  await poubli.goto(lienOubli, { waitUntil: 'domcontentloaded' });
  ok('le lien ne sert qu’une fois',
     (await poubli.locator('main').innerText()).includes('Ce lien ne fonctionne plus'));

  await connexion(poubli, NEUF.email, MDP_NEUF);
  ok('on se connecte avec le nouveau', !poubli.url().includes('p=connexion'),
     poubli.url().split('index.php')[1] ?? '');
  await ctxOubli.close();

  /* ================================================================== */
  console.log('\n━━ 37. Le carnet d’adresses ━━');

  /**
   * Ce que vérifie cette section : qu'une liste collée SURVIT à la
   * campagne, et qu'on peut ensuite la corriger.
   *
   * C'est toute la différence entre un champ texte et un carnet. Les
   * assertions portent donc moins sur les écrans que sur le nombre de
   * destinataires après chaque geste — archiver, sortir de la liste,
   * corriger une adresse — parce que c'est la seule chose que l'utilisateur
   * constatera vraiment, le jour où il enverra.
   */
  const pca = await browser.newPage();
  surveiller(pca);
  await connexion(pca, ADMIN.email, ADMIN.mdp);

  const LISTE = `Invités du Gala ${marque}`;
  const COLLE = [
    `ada-${marque}@exemple.tg`,
    `Kossi Mensah <kossi-${marque}@exemple.tg>`,
    `"Afi Doe" <afi-${marque}@exemple.tg>`,
    `yao-${marque}@exemple.tg (Yao Adjo)`,
    `ADA-${marque}@EXEMPLE.TG`,
    'ceci n’est pas une adresse',
  ].join('\n');

  /**
   * On y entre PAR la régie, et non par le menu.
   *
   * Le menu tient en trois groupes de quatre, et cette règle vaut plus que
   * le confort d'une entrée de plus : le carnet est une face de la régie —
   * là où elle range à qui elle écrit — pas une treizième destination.
   */
  await pca.goto(`${BASE}/index.php?p=regie`, { waitUntil: 'domcontentloaded' });
  await pca.locator('a[href*="p=regie-carnet"]').first().click();
  await pca.waitForLoadState('domcontentloaded');
  ok('le carnet s’ouvre depuis la régie', pca.url().includes('p=regie-carnet')
     && (await pca.locator('h1').innerText()).includes('Carnet'));
  ok('la régie reste le repère du menu pendant qu’on range le carnet',
     (await pca.locator('.barre nav a[aria-current="page"][href*="p=regie"]').count()) === 1);

  // --- créer une liste ---
  await pca.locator('button[data-ouvre="bloc-liste-neuve"]').click();
  await pca.fill('#ln-nom', LISTE);
  await pca.locator('#bloc-liste-neuve button[type=submit]').click();
  await pca.waitForLoadState('domcontentloaded');
  ok('une liste se crée et s’ouvre',
     (await pca.locator('main').innerText()).includes(LISTE), LISTE);

  // --- importer un collage réaliste ---
  await pca.locator('button[data-ouvre="bloc-import"]').first().click();
  await pca.fill('#im-adresses', COLLE);
  await pca.locator('#bloc-import button[type=submit]').click();
  await pca.waitForLoadState('domcontentloaded');
  const bilanImport = await pca.locator('.msg.ok').first().innerText().catch(() => '');
  ok('l’import annonce ce qu’il a compris', /4 adresse\(s\) enregistrée\(s\)/.test(bilanImport),
     bilanImport.replace(/\s+/g, ' ').slice(0, 80));
  ok('il signale la ligne illisible plutôt que de la taire',
     /illisible/.test(bilanImport));
  ok('la même adresse en majuscules n’est pas comptée deux fois',
     (await pca.locator('table tbody tr').count()) === 4,
     `${await pca.locator('table tbody tr').count()} ligne(s)`);
  ok('« Nom <adresse> » a rempli le nom',
     (await pca.locator('table tbody').innerText()).includes('Kossi Mensah'));
  ok('« adresse (Nom) » aussi',
     (await pca.locator('table tbody').innerText()).includes('Yao Adjo'));

  // --- ré-importer le même collage n'ajoute rien ---
  await pca.locator('button[data-ouvre="bloc-import"]').first().click();
  await pca.fill('#im-adresses', COLLE);
  await pca.locator('#bloc-import button[type=submit]').click();
  await pca.waitForLoadState('domcontentloaded');
  ok('ré-importer la même liste ne fabrique pas de doublons',
     (await pca.locator('table tbody tr').count()) === 4
     && /0 nouvelle\(s\)/.test(await pca.locator('.msg.ok').first().innerText().catch(() => '')));

  const urlListe = pca.url();

  // --- chercher ---
  await pca.fill('input[name=q]', 'Kossi');
  await pca.locator('form.chercher button[type=submit]').click();
  await pca.waitForLoadState('domcontentloaded');
  ok('la recherche filtre sur le nom', (await pca.locator('table tbody tr').count()) === 1,
     `${await pca.locator('table tbody tr').count()} ligne(s)`);

  // --- corriger une fiche ---
  await pca.locator('table tbody a[href*="p=regie-carnet-fiche"]').first().click();
  await pca.waitForLoadState('domcontentloaded');
  ok('la fiche s’ouvre', pca.url().includes('p=regie-carnet-fiche'));
  await pca.fill('#c-nom', 'Kossi Mensah-Adjo');
  await pca.fill('#c-org', 'Maquis Akwaba');
  await pca.locator('form:has(#c-nom) button[type=submit]').click();
  await pca.waitForLoadState('domcontentloaded');
  ok('la correction est enregistrée',
     (await pca.locator('main').innerText()).includes('Kossi Mensah-Adjo'));
  ok('la liste de la fiche est cochée',
     (await pca.locator(`.case:has-text("${LISTE}") input:checked`).count()) === 1);

  // --- archiver ---
  await pca.locator('form:has(input[value="contact-archiver"]) button').click();
  await pca.waitForLoadState('domcontentloaded');
  await pca.goto(urlListe, { waitUntil: 'domcontentloaded' });
  ok('une adresse archivée sort des actives',
     (await pca.locator('table tbody tr').count()) === 3,
     `${await pca.locator('table tbody tr').count()} active(s)`);
  await pca.selectOption('select[name=etat]', 'archives');
  await pca.locator('form.chercher button[type=submit]').click();
  await pca.waitForLoadState('domcontentloaded');
  ok('mais reste au carnet, dans les archivées',
     (await pca.locator('table tbody').innerText()).includes('Kossi Mensah-Adjo'));

  // --- sortir de la liste, sans perdre la fiche ---
  await pca.goto(urlListe, { waitUntil: 'domcontentloaded' });
  await pca.locator('#form-lot input[name="choix[]"]').first().check();
  await pca.locator('#form-lot button[value=retirer]').click();
  await pca.waitForLoadState('domcontentloaded');
  ok('sortir de la liste le dit clairement',
     /sortie\(s\) de la liste/.test(await pca.locator('.msg.ok').first().innerText().catch(() => '')));
  ok('la liste en compte une de moins',
     (await pca.locator('table tbody tr').count()) === 2,
     `${await pca.locator('table tbody tr').count()} restante(s)`);
  await pca.goto(`${BASE}/index.php?p=regie-carnet&q=${marque}&etat=toutes`, { waitUntil: 'domcontentloaded' });
  ok('la fiche sortie est toujours au carnet',
     (await pca.locator('table tbody tr').count()) === 4,
     `${await pca.locator('table tbody tr').count()} au carnet`);

  // --- une campagne qui vise la liste ---
  const idListe = (/[?&]l=([^&]+)/.exec(urlListe) ?? [, ''])[1];
  await pca.goto(`${BASE}/index.php?p=regie-ecrire`, { waitUntil: 'domcontentloaded' });
  await pca.selectOption('#r-cible', 'liste');
  await pca.selectOption('#r-liste-id', idListe);
  await pca.fill('#r-sujet', `Retour au Gala ${marque}`);
  await pca.fill('#r-titre', 'On remet ça');
  await pca.fill('#r-corps', 'Vous étiez au Gala. On recommence samedi, même endroit, même heure.');
  await pca.click('main button[type=submit]');
  await pca.waitForLoadState('domcontentloaded');
  ok('une campagne peut viser une liste du carnet', pca.url().includes('p=regie-campagne'));
  const enTete = await pca.locator('.entete').innerText();
  ok('elle affiche le nom de la liste, pas « une liste »', enTete.includes(LISTE), LISTE);
  ok('elle vise les deux adresses actives restantes', /\b2\b\s*destinataire/.test(enTete),
     enTete.replace(/\s+/g, ' ').slice(0, 90));
  ok('le nom de la liste renvoie au carnet',
     (await pca.locator(`.entete a[href*="p=regie-carnet&l="]`).count()) === 1);

  /**
   * Le scénario qui donne son sens à toute la section : coller des
   * adresses dans une campagne les ENREGISTRE. Avant le carnet, elles
   * disparaissaient avec la campagne.
   */
  await pca.goto(`${BASE}/index.php?p=regie-ecrire`, { waitUntil: 'domcontentloaded' });
  await pca.selectOption('#r-cible', 'liste');
  await pca.selectOption('#r-liste-id', 'nouvelle');
  await pca.fill('#r-liste-nom', `Collée à la volée ${marque}`);
  await pca.fill('#r-liste', `volee1-${marque}@exemple.tg\nVolée Deux <volee2-${marque}@exemple.tg>`);
  await pca.fill('#r-sujet', `Collage direct ${marque}`);
  await pca.fill('#r-titre', 'Un message');
  await pca.fill('#r-corps', 'Ce collage doit finir dans le carnet, et pas seulement dans cette campagne.');
  await pca.click('main button[type=submit]');
  await pca.waitForLoadState('domcontentloaded');
  ok('un collage dans une campagne crée la liste', pca.url().includes('p=regie-campagne')
     && (await pca.locator('.entete').innerText()).includes(`Collée à la volée ${marque}`));

  await pca.goto(`${BASE}/index.php?p=regie-carnet&q=volee`, { waitUntil: 'domcontentloaded' });
  const collees = await pca.locator('table tbody').innerText();
  ok('les adresses collées sont bien AU CARNET, pas seulement dans la campagne',
     collees.includes(`volee1-${marque}@exemple.tg`) && collees.includes(`volee2-${marque}@exemple.tg`));
  ok('le nom donné dans le collage a été gardé', collees.includes('Volée Deux'));

  // --- reprendre la campagne ne re-demande pas le collage ---
  await pca.goto(`${BASE}/index.php?p=regie-carnet`, { waitUntil: 'domcontentloaded' });
  ok('la nouvelle liste apparaît parmi les étiquettes',
     (await pca.locator('.etiquettes').innerText()).includes(`Collée à la volée ${marque}`));

  // --- alimenter depuis une audience calculée ---
  await pca.goto(urlListe, { waitUntil: 'domcontentloaded' });
  await pca.locator('button[data-ouvre="bloc-alimenter"]').click();
  await pca.selectOption('#al-cible', 'participants');
  await pca.locator('#bloc-alimenter button[type=submit]').click();
  await pca.waitForLoadState('domcontentloaded');
  ok('une audience calculée se fige dans une liste, où elle devient corrigeable',
     /adresse\(s\) reprises/.test(await pca.locator('.msg.ok').first().innerText().catch(() => '')),
     (await pca.locator('.msg.ok').first().innerText().catch(() => '')).replace(/\s+/g, ' ').slice(0, 70));

  // --- l'export ---
  const csv = await pca.request.get(`${BASE}/index.php?p=regie-carnet-export&l=${idListe}`);
  const texteCsv = await csv.text();
  ok('le carnet s’exporte en CSV', csv.ok()
     && (csv.headers()['content-type'] ?? '').includes('text/csv'),
     csv.headers()['content-type'] ?? `HTTP ${csv.status()}`);
  ok('l’export porte un BOM, sans quoi Excel abîme les accents',
     texteCsv.charCodeAt(0) === 0xfeff);
  ok('il contient les adresses de la liste', texteCsv.includes(marque),
     `${texteCsv.split('\n').length - 1} ligne(s)`);

  // --- une liste qui n'est pas la mienne ---
  await pca.goto(`${BASE}/index.php?p=regie-carnet&l=00000000-0000-4000-8000-000000000000`,
                 { waitUntil: 'domcontentloaded' });
  ok('une liste inconnue ne fait pas tomber l’écran',
     (await pca.locator('h1').innerText()).includes('Carnet'));

  // --- supprimer la liste rend les fiches au carnet ---
  await pca.goto(urlListe, { waitUntil: 'domcontentloaded' });
  pca.once('dialog', (d) => void d.accept());
  await pca.locator('form:has(input[value="liste-supprimer"]) button').click();
  await pca.waitForLoadState('domcontentloaded');
  ok('supprimer une liste ne supprime pas les gens',
     /restent au carnet/.test(await pca.locator('.msg.ok').first().innerText().catch(() => '')),
     (await pca.locator('.msg.ok').first().innerText().catch(() => '')).replace(/\s+/g, ' ').slice(0, 70));
  await pca.goto(`${BASE}/index.php?p=regie-carnet&q=${marque}&etat=toutes`, { waitUntil: 'domcontentloaded' });
  ok('les fiches de la liste supprimée sont toujours là',
     (await pca.locator('table tbody tr').count()) >= 4,
     `${await pca.locator('table tbody tr').count()} fiche(s)`);

  /**
   * Et le carnet d'un organisateur n'est pas celui de l'équipe.
   *
   * C'est la cloison qui rend le carnet utilisable : un organisateur y met
   * des clients à lui, et le fait sans se demander qui d'autre les verra.
   */
  const ppa = await browser.newPage();
  surveiller(ppa);
  await connexion(ppa, PART.email, PART.mdp);
  await ppa.goto(`${BASE}/index.php?p=regie`, { waitUntil: 'domcontentloaded' });
  ok('la régie d’un organisateur mène à son carnet',
     (await ppa.locator('a[href*="p=regie-carnet"]').count()) >= 1);
  await ppa.goto(`${BASE}/index.php?p=regie-carnet`, { waitUntil: 'domcontentloaded' });
  ok('un organisateur atteint son propre carnet', ppa.url().includes('p=regie-carnet'));
  ok('son carnet ne contient pas celui de l’équipe',
     !(await ppa.locator('main').innerText()).includes(`afi-${marque}@exemple.tg`));

  await ppa.close();
  await pca.close();

  /* ================================================================== */
  console.log('\n━━ 38. L’équipe, les clients, et ce qui part par courriel ━━');

  /**
   * Trois défauts d'un seul écran, et ils se tenaient.
   *
   * Un compte de l'équipe recevait une offre commerciale ; un compte créé
   * par l'équipe n'avait aucun moyen de confirmer son adresse ; et le seul
   * bouton à portée — celui du rôle — expédiait « Votre offre est
   * maintenant Découverte » à quelqu'un qui n'a jamais rien acheté. Cette
   * section les éprouve ensemble, parce qu'ils se réparent ensemble.
   *
   * Le transport SMTP est rallumé le temps de la section : sans lui, on ne
   * vérifierait que des écrans, et c'est justement le COURRIEL qui était
   * faux.
   */
  const pcx = await browser.newPage();
  surveiller(pcx);
  await connexion(pcx, ADMIN.email, ADMIN.mdp);

  /**
   * On RÉUTILISE le serveur SMTP de la section 22, encore debout.
   *
   * En ouvrir un second sur le même port fait tomber la recette entière —
   * et un second port serait un deuxième bac de réception à surveiller
   * pour rien. On se contente de rebrancher les réglages dessus, et de
   * noter où en est le bac partagé avant chaque geste.
   */
  const recusCx = recus;
  await pcx.goto(`${BASE}/index.php?p=reglages`, { waitUntil: 'domcontentloaded' });
  await pcx.fill('#smtp_hote', '127.0.0.1');
  await pcx.fill('#smtp_port', String(SMTP_PORT));
  await pcx.selectOption('#smtp_securite', 'aucune');
  await pcx.fill('#courriel_expediteur', 'boost@wakabileguide.com');
  await pcx.click('button[value=enregistrer]');
  await pcx.waitForLoadState('domcontentloaded');

  await pcx.goto(`${BASE}/index.php?p=comptes`, { waitUntil: 'domcontentloaded' });
  ok('les deux familles ont chacune leur formulaire',
     (await pcx.locator('details.creer').count()) === 2
     && (await pcx.locator('form[action*="creer-equipier"]').count()) === 1);
  ok('celui de l’équipe ne propose AUCUNE offre',
     (await pcx.locator('form[action*="creer-equipier"] select[name=formule]').count()) === 0);
  ok('celui des clients ne propose aucun rôle interne',
     (await pcx.locator('#c-role option').evaluateAll(
       (n) => n.map((e) => (e as HTMLOptionElement).value)))
       .every((r) => ['participant', 'partenaire'].includes(r)));

  /* --- créer un équipier : pas d'offre, et un lien de confirmation --- */
  const EQUIPIER = { email: `coord-${marque}@wakabileguide.com`, mdp: 'coordinateur-2026-solide' };
  const avantEq = recusCx.length;
  await pcx.locator('details.creer').nth(1).locator('summary').click();
  await pcx.fill('#e-nom', `Coordinatrice ${marque}`);
  await pcx.fill('#e-email', EQUIPIER.email);
  await pcx.selectOption('#e-role', 'coordinateur');
  await pcx.fill('#e-mdp', EQUIPIER.mdp);
  await pcx.click('form[action*="creer-equipier"] button[type=submit]');
  await pcx.waitForLoadState('domcontentloaded');
  const creeEqui = await pcx.locator('.msg.ok').first().innerText().catch(() => '');
  ok('un compte d’équipe se crée sans offre',
     /Compte créé/.test(creeEqui) && /compte de l’équipe/.test(creeEqui)
     && !/offre/i.test(creeEqui.replace(/compte de l’équipe/, '')),
     creeEqui.replace(/\s+/g, ' ').slice(0, 90));
  ok('et le message annonce le lien de confirmation',
     /lien de confirmation vient de partir/.test(creeEqui));

  const messageEq = recusCx.slice(avantEq).join('\n');
  ok('un VRAI serveur SMTP a reçu le lien de confirmation',
     recusCx.length > avantEq && /Confirmez votre adresse/i.test(objets(messageEq)),
     objets(messageEq).slice(0, 60) || `${recusCx.length - avantEq} message(s)`);
  ok('ce message parle de confirmation, PAS d’offre',
     !/offre/i.test(objets(messageEq)) && /p=verifier&j=/.test(messageEq),
     objets(messageEq).slice(0, 60));

  /* --- la liste dit « sans offre », et ne propose pas d'en donner une --- */
  await pcx.goto(`${BASE}/index.php?p=comptes`, { waitUntil: 'domcontentloaded' });
  const ligneEq = pcx.locator(`tr:has-text("Coordinatrice ${marque}")`).first();
  ok('la ligne d’un équipier n’a pas de déroulant d’offre',
     (await ligneEq.locator('select[name=formule]').count()) === 0
     && (await ligneEq.innerText()).includes('sans offre'));

  /* --- cliquer « OK » sans rien changer n'envoie RIEN --- */
  const avantOk = recusCx.length;
  await ligneEq.locator('button[type=submit]:has-text("OK")').click();
  await pcx.waitForLoadState('domcontentloaded');
  ok('« OK » sans changement ne change rien et ne prévient personne',
     /Rien n’a changé/.test(await pcx.locator('.msg.ok').first().innerText().catch(() => ''))
     && recusCx.length === avantOk,
     `${recusCx.length - avantOk} message(s) partis`);

  /* --- changer le rôle d'un équipier parle de RÔLE, pas d'offre --- */
  const avantRole = recusCx.length;
  await pcx.goto(`${BASE}/index.php?p=comptes`, { waitUntil: 'domcontentloaded' });
  const ligneEq2 = pcx.locator(`tr:has-text("Coordinatrice ${marque}")`).first();
  await ligneEq2.locator('select[name=role]').selectOption('editeur');
  await ligneEq2.locator('button[type=submit]:has-text("OK")').click();
  await pcx.waitForLoadState('domcontentloaded');
  const surRole = recusCx.slice(avantRole).join('\n');
  ok('changer le rôle d’un équipier lui écrit à propos de son RÔLE',
     /Votre rôle est maintenant/.test(objets(surRole)), objets(surRole).slice(0, 70));
  ok('et jamais à propos d’une offre qu’il n’a pas',
     !/offre/i.test(objets(surRole)), objets(surRole).slice(0, 70));

  /* --- l'offre reste vide en base, quoi qu'on poste --- */
  const csrfCx = await pcx.locator('.formulaire-compte input[name=csrf]').first().inputValue();
  const idEq = await pcx.locator(`tr:has-text("Coordinatrice ${marque}") input[name=id]`)
    .first().inputValue();
  await pcx.request.post(`${BASE}/index.php?p=role`, {
    form: { csrf: csrfCx, id: idEq, role: 'coordinateur', formule: 'mouvement' },
  });
  await pcx.goto(`${BASE}/index.php?p=organisateur&id=${encodeURIComponent(idEq)}`,
                 { waitUntil: 'domcontentloaded' });
  // Les pastilles d'en-tête, et elles seules : le reste de la fiche nomme
  // les offres pour d'autres raisons — le comparatif des lignes, par exemple.
  const pastilles = await pcx.locator('.pastilles-compte .pastille')
    .evaluateAll((n) => n.map((e) => (e.textContent ?? '').trim()));
  ok('un formulaire trafiqué ne colle pas une offre payante à un équipier',
     pastilles.includes('Compte de la maison')
     && !pastilles.some((t) => /Mouvement|Croissance|Impact|Découverte/.test(t)),
     pastilles.join(' · '));
  ok('et il n’a donc pas de carte d’abonnement',
     (await pcx.locator('main .carte:has-text("Abonnement")').count()) === 0);

  /* --- le lien de confirmation se renvoie depuis l'écran de l'équipe --- */
  const avantRenvoi = recusCx.length;
  await pcx.locator('form[action*="verif-renvoyer"] button').first().click();
  await pcx.waitForLoadState('domcontentloaded');
  ok('l’équipe peut renvoyer le lien de confirmation d’un compte',
     /Lien de confirmation renvoyé/.test(await pcx.locator('.msg.ok').first().innerText().catch(() => ''))
     && recusCx.length > avantRenvoi,
     `${recusCx.length - avantRenvoi} message(s)`);

  /**
   * Et le lien reçu confirme VRAIMENT l'adresse.
   *
   * C'est le seul test qui prouve la réparation : jusqu'ici, l'équipe
   * regardait « adresse non confirmée » sans aucun geste pour y répondre.
   */
  const lienVerif = /(https?:\/\/\S*p=verifier&(?:amp;)?j=[0-9a-f]{48})/
    .exec(recusCx.slice(avantRenvoi).join('\n'));
  ok('le message porte un lien de confirmation utilisable', lienVerif !== null);
  if (lienVerif) {
    const url = lienVerif[1].replace(/&amp;/g, '&');
    const ctxV = await browser.newContext();
    const pv2 = await ctxV.newPage();
    surveiller(pv2);

    /**
     * Le lien est ouvert DEUX fois, comme dans la vraie vie.
     *
     * Le premier appel imite le filtre anti-hameçonnage de la messagerie,
     * qui suit les liens avant de livrer le message ; le second est celui
     * de la personne. Tant que le jeton mourait au premier appel, elle
     * trouvait « ce lien n’a pas fonctionné » à son PREMIER clic, sur une
     * adresse pourtant confirmée.
     */
    const prefetch = await pv2.request.get(url);
    ok('un filtre de messagerie peut suivre le lien sans le casser', prefetch.ok(),
       `HTTP ${prefetch.status()}`);

    await pv2.goto(url, { waitUntil: 'domcontentloaded' });
    const vu = await pv2.locator('main').innerText();
    ok('la personne qui clique ENSUITE voit une réussite, pas une panne',
       /déjà confirmée|Adresse confirmée/i.test(await pv2.locator('h1').innerText()),
       (await pv2.locator('h1').innerText()).replace(/\s+/g, ' '));
    ok('et l’écran explique pourquoi, sans jargon',
       /filtres|messageries/i.test(vu));

    await pv2.reload({ waitUntil: 'domcontentloaded' });
    ok('un rafraîchissement de plus ne casse rien',
       /déjà confirmée|Adresse confirmée/i.test(await pv2.locator('h1').innerText()));

    /* Un lien inventé, lui, reste refusé — et propose une sortie. */
    await pv2.goto(`${BASE}/index.php?p=verifier&j=${'a'.repeat(48)}`,
                   { waitUntil: 'domcontentloaded' });
    ok('un lien inventé est refusé', /n’a pas fonctionné/.test(await pv2.locator('h1').innerText()));
    ok('mais la page n’est plus un mur : elle demande l’adresse',
       (await pv2.locator('form[action*="verif-demander"] input[name=email]').count()) === 1);

    await pv2.fill('#v-email', 'personne-inconnue@exemple.tg');
    await pv2.click('form[action*="verif-demander"] button[type=submit]');
    await pv2.waitForLoadState('domcontentloaded');
    ok('la réponse est la même pour une adresse inconnue — pas un annuaire',
       /Si un compte existe à cette adresse/.test(await pv2.locator('.msg').first().innerText()),
       (await pv2.locator('.msg').first().innerText()).replace(/\s+/g, ' ').slice(0, 62));

    await ctxV.close();
    await pcx.goto(`${BASE}/index.php?p=organisateur&id=${encodeURIComponent(idEq)}`,
                   { waitUntil: 'domcontentloaded' });
    ok('et la fiche dit que l’adresse est confirmée',
       (await pcx.locator('main').innerText()).includes('Adresse confirmée'));
  }

  /* --- un client, lui, garde bien son offre et son courriel d'offre --- */
  const avantCli = recusCx.length;
  await pcx.goto(`${BASE}/index.php?p=comptes`, { waitUntil: 'domcontentloaded' });
  await pcx.locator('details.creer').first().locator('summary').click();
  await pcx.fill('#c-nom', `Cliente ${marque}`);
  await pcx.fill('#c-email', `separee-${marque}@exemple.tg`);
  await pcx.selectOption('#c-role', 'partenaire');
  await pcx.selectOption('#c-formule', 'impact');
  await pcx.fill('#c-mdp', 'cliente-2026-solide');
  await pcx.click('form[action*="creer-compte"] button[type=submit]');
  await pcx.waitForLoadState('domcontentloaded');
  ok('un client garde son offre à la création',
     /offre Impact/.test(await pcx.locator('.msg.ok').first().innerText().catch(() => '')));
  ok('et reçoit lui aussi son lien de confirmation',
     /Confirmez votre adresse/i.test(objets(recusCx.slice(avantCli).join('\n'))),
     objets(recusCx.slice(avantCli).join('\n')).slice(0, 60));

  const ligneCli = pcx.locator(`tr:has-text("Cliente ${marque}")`).first();
  const avantOffre = recusCx.length;
  await ligneCli.locator('select[name=formule]').selectOption('croissance');
  await ligneCli.locator('button[type=submit]:has-text("OK")').click();
  await pcx.waitForLoadState('domcontentloaded');
  ok('changer l’offre d’un client lui écrit bien à propos de son offre',
     /Votre offre est maintenant/.test(objets(recusCx.slice(avantOffre).join('\n'))),
     objets(recusCx.slice(avantOffre).join('\n')).slice(0, 60));

  /**
   * Un invité n'achète rien non plus.
   *
   * Une offre découpe ce qu'un ORGANISATEUR produit : campagnes, badges
   * par mois, filigrane, Koris, redirection. Toutes ces lignes sont lues
   * sur l'auteur du décor — jamais sur celui qui télécharge. Le quota d'un
   * participant n'avait donc aucun endroit où mordre, et la valeur stockée
   * ne servait qu'à lui promettre un abonnement qui n'existe pas.
   */
  await pcx.goto(`${BASE}/index.php?p=comptes`, { waitUntil: 'domcontentloaded' });
  await pcx.locator('details.creer').first().locator('summary').click();
  await pcx.selectOption('#c-role', 'participant');
  ok('choisir « Participant » retire le champ Offre',
     await pcx.locator('#c-bloc-offre').isHidden());
  await pcx.selectOption('#c-role', 'partenaire');
  ok('et choisir « Organisateur » le remet',
     await pcx.locator('#c-bloc-offre').isVisible());

  const INVITE = { email: `invite-${marque}@exemple.tg`, mdp: 'invite-2026-solide' };
  await pcx.selectOption('#c-role', 'participant');
  await pcx.fill('#c-nom', `Invité ${marque}`);
  await pcx.fill('#c-email', INVITE.email);
  await pcx.fill('#c-mdp', INVITE.mdp);
  await pcx.click('form[action*="creer-compte"] button[type=submit]');
  await pcx.waitForLoadState('domcontentloaded');
  const creeInv = await pcx.locator('.msg.ok').first().innerText().catch(() => '');
  ok('un participant se crée sans offre',
     /Compte créé/.test(creeInv) && /sans offre/.test(creeInv),
     creeInv.replace(/\s+/g, ' ').slice(0, 80));

  await pcx.goto(`${BASE}/index.php?p=comptes&q=${encodeURIComponent(INVITE.email)}`,
                 { waitUntil: 'domcontentloaded' });
  const ligneInv = pcx.locator(`tr:has-text("Invité ${marque}")`).first();
  ok('sa ligne ne propose pas de lui vendre une offre',
     (await ligneInv.locator('select[name=formule]').count()) === 0
     && (await ligneInv.innerText()).includes('sans offre'));

  /* Et un formulaire trafiqué ne lui en colle pas une. */
  const csrfInv = await pcx.locator('.formulaire-compte input[name=csrf]').first().inputValue();
  const idInv = await ligneInv.locator('input[name=id]').first().inputValue();
  const avantInv = recusCx.length;
  await pcx.request.post(`${BASE}/index.php?p=role`, {
    form: { csrf: csrfInv, id: idInv, role: 'participant', formule: 'mouvement' },
  });
  await pcx.goto(`${BASE}/index.php?p=organisateur&id=${encodeURIComponent(idInv)}`,
                 { waitUntil: 'domcontentloaded' });
  const pastillesInv = await pcx.locator('.pastilles-compte .pastille')
    .evaluateAll((n) => n.map((e) => (e.textContent ?? '').trim()));
  ok('un formulaire trafiqué ne colle pas une offre payante à un invité',
     !pastillesInv.some((t) => /Mouvement|Croissance|Impact|Découverte/.test(t)),
     pastillesInv.join(' · '));
  ok('et rien ne part par courriel à ce sujet',
     !/Votre offre est maintenant/.test(objets(recusCx.slice(avantInv).join('\n'))));

  /* ---------------- l'historique des notifications ---------------- */

  await pcx.goto(`${BASE}/index.php?p=diffusion`, { waitUntil: 'domcontentloaded' });
  ok('l’écran des notifications porte un historique',
     (await pcx.locator('main').innerText()).includes('Ce qui est déjà parti'));
  const avantDiff = await pcx.locator('.carte:has-text("Ce qui est déjà parti") table tbody tr').count();

  const TITRE_D = `Historique ${marque}`;
  await pcx.fill('#d-titre', TITRE_D);
  await pcx.fill('#d-corps', 'Un envoi de recette, pour vérifier qu’il laisse une trace.');
  const envoyer = pcx.locator('form button[type=submit]:not([disabled])').last();
  if (await envoyer.count()) {
    await envoyer.click();
    await pcx.waitForLoadState('domcontentloaded');
  }
  const bilanD = await pcx.locator('.msg').first().innerText().catch(() => '');
  ok('le compte rendu d’envoi distingue les personnes des remises',
     /personne\(s\)/.test(bilanD), bilanD.replace(/\s+/g, ' ').slice(0, 90));

  const tableD = pcx.locator('.carte:has-text("Ce qui est déjà parti") table tbody');
  ok('l’envoi laisse une ligne dans l’historique',
     (await tableD.locator('tr').count()) === avantDiff + 1
     && (await tableD.innerText()).includes(TITRE_D),
     `${await tableD.locator('tr').count()} ligne(s)`);
  const premiere = await tableD.locator('tr').first().innerText();
  ok('elle porte la date et l’heure de l’envoi',
     /\d{2}\/\d{2}\/\d{4}/.test(premiere) && /\d{2}:\d{2} UTC/.test(premiere),
     premiere.split('\n').slice(0, 2).join(' '));
  ok('elle porte le segment visé et le nombre d’abonnements',
     /abonnement/.test(premiere), premiere.replace(/\s+/g, ' ').slice(0, 80));
  ok('l’écran dit ce que « personnes » veut dire, et ce qu’il ne dit pas',
     (await pcx.locator('.carte:has-text("Ce qui est déjà parti")').innerText())
       .includes('ne dit que la'));

  /**
   * Un organisateur ne voit QUE ses envois.
   *
   * Lui montrer ceux du guide lui apprendrait la taille et le rythme d'une
   * audience qu'il n'a pas — la même règle que pour les segments.
   */
  const porg = await browser.newPage();
  surveiller(porg);
  await connexion(porg, PART.email, PART.mdp);
  await porg.goto(`${BASE}/index.php?p=diffusion`, { waitUntil: 'domcontentloaded' });
  ok('l’historique d’un organisateur ne contient pas celui du guide',
     !(await porg.locator('main').innerText()).includes(TITRE_D));
  await porg.close();

  // Le transport reste branché : la remise à zéro qui suit l'éteint, et le
  // serveur appartient à la section 22, qui le fermera elle-même.
  await pcx.close();

  /* ================================================================== */
  console.log('\n━━ 39. Un éditeur n’ouvre QUE ce qui le concerne ━━');

  /**
   * Le balayage est exhaustif PAR CONSTRUCTION.
   *
   * Les routes sont lues dans `index.php` lui-même, et l'on compare
   * l'ensemble atteint à une liste attendue, écrite ici. Une liste de
   * pages à essayer, tapée à la main, manquerait justement la route qu'on
   * vient d'ajouter — c'est-à-dire la seule que personne n'a encore
   * regardée. Ici, ajouter une route sans se prononcer fait tomber la
   * recette : quelqu'un DOIT décider si un éditeur y a droit.
   *
   * Ce qu'un éditeur est : il écrit décors et articles, les soumet, et
   * range ses liens. Il ne publie pas, ne scanne pas, ne touche ni aux
   * comptes ni aux réglages — et n'a ni régie, ni notifications push, ni
   * clé d'API.
   */
  const TOUTES_ROUTES = [...readFileSync('php/index.php', 'utf8')
    .matchAll(/^ {4}case '([^']+)':/gm)].map((x) => x[1]);
  ok('les routes se lisent depuis index.php', TOUTES_ROUTES.length > 60,
     `${TOUTES_ROUTES.length} routes`);

  /** Ce qu'un éditeur a le droit d'ouvrir, et rien d'autre. */
  const PERMIS_EDITEUR = new Set([
    // Le site public, ouvert à tout le monde, connecté ou non — y compris
    // aux robots, qui lisent robots.txt et le plan du site sans session.
    'accueil', 'og', 'decors', 'blog', 'desabonnement', 'verifier', 'reinitialiser',
    'qr', 'api-telechargement', 'robots', 'sitemap',
    // Son travail.
    'partenaire', 'nouveau', 'blog-admin', 'blog-editer', 'liens',
    // Son compte.
    'profil', 'profil-export', 'notifications',
  ]);

  /**
   * Des comptes NEUFS, fabriqués ici.
   *
   * Ceux de la section 32 servent à d'autres scénarios qui les
   * suspendent, les promeuvent ou leur changent d'adresse en chemin. Un
   * balayage de droits doit partir d'un compte dont on connaît l'état,
   * sinon un échec ne dit plus si c'est le droit ou le compte qui cloche.
   */
  const pAD = await browser.newPage();
  surveiller(pAD);
  await connexion(pAD, ADMIN.email, ADMIN.mdp);
  await pAD.goto(`${BASE}/index.php?p=comptes`, { waitUntil: 'domcontentloaded' });
  const csrfAD = await pAD.locator('.formulaire-compte input[name=csrf]').first().inputValue();
  const EDI = { email: `droits-editeur-${marque}@wakabileguide.com`, mdp: 'editeur-droits-2026' };
  const SCA = { email: `droits-scanner-${marque}@wakabileguide.com`, mdp: 'scanner-droits-2026' };
  for (const [c, role, nom] of [[EDI, 'editeur', 'Éditeur des droits'],
                                [SCA, 'scanner', 'Scanner des droits']] as const) {
    await pAD.request.post(`${BASE}/index.php?p=creer-equipier`, {
      form: { csrf: csrfAD, nom: `${nom} ${marque}`, email: c.email, role, mot_de_passe: c.mdp },
    });
  }

  const pED = await browser.newPage();
  surveiller(pED);
  await connexion(pED, EDI.email, EDI.mdp);
  ok('un éditeur arrive sur son tableau de bord', pED.url().includes('p=partenaire'),
     pED.url().split('index.php')[1] ?? '');

  const ouvertes: string[] = [];
  for (const r of TOUTES_ROUTES) {
    const rep = await pED.request.get(`${BASE}/index.php?p=${r}`, { maxRedirects: 0 })
      .catch(() => null);
    if (!rep || rep.status() !== 200) continue;
    const t = await rep.text();
    if (/Accès refusé|ne comprend pas cette fonction|n’ouvre pas l’API/.test(t)) continue;
    ouvertes.push(r);
  }
  // `connexion`, `inscription` et `oubli` renvoient chez lui quand il est
  // connecté — donc 302, jamais 200. Si elles remontent ici, c'est que la
  // session ne l'a pas suivi, et le balayage ne prouverait plus rien.
  ok('le balayage se fait bien avec sa session',
     !ouvertes.includes('connexion') && !ouvertes.includes('inscription'),
     ouvertes.slice(0, 4).join(' · '));

  const enTrop = ouvertes.filter((r) => !PERMIS_EDITEUR.has(r));
  const manquantes = [...PERMIS_EDITEUR].filter((r) => !ouvertes.includes(r));
  ok('il n’atteint AUCUNE page hors de ses prérogatives',
     enTrop.length === 0, enTrop.join(' · ') || `${ouvertes.length} pages, toutes légitimes`);
  ok('et il atteint bien toutes les siennes',
     manquantes.length === 0, manquantes.join(' · ') || 'aucune manquante');

  /* --- les trois portes qu'on vient de fermer --- */
  ok('la régie lui est fermée',
     !(await pED.request.get(`${BASE}/index.php?p=regie`, { maxRedirects: 0 })).ok());
  ok('les notifications push aussi',
     !(await pED.request.get(`${BASE}/index.php?p=diffusion`, { maxRedirects: 0 })).ok());

  /**
   * L'API, et pourquoi elle mérite un droit à elle.
   *
   * Une clé d'API est un mot de passe qui ne se déconnecte jamais : elle
   * traverse la session et la double authentification. `capacite()` disait
   * oui à tout compte interne — un SCANNER, dont tout l'intérêt est que le
   * téléphone posé à la porte n'ouvre rien d'autre, pouvait s'en fabriquer
   * une.
   */
  await pED.goto(`${BASE}/index.php?p=api-doc`, { waitUntil: 'domcontentloaded' });
  ok('un éditeur ne lit même pas la documentation de l’API',
     !pED.url().includes('p=api-doc'), pED.url().split('index.php')[1] ?? '');

  const pSC = await browser.newPage();
  surveiller(pSC);
  await connexion(pSC, SCA.email, SCA.mdp);
  const cleScanner = await pSC.request.post(`${BASE}/index.php?p=api-cle`, {
    form: { csrf: 'peu-importe' }, maxRedirects: 0,
  });
  ok('un scanner ne peut pas se fabriquer une clé d’API', !cleScanner.ok(),
     `HTTP ${cleScanner.status()}`);
  await pSC.close();

  /* --- l'écran d'accueil ne propose plus de portes fermées --- */
  await pED.goto(`${BASE}/index.php?p=partenaire`, { waitUntil: 'domcontentloaded' });
  const cibles = await pED.locator('.raccourci').evaluateAll(
    (n) => n.map((e) => (e.getAttribute('href') ?? '').split('?')[1] ?? ''));
  ok('les raccourcis du tableau de bord ne mènent nulle part d’interdit',
     cibles.every((c) => PERMIS_EDITEUR.has(c.replace('p=', ''))
                      || c.replace('p=', '') === 'profil'),
     cibles.join(' · '));
  ok('ni régie, ni push, ni API parmi eux',
     !cibles.some((c) => /regie|diffusion|api/.test(c)), cibles.join(' · '));

  const jauges = await pED.locator('.jauges .marche .haut span').evaluateAll(
    (n) => n.map((e) => (e.textContent ?? '').trim()));
  ok('les compteurs affichés sont ceux de droits qu’il tient vraiment',
     !jauges.some((j) => /E-mails/i.test(j)), jauges.join(' · '));
  ok('et le texte ne lui parle pas d’une offre qu’il n’a pas',
     (await pED.locator('main').innerText()).includes('compte de la maison'));

  /* --- son profil ne propose pas ce dont il n'a pas l'usage --- */
  await pED.goto(`${BASE}/index.php?p=profil`, { waitUntil: 'domcontentloaded' });
  ok('on ne lui demande pas la permission des notifications du navigateur',
     (await pED.locator('#push-bouton').count()) === 0);
  ok('et le texte de la double authentification dit ce que SON compte ouvre',
     (await pED.locator('main').innerText()).includes('la rédaction du blog')
     && !(await pED.locator('main').innerText()).includes('les réglages de l’installation'));

  /* --- mais un organisateur et l'équipe, eux, gardent le leur --- */
  const pPU = await browser.newPage();
  surveiller(pPU);
  await connexion(pPU, PART.email, PART.mdp);
  await pPU.goto(`${BASE}/index.php?p=profil`, { waitUntil: 'domcontentloaded' });
  ok('un organisateur, lui, peut toujours s’abonner aux notifications',
     (await pPU.locator('#push-bouton').count()) === 1);
  await pPU.close();

  await pe.goto(`${BASE}/index.php?p=profil`, { waitUntil: 'domcontentloaded' });
  ok('l’équipe aussi — elle en envoie, elle doit pouvoir s’en envoyer un essai',
     (await pe.locator('#push-bouton').count()) === 1);

  /* --- l'espace des invités n'est pas le sien --- */
  await pED.goto(`${BASE}/index.php?p=compte`, { waitUntil: 'domcontentloaded' });
  ok('l’écran des invités le renvoie chez lui plutôt que de lui montrer 0 Koris',
     pED.url().includes('p=partenaire'), pED.url().split('index.php')[1] ?? '');

  await pED.close();
  await pAD.close();

  /* ================================================================== */
  console.log('\n━━ 40. Un article qui mène à son décor, et qui se partage ━━');

  /**
   * Le lien article → décor est VIVANT, pas une image recopiée.
   *
   * La carte est reconstruite à chaque lecture depuis le décor lui-même.
   * C'est ce qui permet d'écrire l'article avant que le décor soit en
   * ligne, et surtout ce qui évite qu'un décor archivé six mois plus tard
   * laisse dans l'article un bouton qui mène à une page morte.
   */
  const pART = await browser.newPage();
  surveiller(pART);
  await connexion(pART, ADMIN.email, ADMIN.mdp);

  /* --- un décor à citer, fabriqué ici pour pouvoir l'archiver ensuite --- */
  const DECOR_ART = `Soirée liée ${marque}`;
  await pART.goto(`${BASE}/index.php?p=nouveau`, { waitUntil: 'domcontentloaded' });
  await pART.setInputFiles('input[name=cadre]', 'php/public/cadres/jy-serai.png');
  await pART.fill('input[name=titre]', DECOR_ART);
  await pART.fill('input[name=redirection]', 'https://wakabileguide.com/p/lie');
  await pART.click('main button[type=submit]');
  await pART.waitForLoadState('domcontentloaded');

  await pART.goto(`${BASE}/index.php?p=catalogue&q=${encodeURIComponent(DECOR_ART)}`,
                  { waitUntil: 'domcontentloaded' });
  const carteDecor = pART.locator(`.carte:has-text("${DECOR_ART}")`).first();
  await carteDecor.locator('form[action*="p=statut"] button:has-text("Publier")')
    .first().click().catch(() => {});
  await pART.waitForLoadState('domcontentloaded');
  await pART.goto(`${BASE}/index.php?p=catalogue&q=${encodeURIComponent(DECOR_ART)}`,
                  { waitUntil: 'domcontentloaded' });
  const idDecorLie = await pART.locator(`.carte:has-text("${DECOR_ART}") input[name=id]`)
    .first().inputValue().catch(() => '');
  ok('un décor publié est prêt à être cité', idDecorLie !== '',
     idDecorLie.slice(0, 8) + '…');

  /* --- l'article le cite --- */
  const TITRE_LIE = `Le récit du décor ${marque}`;
  await pART.goto(`${BASE}/index.php?p=blog-editer`, { waitUntil: 'domcontentloaded' });
  ok('le sélecteur de décor est présent et FACULTATIF',
     (await pART.locator('#a-decor').count()) === 1
     && (await pART.locator('#a-decor option').first().innerText()).includes('Aucun'));
  ok('la liste des décors citables est plafonnée',
     (await pART.locator('#a-decor option').count()) <= 61,
     `${await pART.locator('#a-decor option').count()} entrées`);

  await pART.fill('#a-titre', TITRE_LIE);
  await pART.fill('#a-chapo', 'Un récit qui mène au décor dont il parle.');
  await pART.locator('#a-bascule').click();
  await pART.fill('#a-corps', 'Le récit complet de la soirée, assez long pour passer '
    + 'la validation, et qui se termine sur une invitation à faire son badge.');
  await pART.selectOption('#a-decor', idDecorLie);
  await pART.click('button[value=publier]');
  await pART.waitForLoadState('domcontentloaded');

  const slugLie = `le-recit-du-decor-${marque}`;
  const lireLie = `${BASE}/index.php?p=blog&a=${slugLie}`;

  /* --- un lecteur ANONYME voit la carte --- */
  const ctxA = await browser.newContext();
  const pAN = await ctxA.newPage();
  surveiller(pAN);
  await pAN.goto(lireLie, { waitUntil: 'domcontentloaded' });
  ok('le décor cité apparaît dans l’article publié',
     (await pAN.locator('.decor-lie').count()) === 1
     && (await pAN.locator('.decor-lie').innerText()).includes(DECOR_ART),
     (await pAN.locator('.decor-lie h3').innerText().catch(() => '—')));
  ok('avec un bouton qui mène au décor, pas au catalogue',
     (await pAN.locator(`.decor-lie a[href*="p=decor&slug="]`).count()) >= 1);
  ok('et il REMPLACE l’invitation générique plutôt que de s’y ajouter',
     !(await pAN.locator('main').innerText()).includes('Votre prochain événement'));

  /* --- le partage --- */
  const reseaux = await pAN.locator('.partage-lien').evaluateAll(
    (n) => n.map((e) => (e.textContent ?? '').trim()));
  ok('l’article propose de se partager',
     ['WhatsApp', 'Facebook', 'X', 'Telegram'].every((r) => reseaux.includes(r)),
     reseaux.join(' · '));
  const hrefWa = (await pAN.locator('.partage-lien.wa').getAttribute('href')) ?? '';
  ok('le lien WhatsApp porte l’adresse de CET article, encodée',
     hrefWa.startsWith('https://wa.me/?text=')
     && hrefWa.includes(encodeURIComponent(lireLie).replace(/%3A/g, '%3A')),
     hrefWa.slice(0, 58));
  const hrefFb = (await pAN.locator('.partage-lien.fb').getAttribute('href')) ?? '';
  ok('celui de Facebook aussi', hrefFb.includes(encodeURIComponent(lireLie)));
  ok('les liens sortants portent rel="noopener"',
     (await pAN.locator('.partage-lien[target=_blank]').evaluateAll(
       (n) => n.every((e) => (e.getAttribute('rel') ?? '').includes('noopener')))));

  /**
   * Aucun script tiers : les boutons officiels des réseaux pistent chaque
   * lecteur d'une page où ils figurent, qu'on clique ou non — et pèsent
   * sur une connexion de Lomé. Une adresse `https://` fait le même travail.
   */
  const scripts = await pAN.locator('script[src]').evaluateAll(
    (n) => n.map((e) => (e as HTMLScriptElement).src));
  ok('et la page ne charge AUCUN script de réseau social',
     !scripts.some((x) => /facebook|twitter|x\.com|telegram|whatsapp|platform\./.test(x)),
     scripts.map((x) => x.split('/').pop()).join(' · ') || 'aucun script externe');

  /* --- rouvrir l'article ne détache pas son décor --- */
  await pART.goto(`${BASE}/index.php?p=blog-admin`, { waitUntil: 'domcontentloaded' });
  await pART.locator(`tr:has-text("${TITRE_LIE}") a`).first().click();
  await pART.waitForLoadState('domcontentloaded');
  ok('rouvrir l’article garde son décor sélectionné',
     (await pART.locator('#a-decor').inputValue()) === idDecorLie);
  // Relevé MAINTENANT : après l'enregistrement, la page redirige vers la
  // liste et l'identifiant a disparu de l'adresse.
  const idArticleLie = /[?&]id=([^&]+)/.exec(pART.url())?.[1] ?? '';
  const csvArticle = await pART.locator('form input[name=csrf]').first().inputValue();
  ok('l’identifiant de l’article est relevé', idArticleLie !== '', idArticleLie.slice(0, 8) + '…');
  await pART.click('button[value=enregistrer]');
  await pART.waitForLoadState('domcontentloaded');
  await pAN.goto(lireLie, { waitUntil: 'domcontentloaded' });
  ok('et le réenregistrer ne l’efface pas',
     (await pAN.locator('.decor-lie').count()) === 1);

  /* --- un identifiant trafiqué est ignoré, pas obéi --- */
  await pART.request.post(`${BASE}/index.php?p=blog-editer&id=${idArticleLie}`, {
    form: { csrf: csvArticle, titre: TITRE_LIE, chapo: 'x', slug: slugLie,
            corps: 'Un corps assez long pour passer la validation des quarante caractères.',
            decor_id: '00000000-0000-4000-8000-000000000000', action: 'enregistrer' },
  });
  await pAN.goto(lireLie, { waitUntil: 'domcontentloaded' });
  ok('un identifiant de décor inventé est ignoré, sans casser l’article',
     (await pAN.locator('h1').innerText()).includes(TITRE_LIE)
     && (await pAN.locator('.decor-lie').count()) === 0);
  ok('et l’invitation générique reprend sa place',
     (await pAN.locator('main').innerText()).includes('Votre prochain événement'));

  /* --- un décor archivé après la parution : la carte s’en va, le texte reste --- */
  await pART.goto(`${BASE}/index.php?p=blog-admin`, { waitUntil: 'domcontentloaded' });
  await pART.locator(`tr:has-text("${TITRE_LIE}") a`).first().click();
  await pART.waitForLoadState('domcontentloaded');
  await pART.selectOption('#a-decor', idDecorLie);
  await pART.click('button[value=enregistrer]');
  await pART.waitForLoadState('domcontentloaded');
  await pAN.goto(lireLie, { waitUntil: 'domcontentloaded' });
  ok('le décor est de nouveau cité', (await pAN.locator('.decor-lie').count()) === 1);

  await pART.goto(`${BASE}/index.php?p=catalogue&q=${encodeURIComponent(DECOR_ART)}`,
                  { waitUntil: 'domcontentloaded' });
  await pART.locator(`.carte:has-text("${DECOR_ART}") form[action*="p=statut"] button:has-text("Archiver")`)
    .first().click();
  await pART.waitForLoadState('domcontentloaded');
  await pAN.goto(lireLie, { waitUntil: 'domcontentloaded' });
  ok('un décor archivé après la parution ne laisse pas un bouton mort',
     (await pAN.locator('.decor-lie').count()) === 0);
  ok('mais l’article, lui, survit intact',
     (await pAN.locator('h1').innerText()).includes(TITRE_LIE)
     && (await pAN.locator('.corps-article').innerText()).trim().length > 40
     && (await pAN.locator('.partage-lien').count()) > 0,
     `${(await pAN.locator('.corps-article').innerText()).trim().length} caractères de texte`);

  /* --- « À lire aussi » : trois cartes, trois images --- */

  /**
   * Une carte sans image au milieu de deux qui en ont ne se lit pas comme
   * « cet article n'a pas d'image » mais comme « cette carte est cassée ».
   * On en garantit donc trois choses : qu'il y a une image, qu'elle s'est
   * VRAIMENT chargée, et que les trois cartes ont la même hauteur.
   */
  await pAN.goto(lireLie, { waitUntil: 'domcontentloaded' });
  const aussi = pAN.locator('section:has-text("À lire aussi") .vignette.article');
  const nAussi = await aussi.count();
  ok('« À lire aussi » propose bien trois articles', nAussi === 3, `${nAussi} carte(s)`);
  ok('chacune porte une image',
     (await aussi.locator('img').count()) === nAussi,
     `${await aussi.locator('img').count()} image(s) pour ${nAussi} carte(s)`);

  const chargees = await aussi.locator('img').evaluateAll(
    (n) => n.map((e) => (e as HTMLImageElement).naturalWidth));
  ok('et chaque image s’est réellement chargée — pas un cadre vide',
     chargees.length > 0 && chargees.every((w) => w > 0), chargees.join(' · '));

  const hauteurs = await aussi.locator('img').evaluateAll(
    (n) => n.map((e) => Math.round(e.getBoundingClientRect().height)));
  ok('les trois cartes ont la même hauteur d’image : la grille ne se tord pas',
     new Set(hauteurs).size === 1, hauteurs.join(' · '));

  await pAN.goto(`${BASE}/index.php?p=blog`, { waitUntil: 'domcontentloaded' });
  const cartesBlog = pAN.locator('.vignette.article');
  ok('au blog aussi, chaque carte porte son image',
     (await cartesBlog.count()) > 0
     && (await cartesBlog.locator('img').count()) === (await cartesBlog.count()),
     `${await cartesBlog.locator('img').count()} / ${await cartesBlog.count()}`);
  const hBlog = await cartesBlog.locator('img').evaluateAll(
    (n) => n.map((e) => Math.round(e.getBoundingClientRect().height)));
  ok('et toutes au même format',
     new Set(hBlog).size === 1, [...new Set(hBlog)].join(' · '));

  /* --- un brouillon ne propose pas de se partager --- */
  await pART.goto(`${BASE}/index.php?p=blog-editer`, { waitUntil: 'domcontentloaded' });
  await pART.fill('#a-titre', `Brouillon sans partage ${marque}`);
  await pART.locator('#a-bascule').click();
  await pART.fill('#a-corps', 'Un brouillon, qui n’est visible que de son auteur et de la rédaction.');
  await pART.click('button[value=enregistrer]');
  await pART.waitForLoadState('domcontentloaded');
  await pART.goto(`${BASE}/index.php?p=blog&a=brouillon-sans-partage-${marque}`,
                  { waitUntil: 'domcontentloaded' });
  ok('un article pas encore en ligne ne propose pas de le partager',
     (await pART.locator('.partage-lien').count()) === 0,
     `${await pART.locator('.partage-lien').count()} bouton(s)`);

  await ctxA.close();
  await pART.close();

  console.log('\n━━ 41. Ce qu’un lien partagé montre de lui-même ━━');

  /**
   * Ce que cette section éprouve n'a AUCUNE trace à l'écran.
   *
   * Un lien collé dans WhatsApp part chercher la page sans session, lit
   * son en-tête, et n'affiche que ça. Rien dans l'interface ne dit si
   * cet en-tête est bon : le défaut ne se voit qu'une fois le lien
   * envoyé, chez le destinataire, et il est alors trop tard. On regarde
   * donc la page avec les yeux du robot — sans cookie, et dans le HTML.
   */
  const pSEO = await browser.newPage();
  surveiller(pSEO);
  await connexion(pSEO, ADMIN.email, ADMIN.mdp);

  /* --- 1. L'écran de réglages vit dans Système --- */
  await pSEO.goto(`${BASE}/index.php?p=reglages`, { waitUntil: 'domcontentloaded' });
  ok('les réglages mènent au référencement',
     await pSEO.locator('a[href*="p=reglages-seo"]').count() > 0);

  await pSEO.goto(`${BASE}/index.php?p=reglages-seo`, { waitUntil: 'domcontentloaded' });
  ok('l’écran de référencement s’ouvre',
     /Référencement|référencement/.test(await pSEO.locator('main').innerText()));
  ok('il montre un aperçu de partage',
     await pSEO.locator('.apercu-partage').count() > 0);

  const NOM_SEO = `Wakabi Boost ${marque}`;
  await pSEO.fill('#s-nom', NOM_SEO);
  await pSEO.fill('#s-desc',
    'Le générateur de badges et l’agenda des soirées de Lomé, Cotonou et Abidjan.');
  await pSEO.click('main button[type=submit]');
  await pSEO.waitForLoadState('domcontentloaded');
  ok('les réglages s’enregistrent',
     /enregistr/i.test(await pSEO.locator('.msg').last().innerText()));

  /* --- 2. Le robot arrive : sans session, sans cookie --- */
  const robot = await browser.newContext();
  const pRobot = await robot.newPage();

  const lireMeta = async (url: string) => {
    await pRobot.goto(url, { waitUntil: 'domcontentloaded' });
    /**
     * Aucune fonction nommée ici : tsx enrobe celles qu'il compile d'un
     * appel à `__name`, qui n'existe pas dans la page. On relève donc les
     * balises par une simple boucle.
     */
    const brut = await pRobot.evaluate(() => {
      const sortie: Record<string, string> = {};
      for (const b of Array.from(document.querySelectorAll('meta'))) {
        const cle = b.getAttribute('property') ?? b.getAttribute('name') ?? '';
        if (cle !== '' && !(cle in sortie)) sortie[cle] = b.getAttribute('content') ?? '';
      }
      sortie['#titre'] = document.title;
      sortie['#canon'] = document.querySelector('link[rel=canonical]')
        ?.getAttribute('href') ?? '';
      sortie['#lang'] = document.documentElement.lang;
      sortie['#h1'] = String(document.querySelectorAll('h1').length);
      sortie['#jsonld'] = document.querySelector('script[type="application/ld+json"]')
        ?.textContent ?? '';
      return sortie;
    });
    const v = (c: string) => brut[c] ?? '';
    return {
      titre: v('#titre'), canon: v('#canon'), desc: v('description'),
      ogTitre: v('og:title'), ogDesc: v('og:description'), ogImg: v('og:image'),
      ogUrl: v('og:url'), ogType: v('og:type'),
      largeur: v('og:image:width'), hauteur: v('og:image:height'),
      robots: v('robots'), lang: v('#lang'), h1: Number(v('#h1')),
      jsonld: v('#jsonld'),
    };
  };

  const acc = await lireMeta(`${BASE}/index.php?p=accueil`);
  ok('l’accueil porte le nom de site réglé', acc.titre.includes(NOM_SEO), acc.titre.slice(0, 60));
  ok('il annonce une image de partage', /^https?:\/\//.test(acc.ogImg), acc.ogImg.slice(0, 70));
  ok('il dit la taille de cette image', +acc.largeur >= 600 && +acc.hauteur > 0,
     `${acc.largeur}×${acc.hauteur}`);
  ok('il déclare sa langue', acc.lang.startsWith('fr'), acc.lang);
  ok('il n’a qu’un seul titre de niveau 1', acc.h1 === 1, `${acc.h1} <h1>`);

  /* --- 3. L'article partagé : le défaut d'origine --- */
  const urlArt = `${BASE}/index.php?p=blog&a=${slugLie}`;
  const art = await lireMeta(urlArt);
  ok('un article se déclare canonique VERS LUI-MÊME',
     art.canon.includes(`a=${slugLie}`), art.canon);
  ok('og:url dit la même chose que la canonique', art.ogUrl === art.canon);
  ok('il s’annonce comme un article', art.ogType === 'article', art.ogType);
  ok('son titre de partage n’est pas vide', art.ogTitre.length > 3, art.ogTitre.slice(0, 50));
  ok('son accroche de partage tient debout', art.ogDesc.length >= 50,
     `${art.ogDesc.length} car. — ${art.ogDesc.slice(0, 60)}`);
  ok('son image de partage est absolue', /^https?:\/\//.test(art.ogImg), art.ogImg.slice(0, 70));

  /**
   * Une image annoncée mais non téléchargeable est le pire des cas : la
   * messagerie garde la vignette vide en cache, et le lien reste muet
   * même une fois l'image réparée. On va la chercher comme elle le fait.
   */
  const rImg = await pRobot.request.get(art.ogImg);
  ok('cette image se télécharge sans session',
     rImg.status() === 200 && (rImg.headers()['content-type'] ?? '').startsWith('image/'),
     `HTTP ${rImg.status()} · ${rImg.headers()['content-type'] ?? '?'}`);

  let ld: Record<string, unknown> = {};
  try { ld = JSON.parse(art.jsonld) as Record<string, unknown>; } catch { /* ld reste vide */ }
  ok('ses données structurées sont un JSON valide', Object.keys(ld).length > 0);
  ok('elles décrivent bien un article',
     JSON.stringify(ld).includes('BlogPosting'));

  /* --- 4. Le décor partagé --- */
  const decor = await lireMeta(`${BASE}/index.php?p=decor&slug=jy-serai`);
  ok('un décor se déclare canonique vers lui-même',
     decor.canon.includes('slug=jy-serai'), decor.canon);
  ok('son accroche de partage tient debout aussi', decor.ogDesc.length >= 50,
     `${decor.ogDesc.length} car.`);
  ok('son image de partage est absolue', /^https?:\/\//.test(decor.ogImg));

  /* --- 5. Le bruit d'une session ne doit pas entrer dans la canonique --- */
  const sale = await lireMeta(`${urlArt}&ok=enregistre&jeton=abcdef123&v=7`);
  ok('la canonique ignore les paramètres de passage',
     !sale.canon.includes('jeton') && !sale.canon.includes('ok=') && !sale.canon.includes('v='),
     sale.canon);
  ok('elle reste celle de l’article', sale.canon === art.canon);

  /* --- 6. Les deux fichiers que le robot lit en premier --- */
  const rRobots = await pRobot.request.get(`${BASE}/index.php?p=robots`);
  const txtRobots = await rRobots.text();
  ok('robots.txt répond en texte brut',
     rRobots.status() === 200 && (rRobots.headers()['content-type'] ?? '').startsWith('text/plain'),
     rRobots.headers()['content-type'] ?? '');
  ok('il désigne le plan du site', /^Sitemap: https?:\/\/\S+/m.test(txtRobots));
  ok('il écarte les écrans d’administration', /Disallow: .*p=admin/.test(txtRobots));

  const rPlan = await pRobot.request.get(`${BASE}/index.php?p=sitemap`);
  const xml = await rPlan.text();
  ok('le plan du site répond en XML',
     rPlan.status() === 200 && (rPlan.headers()['content-type'] ?? '').includes('xml'));
  ok('il est bien formé', xml.startsWith('<?xml') && xml.trimEnd().endsWith('</urlset>'));
  /**
   * Une esperluette nue rend le fichier ENTIER invalide, pas seulement sa
   * ligne : le moteur rejette le plan et n'en retient aucune adresse.
   */
  const nues = [...xml.matchAll(/<loc>([^<]+)<\/loc>/g)]
    .map((m) => m[1]).filter((u) => /&(?!amp;|apos;|quot;|lt;|gt;|#)/.test(u));
  ok('ses esperluettes sont échappées', nues.length === 0, nues[0] ?? '');
  ok('il liste l’article et le décor',
     xml.includes(slugLie) && xml.includes('jy-serai'));

  /* --- 7. Ce qui doit rester hors des moteurs --- */
  await pRobot.goto(`${BASE}/index.php?p=comptes`, { waitUntil: 'domcontentloaded' });
  ok('un écran d’administration renvoie le robot vers la connexion',
     pRobot.url().includes('p=connexion'), pRobot.url().replace(BASE, ''));
  const rech = await lireMeta(`${BASE}/index.php?p=blog&q=soiree`);
  ok('une page de résultats demande à ne pas être indexée',
     rech.robots.includes('noindex'), rech.robots);

  /* --- 8. Le grand interrupteur : couper l'indexation coupe tout --- */
  await pSEO.goto(`${BASE}/index.php?p=reglages-seo`, { waitUntil: 'domcontentloaded' });
  await pSEO.uncheck('input[name=seo_indexable]');
  await pSEO.click('main button[type=submit]');
  await pSEO.waitForLoadState('domcontentloaded');

  const eteint = await lireMeta(`${BASE}/index.php?p=accueil`);
  ok('site fermé aux moteurs : l’accueil porte noindex',
     eteint.robots.includes('noindex'), eteint.robots);
  const robotsEteint = await (await pRobot.request.get(`${BASE}/index.php?p=robots`)).text();
  ok('et robots.txt interdit tout le site',
     /^Disallow: \/$/m.test(robotsEteint));

  await pSEO.goto(`${BASE}/index.php?p=reglages-seo`, { waitUntil: 'domcontentloaded' });
  await pSEO.check('input[name=seo_indexable]');
  await pSEO.click('main button[type=submit]');
  await pSEO.waitForLoadState('domcontentloaded');
  const rallume = await lireMeta(`${BASE}/index.php?p=accueil`);
  ok('rouvert, l’accueil redevient indexable', !rallume.robots.includes('noindex'),
     rallume.robots || '(aucune balise robots)');

  /* --- 9. L'écran est réservé à qui règle le site --- */
  const pEd = await browser.newPage();
  await connexion(pEd, EDI.email, EDI.mdp);
  await pEd.goto(`${BASE}/index.php?p=reglages-seo`, { waitUntil: 'domcontentloaded' });
  ok('un éditeur n’entre pas dans les réglages de référencement',
     !/name="seo_nom_site"/.test(await pEd.content()),
     (await pEd.url()).replace(BASE, ''));
  await pEd.close();

  /* --- 10. La recette ne laisse pas sa marque sur le site --- */
  await pSEO.goto(`${BASE}/index.php?p=reglages-seo`, { waitUntil: 'domcontentloaded' });
  await pSEO.fill('#s-nom', 'Wakabi Boost');
  await pSEO.click('main button[type=submit]');
  await pSEO.waitForLoadState('domcontentloaded');
  const rendu = await lireMeta(`${BASE}/index.php?p=accueil`);
  ok('le nom du site est rendu tel qu’il était', !rendu.titre.includes(marque),
     rendu.titre.slice(0, 50));

  await robot.close();
  await pSEO.close();

  console.log('\n━━ 42. Un lien de décor déjà partagé ne devient jamais muet ━━');

  /**
   * Le jour où l'on archive, tous les liens déjà envoyés changent d'état.
   *
   * Un décor archivé répondait 404. Les messageries n'affichent AUCUN
   * aperçu pour un 404 : ni image, ni titre, ni accroche. Chaque lien
   * posté dans un groupe WhatsApp devenait donc une ligne grise — le jour
   * de l'archivage, pour tout le monde à la fois, et sans que rien ne
   * prévienne l'organisateur qui venait de ranger sa campagne finie.
   *
   * Le décor archivé de la section 40 sert de sujet : il a été publié,
   * partagé, puis rangé. C'est exactement le trajet qu'on éprouve.
   */
  const pFin = await browser.newContext();
  const pF = await pFin.newPage();

  const partage = async (url: string) => {
    const r = await pF.goto(url, { waitUntil: 'domcontentloaded' });
    const brut = await pF.evaluate(() => {
      const s: Record<string, string> = {};
      for (const b of Array.from(document.querySelectorAll('meta'))) {
        const c = b.getAttribute('property') ?? b.getAttribute('name') ?? '';
        if (c !== '' && !(c in s)) s[c] = b.getAttribute('content') ?? '';
      }
      s['#h1'] = document.querySelector('h1')?.textContent ?? '';
      return s;
    });
    return {
      statut: r?.status() ?? 0,
      titre: brut['og:title'] ?? '', desc: brut['og:description'] ?? '',
      image: brut['og:image'] ?? '', robots: brut['robots'] ?? '', h1: brut['#h1'],
    };
  };

  const fini = await partage(`${BASE}/index.php?p=decor&slug=soiree-liee-${marque}`);
  ok('un décor archivé RÉPOND encore — 404 tuerait tout aperçu',
     fini.statut === 200, `HTTP ${fini.statut}`);
  ok('il garde son propre titre, pas « introuvable »',
     fini.titre.includes(DECOR_ART), fini.titre);
  ok('il garde une accroche digne de ce nom', fini.desc.length >= 50,
     `${fini.desc.length} car. — ${fini.desc.slice(0, 60)}`);
  ok('et il dit franchement que la campagne est finie',
     /terminée/i.test(fini.desc) || /terminée/i.test(await pF.locator('main').innerText()));
  ok('sa vignette se télécharge toujours',
     (await pF.request.get(fini.image)).status() === 200, fini.image.slice(-40));
  /**
   * Répondre 200 ne veut pas dire vouloir être trouvé : la page sert les
   * liens d'hier, elle n'a pas à concurrencer les campagnes d'aujourd'hui
   * dans les résultats de recherche.
   */
  ok('mais il se retire des moteurs', fini.robots.includes('noindex'), fini.robots);
  ok('et il ne propose plus de fabriquer un badge',
     (await pF.locator('#toile').count()) === 0);
  ok('il renvoie vers les décors du moment',
     (await pF.locator('a[href*="p=decors"]').count()) >= 1);

  /**
   * Un décor JAMAIS publié, lui, reste un 404 : son adresse n'a rien
   * promis à personne, et une page d'attente y serait une fuite.
   */
  const jamais = await partage(`${BASE}/index.php?p=decor&slug=decor-qui-nexiste-pas-${marque}`);
  ok('une adresse qui n’a jamais existé reste un 404', jamais.statut === 404,
     `HTTP ${jamais.statut}`);
  ok('et elle ne s’offre pas aux moteurs', jamais.robots.includes('noindex'));

  /* --- l'outil qui permet d'éprouver un lien soi-même --- */
  /**
   * Cette page n'est PAS surveillée, et c'est voulu : l'outil qu'on y
   * éprouve va justement chercher une adresse morte, et le navigateur
   * journalise ce 404 comme il le doit. Le surveiller ferait échouer la
   * recette sur le comportement même qu'elle vient vérifier.
   */
  const pDiag = await browser.newPage();
  await connexion(pDiag, ADMIN.email, ADMIN.mdp);
  await pDiag.goto(`${BASE}/index.php?p=reglages-seo`, { waitUntil: 'domcontentloaded' });
  ok('l’écran de référencement propose d’éprouver un lien',
     (await pDiag.locator('#t-url').count()) === 1);
  await pDiag.fill('#t-url', `${BASE}/index.php?p=decor&slug=jy-serai`);
  await pDiag.click('#t-go');
  await pDiag.waitForSelector('#t-sortie .apercu-partage', { timeout: 20_000 });
  const diagnostic = await pDiag.locator('#t-sortie').innerText();
  ok('il rend un diagnostic sur la canonique', /canonique vers elle-même/i.test(diagnostic));
  ok('il mesure vraiment l’image', /Image téléchargée, \d+ × \d+/.test(diagnostic),
     (/Image téléchargée[^\n]*/.exec(diagnostic) ?? [''])[0]);

  await pDiag.fill('#t-url', `${BASE}/index.php?p=decor&slug=decor-qui-nexiste-pas-${marque}`);
  await pDiag.click('#t-go');
  await pDiag.waitForFunction(() => /répond 404/.test(
    document.getElementById('t-sortie')?.textContent ?? ''), null, { timeout: 20_000 });
  ok('et il NOMME la cause quand la page répond 404',
     /Aucun aperçu ne s’affichera/.test(await pDiag.locator('#t-sortie').innerText()));

  await pDiag.fill('#t-url', 'https://exemple.invalide/page');
  await pDiag.click('#t-go');
  await pDiag.waitForFunction(() => /n’appartient pas à ce site/.test(
    document.getElementById('t-sortie')?.textContent ?? ''), null, { timeout: 10_000 });
  ok('une adresse étrangère au site est refusée', true);

  await pDiag.close();
  await pFin.close();

  console.log('\n━━ 43. La vitrine, et ce qu’elle seule porte ━━');

  /**
   * La vitrine est la seule page qu'un inconnu ouvre. Elle porte donc le
   * pied de page du guide — et elle est la seule : ailleurs on est
   * connecté, au travail, et quatre colonnes de liens vers le site public
   * repousseraient vers le bas l'écran qu'on est venu utiliser.
   */
  const ctxVitrine = await browser.newContext();
  const pAnon = await ctxVitrine.newPage();

  await pAnon.goto(`${BASE}/index.php?p=accueil`, { waitUntil: 'domcontentloaded' });
  const menu = await pAnon.locator('header nav > a, header nav > details > summary')
    .evaluateAll((n) => n.map((x) => (x.textContent ?? '').trim()).filter(Boolean));
  ok('la vitrine mène aux deux produits, puis aux décors et au blog',
     menu.slice(0, 4).join(' · ') === 'Wakabi Boost · Wakabi le guide · Les décors · Le blog',
     menu.join(' · '));
  ok('« Wakabi le guide » sort vraiment vers le guide',
     ((await pAnon.locator('header nav a:has-text("Wakabi le guide")').getAttribute('href')) ?? '')
       .startsWith('https://wakabileguide.com'));
  ok('et il emporte rel="noopener"',
     ((await pAnon.locator('header nav a:has-text("Wakabi le guide")').getAttribute('rel')) ?? '')
       .includes('noopener'));
  ok('les deux boutons d’entrée suivent',
     (await pAnon.locator('header nav a:has-text("Connexion")').count()) === 1
     && (await pAnon.locator('header nav a:has-text("Créer un compte")').count()) === 1);

  /**
   * Les en-têtes de section sont TOUS centrés. Deux d'entre eux ne
   * l'étaient pas, et la page tirait à gauche deux fois sur neuf — le
   * genre de décalage qu'on ne nomme pas mais qu'on voit.
   */
  const tetes = await pAnon.locator('.tete').count();
  const centrees = await pAnon.locator('.tete.centre').count();
  ok('tous les en-têtes de section sont centrés', tetes === centrees && tetes >= 6,
     `${centrees} centrés sur ${tetes}`);

  ok('la vitrine porte le pied de page du guide',
     (await pAnon.locator('footer.pied-guide').count()) === 1);
  const colonnes = await pAnon.locator('.pg-col h4')
    .evaluateAll((n) => n.map((x) => (x.textContent ?? '').trim()));
  ok('avec ses trois colonnes', colonnes.join(' · ') === 'Produit · Partenaires · Wakabi',
     colonnes.join(' · '));
  ok('et ses quatre réseaux', (await pAnon.locator('.pg-soc').count()) === 4);
  /**
   * Les icônes sont dessinées ici, pas chargées ailleurs : un pied de
   * page qui va chercher quatre PNG sur un sous-domaine WordPress se vide
   * le jour où ce sous-domaine bouge.
   */
  ok('dessinés sur place, sans rien charger d’un autre serveur',
     (await pAnon.locator('.pg-soc svg').count()) === 4
     && (await pAnon.locator('.pg-soc img').count()) === 0);
  ok('les liens du guide s’ouvrent à part',
     (await pAnon.locator('.pg-liens a[href^="https://wakabileguide.com"][target="_blank"]').count()) >= 3);

  /* --- les autres pages publiques le portent aussi --- */
  for (const p of ['decors', 'blog']) {
    await pAnon.goto(`${BASE}/index.php?p=${p}`, { waitUntil: 'domcontentloaded' });
    ok(`« ${p} » porte le pied de page du guide`,
       (await pAnon.locator('footer.pied-guide').count()) === 1);
  }
  /**
   * Un article aussi : c'est la même route, et un lecteur qui vient d'en
   * finir un est précisément celui à qui montrer le chemin vers la suite.
   */
  const unLu = await pAnon.locator('a[href*="p=blog&a="]').first().getAttribute('href') ?? '';
  if (unLu) {
    await pAnon.goto(unLu.startsWith('http') ? unLu : `${BASE}/${unLu.replace(/^\//, '')}`,
                     { waitUntil: 'domcontentloaded' });
    ok('un article aussi', (await pAnon.locator('footer.pied-guide').count()) === 1);
  }

  /**
   * Le Studio en est exclu, bien qu'il soit public : on n'y lit pas, on y
   * fabrique. Et les écrans de travail gardent la signature discrète.
   */
  for (const p of ['connexion', 'inscription']) {
    await pAnon.goto(`${BASE}/index.php?p=${p}`, { waitUntil: 'domcontentloaded' });
    ok(`« ${p} » garde la signature discrète`,
       (await pAnon.locator('footer.pied-guide').count()) === 0
       && (await pAnon.locator('footer .pied').count()) === 1);
  }
  await pAnon.goto(`${BASE}/index.php?p=decor&slug=jy-serai`, { waitUntil: 'domcontentloaded' });
  ok('le Studio n’est pas repoussé par quatre colonnes de liens',
     (await pAnon.locator('footer.pied-guide').count()) === 0);

  /**
   * Le retour en tête : on l'éprouve en DÉFILANT, pas en lisant le HTML.
   *
   * Un bouton présent dans la page mais toujours caché, ou visible dès le
   * premier écran, sont deux défauts qu'aucune lecture du balisage ne
   * distingue. On regarde donc ce que voit quelqu'un qui descend.
   */
  await pAnon.goto(`${BASE}/index.php?p=accueil`, { waitUntil: 'domcontentloaded' });
  const haut = pAnon.locator('#haut-de-page');
  ok('la vitrine porte un retour en tête de page', (await haut.count()) === 1);
  ok('mais il ne s’affiche pas tant qu’on est déjà en haut', await haut.isHidden());

  await pAnon.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
  await pAnon.waitForFunction(() => {
    const b = document.getElementById('haut-de-page');
    return b !== null && !b.hidden;
  }, null, { timeout: 10_000 }).catch(() => {});
  ok('il apparaît une fois qu’on a descendu la page', await haut.isVisible());
  ok('il dit aux lecteurs d’écran ce qu’il fait',
     ((await haut.getAttribute('aria-label')) ?? '').length > 5,
     (await haut.getAttribute('aria-label')) ?? '');

  await haut.click();
  await pAnon.waitForFunction(() => window.scrollY === 0, null, { timeout: 10_000 })
    .catch(() => {});
  ok('cliquer dessus ramène VRAIMENT en haut',
     (await pAnon.evaluate(() => window.scrollY)) === 0,
     `y = ${await pAnon.evaluate(() => window.scrollY)}`);
  ok('et le bouton se retire de lui-même une fois arrivé', await haut.isHidden());

  /**
   * Il n'existe QUE sur la vitrine : ailleurs, les écrans tiennent en un
   * écran ou deux, et un bouton flottant masquerait une ligne de tableau.
   */
  for (const p of ['decors', 'blog', 'connexion']) {
    await pAnon.goto(`${BASE}/index.php?p=${p}`, { waitUntil: 'domcontentloaded' });
    ok(`« ${p} » n’en porte pas`, (await pAnon.locator('#haut-de-page').count()) === 0);
  }

  /* --- l'accroche retirée de la section du blog --- */
  await pAnon.goto(`${BASE}/index.php?p=accueil`, { waitUntil: 'domcontentloaded' });
  ok('la section du blog n’a plus d’accroche sous son titre',
     !/Des campagnes réelles/.test(await pAnon.locator('main').innerText()));

  /**
   * Le balayage est exhaustif PAR CONSTRUCTION, comme celui des droits.
   *
   * On ne vérifie pas une liste de liens écrite à la main — elle
   * manquerait justement celui qu'on vient d'ajouter. On ramasse TOUTES
   * les ancres de chaque page publique, on garde celles dont l'hôte n'est
   * pas le nôtre, et l'on exige des deux attributs à la fois : l'onglet,
   * pour que le visiteur ne perde pas la page qu'il lisait, et `noopener`,
   * sans quoi la page ouverte garde une poignée sur la nôtre.
   */
  const sorties = async (chemin: string) => {
    await pAnon.goto(`${BASE}/index.php?p=${chemin}`, { waitUntil: 'domcontentloaded' });
    return pAnon.evaluate((hote) => Array.from(document.querySelectorAll('a[href]'))
      .map((a) => {
        const el = a as HTMLAnchorElement;
        return { href: el.href, hote: el.hostname, cible: el.target, rel: el.rel,
                 texte: (el.textContent ?? '').trim().slice(0, 24) };
      })
      .filter((l) => /^https?:$/.test(new URL(l.href).protocol) && l.hote !== hote),
      new URL(BASE).hostname);
  };

  let dehors = 0;
  const fautifs: string[] = [];
  for (const p of ['accueil', 'decors', 'blog', 'connexion', 'inscription']) {
    for (const l of await sorties(p)) {
      dehors++;
      if (l.cible !== '_blank' || !l.rel.includes('noopener')) {
        fautifs.push(`${p} · ${l.texte} → ${l.hote} (cible="${l.cible}" rel="${l.rel}")`);
      }
    }
  }
  ok('des liens sortants ont bien été trouvés à éprouver', dehors >= 8, `${dehors} liens`);
  ok('TOUS s’ouvrent dans un onglet neuf, et lâchent notre fenêtre',
     fautifs.length === 0, fautifs[0] ?? `${dehors} liens vérifiés`);

  /* --- et le guide en particulier, puisque c'est par lui qu'on est venu --- */
  await pAnon.goto(`${BASE}/index.php?p=accueil`, { waitUntil: 'domcontentloaded' });
  const guide = pAnon.locator('header nav a:has-text("Wakabi le guide")');
  ok('« Wakabi le guide » s’ouvre dans un nouvel onglet',
     (await guide.getAttribute('target')) === '_blank',
     `target="${await guide.getAttribute('target')}"`);

  /**
   * L'épreuve la plus fidèle : on CLIQUE, et l'on regarde s'il naît un
   * onglet. Un `target` posé dans le HTML peut être défait par un script,
   * et c'est le clic, pas l'attribut, que fait le visiteur.
   */
  const [neuf] = await Promise.all([
    pAnon.context().waitForEvent('page', { timeout: 15_000 }).catch(() => null),
    guide.click(),
  ]);
  ok('et cliquer dessus ouvre VRAIMENT un second onglet', neuf !== null);
  ok('la page de départ reste ouverte derrière',
     pAnon.url().includes('p=accueil'), pAnon.url().replace(BASE, ''));
  if (neuf) await neuf.close().catch(() => {});

  /* --- une fois connecté, les deux entrées de vitrine s'effacent --- */
  await pAnon.goto(`${BASE}/index.php?p=connexion`, { waitUntil: 'domcontentloaded' });
  await pAnon.fill('input[name=email]', ADMIN.email);
  await pAnon.fill('input[name=mot_de_passe]', ADMIN.mdp);
  await pAnon.click('main button[type=submit]');
  await pAnon.waitForLoadState('domcontentloaded');
  const menuConnecte = await pAnon.locator('header nav').innerText();
  ok('connecté, le menu ne propose plus « Wakabi Boost » ni « Wakabi le guide »',
     !/Wakabi Boost|Wakabi le guide/.test(menuConnecte),
     menuConnecte.replace(/\s+/g, ' ').slice(0, 70));
  ok('et aucun lien ne sort vers le guide depuis le menu',
     (await pAnon.locator('header nav a[href^="https://wakabileguide.com"]').count()) === 0);

  await ctxVitrine.close();

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
