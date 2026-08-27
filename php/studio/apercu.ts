/**
 * L'aperçu du formulaire de décor.
 *
 * Il ne fabrique RIEN : à chaque geste, il renvoie le formulaire au serveur,
 * qui construit le gabarit avec la même fonction que celle qui l'enregistre,
 * puis il dessine ce gabarit avec le même renderer que le Studio. Un aperçu
 * qui composerait sa propre version de la structure finirait par montrer
 * autre chose que ce qu'on enregistre — et c'est exactement ce qu'un aperçu
 * ne doit jamais faire.
 */

import { renderScene } from '@/core/renderScene';
import { loadImage } from '@/core/imagePipeline';
import { clampPhoto } from '@/core/fitPhoto';
import type { LayerAssets, LoadedImage, RenderSpec } from '@/core/types';
import type { Rect } from '@/core/fitPhoto';

interface Contexte {
  base: string;
  csrf: string;
}

const CHAMPS = [
  'disposition', 'cadre_url', 'cadre_fourni', 'titre', 'accroche', 'champ_libelle',
  'texte_couleur', 'texte_align', 'bloc_x', 'bloc_y', 'bloc_w',
  'accroche_taille', 'champ_taille', 'qr_position', 'qr_taille', 'filigrane_position',
];

// Les curseurs affichent une fraction de la hauteur du canevas : un
// pourcentage se lit, « 0.058 » ne se lit pas.
const POURCENTAGES = new Set([
  'bloc_x', 'bloc_y', 'bloc_w', 'accroche_taille', 'champ_taille', 'qr_taille',
]);

function demarrer(ctx: Contexte) {
  const formulaire = document.getElementById('form-decor') as HTMLFormElement | null;
  const canevas = document.getElementById('apercu') as HTMLCanvasElement | null;
  const legende = document.getElementById('apercu-etat') as HTMLElement | null;
  if (!formulaire || !canevas || !legende) return;
  // Rebaptisés après le garde : le typage ne suit pas la vérification à
  // l'intérieur des fonctions déclarées plus bas.
  const form = formulaire, toile = canevas, etat = legende;

  const dessin = toile.getContext('2d')!;
  const assets: LayerAssets = {};
  let photo: LoadedImage | null = null;
  let qr: LoadedImage | null = null;
  let cadreCharge = '';
  let fichierUrl: string | null = null;
  let minuteur: number | undefined;
  let tour = 0;

  const val = (nom: string): string => {
    const el = form.elements.namedItem(nom) as HTMLInputElement | null;
    return el && 'value' in el ? el.value : '';
  };

  /** Les nombres du formulaire, montrés en clair sous chaque curseur. */
  function afficherValeurs() {
    for (const nom of CHAMPS) {
      const sortie = document.getElementById('v-' + nom);
      if (!sortie) continue;
      const v = Number(val(nom));
      sortie.textContent = POURCENTAGES.has(nom) ? Math.round(v * 100) + ' %' : String(v);
    }
  }

  async function rafraichir(reinit = false) {
    const mien = ++tour;
    const corps = new URLSearchParams({ csrf: ctx.csrf });
    for (const nom of CHAMPS) corps.set(nom, val(nom));
    if (reinit) corps.set('reinit', '1');

    let d: any;
    try {
      const r = await fetch(ctx.base + '?p=api-apercu', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: corps.toString(),
      });
      d = await r.json();
    } catch {
      etat.textContent = 'Aperçu indisponible — la saisie, elle, est bien prise en compte.';
      return;
    }
    // Une réponse arrivée après une plus récente ne doit pas la recouvrir.
    if (mien !== tour) return;

    if (d.erreur) {
      etat.textContent = d.erreur;
      return;
    }

    // Réglages d'usine du gabarit : on les remet dans les commandes, sinon
    // les curseurs raconteraient autre chose que l'image.
    if (reinit && d.apparence) {
      for (const [cle, valeur] of Object.entries(d.apparence)) {
        const el = form.elements.namedItem(cle) as HTMLInputElement | null;
        if (el) el.value = String(valeur);
      }
      afficherValeurs();
    }

    const tpl = d.gabarit;
    const couche = (tpl.layers ?? []).find((l: any) => l.type === 'image');
    const source = fichierUrl ?? d.cadre;

    if (couche && source && source !== cadreCharge) {
      try {
        assets[couche.id] = await loadImage(source);
        cadreCharge = source;
      } catch {
        delete assets[couche.id];
        cadreCharge = '';
      }
    }
    if (!photo && d.photo) {
      try {
        photo = await loadImage(d.photo);
      } catch { /* l'aperçu se passe de la photo d'exemple */ }
    }
    if (!qr && d.qr) {
      try {
        qr = await loadImage(d.qr);
      } catch { /* idem pour le QR d'illustration */ }
    }
    if (mien !== tour) return;

    dessiner(tpl);
    etat.textContent = fichierUrl
      ? 'Aperçu avec votre cadre et une photo d’exemple.'
      : 'Aperçu avec un cadre et une photo d’exemple.';
  }

  function dessiner(tpl: any) {
    const MAX_L = 300, MAX_H = 430;
    const ratio = tpl.canvas.width / tpl.canvas.height;
    let l = MAX_L, h = Math.round(MAX_L / ratio);
    if (h > MAX_H) {
      h = MAX_H;
      l = Math.round(MAX_H * ratio);
    }
    const dpr = Math.min(2, window.devicePixelRatio || 1);
    toile.width = l * dpr;
    toile.height = h * dpr;
    toile.style.width = l + 'px';
    toile.style.height = h + 'px';

    const emplacement: Rect = (tpl.layers ?? []).find((c: any) => c.type === 'photoSlot')?.rect
      ?? { x: 0, y: 0, w: 1, h: 1 };

    const spec: RenderSpec = {
      templateId: tpl.id,
      photo: null,
      filter: 'none',
      filterIntensity: 0.35,
      texts: {},
      qr,
    };
    for (const c of tpl.layers ?? []) {
      if (c.type === 'text' && c.editable) spec.texts[c.id] = c.value || c.placeholder || '';
    }
    if (photo) {
      const p = { image: photo, x: 0, y: 0, scale: 1, flipX: false };
      spec.photo = clampPhoto(
        p, emplacement,
        (photo as HTMLImageElement).width || 1,
        (photo as HTMLImageElement).height || 1,
        tpl.canvas.width, tpl.canvas.height,
      );
    }

    renderScene(dessin, spec, tpl, assets, (l * dpr) / tpl.canvas.width);
  }

  /* ---------------- écoute du formulaire ---------------- */

  const plusTard = (reinit = false) => {
    window.clearTimeout(minuteur);
    minuteur = window.setTimeout(() => rafraichir(reinit), 220);
  };

  form.addEventListener('input', (e) => {
    afficherValeurs();
    const cible = e.target as HTMLElement;
    // Changer de format repart des réglages de ce gabarit.
    plusTard(cible.id === 'disposition');
  });
  form.addEventListener('change', (e) => {
    const cible = e.target as HTMLInputElement;
    if (cible.id === 'cadre') {
      // Le cadre choisi n'est pas encore sur le serveur : on le lit sur place.
      if (fichierUrl) URL.revokeObjectURL(fichierUrl);
      const f = cible.files?.[0];
      fichierUrl = f ? URL.createObjectURL(f) : null;
      cadreCharge = '';
      const nom = document.querySelector('.fichier .texte');
      if (nom) nom.textContent = f ? f.name : 'Choisir un fichier';
    }
    plusTard(cible.id === 'disposition');
  });

  document.getElementById('apparence-defaut')?.addEventListener('click', () => rafraichir(true));

  afficherValeurs();
  rafraichir();
}

const ctx = (window as unknown as { WAKABI_APERCU?: Contexte }).WAKABI_APERCU;
if (ctx) demarrer(ctx);
