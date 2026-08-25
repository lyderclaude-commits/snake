/**
 * Décodage et préparation de la photo de l'utilisateur.
 *
 * Deux pièges traités ici, qui sont ceux qui font échouer ce genre d'outil
 * en production (docs/00-SPEC-TECHNIQUE.md §7) :
 *
 *  1. L'ORIENTATION EXIF. Une photo prise au téléphone porte une rotation
 *     dans ses métadonnées. Lue naïvement, elle s'affiche couchée.
 *  2. LA SATURATION MÉMOIRE. Une photo de 12 Mpx décompressée occupe ~48 Mo.
 *     Deux ou trois manipulations et un Android d'entrée de gamme fait
 *     tomber l'onglet. On réduit donc AVANT toute mise en scène, et
 *     l'original n'est jamais conservé.
 *
 * La photo ne quitte jamais l'appareil (contrainte C6) : rien n'est
 * téléversé, rien n'est conservé.
 */

import type { LoadedImage } from './types';

/** Grand côté maximal conservé en mémoire. Au-delà, on réduit. */
export const MAX_EDGE = 2560;

export interface DecodedPhoto {
  image: LoadedImage;
  width: number;
  height: number;
  /** true si l'image a été réduite par rapport à l'originale. */
  downscaled: boolean;
}

function supportsImageBitmap(): boolean {
  return typeof createImageBitmap === 'function';
}

/** Réduit une source déjà décodée si elle dépasse MAX_EDGE. */
function downscale(src: LoadedImage, w: number, h: number): DecodedPhoto {
  const longest = Math.max(w, h);
  if (longest <= MAX_EDGE) return { image: src, width: w, height: h, downscaled: false };

  const ratio = MAX_EDGE / longest;
  const tw = Math.round(w * ratio);
  const th = Math.round(h * ratio);

  const canvas = document.createElement('canvas');
  canvas.width = tw;
  canvas.height = th;
  const ctx = canvas.getContext('2d');
  if (!ctx) return { image: src, width: w, height: h, downscaled: false };
  ctx.imageSmoothingQuality = 'high';
  ctx.drawImage(src as CanvasImageSource, 0, 0, tw, th);

  // L'ImageBitmap d'origine occupe de la mémoire tant qu'il n'est pas fermé.
  if (typeof ImageBitmap !== 'undefined' && src instanceof ImageBitmap) src.close();

  return { image: canvas, width: tw, height: th, downscaled: true };
}

/** Repli pour les navigateurs sans createImageBitmap. */
function decodeViaElement(file: File): Promise<DecodedPhoto> {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(file);
    const img = new Image();
    img.onload = () => {
      URL.revokeObjectURL(url);
      resolve(downscale(img, img.naturalWidth, img.naturalHeight));
    };
    img.onerror = () => {
      URL.revokeObjectURL(url);
      reject(new Error("Cette image n'a pas pu être ouverte."));
    };
    img.src = url;
  });
}

export async function decodePhoto(file: File): Promise<DecodedPhoto> {
  if (!file.type.startsWith('image/')) {
    throw new Error('Choisis une image (JPEG, PNG ou WebP).');
  }

  if (!supportsImageBitmap()) return decodeViaElement(file);

  try {
    // `imageOrientation: 'from-image'` applique la rotation EXIF au décodage.
    // Sans elle, les photos prises en portrait arrivent couchées.
    const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    return downscale(bitmap, bitmap.width, bitmap.height);
  } catch {
    return decodeViaElement(file);
  }
}

/** Charge une image depuis une URL (cadres, logo). */
export function loadImage(src: string): Promise<HTMLImageElement> {
  return new Promise((resolve, reject) => {
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = () => resolve(img);
    img.onerror = () => reject(new Error(`Image introuvable : ${src}`));
    img.src = src;
  });
}
