/**
 * Cadrage de la photo dans son emplacement.
 *
 * Deux règles tiennent tout l'éditeur :
 *  - `scale` est un multiplicateur du cadrage « cover » ;
 *  - la photo ne doit JAMAIS laisser apparaître un vide dans l'emplacement.
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
 * Ramène le décalage dans les bornes où la photo couvre encore tout
 * l'emplacement. Sans cela, un glissement un peu vif laisse un bord vide —
 * le défaut le plus visible d'un générateur de ce genre.
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
): PhotoState {
  const slotPxW = slot.w * canvasW;
  const slotPxH = slot.h * canvasH;

  const scale = Math.max(1, photo.scale);
  const s = coverScale(imgW, imgH, slotPxW, slotPxH) * scale;
  const drawnW = imgW * s;
  const drawnH = imgH * s;

  // Marge de manœuvre en pixels, puis ramenée en unités normalisées.
  const slackX = Math.max(0, (drawnW - slotPxW) / 2) / canvasW;
  const slackY = Math.max(0, (drawnH - slotPxH) / 2) / canvasH;

  return {
    ...photo,
    scale,
    x: Math.min(slackX, Math.max(-slackX, photo.x)),
    y: Math.min(slackY, Math.max(-slackY, photo.y)),
  };
}
