'use client';

import { useActionState } from 'react';
import Link from 'next/link';
import { signIn, type FormState } from '@/app/actions/auth';
import { Button, Card, Field, inputClass } from '@/components/ui';
import { WakabiMark } from '@/components/WakabiMark';

export default function ConnexionPage() {
  const [state, action, pending] = useActionState<FormState, FormData>(signIn, {});

  return (
    <main className="mx-auto flex min-h-dvh max-w-md flex-col justify-center px-5 py-12">
      <Link href="/" className="mx-auto mb-8">
        <WakabiMark />
      </Link>

      <Card className="p-6 shadow-wk">
        <h1 className="font-display text-[24px] font-extrabold tracking-tight">Se connecter</h1>
        <p className="mt-1 text-[14px] text-wk-text2">
          Retrouvez vos campagnes et vos créations.
        </p>

        <form action={action} className="mt-6 space-y-4">
          <Field label="E-mail">
            <input name="email" type="email" autoComplete="email" required className={inputClass} />
          </Field>
          <Field label="Mot de passe">
            <input
              name="password"
              type="password"
              autoComplete="current-password"
              required
              className={inputClass}
            />
          </Field>

          {state.error && (
            <p role="alert" className="rounded-wk-sm bg-red-50 px-3 py-2 text-[13px] text-wk-red">
              {state.error}
            </p>
          )}

          <Button type="submit" size="lg" className="w-full" disabled={pending}>
            {pending ? 'Connexion…' : 'Se connecter'}
          </Button>
        </form>

        <p className="mt-5 text-center text-[13.5px] text-wk-text2">
          Pas encore de compte ?{' '}
          <Link href="/inscription" className="font-semibold text-wk-primary hover:underline">
            Créer un compte
          </Link>
        </p>
      </Card>

      <details className="mx-auto mt-5 w-full max-w-md rounded-wk-md border border-wk-border bg-white p-4">
        <summary className="cursor-pointer text-[13px] font-semibold text-wk-text2">
          Comptes de démonstration
        </summary>
        <ul className="mt-3 space-y-1.5 font-mono text-[12px] text-wk-text2">
          <li>admin@wakabileguide.com — administration</li>
          <li>partenaire@chezlena.tg — espace partenaire</li>
          <li>kossi@exemple.tg — compte utilisateur</li>
          <li className="pt-1 text-wk-text3">mot de passe : wakabi2026</li>
        </ul>
      </details>
    </main>
  );
}
