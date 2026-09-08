/**
 * L'aperçu du formulaire de décor.
 *
 * Il ne fabrique RIEN : à chaque geste, il renvoie le formulaire au serveur,
 * qui construit le gabarit avec la même fonction que celle qui l'enregistre,
 * puis il dessine ce gabarit avec le même renderer que le Studio. Un aperçu
 * qui composerait sa propre version de la structure finirait par montrer
 * autre chose que ce qu'on enregistre — et c'est exactement ce qu'un aperçu
 * ne doit jamais faire.
 */

import { renderScene } from '@/core/renderScene';
import { loadImage } from '@/core/imagePipeline';
import { clampPhoto } from '@/core/fitPhoto';
import type { LayerAssets, LoadedImage, RenderSpec } from '@/core/types';
import type { Rect } from '@/core/fitPhoto';
import { fenetreOuverte, fenetreArrondie, formatProche } from '@/core/photoWindow';
import type { Fenetre } from '@/core/photoWindow';
import { attendrePolices } from '@/core/polices';

interface Contexte {
  base: string;
  csrf: string;
}

const CHAMPS = [
  'disposition', 'cadre_url', 'cadre_fourni', 'titre', 'accroche', 'champ_libelle',
  'texte_couleur', 'texte_align', 'bloc_x', 'bloc_y', 'bloc_w',
  'accroche_taille', 'champ_taille', 'qr_position', 'qr_taille', 'filigrane_position',
  'format', 'fond', 'photo_x', 'photo_y', 'photo_w', 'photo_h', 'photo_forme',
  'calques',
];

// Les curseurs affichent une fraction de la hauteur du canevas : un
// pourcentage se lit, « 0.058 » ne se lit pas.
const POURCENTAGES = new Set([
  'bloc_x', 'bloc_y', 'bloc_w', 'accroche_taille', 'champ_taille', 'qr_taille',
  'photo_x', 'photo_y', 'photo_w', 'photo_h',
]);

/**
 * Enveloppe DOM autour de `fenetreOuverte` : rasteriser, puis mesurer.
 *
 * 260 px suffisent — on cherche un rectangle, pas un contour. À taille
 * réelle ce sont deux millions de pixels à parcourir sur un téléphone.
 */
function fenetreDuCadre(img: CanvasImageSource, iw: number, ih: number): Fenetre | null {
  const MAX = 260;
  const ech = Math.min(1, MAX / Math.max(iw, ih, 1));
  const w = Math.max(1, Math.round(iw * ech));
  const h = Math.max(1, Math.round(ih * ech));

  const hors = document.createElement('canvas');
  hors.width = w;
  hors.height = h;
  const g = hors.getContext('2d', { willReadFrequently: true });
  if (!g) return null;
  g.clearRect(0, 0, w, h);
  g.drawImage(img, 0, 0, w, h);

  try {
    return fenetreOuverte(g.getImageData(0, 0, w, h).data, w, h);
  } catch {
    // Cadre servi par un autre domaine : le canevas est teinté, rien à lire.
    return null;
  }
}

function demarrer(ctx: Contexte) {
  const formulaire = document.getElementById('form-decor') as HTMLFormElement | null;
  const canevas = document.getElementById('apercu') as HTMLCanvasElement | null;
  const legende = document.getElementById('apercu-etat') as HTMLElement | null;
  if (!formulaire || !canevas || !legende) return;
  // Rebaptisés après le garde : le typage ne suit pas la vérification à
  // l'intérieur des fonctions déclarées plus bas.
  const form = formulaire, toile = canevas, etat = legende;

  const dessin = toile.getContext('2d')!;
  const assets: LayerAssets = {};
  let photo: LoadedImage | null = null;
  let qr: LoadedImage | null = null;
  let cadreCharge = '';
  let cadreServeur = '';
  let fichierUrl: string | null = null;
  let minuteur: number | undefined;
  let tour = 0;

  const val = (nom: string): string => {
    const el = form.elements.namedItem(nom) as HTMLInputElement | null;
    return el && 'value' in el ? el.value : '';
  };

  /**
   * La couleur de fond n'a de sens que pour la page blanche.
   *
   * Ailleurs, le cadre couvre tout ce que la photo ne couvre pas : proposer
   * de la changer promettrait un effet qu'on ne verrait jamais. Le format et
   * la fenêtre photo, eux, valent pour tous les gabarits — c'est le cadre
   * qui les dicte, pas la disposition.
   */
  function ajusterGroupes() {
    const vierge = val('disposition') === 'vierge';
    for (const el of document.querySelectorAll('.si-vierge')) {
      (el as HTMLElement).hidden = !vierge;
    }
  }

  /** Les nombres du formulaire, montrés en clair sous chaque curseur. */
  function afficherValeurs() {
    for (const nom of CHAMPS) {
      const sortie = document.getElementById('v-' + nom);
      if (!sortie) continue;
      const v = Number(val(nom));
      sortie.textContent = POURCENTAGES.has(nom) ? Math.round(v * 100) + ' %' : String(v);
    }
  }

  /**
   * @param reinit  '' : on garde les réglages en cours.
   *                'disposition' : on repart des réglages d'usine du gabarit,
   *                  format d'origine COMPRIS — changer de gabarit, c'est en
   *                  prendre un autre en entier.
   *                'format' : réglages d'usine, mais pour le format affiché —
   *                  c'est ce qu'il faut quand le cadre vient d'imposer le sien.
   */
  async function rafraichir(reinit: '' | 'disposition' | 'format' = '') {
    const mien = ++tour;
    const corps = new URLSearchParams({ csrf: ctx.csrf });
    for (const nom of CHAMPS) corps.set(nom, val(nom));
    if (reinit) corps.set('reinit', reinit);

    let d: any;
    try {
      const r = await fetch(ctx.base + '?p=api-apercu', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: corps.toString(),
      });
      d = await r.json();
    } catch {
      etat.textContent = 'Aperçu indisponible — la saisie, elle, est bien prise en compte.';
      return;
    }
    // Une réponse arrivée après une plus récente ne doit pas la recouvrir.
    if (mien !== tour) return;

    if (d.erreur) {
      etat.textContent = d.erreur;
      /**
       * Un réglage vient de rendre le décor invalide, donc impossible à
       * juger. Laisser la santé sur ses coches vertes d'il y a une seconde
       * dirait le contraire de la vérité, au moment précis où l'auteur a
       * besoin de savoir que quelque chose ne va pas.
       */
      santeIndecise(d.erreur);
      return;
    }

    // Réglages d'usine du gabarit : on les remet dans les commandes, sinon
    // les curseurs raconteraient autre chose que l'image.
    if (reinit && d.apparence) {
      for (const [cle, valeur] of Object.entries(d.apparence)) {
        const el = form.elements.namedItem(cle) as HTMLInputElement | null;
        if (el) el.value = String(valeur);
      }
      afficherValeurs();
      ajusterGroupes();
    }

    const tpl = d.gabarit;
    /**
     * Le cadre se retrouve par son IDENTIFIANT, pas par son type.
     *
     * Depuis que l'on peut poser ses propres images — un logo de sponsor —,
     * « la première couche image » n'est plus le cadre : ce serait le logo,
     * et l'on chargerait le fichier du cadre à sa place.
     */
    const couche = (tpl.layers ?? []).find((l: any) => l.id === 'frame');
    cadreServeur = d.cadre || '';
    const source = fichierUrl ?? cadreServeur;

    if (couche && source && source !== cadreCharge) {
      try {
        assets[couche.id] = await loadImage(source);
        cadreCharge = source;
      } catch {
        delete assets[couche.id];
        cadreCharge = '';
      }
    }
    /**
     * Les images posées par l'auteur — un logo de sponsor — se chargent
     * comme le cadre. Sans cela, `renderScene` ne trouve pas leur bitmap et
     * ne dessine rien : le calque existe dans la liste, il est absent de
     * l'image, et l'on cherche longtemps pourquoi.
     */
    for (const l of (tpl.layers ?? []) as any[]) {
      if (l.type !== 'image' || l.id === 'frame' || assets[l.id] || !l.src) continue;
      try {
        assets[l.id] = await loadImage(l.src);
      } catch { /* une image injoignable laisse simplement sa place vide */ }
    }

    if (!photo && d.photo) {
      try {
        photo = await loadImage(d.photo);
      } catch { /* l'aperçu se passe de la photo d'exemple */ }
    }
    if (!qr && d.qr) {
      try {
        qr = await loadImage(d.qr);
      } catch { /* idem pour le QR d'illustration */ }
    }
    if (mien !== tour) return;

    dessiner(tpl);
    montrerSante(d.sante);
    montrerPalette(d.palette);
    etat.textContent = fichierUrl
      ? 'Aperçu avec votre cadre et une photo d’exemple.'
      : 'Aperçu avec un cadre et une photo d’exemple.';
  }

  function dessiner(tpl: any) {
    const MAX_L = 300, MAX_H = 430;
    const ratio = tpl.canvas.width / tpl.canvas.height;
    let l = MAX_L, h = Math.round(MAX_L / ratio);
    if (h > MAX_H) {
      h = MAX_H;
      l = Math.round(MAX_H * ratio);
    }
    const dpr = Math.min(2, window.devicePixelRatio || 1);
    toile.width = l * dpr;
    toile.height = h * dpr;
    toile.style.width = l + 'px';
    toile.style.height = h + 'px';

    const emplacement: Rect = (tpl.layers ?? []).find((c: any) => c.type === 'photoSlot')?.rect
      ?? { x: 0, y: 0, w: 1, h: 1 };

    const spec: RenderSpec = {
      templateId: tpl.id,
      photo: null,
      filter: 'none',
      filterIntensity: 0.35,
      texts: {},
      qr,
    };
    for (const c of tpl.layers ?? []) {
      if (c.type === 'text' && c.editable) spec.texts[c.id] = c.value || c.placeholder || '';
    }
    if (photo) {
      const p = { image: photo, x: 0, y: 0, scale: 1, flipX: false };
      spec.photo = clampPhoto(
        p, emplacement,
        (photo as HTMLImageElement).width || 1,
        (photo as HTMLImageElement).height || 1,
        tpl.canvas.width, tpl.canvas.height,
      );
    }

    renderScene(dessin, spec, tpl, assets, (l * dpr) / tpl.canvas.width);
    guide(emplacement, (tpl.layers ?? []).find((c: any) => c.type === 'photoSlot')?.mask,
          l * dpr, h * dpr);
    dernierTpl = tpl;
    poignees(l * dpr, h * dpr);
  }

  /* ================================================================ */
  /* Prendre les objets à la main                                      */
  /* ================================================================ */

  /**
   * Quatre curseurs pour poser un objet, c'est viser sans regarder.
   *
   * On garde les curseurs — ils donnent la valeur exacte, et permettent de
   * recopier un réglage d'un décor à l'autre — mais on peut aussi saisir
   * l'objet là où il est. Les deux commandes écrivent au même endroit, si
   * bien qu'aucune ne peut mentir sur ce que l'autre a fait.
   */
  type Prise =
    | { quoi: 'libre'; rang: number }
    | { quoi: 'photo' }
    | { quoi: 'texte' };

  let dernierTpl: any = null;
  let prise: Prise | null = null;
  let glisse: { coin: string; x0: number; y0: number; r0: Rect } | null = null;

  const champCalques = document.getElementById('champ-calques') as HTMLInputElement | null;

  const lireCalques = (): any[] => {
    if (!champCalques) return [];
    try {
      const v = JSON.parse(champCalques.value || '[]');
      return Array.isArray(v) ? v : [];
    } catch { return []; }
  };

  /** Le rectangle de l'objet pris, en fractions du canevas. */
  function rectDe(p: Prise): Rect | null {
    if (p.quoi === 'libre') {
      const c = lireCalques()[p.rang];
      return c ? { x: c.x, y: c.y, w: c.w, h: c.h } : null;
    }
    if (p.quoi === 'photo') {
      return { x: +val('photo_x'), y: +val('photo_y'), w: +val('photo_w'), h: +val('photo_h') };
    }
    /**
     * Le bloc de texte n'a pas de hauteur réglable : elle se déduit de la
     * taille de l'accroche. On la reconstitue pour dessiner une prise qui
     * corresponde à ce qu'on voit, sinon la poignée du bas serait ailleurs
     * que le bas du texte.
     */
    const t = dernierTpl?.layers?.find((c: any) => c.id === 'claim')?.rect;
    return t ? { x: t.x, y: t.y, w: t.w, h: t.h } : null;
  }

  /** Écrit le rectangle de l'objet pris, et redessine tout de suite. */
  function poserRect(p: Prise, r: Rect) {
    const borne = (v: number, min: number, max: number) => Math.max(min, Math.min(max, v));
    r = {
      x: borne(r.x, 0, 0.98), y: borne(r.y, 0, 0.98),
      w: borne(r.w, 0.03, 1), h: borne(r.h, 0.02, 1),
    };
    if (r.x + r.w > 1) r.w = 1 - r.x;
    if (r.y + r.h > 1) r.h = 1 - r.y;

    if (p.quoi === 'libre' && champCalques) {
      const cs = lireCalques();
      if (!cs[p.rang]) return;
      Object.assign(cs[p.rang], r);
      champCalques.value = JSON.stringify(cs);
      // Le panneau de calques écoute `input` : ses curseurs suivent la souris.
      champCalques.dispatchEvent(new Event('input', { bubbles: true }));
      if (dernierTpl) {
        const c = dernierTpl.layers?.find((x: any) => x.id === 'libre-' + (p.rang + 1));
        if (c) c.rect = { ...r };
      }
    } else if (p.quoi === 'photo') {
      poserCurseur('photo_x', r.x); poserCurseur('photo_y', r.y);
      poserCurseur('photo_w', r.w); poserCurseur('photo_h', r.h);
      const c = dernierTpl?.layers?.find((x: any) => x.type === 'photoSlot');
      if (c) c.rect = { ...r };
    } else {
      poserCurseur('bloc_x', r.x); poserCurseur('bloc_y', r.y); poserCurseur('bloc_w', r.w);
      const c = dernierTpl?.layers?.find((x: any) => x.id === 'claim');
      if (c) c.rect = { ...c.rect, x: r.x, y: r.y, w: r.w };
    }
    afficherValeurs();
    if (dernierTpl) dessiner(dernierTpl);
  }

  function poserCurseur(nom: string, v: number) {
    const el = form.elements.namedItem(nom) as HTMLInputElement | null;
    if (!el) return;
    const min = Number(el.min || 0), max = Number(el.max || 1);
    el.value = String(Math.max(min, Math.min(max, v)));
  }

  /**
   * Le contour et les quatre coins de l'objet pris.
   *
   * Deux traits superposés, comme pour la fenêtre photo : un décor peut être
   * noir ou blanc, et une seule couleur disparaîtrait sur l'un des deux.
   */
  function poignees(W: number, H: number) {
    if (!prise) return;
    const r = rectDe(prise);
    if (!r) return;
    const x = r.x * W, y = r.y * H, w = r.w * W, h = r.h * H;

    dessin.save();
    dessin.lineWidth = 2;
    dessin.strokeStyle = 'rgba(15,23,42,.65)';
    dessin.strokeRect(x, y, w, h);
    dessin.strokeStyle = '#2563EB';
    dessin.setLineDash([6, 4]);
    dessin.strokeRect(x, y, w, h);
    dessin.setLineDash([]);

    const c = 5;
    for (const [px, py] of [[x, y], [x + w, y], [x, y + h], [x + w, y + h]]) {
      dessin.fillStyle = '#FFFFFF';
      dessin.fillRect(px - c, py - c, c * 2, c * 2);
      dessin.strokeStyle = '#2563EB';
      dessin.lineWidth = 2;
      dessin.strokeRect(px - c, py - c, c * 2, c * 2);
    }
    dessin.restore();
  }

  /** L'objet le plus HAUT sous le pointeur — celui qu'on voit, donc. */
  function objetSous(fx: number, fy: number): Prise | null {
    const dans = (r: Rect | null) =>
      !!r && fx >= r.x && fx <= r.x + r.w && fy >= r.y && fy <= r.y + r.h;

    const cs = lireCalques();
    for (let i = cs.length - 1; i >= 0; i--) {
      if (cs[i].visible !== false && dans({ x: cs[i].x, y: cs[i].y, w: cs[i].w, h: cs[i].h })) {
        return { quoi: 'libre', rang: i };
      }
    }
    if (dans(rectDe({ quoi: 'texte' }))) return { quoi: 'texte' };
    if (dans(rectDe({ quoi: 'photo' }))) return { quoi: 'photo' };
    return null;
  }

  /** Le coin saisi, s'il y en a un — sinon on déplace l'objet entier. */
  function coinSous(r: Rect, fx: number, fy: number): string {
    const t = 0.045;
    const g = Math.abs(fx - r.x) < t, d = Math.abs(fx - (r.x + r.w)) < t;
    const hh = Math.abs(fy - r.y) < t, b = Math.abs(fy - (r.y + r.h)) < t;
    if (g && hh) return 'gh';
    if (d && hh) return 'dh';
    if (g && b) return 'gb';
    if (d && b) return 'db';
    return '';
  }

  const fractions = (e: PointerEvent) => {
    const b = toile.getBoundingClientRect();
    return { fx: (e.clientX - b.left) / b.width, fy: (e.clientY - b.top) / b.height };
  };

  toile.style.touchAction = 'none';
  toile.addEventListener('pointerdown', (e) => {
    const { fx, fy } = fractions(e);
    const cible = objetSous(fx, fy);
    prise = cible;
    if (!cible) { if (dernierTpl) dessiner(dernierTpl); return; }

    /* Prendre un objet sur l'image le sélectionne aussi dans le panneau :
       une seule sélection pour les deux, sinon on règle un objet en en
       regardant un autre. */
    if (cible.quoi === 'libre') {
      document.dispatchEvent(new CustomEvent('wakabi:calque', { detail: cible.rang }));
    }
    const r = rectDe(cible);
    if (!r) return;
    glisse = { coin: coinSous(r, fx, fy), x0: fx, y0: fy, r0: { ...r } };
    toile.setPointerCapture(e.pointerId);
    if (dernierTpl) dessiner(dernierTpl);
  });

  toile.addEventListener('pointermove', (e) => {
    if (!prise || !glisse) {
      // Sans prise en cours, le curseur annonce ce qu'on peut saisir.
      const { fx, fy } = fractions(e);
      const cible = objetSous(fx, fy);
      const r = cible ? rectDe(cible) : null;
      toile.style.cursor = !cible ? 'default'
        : (r && coinSous(r, fx, fy) ? 'nwse-resize' : 'move');
      return;
    }
    const { fx, fy } = fractions(e);
    const dx = fx - glisse.x0, dy = fy - glisse.y0, r0 = glisse.r0;
    const r = { ...r0 };
    switch (glisse.coin) {
      case 'gh': r.x = r0.x + dx; r.y = r0.y + dy; r.w = r0.w - dx; r.h = r0.h - dy; break;
      case 'dh': r.y = r0.y + dy; r.w = r0.w + dx; r.h = r0.h - dy; break;
      case 'gb': r.x = r0.x + dx; r.w = r0.w - dx; r.h = r0.h + dy; break;
      case 'db': r.w = r0.w + dx; r.h = r0.h + dy; break;
      default: r.x = r0.x + dx; r.y = r0.y + dy;
    }
    poserRect(prise, r);
  });

  const relacher = (e: PointerEvent) => {
    if (!glisse) return;
    glisse = null;
    try { toile.releasePointerCapture(e.pointerId); } catch { /* déjà relâché */ }
    /**
     * Pendant le glissement on redessine LOCALEMENT, sans le serveur : une
     * requête par image rendrait le geste saccadé sur une connexion lente,
     * et c'est précisément le geste qui doit être fluide. Le serveur a le
     * dernier mot une fois le doigt levé.
     */
    plusTard();
  };
  toile.addEventListener('pointerup', relacher);
  toile.addEventListener('pointercancel', relacher);

  /* Le panneau de calques annonce sa sélection ; l'aperçu la suit. */
  document.addEventListener('wakabi:selection', (e) => {
    const rang = (e as CustomEvent).detail;
    prise = typeof rang === 'number' && rang >= 0 ? { quoi: 'libre', rang } : null;
    if (dernierTpl) dessiner(dernierTpl);
  });

  /**
   * La santé du décor, telle que le pré-vol la rend.
   *
   * On n'invente rien ici : on affiche ce que le serveur a calculé avec la
   * fonction qui décide vraiment. Écrire une seconde liste de contrôles
   * côté navigateur aurait donné deux vérités, et c'est toujours la plus
   * optimiste qu'on croit.
   */
  /**
   * Les teintes du cadre s'ajoutent aux couleurs proposées.
   *
   * Six couleurs imposées, c'est la charte de Wakabi, pas celle de
   * l'organisateur. Les siennes sont dans le fichier qu'il vient de
   * déposer : on les lui relit plutôt que de lui demander un code
   * hexadécimal que personne ne connaît par cœur.
   *
   * On REMPLACE le groupe à chaque fois : changer de cadre change la
   * palette, et laisser traîner les teintes du cadre précédent proposerait
   * des couleurs qui ne sont plus nulle part sur l'image.
   */
  function montrerPalette(palette: unknown) {
    const teintes = Array.isArray(palette) ? (palette as string[]) : [];
    for (const sel of document.querySelectorAll('select')) {
      const s = sel as HTMLSelectElement;
      if (!/couleur|fond/.test(s.id) && !/couleur|fond/.test(s.name)) continue;

      const choisie = s.value;
      s.querySelector('optgroup[data-cadre]')?.remove();
      if (!teintes.length) continue;

      const g = document.createElement('optgroup');
      g.label = 'Relevées sur votre cadre';
      g.setAttribute('data-cadre', '1');
      for (const hex of teintes) {
        const o = document.createElement('option');
        o.value = hex;
        o.textContent = hex;
        g.appendChild(o);
      }
      s.appendChild(g);
      // Une teinte choisie qui vient de disparaître ne doit pas faire
      // retomber le select sur sa première option en silence.
      if (choisie) s.value = choisie;
      if (s.value !== choisie && choisie.startsWith('#')) {
        const o = document.createElement('option');
        o.value = choisie; o.textContent = choisie + ' (cadre précédent)';
        g.appendChild(o);
        s.value = choisie;
      }
    }
  }

  /** Le décor ne se laisse pas juger : on le dit, plutôt que de mentir. */
  function santeIndecise(pourquoi: string) {
    const liste = document.getElementById('sd-sante-liste');
    if (!liste) return;
    liste.textContent = '';
    const li = document.createElement('li');
    li.className = 'sd-ct echec';
    li.textContent = pourquoi;
    liste.appendChild(li);
  }

  function montrerSante(rapport: any) {
    const liste = document.getElementById('sd-sante-liste');
    if (!liste || !rapport || !Array.isArray(rapport.controles)) return;

    liste.textContent = '';
    for (const c of rapport.controles) {
      // Le contrat de données n'apprend rien à l'auteur : il est vrai ou le
      // gabarit ne serait pas là. On ne montre que ce sur quoi il peut agir.
      if (c.id === 'schema' && c.etat === 'ok') continue;
      const li = document.createElement('li');
      li.className = 'sd-ct ' + (c.etat === 'ok' ? 'ok' : c.etat === 'alerte' ? 'alerte' : 'echec');
      li.textContent = c.message;
      liste.appendChild(li);
    }
  }

  /**
   * Le pointillé qui montre la fenêtre photo.
   *
   * Quatre curseurs qui déplacent une zone invisible, c'est viser les yeux
   * fermés : l'ouverture d'un cadre ne se devine qu'une fois la photo posée
   * dedans, et jamais la partie qui déborde. Deux traits superposés — un
   * sombre, un clair, en pointillés décalés — pour rester lisible sur un
   * cadre noir comme sur un cadre blanc.
   *
   * Il n'existe QUE dans cet aperçu de réglage : ni le Studio de l'invité,
   * ni l'export ne le connaissent.
   */
  function guide(zone: Rect, masque: any, W: number, H: number) {
    // Une fenêtre qui occupe tout le canevas n'apprend rien à personne :
    // c'est le réglage d'usine, et le pointillé se confondrait avec le bord.
    if (zone.x <= 0 && zone.y <= 0 && zone.w >= 1 && zone.h >= 1) return;

    const x = zone.x * W, y = zone.y * H, w = zone.w * W, h = zone.h * H;
    const chemin = () => {
      dessin.beginPath();
      if (masque?.kind === 'circle') {
        dessin.arc(x + w / 2, y + h / 2, Math.min(w, h) / 2, 0, Math.PI * 2);
        return;
      }
      const r = Math.min((masque?.radius ?? 0) * Math.min(w, h), w / 2, h / 2);
      if (r <= 0) {
        dessin.rect(x, y, w, h);
        return;
      }
      // `roundRect` manque encore sur des navigateurs bien vivants au Togo.
      dessin.moveTo(x + r, y);
      dessin.arcTo(x + w, y, x + w, y + h, r);
      dessin.arcTo(x + w, y + h, x, y + h, r);
      dessin.arcTo(x, y + h, x, y, r);
      dessin.arcTo(x, y, x + w, y, r);
      dessin.closePath();
    };

    dessin.save();
    dessin.lineWidth = Math.max(1, W / 220);
    dessin.setLineDash([W / 46, W / 46]);
    dessin.strokeStyle = 'rgba(15,23,42,.85)';
    chemin();
    dessin.stroke();
    dessin.lineDashOffset = W / 46;
    dessin.strokeStyle = 'rgba(255,255,255,.95)';
    chemin();
    dessin.stroke();
    dessin.restore();
  }

  /**
   * « Détecter dans le cadre » : relever l'ouverture et poser la fenêtre.
   *
   * Lancé aussi tout seul quand on choisit un cadre, parce que c'est le
   * moment exact où l'information est disponible et où personne n'a encore
   * eu l'occasion d'y penser.
   */
  async function detecterFenetre(auto = false) {
    const bouton = document.getElementById('detecter-fenetre') as HTMLButtonElement | null;
    const source = fichierUrl || cadreServeur;
    if (!source) {
      if (!auto) etat.textContent = 'Choisissez d’abord un cadre : c’est lui qui porte l’ouverture.';
      return;
    }

    const libelle = bouton ? bouton.textContent : null;
    if (bouton) {
      bouton.disabled = true;
      bouton.textContent = 'Lecture du cadre…';
    }
    let trouvee: Fenetre | null = null;
    let format = '';
    try {
      const img = await loadImage(source);
      const iw = (img as HTMLImageElement).width || 1;
      const ih = (img as HTMLImageElement).height || 1;
      // Le format AVANT la fenêtre : un cadre 4:5 posé sur un canevas carré
      // s'aplatit d'un quart, et la fenêtre la mieux relevée du monde ne
      // rattrape pas une affiche écrasée.
      format = formatProche(iw, ih);
      trouvee = fenetreDuCadre(img as CanvasImageSource, iw, ih);
    } catch {
      trouvee = null;
    }
    if (bouton) {
      bouton.disabled = false;
      bouton.textContent = libelle ?? 'Détecter dans le cadre';
    }

    const poser = (nom: string, v: number | string) => {
      const el = form.elements.namedItem(nom) as HTMLInputElement | null;
      if (el) el.value = String(v);
    };
    const poserFenetre = () => {
      if (!trouvee) return;
      const f = fenetreArrondie(trouvee);
      poser('photo_x', f.x);
      poser('photo_y', f.y);
      poser('photo_w', f.w);
      poser('photo_h', f.h);
      poser('photo_forme', f.forme);
    };

    const changeFormat = format !== '' && format !== val('format');
    if (!trouvee && !changeFormat) {
      etat.textContent = auto
        ? 'Ce cadre n’a pas d’ouverture transparente nette : placez la fenêtre à la main, le pointillé la montre.'
        : 'Aucune ouverture transparente trouvée dans ce cadre. Réglez la fenêtre avec les curseurs.';
      return;
    }

    window.clearTimeout(minuteur);
    if (changeFormat) {
      // Changer de format, c'est changer les hauteurs : le QR est
      // dimensionné sur la largeur, donc il occupe une part de hauteur très
      // différente d'un format à l'autre, et un bloc de texte calé pour le
      // carré passe sous lui en 9:16. On repart donc des réglages d'usine du
      // NOUVEAU format — puis on repose la fenêtre relevée par-dessus, elle
      // seule ne se déduit pas du format mais du cadre.
      poser('format', format);
      afficherValeurs();
      await rafraichir('format');
    }
    poserFenetre();
    afficherValeurs();
    await rafraichir();

    etat.textContent = trouvee
      ? (changeFormat
          ? `Cadre relevé : format ${format}, et la photo se placera dans le pointillé.`
          : 'Ouverture relevée sur le cadre : la photo se placera dans le pointillé.')
      : `Format du décor aligné sur le cadre (${format}). Placez la fenêtre photo à la main.`;
  }

  /**
   * Envoyer le cadre tout de suite, sans attendre l'enregistrement.
   *
   * Il passe par la même porte que les images de calque, et son adresse
   * remplace `cadre_url` : à partir de là, l'aperçu et le pré-vol parlent
   * du même fichier. C'est aussi ce qui fait qu'un cadre survit à une
   * erreur de saisie — il était déjà déposé.
   */
  async function envoyerCadre(f: File) {
    const champ = form.elements.namedItem('cadre_url') as HTMLInputElement | null;
    if (!champ) return;
    const corps = new FormData();
    const jeton = form.elements.namedItem('csrf') as HTMLInputElement | null;
    corps.append('csrf', jeton?.value ?? '');
    corps.append('image', f);
    try {
      const r = await fetch(ctx.base + '?p=api-calque-image', { method: 'POST', body: corps });
      const d = await r.json();
      if (!d.url) return;
      champ.value = d.url;
      // Le fichier local a fait son office ; le serveur sert la suite.
      if (fichierUrl) URL.revokeObjectURL(fichierUrl);
      fichierUrl = null;
      cadreCharge = '';
      plusTard();
    } catch { /* on garde l'aperçu local : la saisie n'est pas perdue */ }
  }

  /* ---------------- écoute du formulaire ---------------- */

  const plusTard = (reinit: '' | 'disposition' | 'format' = '') => {
    window.clearTimeout(minuteur);
    minuteur = window.setTimeout(() => rafraichir(reinit), 220);
  };

  // Changer de gabarit OU de format repart des réglages d'usine : toutes les
  // hauteurs se déduisent du format — le QR est dimensionné sur la largeur —
  // et les garder en passant du carré au paysage poserait le texte sous lui.
  // La nuance : changer de GABARIT reprend aussi son format d'origine, alors
  // que changer de FORMAT garde celui qu'on vient de choisir.
  const remetAZero = (id: string): '' | 'disposition' | 'format' =>
    id === 'disposition' ? 'disposition' : id === 'r-format' ? 'format' : '';

  form.addEventListener('input', (e) => {
    afficherValeurs();
    ajusterGroupes();
    plusTard(remetAZero((e.target as HTMLElement).id));
  });
  form.addEventListener('change', (e) => {
    const cible = e.target as HTMLInputElement;
    if (cible.id === 'cadre') {
      // On le lit sur place pour que l'image apparaisse SANS ATTENDRE le
      // réseau : sur une connexion lente, deux secondes d'écran vide après
      // avoir choisi un fichier font croire que rien ne s'est passé.
      if (fichierUrl) URL.revokeObjectURL(fichierUrl);
      const f = cible.files?.[0];
      fichierUrl = f ? URL.createObjectURL(f) : null;
      cadreCharge = '';
      const nom = document.querySelector('.fichier .texte');
      if (nom) nom.textContent = f ? f.name : 'Choisir un fichier';
      if (f) {
        // …puis on l'envoie, parce que le serveur ne peut juger que ce
        // qu'il a. Sans cela la santé du décor se prononcerait sur le cadre
        // PRÉCÉDENT, et dirait « 36 Ko, bien lisible » d'un fichier qu'elle
        // n'a jamais vu — le pire des mensonges, celui qui rassure.
        envoyerCadre(f);
        detecterFenetre(true);
        return;
      }
    }
    if (cible.id === 'cadre_fourni') {
      // Celui-là n'existe que côté serveur : on attend sa réponse, puis on lit.
      window.clearTimeout(minuteur);
      rafraichir().then(() => {
        if (cible.value) detecterFenetre(true);
      });
      return;
    }
    plusTard(remetAZero(cible.id));
  });

  document.getElementById('apparence-defaut')?.addEventListener('click', () => rafraichir('format'));
  document.getElementById('detecter-fenetre')?.addEventListener('click', () => detecterFenetre());

  afficherValeurs();
  ajusterGroupes();
  // Les polices d'abord : un canevas dessine avec ce qui est chargé.
  void attendrePolices().then(() => rafraichir());
}

const ctx = (window as unknown as { WAKABI_APERCU?: Contexte }).WAKABI_APERCU;
if (ctx) demarrer(ctx);
