/**
 * Recolorer un cadre, sans toucher à ce qui n'a pas de couleur.
 *
 * Un cadre Wakabi est un aplat : un fond coloré, une bande, un accent, et
 * du texte blanc. « Changer sa couleur » veut dire une chose précise pour
 * celui qui le demande — que le bleu devienne SA couleur — et surtout pas
 * que le texte blanc et la bande noire y passent aussi.
 *
 * La règle tient donc en une phrase, et l'écran la dit telle quelle : le
 * cadre prend la couleur choisie, ses clairs et ses sombres sont gardés, et
 * ce qui est blanc, noir ou gris ne bouge pas.
 *
 * Ce module vit à côté du moteur de rendu, et non dedans : `renderScene`
 * ne dépend ni du DOM ni d'une seconde surface, et c'est ce qui la rend
 * utilisable dans un worker. La teinte est appliquée À L'IMAGE, une fois,
 * là où elle est chargée — l'aperçu, le Studio de l'invité et l'export
 * reçoivent donc le même bitmap déjà recoloré, et ne peuvent pas diverger.
 */
import type { LoadedImage } from './types';

/** En dessous, un pixel est gris : il garde sa couleur. */
const SEUIL_GRIS = 0.15;

/** Une surface de travail, sans exiger le DOM quand on peut s'en passer. */
function surface(w: number, h: number): { c: HTMLCanvasElement | OffscreenCanvas;
                                          ctx: CanvasRenderingContext2D | OffscreenCanvasRenderingContext2D } | null {
  try {
    if (typeof OffscreenCanvas !== 'undefined') {
      const c = new OffscreenCanvas(w, h);
      const ctx = c.getContext('2d', { willReadFrequently: true });
      if (ctx) return { c, ctx };
    }
  } catch { /* on essaie l'autre */ }
  try {
    if (typeof document !== 'undefined') {
      const c = document.createElement('canvas');
      c.width = w; c.height = h;
      const ctx = c.getContext('2d', { willReadFrequently: true });
      if (ctx) return { c, ctx };
    }
  } catch { /* tant pis */ }
  return null;
}

/** Teinte et saturation d'une couleur, en 0..1. */
function teinteEtSaturation(hex: string): { h: number; s: number } | null {
  const m = /^#([0-9a-f]{6})$/i.exec(hex.trim());
  if (!m) return null;
  const n = parseInt(m[1], 16);
  const r = ((n >> 16) & 255) / 255, v = ((n >> 8) & 255) / 255, b = (n & 255) / 255;
  const max = Math.max(r, v, b), min = Math.min(r, v, b);
  const l = (max + min) / 2;
  if (max === min) return { h: 0, s: 0 };
  const d = max - min;
  const s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
  let h = 0;
  if (max === r) h = ((v - b) / d + (v < b ? 6 : 0)) / 6;
  else if (max === v) h = ((b - r) / d + 2) / 6;
  else h = ((r - v) / d + 4) / 6;
  return { h, s };
}

/** HSL vers RVB, en 0..255. */
function versRvb(h: number, s: number, l: number): [number, number, number] {
  if (s === 0) { const v = Math.round(l * 255); return [v, v, v]; }
  const q = l < 0.5 ? l * (1 + s) : l + s - l * s;
  const p = 2 * l - q;
  const canal = (t: number): number => {
    if (t < 0) t += 1;
    if (t > 1) t -= 1;
    if (t < 1 / 6) return p + (q - p) * 6 * t;
    if (t < 1 / 2) return q;
    if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
    return p;
  };
  return [Math.round(canal(h + 1 / 3) * 255), Math.round(canal(h) * 255),
          Math.round(canal(h - 1 / 3) * 255)];
}

/**
 * Le même bitmap, recoloré — ou celui d'origine si l'on ne peut pas.
 *
 * Ne pas pouvoir n'est pas une panne : un navigateur sans surface de
 * travail rendra le cadre tel qu'il est, ce qui reste un badge juste. Un
 * cadre absent serait une panne ; un cadre non recoloré est un cadre.
 */
export function teinterImage(img: LoadedImage, hex: string): LoadedImage {
  const cible = teinteEtSaturation(hex);
  if (!cible || cible.s === 0) return img;

  const w = (img as HTMLImageElement).naturalWidth || (img as ImageBitmap).width;
  const h = (img as HTMLImageElement).naturalHeight || (img as ImageBitmap).height;
  if (!w || !h) return img;

  const s = surface(w, h);
  if (!s) return img;

  s.ctx.drawImage(img as CanvasImageSource, 0, 0, w, h);
  let d: ImageData;
  try {
    d = s.ctx.getImageData(0, 0, w, h);
  } catch {
    // Une image d'une autre origine souille le canevas : on rend l'original
    // plutôt que de lever au milieu d'un rendu.
    return img;
  }

  const px = d.data;
  for (let i = 0; i < px.length; i += 4) {
    if (px[i + 3] === 0) continue;
    const r = px[i] / 255, v = px[i + 1] / 255, b = px[i + 2] / 255;
    const max = Math.max(r, v, b), min = Math.min(r, v, b);
    const l = (max + min) / 2;
    if (max === min) continue;                       // gris pur
    const dl = max - min;
    const sat = l > 0.5 ? dl / (2 - max - min) : dl / (max + min);
    if (sat < SEUIL_GRIS) continue;                  // presque gris : on n'y touche pas
    const [nr, nv, nb] = versRvb(cible.h, cible.s, l);
    px[i] = nr; px[i + 1] = nv; px[i + 2] = nb;
  }
  s.ctx.putImageData(d, 0, 0);
  return s.c as LoadedImage;
}

/**
 * La teinte d'un gabarit, s'il en porte une.
 *
 * Lue sur le calque du cadre plutôt que passée à part : le gabarit se
 * suffit, on le copie et on le restaure sans avoir à transporter un
 * réglage qui vivrait ailleurs.
 */
export function teinteDuCadre(tpl: unknown): string {
  const couches = (tpl as { layers?: Array<Record<string, unknown>> })?.layers ?? [];
  const cadre = couches.find((l) => l.id === 'frame');
  const t = cadre?.tint;
  return typeof t === 'string' && /^#[0-9a-f]{6}$/i.test(t) ? t : '';
}
