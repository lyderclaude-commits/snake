'use client';

import { useActionState, useState } from 'react';
import Link from 'next/link';
import { signUp, type FormState } from '@/app/actions/auth';
import { Button, Card, Field, inputClass } from '@/components/ui';
import { WakabiMark } from '@/components/WakabiMark';

export default function InscriptionPage() {
  const [state, action, pending] = useActionState<FormState, FormData>(signUp, {});
  const [partner, setPartner] = useState(false);

  return (
    <main className="mx-auto flex min-h-dvh max-w-md flex-col justify-center px-5 py-12">
      <Link href="/" className="mx-auto mb-8">
        <WakabiMark />
      </Link>

      <Card className="p-6 shadow-wk">
        <h1 className="font-display text-[24px] font-extrabold tracking-tight">Créer un compte</h1>
        <p className="mt-1 text-[14px] text-wk-text2">
          Gratuit. Créer un badge ne demande aucun compte — celui-ci sert à retrouver vos
          créations, ou à lancer vos propres campagnes.
        </p>

        <form action={action} className="mt-6 space-y-4">
          {/* Choix du type de compte */}
          <div className="grid grid-cols-2 gap-2">
            {[
              [false, 'Je participe', 'Retrouver mes créations'],
              [true, 'J’organise', 'Créer mes campagnes'],
            ].map(([isP, label, hint]) => (
              <button
                key={String(isP)}
                type="button"
                onClick={() => setPartner(isP as boolean)}
                aria-pressed={partner === isP}
                className={`rounded-wk-md border-2 p-3 text-left transition ${
                  partner === isP
                    ? 'border-wk-primary bg-wk-primary-l'
                    : 'border-wk-border bg-white hover:border-wk-border2'
                }`}
              >
                <b className="block font-display text-[14px] font-bold">{label as string}</b>
                <span className="text-[11.5px] text-wk-text3">{hint as string}</span>
              </button>
            ))}
          </div>
          <input type="hidden" name="role" value={partner ? 'partner' : 'user'} />

          <Field label="Nom">
            <input name="name" type="text" autoComplete="name" required className={inputClass} />
          </Field>

          {partner && (
            <Field label="Structure" hint="Le nom qui apparaîtra sur vos campagnes.">
              <input name="organisation" type="text" required className={inputClass} />
            </Field>
          )}

          <Field label="Ville">
            <select name="city" defaultValue="" className={inputClass}>
              <option value="">—</option>
              <option value="lome">Lomé</option>
              <option value="cotonou">Cotonou</option>
              <option value="abidjan">Abidjan</option>
              <option value="dakar">Dakar</option>
            </select>
          </Field>

          <Field label="E-mail">
            <input name="email" type="email" autoComplete="email" required className={inputClass} />
          </Field>
          <Field label="Mot de passe" hint="8 caractères minimum.">
            <input
              name="password"
              type="password"
              autoComplete="new-password"
              required
              minLength={8}
              className={inputClass}
            />
          </Field>

          {state.error && (
            <p role="alert" className="rounded-wk-sm bg-red-50 px-3 py-2 text-[13px] text-wk-red">
              {state.error}
            </p>
          )}

          <Button type="submit" size="lg" className="w-full" disabled={pending}>
            {pending ? 'Création…' : 'Créer mon compte'}
          </Button>
        </form>

        <p className="mt-5 text-center text-[13.5px] text-wk-text2">
          Déjà inscrit ?{' '}
          <Link href="/connexion" className="font-semibold text-wk-primary hover:underline">
            Se connecter
          </Link>
        </p>
      </Card>
    </main>
  );
}
