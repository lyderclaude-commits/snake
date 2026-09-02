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
    const contenu = Array.from(e.childNodes).map(marquesEnLigne).join('');
    const lignes = contenu.split('\n').map(nettoyer).filter((l, i, t) =>
      l !== '' || (i > 0 && i < t.length - 1));

    switch (e.tagName) {
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

  boite.appendChild(barre);
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
