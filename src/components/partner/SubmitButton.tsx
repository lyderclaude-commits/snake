'use client';

import { useTransition } from 'react';
import { submitDecor } from '@/app/actions/decors';

export function SubmitButton({ id }: { id: string }) {
  const [pending, start] = useTransition();
  return (
    <button
      type="button"
      disabled={pending}
      onClick={() => start(() => void submitDecor(id))}
      className="rounded-wk-sm bg-wk-primary px-3 py-1.5 text-[12.5px] font-bold text-white transition hover:bg-wk-primary-d disabled:opacity-50"
    >
      {pending ? 'Envoi…' : 'Soumettre à la relecture'}
    </button>
  );
}
