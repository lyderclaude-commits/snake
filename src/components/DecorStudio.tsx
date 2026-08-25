'use client';

/**
 * L'orchestrateur : détient le RenderSpec, charge les ressources, et
 * distribue tout aux sous-composants. Voir docs/00-SPEC-TECHNIQUE.md §6.
 */

import { useEffect, useMemo, useState } from 'react';
import Link from 'next/link';
import type { DecorTemplate } from '@/core/template.schema';
import type { LayerAssets } from '@/core/types';
import { loadImage } from '@/core/imagePipeline';
import { mintBadge } from '@/app/actions/badges';
import { useRenderSpec } from '@/store/useRenderSpec';
import { CanvasStage } from './CanvasStage';
import { PhotoDropzone } from './PhotoDropzone';
import { CropToolbar } from './CropToolbar';
import { TextPanel } from './TextPanel';
import { ExportBar } from './ExportBar';
import { WakabiMark } from './WakabiMark';

/**
 * Les polices doivent être RÉELLEMENT chargées avant le premier rendu :
 * canvas ne déclenche aucun chargement et retomberait silencieusement sur
 * une police système, ce qui décalerait toute la mise en page.
 */
async function ensureFonts(): Promise<void> {
  if (typeof document === 'undefined' || !document.fonts) return;
  await Promise.all([
    document.fonts.load("800 64px 'Bricolage Grotesque'"),
    document.fonts.load("700 64px 'Bricolage Grotesque'"),
    document.fonts.load("600 32px 'Plus Jakarta Sans'"),
    document.fonts.load("700 32px 'Plus Jakarta Sans'"),
  ]);
  await document.fonts.ready;
}

interface StudioProps {
  tpl: DecorTemplate;
  /** Identifiant en base — sert aux compteurs et à l'historique. */
  decorId?: string;
  /** Cadre servi localement ; le `src` du gabarit pointe vers le CDN. */
  frameUrl?: string | null;
  signedIn?: boolean;
  stats?: { downloads: number; views: number };
}

export function DecorStudio({ tpl, decorId, frameUrl, signedIn, stats }: StudioProps) {
  const { spec, dispatch, setPhoto } = useRenderSpec(tpl);
  const [assets, setAssets] = useState<LayerAssets>({});
  const [loading, setLoading] = useState(true);
  const [token, setToken] = useState<string | null>(null);

  /**
   * Émettre le badge dès que la photo est là, pas au téléchargement.
   *
   * Le QR fait partie du visuel : le montrer seulement après l'export
   * trahirait le « ce que tu vois est ce que tu télécharges ». Un jeton
   * émis et jamais utilisé ne coûte qu'une ligne ; une surprise à
   * l'ouverture du fichier coûte la confiance.
   */
  useEffect(() => {
    if (!decorId || !spec.photo || token || !tpl.qr.enabled) return;
    let alive = true;
    (async () => {
      const minted = await mintBadge(decorId);
      if (!alive) return;
      setToken(minted.token);
      dispatch({ type: 'setQr', qr: await loadImage(minted.qrDataUrl) });
    })().catch(() => undefined);
    return () => {
      alive = false;
    };
  }, [decorId, spec.photo, token, tpl.qr.enabled, dispatch]);

  const maxScale = useMemo(() => {
    const slot = tpl.layers.find((l) => l.type === 'photoSlot');
    return slot && slot.type === 'photoSlot' ? slot.maxScale : 4;
  }, [tpl, frameUrl]);

  useEffect(() => {
    let alive = true;
    (async () => {
      const imageLayers = tpl.layers.filter((l) => l.type === 'image');
      const [, ...images] = await Promise.all([
        ensureFonts(),
        ...imageLayers.map((l) =>
          l.type === 'image'
            ? loadImage(frameUrl ?? `/frames/${l.src.split('/').pop()}`)
            : Promise.resolve(null),
        ),
      ]);
      if (!alive) return;
      const next: LayerAssets = {};
      imageLayers.forEach((l, i) => {
        const img = images[i];
        if (img) next[l.id] = img;
      });
      setAssets(next);
      setLoading(false);
    })().catch(() => alive && setLoading(false));
    return () => {
      alive = false;
    };
  }, [tpl, frameUrl]);

  return (
    <main className="mx-auto min-h-dvh w-full max-w-5xl px-5 pb-28">
      <header className="flex items-center justify-between py-5">
        <Link href="/" aria-label="Retour aux décors">
          <WakabiMark tagline={false} />
        </Link>
        <Link
          href="/"
          className="rounded-full border border-wk-border bg-white px-3 py-1.5 text-[12px] font-semibold text-wk-text2 transition hover:border-wk-border2"
        >
          ← Tous les décors
        </Link>
      </header>

      <div className="mb-5">
        <h1 className="font-display text-[clamp(1.5rem,4.5vw,2.1rem)] font-extrabold leading-tight tracking-[-0.03em]">
          {tpl.title}
        </h1>
        <p className="mt-1 text-[15px] text-wk-text2">{tpl.subtitle}</p>
        {stats && (
          <p className="mt-2 flex items-center gap-4 text-[12.5px] font-semibold text-wk-text3">
            <span>⬇ {stats.downloads} téléchargés</span>
            <span>👁 {stats.views} vues</span>
            {!signedIn && (
              <Link href="/inscription" className="text-wk-primary hover:underline">
                Créer un compte pour retrouver tes visuels
              </Link>
            )}
          </p>
        )}
      </div>

      <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px] lg:items-start">
        <div className="mx-auto w-full max-w-[440px] lg:max-w-none">
          <CanvasStage
            tpl={tpl}
            spec={spec}
            assets={assets}
            dispatch={dispatch}
            interactive={!!spec.photo}
          />
          <p className="mt-2 text-center text-[12px] text-wk-text3">
            {loading
              ? 'Chargement du décor…'
              : spec.photo
                ? 'Glisse pour déplacer · pince ou molette pour zoomer'
                : 'Ajoute ta photo pour commencer'}
          </p>
        </div>

        <div className="space-y-4">
          <PhotoDropzone onPhoto={setPhoto} hasPhoto={!!spec.photo} />
          <CropToolbar spec={spec} dispatch={dispatch} maxScale={maxScale} />
          <TextPanel tpl={tpl} spec={spec} dispatch={dispatch} />
          <ExportBar
            tpl={tpl}
            spec={spec}
            assets={assets}
            ready={!!spec.photo && !loading}
            badgeToken={token}
          />
        </div>
      </div>
    </main>
  );
}
