'use client';

/**
 * L'état de l'éditeur EST le RenderSpec (docs/00-SPEC-TECHNIQUE.md §4).
 *
 * Un `useReducer` suffit : l'état est un seul objet plat, manipulé depuis un
 * seul écran. Pas de store global, pas de dépendance.
 */

import { useReducer, useCallback } from 'react';
import type { DecorTemplate } from '@/core/template.schema';
import type { PhotoState, RenderSpec } from '@/core/types';
import { clampPhoto } from '@/core/fitPhoto';

type Action =
  | { type: 'setPhoto'; photo: PhotoState | null }
  | { type: 'pan'; dx: number; dy: number }
  | { type: 'zoom'; factor: number }
  | { type: 'setZoom'; scale: number }
  | { type: 'flip' }
  | { type: 'recenter' }
  | { type: 'setFilter'; intensity: number }
  | { type: 'setText'; id: string; value: string }
  | { type: 'setQr'; qr: RenderSpec['qr'] };

function initialSpec(tpl: DecorTemplate): RenderSpec {
  const texts: Record<string, string> = {};
  for (const l of tpl.layers) {
    if (l.type === 'text' && l.editable) texts[l.id] = l.value;
  }
  return {
    templateId: tpl.id,
    photo: null,
    filter: 'wakabi-blue',
    filterIntensity: 0,
    texts,
    qr: null,
  };
}

/**
 * Après chaque geste, la photo est ramenée dans les bornes où elle couvre
 * encore tout l'emplacement. Centraliser ici garantit qu'aucun chemin ne
 * peut produire un bord vide.
 */
function withClamp(spec: RenderSpec, tpl: DecorTemplate): RenderSpec {
  if (!spec.photo) return spec;
  const slotLayer = tpl.layers.find((l) => l.type === 'photoSlot');
  if (!slotLayer || slotLayer.type !== 'photoSlot') return spec;
  const img = spec.photo.image;
  const iw = 'width' in img ? (img.width as number) : 1;
  const ih = 'height' in img ? (img.height as number) : 1;
  return {
    ...spec,
    photo: clampPhoto(
      spec.photo,
      slotLayer.rect,
      iw,
      ih,
      tpl.canvas.width,
      tpl.canvas.height,
    ),
  };
}

function reducer(tpl: DecorTemplate) {
  return (state: RenderSpec, action: Action): RenderSpec => {
    switch (action.type) {
      case 'setPhoto':
        return withClamp(
          { ...state, photo: action.photo },
          tpl,
        );
      case 'pan':
        if (!state.photo) return state;
        return withClamp(
          {
            ...state,
            photo: {
              ...state.photo,
              x: state.photo.x + action.dx,
              y: state.photo.y + action.dy,
            },
          },
          tpl,
        );
      case 'zoom':
      case 'setZoom': {
        if (!state.photo) return state;
        const slot = tpl.layers.find((l) => l.type === 'photoSlot');
        // Les deux bornes viennent du gabarit. Le plancher était à 1 : sous
        // cette valeur la photo est simplement plus petite que son
        // emplacement, ce qui est le seul moyen d'y faire tenir une image
        // entière dont les proportions ne sont pas celles du décor.
        const max = slot && slot.type === 'photoSlot' ? slot.maxScale : 4;
        const min = slot && slot.type === 'photoSlot' ? slot.minScale : 0.2;
        const next =
          action.type === 'zoom'
            ? state.photo.scale * action.factor
            : action.scale;
        return withClamp(
          { ...state, photo: { ...state.photo, scale: Math.min(max, Math.max(min, next)) } },
          tpl,
        );
      }
      case 'flip':
        if (!state.photo) return state;
        return { ...state, photo: { ...state.photo, flipX: !state.photo.flipX } };
      case 'recenter':
        if (!state.photo) return state;
        return withClamp(
          { ...state, photo: { ...state.photo, x: 0, y: 0, scale: 1 } },
          tpl,
        );
      case 'setFilter':
        return { ...state, filterIntensity: action.intensity };
      case 'setText':
        return { ...state, texts: { ...state.texts, [action.id]: action.value } };
      case 'setQr':
        return { ...state, qr: action.qr };
      default:
        return state;
    }
  };
}

export function useRenderSpec(tpl: DecorTemplate) {
  const [spec, dispatch] = useReducer(reducer(tpl), tpl, initialSpec);
  const setPhoto = useCallback(
    (photo: PhotoState | null) => dispatch({ type: 'setPhoto', photo }),
    [],
  );
  return { spec, dispatch, setPhoto };
}

export type SpecDispatch = ReturnType<typeof useRenderSpec>['dispatch'];
