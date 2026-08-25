'use client';

import { useState } from 'react';
import { reviewDecor } from '@/app/actions/decors';
import { Card, Icon } from '@/components/ui';

/**
 * Métabox de relecture : trois décisions, motif obligatoire pour deux d'entre
 * elles. Le contrôle automatique est affiché à côté, pour que le relecteur ne
 * juge que ce qu'une machine ne peut pas juger.
 */
export function ReviewPanel(props: {
  id: string;
  title: string;
  subtitle: string | null;
  author: string;
  slug: string;
  frameUrl: string | null;
  submittedAt: string | null;
  ratio: string;
  redirectUrl: string | null;
  expiresAt: string | null;
  schemaOk: boolean;
  preflight: { passed: boolean; ranAt: string; checks: { id: string; status: string; message: string }[] } | null;
}) {
  const [mode, setMode] = useState<'none' | 'changes' | 'reject'>('none');

  const pf = props.preflight;

  return (
    <Card className="overflow-hidden">
      <div className="grid gap-5 p-5 md:grid-cols-[150px_minmax(0,1fr)]">
        <div
          className="overflow-hidden rounded-wk-md bg-wk-surface2"
          style={{ aspectRatio: props.ratio }}
        >
          {props.frameUrl && (
            /* eslint-disable-next-line @next/next/no-img-element */
            <img src={props.frameUrl} alt="" className="size-full object-cover" />
          )}
        </div>

        <div className="min-w-0">
          <h2 className="font-display text-[17px] font-bold leading-tight">{props.title}</h2>
          {props.subtitle && (
            <p className="mt-0.5 text-[13.5px] text-wk-text2">{props.subtitle}</p>
          )}
          <p className="mt-1.5 text-[12.5px] text-wk-text3">
            Par <b className="text-wk-text2">{props.author}</b>
            {props.submittedAt &&
              ` · soumis le ${new Date(props.submittedAt).toLocaleString('fr-FR', {
                dateStyle: 'medium',
                timeStyle: 'short',
              })}`}
          </p>

          {/* Rapport du contrôle automatique — sept points, exécutés côté
              serveur à la soumission. Le relecteur n'a plus à les refaire. */}
          <div className="mt-4 rounded-wk-md border border-wk-border bg-wk-bg2 p-3">
            <p className="mb-2 flex items-center gap-2 text-[12px] font-bold uppercase tracking-wide text-wk-text3">
              Contrôle automatique
              {pf && (
                <span
                  className={`rounded-wk-sm px-2 py-0.5 text-[10.5px] ${
                    pf.passed ? 'bg-green-50 text-wk-green' : 'bg-red-50 text-wk-red'
                  }`}
                >
                  {pf.passed ? '7/7 franchis' : 'bloquant'}
                </span>
              )}
            </p>
            {pf ? (
              <ul className="grid gap-1.5 sm:grid-cols-2">
                {pf.checks.map((c) => (
                  <li key={c.id} className="flex items-start gap-2 text-[12.5px]">
                    <span
                      className={
                        c.status === 'pass'
                          ? 'text-wk-green'
                          : c.status === 'warn'
                            ? 'text-wk-gold'
                            : 'text-wk-red'
                      }
                    >
                      {c.status === 'pass' ? '✓' : c.status === 'warn' ? '⚠' : '✗'}
                    </span>
                    <span className="min-w-0">
                      <b className="font-mono text-[11.5px] font-semibold">{c.id}</b>
                      <span className="block text-wk-text2">{c.message}</span>
                    </span>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="text-[12.5px] text-wk-text3">
                Aucun rapport — ce décor a été soumis avant la mise en place du pré-vol.
              </p>
            )}
          </div>

          <p className="mt-4 rounded-wk-sm bg-wk-bg2 px-3 py-2 text-[12.5px] leading-snug text-wk-text2">
            <b className="text-wk-text">À vérifier vous-même :</b> les droits sur le visuel, le ton,
            le caractère approprié du contenu, et que l’événement existe bien.
          </p>

          {/* décisions */}
          <div className="mt-4 flex flex-wrap gap-2">
            <form action={reviewDecor}>
              <input type="hidden" name="id" value={props.id} />
              <input type="hidden" name="to" value="published" />
              <button
                type="submit"
                className="inline-flex items-center gap-1.5 rounded-wk-md bg-wk-green px-4 py-2 font-display text-[13.5px] font-bold text-white transition hover:opacity-90"
              >
                <Icon name="check" className="size-4" /> Approuver et publier
              </button>
            </form>
            <button
              type="button"
              onClick={() => setMode(mode === 'changes' ? 'none' : 'changes')}
              className="rounded-wk-md border border-wk-border bg-white px-4 py-2 font-display text-[13.5px] font-bold text-wk-text2 transition hover:border-wk-border2"
            >
              Demander des corrections
            </button>
            <button
              type="button"
              onClick={() => setMode(mode === 'reject' ? 'none' : 'reject')}
              className="rounded-wk-md border border-wk-border bg-white px-4 py-2 font-display text-[13.5px] font-bold text-wk-red transition hover:border-wk-red"
            >
              Refuser
            </button>
          </div>

          {mode !== 'none' && (
            <form action={reviewDecor} className="mt-3">
              <input type="hidden" name="id" value={props.id} />
              <input
                type="hidden"
                name="to"
                value={mode === 'changes' ? 'changes_requested' : 'rejected'}
              />
              <label className="block text-[12.5px] font-semibold text-wk-text2">
                Motif — obligatoire, il est envoyé au partenaire
              </label>
              <textarea
                name="note"
                required
                rows={3}
                maxLength={600}
                placeholder={
                  mode === 'changes'
                    ? 'Ex. : le cadre recouvre la zone photo, l’invité n’apparaîtra pas.'
                    : 'Ex. : le visuel n’est pas libre de droits.'
                }
                className="mt-1.5 w-full rounded-wk-sm border border-wk-border bg-wk-bg2 px-3 py-2 text-[14px] outline-none focus:border-wk-primary focus:bg-white"
              />
              <button
                type="submit"
                className={`mt-2 rounded-wk-md px-4 py-2 font-display text-[13.5px] font-bold text-white transition hover:opacity-90 ${
                  mode === 'changes' ? 'bg-wk-orange' : 'bg-wk-red'
                }`}
              >
                {mode === 'changes' ? 'Renvoyer pour correction' : 'Confirmer le refus'}
              </button>
            </form>
          )}
        </div>
      </div>
    </Card>
  );
}
