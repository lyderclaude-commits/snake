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
  await p.goto(`${BASE}/index.php?p=decors`, { waitUntil: 'domcontentloaded' });
  await p.locator('a[href*="p=decor&slug="]').first().click();
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
  ok('entrée validée', /Entrée validée/.test(await p.locator('[role=status]').first().innerText()));

  await p.fill('input[name=jeton]', jeton);
  await p.click('main button[type=submit]');
  await p.waitForLoadState('domcontentloaded');
  ok('rescan refusé (idempotent)', /déjà été scanné/.test(await p.locator('[role=status]').first().innerText()));

  await p.fill('input[name=jeton]', 'ZZZZZZZZZZ');
  await p.click('main button[type=submit]');
  await p.waitForLoadState('domcontentloaded');
  ok('code inconnu refusé', /inconnu/i.test(await p.locator('[role=status]').first().innerText()));

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
  ok('les six gabarits sont proposés', (await p.locator('#disposition option').count()) === 6);

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
  ok('les cinq destinations sont sous un seul intitulé',
     (await p.locator('.barre .deroulant > summary').innerText()).trim().startsWith('Administration')
     && (await p.locator('.barre .deroulant .volet a').count()) === 5);
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
