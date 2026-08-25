/**
 * Wakabi Studio — état d'un visuel en cours d'édition.
 *
 * Le RenderSpec décrit INTÉGRALEMENT ce qui doit être dessiné. Il est plat,
 * sérialisable (hors bitmap), et c'est le seul état que l'éditeur manipule.
 * Voir docs/00-SPEC-TECHNIQUE.md §4.
 */

import type { FilterId } from './template.schema';

/** Une image déjà décodée, prête à être dessinée. */
export type LoadedImage = ImageBitmap | HTMLImageElement | HTMLCanvasElement;

export interface PhotoState {
  image: LoadedImage;
  /**
   * Décalage depuis le centre de l'emplacement photo, en unités NORMALISÉES
   * du canevas (0..1). 0,0 = centré. Indépendant de la résolution, donc
   * identique en aperçu et à l'export.
   */
  x: number;
  y: number;
  /**
   * Multiplicateur appliqué au cadrage « cover ». 1 = la photo remplit
   * exactement l'emplacement. En dessous de 1 un vide apparaîtrait, d'où
   * le plancher dans clampPhoto().
   */
  scale: number;
  flipX: boolean;
}

export interface RenderSpec {
  templateId: string;
  photo: PhotoState | null;
  filter: FilterId;
  filterIntensity: number;
  /** slotId -> contenu saisi par l'utilisateur. */
  texts: Record<string, string>;
  /**
   * QR du badge, déjà rendu en image. Émis au moment du téléchargement :
   * l'aperçu n'en a pas, l'export oui — chaque badge doit porter un jeton
   * unique, et un aperçu n'en consomme pas.
   */
  qr?: LoadedImage | null;
}

/**
 * Images des calques déjà chargées, indexées par id de calque.
 * renderScene est SYNCHRONE et pure : elle ne charge rien elle-même.
 * C'est ce qui la rend testable et utilisable dans un worker.
 */
export type LayerAssets = Record<string, LoadedImage | undefined>;

/** Contexte 2D, DOM ou worker — renderScene ne fait pas la différence. */
export type Ctx2D = CanvasRenderingContext2D | OffscreenCanvasRenderingContext2D;
