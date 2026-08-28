'use client';

import type { RenderSpec } from '@/core/types';
import type { SpecDispatch } from '@/store/useRenderSpec';

/**
 * Zoom, recentrage, miroir. Volontairement PAS de rotation.
 *
 * mougni, qui domine la catégorie, se limite à « zoom, position » — et
 * chaque geste supplémentaire coûte sur le budget des 30 secondes.
 * Voir docs/03-ANALYSE-MOUGNI.md §3.
 */
export function CropToolbar({
  spec,
  dispatch,
  minScale = 0.2,
  maxScale,
}: {
  spec: RenderSpec;
  dispatch: SpecDispatch;
  minScale?: number;
  maxScale: number;
}) {
  const disabled = !spec.photo;
  const scale = spec.photo?.scale ?? 1;

  return (
    <div className="rounded-wk-lg border border-wk-border bg-white p-4">
      <div className="mb-3 flex items-center justify-between">
        <label htmlFor="zoom" className="text-[13px] font-semibold text-wk-text2">
          Zoom
        </label>
        <span className="font-mono text-[12px] tabular-nums text-wk-text3">
          ×{scale.toFixed(2)}
        </span>
      </div>
      <input
        id="zoom"
        type="range"
        min={minScale}
        max={maxScale}
        step={0.01}
        value={scale}
        disabled={disabled}
        onChange={(e) => dispatch({ type: 'setZoom', scale: Number(e.target.value) })}
        className="w-full accent-wk-primary disabled:opacity-40"
      />
      <div className="mt-3 flex gap-2">
        <button
          type="button"
          disabled={disabled}
          onClick={() => dispatch({ type: 'recenter' })}
          className="flex-1 rounded-wk-sm border border-wk-border px-3 py-2 text-[13px] font-semibold text-wk-text2 transition hover:border-wk-border2 disabled:opacity-40"
        >
          Recentrer
        </button>
        <button
          type="button"
          disabled={disabled}
          onClick={() => dispatch({ type: 'flip' })}
          className="flex-1 rounded-wk-sm border border-wk-border px-3 py-2 text-[13px] font-semibold text-wk-text2 transition hover:border-wk-border2 disabled:opacity-40"
        >
          Miroir
        </button>
      </div>

      <div className="mt-4 border-t border-wk-border pt-3">
        <div className="mb-2 flex items-center justify-between">
          <label htmlFor="filter" className="text-[13px] font-semibold text-wk-text2">
            Teinte Wakabi
          </label>
          <span className="font-mono text-[12px] tabular-nums text-wk-text3">
            {Math.round(spec.filterIntensity * 100)} %
          </span>
        </div>
        <input
          id="filter"
          type="range"
          min={0}
          max={1}
          step={0.05}
          value={spec.filterIntensity}
          disabled={disabled}
          onChange={(e) => dispatch({ type: 'setFilter', intensity: Number(e.target.value) })}
          className="w-full accent-wk-primary disabled:opacity-40"
        />
      </div>
    </div>
  );
}
