/**
 * Le référencement, mesuré comme le mesure un robot.
 *
 * Ce vérifieur existe à cause d'un défaut précis, et sa forme en découle.
 * L'adresse canonique était RECONSTRUITE en ne gardant que `p` et `slug` —
 * or un article s'identifie par `a`. Chaque article annonçait donc l'index
 * du blog comme sa propre adresse. WhatsApp rendait une carte nue, et
 * Google lisait « doublon de /blog » : aucun article ne pouvait remonter.
 *
 * Rien de tout cela ne se voit à l'écran. C'est le genre de panne qu'on
 * découvre des mois plus tard, en se demandant pourquoi le blog ne ramène
 * personne. On la mesure donc, page par page, en demandant les pages.
 *
 *   npx tsx scripts/verifier-seo.ts
 *   BASE_URL=http://127.0.0.1:3700 npx tsx scripts/verifier-seo.ts
 */

const BASE = process.env.BASE_URL ?? 'http://127.0.0.1:3600';

let pass = 0;
let fail = 0;
const ok = (label: string, cond: boolean, detail = '') => {
  cond ? pass++ : fail++;
  console.log(`  ${cond ? '✓' : '✗'} ${label}${detail ? ' — ' + detail : ''}`);
};

const entre = (h: string, re: RegExp): string => (re.exec(h) ?? [, ''])[1] ?? '';

/** Une balise `<meta>`, quel que soit l'ordre de ses attributs. */
const meta = (html: string, cle: string): string => {
  const par = new RegExp(
    `<meta[^>]+(?:property|name)=["']${cle.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}["'][^>]*>`, 'i');
  const balise = (par.exec(html) ?? [''])[0];
  return entre(balise, /content=["']([^"']*)["']/i)
    .replace(/&amp;/g, '&').replace(/&quot;/g, '"').replace(/&#039;/g, "'")
    .replace(/&lt;/g, '<').replace(/&gt;/g, '>');
};

interface Page { nom: string; chemin: string; type: string }

const main = async () => {
  console.log('\n━━ Le référencement, vu par un robot ━━\n');

  /* ---- de quoi parler : une page de chaque nature ---- */
  const plan = await (await fetch(`${BASE}/index.php?p=sitemap`)).text();
  /**
   * Dans un plan de site, les adresses sont du XML : l'esperluette y
   * est écrite « &amp; ». Un robot la relit avant d'aller voir ; nous
   * aussi, sinon nous éprouverions des adresses que personne n'appelle.
   */
  const locs = [...plan.matchAll(/<loc>([^<]+)<\/loc>/g)]
    .map((m) => m[1].replace(/&amp;/g, '&').replace(/&apos;/g, "'")
      .replace(/&quot;/g, '"').replace(/&lt;/g, '<').replace(/&gt;/g, '>'));
  const unArticle = locs.find((u) => u.includes('p=blog&a=')) ?? '';
  const unDecor = locs.find((u) => u.includes('p=decor&slug=')) ?? '';

  const pages: Page[] = [
    { nom: 'l’accueil', chemin: '/index.php?p=accueil', type: 'website' },
    { nom: 'les décors', chemin: '/index.php?p=decors', type: 'website' },
    { nom: 'le blog', chemin: '/index.php?p=blog', type: 'website' },
    ...(unArticle ? [{ nom: 'un article', chemin: unArticle.replace(BASE, ''), type: 'article' }] : []),
    ...(unDecor ? [{ nom: 'un décor', chemin: unDecor.replace(BASE, ''), type: 'website' }] : []),
  ];
  ok('le plan du site désigne un article et un décor',
     unArticle !== '' && unDecor !== '', `${locs.length} adresses`);

  const titres = new Map<string, string>();

  for (const page of pages) {
    console.log(`\n  ── ${page.nom} ──`);
    const url = BASE + page.chemin;
    const rep = await fetch(url);
    const html = await rep.text();
    ok('la page répond', rep.status === 200, `HTTP ${rep.status}`);

    /**
     * L'ADRESSE CANONIQUE EST CELLE DE LA PAGE ELLE-MÊME.
     *
     * C'est le contrôle qui aurait attrapé le défaut d'origine, et le seul
     * dont dépendent tous les autres : une page qui se déclare ailleurs
     * offre ses titres, son image et ses données structurées à une autre.
     */
    const canon = entre(html, /<link rel="canonical" href="([^"]+)"/).replace(/&amp;/g, '&');
    ok('elle se déclare canonique VERS ELLE-MÊME', canon === url, canon || 'absente');
    ok('og:url dit la même chose que la balise canonique',
       meta(html, 'og:url') === canon, meta(html, 'og:url'));

    const titre = entre(html, /<title>([^<]*)<\/title>/);
    ok('elle a un titre de longueur utile',
       titre.length >= 12 && titre.length <= 75, `${titre.length} car. — ${titre.slice(0, 46)}`);
    const deja = titres.get(titre);
    ok('ce titre n’est pas déjà celui d’une autre page',
       deja === undefined, deja ? 'identique à ' + deja : '');
    titres.set(titre, page.nom);

    const desc = meta(html, 'description');
    ok('elle a une description de longueur utile',
       desc.length >= 50 && desc.length <= 200, `${desc.length} car.`);
    ok('og:description reprend la description', meta(html, 'og:description') === desc);
    ok('og:title est renseigné', meta(html, 'og:title').length > 3, meta(html, 'og:title').slice(0, 40));
    ok('og:type dit la nature de la page',
       meta(html, 'og:type') === page.type, meta(html, 'og:type'));

    /**
     * L'image de partage, VÉRIFIÉE et non déclarée.
     *
     * Facebook et WhatsApp font confiance aux dimensions annoncées : une
     * image de 240 px annoncée en 1200 est écartée sans un mot. Le code
     * annonçait 1200×630 pour toute image, y compris une couverture
     * d'article de 240 px de large.
     */
    const img = meta(html, 'og:image');
    ok('elle annonce une image de partage', img.startsWith('http'), img.slice(0, 58));
    const ri = await fetch(img);
    const buf = Buffer.from(await ri.arrayBuffer());
    ok('cette image se télécharge vraiment, sans session',
       ri.status === 200 && (ri.headers.get('content-type') ?? '').startsWith('image/'),
       `HTTP ${ri.status} · ${ri.headers.get('content-type')} · ${Math.round(buf.length / 1024)} Ko`);
    ok('son type déclaré est le bon',
       meta(html, 'og:image:type') === (ri.headers.get('content-type') ?? '').split(';')[0],
       `${meta(html, 'og:image:type')} vs ${ri.headers.get('content-type')}`);

    const dims = mesurer(buf);
    const dl = Number(meta(html, 'og:image:width'));
    const dh = Number(meta(html, 'og:image:height'));
    ok('les dimensions annoncées sont les VRAIES',
       dims !== null && dims.l === dl && dims.h === dh,
       dims ? `réelles ${dims.l}×${dims.h}, annoncées ${dl}×${dh}` : 'illisible');
    ok('elle est assez large pour une belle vignette',
       dims !== null && dims.l >= 600, dims ? `${dims.l} px` : '—');

    /* ---- ce que Google lit en plus ---- */
    ok('la page déclare sa langue', /<html lang="fr"/.test(html));
    ok('elle a un titre de niveau 1, et un seul',
       (html.match(/<h1[\s>]/g) ?? []).length === 1,
       `${(html.match(/<h1[\s>]/g) ?? []).length} <h1>`);

    const ld = entre(html, /<script type="application\/ld\+json">([\s\S]*?)<\/script>/);
    let graphe: unknown = null;
    try { graphe = JSON.parse(ld); } catch { /* invalide */ }
    ok('ses données structurées sont un JSON valide', graphe !== null && ld.length > 40,
       `${ld.length} octets`);
    ok('elles nomment leur vocabulaire',
       typeof graphe === 'object' && graphe !== null
       && (graphe as Record<string, unknown>)['@context'] === 'https://schema.org');

    if (page.type === 'article') {
      ok('un article dit quand il a paru',
         /^\d{4}-\d{2}-\d{2}/.test(meta(html, 'article:published_time')),
         meta(html, 'article:published_time'));
      ok('et qui l’a écrit', meta(html, 'article:author').length > 1,
         meta(html, 'article:author'));
    }
  }

  /* ---- ce que le robot demande avant tout le reste ---- */
  console.log('\n  ── robots.txt et le plan du site ──');
  const rr = await fetch(`${BASE}/index.php?p=robots`);
  const robots = await rr.text();
  ok('robots.txt répond', rr.status === 200, `HTTP ${rr.status}`);
  ok('en texte brut', (rr.headers.get('content-type') ?? '').startsWith('text/plain'),
     rr.headers.get('content-type') ?? '');
  ok('il indique le plan du site', /^Sitemap: https?:\/\/\S+/m.test(robots),
     (/^Sitemap: (\S+)/m.exec(robots) ?? [, ''])[1]);
  ok('il n’interdit pas tout le site', !/^Disallow: \/$/m.test(robots));
  ok('il écarte les écrans d’administration', /Disallow: .*p=admin/.test(robots));

  const rp = await fetch(`${BASE}/index.php?p=sitemap`);
  ok('le plan du site répond en XML',
     rp.status === 200 && (rp.headers.get('content-type') ?? '').includes('xml'),
     rp.headers.get('content-type') ?? '');
  ok('il est bien formé', plan.startsWith('<?xml') && plan.trimEnd().endsWith('</urlset>'));
  ok('il ne liste aucune adresse en double', new Set(locs).size === locs.length,
     `${locs.length} adresses, ${new Set(locs).size} distinctes`);

  /**
   * Une adresse à deux paramètres contient une esperluette ; le format
   * du plan exige qu'elle soit échappée. Un plan qui l'oublie n'est pas
   * du XML valide et se fait rejeter en entier, pas seulement la ligne.
   */
  const brutes = [...plan.matchAll(/<loc>([^<]+)<\/loc>/g)].map((m) => m[1]);
  const nues = brutes.filter((u) => /&(?!amp;|apos;|quot;|lt;|gt;|#)/.test(u));
  ok('les esperluettes y sont échappées', nues.length === 0, nues[0] ?? '');

  /**
   * Un plan qui liste des pages mortes est pire que pas de plan : le
   * moteur perd son budget de passage sur des 404, et finit par revenir
   * moins souvent. On en éprouve un échantillon, aux deux bouts.
   */
  const echantillon = [...locs.slice(0, 4), ...locs.slice(-4)];
  let vivantes = 0;
  for (const u of echantillon) {
    const r = await fetch(u, { redirect: 'manual' });
    if (r.status === 200) vivantes++;
  }
  ok('les adresses du plan mènent à des pages vivantes',
     vivantes === echantillon.length, `${vivantes}/${echantillon.length}`);

  /* ---- et ce qui ne doit PAS être indexé ---- */
  console.log('\n  ── ce qui reste hors des moteurs ──');
  /**
   * Un robot arrive sans compte. Deux fins acceptables pour un écran
   * privé : on le renvoie ailleurs, ou on le sert avec l'ordre de ne
   * pas indexer. Ce qu'on refuse, c'est la troisième — une page rendue
   * qui ne dit rien, et que le moteur garde. On suit donc le renvoi à
   * la main : le suivre en aveugle nous ferait juger la page d'arrivée,
   * qui est publique, au lieu de celle qu'on éprouve.
   */
  for (const p of ['admin', 'comptes', 'reglages', 'profil', 'blog-admin']) {
    const r = await fetch(`${BASE}/index.php?p=${p}`, { redirect: 'manual' });
    const renvoi = r.status >= 300 && r.status < 400;
    const h = renvoi ? '' : await r.text();
    ok(`« ${p} » reste hors des moteurs`,
       renvoi || /<meta name="robots" content="noindex/.test(h),
       renvoi ? `renvoi ${r.status} vers ${r.headers.get('location') ?? '?'}`
              : `HTTP ${r.status}, page rendue sans noindex`);
  }
  const rq = await fetch(`${BASE}/index.php?p=blog&q=soiree`);
  ok('une page de résultats de recherche non plus',
     /<meta name="robots" content="noindex/.test(await rq.text()));

  console.log(`\n━━ ${pass} réussis, ${fail} échoués ━━\n`);
  process.exit(fail === 0 ? 0 : 1);
};

/**
 * Les dimensions d'une image, lues dans ses octets.
 *
 * PNG, JPEG et WebP à la main : appeler une bibliothèque reviendrait à lui
 * demander si elle est d'accord avec elle-même, alors qu'on veut savoir ce
 * que verra Facebook — qui, lui, lit les octets.
 */
function mesurer(b: Buffer): { l: number; h: number } | null {
  if (b.length > 24 && b.toString('ascii', 1, 4) === 'PNG') {
    return { l: b.readUInt32BE(16), h: b.readUInt32BE(20) };
  }
  if (b.length > 30 && b.toString('ascii', 0, 4) === 'RIFF' && b.toString('ascii', 8, 12) === 'WEBP') {
    if (b.toString('ascii', 12, 16) === 'VP8X') {
      return { l: (b.readUIntLE(24, 3) & 0xffffff) + 1, h: (b.readUIntLE(27, 3) & 0xffffff) + 1 };
    }
    if (b.toString('ascii', 12, 16) === 'VP8 ') {
      return { l: b.readUInt16LE(26) & 0x3fff, h: b.readUInt16LE(28) & 0x3fff };
    }
  }
  if (b.length > 4 && b[0] === 0xff && b[1] === 0xd8) {
    let i = 2;
    while (i < b.length - 9) {
      if (b[i] !== 0xff) { i++; continue; }
      const marqueur = b[i + 1];
      // SOF0 à SOF15, sauf les marqueurs qui ne portent pas de dimensions.
      if (marqueur >= 0xc0 && marqueur <= 0xcf
          && marqueur !== 0xc4 && marqueur !== 0xc8 && marqueur !== 0xcc) {
        return { l: b.readUInt16BE(i + 7), h: b.readUInt16BE(i + 5) };
      }
      i += 2 + b.readUInt16BE(i + 2);
    }
  }
  return null;
}

void main();
