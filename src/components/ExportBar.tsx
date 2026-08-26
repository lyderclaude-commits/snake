'use client';

import { useState } from 'react';
import type { DecorTemplate } from '@/core/template.schema';
import type { LayerAssets, RenderSpec } from '@/core/types';
import { renderScene } from '@/core/renderScene';
import {
  chooseRoute,
  shareFile,
  slugifyFilename,
  triggerDownload,
} from '@/lib/share';

interface Props {
  tpl: DecorTemplate;
  spec: RenderSpec;
  assets: LayerAssets;
  ready: boolean;
  /** Jeton déjà émis par le studio ; le QR est déjà dans le RenderSpec. */
  badgeToken?: string | null;
  /** Appelé une seule fois, quand l'image a réellement été produite. */
  onExported?: (token?: string) => void;
}

/**
 * Rend le visuel en haute définition et renvoie un Blob.
 *
 * Même fonction que l'aperçu, seule l'échelle change — c'est ce qui rend le
 * « ce que tu vois est ce que tu télécharges » vrai par construction.
 *
 * NOTE : le rendu se fait ici sur le thread principal. La spécification
 * prévoit un OffscreenCanvas dans un Web Worker (§4) pour ne pas figer un
 * Android d'entrée de gamme ; le renderer étant déjà pur et sans DOM, le
 * basculement ne touchera que ce fichier. À faire avant la mise en ligne.
 */
async function renderExport(
  tpl: DecorTemplate,
  spec: RenderSpec,
  assets: LayerAssets,
): Promise<Blob> {
  const longest = Math.max(tpl.canvas.width, tpl.canvas.height);
  const scale = tpl.export.maxPx / longest;

  const canvas = document.createElement('canvas');
  canvas.width = Math.round(tpl.canvas.width * scale);
  canvas.height = Math.round(tpl.canvas.height * scale);
  const ctx = canvas.getContext('2d');
  if (!ctx) throw new Error('Canvas indisponible sur ce navigateur.');

  renderScene(ctx, spec, tpl, assets, scale);

  return new Promise((resolve, reject) => {
    canvas.toBlob(
      (blob) => (blob ? resolve(blob) : reject(new Error("L'image n'a pas pu être générée."))),
      tpl.export.mimeType,
      tpl.export.quality,
    );
  });
}

export function ExportBar({ tpl, spec, assets, ready, badgeToken, onExported }: Props) {
  const [busy, setBusy] = useState(false);
  const [longPress, setLongPress] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [done, setDone] = useState(false);

  const ext = tpl.export.mimeType === 'image/png' ? 'png' : 'jpg';
  const filename = slugifyFilename(tpl.slug, ext);
  const caption = [tpl.share.defaultCaption, ...tpl.share.hashtags.map((h) => `#${h}`)]
    .filter(Boolean)
    .join(' ');

  const run = async () => {
    setBusy(true);
    setError(null);
    try {
      // Le QR est déjà dans le RenderSpec depuis l'ajout de la photo :
      // l'aperçu et l'export portent donc exactement le même code.
      const blob = await renderExport(tpl, spec, assets);
      const route = chooseRoute();

      if (route === 'share') {
        const ok = await shareFile(blob, filename, caption);
        if (!ok) triggerDownload(blob, filename);
      } else if (route === 'download') {
        triggerDownload(blob, filename);
      } else {
        // Webview : le téléchargement est bloqué, on affiche l'image et on
        // explique l'appui long. C'est exactement le chemin d'arrivée depuis
        // Instagram ou Facebook.
        setLongPress(URL.createObjectURL(blob));
      }
      setDone(true);
      onExported?.(badgeToken ?? undefined);
    } catch (e) {
      setError(e instanceof Error ? e.message : 'Une erreur est survenue.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="space-y-3">
      <button
        type="button"
        onClick={run}
        disabled={!ready || busy}
        className="w-full rounded-wk-md bg-wk-primary px-5 py-4 font-display text-[16px] font-bold text-white shadow-wk transition hover:bg-wk-primary-d disabled:cursor-not-allowed disabled:opacity-45"
      >
        {busy ? 'Génération…' : ready ? 'Télécharger mon visuel' : 'Ajoute une photo d’abord'}
      </button>

      {error && (
        <p role="alert" className="rounded-wk-md bg-red-50 px-3 py-2 text-[13px] text-wk-red">
          {error}
        </p>
      )}

      {badgeToken && done && (
        <div className="rounded-wk-md border border-wk-border bg-wk-bg2 px-4 py-3">
          <p className="text-[13px] leading-snug text-wk-text2">
            <b className="text-wk-text">Ton QR est sur le badge.</b> Présente-le à l’entrée pour
            valider ta présence et gagner des Koris.
          </p>
          <p className="mt-1.5 font-mono text-[15px] font-bold tracking-[0.12em] text-wk-primary">
            {badgeToken}
          </p>
        </div>
      )}

      {/* La redirection après téléchargement — la brique d'ancrage.
          mougni la facture dès 5 000 FCFA/mois ; ici elle est gratuite et
          structurante : c'est elle qui ramène le monde sur Wakabi. */}
      {done && tpl.share.redirectUrl && (
        <a
          href={tpl.share.redirectUrl}
          target="_blank"
          rel="noopener noreferrer"
          className="block rounded-wk-md border-2 border-wk-primary bg-wk-primary-l px-5 py-3.5 text-center font-display text-[15px] font-bold text-wk-primary transition hover:bg-wk-bg3"
        >
          {tpl.share.redirectLabel} →
        </a>
      )}

      {longPress && (
        <div className="rounded-wk-lg border border-wk-border bg-white p-3">
          <p className="mb-2 text-[13px] font-semibold text-wk-text">
            Appui long sur l’image pour l’enregistrer
          </p>
          <p className="mb-3 text-[12px] leading-snug text-wk-text2">
            Ton navigateur bloque le téléchargement direct. Ouvre le lien dans Chrome ou
            Safari pour l’enregistrer d’un seul geste.
          </p>
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img src={longPress} alt="Ton visuel Wakabi" className="w-full rounded-wk-md" />
        </div>
      )}
    </div>
  );
}
