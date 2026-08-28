/**
 * Où se trouve l'ouverture d'un cadre ?
 *
 * Un cadre PNG dit déjà où va la photo de l'invité : c'est le trou
 * transparent au milieu du dessin. Tant que personne ne le lit, la fenêtre
 * photo reste le canevas ENTIER — la photo est cadrée sur toute la surface,
 * et seule la part qui tombe par hasard dans l'ouverture se voit. C'est très
 * exactement le défaut qu'on décrit en disant « l'image n'est pas bonne dans
 * le cadre » : le visage tombe où il veut.
 *
 * Une seule implémentation, deux appelants : la fabrication des cadres
 * fournis (Node, via sharp) et le formulaire de décor (navigateur, via un
 * canevas hors écran). Deux mesures qui ne diraient pas la même chose
 * vaudraient moins qu'une.
 */

export interface Fenetre {
  x: number;
  y: number;
  w: number;
  h: number;
  /** `rect`, `arrondi` ou `cercle` — le masque à appliquer à la photo. */
  forme: 'rect' | 'arrondi' | 'cercle';
}

/**
 * Alpha en dessous duquel un pixel est considéré comme percé.
 *
 * Le même seuil que le pré-vol côté serveur (`php/app/prevol.php`) : c'est
 * ce qui permet à l'organisateur d'entendre deux fois la même chose.
 */
export const SEUIL_ALPHA = 40;

/** Sous 1 % du cadre, ce n'est pas une fenêtre : c'est du bruit de bord. */
const AIRE_MINIMALE = 0.01;

/**
 * Le plus grand ensemble de pixels transparents **d'un seul tenant**.
 *
 * « D'un seul tenant » n'est pas un détail : les bords adoucis d'un logo
 * posé dans un coin sont eux aussi transparents, et une simple boîte
 * englobante de tous les pixels percés rendrait le canevas entier — soit
 * exactement le réglage qu'on cherche à corriger.
 *
 * @param rgba  pixels bruts, 4 octets par pixel (canvas `getImageData`,
 *              ou sharp `.ensureAlpha().raw()`)
 */
export function fenetreOuverte(
  rgba: ArrayLike<number>,
  w: number,
  h: number,
): Fenetre | null {
  const total = w * h;
  if (total <= 0 || rgba.length < total * 4) return null;

  const perce = new Uint8Array(total);
  let percés = 0;
  for (let i = 0, a = 3; i < total; i++, a += 4) {
    if (rgba[a] < SEUIL_ALPHA) {
      perce[i] = 1;
      percés++;
    }
  }
  if (percés < total * AIRE_MINIMALE) return null;

  // Remplissage itératif, jamais récursif : une zone de 60 000 pixels
  // déborderait la pile d'appels d'un navigateur mobile.
  const vu = new Uint8Array(total);
  const pile = new Int32Array(total);
  let meilleure: Fenetre | null = null;
  let aireMax = 0;

  for (let depart = 0; depart < total; depart++) {
    if (!perce[depart] || vu[depart]) continue;
    let sommet = 0;
    pile[sommet++] = depart;
    vu[depart] = 1;
    let aire = 0;
    let x0 = w, y0 = h, x1 = -1, y1 = -1;

    while (sommet > 0) {
      const p = pile[--sommet];
      const px = p % w;
      const py = (p / w) | 0;
      aire++;
      if (px < x0) x0 = px;
      if (px > x1) x1 = px;
      if (py < y0) y0 = py;
      if (py > y1) y1 = py;
      if (px > 0 && perce[p - 1] && !vu[p - 1]) { vu[p - 1] = 1; pile[sommet++] = p - 1; }
      if (px < w - 1 && perce[p + 1] && !vu[p + 1]) { vu[p + 1] = 1; pile[sommet++] = p + 1; }
      if (py > 0 && perce[p - w] && !vu[p - w]) { vu[p - w] = 1; pile[sommet++] = p - w; }
      if (py < h - 1 && perce[p + w] && !vu[p + w]) { vu[p + w] = 1; pile[sommet++] = p + w; }
    }

    if (aire <= aireMax) continue;
    aireMax = aire;
    const bw = x1 - x0 + 1;
    const bh = y1 - y0 + 1;
    // Un disque occupe π/4 ≈ 79 % de sa boîte, un rectangle 100 %.
    const remplissage = aire / (bw * bh);
    const carre = Math.abs(bw - bh) / Math.max(bw, bh) < 0.08;
    meilleure = {
      x: x0 / w,
      y: y0 / h,
      w: bw / w,
      h: bh / h,
      forme: remplissage > 0.93 ? 'rect'
        : (carre && remplissage > 0.68 && remplissage < 0.9) ? 'cercle'
        : 'arrondi',
    };
  }

  return aireMax < total * AIRE_MINIMALE ? null : meilleure;
}

/**
 * La fenêtre élargie d'un cheveu, arrondie au pas des curseurs.
 *
 * Les contours d'une ouverture sont adoucis : mieux vaut que la photo passe
 * légèrement SOUS le cadre — qui la recouvre — que de laisser apparaître un
 * liseré de fond tout autour.
 */
export function fenetreArrondie(f: Fenetre, marge = 0.005, pas = 0.005): Fenetre {
  // Deux arrondis : au pas des curseurs, puis au millième — sans quoi
  // 0,945 s'écrit 0.9450000000000001 dans le manifeste.
  const arr = (v: number) => Math.round(Math.round(v / pas) * pas * 1000) / 1000;
  const x = Math.max(0, f.x - marge);
  const y = Math.max(0, f.y - marge);
  return {
    x: arr(x),
    y: arr(y),
    w: arr(Math.min(1 - x, f.w + 2 * marge)),
    h: arr(Math.min(1 - y, f.h + 2 * marge)),
    forme: f.forme,
  };
}

/** Les formats de canevas proposés, et leur rapport largeur/hauteur. */
export const FORMATS: Record<string, number> = {
  '1:1': 1,
  '4:5': 0.8,
  '9:16': 0.5625,
  '16:9': 1.7778,
};

/**
 * Le format de canevas le plus proche des proportions d'une image.
 *
 * C'est le CADRE qui impose le format du décor : étiré sur un canevas qui
 * n'est pas le sien, il s'aplatit, et aucun réglage de fenêtre photo ne
 * rattrape une affiche écrasée d'un quart.
 */
export function formatProche(largeur: number, hauteur: number): string {
  const r = hauteur > 0 ? largeur / hauteur : 1;
  let meilleur = '1:1';
  let ecart = Infinity;
  for (const [nom, valeur] of Object.entries(FORMATS)) {
    // Écart RELATIF : en absolu, 16:9 serait toujours le plus loin de tout.
    const d = Math.abs(Math.log(r / valeur));
    if (d < ecart) {
      ecart = d;
      meilleur = nom;
    }
  }
  return meilleur;
}
