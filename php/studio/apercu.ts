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

interface Contexte {
  base: string;
  csrf: string;
}

const CHAMPS = [
  'disposition', 'cadre_url', 'cadre_fourni', 'titre', 'accroche', 'champ_libelle',
  'texte_couleur', 'texte_align', 'bloc_x', 'bloc_y', 'bloc_w',
  'accroche_taille', 'champ_taille', 'qr_position', 'qr_taille', 'filigrane_position',
  'format', 'fond', 'photo_x', 'photo_y', 'photo_w', 'photo_h', 'photo_forme',
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
    const couche = (tpl.layers ?? []).find((l: any) => l.type === 'image');
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
      // Le cadre choisi n'est pas encore sur le serveur : on le lit sur place.
      if (fichierUrl) URL.revokeObjectURL(fichierUrl);
      const f = cible.files?.[0];
      fichierUrl = f ? URL.createObjectURL(f) : null;
      cadreCharge = '';
      const nom = document.querySelector('.fichier .texte');
      if (nom) nom.textContent = f ? f.name : 'Choisir un fichier';
      // Un cadre neuf porte sa propre ouverture : la relever maintenant
      // évite d'avoir à espérer que quelqu'un y pense.
      if (fichierUrl) {
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
  rafraichir();
}

const ctx = (window as unknown as { WAKABI_APERCU?: Contexte }).WAKABI_APERCU;
if (ctx) demarrer(ctx);
