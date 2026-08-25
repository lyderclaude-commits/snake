'use client';

import { useActionState, useEffect, useRef } from 'react';
import { scanBadge, type ScanState } from '@/app/actions/badges';
import { Button, Card } from '@/components/ui';

/**
 * Contrôle d'entrée.
 *
 * Le champ est en saisie libre plutôt qu'une caméra : sur le terrain, un
 * lecteur de QR physique se comporte comme un clavier, et l'entrée manuelle
 * reste possible quand le code est abîmé ou l'éclairage mauvais.
 */
export function ScanForm() {
  const [state, action, pending] = useActionState<ScanState, FormData>(scanBadge, {});
  const ref = useRef<HTMLInputElement>(null);

  // Reprendre le focus après chaque validation : à l'entrée, on enchaîne.
  useEffect(() => {
    if (!pending) ref.current?.focus();
  }, [pending, state]);

  return (
    <div className="space-y-4">
      <Card className="p-6">
        <form action={action}>
          <label htmlFor="token" className="block font-display text-[15px] font-bold">
            Code du badge
          </label>
          <p className="mb-3 mt-1 text-[13px] text-wk-text2">
            Scannez le QR — le lecteur se comporte comme un clavier — ou tapez les
            10&nbsp;caractères.
          </p>
          <div className="flex gap-2">
            <input
              ref={ref}
              id="token"
              name="token"
              autoFocus
              autoComplete="off"
              maxLength={10}
              placeholder="A7K2M9XQ4P"
              className="w-full rounded-wk-md border-2 border-wk-border bg-wk-bg2 px-4 py-3 text-center font-mono text-[22px] font-bold uppercase tracking-[0.18em] outline-none transition focus:border-wk-primary focus:bg-white"
            />
            <Button type="submit" size="lg" disabled={pending}>
              {pending ? '…' : 'Valider'}
            </Button>
          </div>
        </form>
      </Card>

      {state.message && (
        <div
          role="status"
          className={`rounded-wk-lg border-2 p-5 ${
            state.ok ? 'border-wk-green bg-green-50' : 'border-wk-red bg-red-50'
          }`}
        >
          <p
            className={`font-display text-[18px] font-extrabold ${
              state.ok ? 'text-wk-green' : 'text-wk-red'
            }`}
          >
            {state.ok ? '✓ ' : '✗ '}
            {state.message}
          </p>
          {state.detail && <p className="mt-1 text-[14px] text-wk-text2">{state.detail}</p>}
        </div>
      )}
    </div>
  );
}
