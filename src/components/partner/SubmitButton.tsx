'use client';

import { useState, useTransition } from 'react';
import { submitDecor, type SubmitResult } from '@/app/actions/decors';

export function SubmitButton({ id }: { id: string }) {
  const [pending, start] = useTransition();
  const [res, setRes] = useState<SubmitResult | null>(null);

  return (
    <span className="inline-block">
      <button
        type="button"
        disabled={pending}
        onClick={() =>
          start(async () => {
            setRes(await submitDecor(id));
          })
        }
        className="rounded-wk-sm bg-wk-primary px-3 py-1.5 text-[12.5px] font-bold text-white transition hover:bg-wk-primary-d disabled:opacity-50"
      >
        {pending ? 'Contrôle…' : 'Soumettre à la relecture'}
      </button>

      {res && !res.ok && (
        <span
          role="alert"
          className="mt-2 block rounded-wk-sm bg-red-50 px-3 py-2 text-[12.5px] leading-snug text-wk-red"
        >
          <b className="block">{res.message}</b>
          {res.failures?.map((f) => (
            <span key={f} className="mt-1 block">
              • {f}
            </span>
          ))}
        </span>
      )}
    </span>
  );
}
