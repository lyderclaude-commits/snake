'use client';

import { useActionState, useState } from 'react';
import { createDecor, uploadFrame, type DecorFormState } from '@/app/actions/decors';
import { Button, Card, Field, inputClass } from '@/components/ui';

const LAYOUTS = [
  { id: 'bandeau', label: 'Bandeau bas', hint: 'Carré · texte sur une bande en bas' },
  { id: 'angle', label: 'Coin & voile', hint: 'Carré · texte en bas à gauche' },
  { id: 'story', label: 'Story verticale', hint: '9:16 · pour le statut WhatsApp' },
];

export function NewDecorForm({ isAdmin }: { isAdmin: boolean }) {
  const [state, action, pending] = useActionState<DecorFormState, FormData>(createDecor, {});
  /** Restaure ce que le partenaire avait saisi : React vide le formulaire
   *  après une action, et perdre sa saisie sur une faute de frappe est le
   *  meilleur moyen de le faire abandonner. */
  const kept = (k: string, fallback = '') => state.values?.[k] ?? fallback;
  const [layout, setLayout] = useState('bandeau');
  const [preview, setPreview] = useState<string | null>(null);
  const [frameUrl, setFrameUrl] = useState('');
  const [upload, setUpload] = useState<{ busy: boolean; error?: string }>({ busy: false });

  // Après une erreur, le cadre déjà téléversé revient par l'état de l'action.
  const effectiveFrame = frameUrl || kept('frameUrl');

  async function onFrameChange(e: React.ChangeEvent<HTMLInputElement>) {
    const f = e.target.files?.[0];
    if (!f) return;
    setPreview(URL.createObjectURL(f));
    setUpload({ busy: true });
    const fd = new FormData();
    fd.set('frame', f);
    const res = await uploadFrame(fd);
    if (res.url) {
      setFrameUrl(res.url);
      setUpload({ busy: false });
    } else {
      setFrameUrl('');
      setPreview(null);
      setUpload({ busy: false, error: res.error });
    }
  }

  return (
    <form action={action} className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_300px] lg:items-start">
      <div className="space-y-5">
        {/* 1 — gabarit */}
        <Card className="p-5">
          <h2 className="mb-1 font-display text-[16px] font-bold">1 · Le gabarit</h2>
          <p className="mb-4 text-[13px] text-wk-text2">
            Les positions du texte et de la zone photo sont déjà réglées. Vous n’avez qu’à
            fournir le cadre.
          </p>
          <div className="grid gap-2 sm:grid-cols-3">
            {LAYOUTS.map((l) => (
              <button
                key={l.id}
                type="button"
                onClick={() => setLayout(l.id)}
                aria-pressed={layout === l.id}
                className={`rounded-wk-md border-2 p-3 text-left transition ${
                  layout === l.id
                    ? 'border-wk-primary bg-wk-primary-l'
                    : 'border-wk-border bg-white hover:border-wk-border2'
                }`}
              >
                <b className="block font-display text-[13.5px] font-bold">{l.label}</b>
                <span className="text-[11.5px] leading-snug text-wk-text3">{l.hint}</span>
              </button>
            ))}
          </div>
          <input type="hidden" name="layout" value={layout} />
        </Card>

        {/* 2 — cadre */}
        <Card className="p-5">
          <h2 className="mb-1 font-display text-[16px] font-bold">2 · Votre cadre</h2>
          <p className="mb-4 text-[13px] leading-relaxed text-wk-text2">
            PNG ou WebP avec <b>fond transparent</b>, 800 Ko maximum. La photo de l’invité
            apparaîtra derrière — laissez donc le centre vide.{' '}
            <span className="text-wk-text3">Le SVG est refusé pour raison de sécurité.</span>
          </p>
          <input
            name="frame"
            type="file"
            accept="image/png,image/webp"
            onChange={onFrameChange}
            className="block w-full text-[13.5px] file:mr-3 file:rounded-wk-sm file:border-0 file:bg-wk-primary file:px-4 file:py-2 file:font-display file:text-[13px] file:font-bold file:text-white hover:file:bg-wk-primary-d"
          />
          <input type="hidden" name="frameUrl" value={effectiveFrame} />
          {upload.busy && (
            <p className="mt-2 text-[12.5px] font-semibold text-wk-text2">Téléversement…</p>
          )}
          {effectiveFrame && !upload.busy && (
            <p className="mt-2 text-[12.5px] font-semibold text-wk-green">
              ✓ Cadre enregistré — il sera conservé même si le formulaire signale une erreur.
            </p>
          )}
          {upload.error && (
            <p role="alert" className="mt-2 text-[12.5px] font-semibold text-wk-red">
              {upload.error}
            </p>
          )}
        </Card>

        {/* 3 — campagne */}
        <Card className="space-y-4 p-5">
          <h2 className="font-display text-[16px] font-bold">3 · La campagne</h2>
          <Field label="Titre" hint="Ce que les invités verront dans le catalogue.">
            <input name="title" required maxLength={80} defaultValue={kept("title")} className={inputClass} placeholder="Soirée Garden Party" />
          </Field>
          <Field label="Sous-titre">
            <input name="subtitle" maxLength={160} defaultValue={kept("subtitle")} className={inputClass} placeholder="Samedi 12, HECM Cotonou" />
          </Field>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Ville">
              <select name="city" defaultValue={kept("city", "all")} className={inputClass}>
                <option value="all">Toutes</option>
                <option value="lome">Lomé</option>
                <option value="cotonou">Cotonou</option>
                <option value="abidjan">Abidjan</option>
              </select>
            </Field>
            <Field label="Rubrique">
              <select name="rubrique" defaultValue={kept("rubrique", "evenements")} className={inputClass}>
                <option value="evenements">Événements</option>
                <option value="gastronomie">Gastronomie</option>
                <option value="hebergements">Hébergements</option>
                <option value="culture">Culture</option>
                <option value="campagne">Campagne</option>
              </select>
            </Field>
          </div>
          <Field label="Expiration" hint="Un décor daté doit expirer, sinon il encombre le catalogue.">
            <input name="expiresAt" type="datetime-local" defaultValue={kept("expiresAt")} className={inputClass} />
          </Field>
        </Card>

        {/* 4 — textes */}
        <Card className="space-y-4 p-5">
          <h2 className="font-display text-[16px] font-bold">4 · Les textes du badge</h2>
          <Field label="Accroche" hint="Texte fixe, affiché sur tous les badges.">
            <input name="claim" required maxLength={40} defaultValue={kept("claim", "J'Y SERAI")} className={inputClass} />
          </Field>
          <div className="grid gap-4 sm:grid-cols-2">
            <Field label="Champ à remplir" hint="Ce que l’invité saisit.">
              <input name="fieldLabel" maxLength={40} defaultValue={kept("fieldLabel", "Ton prénom")} className={inputClass} />
            </Field>
            <Field label="Exemple affiché">
              <input name="fieldValue" maxLength={42} defaultValue={kept("fieldValue", "Kossi")} className={inputClass} />
            </Field>
          </div>
        </Card>

        {/* 5 — redirection */}
        <Card className="space-y-4 p-5">
          <h2 className="font-display text-[16px] font-bold">5 · Après le téléchargement</h2>
          <p className="text-[13px] leading-relaxed text-wk-text2">
            C’est le moment où l’attention de l’invité est totale. Le badge le renvoie vers votre
            page.
            {!isAdmin && (
              <>
                {' '}
                <b className="text-wk-text">
                  L’adresse doit être une page wakabileguide.com
                </b>{' '}
                — c’est ce qui garantit que le trafic revient dans l’écosystème.
              </>
            )}
          </p>
          <Field label="Adresse de redirection">
            <input
              name="redirectUrl"
              type="url"
              required
              defaultValue={kept('redirectUrl')}
              className={inputClass}
              placeholder="https://wakabileguide.com/p/mon-lieu"
            />
          </Field>
          <Field label="Texte du bouton">
            <input name="redirectLabel" maxLength={60} defaultValue={kept("redirectLabel", "Découvrir sur Wakabi")} className={inputClass} />
          </Field>
          <Field label="Légende de partage" hint="Préremplit le message WhatsApp.">
            <input name="caption" maxLength={280} defaultValue={kept("caption")} className={inputClass} placeholder="J'y serai ! On se retrouve là-bas 🎉" />
          </Field>
        </Card>

        {state.error && (
          <p role="alert" className="rounded-wk-md bg-red-50 px-4 py-3 text-[13.5px] text-wk-red">
            {state.error}
          </p>
        )}

        <Button type="submit" size="lg" disabled={pending || upload.busy || !effectiveFrame}>
          {pending ? 'Création…' : isAdmin ? 'Créer le décor' : 'Créer et enregistrer en brouillon'}
        </Button>
      </div>

      {/* colonne d'aide */}
      <aside className="space-y-4 lg:sticky lg:top-6">
        <Card className="p-4">
          <p className="mb-2 font-display text-[14px] font-bold">Aperçu du cadre</p>
          <div
            className="overflow-hidden rounded-wk-md bg-wk-surface2"
            style={{ aspectRatio: layout === 'story' ? '9 / 16' : '1 / 1' }}
          >
            {preview || effectiveFrame ? (
              /* eslint-disable-next-line @next/next/no-img-element */
              <img src={preview ?? effectiveFrame} alt="" className="size-full object-contain" />
            ) : (
              <p className="grid size-full place-items-center px-4 text-center text-[12.5px] text-wk-text3">
                Téléversez votre cadre pour le voir ici
              </p>
            )}
          </div>
        </Card>

        <Card className="p-4">
          <p className="mb-2 font-display text-[14px] font-bold">Ce que la relecture vérifie</p>
          <ul className="space-y-1.5 text-[12.5px] leading-snug text-wk-text2">
            {[
              'La zone photo reste visible',
              'Les textes tiennent dans le cadre',
              'Le filigrane n’est pas recouvert',
              'Vous avez les droits sur le visuel',
              'La date correspond à un vrai événement',
            ].map((t) => (
              <li key={t} className="flex gap-2">
                <span className="text-wk-green">✓</span>
                {t}
              </li>
            ))}
          </ul>
          <p className="mt-3 text-[12px] text-wk-text3">Réponse sous 24 h ouvrées.</p>
        </Card>
      </aside>
    </form>
  );
}
