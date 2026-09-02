/**
 * L'éditeur d'article : on voit le résultat pendant qu'on écrit.
 *
 * Un champ de texte avec des `##` et des `**` demande d'apprendre une
 * syntaxe avant d'écrire une ligne. Personne ne devrait avoir à faire ça
 * pour raconter une soirée.
 *
 * **Mais l'éditeur n'envoie pas de HTML.** C'est la décision qui tient
 * tout le reste. Le serveur continue de recevoir du TEXTE, de l'échapper
 * entièrement, et d'écrire lui-même chaque balise de la page publiée. Un
 * éditeur qui enverrait du HTML obligerait à écrire ici un analyseur et
 * une liste blanche — sans bibliothèque, et à maintenir — sous peine de
 * laisser passer un `<script>` sur une page lue par tout le monde.
 *
 * Ce fichier est donc un TRADUCTEUR à double sens :
 *
 *     texte à marques  →  DOM éditable   (à l'ouverture)
 *     DOM éditable     →  texte à marques (à l'enregistrement)
 *
 * Ce que le navigateur invente entre les deux — `<span>` de style, HTML
 * collé depuis Word, `<font>` d'un autre âge — ne survit pas au retour :
 * la sérialisation ne connaît que sept formes et rend tout le reste en
 * texte nu. C'est ce qui rend l'ensemble sûr par construction plutôt que
 * par vigilance.
 *
 * `scripts/verifier-editeur.ts` fait l'aller-retour sur un corpus dans un
 * vrai navigateur, et compare le rendu à celui de `texte_riche()` en PHP.
 * Deux traducteurs qui divergent, c'est un aperçu qui ment.
 */

/* ------------------------------------------------------------------ */
/* L'adresse d'une image porte le cadrage et la largeur                */
/* ------------------------------------------------------------------ */

/**
 * Deux réglages voyagent DANS l'adresse de l'image, et nulle part ailleurs.
 *
 *   `c=x-y-l-h`  le cadrage, en millièmes de l'original ;
 *   `t=NN`       la largeur d'affichage, en pourcents de la colonne.
 *
 * C'est ce qui fait que l'éditeur montre exactement la page publiée sans
 * une ligne de code en plus : les deux demandent la même adresse au même
 * serveur, qui découpe et met en cache une fois pour toutes. Et comme le
 * cadrage est exprimé en millièmes de l'ORIGINAL, on peut toujours le
 * rouvrir et réélargir : rien n'a été détruit.
 *
 * La largeur, elle, ne change pas les octets servis. On la garde donc hors
 * de l'adresse pendant l'édition — sur un `data-t` — pour ne pas
 * retélécharger l'image à chaque cran du curseur, et on ne la réinjecte
 * qu'au moment d'écrire les marques.
 */
export function urlAvec(src: string, cles: Record<string, string | null>): string {
  const absolue = /^[a-z][a-z0-9+.-]*:/i.test(src);
  let u: URL;
  try { u = new URL(src, 'http://wakabi.invalide/'); } catch { return src; }
  for (const [k, v] of Object.entries(cles)) {
    // `set` remplace SUR PLACE : `p` et `f` gardent leur rang, et le
    // serveur continue de reconnaître l'adresse.
    if (v === null) { u.searchParams.delete(k); } else { u.searchParams.set(k, v); }
  }
  // Une adresse RELATIVE le reste. La rendre absolue marcherait, mais
  // réécrirait le texte de quelqu'un qui a tapé sa marque à la main — et
  // un éditeur qui modifie ce qu'on n'a pas touché finit par inquiéter.
  return absolue ? u.toString() : src.split('?')[0] + u.search;
}

/** La valeur d'un paramètre de l'adresse, ou `null`. */
export function urlLire(src: string, cle: string): string | null {
  try { return new URL(src, 'http://wakabi.invalide/').searchParams.get(cle); }
  catch { return null; }
}

/** Le cadrage d'une adresse, en millièmes, ou le cadre plein. */
export function cadrageDe(src: string): [number, number, number, number] {
  const v = (urlLire(src, 'c') ?? '').split('-').map((n) => parseInt(n, 10));
  return v.length === 4 && v.every((n) => Number.isFinite(n) && n >= 0 && n <= 1000)
    ? [v[0], v[1], v[2], v[3]]
    : [0, 0, 1000, 1000];
}

/** La largeur d'affichage d'une figure, en pourcents. */
function largeurDe(fig: HTMLElement): number {
  const n = parseInt(fig.dataset.t ?? '100', 10);
  return Number.isFinite(n) ? Math.max(20, Math.min(100, n)) : 100;
}

/** Pose la largeur sur la figure : l'attribut mène, le style suit. */
function poserLargeur(fig: HTMLElement, pourcent: number): void {
  const p = Math.max(20, Math.min(100, Math.round(pourcent)));
  fig.dataset.t = String(p);
  fig.style.width = p < 100 ? p + '%' : '';
}

/* ------------------------------------------------------------------ */
/* Les marques → le DOM                                                */
/* ------------------------------------------------------------------ */

const echapper = (s: string): string =>
  s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

/** Le gras, l'italique, le code et les liens, dans une ligne déjà échappée. */
function enLigne(echappe: string): string {
  let t = echappe.replace(/\*\*(?=\S)(.+?)(?<=\S)\*\*/g, '<strong>$1</strong>');
  t = t.replace(/(?<![*\w])\*(?=\S)(.+?)(?<=\S)\*(?![*\w])/g, '<em>$1</em>');
  t = t.replace(/`([^`]+)`/g, '<code>$1</code>');
  return t.replace(/\[([^\]]{1,120})\]\((https?:\/\/[^\s)]+)\)/g, (tout, texte, url) =>
    /^https?:\/\//i.test(url) ? `<a href="${url}">${texte}</a>` : tout);
}

/**
 * Le texte à marques, en HTML éditable.
 *
 * Volontairement la même grammaire que `texte_riche()` en PHP, dans le
 * même ordre : ce que l'éditeur montre doit être ce que la page publiera.
 */
export function marquesVersHtml(brut: string): string {
  const lignes = echapper(brut.trim()).split(/\r\n|\r|\n/);
  const sortie: string[] = [];
  let liste: string[] = [];
  let paragraphe: string[] = [];

  const viderListe = () => {
    if (liste.length) {
      sortie.push('<ul>' + liste.map((l) => `<li>${enLigne(l)}</li>`).join('') + '</ul>');
      liste = [];
    }
  };
  const viderParagraphe = () => {
    if (paragraphe.length) {
      sortie.push(`<p>${enLigne(paragraphe.join('<br>'))}</p>`);
      paragraphe = [];
    }
  };

  for (const ligne of lignes) {
    const l = ligne.trim();
    if (l === '') { viderParagraphe(); viderListe(); continue; }

    let m: RegExpExecArray | null;
    // Une image est un BLOC : elle occupe sa ligne, comme dans la page
    // publiée. La traiter en ligne obligerait à décider quoi faire du
    // texte autour, et à répondre autrement que le rendu PHP.
    if ((m = /^!\[([^\]]*)\]\(([^)\s]+)\)$/.exec(l))) {
      viderParagraphe(); viderListe();
      const legende = m[1];
      // L'adresse a été échappée avec le reste : `&` y est écrit `&amp;`.
      // Il faut la vraie adresse pour en lire la largeur.
      const adresse = m[2].replace(/&amp;/g, '&');
      const t = Math.max(20, Math.min(100, parseInt(urlLire(adresse, 't') ?? '100', 10) || 100));
      sortie.push(
        `<figure class="image-article" contenteditable="false" data-t="${t}"`
        + (t < 100 ? ` style="width:${t}%"` : '') + '>'
        + `<img src="${echapper(urlAvec(adresse, { t: null }))}" alt="${legende}">`
        + (legende ? `<figcaption>${legende}</figcaption>` : '')
        + '</figure>'
      );
      continue;
    }
    if ((m = /^###\s+(.*)$/.exec(l))) {
      viderParagraphe(); viderListe();
      sortie.push(`<h4>${enLigne(m[1])}</h4>`);
    } else if ((m = /^##\s+(.*)$/.exec(l))) {
      viderParagraphe(); viderListe();
      sortie.push(`<h3>${enLigne(m[1])}</h3>`);
    } else if ((m = /^&gt;\s?(.*)$/.exec(l))) {
      viderParagraphe(); viderListe();
      sortie.push(`<blockquote>${enLigne(m[1])}</blockquote>`);
    } else if ((m = /^[-*]\s+(.*)$/.exec(l))) {
      viderParagraphe();
      liste.push(m[1]);
    } else {
      viderListe();
      paragraphe.push(l);
    }
  }
  viderParagraphe();
  viderListe();
  // Un éditeur vide sans bloc n'a pas de curseur où se poser.
  return sortie.join('') || '<p><br></p>';
}

/* ------------------------------------------------------------------ */
/* Le DOM → les marques                                                */
/* ------------------------------------------------------------------ */

/** Les marques d'une portion de ligne. Tout ce qui n'est pas connu → texte nu. */
function marquesEnLigne(noeud: Node): string {
  if (noeud.nodeType === Node.TEXT_NODE) {
    // Les espaces insécables que les navigateurs sèment en éditant.
    return (noeud.textContent ?? '').replace(/ /g, ' ');
  }
  if (noeud.nodeType !== Node.ELEMENT_NODE) return '';

  const e = noeud as HTMLElement;

  /**
   * Certaines balises ne contiennent pas de la PROSE mais du code.
   *
   * Les ignorer ne suffit pas : leur texte remonterait, et coller une
   * page web entière déverserait le contenu de ses `<script>` au milieu
   * de l'article. Rien de dangereux — le serveur l'échapperait — mais
   * personne ne veut relire `window.dataLayer.push({...})` dans son
   * papier. On les jette avec leur contenu.
   */
  if (/^(SCRIPT|STYLE|NOSCRIPT|TEMPLATE|IFRAME|OBJECT|SVG|HEAD|TITLE)$/.test(e.tagName)) {
    return '';
  }
  // Une figure est un bloc : rencontrée en ligne, elle a été imbriquée par
  // le navigateur, et sortir sa légende au milieu d'une phrase donnerait un
  // texte que personne n'a écrit.
  if (e.tagName === 'FIGURE') {
    return '';
  }

  const dedans = Array.from(e.childNodes).map(marquesEnLigne).join('');

  switch (e.tagName) {
    case 'BR': return '\n';
    // Deux cellules côte à côte sont deux mots, pas un seul : sans cette
    // espace, un tableau collé rend « Unecellule ».
    case 'TD': case 'TH': return dedans + ' ';
    case 'STRONG': case 'B': return dedans.trim() ? `**${dedans}**` : dedans;
    case 'EM': case 'I': return dedans.trim() ? `*${dedans}*` : dedans;
    case 'CODE': return dedans.trim() ? '`' + dedans + '`' : dedans;
    case 'A': {
      const href = e.getAttribute('href') ?? '';
      // Seuls http et https : un `javascript:` collé depuis ailleurs ne
      // doit pas ressortir sous forme de lien, même si le serveur le
      // refuserait ensuite. Deux gardes valent mieux qu'une.
      return /^https?:\/\//i.test(href) && dedans.trim() ? `[${dedans}](${href})` : dedans;
    }
    default: return dedans;
  }
}

const nettoyer = (s: string): string => s.replace(/[ \t]+/g, ' ').trim();

/** Le contenu de l'éditeur, retraduit en texte à marques. */
export function htmlVersMarques(racine: HTMLElement): string {
  const blocs: string[] = [];

  const bloc = (e: HTMLElement): void => {
    /**
     * Une figure EMBALLÉE dans un conteneur reste une image.
     *
     * C'est ce que produit un collage depuis la page publiée, ou certains
     * navigateurs quand on presse Entrée juste avant une image. Sans cette
     * descente, `marquesEnLigne` jette la figure avec son conteneur :
     * l'image disparaît à l'enregistrement, sans un mot, et l'auteur ne le
     * découvre qu'une fois l'article en ligne.
     */
    if (e.tagName !== 'FIGURE' && e.querySelector('figure img')) {
      for (const enfant of Array.from(e.childNodes)) {
        if (enfant.nodeType === Node.ELEMENT_NODE) {
          bloc(enfant as HTMLElement);
        } else {
          const t = nettoyer(enfant.textContent ?? '');
          if (t) blocs.push(t);
        }
      }
      return;
    }

    const contenu = Array.from(e.childNodes).map(marquesEnLigne).join('');
    const lignes = contenu.split('\n').map(nettoyer).filter((l, i, t) =>
      l !== '' || (i > 0 && i < t.length - 1));

    switch (e.tagName) {
      case 'FIGURE': {
        const img = e.querySelector('img');
        const src = img?.getAttribute('src') ?? '';
        const legende = nettoyer(e.querySelector('figcaption')?.textContent ?? '');
        // Une figure sans image n'a rien à donner ; et l'adresse est
        // laissée telle quelle : c'est le serveur qui décide de la servir
        // ou non, et lui seul sait ce qui lui appartient. La largeur, elle,
        // rejoint l'adresse ici : c'est sa seule façon d'être enregistrée.
        const t = largeurDe(e);
        if (src) blocs.push(`![${legende}](${urlAvec(src, { t: t < 100 ? String(t) : null })})`);
        return;
      }
      case 'H1': case 'H2': case 'H3':
        if (nettoyer(contenu)) blocs.push('## ' + nettoyer(contenu));
        return;
      case 'H4': case 'H5': case 'H6':
        if (nettoyer(contenu)) blocs.push('### ' + nettoyer(contenu));
        return;
      case 'BLOCKQUOTE':
        for (const l of lignes) if (l) blocs.push('> ' + l);
        return;
      case 'UL': case 'OL': {
        const points = Array.from(e.children)
          .map((li) => nettoyer(Array.from(li.childNodes).map(marquesEnLigne).join('')))
          .filter(Boolean);
        if (points.length) blocs.push(points.map((p) => '- ' + p).join('\n'));
        return;
      }
      default:
        if (lignes.some(Boolean)) blocs.push(lignes.join('\n'));
    }
  };

  for (const enfant of Array.from(racine.childNodes)) {
    if (enfant.nodeType === Node.ELEMENT_NODE) {
      const e = enfant as HTMLElement;
      // Un conteneur que le navigateur a inventé autour de vrais blocs :
      // on descend plutôt que d'aplatir son contenu en un paragraphe.
      if ((e.tagName === 'DIV' || e.tagName === 'SECTION')
          && e.querySelector('p,h1,h2,h3,h4,ul,ol,blockquote')) {
        for (const petit of Array.from(e.children)) bloc(petit as HTMLElement);
      } else {
        bloc(e);
      }
    } else {
      // Du texte nu directement dans l'éditeur : c'est un paragraphe.
      const t = nettoyer(enfant.textContent ?? '');
      if (t) blocs.push(t);
    }
  }
  return blocs.join('\n\n');
}

/* ------------------------------------------------------------------ */
/* L'éditeur                                                           */
/* ------------------------------------------------------------------ */

interface Outil {
  cle: string;
  titre: string;
  icone: string;
  raccourci?: string;
  agir: (z: HTMLElement) => void;
}

/** Le bloc qui contient la sélection, dans l'éditeur. */
function blocCourant(zone: HTMLElement): HTMLElement | null {
  const sel = window.getSelection();
  if (!sel || !sel.rangeCount) return null;
  let n: Node | null = sel.getRangeAt(0).startContainer;
  while (n && n !== zone) {
    if (n.nodeType === Node.ELEMENT_NODE && (n as HTMLElement).parentElement === zone) {
      return n as HTMLElement;
    }
    n = n.parentNode;
  }
  return null;
}

/** Remplace le bloc courant par un autre, en gardant son contenu. */
function changerBloc(zone: HTMLElement, balise: string): void {
  const bloc = blocCourant(zone);
  if (!bloc) return;

  // Recliquer sur le même outil revient au paragraphe : un bouton d'état
  // qui ne se relâche pas oblige à passer par un autre pour en sortir.
  const cible = bloc.tagName === balise.toUpperCase() ? 'P' : balise;
  const neuf = document.createElement(cible);

  if (cible === 'UL') {
    const li = document.createElement('li');
    li.innerHTML = bloc.innerHTML || '<br>';
    neuf.appendChild(li);
  } else if (bloc.tagName === 'UL') {
    neuf.innerHTML = Array.from(bloc.children).map((li) => li.innerHTML).join('<br>') || '<br>';
  } else {
    neuf.innerHTML = bloc.innerHTML || '<br>';
  }

  bloc.replaceWith(neuf);
  const r = document.createRange();
  r.selectNodeContents(cible === 'UL' ? (neuf.firstElementChild ?? neuf) : neuf);
  r.collapse(false);
  const sel = window.getSelection();
  sel?.removeAllRanges();
  sel?.addRange(r);
}

/**
 * Les intitulés sont ÉCRITS, pas pictographiques.
 *
 * « T1 », « ❝ » et « •— » demandent de deviner, et l'on ne devine pas
 * bien quand on écrit son premier article. Seuls le gras et l'italique
 * gardent leur lettre : c'est la convention de tous les traitements de
 * texte français depuis trente ans, et elle, tout le monde la connaît.
 */
/**
 * Choisir une image, l'envoyer, et la poser dans le texte.
 *
 * L'attente est ANNONCÉE : sur une connexion de Lomé, une photo de cinq
 * mégaoctets met du temps, et un bouton qui ne réagit pas se reclique —
 * on se retrouve avec l'image en triple. Un bloc « envoi en cours »
 * occupe la place, puis devient l'image.
 */
async function televerser(zone: HTMLElement): Promise<void> {
  const contexte = document.getElementById('editeur-contexte');
  const csrf = (() => {
    try { return (JSON.parse(contexte?.textContent ?? '{}') as { csrf?: string }).csrf ?? ''; }
    catch { return ''; }
  })();
  const base = (() => {
    try { return (JSON.parse(contexte?.textContent ?? '{}') as { base?: string }).base ?? ''; }
    catch { return ''; }
  })();

  const choix = document.createElement('input');
  choix.type = 'file';
  choix.accept = 'image/png,image/jpeg,image/webp';
  choix.addEventListener('change', async () => {
    const fichier = choix.files?.[0];
    if (!fichier) return;

    const legende = prompt(
      'La légende de l’image — c’est aussi ce que lisent les gens qui ne la voient pas.',
      ''
    ) ?? '';

    const attente = document.createElement('p');
    attente.className = 'editeur-attente';
    attente.textContent = 'Envoi de ' + fichier.name + '…';
    const bloc = blocCourant(zone);
    bloc ? bloc.after(attente) : zone.appendChild(attente);

    try {
      const f = new FormData();
      f.set('csrf', csrf);
      f.set('image', fichier);
      const r = await fetch(base + 'index.php?p=api-media', { method: 'POST', body: f });
      const rep = await r.json() as { ok?: boolean; url?: string; poids?: string; message?: string };
      if (!rep.ok || !rep.url) throw new Error(rep.message ?? 'l’envoi a échoué');

      const figure = document.createElement('figure');
      figure.className = 'image-article';
      figure.contentEditable = 'false';
      const img = document.createElement('img');
      img.src = rep.url;
      img.alt = legende;
      figure.appendChild(img);
      if (legende) {
        const cap = document.createElement('figcaption');
        cap.textContent = legende;
        figure.appendChild(cap);
      }
      attente.replaceWith(figure);

      // Un paragraphe après l'image : sans lui, il n'y a plus où écrire
      // quand elle est le dernier bloc.
      if (!figure.nextElementSibling) {
        const p = document.createElement('p');
        p.innerHTML = '<br>';
        figure.after(p);
      }
      // Le poids obtenu est ANNONCÉ : c'est la preuve que la compression a
      // eu lieu, et le seul moyen pour l'auteur de savoir ce que ses
      // lecteurs vont télécharger.
      noter(zone, 'Image ajoutée' + (rep.poids ? ' — ' + rep.poids + ' après compression.' : '.')
        + ' Cliquez dessus pour la recadrer ou changer sa largeur.');
      zone.dispatchEvent(new Event('input'));
    } catch (e) {
      attente.className = 'editeur-attente erreur';
      attente.textContent = 'L’image n’est pas partie : '
        + (e instanceof Error ? e.message : 'erreur inconnue');
      setTimeout(() => attente.remove(), 6000);
    }
  });
  choix.click();
}

/* ------------------------------------------------------------------ */
/* Une ligne de nouvelles, hors du texte                               */
/* ------------------------------------------------------------------ */

/**
 * Ce que l'éditeur a à dire s'affiche SOUS la barre, jamais dans le texte.
 *
 * Un message glissé dans la zone éditable serait enregistré avec
 * l'article si l'auteur sauvegarde dans les secondes qui suivent — et
 * « Image ajoutée — 48 Ko » se retrouverait publié au milieu du papier.
 */
const NOTES = new WeakMap<HTMLElement, HTMLElement>();

function noter(zone: HTMLElement, texte: string, erreur = false): void {
  const n = NOTES.get(zone);
  if (!n) return;
  n.textContent = texte;
  n.className = 'editeur-note' + (erreur ? ' erreur' : '');
  n.hidden = false;
  window.clearTimeout(Number(n.dataset.minuteur ?? 0));
  n.dataset.minuteur = String(window.setTimeout(() => { n.hidden = true; }, 6000));
}

/* ------------------------------------------------------------------ */
/* Recadrer                                                            */
/* ------------------------------------------------------------------ */

/** Les proportions proposées. « Libre » d'abord : c'est le cas courant. */
const PROPORTIONS: Array<{ nom: string; r: number | null }> = [
  { nom: 'Libre', r: null },
  { nom: '16:9', r: 16 / 9 },
  { nom: '4:3', r: 4 / 3 },
  { nom: 'Carré', r: 1 },
  { nom: '3:4', r: 3 / 4 },
];

/**
 * Le recadrage, en grand, sur l'image d'ORIGINE.
 *
 * On montre toujours l'original complet, jamais le découpage précédent :
 * sinon on recadre un recadrage et l'on ne peut plus jamais réélargir.
 * Le rectangle part de là où l'auteur l'avait laissé.
 */
function recadrer(figure: HTMLElement, fini: () => void): void {
  const img = figure.querySelector('img');
  if (!img) return;

  const entier = urlAvec(img.getAttribute('src') ?? '', { c: null });
  let [cx, cy, cl, ch] = cadrageDe(img.getAttribute('src') ?? '');
  let ratio: number | null = null;

  const fond = document.createElement('div');
  fond.className = 'recadrage';
  fond.innerHTML = `
    <div class="recadrage-boite" role="dialog" aria-modal="true" aria-label="Recadrer l’image">
      <p class="recadrage-aide">Tirez les coins pour choisir ce qu’on doit voir.
      L’original est conservé : vous pourrez toujours réélargir.</p>
      <div class="recadrage-scene"><img alt=""><div class="recadrage-cadre">
        <span data-coin="ho"></span><span data-coin="hd"></span>
        <span data-coin="bo"></span><span data-coin="bd"></span>
      </div></div>
      <div class="recadrage-pieds">
        <div class="recadrage-ratios"></div>
        <div class="rangee" style="gap:8px;margin-left:auto">
          <button type="button" class="bouton fant petit" data-r="tout">Tout afficher</button>
          <button type="button" class="bouton fant petit" data-r="annuler">Annuler</button>
          <button type="button" class="bouton petit" data-r="ok">Valider</button>
        </div>
      </div>
    </div>`;
  document.body.appendChild(fond);

  const grand = fond.querySelector('img') as HTMLImageElement;
  const scene = fond.querySelector('.recadrage-scene') as HTMLElement;
  const cadre = fond.querySelector('.recadrage-cadre') as HTMLElement;
  grand.src = entier;

  const dessiner = () => {
    cadre.style.left = cx / 10 + '%';
    cadre.style.top = cy / 10 + '%';
    cadre.style.width = cl / 10 + '%';
    cadre.style.height = ch / 10 + '%';
  };
  dessiner();

  /** La hauteur, en millièmes, qui donne la proportion demandée à l'écran. */
  const hauteurPour = (largeur: number): number => {
    if (ratio === null) return ch;
    const l = grand.naturalWidth || 1;
    const h = grand.naturalHeight || 1;
    return Math.round((largeur * l) / (ratio * h));
  };

  const ratios = fond.querySelector('.recadrage-ratios') as HTMLElement;
  for (const p of PROPORTIONS) {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'bouton fant petit' + (p.r === null ? ' actif' : '');
    b.textContent = p.nom;
    b.addEventListener('click', () => {
      ratio = p.r;
      for (const autre of Array.from(ratios.children)) autre.classList.remove('actif');
      b.classList.add('actif');
      if (ratio !== null) {
        // On garde la largeur et l'on recalcule la hauteur ; si elle
        // déborde, c'est la largeur qui cède.
        ch = hauteurPour(cl);
        if (cy + ch > 1000) { ch = 1000 - cy; cl = Math.round(ch * cl / hauteurPour(cl) || cl); }
        ch = Math.max(50, Math.min(1000 - cy, ch));
        dessiner();
      }
    });
    ratios.appendChild(b);
  }

  /* --- tirer : déplacer le cadre, ou l'un de ses quatre coins --- */
  let geste: { coin: string | null; x0: number; y0: number; d: number[] } | null = null;
  const enMille = (e: PointerEvent) => {
    const r = scene.getBoundingClientRect();
    return [
      Math.max(0, Math.min(1000, ((e.clientX - r.left) / Math.max(1, r.width)) * 1000)),
      Math.max(0, Math.min(1000, ((e.clientY - r.top) / Math.max(1, r.height)) * 1000)),
    ];
  };

  scene.addEventListener('pointerdown', (e) => {
    const cible = e.target as HTMLElement;
    const [x, y] = enMille(e);
    if (cible.dataset.coin) {
      geste = { coin: cible.dataset.coin, x0: x, y0: y, d: [cx, cy, cl, ch] };
    } else if (cible === cadre) {
      geste = { coin: null, x0: x, y0: y, d: [cx, cy, cl, ch] };
    } else {
      // Un clic dans le vide commence un NOUVEAU cadre à cet endroit.
      cx = Math.round(x); cy = Math.round(y); cl = 50; ch = hauteurPour(50) || 50;
      geste = { coin: 'bd', x0: x, y0: y, d: [cx, cy, cl, ch] };
    }
    scene.setPointerCapture(e.pointerId);
    e.preventDefault();
  });

  scene.addEventListener('pointermove', (e) => {
    if (!geste) return;
    const [x, y] = enMille(e);
    const dx = x - geste.x0;
    const dy = y - geste.y0;
    const [ox, oy, ol, oh] = geste.d;

    if (geste.coin === null) {
      cx = Math.round(Math.max(0, Math.min(1000 - ol, ox + dx)));
      cy = Math.round(Math.max(0, Math.min(1000 - oh, oy + dy)));
    } else {
      const droite = geste.coin.includes('d');
      const bas = geste.coin.includes('b');
      let l = Math.round(droite ? ol + dx : ol - dx);
      let x0 = Math.round(droite ? ox : ox + dx);
      l = Math.max(50, Math.min(l, droite ? 1000 - ox : ox + ol));
      x0 = Math.max(0, Math.min(x0, ox + ol - 50));
      let h = ratio !== null ? hauteurPour(l) : Math.round(bas ? oh + dy : oh - dy);
      let y0 = bas ? oy : (ratio !== null ? oy + oh - h : Math.round(oy + dy));
      h = Math.max(50, Math.min(h, bas ? 1000 - oy : oy + oh));
      y0 = Math.max(0, Math.min(y0, 1000 - h));
      cx = x0; cy = y0; cl = l; ch = Math.min(h, 1000 - y0);
    }
    dessiner();
  });

  const relacher = () => { geste = null; };
  scene.addEventListener('pointerup', relacher);
  scene.addEventListener('pointercancel', relacher);

  /* --- sortir --- */
  const fermer = () => { fond.remove(); document.removeEventListener('keydown', auClavier); };
  const auClavier = (e: KeyboardEvent) => { if (e.key === 'Escape') fermer(); };
  document.addEventListener('keydown', auClavier);

  fond.addEventListener('click', (e) => {
    const quoi = (e.target as HTMLElement).dataset.r;
    if (!quoi && e.target !== fond) return;
    if (quoi === 'ok') {
      const plein = cx === 0 && cy === 0 && cl === 1000 && ch === 1000;
      img.src = urlAvec(entier, { c: plein ? null : `${cx}-${cy}-${cl}-${ch}` });
      fini();
    } else if (quoi === 'tout') {
      cx = 0; cy = 0; cl = 1000; ch = 1000;
      dessiner();
      return;
    }
    fermer();
  });
}

/* ------------------------------------------------------------------ */
/* La barre de l'image sélectionnée                                    */
/* ------------------------------------------------------------------ */

/**
 * Une image se règle une fois sélectionnée, dans une barre à elle.
 *
 * Elle est posée SOUS la barre de mise en forme plutôt qu'en surimpression
 * de l'image : une bulle flottante se retrouve hors de l'écran dès que
 * l'image est en bas, et sur téléphone elle recouvre ce qu'elle règle.
 * Ici, elle est toujours au même endroit, toujours entièrement visible.
 */
function barreImage(zone: HTMLElement, apres: () => void): HTMLElement {
  const barre = document.createElement('div');
  barre.className = 'editeur-image';
  barre.hidden = true;
  barre.innerHTML = `
    <span class="editeur-image-titre">Image</span>
    <label class="editeur-image-taille">Largeur
      <input type="range" min="20" max="100" step="5" value="100" aria-label="Largeur de l’image">
      <output>100 %</output>
    </label>
    <button type="button" class="bouton fant petit" data-i="cadrer">Recadrer</button>
    <button type="button" class="bouton fant petit" data-i="legende">Légende</button>
    <button type="button" class="bouton fant petit" data-i="retirer">Retirer</button>`;

  const curseur = barre.querySelector('input') as HTMLInputElement;
  const sortie = barre.querySelector('output') as HTMLOutputElement;
  let choisie: HTMLElement | null = null;

  const montrer = (fig: HTMLElement | null) => {
    for (const f of Array.from(zone.querySelectorAll('.image-article'))) {
      f.classList.toggle('choisie', f === fig);
    }
    choisie = fig;
    barre.hidden = fig === null;
    if (fig) {
      curseur.value = String(largeurDe(fig));
      sortie.textContent = largeurDe(fig) + ' %';
    }
  };

  curseur.addEventListener('input', () => {
    if (!choisie) return;
    poserLargeur(choisie, Number(curseur.value));
    sortie.textContent = curseur.value + ' %';
    apres();
  });

  barre.addEventListener('click', (e) => {
    const quoi = (e.target as HTMLElement).dataset.i;
    if (!quoi || !choisie) return;
    const fig = choisie;
    if (quoi === 'cadrer') {
      recadrer(fig, apres);
    } else if (quoi === 'legende') {
      const cap = fig.querySelector('figcaption');
      const texte = prompt('La légende de l’image — c’est aussi ce que lisent '
        + 'les gens qui ne la voient pas.', cap?.textContent ?? '');
      if (texte === null) return;
      const img = fig.querySelector('img');
      if (img) img.alt = texte;
      if (texte.trim() === '') {
        cap?.remove();
      } else if (cap) {
        cap.textContent = texte;
      } else {
        const neuf = document.createElement('figcaption');
        neuf.textContent = texte;
        fig.appendChild(neuf);
      }
      apres();
    } else if (quoi === 'retirer') {
      fig.remove();
      montrer(null);
      apres();
    }
  });

  // Cliquer une image la choisit ; cliquer ailleurs la relâche.
  zone.addEventListener('click', (e) => {
    const fig = (e.target as HTMLElement).closest('.image-article') as HTMLElement | null;
    montrer(fig && zone.contains(fig) ? fig : null);
  });
  document.addEventListener('click', (e) => {
    const n = e.target as HTMLElement;
    if (!zone.contains(n) && !barre.contains(n) && !n.closest('.recadrage')) montrer(null);
  });

  return barre;
}

const OUTILS: Outil[] = [
  { cle: 'h3', titre: 'Intertitre', icone: 'Titre', agir: (z) => changerBloc(z, 'H3') },
  { cle: 'h4', titre: 'Sous-titre', icone: 'Sous-titre', agir: (z) => changerBloc(z, 'H4') },
  { cle: 'gras', titre: 'Gras', icone: 'G', raccourci: 'b',
    agir: () => document.execCommand('bold') },
  { cle: 'italique', titre: 'Italique', icone: 'I', raccourci: 'i',
    agir: () => document.execCommand('italic') },
  { cle: 'liste', titre: 'Liste à puces', icone: 'Liste', agir: (z) => changerBloc(z, 'UL') },
  { cle: 'citation', titre: 'Citation', icone: 'Citation', agir: (z) => changerBloc(z, 'BLOCKQUOTE') },
  {
    cle: 'image', titre: 'Image', icone: 'Image',
    agir: (z) => { void televerser(z); },
  },
  {
    cle: 'lien', titre: 'Lien', icone: 'Lien',
    agir: () => {
      const sel = window.getSelection();
      if (!sel || sel.isCollapsed) {
        alert('Sélectionnez d’abord le texte qui doit devenir un lien.');
        return;
      }
      const url = prompt('L’adresse du lien', 'https://');
      if (!url) return;
      if (!/^https?:\/\//i.test(url)) {
        alert('Une adresse doit commencer par http:// ou https://');
        return;
      }
      document.execCommand('createLink', false, url);
    },
  },
];

function demarrer(): void {
  const champ = document.getElementById('a-corps') as HTMLTextAreaElement | null;
  if (!champ) return;
  const form = champ.closest('form');
  if (!form) return;

  /**
   * `styleWithCSS` à false : le navigateur écrit `<b>` et non
   * `<span style="font-weight:bold">`.
   *
   * La sérialisation sait lire les deux — un style ne devient rien —
   * mais dans ce cas le gras serait perdu à l'enregistrement, sans que
   * rien ne prévienne. Mieux vaut demander la forme qu'on sait relire.
   */
  try { document.execCommand('styleWithCSS', false, 'false'); } catch { /* vieux navigateur */ }

  const boite = document.createElement('div');
  boite.className = 'editeur';

  const barre = document.createElement('div');
  barre.className = 'editeur-barre';
  barre.setAttribute('role', 'toolbar');
  barre.setAttribute('aria-label', 'Mise en forme');

  const zone = document.createElement('div');
  zone.className = 'editeur-zone corps-article';
  zone.contentEditable = 'true';
  zone.setAttribute('role', 'textbox');
  zone.setAttribute('aria-multiline', 'true');
  zone.setAttribute('aria-label', 'Corps de l’article');
  zone.innerHTML = marquesVersHtml(champ.value);

  for (const outil of OUTILS) {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'editeur-outil';
    b.textContent = outil.icone;
    b.title = outil.titre + (outil.raccourci ? ` (Ctrl+${outil.raccourci.toUpperCase()})` : '');
    b.setAttribute('aria-label', outil.titre);
    // `mousedown` et non `click` : un clic déplace le focus AVANT
    // d'agir, et la sélection dans la zone est alors déjà perdue.
    b.addEventListener('mousedown', (e) => {
      e.preventDefault();
      zone.focus();
      outil.agir(zone);
      champ.value = htmlVersMarques(zone);
    });
    barre.appendChild(b);
  }

  /**
   * Le collage passe par le TEXTE BRUT, toujours.
   *
   * Coller depuis Word ou Google Docs apporte des tableaux de mise en
   * page, des polices en points et des couleurs en dur. La
   * sérialisation les jetterait de toute façon, mais entre-temps
   * l'éditeur afficherait quelque chose qui ne sera pas publié — un
   * aperçu qui ment est pire qu'un aperçu absent.
   */
  zone.addEventListener('paste', (e) => {
    e.preventDefault();
    const texte = e.clipboardData?.getData('text/plain') ?? '';
    document.execCommand('insertText', false, texte);
  });

  zone.addEventListener('keydown', (e) => {
    if (!e.ctrlKey && !e.metaKey) return;
    const outil = OUTILS.find((o) => o.raccourci === e.key.toLowerCase());
    if (outil) { e.preventDefault(); outil.agir(zone); champ.value = htmlVersMarques(zone); }
  });

  /**
   * Recopier l'éditeur dans le champ — SAUF quand c'est le champ qui mène.
   *
   * En mode texte brut, l'éditeur est masqué et ne suit plus la frappe :
   * le recopier à l'enregistrement écraserait ce qui vient d'être tapé
   * par une version d'avant la bascule. Le défaut est silencieux — on
   * enregistre, la page dit « enregistré », et le texte est l'ancien.
   */
  const synchroniser = () => {
    if (boite.hidden) {
      return;
    }
    champ.value = htmlVersMarques(zone);
    // `:empty` est faux dès qu'un `<br>` traîne — et il y en a toujours un
    // dans un `contenteditable`. C'est le CONTENU qui décide, pas le DOM.
    zone.toggleAttribute('data-vide', champ.value === '');
  };
  zone.addEventListener('input', synchroniser);
  zone.addEventListener('blur', synchroniser);
  // La ceinture avec les bretelles : l'enregistrement ne doit jamais
  // partir avec une version d'avant la dernière frappe.
  form.addEventListener('submit', synchroniser);

  // La barre de l'image vient APRÈS la barre de mise en forme et avant la
  // zone : elle apparaît là où l'œil est déjà, sans déplacer le texte.
  const barreDeLImage = barreImage(zone, () => { synchroniser(); });

  const note = document.createElement('p');
  note.className = 'editeur-note';
  note.hidden = true;
  note.setAttribute('role', 'status');
  NOTES.set(zone, note);

  boite.appendChild(barre);
  boite.appendChild(barreDeLImage);
  boite.appendChild(note);
  boite.appendChild(zone);
  champ.parentElement?.insertBefore(boite, champ);
  synchroniser();

  // Le champ d'origine reste : c'est lui qui est envoyé, et il redevient
  // le seul éditeur si ce script ne charge pas.
  champ.hidden = true;
  champ.setAttribute('aria-hidden', 'true');
  champ.tabIndex = -1;

  const aide = document.getElementById('a-marques');
  if (aide) { aide.hidden = true; }
  const bascule = document.getElementById('a-bascule');
  if (bascule) {
    bascule.hidden = false;
    bascule.addEventListener('click', (e) => {
      e.preventDefault();
      const brut = champ.hidden;
      champ.hidden = !brut;
      champ.tabIndex = brut ? 0 : -1;
      champ.removeAttribute('aria-hidden');
      boite.hidden = brut;
      if (aide) { aide.hidden = !brut; }
      bascule.textContent = brut ? 'Revenir à l’éditeur' : 'Écrire en texte brut';
      if (brut) { champ.value = htmlVersMarques(zone); champ.focus(); }
      else { zone.innerHTML = marquesVersHtml(champ.value); zone.focus(); }
    });
  }
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', demarrer);
} else {
  demarrer();
}
