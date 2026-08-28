/**
 * Le Studio, sans cadriciel.
 *
 * Ce fichier est le SEUL code écrit pour la version PHP : tout le reste vient
 * de src/core, importé tel quel. Le renderer, le pipeline photo, le garde-fou
 * anti-vide et le partage sont donc rigoureusement les mêmes que dans la
 * version Next.js — un décor rendu ici et là-bas donne le même fichier.
 *
 * La photo ne quitte jamais l'appareil : elle est décodée, recadrée et
 * exportée dans le navigateur. Le serveur ne reçoit que le jeton du badge.
 */

import { renderScene } from '@/core/renderScene';
import { decodePhoto, loadImage } from '@/core/imagePipeline';
import { clampPhoto, containScale } from '@/core/fitPhoto';
import { canShareFile, chooseRoute, shareFile, slugifyFilename, triggerDownload } from '@/lib/share';
import type { LayerAssets, LoadedImage, PhotoState, RenderSpec } from '@/core/types';
import type { Rect } from '@/core/fitPhoto';

interface Contexte {
  gabarit: any;
  decorId: string;
  slug: string;
  cadreUrl: string | null;
  base: string;
  connecte: boolean;
}

const $ = <T extends HTMLElement>(s: string) => document.querySelector(s) as T;

function demarrer(ctx: Contexte) {
  const canvas = $('#toile') as HTMLCanvasElement;
  const ctx2d = canvas.getContext('2d')!;
  const tpl = ctx.gabarit;

  const assets: LayerAssets = {};
  let spec: RenderSpec = {
    templateId: tpl.id,
    photo: null,
    filter: 'none',
    filterIntensity: 0.35,
    texts: {},
    qr: null,
  };
  let jeton: string | null = null;

  // Champs éditables décrits par le gabarit — l'éditeur n'invente rien.
  const champs = (tpl.layers ?? []).filter((l: any) => l.type === 'text' && l.editable);
  for (const c of champs) spec.texts[c.id] = c.value ?? '';

  const couchePhoto = (tpl.layers ?? []).find((l: any) => l.type === 'photoSlot');
  const emplacement: Rect = couchePhoto?.rect ?? { x: 0, y: 0, w: 1, h: 1 };

  /**
   * Les bornes du zoom viennent du gabarit.
   *
   * Elles étaient écrites en dur (0,5 à 4) à trois endroits, dont un curseur
   * dont la moitié gauche ne faisait rien : le cadrage refusait alors tout
   * ce qui passait sous 1.
   */
  const bornes = {
    min: Number(couchePhoto?.minScale ?? 0.2),
    max: Number(couchePhoto?.maxScale ?? 4),
  };

  /** La photo reste dans son emplacement, plus petite ou plus grande que lui. */
  function recadrer(p: PhotoState): PhotoState {
    return clampPhoto(
      p, emplacement,
      (p.image as HTMLImageElement).width || 1,
      (p.image as HTMLImageElement).height || 1,
      tpl.canvas.width, tpl.canvas.height,
      bornes,
    );
  }

  /** L'échelle qui fait tenir l'image entière dans l'emplacement. */
  function echelleAjustee(image: HTMLImageElement): number {
    return containScale(
      image.width || 1, image.height || 1,
      emplacement.w * tpl.canvas.width, emplacement.h * tpl.canvas.height,
    );
  }

  /* ---------------- rendu ---------------- */

  const COTE = 640;
  function dessiner() {
    const ratio = tpl.canvas.width / tpl.canvas.height;
    const w = ratio >= 1 ? COTE : Math.round(COTE * ratio);
    const h = ratio >= 1 ? Math.round(COTE / ratio) : COTE;
    const dpr = Math.min(2, window.devicePixelRatio || 1);

    canvas.width = w * dpr;
    canvas.height = h * dpr;
    canvas.style.width = w + 'px';
    canvas.style.height = h + 'px';

    // Une seule fonction de rendu, deux échelles : c'est ce qui rend
    // l'aperçu structurellement fidèle à l'export.
    const echelle = (w * dpr) / tpl.canvas.width;
    renderScene(ctx2d, spec, tpl, assets, echelle);
  }

  /* ---------------- chargement du cadre ---------------- */

  async function chargerCadre() {
    const couche = (tpl.layers ?? []).find((l: any) => l.type === 'image');
    if (!couche) return;
    try {
      assets[couche.id] = await loadImage(ctx.cadreUrl ?? couche.src);
    } catch {
      // Un cadre manquant ne doit pas empêcher d'écrire son prénom.
      console.warn('cadre introuvable :', couche.src);
    }
    dessiner();
  }

  /* ---------------- photo ---------------- */

  const entree = $('#photo') as HTMLInputElement;

  entree.addEventListener('change', async () => {
    const f = entree.files?.[0];
    if (!f) return;
    $('#etat').textContent = 'Chargement…';
    try {
      const { image } = await decodePhoto(f);
      spec.photo = { image: image as LoadedImage, x: 0, y: 0, scale: 1, flipX: false };
      spec.photo = recadrer(spec.photo);
      $('#etat').textContent = '';
      $('#outils').hidden = false;
      ($('#zoom') as HTMLInputElement).value = String(spec.photo.scale);
      afficherZoom();
      const etiquette = document.querySelector('.fichier .texte');
      if (etiquette) etiquette.textContent = 'Changer la photo';

      // Le jeton est émis MAINTENANT, pas au téléchargement : sinon le QR
      // n'apparaîtrait qu'à l'export et l'aperçu mentirait.
      if (!jeton) await emettreBadge();
      dessiner();
    } catch (e) {
      $('#etat').textContent = 'Cette image n’a pas pu être lue. Essayez-en une autre.';
    }
  });

  async function emettreBadge() {
    try {
      const r = await fetch(ctx.base + '?p=api-badge', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ decor: ctx.decorId }),
      });
      const d = await r.json();
      if (!d.jeton) return;
      jeton = d.jeton;
      spec.qr = await loadImage(d.qr);
      const el = document.getElementById('jeton');
      if (el) {
        el.textContent = d.jeton;
        (el.closest('[data-jeton]') as HTMLElement | null)?.removeAttribute('hidden');
      }
    } catch {
      // Sans réseau, le badge se crée quand même — sans QR.
    }
  }

  /* ---------------- recadrage : glisser, pincer ----------------
     Un doigt déplace la photo, deux doigts la zooment. Sans le pincement,
     le seul zoom du téléphone était le curseur, situé sous la toile : on
     réglait à l'aveugle ce qu'on ne voyait plus. */

  const doigts = new Map<number, { x: number; y: number }>();
  let attrape: { x: number; y: number; px: number; py: number } | null = null;
  let pince: { ecart: number; echelle: number } | null = null;

  const ecartement = () => {
    const [a, b] = [...doigts.values()];
    return Math.hypot(a.x - b.x, a.y - b.y);
  };

  const saisir = (e: PointerEvent) => {
    if (!spec.photo) return;
    attrape = { x: e.clientX, y: e.clientY, px: spec.photo.x, py: spec.photo.y };
  };

  canvas.addEventListener('pointerdown', (e) => {
    if (!spec.photo) return;
    canvas.setPointerCapture(e.pointerId);
    doigts.set(e.pointerId, { x: e.clientX, y: e.clientY });
    if (doigts.size >= 2) {
      attrape = null;
      pince = { ecart: ecartement(), echelle: spec.photo.scale };
    } else {
      saisir(e);
    }
  });

  canvas.addEventListener('pointermove', (e) => {
    if (!spec.photo || !doigts.has(e.pointerId)) return;
    doigts.set(e.pointerId, { x: e.clientX, y: e.clientY });

    if (pince && doigts.size >= 2) {
      const ecart = ecartement();
      if (pince.ecart > 0) zoomer(pince.echelle * (ecart / pince.ecart));
      return;
    }
    if (!attrape) return;
    const r = canvas.getBoundingClientRect();
    spec.photo.x = attrape.px + (e.clientX - attrape.x) / r.width;
    spec.photo.y = attrape.py + (e.clientY - attrape.y) / r.height;
    spec.photo = recadrer(spec.photo);
    dessiner();
  });

  const lacher = (e: PointerEvent) => {
    doigts.delete(e.pointerId);
    if (doigts.size < 2) pince = null;
    // Le doigt qui reste reprend le glissement là où il est : sans ce
    // réamorçage, la photo saute au relâchement du second doigt.
    const reste = [...doigts.values()][0];
    attrape = reste && spec.photo
      ? { x: reste.x, y: reste.y, px: spec.photo.x, py: spec.photo.y }
      : null;
  };
  canvas.addEventListener('pointerup', lacher);
  canvas.addEventListener('pointercancel', lacher);

  canvas.addEventListener('wheel', (e) => {
    if (!spec.photo) return;
    e.preventDefault();
    zoomer(spec.photo.scale * (e.deltaY < 0 ? 1.06 : 0.94));
  }, { passive: false });

  function zoomer(valeur: number) {
    if (!spec.photo) return;
    spec.photo.scale = Math.min(bornes.max, Math.max(bornes.min, valeur));
    spec.photo = recadrer(spec.photo);
    ($('#zoom') as HTMLInputElement).value = String(spec.photo.scale);
    afficherZoom();
    dessiner();
  }

  /** Le facteur en clair : « 0,42 » ne dit rien, « 42 % » se lit. */
  function afficherZoom() {
    const sortie = document.getElementById('zoom-valeur');
    if (sortie && spec.photo) {
      sortie.textContent = Math.round(spec.photo.scale * 100) + ' %';
    }
  }

  $('#zoom').addEventListener('input', (e) => zoomer(Number((e.target as HTMLInputElement).value)));

  $('#miroir').addEventListener('click', () => {
    if (!spec.photo) return;
    spec.photo.flipX = !spec.photo.flipX;
    dessiner();
  });

  // « Remplir » : le cadrage d'origine, la photo couvre tout l'emplacement.
  $('#recentrer').addEventListener('click', () => {
    if (!spec.photo) return;
    spec.photo.x = 0;
    spec.photo.y = 0;
    spec.photo.scale = 1;
    spec.photo = recadrer(spec.photo);
    ($('#zoom') as HTMLInputElement).value = '1';
    afficherZoom();
    dessiner();
  });

  /**
   * « Tout afficher » : l'image entière tient dans l'emplacement.
   *
   * C'est la réponse en un geste à « ma photo ne rentre pas » — une affiche
   * panoramique, une capture d'écran, un visuel carré dans un décor
   * vertical. Le fond du décor apparaît autour, et c'est voulu.
   */
  document.getElementById('ajuster')?.addEventListener('click', () => {
    if (!spec.photo) return;
    spec.photo.x = 0;
    spec.photo.y = 0;
    zoomer(echelleAjustee(spec.photo.image as HTMLImageElement));
  });

  /* ---------------- teinte ---------------- */

  const teinte = document.getElementById('teinte') as HTMLInputElement | null;
  teinte?.addEventListener('input', () => {
    const v = Number(teinte.value);
    spec.filter = v > 0 ? 'wakabi-blue' : 'none';
    spec.filterIntensity = v;
    dessiner();
  });

  /* ---------------- textes ---------------- */

  for (const c of champs) {
    const input = document.getElementById('champ-' + c.id) as HTMLInputElement | null;
    input?.addEventListener('input', () => {
      spec.texts[c.id] = input.value;
      const compteur = document.getElementById('compte-' + c.id);
      if (compteur) compteur.textContent = `${input.value.length}/${c.maxLength ?? 42}`;
      dessiner();
    });
  }

  /* ---------------- export ---------------- */

  $('#telecharger').addEventListener('click', async () => {
    const bouton = $('#telecharger') as HTMLButtonElement;
    bouton.disabled = true;
    bouton.textContent = 'Préparation…';

    try {
      const max = tpl.export?.maxPx ?? 2048;
      const ratio = tpl.canvas.width / tpl.canvas.height;
      const w = ratio >= 1 ? max : Math.round(max * ratio);
      const h = ratio >= 1 ? Math.round(max / ratio) : max;

      const hors = document.createElement('canvas');
      hors.width = w;
      hors.height = h;
      // Exactement la même fonction, à une autre échelle.
      renderScene(hors.getContext('2d')!, spec, tpl, assets, w / tpl.canvas.width);

      // Le format vient du gabarit, il n'est pas décidé ici. Le JPEG par
      // défaut n'est pas un détail : le même badge pèse 921 Ko en PNG contre
      // environ 200 Ko en JPEG, et la contrainte est un forfait data cher.
      const type: string = tpl.export?.mimeType ?? 'image/jpeg';
      const blob: Blob = await new Promise((res) =>
        hors.toBlob((b) => res(b!), type, tpl.export?.quality ?? 0.92),
      );
      const nom = slugifyFilename(ctx.slug, type === 'image/png' ? 'png' : 'jpg');

      fetch(ctx.base + '?p=api-telechargement', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ decor: ctx.decorId }),
      }).catch(() => {});

      // Le bouton dit « Télécharger » : il télécharge. Le partage est un
      // second bouton, proposé seulement s'il marche réellement.
      if (chooseRoute() === 'long-press') {
        // Navigateur intégré (WhatsApp, Instagram) : `<a download>` est
        // inerte. Un téléchargement y échouerait sans rien dire.
        const img = $('#apercu-partage') as HTMLImageElement;
        img.src = URL.createObjectURL(blob);
        $('#bloc-partage').hidden = false;
      } else {
        triggerDownload(blob, nom);
      }

      // Le partage, offert et non imposé.
      const partage = document.getElementById('partager') as HTMLButtonElement | null;
      if (partage && canShareFile(blob, nom)) {
        partage.hidden = false;
        partage.onclick = () => shareFile(blob, nom, tpl.share?.defaultCaption ?? '');
      }

      const suite = $('#apres') as HTMLElement;
      if (suite) suite.hidden = false;
    } finally {
      bouton.disabled = false;
      bouton.textContent = 'Télécharger mon badge';
    }
  });

  chargerCadre();
  dessiner();
}

declare global {
  interface Window { WAKABI: Contexte }
}

if (window.WAKABI) demarrer(window.WAKABI);
