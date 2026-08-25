'use client';

/**
 * L'aperçu — échelle basse, thread principal.
 *
 * Le canevas n'est PAS une scène d'objets : c'est un simple appel à
 * `renderScene` à chaque changement d'état. C'est la découverte de
 * l'implémentation — avec un renderer pur à deux échelles, une bibliothèque
 * de graphe de scène (Konva, Fabric) n'apporte rien. Voir le journal
 * d'implémentation, docs/05-IMPLEMENTATION.md.
 */

import { useEffect, useRef, useCallback } from 'react';
import type { DecorTemplate } from '@/core/template.schema';
import type { LayerAssets, RenderSpec } from '@/core/types';
import { renderScene } from '@/core/renderScene';
import type { SpecDispatch } from '@/store/useRenderSpec';

interface Props {
  tpl: DecorTemplate;
  spec: RenderSpec;
  assets: LayerAssets;
  dispatch: SpecDispatch;
  interactive: boolean;
}

export function CanvasStage({ tpl, spec, assets, dispatch, interactive }: Props) {
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const boxRef = useRef<HTMLDivElement>(null);
  const pointers = useRef(new Map<number, { x: number; y: number }>());
  const pinchDist = useRef(0);

  /** Redessine à l'échelle de l'aperçu. */
  const draw = useCallback(() => {
    const canvas = canvasRef.current;
    const box = boxRef.current;
    if (!canvas || !box) return;

    const cssW = box.clientWidth;
    if (!cssW) return;
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    const scale = (cssW * dpr) / tpl.canvas.width;

    const w = Math.round(tpl.canvas.width * scale);
    const h = Math.round(tpl.canvas.height * scale);
    if (canvas.width !== w || canvas.height !== h) {
      canvas.width = w;
      canvas.height = h;
    }
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    renderScene(ctx, spec, tpl, assets, scale);
  }, [spec, tpl, assets]);

  useEffect(() => {
    draw();
  }, [draw]);

  useEffect(() => {
    const ro = new ResizeObserver(() => draw());
    if (boxRef.current) ro.observe(boxRef.current);
    return () => ro.disconnect();
  }, [draw]);

  /* ---- gestes : Pointer Events, un seul chemin souris + tactile ---- */

  const onPointerDown = (e: React.PointerEvent) => {
    if (!interactive) return;
    (e.target as Element).setPointerCapture(e.pointerId);
    pointers.current.set(e.pointerId, { x: e.clientX, y: e.clientY });
    if (pointers.current.size === 2) {
      const [a, b] = [...pointers.current.values()];
      pinchDist.current = Math.hypot(a.x - b.x, a.y - b.y);
    }
  };

  const onPointerMove = (e: React.PointerEvent) => {
    if (!interactive) return;
    const prev = pointers.current.get(e.pointerId);
    if (!prev) return;
    const box = boxRef.current;
    if (!box) return;
    const cssW = box.clientWidth;

    pointers.current.set(e.pointerId, { x: e.clientX, y: e.clientY });

    if (pointers.current.size === 2) {
      const [a, b] = [...pointers.current.values()];
      const dist = Math.hypot(a.x - b.x, a.y - b.y);
      if (pinchDist.current > 0) {
        dispatch({ type: 'zoom', factor: dist / pinchDist.current });
      }
      pinchDist.current = dist;
      return;
    }

    // Un déplacement en pixels d'écran devient un décalage normalisé :
    // la largeur affichée correspond exactement à 1 unité de canevas.
    dispatch({
      type: 'pan',
      dx: (e.clientX - prev.x) / cssW,
      dy: (e.clientY - prev.y) / cssW,
    });
  };

  const onPointerUp = (e: React.PointerEvent) => {
    pointers.current.delete(e.pointerId);
    if (pointers.current.size < 2) pinchDist.current = 0;
  };

  const onWheel = (e: React.WheelEvent) => {
    if (!interactive) return;
    dispatch({ type: 'zoom', factor: e.deltaY < 0 ? 1.06 : 1 / 1.06 });
  };

  return (
    <div
      ref={boxRef}
      className="wk-stage relative w-full overflow-hidden rounded-wk-lg bg-wk-surface2 shadow-wk"
      style={{ aspectRatio: `${tpl.canvas.width} / ${tpl.canvas.height}` }}
      onPointerDown={onPointerDown}
      onPointerMove={onPointerMove}
      onPointerUp={onPointerUp}
      onPointerCancel={onPointerUp}
      onWheel={onWheel}
    >
      <canvas
        ref={canvasRef}
        className="block size-full"
        style={{ cursor: interactive ? 'grab' : 'default' }}
        aria-label={`Aperçu du visuel : ${tpl.title}`}
      />
    </div>
  );
}
