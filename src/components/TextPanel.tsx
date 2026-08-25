'use client';

import type { DecorTemplate } from '@/core/template.schema';
import type { RenderSpec } from '@/core/types';
import type { SpecDispatch } from '@/store/useRenderSpec';

export function TextPanel({
  tpl,
  spec,
  dispatch,
}: {
  tpl: DecorTemplate;
  spec: RenderSpec;
  dispatch: SpecDispatch;
}) {
  const fields = tpl.layers.filter((l) => l.type === 'text' && l.editable);
  if (!fields.length) return null;

  return (
    <div className="rounded-wk-lg border border-wk-border bg-white p-4">
      <p className="mb-3 text-[13px] font-semibold text-wk-text2">Personnalise ton texte</p>
      <div className="space-y-3">
        {fields.map((f) => {
          if (f.type !== 'text') return null;
          const value = spec.texts[f.id] ?? '';
          return (
            <div key={f.id}>
              <div className="mb-1 flex items-baseline justify-between">
                <label htmlFor={f.id} className="text-[12px] font-medium text-wk-text3">
                  {f.placeholder || f.id}
                </label>
                <span className="font-mono text-[11px] tabular-nums text-wk-text3">
                  {value.length}/{f.maxLength}
                </span>
              </div>
              <input
                id={f.id}
                type="text"
                value={value}
                maxLength={f.maxLength}
                placeholder={f.placeholder}
                onChange={(e) =>
                  dispatch({ type: 'setText', id: f.id, value: e.target.value })
                }
                className="w-full rounded-wk-sm border border-wk-border bg-wk-bg2 px-3 py-2.5 text-[15px] text-wk-text outline-none transition focus:border-wk-primary focus:bg-white"
              />
            </div>
          );
        })}
      </div>
    </div>
  );
}
