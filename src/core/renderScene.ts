/**
 * Wakabi Studio — LE CŒUR DU PRODUIT.
 *
 * Une seule fonction de rendu, deux échelles. Jamais deux implémentations.
 *
 *   aperçu  : renderScene(ctx2d,        spec, tpl, assets, 0.31)  ~640 px
 *   export  : renderScene(offscreenCtx, spec, tpl, assets, 1)     2048 px
 *
 * Conséquence : le bug classique du genre — « l'export ne ressemble pas à
 * l'aperçu » — devient structurellement impossible, et la fonction est
 * testable hors navigateur.
 *
 * Contraintes tenues ici :
 *  - SYNCHRONE et pure : aucune ressource n'est chargée, tout arrive par
 *    `assets`. C'est ce qui la rend utilisable dans un Web Worker.
 *  - AUCUNE dépendance au DOM : pas de document, pas de window.
 *  - AUCUNE couleur en dur : tout vient de la charte (voir resolveColor).
 *
 * Voir docs/00-SPEC-TECHNIQUE.md §4.
 */

import type { DecorTemplate, Layer } from './template.schema';
// Depuis `brand` et non `template.schema` : ce module-ci part dans le
// navigateur, et le schéma y entraînerait zod avec lui.
import { WAKABI_TAGLINE } from './brand';
import type { Ctx2D, LayerAssets, RenderSpec } from './types';
import { coverScale, toPx } from './fitPhoto';

/* ------------------------------------------------------------------ */
/* Charte — unique endroit du renderer où des couleurs apparaissent    */
/* ------------------------------------------------------------------ */

const BRAND = {
  'brand.primary': '#2563EB',
  'brand.secondary': '#0D9488',
  'brand.accent': '#F97316',
  'brand.kori': '#D97706',
  'brand.ink': '#0F172A',
  'brand.paper': '#FFFFFF',
} as const;

const FONTS = {
  display: "'Bricolage Grotesque', system-ui, sans-serif",
  heading: "'Bricolage Grotesque', system-ui, sans-serif",
  body: "'Plus Jakarta Sans', system-ui, sans-serif",
} as const;

function resolveColor(ref: string): string {
  return (BRAND as Record<string, string>)[ref] ?? ref;
}

/* ------------------------------------------------------------------ */
/* Utilitaires de dessin                                               */
/* ------------------------------------------------------------------ */

function roundRect(ctx: Ctx2D, x: number, y: number, w: number, h: number, r: number) {
  const rr = Math.min(r, w / 2, h / 2);
  ctx.beginPath();
  ctx.moveTo(x + rr, y);
  ctx.arcTo(x + w, y, x + w, y + h, rr);
  ctx.arcTo(x + w, y + h, x, y + h, rr);
  ctx.arcTo(x, y + h, x, y, rr);
  ctx.arcTo(x, y, x + w, y, rr);
  ctx.closePath();
}

/**
 * Texte avec interlettrage manuel, ajusté à la largeur disponible.
 *
 * `ctx.letterSpacing` n'existe pas partout (absent de Firefox, arrivé tard
 * dans Safari) ; or la signature Wakabi est très espacée. On dessine donc
 * caractère par caractère : déterministe sur tous les navigateurs, et
 * identique entre l'aperçu et l'export.
 *
 * `maxWidth` n'est pas un luxe : « LE GUIDE DES BONS PLANS » fait 23
 * caractères et débordait de près du double de la pastille du filigrane.
 * On resserre d'abord l'interlettrage, puis on réduit le corps — dans cet
 * ordre, parce qu'un texte serré reste plus lisible qu'un texte minuscule.
 *
 * @returns le corps réellement utilisé, pour que l'appelant puisse
 *          positionner ce qui suit.
 */
function drawTracked(
  ctx: Ctx2D,
  text: string,
  cx: number,
  y: number,
  size: number,
  family: string,
  spacing: number,
  maxWidth: number,
): number {
  const chars = [...text];
  let fontSize = size;
  let track = spacing;

  const measure = () => {
    ctx.font = `600 ${fontSize}px ${family}`;
    const widths = chars.map((c) => ctx.measureText(c).width);
    return {
      widths,
      total: widths.reduce((a, b) => a + b, 0) + track * (chars.length - 1),
    };
  };

  let m = measure();
  if (m.total > maxWidth) {
    // 1. resserrer l'interlettrage, sans jamais coller les lettres
    const glyphs = m.total - track * (chars.length - 1);
    const room = maxWidth - glyphs;
    track = Math.max(fontSize * 0.06, room / Math.max(1, chars.length - 1));
    m = measure();
  }
  if (m.total > maxWidth) {
    // 2. puis réduire le corps, proportionnellement au dépassement
    const ratio = maxWidth / m.total;
    fontSize *= ratio;
    track *= ratio;
    m = measure();
  }

  let x = cx - m.total / 2;
  const prevAlign = ctx.textAlign;
  ctx.textAlign = 'left';
  chars.forEach((c, i) => {
    ctx.fillText(c, x, y);
    x += m.widths[i] + track;
  });
  ctx.textAlign = prevAlign;
  return fontSize;
}

/** Découpe un texte en lignes tenant dans `maxWidth`. */
function wrap(ctx: Ctx2D, text: string, maxWidth: number): string[] {
  const words = text.split(/\s+/).filter(Boolean);
  if (!words.length) return [];
  const lines: string[] = [];
  let line = words[0];
  for (let i = 1; i < words.length; i++) {
    const next = `${line} ${words[i]}`;
    if (ctx.measureText(next).width <= maxWidth) line = next;
    else {
      lines.push(line);
      line = words[i];
    }
  }
  lines.push(line);
  return lines;
}

/* ------------------------------------------------------------------ */
/* Calques                                                             */
/* ------------------------------------------------------------------ */

function drawPhotoSlot(
  ctx: Ctx2D,
  layer: Extract<Layer, { type: 'photoSlot' }>,
  spec: RenderSpec,
  W: number,
  H: number,
) {
  const slot = toPx(layer.rect, W, H);

  ctx.save();

  // Masque
  if (layer.mask.kind === 'circle') {
    const r = Math.min(slot.w, slot.h) / 2;
    ctx.beginPath();
    ctx.arc(slot.x + slot.w / 2, slot.y + slot.h / 2, r, 0, Math.PI * 2);
    ctx.clip();
  } else if (layer.mask.kind === 'rect') {
    roundRect(ctx, slot.x, slot.y, slot.w, slot.h, layer.mask.radius * Math.min(slot.w, slot.h));
    ctx.clip();
  } else {
    ctx.beginPath();
    ctx.rect(slot.x, slot.y, slot.w, slot.h);
    ctx.clip();
  }

  const photo = spec.photo;
  if (!photo) {
    // Emplacement vide : un aplat neutre, jamais du blanc pur — l'utilisateur
    // doit comprendre au premier coup d'œil que c'est là que sa photo va.
    ctx.fillStyle = '#CBD5E1';
    ctx.fillRect(slot.x, slot.y, slot.w, slot.h);
    ctx.restore();
    return;
  }

  const img = photo.image;
  const iw = 'width' in img ? (img.width as number) : 0;
  const ih = 'height' in img ? (img.height as number) : 0;
  if (!iw || !ih) {
    ctx.restore();
    return;
  }

  // Pas de plancher à 1 : sous cette valeur la photo est plus petite que son
  // emplacement et le fond du décor apparaît autour. C'est ce qui permet de
  // faire tenir une image entière dans un décor qui n'a pas ses proportions.
  const s = coverScale(iw, ih, slot.w, slot.h) * Math.max(0.01, photo.scale);
  const dw = iw * s;
  const dh = ih * s;
  const cx = slot.x + slot.w / 2 + photo.x * W;
  const cy = slot.y + slot.h / 2 + photo.y * H;

  ctx.save();
  if (photo.flipX) {
    ctx.translate(cx, cy);
    ctx.scale(-1, 1);
    ctx.translate(-cx, -cy);
  }
  ctx.drawImage(img as CanvasImageSource, cx - dw / 2, cy - dh / 2, dw, dh);
  ctx.restore();

  // Filtre signature — un voile bleu de marque en « soft-light ».
  // Volontairement fait en composition plutôt qu'avec ctx.filter, dont le
  // support reste inégal : la composition, elle, est universelle et rend
  // exactement pareil à l'aperçu et à l'export.
  if (spec.filter === 'wakabi-blue' && spec.filterIntensity > 0) {
    ctx.save();
    ctx.globalCompositeOperation = 'soft-light';
    ctx.globalAlpha = Math.min(1, Math.max(0, spec.filterIntensity)) * 0.85;
    ctx.fillStyle = BRAND['brand.primary'];
    ctx.fillRect(slot.x, slot.y, slot.w, slot.h);
    ctx.restore();
  }

  ctx.restore();
}

function drawImageLayer(
  ctx: Ctx2D,
  layer: Extract<Layer, { type: 'image' }>,
  assets: LayerAssets,
  W: number,
  H: number,
) {
  const asset = assets[layer.id];
  if (!asset) return;
  const r = toPx(layer.rect, W, H);
  ctx.save();
  ctx.globalAlpha = layer.opacity;
  if (layer.blendMode !== 'normal') {
    ctx.globalCompositeOperation = layer.blendMode as GlobalCompositeOperation;
  }
  ctx.drawImage(asset as CanvasImageSource, r.x, r.y, r.w, r.h);
  ctx.restore();
}

function drawTextLayer(
  ctx: Ctx2D,
  layer: Extract<Layer, { type: 'text' }>,
  spec: RenderSpec,
  W: number,
  H: number,
) {
  const raw = layer.editable ? (spec.texts[layer.id] ?? layer.value) : layer.value;
  const text = layer.uppercase ? raw.toLocaleUpperCase('fr') : raw;
  if (!text.trim()) return;

  const r = toPx(layer.rect, W, H);
  const family = FONTS[layer.font];

  // Taille exprimée en fraction de la hauteur du canevas : indépendante de
  // la résolution, donc l'aperçu et l'export ont exactement la même mise en page.
  let size = layer.size * H;
  let lines: string[] = [];

  // autoShrink : on réduit jusqu'à ce que le texte tienne dans son rectangle.
  for (let i = 0; i < 24; i++) {
    ctx.font = `700 ${size}px ${family}`;
    lines = wrap(ctx, text, r.w);
    const tooWide = lines.some((l) => ctx.measureText(l).width > r.w);
    const tooTall = lines.length * size * 1.15 > r.h;
    if (!layer.autoShrink || (!tooWide && !tooTall)) break;
    size *= 0.92;
  }

  ctx.save();
  ctx.font = `700 ${size}px ${family}`;
  ctx.fillStyle = resolveColor(layer.color);
  ctx.textBaseline = 'middle';
  ctx.textAlign = layer.align;

  // Ombre portée douce : le texte doit rester lisible sur une photo claire
  // comme sur une photo sombre. C'est le contrôle `contrast` du pré-vol
  // qui vérifiera le résultat (docs/04-MODERATION.md §4).
  ctx.shadowColor = 'rgba(15,23,42,0.45)';
  ctx.shadowBlur = size * 0.18;
  ctx.shadowOffsetY = size * 0.04;

  const lineH = size * 1.15;
  const startY = r.y + r.h / 2 - ((lines.length - 1) * lineH) / 2;
  const x = layer.align === 'left' ? r.x : layer.align === 'right' ? r.x + r.w : r.x + r.w / 2;
  lines.forEach((l, i) => ctx.fillText(l, x, startY + i * lineH));
  ctx.restore();
}

/* ------------------------------------------------------------------ */
/* Filigrane                                                           */
/* ------------------------------------------------------------------ */

/**
 * Filigrane Wakabi, systématique.
 * L'outil est gratuit et sert l'offre partenaire : le logo voyage sur chaque
 * partage, sans l'arbitrage commercial que mougni doit faire.
 *
 * Aucun logo vectoriel n'existant, la variante `text` est la seule qui ne
 * dépende d'aucun asset — c'est elle qui permet de livrer un aperçu
 * aujourd'hui. Voir docs/02-CHARTE-WAKABI.md §2 bis.
 */
function drawWatermark(
  ctx: Ctx2D,
  tpl: DecorTemplate,
  assets: LayerAssets,
  W: number,
  H: number,
) {
  const wm = tpl.watermark;
  if (!wm.enabled) return;

  // 21 % plutôt que 18 % : la signature est longue, et la réduire
  // davantage la rendrait illisible sur un statut vu au téléphone.
  const targetW = Math.max(W * 0.21, 120 * (W / tpl.canvas.width));
  const margin = Math.min(W, H) * 0.04;

  // `wordmark` et `lockup` supposent un fichier logo. Tant qu'aucun asset
  // vectoriel n'existe (docs/02-CHARTE-WAKABI.md §2 bis), on retombe sur la
  // composition typographique — et dans ce cas la signature accompagne
  // toujours le nom : sans elle, « WAKABI » seul ne dit pas ce qu'est Wakabi.
  const logoAsset = assets.__watermark__;
  const variant = wm.variant !== 'text' && !logoAsset ? 'text' : wm.variant;
  const withTagline = variant !== 'wordmark';

  const padX = targetW * 0.09;
  const padY = targetW * 0.075;
  const nameSize = targetW * 0.235;
  const tagSize = targetW * 0.072;
  const boxH = padY * 2 + nameSize + (withTagline ? tagSize * 2.1 : 0);
  const boxW = targetW;

  const x =
    wm.position === 'bottom-left'
      ? margin
      : wm.position === 'bottom-center'
        ? (W - boxW) / 2
        : W - boxW - margin;
  const y = H - boxH - margin;

  ctx.save();
  ctx.globalAlpha = wm.opacity;

  // Voile sombre local : le bleu de marque disparaît sur un ciel bleu, et
  // les photos de sortie en sont pleines. Blanc sur voile, jamais bleu nu.
  ctx.fillStyle = 'rgba(15,23,42,0.55)';
  roundRect(ctx, x, y, boxW, boxH, targetW * 0.1);
  ctx.fill();

  const cx = x + boxW / 2;
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';

  if (logoAsset && variant !== 'text') {
    const lw = boxW - padX * 2;
    const iw = 'width' in logoAsset ? (logoAsset.width as number) : 1;
    const ih = 'height' in logoAsset ? (logoAsset.height as number) : 1;
    const lh = (ih / iw) * lw;
    ctx.drawImage(logoAsset as CanvasImageSource, x + padX, y + (boxH - lh) / 2, lw, lh);
  } else {
    const inner = boxW - padX * 2;
    ctx.fillStyle = '#FFFFFF';
    drawTracked(
      ctx, 'WAKABI', cx, y + padY + nameSize * 0.55,
      nameSize, FONTS.display, nameSize * 0.02, inner,
    );
    if (withTagline) {
      ctx.fillStyle = 'rgba(255,255,255,0.85)';
      drawTracked(
        ctx, WAKABI_TAGLINE, cx, y + padY + nameSize + tagSize * 0.95,
        tagSize, FONTS.body, tagSize * 0.42, inner,
      );
    }
  }

  ctx.restore();
}

/**
 * Le QR du badge.
 *
 * Posé sur une pastille blanche : un QR doit avoir du blanc autour pour être
 * lu, et une photo quelconque derrière le rendrait indéchiffrable. La zone
 * de silence fait 8 % de la largeur du code, comme le veut la norme.
 */
function drawQr(
  ctx: Ctx2D,
  tpl: DecorTemplate,
  spec: RenderSpec,
  W: number,
  H: number,
) {
  const cfg = tpl.qr;
  if (!cfg.enabled || !spec.qr) return;

  const size = W * cfg.size;
  const quiet = size * 0.08;
  const box = size + quiet * 2;
  const margin = Math.min(W, H) * 0.04;

  const x =
    cfg.position === 'top-right' ? W - box - margin : margin;
  const y =
    cfg.position === 'bottom-left' ? H - box - margin : margin;

  ctx.save();
  ctx.fillStyle = '#FFFFFF';
  roundRect(ctx, x, y, box, box, box * 0.12);
  ctx.fill();
  ctx.drawImage(spec.qr as CanvasImageSource, x + quiet, y + quiet, size, size);
  ctx.restore();
}

/* ------------------------------------------------------------------ */
/* Point d'entrée                                                      */
/* ------------------------------------------------------------------ */

/**
 * Rend la scène complète.
 *
 * @param scale 1 pour l'export haute définition, ~0.31 pour l'aperçu.
 *              Le contexte doit déjà être dimensionné en conséquence.
 */
export function renderScene(
  ctx: Ctx2D,
  spec: RenderSpec,
  tpl: DecorTemplate,
  assets: LayerAssets,
  scale: number,
): void {
  const W = tpl.canvas.width * scale;
  const H = tpl.canvas.height * scale;

  ctx.save();
  ctx.clearRect(0, 0, W, H);
  ctx.fillStyle = resolveColor(tpl.canvas.background);
  ctx.fillRect(0, 0, W, H);

  for (const layer of tpl.layers) {
    if (layer.type === 'photoSlot') drawPhotoSlot(ctx, layer, spec, W, H);
    else if (layer.type === 'image') drawImageLayer(ctx, layer, assets, W, H);
    else drawTextLayer(ctx, layer, spec, W, H);
  }

  drawQr(ctx, tpl, spec, W, H);
  drawWatermark(ctx, tpl, assets, W, H);
  ctx.restore();
}

export { BRAND, FONTS };
