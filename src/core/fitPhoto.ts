/**
 * Cadrage de la photo dans son emplacement.
 *
 * Deux règles tiennent tout l'éditeur :
 *  - `scale` est un multiplicateur du cadrage « cover » : 1 remplit
 *    exactement l'emplacement, au-dessus on agrandit, en dessous on réduit ;
 *  - la photo reste DANS son emplacement, quoi qu'il arrive.
 *
 * La deuxième règle a longtemps été « la photo ne laisse jamais de vide »,
 * ce qui interdisait toute échelle inférieure à 1. Une affiche panoramique
 * ou une image carrée dans un décor vertical devenaient alors impossibles à
 * cadrer autrement qu'en coupant l'essentiel. Sous 1, la photo est
 * simplement plus petite que son emplacement et le fond du décor apparaît
 * autour : c'est un choix de mise en page, pas un défaut.
 */

import type { PhotoState } from './types';

export interface Rect {
  x: number;
  y: number;
  w: number;
  h: number;
}

/** Convertit un rectangle normalisé (0..1) en pixels. */
export function toPx(rect: Rect, width: number, height: number): Rect {
  return {
    x: rect.x * width,
    y: rect.y * height,
    w: rect.w * width,
    h: rect.h * height,
  };
}

/** Échelle qui fait exactement remplir l'emplacement (« cover »). */
export function coverScale(
  imgW: number,
  imgH: number,
  slotW: number,
  slotH: number,
): number {
  return Math.max(slotW / imgW, slotH / imgH);
}

/**
 * Multiplicateur qui fait tenir l'image ENTIÈRE dans l'emplacement.
 *
 * Exprimé par rapport au cadrage « cover », puisque c'est l'unité de
 * `scale`. Toujours ≤ 1 : c'est le « tout afficher » d'un éditeur photo, et
 * la réponse en un geste à « ma photo ne rentre pas ».
 */
export function containScale(
  imgW: number,
  imgH: number,
  slotW: number,
  slotH: number,
): number {
  const cover = coverScale(imgW, imgH, slotW, slotH);
  if (cover <= 0) return 1;
  return Math.min(slotW / imgW, slotH / imgH) / cover;
}

/**
 * Ramène la photo dans les bornes de son emplacement.
 *
 * La marge de manœuvre est la DIFFÉRENCE, en valeur absolue, entre l'image
 * dessinée et l'emplacement : au-dessus de 1 on fait glisser une image plus
 * grande que la fenêtre, en dessous on déplace une image plus petite à
 * l'intérieur. Un seul calcul couvre les deux, et dans les deux cas la
 * photo ne peut pas sortir du cadre.
 *
 * `bornes` limite l'échelle à ce que le gabarit autorise. Sans elle, la
 * valeur passe telle quelle : les appelants qui bornent déjà de leur côté
 * n'ont rien à changer.
 *
 * Toutes les valeurs sont en unités normalisées du canevas.
 */
export function clampPhoto(
  photo: PhotoState,
  slot: Rect,
  imgW: number,
  imgH: number,
  canvasW: number,
  canvasH: number,
  bornes?: { min: number; max: number },
): PhotoState {
  const slotPxW = slot.w * canvasW;
  const slotPxH = slot.h * canvasH;

  const scale = bornes
    ? Math.min(bornes.max, Math.max(bornes.min, photo.scale))
    : Math.max(0.01, photo.scale);
  const s = coverScale(imgW, imgH, slotPxW, slotPxH) * scale;
  const drawnW = imgW * s;
  const drawnH = imgH * s;

  // Marge de manœuvre en pixels, puis ramenée en unités normalisées.
  const slackX = Math.abs(drawnW - slotPxW) / 2 / canvasW;
  const slackY = Math.abs(drawnH - slotPxH) / 2 / canvasH;

  return {
    ...photo,
    scale,
    x: Math.min(slackX, Math.max(-slackX, photo.x)),
    y: Math.min(slackY, Math.max(-slackY, photo.y)),
  };
}
