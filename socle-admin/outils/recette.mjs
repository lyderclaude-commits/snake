// La recette du socle d'administration, jouée sur le site exemple, dans un vrai navigateur.
//
//     node socle-admin/outils/recette.mjs
//
// Elle copie `socle-admin/exemple` (socle compris) dans un dossier temporaire,
// démarre le serveur intégré de PHP avec un faux `sendmail`, puis joue chaque
// scénario sur une installation neuve. Rien n'est écrit dans le dépôt.
import { chromium } from 'playwright-core';
import { spawn } from 'node:child_process';
import { cpSync, mkdtempSync, readdirSync, readFileSync, rmSync, writeFileSync, existsSync, openSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const ICI = dirname(fileURLToPath(import.meta.url));
const SITE = join(ICI, '..', 'exemple');
const PORT = 8280;
const B = `http://127.0.0.1:${PORT}`;
const CAPTURES = process.env.CAPTURES || '';

const tmp = mkdtempSync(join(tmpdir(), 'socle-recette-'));
const COURRIELS = mkdtempSync(join(tmpdir(), 'socle-courriels-'));
const lettres = () => readdirSync(COURRIELS).sort().map((f) => readFileSync(join(COURRIELS, f), 'utf8'));
const decoder = (eml) => eml.replace(/=\?UTF-8\?B\?([^?]+)\?=/g, (_, b) => Buffer.from(b, 'base64').toString())
  + '\n' + [...eml.matchAll(/base64\r?\n\r?\n([A-Za-z0-9+/=\r\n]+)/g)].map((m) => Buffer.from(m[1].replace(/\s/g, ''), 'base64').toString()).join('\n');

// `dereference` : dans le dépôt, `exemple/socle` est un lien vers `../socle` ; la copie reçoit les vrais fichiers.
cpSync(SITE, tmp, { recursive: true, dereference: true, filter: (s) => !/donnees\/|uploads\/\d{4}/.test(s) });
const serveur = spawn('php', ['-d', `sendmail_path=php ${join(ICI, 'capture-mail.php')}`, '-S', `127.0.0.1:${PORT}`, '-t', tmp, join(ICI, 'routeur-dev.php')],
  { stdio: ['ignore', 'ignore', process.env.JOURNAL ? openSync(process.env.JOURNAL, 'w') : 'ignore'], env: { ...process.env, PHP_CLI_SERVER_WORKERS: '4', COURRIELS_RECETTE: COURRIELS } });
const fin = () => { serveur.kill(); rmSync(tmp, { recursive: true, force: true }); rmSync(COURRIELS, { recursive: true, force: true }); };
process.on('exit', fin);
await new Promise((r) => setTimeout(r, 800));

const navigateur = await chromium.launch(existsSync('/opt/pw-browsers/chromium') ? { executablePath: '/opt/pw-browsers/chromium' } : {});
const ctx = await navigateur.newContext({ viewport: { width: 1360, height: 900 }, locale: 'fr-FR' });
const p = await ctx.newPage();
const erreurs = [];
p.on('pageerror', (e) => erreurs.push(p.url() + ' — ' + e));
p.on('console', (m) => { if (m.type() === 'error' && !/status of (40[34]|422)/.test(m.text())) erreurs.push(p.url() + ' — ' + m.text()); });
p.on('dialog', (d) => d.accept());

let ok = 0, ko = 0;
async function scenario(nom, f) {
  try { await f(); ok++; console.log('  ✓', nom); }
  catch (e) { ko++; console.log('  ✗', nom, '\n     ', String(e.message || e).split('\n')[0]); }
}
function verifier(c, msg) { if (!c) throw new Error(msg); }
const brut = (u, o = {}) => fetch(B + u, { redirect: 'manual', ...o });
const soumettre = async (sel) => { await Promise.all([p.waitForNavigation(), p.click(sel)]); };
const capture = async (nom) => { if (CAPTURES) await p.screenshot({ path: join(CAPTURES, 'socle-' + nom + '.png') }); };

console.log('Installation');
await scenario('une installation neuve mène au premier compte, puis au tableau de bord', async () => {
  await p.goto(B + '/admin');
  verifier(p.url().endsWith('/admin/premier-compte'), 'obtenu ' + p.url());
  await p.fill('#nom', 'Awa'); await p.fill('#email', 'awa@exemple.com');
  await p.fill('#mdp', 'motdepasse-solide'); await p.fill('#mdp2', 'motdepasse-solide');
  await soumettre('button[type=submit]');
  // Sans module WordPress, pas de détour par l'import.
  verifier(p.url().endsWith('/admin') && (await p.textContent('.msg.ok')).includes('Bienvenue'), 'obtenu ' + p.url());
  await capture('bord');
});
await scenario('le menu suit la définition : collections, sans import ni traduction', async () => {
  const menu = await p.textContent('.barre');
  for (const m of ['Articles', 'Réalisations', 'Questions fréquentes', 'Médiathèque', 'Messages', 'Réglages', 'Comptes']) verifier(menu.includes(m), 'manque ' + m);
  for (const m of ['Import WordPress', 'Traduction', 'Campagnes']) verifier(!menu.includes(m), 'ne devrait pas avoir ' + m);
});
await scenario('la couleur de la marque est appliquée, le manifeste aussi', async () => {
  const h = await p.content();
  verifier(h.includes('--primary:#0D9488'), 'couleur');
  const m = await (await brut('/admin/manifest.json')).json();
  verifier(m.theme_color === '#0D9488' && m.name.includes('Atelier Exemple'), JSON.stringify(m));
});
await scenario('le contenu de départ est posé une seule fois', async () => {
  const h = await (await brut('/')).text();
  verifier(h.includes('Refonte d’une marque de jus') && h.includes('+60 %'), 'réalisations absentes');
});

console.log('Collections');
await scenario('un champ obligatoire vide est refusé, la saisie est gardée', async () => {
  await p.goto(B + '/admin/realisations/nouveau');
  await p.fill('#client', 'Client Gardé');
  await p.evaluate(() => document.getElementById('titre').removeAttribute('required'));
  await soumettre('button:has-text("Enregistrer")');
  verifier((await p.textContent('.msg.err')).includes('« Titre » est obligatoire'), 'erreur');
  verifier(await p.inputValue('#client') === 'Client Gardé', 'saisie perdue');
});
await scenario('une réalisation créée, avec ses chiffres et son image, paraît sur l’accueil', async () => {
  const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAIAAAD91JpzAAAAFklEQVR4nGP8z8DAwMDAxMDAwMDAAAANHQEDasKb6QAAAABJRU5ErkJggg==', 'base64');
  const f = join(tmp, 'projet-phare.png'); writeFileSync(f, png);
  await p.fill('#titre', 'Application de livraison');
  await p.fill('#resume', 'Commander en trois gestes.');
  await p.fill('#chiffres-0-valeur', '10 000'); await p.fill('#chiffres-0-libelle', 'téléchargements');
  await p.fill('#lien', 'https://exemple.com/app');
  await p.click('[data-choisir-couverture]');
  await p.setInputFiles('#selecteur-medias [data-televerser]', f);
  await p.waitForFunction(() => document.querySelector('[data-couverture-champ]').value.includes('projet-phare'));
  await soumettre('button:has-text("Enregistrer")');
  verifier((await p.textContent('.msg.ok')).includes('Réalisation enregistrée'), await p.textContent('.msg'));
  const h = await (await brut('/')).text();
  verifier(h.includes('Application de livraison') && h.includes('<b>10 000</b> téléchargements') && /src="\/uploads\/\d{4}\/\d\d\/projet-phare/.test(h), 'accueil');
  await capture('fiche');
});
await scenario('une adresse sans https:// est refusée, la valeur n’est pas rangée', async () => {
  await p.fill('#lien', 'javascript:alert(1)');
  await soumettre('button:has-text("Enregistrer")');
  verifier(!(await (await brut('/')).text()).includes('javascript:'), 'lien dangereux publié');
});
await scenario('l’ordre se règle depuis la liste, « à la une » reste en tête', async () => {
  await p.goto(B + '/admin/realisations');
  await capture('liste');
  const lignes = await p.locator('tbody tr').count();
  verifier(lignes === 3, lignes + ' lignes');
  const ids = await p.$$eval('input[name^="ordre["]', (l) => l.map((i) => i.name));
  await p.fill(`input[name="${ids[2]}"]`, '-5');
  await soumettre('button:has-text("Enregistrer l’ordre")');
  const h = await (await brut('/')).text();
  verifier(h.indexOf('Refonte d’une marque de jus') < h.indexOf(await p.textContent('tbody tr:nth-child(2) .titre-ligne')), 'la réalisation à la une doit rester première');
});
await scenario('masquer une réalisation la retire du site, la supprimer la retire de la liste', async () => {
  await p.goto(B + '/admin/realisations');
  await Promise.all([p.waitForNavigation(), p.click('a.titre-ligne:text-is("Site d’une clinique")')]);
  await p.uncheck('input[name=visible]');
  await soumettre('button:has-text("Enregistrer")');
  verifier(!(await (await brut('/')).text()).includes('Site d’une clinique'), 'toujours visible');
  await soumettre('button:has-text("Supprimer")');
  verifier((await p.textContent('.msg.ok')).includes('supprimée') && !(await p.textContent('.tableau')).includes('clinique'), 'suppression');
});
await scenario('la recherche du tableau de bord trouve les éléments des collections', async () => {
  await p.goto(B + '/admin/recherche?q=livraison');
  verifier((await p.textContent('.bord-file')).includes('Application de livraison'), 'introuvable');
});
await scenario('une question fréquente s’ajoute à la page Contact', async () => {
  await p.goto(B + '/admin/faq/nouveau');
  await p.fill('#question', 'Acceptez-vous les petits budgets ?');
  await p.fill('#reponse', 'Oui, avec une offre de départ.');
  await soumettre('button:has-text("Enregistrer")');
  verifier((await (await brut('/contact')).text()).includes('Acceptez-vous les petits budgets ?'), 'absente');
});

console.log('Réglages');
await scenario('un réglage déclaré s’affiche sur le site', async () => {
  await p.goto(B + '/admin/reglages');
  await p.fill('#r-slogan', 'Des sites qui tiennent leurs promesses.');
  await soumettre('button:has-text("Enregistrer")');
  verifier((await (await brut('/')).text()).includes('Des sites qui tiennent leurs promesses.'), 'slogan');
  await capture('reglages');
});
await scenario('une adresse de réglage invalide est refusée', async () => {
  // Le navigateur bloque déjà la saisie ; on le contourne pour éprouver le contrôle du serveur.
  await p.evaluate(() => { document.getElementById('r-site_url').type = 'text'; });
  await p.fill('#r-site_url', 'exemple.com');
  await soumettre('button:has-text("Enregistrer")');
  verifier((await p.textContent('.msg.err')).includes('doit commencer par https://'), 'refus attendu');
});
await scenario('l’onglet E-mails du socle est là, sans réglage à déclarer', async () => {
  await p.goto(B + '/admin/reglages/courriel');
  verifier((await p.textContent('main')).includes('contact@exemple.com — toujours'), 'destinataire fixe');
  verifier(await p.locator('#r-smtp_hote').count() === 1, 'SMTP');
});

console.log('Contact');
await scenario('une demande du formulaire est rangée, envoyée par e-mail, et listée avec son sujet', async () => {
  const q = await ctx.newPage();
  await q.goto(B + '/contact');
  await q.fill('#c-nom', 'Kofi'); await q.fill('#c-email', 'kofi@client.tg'); await q.fill('#c-sujet', 'Un site vitrine');
  await q.fill('#c-message', 'Bonjour, un devis ?');
  await Promise.all([q.waitForNavigation(), q.click('button:has-text("Envoyer")')]);
  verifier(await q.isVisible('#envoye'), 'confirmation');
  await q.close();
  const l = lettres().at(-1) || '';
  verifier(l.includes('contact@exemple.com') && decoder(l).includes('Un site vitrine') && /Reply-To: kofi@client\.tg/.test(l), 'e-mail');
  await p.goto(B + '/admin/messages');
  verifier((await p.textContent('thead')).includes('Sujet') && (await p.textContent('tbody')).includes('Un site vitrine'), 'liste');
  await Promise.all([p.waitForNavigation(), p.click('text=Kofi')]);
  verifier((await p.textContent('.fiche')).includes('kofi@client.tg') && !(await p.textContent('.fiche')).includes('Langue'), 'fiche');
});
await scenario('un champ obligatoire manquant est signalé au visiteur', async () => {
  const q = await ctx.newPage();
  await q.goto(B + '/contact');
  await q.fill('#c-nom', 'Sans Message'); await q.fill('#c-email', 'sm@client.tg');
  await q.evaluate(() => document.getElementById('c-message').removeAttribute('required'));
  await Promise.all([q.waitForNavigation(), q.click('button:has-text("Envoyer")')]);
  verifier((await q.textContent('.erreur')).includes('Message'), await q.textContent('body'));
  await q.close();
});

console.log('Blog');
await scenario('un article écrit dans l’éditeur paraît sur le blog et compte ses lectures', async () => {
  await p.goto(B + '/admin/articles/nouveau');
  verifier(await p.locator('details.version-en').count() === 0, 'pas de version anglaise sur un site en français seul');
  await p.fill('#titre', 'Notre méthode');
  await p.click('.editeur-zone'); await p.keyboard.type('Écouter, dessiner, livrer.');
  await p.selectOption('#statut', 'publie');
  await soumettre('form#form-article button[type=submit]');
  verifier((await (await brut('/blog')).text()).includes('Notre méthode'), 'blog');
  await p.goto(B + '/blog/notre-methode'); await p.waitForTimeout(1600);
  await p.goto(B + '/admin/articles');
  verifier((await p.textContent('tbody td.chiffre')).trim() === '1', 'lecture non comptée');
});

console.log('Sécurité');
await scenario('le socle, la définition et la base ne sont jamais servis', async () => {
  for (const u of ['/socle/demarrer.php', '/socle/noyau/base.php', '/socle/vues/bord.php', '/app/definition.php', '/donnees/site.sqlite']) {
    verifier((await brut(u)).status === 403, u);
  }
  verifier((await brut('/socle/assets/admin.css')).status === 200, 'les styles du socle doivent être servis');
});
await scenario('l’administration exige une connexion, et un jeton pour écrire', async () => {
  const r = await brut('/admin/realisations');
  verifier(r.status === 303 && r.headers.get('location').includes('/admin/connexion'), 'statut ' + r.status);
  verifier((await brut('/contact', { method: 'POST', body: new URLSearchParams({ nom: 'x', email: 'a@b.tg' }) })).status === 419, 'jeton');
});
await scenario('un éditeur ne voit pas le groupe Système', async () => {
  await p.goto(B + '/admin/comptes');
  await p.click('summary:has-text("Nouveau compte")');
  await p.fill('#n-nom', 'Yao'); await p.fill('#n-email', 'yao@exemple.com'); await p.fill('#n-mdp', 'editeur-2026-ok');
  await soumettre('button:has-text("Créer le compte")');
  const c = await navigateur.newContext({ locale: 'fr-FR' });
  const q = await c.newPage();
  await q.goto(B + '/admin/connexion');
  await q.fill('#email', 'yao@exemple.com'); await q.fill('#mdp', 'editeur-2026-ok');
  await Promise.all([q.waitForNavigation(), q.click('button[type=submit]')]);
  verifier(!(await q.textContent('.barre')).includes('Système') && (await q.textContent('.barre')).includes('Réalisations'), 'menu');
  await c.close();
});

console.log('Téléphone');
const tel = await ctx.newPage();
await tel.setViewportSize({ width: 380, height: 800 });
for (const chemin of ['/', '/contact', '/admin', '/admin/realisations', '/admin/realisations/nouveau', '/admin/reglages', '/admin/messages']) {
  await scenario(`${chemin} tient dans 380 px`, async () => {
    await tel.goto(B + chemin, { waitUntil: 'load' });
    const debord = await tel.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    verifier(debord <= 1, 'déborde de ' + debord + ' px');
  });
}

await scenario('aucune erreur JavaScript sur tout le parcours', async () => {
  verifier(erreurs.length === 0, erreurs.slice(0, 5).join(' | '));
});

await navigateur.close();
console.log(`\n${ok} scénario(s) réussi(s), ${ko} en échec.`);
fin();
process.exit(ko ? 1 : 0);
