// La recette du site Live Y Dream, dans un vrai navigateur.
//
//     node dream/outils/recette.mjs
//
// Elle copie `dream/site` dans un dossier temporaire (base vide), démarre
// le site et un faux WordPress avec le serveur intégré de PHP, puis joue
// chaque scénario. Rien n'est écrit dans le dépôt.
import { chromium } from 'playwright-core';
import { spawn } from 'node:child_process';
import { cpSync, mkdtempSync, rmSync, writeFileSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ICI = dirname(fileURLToPath(import.meta.url));
const SITE = join(ICI, '..', 'site');
const PORT = 8180, PORT_WP = 8190;
const B = `http://127.0.0.1:${PORT}`, WP = `http://127.0.0.1:${PORT_WP}/wp-json/wp/v2`;
const CAPTURES = process.env.CAPTURES || '';

const tmp = mkdtempSync(join(tmpdir(), 'lyd-recette-'));
cpSync(SITE, tmp, { recursive: true, filter: (s) => !/donnees\/(lyd\.sqlite|sessions)|uploads\/\d{4}/.test(s) });
const serveurs = [
  spawn('php', ['-S', `127.0.0.1:${PORT}`, '-t', tmp, join(ICI, 'routeur-dev.php')], { stdio: 'ignore', env: { ...process.env, PHP_CLI_SERVER_WORKERS: '4' } }),
  spawn('php', ['-S', `127.0.0.1:${PORT_WP}`, join(ICI, 'faux-wordpress', 'index.php')], { stdio: 'ignore' }),
];
const fin = () => { serveurs.forEach((s) => s.kill()); rmSync(tmp, { recursive: true, force: true }); };
process.on('exit', fin);
await new Promise((r) => setTimeout(r, 900));

const navigateur = await chromium.launch(existsSync('/opt/pw-browsers/chromium') ? { executablePath: '/opt/pw-browsers/chromium' } : {});
const ctx = await navigateur.newContext({ viewport: { width: 1360, height: 900 } });
const p = await ctx.newPage();
const erreurs = [];
// Les ressources de tiers (YouTube, Unsplash) peuvent manquer selon le réseau : ce n'est pas une erreur du site.
const tiers = /youtube|unsplash|ERR_TUNNEL|ERR_NAME|ERR_CONNECTION|ERR_INTERNET|net::ERR_FAILED/;
p.on('pageerror', (e) => erreurs.push(p.url() + ' — ' + e));
p.on('console', (m) => { if (m.type() === 'error' && !tiers.test(m.text()) && !/status of 40[34]/.test(m.text())) erreurs.push(p.url() + ' — ' + m.text()); });
p.on('dialog', (d) => d.accept(d.type() === 'prompt' ? (d.message().includes('Légende') ? 'Une légende' : 'https://exemple.tg') : undefined));

let ok = 0, ko = 0;
async function scenario(nom, f) {
  try { await f(); ok++; console.log('  ✓', nom); }
  catch (e) { ko++; console.log('  ✗', nom, '\n     ', String(e.message || e).split('\n')[0]); if (CAPTURES) await p.screenshot({ path: join(CAPTURES, 'echec-' + ok + ko + '.png') }).catch(() => {}); }
}
function verifier(c, msg) { if (!c) throw new Error(msg); }
const brut = (u, o = {}) => fetch(B + u, { redirect: 'manual', ...o });
const cliquer = async (sel) => { await Promise.all([p.waitForLoadState('load'), p.click(sel)]); await p.waitForLoadState('load'); };
const soumettre = async (sel) => { await Promise.all([p.waitForNavigation(), p.click(sel)]); };
const capture = async (nom, page = p) => { if (CAPTURES) await page.screenshot({ path: join(CAPTURES, nom + '.png'), fullPage: false }); };

console.log('Installation');
await scenario('une installation neuve mène à la création du premier compte', async () => {
  await p.goto(B + '/admin');
  verifier(p.url().endsWith('/admin/premier-compte'), 'redirection attendue, obtenu ' + p.url());
});
await scenario('deux mots de passe différents sont refusés, la saisie est gardée', async () => {
  await p.fill('#nom', 'Ulrich'); await p.fill('#email', 'admin@liveydream.com');
  await p.fill('#mdp', 'motdepasse-solide'); await p.fill('#mdp2', 'autre-chose-123');
  await soumettre('button[type=submit]');
  verifier((await p.textContent('.msg.err')).includes('correspondent pas'), 'message attendu');
  verifier(await p.inputValue('#email') === 'admin@liveydream.com', 'e-mail perdu');
});
await scenario('le premier compte est créé administrateur et mène à l’import', async () => {
  await p.fill('#mdp', 'motdepasse-solide'); await p.fill('#mdp2', 'motdepasse-solide');
  await soumettre('button[type=submit]');
  verifier(p.url().endsWith('/admin/import'), 'obtenu ' + p.url());
});
await scenario('la page de premier compte se ferme une fois le compte créé', async () => {
  const r = await brut('/admin/premier-compte');
  verifier(r.status === 303 || r.status === 302, 'statut ' + r.status);
});

console.log('Import WordPress');
await scenario('le test de connexion compte les articles', async () => {
  await p.fill('#racine', WP);
  await p.click('[data-sonder]');
  await p.waitForFunction(() => /réussie|Échec/.test(document.querySelector('[data-etat-import]').textContent));
  verifier((await p.textContent('[data-etat-import]')).includes('23 article'), await p.textContent('[data-etat-import]'));
});
await scenario('une adresse fausse est signalée sans planter', async () => {
  await p.fill('#racine', `http://127.0.0.1:${PORT_WP}/pas-wordpress`);
  await p.click('[data-sonder]');
  await p.waitForFunction(() => /Échec/.test(document.querySelector('[data-etat-import]').textContent));
  await p.fill('#racine', WP);
});
await scenario('l’import rapatrie les 23 articles et leurs images, par lots', async () => {
  await p.click('[data-lancer]');
  await p.waitForFunction(() => /terminé|interrompu/.test(document.querySelector('[data-etat-import]').textContent), null, { timeout: 240000 });
  const t = await p.textContent('[data-etat-import]');
  verifier(t.includes('23 créé'), t);
  verifier(/46 image/.test(t), 'images : ' + t);
  verifier(t.includes('1 image(s) non récupérée'), 'l’image morte doit être signalée : ' + t);
  await capture('admin-import');
});
await scenario('relancer l’import ne duplique rien', async () => {
  await p.click('[data-lancer]');
  await p.waitForFunction(() => /terminé|interrompu/.test(document.querySelector('[data-etat-import]').textContent) && !document.querySelector('[data-lancer]').disabled, null, { timeout: 240000 });
  const t = await p.textContent('[data-etat-import]');
  verifier(t.includes('0 créé') && t.includes('23 mis à jour'), t);
});
await scenario('un article retouché ici n’est pas écrasé par un nouvel import', async () => {
  await p.goto(B + '/admin/articles?q=article-numero-2');
  await cliquer('a.titre-ligne:text-is("Article n°2 – l’exemple")');
  await p.fill('#titre', 'Article 2 retouché par l’équipe');
  await soumettre('form#form-article button[type=submit]');
  await p.goto(B + '/admin/import');
  await p.click('[data-lancer]');
  await p.waitForFunction(() => /terminé|interrompu/.test(document.querySelector('[data-etat-import]').textContent), null, { timeout: 240000 });
  verifier((await p.textContent('[data-etat-import]')).includes('1 gardé'), await p.textContent('[data-etat-import]'));
  const r = await (await brut('/blog/article-numero-2')).text();
  verifier(r.includes('Article 2 retouché'), 'retouche perdue');
});

console.log('Blog public');
await scenario('le blog liste 9 articles par page, rendus par le serveur', async () => {
  const h = await (await brut('/blog')).text();
  verifier((h.match(/class="article-card/g) || []).length === 9, 'cartes : ' + (h.match(/class="article-card/g) || []).length);
  verifier(h.includes('aria-label="Pages du blog"'), 'pagination absente');
});
await scenario('la page 3 du blog porte les 5 derniers', async () => {
  const h = await (await brut('/blog?page=3')).text();
  verifier((h.match(/class="article-card/g) || []).length === 5, 'page 3');
});
await scenario('catégorie, tag et recherche filtrent', async () => {
  const c = await (await brut('/blog?categorie=Strat%C3%A9gie')).text();
  verifier((c.match(/class="article-card/g) || []).length === 5, 'catégorie : ' + (c.match(/class="article-card/g) || []).length);
  verifier(!c.includes('Non classé'), '« Non classé » ne doit pas apparaître');
  const t = await (await brut('/blog?tag=Lom%C3%A9')).text();
  verifier((t.match(/class="article-card/g) || []).length > 0 && t.includes('noindex'), 'tag');
  const q = await (await brut('/blog?q=n%C2%B017')).text();
  verifier(q.includes('Article n°17'), 'recherche');
});
await scenario('un article a sa page, son titre, sa date et ses données structurées', async () => {
  await p.goto(B + '/blog/article-numero-3');
  verifier(await p.textContent('h1') === 'Article n°3 – l’exemple', 'titre : ' + await p.textContent('h1'));
  verifier(await p.locator('h1').count() === 1, 'un seul h1');
  const h = await p.content();
  verifier(h.includes('"BlogPosting"') && h.includes('og:image'), 'données de partage');
  verifier(h.includes('4 avril 2024'), 'date');
  await capture('public-article');
});
await scenario('le contenu importé est désarmé : ni script, ni onclick, ni javascript:', async () => {
  const h = await (await brut('/blog/article-numero-3')).text();
  const corps = h.split('class="am-content"')[1].split('am-cta')[0];
  verifier(!/<script|onclick|javascript:/i.test(corps), 'contenu dangereux');
  verifier(corps.includes('youtube.com/embed'), 'la vidéo YouTube doit rester');
});
await scenario('toutes les images d’un article importé sont servies par le site', async () => {
  const h = await (await brut('/blog/article-numero-3')).text();
  const srcs = [...h.split('class="am-content"')[1].split('am-cta')[0].matchAll(/<img[^>]+src="([^"]+)"/g)].map((m) => m[1]);
  verifier(srcs.length >= 2 && srcs.every((s) => s.startsWith('/uploads/')), srcs.join(' '));
  verifier(!h.includes('-300x188'), 'la vignette 300 px a été prise au lieu de la grande image');
  for (const s of srcs) verifier((await brut(s)).status === 200, 'image absente ' + s);
});
await scenario('les liens internes de l’ancien site pointent vers le nouveau', async () => {
  const h = await (await brut('/blog/article-numero-3')).text();
  verifier(h.includes('href="/article-numero-1/"'), 'lien interne');
  const r = await brut('/article-numero-1/');
  verifier(r.status === 301 && r.headers.get('location').endsWith('/blog/article-numero-1'), 'redirection ' + r.status);
});
await scenario('les anciennes adresses WordPress redirigent en 301', async () => {
  for (const [de, vers] of [['/2024/04/article-numero-3/', '/blog/article-numero-3'], ['/?p=103', '/blog/article-numero-3'],
    ['/blog.html', '/blog'], ['/feed/', '/blog/flux.xml'], ['/category/strategie/', '/blog'], ['/services.html', '/services']]) {
    const r = await brut(de);
    verifier(r.status === 301 && r.headers.get('location').endsWith(vers), de + ' → ' + r.status + ' ' + r.headers.get('location'));
  }
});
await scenario('plan du site, robots.txt et flux RSS', async () => {
  const s = await (await brut('/sitemap.xml')).text();
  verifier((s.match(/<loc>/g) || []).length === 6 + 23, 'sitemap : ' + (s.match(/<loc>/g) || []).length);
  verifier((await (await brut('/robots.txt')).text()).includes('Disallow: /admin/'), 'robots');
  verifier(((await (await brut('/blog/flux.xml')).text()).match(/<item>/g) || []).length === 20, 'rss');
});
await scenario('une adresse inconnue répond 404 avec la page du site', async () => {
  const r = await brut('/nimporte-quoi-xyz');
  verifier(r.status === 404 && (await r.text()).includes('Cette page n’existe pas'), 'statut ' + r.status);
});

console.log('Pages publiques');
for (const [chemin, attendu] of [['/', 'VOS CLIENTS NE PEUVENT PAS VOUS IGNORER'], ['/services', 'Community'], ['/campagnes', 'Woodin'],
  ['/a-propos', 'Live Y Dream'], ['/contact', 'Parlons de votre projet'], ['/mentions-legales', 'Éditeur du site'], ['/confidentialite', 'Vos droits']]) {
  await scenario(`${chemin} s’affiche sans erreur`, async () => {
    await p.goto(B + chemin, { waitUntil: 'load' });
    verifier((await p.textContent('body')).includes(attendu), 'texte attendu : ' + attendu);
    verifier(await p.locator('nav.site-nav').count() === 1 && await p.locator('footer.site-footer').count() === 1, 'barre ou pied absents');
    verifier(!(await p.content()).includes('loremcommunication.com') && !(await p.content()).includes('icons8'), 'ressource externe restante');
  });
}
await scenario('l’accueil montre les chiffres sans JavaScript et 4 campagnes', async () => {
  const h = await (await brut('/')).text();
  verifier(h.includes('>10+<') && h.includes('>200+<'), 'chiffres absents du HTML');
  verifier((h.match(/class="pg-item/g) || []).length === 4, 'campagnes : ' + (h.match(/class="pg-item/g) || []).length);
  await p.goto(B + '/'); await capture('public-accueil');
});
await scenario('les filtres et la fiche des campagnes marchent', async () => {
  await p.goto(B + '/campagnes');
  await p.click('.filter-btn[data-filter=event]');
  verifier(await p.locator('#campaignGrid .cg-item').count() === 2, 'événementiel : ' + await p.locator('#campaignGrid .cg-item').count());
  await p.click('#campaignGrid .cg-item >> nth=0');
  await p.waitForSelector('#caseModal.open');
  verifier((await p.textContent('#caseResults')).includes('Participants'), 'résultats');
  await p.keyboard.press('Escape');
  verifier(!(await p.locator('#caseModal.open').count()), 'la fiche doit se fermer');
});
await scenario('un lien /campagnes#campagne-N ouvre la fiche', async () => {
  await p.goto(B + '/campagnes#campagne-1');
  await p.waitForSelector('#caseModal.open');
});

console.log('Contact');
await scenario('une adresse e-mail invalide est refusée, la saisie est gardée', async () => {
  await p.goto(B + '/contact');
  await p.fill('#c-nom', 'Afi Mensah'); await p.fill('#c-mail', 'pas-une-adresse@x');
  await p.fill('#c-wa', '+228 90 00 00 00'); await p.fill('#c-msg', 'Nous lançons une boutique.');
  await p.evaluate(() => document.getElementById('c-mail').type = 'text');
  await soumettre('.form-submit-btn');
  verifier((await p.textContent('.form-erreur')).includes('e-mail'), 'erreur attendue');
  verifier(await p.inputValue('#c-msg') === 'Nous lançons une boutique.', 'message perdu');
});
await scenario('une demande valide est enregistrée et confirmée', async () => {
  await p.fill('#c-mail', 'afi@boutique.tg');
  await p.selectOption('#c-besoin', 'Community Management');
  await soumettre('.form-submit-btn');
  verifier(await p.isVisible('#formSuccess'), 'confirmation');
});
await scenario('un robot qui remplit le champ piège est ignoré en silence', async () => {
  const h = await (await brut('/contact', { headers: { Cookie: '' } })).text();
  verifier(h.includes('name="site_web"'), 'champ piège absent');
});
await scenario('la demande apparaît dans l’administration, marquée non lue', async () => {
  await p.goto(B + '/admin');
  verifier((await p.textContent('.barre')).includes('Messages1'), 'compteur absent');
  verifier((await p.textContent('.bord-file')).includes('Afi Mensah'), 'à traiter');
  await capture('admin-bord');
  await p.goto(B + '/admin/messages'); await cliquer('text=Afi Mensah');
  verifier((await p.textContent('.fiche')).includes('afi@boutique.tg'), 'fiche');
  verifier(await p.locator('a:has-text("Répondre sur WhatsApp")').count() === 1, 'bouton WhatsApp');
  await p.goto(B + '/admin');
  verifier(!(await p.textContent('.barre')).includes('Messages1'), 'devrait être lu');
});

console.log('Écrire');
await scenario('un article écrit dans l’éditeur paraît sur le blog', async () => {
  await p.goto(B + '/admin/articles/nouveau');
  await p.fill('#titre', 'Réussir son lancement à Lomé');
  await p.click('.editeur-zone');
  await p.keyboard.type('Premier paragraphe du texte.');
  await p.keyboard.press('Enter');
  await p.click('[data-cmd=formatBlock][data-val=H2]');
  await p.keyboard.type('Un intertitre');
  await p.keyboard.press('Enter');
  await p.keyboard.press('Control+B'); await p.keyboard.type('Gras'); await p.keyboard.press('Control+B');
  await p.fill('#categorie', 'Conseils'); await p.fill('#tags', 'Lancement, Lomé');
  await p.selectOption('#statut', 'publie');
  await soumettre('form#form-article button[type=submit]');
  verifier((await p.textContent('.msg.ok')).includes('en ligne'), 'confirmation');
  const h = await (await brut('/blog/reussir-son-lancement-a-lome')).text();
  verifier(h.includes('<h2>Un intertitre</h2>') && /<(b|strong)>Gras<\/(b|strong)>/.test(h), 'mise en forme perdue');
  await capture('admin-editeur');
});
await scenario('un brouillon reste invisible du public, visible en aperçu pour l’équipe', async () => {
  await p.goto(B + '/admin/articles/nouveau');
  await p.fill('#titre', 'Brouillon secret');
  await p.click('.editeur-zone'); await p.keyboard.type('Pas encore prêt.');
  await soumettre('form#form-article button[type=submit]');
  verifier((await brut('/blog/brouillon-secret')).status === 404, 'visible du public');
  await p.goto(B + '/blog/brouillon-secret');
  verifier((await p.textContent('.am-brouillon')).includes('Aperçu'), 'aperçu');
});
await scenario('une image téléversée rejoint la médiathèque et devient la couverture', async () => {
  const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAFklEQVR4nGP8z8DAwMDAxMDAwMDAAAANHQEDasKb6QAAAABJRU5ErkJggg==', 'base64');
  const f = join(tmp, 'essai-couverture.png'); writeFileSync(f, png);
  await p.goto(B + '/admin/medias');
  await p.setInputFiles('[data-depot] input[type=file]', f);
  await soumettre('[data-depot] button[type=submit]');
  verifier((await p.textContent('.msg.ok')).includes('ajoutée'), 'téléversement');
  await p.goto(B + '/admin/articles?q=reussir'); await cliquer('text=Modifier');
  await p.click('[data-choisir-couverture]');
  await p.waitForSelector('#selecteur-medias [data-grille] .media');
  await p.click('#selecteur-medias [data-grille] button.choisir >> nth=0');
  verifier((await p.inputValue('[data-couverture-champ]')).includes('essai-couverture'), 'champ couverture');
  await soumettre('form#form-article button[type=submit]');
  const h = await (await brut('/blog/reussir-son-lancement-a-lome')).text();
  verifier(h.includes('essai-couverture'), 'couverture non affichée');
});
await scenario('un fichier qui n’est pas une image est refusé', async () => {
  const f = join(tmp, 'piege.php.jpg'); writeFileSync(f, '<?php echo "x"; ?>');
  await p.goto(B + '/admin/medias');
  await p.setInputFiles('[data-depot] input[type=file]', f);
  await soumettre('[data-depot] button[type=submit]');
  verifier((await p.textContent('.msg.err')).includes('pas une image'), 'devrait être refusé');
});
await scenario('une campagne modifiée et masquée suit sur le site', async () => {
  await p.goto(B + '/admin/campagnes'); await cliquer('text=Lomé By Night — Concept Événementiel');
  await p.fill('#titre', 'Lomé By Night — Édition 2025');
  await p.uncheck('input[name=publie]');
  await soumettre('button:has-text("Enregistrer")');
  const h = await (await brut('/campagnes')).text();
  verifier(!h.includes('Lomé By Night'), 'devrait être masquée');
});

console.log('Réglages et comptes');
await scenario('les coordonnées réglées s’affichent partout', async () => {
  await p.goto(B + '/admin/reglages');
  await p.fill('#r-telephone', '+228 99 99 99 99');
  await soumettre('button:has-text("Enregistrer")');
  const h = await (await brut('/contact')).text();
  verifier(h.includes('+228 99 99 99 99') && h.includes('tel:+22899999999'), 'téléphone');
});
await scenario('le titre de l’accueil accepte la couleur et le contour', async () => {
  await p.goto(B + '/admin/reglages/accueil');
  await p.fill('#r-hero_titre', 'VOS CLIENTS\n*NE PEUVENT*\n_PAS IGNORER_');
  await soumettre('button:has-text("Enregistrer")');
  const h = await (await brut('/')).text();
  verifier(h.includes('<span class="gold">NE PEUVENT</span><br>') && h.includes('<span class="stroke">PAS IGNORER</span>'), 'mise en forme du titre');
});
await scenario('un compte éditeur est créé', async () => {
  await p.goto(B + '/admin/comptes');
  await p.click('summary:has-text("Nouveau compte")');
  await p.fill('#n-nom', 'Edem'); await p.fill('#n-email', 'edem@liveydream.com'); await p.fill('#n-mdp', 'editeur-2026-ok');
  await soumettre('button:has-text("Créer le compte")');
  verifier((await p.textContent('.tableau')).includes('edem@liveydream.com'), 'compte absent');
  await capture('admin-comptes');
});
await scenario('un mauvais mot de passe est refusé', async () => {
  await p.click('.menu button:has-text("Déconnexion")'); await p.waitForURL(/connexion/);
  await p.fill('#email', 'edem@liveydream.com'); await p.fill('#mdp', 'faux-mot-de-passe');
  await soumettre('button[type=submit]');
  verifier((await p.textContent('.msg.err')).includes('incorrect'), 'refus attendu');
});
await scenario('l’éditeur écrit mais ne règle pas le site', async () => {
  await p.fill('#mdp', 'editeur-2026-ok');
  await soumettre('button[type=submit]');
  verifier(!(await p.textContent('.barre')).includes('Système'), 'menu Système visible');
  await p.goto(B + '/admin/reglages');
  verifier(p.url().endsWith('/admin') && (await p.textContent('.msg.err')).includes('réservé'), 'accès aux réglages');
  await p.goto(B + '/admin/articles');
  verifier(await p.locator('text=Écrire un article').count() === 1, 'articles inaccessibles');
});

console.log('Sécurité');
await scenario('le code et la base ne sont jamais servis', async () => {
  for (const u of ['/app/bootstrap.php', '/donnees/lyd.sqlite', '/app/vues/site/blog.php', '/.htaccess']) {
    verifier((await brut(u)).status === 403, u);
  }
});
await scenario('une écriture sans jeton anti-falsification est refusée', async () => {
  const r = await brut('/contact', { method: 'POST', body: new URLSearchParams({ nom: 'x', email: 'a@b.tg', whatsapp: '1' }) });
  verifier(r.status === 419, 'statut ' + r.status);
});
await scenario('l’administration exige une connexion', async () => {
  const r = await brut('/admin/articles');
  verifier(r.status === 303 && r.headers.get('location').includes('/admin/connexion'), 'statut ' + r.status);
});

console.log('Téléphone');
const tel = await ctx.newPage();
await tel.setViewportSize({ width: 380, height: 800 });
for (const chemin of ['/', '/services', '/campagnes', '/blog', '/blog/article-numero-3', '/a-propos', '/contact', '/admin', '/admin/articles']) {
  await scenario(`${chemin} tient dans 380 px sans défilement horizontal`, async () => {
    await tel.goto(B + chemin, { waitUntil: 'load' });
    const debord = await tel.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    verifier(debord <= 1, 'déborde de ' + debord + ' px');
  });
}
await scenario('le menu du téléphone s’ouvre et se referme', async () => {
  await tel.goto(B + '/');
  await tel.click('#navToggle');
  verifier(await tel.isVisible('#navMenu a[href="/blog"]'), 'menu fermé');
  await tel.keyboard.press('Escape');
  verifier(!(await tel.isVisible('#navMenu a[href="/blog"]')), 'menu resté ouvert');
  await capture('mobile-accueil', tel);
});

console.log('Console');
await scenario('aucune erreur JavaScript sur tout le parcours', async () => {
  verifier(erreurs.length === 0, erreurs.slice(0, 5).join(' | '));
});

await navigateur.close();
console.log(`\n${ok} scénario(s) réussi(s), ${ko} en échec.`);
fin();
process.exit(ko ? 1 : 0);
