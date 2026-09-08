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
import { attendrePolices } from '@/core/polices';
import { decodePhoto, loadImage } from '@/core/imagePipeline';
import { clampPhoto, containScale } from '@/core/fitPhoto';
import { canShareFile, chooseRoute, shareFile, slugifyFilename, triggerDownload } from '@/lib/share';
import type { LayerAssets, LoadedImage, PhotoState, RenderSpec } from '@/core/types';
import type { Rect } from '@/core/fitPhoto';

interface Contexte {
  gabarit: any;
  /** Les gabarits de chaque format, indexés par rapport (« 1:1 », « 9:16 »). */
  gabarits?: Record<string, any>;
  /** Le format affiché au chargement. */
  format?: string;
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
  let tpl = ctx.gabarit;

  // Le format qu'on regarde. Il change quand on clique une pastille.
  let ratio = String(ctx.format || ctx.gabarit?.canvas?.ratio || '');

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
  // Vrai dès que l'offre de l'organisateur ferme le robinet pour le mois.
  let bloque = false;

  // Champs éditables décrits par le gabarit — l'éditeur n'invente rien.
  const champs = (tpl.layers ?? []).filter((l: any) => l.type === 'text' && l.editable);
  for (const c of champs) spec.texts[c.id] = c.value ?? '';

  let couchePhoto = (tpl.layers ?? []).find((l: any) => l.type === 'photoSlot');
  let emplacement: Rect = couchePhoto?.rect ?? { x: 0, y: 0, w: 1, h: 1 };

  /**
   * Les bornes du zoom viennent du gabarit.
   *
   * Elles étaient écrites en dur (0,5 à 4) à trois endroits, dont un curseur
   * dont la moitié gauche ne faisait rien : le cadrage refusait alors tout
   * ce qui passait sous 1.
   */
  let bornes = {
    min: Number(couchePhoto?.minScale ?? 0.2),
    max: Number(couchePhoto?.maxScale ?? 4),
  };

  /**
   * Adopter un gabarit : c'est tout ce qui change d'un format à l'autre.
   *
   * La toile, le cadre et la fenêtre de la photo — rien d'autre. Les textes
   * sont en fractions du canevas et se replacent seuls, et c'est
   * précisément ce qui permet de décliner un décor sans le refaire.
   */
  function adopter(g: any): void {
    tpl = g;
    couchePhoto = (tpl.layers ?? []).find((l: any) => l.type === 'photoSlot');
    emplacement = couchePhoto?.rect ?? { x: 0, y: 0, w: 1, h: 1 };
    bornes = {
      min: Number(couchePhoto?.minScale ?? 0.2),
      max: Number(couchePhoto?.maxScale ?? 4),
    };
  }

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

  /**
   * La place où la toile doit tenir — largeur ET hauteur.
   *
   * Sur téléphone, la toile est collée en haut pendant qu'on règle : sa
   * hauteur est donc bornée pour laisser voir les réglages qu'on touche.
   * Cette borne était posée en CSS (`max-height`), qui ne sait rabattre
   * qu'une dimension : un badge 9:16 y gardait sa largeur et perdait sa
   * hauteur — il s'affichait écrasé, et ce n'était pas le badge qu'on
   * allait télécharger. C'est ici qu'il faut la poser, où les deux côtés
   * se calculent ensemble.
   */
  function placeDisponible(): { w: number; h: number } {
    const boite = canvas.parentElement;
    let large = COTE;
    if (boite) {
      const st = getComputedStyle(boite);
      const dedans = boite.clientWidth
        - parseFloat(st.paddingLeft || '0') - parseFloat(st.paddingRight || '0');
      if (dedans > 120) large = Math.min(COTE, dedans);
    }
    // Le même seuil que la feuille de style : au-delà, la toile n'est plus
    // collée et rien ne borne sa hauteur.
    const haut = window.innerWidth <= 860 ? Math.round(window.innerHeight * 0.44) : COTE;
    return { w: large, h: Math.max(160, haut) };
  }

  function dessiner() {
    const ratio = tpl.canvas.width / tpl.canvas.height;
    const place = placeDisponible();
    // Le plus grand rectangle du bon rapport qui tienne dans cette place.
    const w = Math.round(Math.min(place.w, place.h * ratio));
    const h = Math.round(w / ratio);
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

  /* ---------------- chargement des images ---------------- */

  /**
   * Ce que chaque couche image doit charger, dans le format courant.
   *
   * C'est le gabarit qui le dit, jamais la colonne `cadre_url` du décor :
   * celle-ci ne connaît que le cadre du format natif, et l'imposer à une
   * story y collait le cadre carré, étiré sur seize neuvièmes. Elle ne
   * sert plus que de recours si le gabarit ne nomme rien.
   */
  function sourceDe(couche: any): string {
    return String(couche.src || (couche.id === 'frame' ? ctx.cadreUrl ?? '' : ''));
  }

  /** Ce qui est déjà chargé, et depuis où : on ne retélécharge pas pour rien. */
  const chargees: Record<string, string> = {};

  /**
   * Le cadre ET les images posées par l'auteur.
   *
   * Seul le cadre était chargé. Un logo de sponsor existait donc dans le
   * gabarit, s'affichait dans l'atelier — qui, lui, charge tout — et
   * disparaissait du badge de l'invité : `renderScene` ne trouve pas son
   * bitmap et passe la couche sans rien dire. L'organisateur n'avait aucun
   * moyen de s'en apercevoir avant de voir un badge reçu.
   */
  async function chargerImages() {
    const couches = (tpl.layers ?? []).filter((l: any) => l.type === 'image');
    for (const couche of couches) {
      const source = sourceDe(couche);
      if (!source || chargees[couche.id] === source) continue;
      try {
        assets[couche.id] = await loadImage(source);
        chargees[couche.id] = source;
      } catch {
        // Une image manquante ne doit pas empêcher d'écrire son prénom.
        delete assets[couche.id];
        delete chargees[couche.id];
        console.warn('image de calque introuvable :', source);
      }
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

  /**
   * Le badge est refusé quand l'organisateur a épuisé son offre du mois.
   *
   * L'invité n'y est pour rien : lui laisser une page muette, sans QR et
   * sans explication, lui ferait croire à une panne — et il réessaierait.
   * On le dit, et on ferme le téléchargement plutôt que de livrer un badge
   * sans jeton qui ne vaudrait rien à l'entrée.
   */
  function bloquer(message: string) {
    bloque = true;
    const etat = $('#etat');
    if (etat) etat.textContent = message;
    for (const sel of ['#telecharger', '#partager']) {
      const b = document.querySelector(sel) as HTMLButtonElement | null;
      if (b) {
        b.disabled = true;
        b.title = message;
      }
    }
  }

  /**
   * Où l'on retient le badge déjà obtenu, le temps de la visite.
   *
   * `sessionStorage` et non `localStorage` : quelqu'un qui revient la
   * semaine prochaine mérite un nouveau badge — c'est une nouvelle
   * participation. Ce qu'on veut éviter, c'est qu'une même visite en
   * consomme trois parce qu'elle a regardé les trois formats.
   */
  const CLE_JETON = 'wakabi.badge.' + ctx.decorId;

  function jetonRetenu(): string {
    try { return sessionStorage.getItem(CLE_JETON) ?? ''; } catch { return ''; }
  }

  async function emettreBadge() {
    try {
      const r = await fetch(ctx.base + '?p=api-badge', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        // Le serveur rend celui-là s'il est bien de ce décor, au lieu d'en
        // émettre un second : une place d'offre, un code d'entrée.
        body: JSON.stringify({ decor: ctx.decorId, jeton: jetonRetenu() || undefined }),
      });
      const d = await r.json();
      if (d.quota) {
        bloquer(d.erreur || 'Cette campagne a atteint son nombre de badges pour ce mois-ci.');
        return;
      }
      if (!d.jeton) return;
      jeton = d.jeton;
      try { sessionStorage.setItem(CLE_JETON, d.jeton); } catch { /* navigation privée */ }
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
    // Le bouton est déjà désactivé dans ce cas ; la garde couvre le clavier
    // et tout ce qui déclencherait l'événement sans passer par la souris.
    if (bloque) return;
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

  /* ---------------- changer de format ---------------- */

  /**
   * Les formats basculent SUR PLACE, sans recharger la page.
   *
   * Les liens restent de vrais liens — sans JavaScript, ils naviguent, et
   * un format reste partageable. Mais suivre le lien coûtait cher à
   * l'invité : il reperdait sa photo et son cadrage à chaque essai, et
   * l'organisateur y perdait une place d'offre par format regardé, le
   * jeton étant émis à chaque chargement.
   *
   * L'adresse est tout de même mise à jour : celui qui copie ce qu'il voit
   * dans sa barre partage le format qu'il regarde.
   */
  function basculer(vers: string, lien: HTMLAnchorElement): void {
    const g = (ctx.gabarits ?? {})[vers];
    if (!g || vers === ratio) return;

    ratio = vers;
    adopter(g);

    // La photo garde son cadrage, ramené dans la nouvelle fenêtre : une
    // story est plus haute qu'un carré, le zoom qui convenait à l'un peut
    // laisser un vide dans l'autre.
    if (spec.photo) spec.photo = recadrer(spec.photo);

    for (const a of document.querySelectorAll('.format-choix')) {
      const ici = a === lien;
      a.classList.toggle('actif', ici);
      if (ici) a.setAttribute('aria-current', 'true');
      else a.removeAttribute('aria-current');
    }

    dessiner();
    void chargerImages();
    if (spec.photo) {
      ($('#zoom') as HTMLInputElement).min = String(bornes.min);
      ($('#zoom') as HTMLInputElement).max = String(bornes.max);
      ($('#zoom') as HTMLInputElement).value = String(spec.photo.scale);
      afficherZoom();
    }
    try { history.replaceState(null, '', lien.href); } catch { /* peu importe */ }
  }

  for (const noeud of document.querySelectorAll('.format-choix')) {
    const lien = noeud as HTMLAnchorElement;
    const vers = lien.dataset.format ?? '';
    if (!(ctx.gabarits ?? {})[vers]) continue;
    lien.addEventListener('click', (e) => {
      e.preventDefault();
      basculer(vers, lien);
    });
  }

  void chargerImages();
  dessiner();
  /**
   * Puis on redessine une fois les polices là.
   *
   * Le premier dessin part TOUT DE SUITE : sur une connexion lente, un
   * canevas vide en attendant une fonte donnerait l'impression que le
   * Studio ne s'ouvre pas. Le second remet le texte dans la bonne police,
   * et c'est celui-là que l'invité voit avant de télécharger.
   */
  void attendrePolices().then(dessiner);
}

declare global {
  interface Window { WAKABI: Contexte }
}

if (window.WAKABI) demarrer(window.WAKABI);
