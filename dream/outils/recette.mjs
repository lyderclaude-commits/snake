// La recette du site Live Y Dream, dans un vrai navigateur.
//
//     node dream/outils/recette.mjs
//
// Elle copie `dream/site` dans un dossier temporaire (base vide), démarre
// le site et un faux WordPress avec le serveur intégré de PHP, puis joue
// chaque scénario. Rien n'est écrit dans le dépôt.
import { chromium } from 'playwright-core';
import { spawn } from 'node:child_process';
import { cpSync, mkdtempSync, mkdirSync, readdirSync, readFileSync, rmSync, writeFileSync, existsSync } from 'node:fs';
import { createServer } from 'node:net';
import { createServer as serveurHttp } from 'node:http';
import { createECDH, createDecipheriv, createPublicKey, hkdfSync, randomBytes, verify } from 'node:crypto';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ICI = dirname(fileURLToPath(import.meta.url));
const SITE = join(ICI, '..', 'site');
const PORT = 8180, PORT_WP = 8190;
const B = `http://127.0.0.1:${PORT}`, WP = `http://127.0.0.1:${PORT_WP}/wp-json/wp/v2`;
const CAPTURES = process.env.CAPTURES || '';

const tmp = mkdtempSync(join(tmpdir(), 'lyd-recette-'));
// Les e-mails remis par mail() (faux sendmail) et ceux reçus par le faux serveur SMTP.
const COURRIELS = mkdtempSync(join(tmpdir(), 'lyd-courriels-'));
const lettres = () => readdirSync(COURRIELS).sort().map((f) => readFileSync(join(COURRIELS, f), 'utf8'));
const decoder = (eml) => eml.replace(/=\?UTF-8\?B\?([^?]+)\?=/g, (_, b) => Buffer.from(b, 'base64').toString())
  + '\n' + [...eml.matchAll(/base64\r?\n\r?\n([A-Za-z0-9+/=\r\n]+)/g)].map((m) => Buffer.from(m[1].replace(/\s/g, ''), 'base64').toString()).join('\n');
const PORT_SMTP = 8125;
const smtp = [];
const serveurSmtp = createServer((c) => {
  const lettre = { de: '', a: [], auth: '', donnees: '' };
  let enDonnees = false, tampon = '', etape = '';
  c.write('220 faux-smtp ESMTP\r\n');
  c.on('data', (d) => {
    tampon += d.toString();
    let i;
    while ((i = tampon.indexOf('\r\n')) >= 0) {
      const l = tampon.slice(0, i); tampon = tampon.slice(i + 2);
      if (enDonnees) {
        if (l === '.') { enDonnees = false; smtp.push(lettre); c.write('250 2.0.0 accepté\r\n'); }
        else lettre.donnees += l + '\n';
        continue;
      }
      if (etape === 'u') { lettre.auth = Buffer.from(l, 'base64').toString(); etape = 'p'; c.write('334 UGFzc3dvcmQ6\r\n'); continue; }
      if (etape === 'p') { etape = ''; c.write(Buffer.from(l, 'base64').toString() === 'bon-mdp' ? '235 ok\r\n' : '535 identifiants refusés\r\n'); continue; }
      if (/^EHLO/i.test(l)) c.write('250-faux-smtp\r\n250 AUTH LOGIN\r\n');
      else if (/^AUTH LOGIN/i.test(l)) { etape = 'u'; c.write('334 VXNlcm5hbWU6\r\n'); }
      else if (/^MAIL FROM:/i.test(l)) { lettre.de = l.slice(10); c.write('250 ok\r\n'); }
      else if (/^RCPT TO:/i.test(l)) { lettre.a.push(l.slice(8).replace(/[<>]/g, '')); c.write('250 ok\r\n'); }
      else if (/^DATA/i.test(l)) { enDonnees = true; c.write('354 go\r\n'); }
      else if (/^QUIT/i.test(l)) { c.end('221 bye\r\n'); }
      else c.write('250 ok\r\n');
    }
  });
}).listen(PORT_SMTP, '127.0.0.1');
cpSync(SITE, tmp, { recursive: true, filter: (s) => !/donnees\/(lyd\.sqlite|sessions)|uploads\/\d{4}/.test(s) });
// DeepL est remplacé par un faux service local, monté plus bas : la copie de recette le dit dans son config.php.
const PORT_DEEPL = 8128;
writeFileSync(join(tmp, 'config.php'), `<?php return ['deepl_url' => 'http://127.0.0.1:${PORT_DEEPL}'];\n`);
const deepl = [];
const serveurDeepl = serveurHttp((req, res) => {
  const morceaux = [];
  req.on('data', (d) => morceaux.push(d));
  req.on('end', () => {
    const cle = String(req.headers.authorization || '').replace(/^DeepL-Auth-Key /, '');
    const repondre = (code, d) => { res.statusCode = code; res.setHeader('Content-Type', 'application/json'); res.end(JSON.stringify(d)); };
    if (!/^(cle-test|quota):fx$/.test(cle)) return repondre(403, { message: 'Authorization failed' });
    if (req.url === '/v2/usage') return repondre(200, { character_count: deepl.reduce((n, r) => n + r.caracteres, 0), character_limit: 500000 });
    if (req.url !== '/v2/translate' || req.method !== 'POST') return repondre(404, { message: 'Not found' });
    if (cle === 'quota:fx') return repondre(456, { message: 'Quota exceeded' });
    const corps = JSON.parse(Buffer.concat(morceaux).toString());
    deepl.push({ corps, caracteres: corps.text.join('').length });
    // « Traduire » : chaque texte entre deux balises reçoit « EN· », les balises restent intactes.
    const traduire = (t) => corps.tag_handling === 'html' && t.includes('<')
      ? t.replace(/>([^<]+)</g, (m, x) => (x.trim() ? '>EN·' + x + '<' : m)) : 'EN·' + t;
    repondre(200, { translations: corps.text.map((t) => ({ detected_source_language: 'FR', text: traduire(t) })) });
  });
}).listen(PORT_DEEPL, '127.0.0.1');
const serveurs = [
  spawn('php', ['-d', `sendmail_path=php ${join(ICI, 'capture-mail.php')}`, '-S', `127.0.0.1:${PORT}`, '-t', tmp, join(ICI, 'routeur-dev.php')],
    { stdio: 'ignore', env: { ...process.env, PHP_CLI_SERVER_WORKERS: '4', LYD_COURRIELS: COURRIELS } }),
  spawn('php', ['-S', `127.0.0.1:${PORT_WP}`, join(ICI, 'faux-wordpress', 'index.php')], { stdio: 'ignore' }),
];
const fin = () => { serveurs.forEach((s) => s.kill()); serveurSmtp.close(); rmSync(tmp, { recursive: true, force: true }); rmSync(COURRIELS, { recursive: true, force: true }); };
process.on('exit', fin);
await new Promise((r) => setTimeout(r, 900));

const navigateur = await chromium.launch(existsSync('/opt/pw-browsers/chromium') ? { executablePath: '/opt/pw-browsers/chromium' } : {});
// Un navigateur réglé en français : sinon l'accueil, qui suit la langue du navigateur, partirait vers /en.
const ctx = await navigateur.newContext({ viewport: { width: 1360, height: 900 }, locale: 'fr-FR' });
const p = await ctx.newPage();
const erreurs = [];
// Les ressources de tiers (YouTube, Unsplash) peuvent manquer selon le réseau : ce n'est pas une erreur du site.
const tiers = /youtube|unsplash|ERR_TUNNEL|ERR_NAME|ERR_CONNECTION|ERR_INTERNET|net::ERR_FAILED/;
p.on('pageerror', (e) => erreurs.push(p.url() + ' — ' + e));
p.on('console', (m) => { if (m.type() === 'error' && !tiers.test(m.text()) && !/status of (40[34]|422|502)/.test(m.text())) erreurs.push(p.url() + ' — ' + m.text()); });
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
  verifier((s.match(/<loc>/g) || []).length === 6 * 2 + 23, 'sitemap : ' + (s.match(/<loc>/g) || []).length);
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
  verifier((h.match(/class="pg-item/g) || []).length === 5, 'campagnes : ' + (h.match(/class="pg-item/g) || []).length);
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
await scenario('la demande part par e-mail à contact@liveydream.com, réponse vers le client', async () => {
  const l = lettres();
  verifier(l.length === 1, l.length + ' e-mail(s) remis');
  const m = decoder(l[0]);
  verifier(/^To: contact@liveydream\.com$/m.test(l[0]), 'destinataire : ' + (l[0].match(/^To: .*/m) || ['aucun'])[0]);
  verifier(/^Reply-To: afi@boutique\.tg$/m.test(l[0]), 'Reply-To');
  verifier(m.includes('Nouvelle demande : Afi Mensah — Community Management'), 'sujet');
  verifier(m.includes('Nous lançons une boutique.') && m.includes('+228 90 00 00 00') && m.includes('/admin/messages/1'), 'contenu');
  verifier(/-f\S+@/.test(l[0]), 'expéditeur d’enveloppe absent');
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
  verifier((await p.textContent('.msg.ok')).includes('envoyé') && (await p.textContent('.msg.ok')).includes('contact@liveydream.com'), 'statut d’envoi');
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

console.log('E-mails par SMTP');
await scenario('le destinataire contact@ est affiché comme fixe, les copies s’ajoutent', async () => {
  await p.click('.menu button:has-text("Déconnexion")'); await p.waitForURL(/connexion/);
  await p.fill('#email', 'admin@liveydream.com'); await p.fill('#mdp', 'motdepasse-solide');
  await soumettre('button[type=submit]');
  await p.goto(B + '/admin/reglages');
  verifier((await p.textContent('main')).includes('contact@liveydream.com — toujours'), 'mention du destinataire fixe');
  await p.fill('#r-contact_copies', 'direction@liveydream.com, pas-une-adresse');
  await p.fill('#r-smtp_hote', '127.0.0.1'); await p.fill('#r-smtp_port', String(PORT_SMTP));
  await p.selectOption('#r-smtp_chiffrement', 'aucun');
  await p.fill('#r-smtp_utilisateur', 'contact@liveydream.com'); await p.fill('#r-smtp_mdp', 'bon-mdp');
  await soumettre('button[name=tester]');
  verifier((await p.textContent('.msg.ok')).includes('E-mail de test envoyé'), await p.textContent('.msg'));
  const l = smtp.at(-1);
  verifier(l && l.a.join() === 'contact@liveydream.com,direction@liveydream.com', 'destinataires SMTP : ' + (l && l.a.join()));
  verifier(l.auth === 'contact@liveydream.com' && l.de === '<contact@liveydream.com>', 'authentification ou expéditeur');
  await capture('admin-reglages-courriel');
});
await scenario('le mot de passe SMTP n’est jamais réaffiché et reste gardé', async () => {
  verifier(await p.inputValue('#r-smtp_mdp') === '', 'mot de passe réaffiché');
  verifier(!(await p.content()).includes('bon-mdp'), 'mot de passe dans la page');
  await soumettre('button[name=tester]');
  verifier((await p.textContent('.msg.ok')).includes('E-mail de test envoyé'), 'mot de passe perdu');
});
await scenario('une nouvelle demande part par SMTP à contact@ et aux copies', async () => {
  const avant = smtp.length;
  const page = await ctx.newPage();
  await page.goto(B + '/contact');
  await page.fill('#c-nom', 'Kodjo Agbeko'); await page.fill('#c-mail', 'kodjo@exemple.tg');
  await page.fill('#c-wa', '+228 91 11 11 11'); await page.fill('#c-msg', 'Un devis pour un lancement.\n.Ligne qui commence par un point.');
  await Promise.all([page.waitForNavigation(), page.click('.form-submit-btn')]);
  await page.close();
  verifier(smtp.length === avant + 1, 'aucun e-mail SMTP');
  const l = smtp.at(-1), m = decoder(l.donnees);
  verifier(l.a.includes('contact@liveydream.com') && l.a.includes('direction@liveydream.com'), 'destinataires');
  verifier(/^Reply-To: kodjo@exemple\.tg$/m.test(l.donnees) && m.includes('Kodjo Agbeko'), 'contenu');
  verifier(m.includes('\n.Ligne qui commence par un point.'), 'la ligne commençant par un point est abîmée');
});
await scenario('un échec d’envoi est noté sur la demande, et se renvoie', async () => {
  await p.goto(B + '/admin/reglages');
  await p.fill('#r-smtp_mdp', 'mauvais-mdp');
  await soumettre('button[name=tester]');
  verifier((await p.textContent('.msg.err')).includes('identifiants refusés'), 'échec attendu');
  verifier(!(await p.textContent('.msg.err')).includes('mauvais-mdp'), 'le mot de passe apparaît dans l’erreur');
  const page = await ctx.newPage();
  await page.goto(B + '/contact');
  await page.fill('#c-nom', 'Essai Échec'); await page.fill('#c-mail', 'echec@exemple.tg'); await page.fill('#c-wa', '+228 92 22 22 22');
  await Promise.all([page.waitForNavigation(), page.click('.form-submit-btn')]);
  verifier(await page.isVisible('#formSuccess'), 'le visiteur doit voir sa demande acceptée même si l’e-mail échoue');
  await page.close();
  await p.goto(B + '/admin/messages');
  verifier((await p.textContent('tr:has-text("Essai Échec")')).includes('E-mail non parti'), 'pastille d’échec');
  await p.goto(B + '/admin/reglages');
  await p.fill('#r-smtp_mdp', 'bon-mdp');
  await soumettre('button:has-text("Enregistrer"):not([name])');
  await p.goto(B + '/admin/messages'); await cliquer('text=Essai Échec');
  verifier((await p.textContent('.msg.err')).includes('échec'), 'statut d’échec');
  const avant = smtp.length;
  await soumettre('button:has-text("Renvoyer par e-mail")');
  verifier((await p.textContent('.msg.ok')).includes('renvoyée') && smtp.length === avant + 1, 'renvoi');
  verifier((await p.textContent('.carte .msg.ok')).includes('envoyé'), 'statut mis à jour');
});

console.log('Bilingue');
const ROUTES_EN = { '/en': 'YOUR CLIENTS CAN', '/en/services': 'We don\'t sell', '/en/campaigns': 'Woodin Togo — Digital Domination',
  '/en/blog': 'Tips & strategies', '/en/about': 'Built to make others win', '/en/contact': 'Let\'s talk about your project',
  '/en/legal-notice': 'Website publisher', '/en/privacy': 'Your rights' };
for (const [chemin, attendu] of Object.entries(ROUTES_EN)) {
  await scenario(`${chemin} est en anglais, avec ses adresses jumelles`, async () => {
    const r = await brut(chemin);
    const h = (await r.text()).replace(/&#039;/g, "'").replace(/&amp;/g, '&');
    verifier(r.status === 200, 'statut ' + r.status);
    verifier(h.includes('<html lang="en"'), 'lang');
    verifier(h.includes(attendu), 'texte attendu : ' + attendu);
    verifier(h.includes('hreflang="fr"') && h.includes('hreflang="en"'), 'hreflang');
    verifier(!/>(Accueil|Campagnes|À Propos|Devis Gratuit|Demander un devis|Envoyer ma demande)</.test(h), 'reste du français : ' + (h.match(/>(Accueil|Campagnes|À Propos|Devis Gratuit|Demander un devis|Envoyer ma demande)</) || [])[0]);
    verifier(!/href="\/(services|campagnes|blog|a-propos|contact)["#?]/.test(h.split('<footer')[0].split('nav-langues')[0]), 'un lien de la page anglaise repart vers le français');
  });
}
await scenario('les adresses mal formées redirigent vers la bonne langue', async () => {
  for (const [de, vers] of [['/en/campagnes', '/en/campaigns'], ['/campaigns', '/en/campaigns'], ['/about', '/en/about'], ['/en/a-propos', '/en/about']]) {
    const r = await brut(de);
    verifier(r.status === 301 && r.headers.get('location').endsWith(vers), de + ' → ' + r.status + ' ' + r.headers.get('location'));
  }
});
await scenario('un navigateur anglais arrive sur /en ; le choix du français est ensuite retenu', async () => {
  const c = await navigateur.newContext({ locale: 'en-US' });
  const q = await c.newPage();
  await q.goto(B + '/');
  verifier(q.url() === B + '/en', 'obtenu ' + q.url());
  await Promise.all([q.waitForNavigation(), q.click('.nav-langues a[hreflang=fr]')]);
  verifier(q.url() === B + '/', 'le sélecteur doit mener au français : ' + q.url());
  await q.goto(B + '/');
  verifier(q.url() === B + '/' && await q.getAttribute('html', 'lang') === 'fr', 'le choix du français n’est pas retenu');
  await c.close();
});
await scenario('le sélecteur garde la page et ses paramètres', async () => {
  const c = await navigateur.newContext({ locale: 'fr-FR' });
  const q = await c.newPage();
  await q.goto(B + '/blog?categorie=Strat%C3%A9gie');
  await Promise.all([q.waitForNavigation(), q.click('.nav-langues a[hreflang=en]')]);
  verifier(q.url() === B + '/en/blog?categorie=Strat%C3%A9gie', 'obtenu ' + q.url());
  verifier((await q.textContent('.blog-filtre')).includes('in “Strategy”'), 'filtre en anglais');
  await c.close();
});
await scenario('un article non traduit s’affiche en français sur /en, et renvoie à l’original', async () => {
  const h = await (await brut('/en/blog/article-numero-3')).text();
  verifier(h.includes('This article is only available in French.'), 'mention absente');
  verifier(h.includes(`<link rel="canonical" href="${B}/blog/article-numero-3"`), 'canonique vers le français');
  verifier(!h.includes('hreflang="en"'), 'une page non traduite ne doit pas s’annoncer en anglais');
  verifier(/(January|February|March|April|May|June|July|August|September|October|November|December) \d{1,2}, 20\d\d/.test(h), 'date en anglais');
});
await scenario('traduire un article dans l’admin le publie en anglais', async () => {
  await p.goto(B + '/admin/articles?q=article-numero-3'); await cliquer('a.titre-ligne:text-is("Article n°3 – l’exemple")');
  await p.click('details.version-en > summary');
  await p.fill('#titre_en', 'Article 3 — the example');
  await p.fill('#extrait_en', 'The English summary.');
  await p.click('details.version-en .editeur-zone');
  await p.keyboard.type('The English text of the article.');
  await soumettre('form#form-article button[type=submit]');
  const en = await (await brut('/en/blog/article-numero-3')).text();
  verifier(en.includes('<h1 class="am-title">Article 3 — the example</h1>') && en.includes('The English text of the article.'), 'version anglaise');
  verifier(en.includes('hreflang="en"') && !en.includes('only available in French'), 'alternatives');
  const fr = await (await brut('/blog/article-numero-3')).text();
  verifier(fr.includes('Article n°3 – l’exemple') && !fr.includes('The English text'), 'le français a changé');
  verifier((await (await brut('/sitemap.xml')).text()).includes(`${B}/en/blog/article-numero-3`), 'plan du site');
});
await scenario('les campagnes ont leur anglais, filtres et fiche compris', async () => {
  const c = await navigateur.newContext({ locale: 'en-US' });
  const q = await c.newPage();
  await q.goto(B + '/en/campaigns');
  await q.click('.filter-btn[data-filter=event]');
  await q.click('#campaignGrid .cg-item >> nth=0');
  await q.waitForSelector('#caseModal.open');
  verifier((await q.textContent('#caseCat')) === 'Events' && (await q.textContent('#caseResults')).includes('Participants'), 'fiche');
  verifier((await q.textContent('#caseTitle')).includes('Battle Arena — International Competition'), 'titre : ' + await q.textContent('#caseTitle'));
  await c.close();
});
await scenario('le formulaire anglais répond en anglais et marque la demande EN', async () => {
  const c = await navigateur.newContext({ locale: 'en-US' });
  const q = await c.newPage();
  await q.goto(B + '/en/contact');
  await q.fill('#c-nom', 'John Smith'); await q.fill('#c-mail', 'pas-valide@x'); await q.fill('#c-wa', '+44 7700 900000');
  await q.evaluate(() => document.getElementById('c-mail').type = 'text');
  await Promise.all([q.waitForNavigation(), q.click('.form-submit-btn')]);
  verifier((await q.textContent('.form-erreur')).includes('does not look valid'), 'erreur en anglais');
  await q.fill('#c-mail', 'john@example.com');
  await q.selectOption('#c-besoin', 'Event Management');
  const avant = smtp.length;
  await Promise.all([q.waitForNavigation(), q.click('.form-submit-btn')]);
  verifier(q.url().startsWith(B + '/en/contact') && (await q.textContent('#formSuccess')).includes('Message sent!'), 'confirmation : ' + q.url());
  await c.close();
  verifier(smtp.length === avant + 1 && decoder(smtp.at(-1).donnees).includes('anglais'), 'l’e-mail doit dire que la demande vient du site anglais');
  await p.goto(B + '/admin/messages');
  verifier((await p.textContent('tr:has-text("John Smith")')).includes('EN'), 'pastille EN');
});

console.log('Traduction DeepL');
await scenario('sans clé, l’éditeur propose de brancher DeepL', async () => {
  await p.goto(B + '/admin/articles?q=article-numero-5'); await cliquer('a.titre-ligne:text-is("Article n°5 – l’exemple")');
  verifier(await p.locator('details.version-en a:has-text("Branchez DeepL")').count() === 1, 'lien absent');
  verifier(await p.locator('[data-traduire]').count() === 0, 'bouton présent sans clé');
});
await scenario('une clé refusée par DeepL est signalée', async () => {
  await p.goto(B + '/admin/traduction');
  await p.fill('#deepl_cle', 'mauvaise:fx');
  await soumettre('button:has-text("Enregistrer")');
  verifier((await p.textContent('.msg.err')).includes('DeepL refuse la clé'), await p.textContent('.msg'));
});
await scenario('une bonne clé : DeepL répond, la clé n’est jamais réaffichée', async () => {
  await p.fill('#deepl_cle', 'cle-test:fx');
  await soumettre('button:has-text("Enregistrer")');
  verifier((await p.textContent('.msg.ok')).includes('caractères utilisés ce mois-ci sur 500 000'), await p.textContent('.msg'));
  verifier(!(await p.content()).includes('cle-test:fx') && (await p.getAttribute('#deepl_cle', 'placeholder')).includes('offre gratuite'), 'clé visible');
  await p.click('[data-tester-deepl]');
  await p.waitForFunction(() => /DeepL répond|✗/.test(document.querySelector('[data-etat-deepl]').textContent));
});
await scenario('le bouton DeepL de l’éditeur traduit le formulaire, HTML intact', async () => {
  await p.goto(B + '/admin/articles?q=article-numero-5'); await cliquer('a.titre-ligne:text-is("Article n°5 – l’exemple")');
  await p.click('details.version-en > summary');
  const avant = deepl.length;
  await p.click('[data-traduire]');
  await p.waitForFunction(() => /✓|✗/.test(document.querySelector('[data-traduire-etat]').textContent));
  verifier((await p.textContent('[data-traduire-etat]')).includes('✓'), await p.textContent('[data-traduire-etat]'));
  verifier((await p.inputValue('#titre_en')) === 'EN·Article n°5 – l’exemple', 'titre : ' + await p.inputValue('#titre_en'));
  const html = await p.innerHTML('details.version-en .editeur-zone');
  verifier(html.includes('<h2>EN·Un h1 dans le contenu</h2>') && /<img[^>]+src="\/uploads\//.test(html), 'contenu : ' + html.slice(0, 200));
  const envoi = deepl.slice(avant);
  verifier(envoi.some((r) => r.corps.tag_handling === 'html') && envoi.every((r) => r.corps.source_lang === 'FR' && r.corps.target_lang === 'EN-GB'), 'requête DeepL');
  await soumettre('form#form-article button[type=submit]');
  const en = await (await brut('/en/blog/article-numero-5')).text();
  verifier(en.includes('<h1 class="am-title">EN·Article n°5 – l’exemple</h1>') && !en.includes('only available in French'), 'page anglaise');
  await p.goto(B + '/admin/articles?q=article-numero-5');
  verifier((await p.textContent('tbody')).includes('Auto · à relire'), 'mention « à relire »');
});
await scenario('cocher « relue » retire la mention de traduction automatique', async () => {
  await cliquer('a.titre-ligne:text-is("Article n°5 – l’exemple")');
  await p.check('input[name=traduction_relue]');
  await soumettre('form#form-article button[type=submit]');
  await p.goto(B + '/admin/articles?q=article-numero-5');
  verifier(!(await p.textContent('tbody')).includes('à relire') && (await p.textContent('tbody')).includes('Traduit'), 'toujours à relire');
});
await scenario('traduire tous les articles en une fois', async () => {
  await p.goto(B + '/admin/traduction');
  const n = parseInt(await p.textContent('[data-n-a-traduire]'), 10);
  verifier(n > 15, n + ' article(s) à traduire');
  await p.click('[data-lancer-traduction]');
  await p.waitForFunction(() => /Terminé|Arrêté/.test(document.querySelector('[data-etat-lot]').textContent), null, { timeout: 120000 });
  verifier((await p.textContent('[data-etat-lot]')).includes(n + ' article(s) traduit(s)'), await p.textContent('[data-etat-lot]'));
  const blog = await (await brut('/en/blog')).text();
  verifier(!blog.includes('class="article-lang"') && blog.includes('EN·'), 'des articles restent en français sur /en/blog');
  verifier(((await (await brut('/sitemap.xml')).text()).match(/\/en\/blog\//g) || []).length >= n + 2, 'plan du site');
  await p.goto(B + '/admin');
  verifier((await p.textContent('.bord-file')).includes('traduction(s) automatique(s) DeepL à relire'), 'tableau de bord');
  await capture('admin-traduction');
});
await scenario('traduction à la publication, et une panne DeepL ne bloque jamais la publication', async () => {
  await p.goto(B + '/admin/traduction');
  await p.check('input[name=deepl_auto]');
  await soumettre('button:has-text("Enregistrer")');
  const ecrire = async (titre) => {
    await p.goto(B + '/admin/articles/nouveau');
    await p.fill('#titre', titre);
    await p.click('.editeur-zone >> nth=0'); await p.keyboard.type('Texte à traduire.');
    await p.selectOption('#statut', 'publie');
    await soumettre('form#form-article button[type=submit]');
    return p.textContent('.msg');
  };
  verifier((await ecrire('Publié et traduit')).includes('traduite par DeepL'), 'traduction à la publication');
  verifier((await (await brut('/en/blog/publie-et-traduit')).text()).includes('EN·Publié et traduit'), 'page anglaise');
  await p.goto(B + '/admin/traduction');
  await p.fill('#deepl_cle', 'quota:fx');
  await soumettre('button:has-text("Enregistrer")');
  const msg = await ecrire('Publié malgré la panne');
  verifier(msg.includes('en ligne') && msg.includes('quota DeepL du mois est épuisé'), msg);
  verifier((await brut('/blog/publie-malgre-la-panne')).status === 200, 'l’article n’est pas en ligne');
  await p.goto(B + '/admin/traduction');
  await p.click('[data-lancer-traduction]');
  await p.waitForFunction(() => /Terminé|Arrêté/.test(document.querySelector('[data-etat-lot]').textContent));
  verifier((await p.textContent('[data-etat-lot]')).includes('quota'), 'arrêt sur quota : ' + await p.textContent('[data-etat-lot]'));
  await p.fill('#deepl_cle', 'cle-test:fx'); await p.uncheck('input[name=deepl_auto]');
  await soumettre('button:has-text("Enregistrer")');
  serveurDeepl.close();
});

console.log('Accueil réglable');
await scenario('modifier un service change l’accueil, la page Services et le pied, en deux langues', async () => {
  await p.goto(B + '/admin/services'); await cliquer('a.titre-ligne:text-is("Production Vidéo & Photo")');
  await p.fill('#s-fr-titre', 'Production Vidéo Premium');
  await p.fill('#s-en-titre', 'Premium Video Production');
  await p.fill('[name="fr[livrables][titre][]"] >> nth=4', 'Drone');
  await p.fill('[name="fr[livrables][texte][]"] >> nth=4', 'Prises de vue aériennes');
  await soumettre('button:has-text("Enregistrer")');
  verifier((await p.textContent('.msg.ok')).includes('enregistré'), 'enregistrement');
  const fr = await (await brut('/')).text(), en = await (await brut('/en')).text();
  verifier(fr.includes('<h3>Production Vidéo Premium</h3>') && en.includes('<h3>Premium Video Production</h3>'), 'carte de l’accueil');
  const sv = await (await brut('/services')).text();
  verifier(sv.includes('<h5>Drone</h5>') && sv.includes('>Production Vidéo Premium</a></li>'), 'section détaillée ou pied');
});
await scenario('masquer un service le retire partout', async () => {
  await p.goto(B + '/admin/services'); await cliquer('a.titre-ligne:text-is("Production Vidéo Premium")');
  await p.uncheck('input[name=visible]');
  await soumettre('button:has-text("Enregistrer")');
  for (const u of ['/', '/services', '/en']) verifier(!/Production Vidéo Premium|Premium Video Production/.test(await (await brut(u)).text()), u);
  await p.check('input[name=visible]');
  await soumettre('button:has-text("Enregistrer")');
});
await scenario('un témoignage ajouté paraît sur l’accueil, traduit ; masqué, il disparaît', async () => {
  await p.goto(B + '/admin/temoignages/nouveau');
  await p.fill('#entreprise', 'Banque Exemple');
  await p.fill('#t-fr', 'Une équipe qui livre à l’heure.'); await p.fill('#t-en', 'A team that delivers on time.');
  await p.fill('#a-fr', 'Directrice'); await p.fill('#a-en', 'Director');
  await soumettre('button:has-text("Enregistrer")');
  verifier((await (await brut('/')).text()).includes('Une équipe qui livre à l’heure.'), 'français');
  verifier((await (await brut('/en')).text()).includes('A team that delivers on time.'), 'anglais');
  await p.uncheck('input[name=visible]');
  await soumettre('button:has-text("Enregistrer")');
  verifier(!(await (await brut('/')).text()).includes('Banque Exemple'), 'toujours visible');
});
await scenario('un logo client s’ajoute depuis la médiathèque', async () => {
  await p.goto(B + '/admin/clients');
  await p.click('summary:has-text("Ajouter un client")');
  await p.fill('#c-nom', 'NOUVEAU CLIENT');
  await p.click('[data-choisir-couverture]');
  await p.waitForSelector('#selecteur-medias [data-grille] .media');
  await p.click('#selecteur-medias [data-grille] button.choisir >> nth=0');
  await soumettre('button:has-text("Ajouter")');
  verifier((await p.textContent('.msg.ok')).includes('NOUVEAU CLIENT'), 'ajout');
  const h = await (await brut('/')).text();
  verifier(/<img src="\/uploads\/[^"]+" alt="Logo NOUVEAU CLIENT"/.test(h), 'logo absent de l’accueil');
  verifier(h.includes('>UBA<'), 'un client sans logo garde son nom');
});
await scenario('le titre anglais de l’accueil se règle', async () => {
  await p.goto(B + '/admin/reglages/accueil');
  await p.fill('#r-hero_titre_en', 'BE SEEN\n*EVERYWHERE*');
  await soumettre('button:has-text("Enregistrer")');
  verifier(/BE SEEN<br>\s*<span class="gold">EVERYWHERE<\/span>/.test(await (await brut('/en')).text()), 'titre anglais');
});

console.log('Lectures');
const lectures = async (slug) => {
  await p.goto(B + '/admin/articles?q=' + slug);
  return parseInt(await p.textContent(`tr:has(.sous:text-is("${slug}")) td.chiffre`), 10);
};
await scenario('une lecture compte une fois par onglet, équipe connectée comprise', async () => {
  const avant = await lectures('article-numero-4');
  await p.goto(B + '/blog/article-numero-4'); await p.waitForTimeout(1600);
  await p.reload(); await p.waitForTimeout(1600);
  const onglet = await ctx.newPage();
  await onglet.goto(B + '/en/blog/article-numero-4'); await onglet.waitForTimeout(1600);
  await onglet.close();
  const apres = await lectures('article-numero-4');
  verifier(apres === avant + 2, `attendu ${avant + 2}, obtenu ${apres}`);
});
await scenario('les robots et les aperçus de liens ne comptent pas', async () => {
  const avant = await lectures('article-numero-4');
  for (const ua of ['Googlebot/2.1', 'facebookexternalhit/1.1', 'WhatsApp/2.23']) {
    const r = await brut('/blog/article-numero-4/vue', { method: 'POST', headers: { 'User-Agent': ua } });
    verifier(r.status === 204, 'statut ' + r.status);
  }
  verifier(await lectures('article-numero-4') === avant, 'un robot a été compté');
});

console.log('Notifications push');
const PORT_PUSH = 8126, recus = [];
const serveurPush = serveurHttp((req, res) => {
  const morceaux = [];
  req.on('data', (d) => morceaux.push(d));
  req.on('end', () => {
    recus.push({ chemin: req.url, entetes: req.headers, corps: Buffer.concat(morceaux) });
    res.statusCode = req.url.includes('mort') ? 410 : 201;
    res.end();
  });
}).listen(PORT_PUSH, '127.0.0.1');
// Un « navigateur » abonné : sa paire de clés et son secret, comme les fabrique pushManager.subscribe().
const nav = createECDH('prime256v1'); nav.generateKeys();
const navAuth = randomBytes(16);
const dechiffrer = (corps) => {
  const sel = corps.subarray(0, 16), lg = corps[20], cleServeur = corps.subarray(21, 21 + lg), bloc = corps.subarray(21 + lg);
  const partage = nav.computeSecret(cleServeur);
  const info = Buffer.concat([Buffer.from('WebPush: info\0'), nav.getPublicKey(), cleServeur]);
  const prk = Buffer.from(hkdfSync('sha256', partage, navAuth, info, 32));
  const cek = Buffer.from(hkdfSync('sha256', prk, sel, Buffer.from('Content-Encoding: aes128gcm\0'), 16));
  const nonce = Buffer.from(hkdfSync('sha256', prk, sel, Buffer.from('Content-Encoding: nonce\0'), 12));
  const d = createDecipheriv('aes-128-gcm', cek, nonce);
  d.setAuthTag(bloc.subarray(bloc.length - 16));
  const clair = Buffer.concat([d.update(bloc.subarray(0, bloc.length - 16)), d.final()]);
  return JSON.parse(clair.subarray(0, clair.lastIndexOf(2)).toString());
};
const abonner = (endpoint, cle) => p.evaluate(async ([endpoint, p256dh, auth]) => {
  const f = new FormData(); f.append('endpoint', endpoint); f.append('p256dh', p256dh); f.append('auth', auth); f.append('_csrf', document.body.dataset.csrf);
  return (await fetch('/admin/push/abonner', { method: 'POST', body: f })).json();
}, [endpoint, cle, navAuth.toString('base64url')]);
await scenario('le service worker et la clé VAPID sont servis, la clé ne change pas', async () => {
  const sw = await brut('/sw.js');
  verifier(sw.status === 200 && /javascript/.test(sw.headers.get('content-type')), 'sw.js');
  await p.goto(B + '/admin/profil');
  verifier(await p.locator('[data-push]').count() === 1, 'carte Notifications absente du profil');
  const cle = () => p.evaluate(async () => (await (await fetch('/admin/push/cle')).json()).cle);
  const a = await cle(), b = await cle();
  verifier(a && a === b && Buffer.from(a, 'base64url').length === 65, 'clé VAPID');
});
await scenario('une nouvelle demande arrive, chiffrée, sur l’appareil abonné', async () => {
  const r = await abonner(`http://127.0.0.1:${PORT_PUSH}/push/ok`, nav.getPublicKey().toString('base64url'));
  verifier(r.ok, JSON.stringify(r));
  const avant = recus.length;
  const q = await ctx.newPage();
  await q.goto(B + '/contact');
  await q.fill('#c-nom', 'Push Testeur'); await q.fill('#c-mail', 'push@exemple.tg'); await q.fill('#c-wa', '+228 93 33 33 33');
  await q.selectOption('#c-besoin', 'Régie Publicitaire'); await q.fill('#c-msg', 'Notification attendue.');
  await Promise.all([q.waitForNavigation(), q.click('.form-submit-btn')]);
  await q.close();
  verifier(recus.length === avant + 1, (recus.length - avant) + ' notification(s) reçue(s)');
  const m = recus.at(-1);
  verifier(m.entetes['content-encoding'] === 'aes128gcm' && m.entetes.urgency === 'high', 'en-têtes');
  const charge = dechiffrer(m.corps);
  verifier(charge.titre === 'Nouvelle demande : Push Testeur', 'titre : ' + charge.titre);
  verifier(charge.corps.includes('Régie Publicitaire') && /^\/admin\/messages\/\d+$/.test(charge.lien), 'contenu : ' + JSON.stringify(charge));
  // Le jeton VAPID : signé par la clé annoncée, pour l'origine du service de push.
  const [, jeton, k] = /vapid t=([^,]+), k=(\S+)/.exec(m.entetes.authorization);
  const [e64, c64, s64] = jeton.split('.');
  const pub = Buffer.from(k, 'base64url');
  const cle = createPublicKey({ key: { kty: 'EC', crv: 'P-256', x: pub.subarray(1, 33).toString('base64url'), y: pub.subarray(33).toString('base64url') }, format: 'jwk' });
  verifier(verify('sha256', Buffer.from(e64 + '.' + c64), { key: cle, dsaEncoding: 'ieee-p1363' }, Buffer.from(s64, 'base64url')), 'signature VAPID invalide');
  verifier(JSON.parse(Buffer.from(c64, 'base64url')).aud === `http://127.0.0.1:${PORT_PUSH}`, 'audience');
});
await scenario('un appareil disparu (410) est retiré, le test le dit', async () => {
  const autre = createECDH('prime256v1'); autre.generateKeys();
  verifier((await abonner(`http://127.0.0.1:${PORT_PUSH}/push/mort`, autre.getPublicKey().toString('base64url'))).ok, 'abonnement');
  await p.goto(B + '/admin/profil');
  verifier(await p.locator('.appareils li').count() === 2, 'deux appareils attendus');
  await soumettre('button:has-text("Envoyer une notification de test")');
  verifier((await p.textContent('.msg.ok')).includes('1 notification(s) envoyée(s)') && (await p.textContent('.msg.ok')).includes('retiré'), await p.textContent('.msg'));
  verifier(await p.locator('.appareils li').count() === 1, 'l’appareil mort est resté');
  verifier((await p.textContent('.appareils')).includes('reçu par le service'), 'dernier résultat');
});
await scenario('un abonnement incomplet est refusé', async () => {
  const r = await p.evaluate(async () => {
    const f = new FormData(); f.append('endpoint', 'https://exemple.tg/x'); f.append('p256dh', 'abc'); f.append('auth', 'abc'); f.append('_csrf', document.body.dataset.csrf);
    return (await fetch('/admin/push/abonner', { method: 'POST', body: f })).status;
  });
  verifier(r === 422, 'statut ' + r);
  serveurPush.close();
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
for (const chemin of ['/', '/services', '/campagnes', '/blog', '/blog/article-numero-3', '/a-propos', '/contact', '/en', '/en/services', '/en/contact',
  '/admin', '/admin/articles', '/admin/services/1', '/admin/clients', '/admin/profil']) {
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
  verifier(await tel.isVisible('#navMenu .nav-langues a[hreflang=en]'), 'le sélecteur de langue manque au menu du téléphone');
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
