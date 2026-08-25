/**
 * Démonstration autonome — un seul fichier HTML, aucune dépendance réseau.
 *
 * Elle importe EXACTEMENT le même cœur que l'application Next.js :
 * renderScene, imagePipeline, fitPhoto, le schéma Zod. C'est la
 * démonstration que la fonction de rendu est bien pure — elle tourne ici
 * sans React, sans Next, sans DOM autre qu'un canvas.
 */

import { TEMPLATES } from '@/core/templates';
import type { DecorTemplate } from '@/core/template.schema';
import { renderScene } from '@/core/renderScene';
import { decodePhoto, loadImage } from '@/core/imagePipeline';
import { clampPhoto } from '@/core/fitPhoto';
import type { LayerAssets, PhotoState, RenderSpec } from '@/core/types';
import { chooseRoute, shareFile, slugifyFilename, triggerDownload } from '@/lib/share';
import { FRAMES } from './frames.generated';

/* ---------------------------------------------------------------- */
/* Enregistrement dans le visualiseur d'Artifacts                    */
/* ---------------------------------------------------------------- */
/**
 * Dans le visualiseur claude.ai, `<a download>` est inerte : la page doit
 * passer par la capacité `downloads`, qui demande confirmation au lecteur.
 * Partout ailleurs (fichier local, application Next.js) cette capacité
 * n'existe pas et on retombe sur l'arbre de décision normal de share.ts.
 */
interface SaveNs { save(r: { filename: string; data: Blob }): Promise<unknown> }
let savePromise: Promise<SaveNs | null> | null = null;

function downloadsNamespace(): Promise<SaveNs | null> {
  if (savePromise) return savePromise;
  const g = (window as unknown as { claude?: { use?: (n: string) => Promise<unknown> } }).claude;
  savePromise = g?.use
    ? g.use('downloads').then((n) => (n as SaveNs) ?? null).catch(() => null)
    : Promise.resolve(null);
  return savePromise;
}

const $ = <T extends HTMLElement>(sel: string) => document.querySelector(sel) as T;

let tpl: DecorTemplate = TEMPLATES[0];
let spec: RenderSpec;
let assets: LayerAssets = {};
let dirty = true;

function freshSpec(t: DecorTemplate): RenderSpec {
  const texts: Record<string, string> = {};
  for (const l of t.layers) if (l.type === 'text' && l.editable) texts[l.id] = l.value;
  return { templateId: t.id, photo: null, filter: 'wakabi-blue', filterIntensity: 0, texts };
}

function clamp() {
  const slot = tpl.layers.find((l) => l.type === 'photoSlot');
  if (!spec.photo || !slot || slot.type !== 'photoSlot') return;
  const img = spec.photo.image as { width: number; height: number };
  spec.photo = clampPhoto(spec.photo, slot.rect, img.width, img.height, tpl.canvas.width, tpl.canvas.height);
}

/* ---------------- rendu de l'aperçu ---------------- */

function draw() {
  const canvas = $<HTMLCanvasElement>('#stage');
  const box = canvas.parentElement!;
  const cssW = box.clientWidth;
  if (!cssW) return;
  const dpr = Math.min(window.devicePixelRatio || 1, 2);
  const scale = (cssW * dpr) / tpl.canvas.width;
  const w = Math.round(tpl.canvas.width * scale);
  const h = Math.round(tpl.canvas.height * scale);
  if (canvas.width !== w || canvas.height !== h) { canvas.width = w; canvas.height = h; }
  const ctx = canvas.getContext('2d');
  if (ctx) renderScene(ctx, spec, tpl, assets, scale);
}

function loop() {
  if (dirty) { draw(); dirty = false; }
  requestAnimationFrame(loop);
}
const invalidate = () => { dirty = true; };

/* ---------------- chargement d'un modèle ---------------- */

async function selectTemplate(t: DecorTemplate) {
  const previousPhoto = spec?.photo ?? null;
  tpl = t;
  spec = freshSpec(t);
  spec.photo = previousPhoto;

  assets = {};
  for (const l of t.layers) {
    if (l.type !== 'image') continue;
    const name = l.src.split('/').pop() ?? '';
    const data = FRAMES[name];
    if (data) assets[l.id] = await loadImage(data);
  }
  clamp();
  buildControls();
  syncChips();
  invalidate();
}

function syncChips() {
  document.querySelectorAll<HTMLButtonElement>('[data-slug]').forEach((b) => {
    const on = b.dataset.slug === tpl.slug;
    b.className = on ? 'chip chip-on' : 'chip';
  });
  $<HTMLElement>('#tplTitle').textContent = tpl.title;
  $<HTMLElement>('#tplSub').textContent = tpl.subtitle ?? '';
  $<HTMLElement>('#stageBox').style.aspectRatio = `${tpl.canvas.width} / ${tpl.canvas.height}`;
}

/* ---------------- champs de texte ---------------- */

function buildControls() {
  const wrap = $<HTMLElement>('#texts');
  wrap.innerHTML = '';
  for (const l of tpl.layers) {
    if (l.type !== 'text' || !l.editable) continue;
    const field = document.createElement('label');
    field.className = 'field';
    field.innerHTML = `<span>${l.placeholder || l.id}</span>`;
    const input = document.createElement('input');
    input.type = 'text';
    input.maxLength = l.maxLength;
    input.value = spec.texts[l.id] ?? '';
    input.placeholder = l.placeholder;
    input.oninput = () => { spec.texts[l.id] = input.value; invalidate(); };
    field.appendChild(input);
    wrap.appendChild(field);
  }
}

/* ---------------- gestes ---------------- */

function wireGestures() {
  const box = $<HTMLElement>('#stageBox');
  const pointers = new Map<number, { x: number; y: number }>();
  let pinch = 0;

  box.addEventListener('pointerdown', (e) => {
    if (!spec.photo) return;
    box.setPointerCapture(e.pointerId);
    pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
    if (pointers.size === 2) {
      const [a, b] = [...pointers.values()];
      pinch = Math.hypot(a.x - b.x, a.y - b.y);
    }
  });
  box.addEventListener('pointermove', (e) => {
    const prev = pointers.get(e.pointerId);
    if (!prev || !spec.photo) return;
    pointers.set(e.pointerId, { x: e.clientX, y: e.clientY });
    if (pointers.size === 2) {
      const [a, b] = [...pointers.values()];
      const d = Math.hypot(a.x - b.x, a.y - b.y);
      if (pinch > 0) setZoom(spec.photo.scale * (d / pinch));
      pinch = d;
      return;
    }
    const cssW = box.clientWidth;
    spec.photo.x += (e.clientX - prev.x) / cssW;
    spec.photo.y += (e.clientY - prev.y) / cssW;
    clamp();
    invalidate();
  });
  const end = (e: PointerEvent) => { pointers.delete(e.pointerId); if (pointers.size < 2) pinch = 0; };
  box.addEventListener('pointerup', end);
  box.addEventListener('pointercancel', end);
  box.addEventListener('wheel', (e) => {
    if (!spec.photo) return;
    e.preventDefault();
    setZoom(spec.photo.scale * (e.deltaY < 0 ? 1.06 : 1 / 1.06));
  }, { passive: false });
}

function setZoom(v: number) {
  if (!spec.photo) return;
  const slot = tpl.layers.find((l) => l.type === 'photoSlot');
  const max = slot && slot.type === 'photoSlot' ? slot.maxScale : 4;
  spec.photo.scale = Math.min(max, Math.max(1, v));
  clamp();
  $<HTMLInputElement>('#zoom').value = String(spec.photo.scale);
  $<HTMLElement>('#zoomVal').textContent = '×' + spec.photo.scale.toFixed(2);
  invalidate();
}

/* ---------------- export ---------------- */

/** Affiche la redirection après téléchargement — la brique d'ancrage. */
function showAfter() {
  $<HTMLElement>('#after').hidden = false;
  const link = $<HTMLAnchorElement>('#redirect');
  link.href = tpl.share.redirectUrl ?? 'https://wakabileguide.com/';
  link.textContent = (tpl.share.redirectLabel ?? 'Découvrir sur Wakabi') + ' →';
}

async function exportImage() {
  const btn = $<HTMLButtonElement>('#dl');
  btn.disabled = true;
  const label = btn.textContent;
  btn.textContent = 'Génération…';
  try {
    const longest = Math.max(tpl.canvas.width, tpl.canvas.height);
    const scale = tpl.export.maxPx / longest;
    const c = document.createElement('canvas');
    c.width = Math.round(tpl.canvas.width * scale);
    c.height = Math.round(tpl.canvas.height * scale);
    renderScene(c.getContext('2d')!, spec, tpl, assets, scale);
    const blob: Blob = await new Promise((res, rej) =>
      c.toBlob((b) => (b ? res(b) : rej(new Error('échec'))), tpl.export.mimeType, tpl.export.quality),
    );
    const name = slugifyFilename(tpl.slug, tpl.export.mimeType === 'image/png' ? 'png' : 'jpg');
    const caption = [tpl.share.defaultCaption, ...tpl.share.hashtags.map((h) => '#' + h)].join(' ');
    const saver = await downloadsNamespace();
    if (saver) {
      try {
        await saver.save({ filename: name, data: blob });
      } catch (err) {
        // « declined » : le lecteur a refusé, ce n'est pas une erreur.
        const code = (err as { code?: string } | null)?.code;
        if (code !== 'declined') triggerDownload(blob, name);
      }
      showAfter();
      return;
    }

    const route = chooseRoute(blob, name);
    if (route === 'share') { if (!(await shareFile(blob, name, caption))) triggerDownload(blob, name); }
    else if (route === 'download') triggerDownload(blob, name);
    else {
      const img = $<HTMLImageElement>('#longpress');
      img.src = URL.createObjectURL(blob);
      $<HTMLElement>('#longpressBox').hidden = false;
    }
    showAfter();
  } finally {
    btn.disabled = false;
    btn.textContent = label;
  }
}

/* ---------------- démarrage ---------------- */

export async function start() {
  spec = freshSpec(TEMPLATES[0]);

  const picker = $<HTMLElement>('#picker');
  for (const t of TEMPLATES) {
    const b = document.createElement('button');
    b.dataset.slug = t.slug;
    b.textContent = t.title;
    b.onclick = () => selectTemplate(t);
    picker.appendChild(b);
  }

  $<HTMLInputElement>('#file').onchange = async (e) => {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    const status = $<HTMLElement>('#status');
    status.textContent = 'Ouverture…';
    try {
      const d = await decodePhoto(file);
      const photo: PhotoState = { image: d.image, x: 0, y: 0, scale: 1, flipX: false };
      spec.photo = photo;
      clamp();
      status.textContent = 'Glisse pour déplacer · pince ou molette pour zoomer';
      $<HTMLElement>('#tools').hidden = false;
      $<HTMLButtonElement>('#dl').disabled = false;
      invalidate();
    } catch (err) {
      status.textContent = err instanceof Error ? err.message : 'Image illisible.';
    }
  };

  $<HTMLInputElement>('#zoom').oninput = (e) => setZoom(Number((e.target as HTMLInputElement).value));
  $<HTMLInputElement>('#tint').oninput = (e) => {
    spec.filterIntensity = Number((e.target as HTMLInputElement).value);
    $<HTMLElement>('#tintVal').textContent = Math.round(spec.filterIntensity * 100) + ' %';
    invalidate();
  };
  $<HTMLButtonElement>('#recenter').onclick = () => { if (spec.photo) { spec.photo.x = 0; spec.photo.y = 0; setZoom(1); } };
  $<HTMLButtonElement>('#flip').onclick = () => { if (spec.photo) { spec.photo.flipX = !spec.photo.flipX; invalidate(); } };
  $<HTMLButtonElement>('#dl').onclick = exportImage;

  wireGestures();
  void downloadsNamespace();
  new ResizeObserver(invalidate).observe($<HTMLElement>('#stageBox'));

  if (document.fonts) {
    await Promise.all([
      document.fonts.load("800 64px 'Bricolage Grotesque'"),
      document.fonts.load("700 64px 'Bricolage Grotesque'"),
      document.fonts.load("600 32px 'Plus Jakarta Sans'"),
    ]).catch(() => undefined);
  }

  await selectTemplate(TEMPLATES[0]);
  loop();
}
