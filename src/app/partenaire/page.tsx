import { redirect } from 'next/navigation';
import Link from 'next/link';
import { currentUser } from '@/server/auth';
import { listByAuthor } from '@/server/repo/decors';
import { AppShell, PARTNER_NAV } from '@/components/site/AppShell';
import { Card, LinkButton, Stat, StatusPill, Icon } from '@/components/ui';
import { SubmitButton } from '@/components/partner/SubmitButton';

export const dynamic = 'force-dynamic';

export default async function PartenairePage() {
  const me = await currentUser();
  if (!me) redirect('/connexion');
  if (me.role === 'user') redirect('/compte');

  const mine = listByAuthor(me.id);
  const published = mine.filter((d) => d.status === 'published');
  const downloads = mine.reduce((a, d) => a + d.downloads, 0);
  const views = mine.reduce((a, d) => a + d.views, 0);

  return (
    <AppShell
      me={me}
      nav={PARTNER_NAV}
      title="Mes campagnes"
      subtitle={me.organisation ?? undefined}
      action={<LinkButton href="/partenaire/nouveau">Nouveau décor</LinkButton>}
    >
      <div className="mb-7 grid gap-3 sm:grid-cols-4">
        <Stat value={published.length} label="campagnes publiées" tone="primary" />
        <Stat value={downloads} label="badges téléchargés" tone="green" />
        <Stat value={views} label="vues" />
        <Stat
          value={views ? `${Math.round((downloads / views) * 100)} %` : '—'}
          label="taux de conversion"
          tone="gold"
        />
      </div>

      {mine.length === 0 ? (
        <Card className="p-10 text-center">
          <p className="font-display text-[17px] font-bold">Aucun décor pour l’instant</p>
          <p className="mx-auto mt-2 max-w-[46ch] text-[14px] text-wk-text2">
            Créez votre premier décor : choisissez un gabarit, téléversez votre cadre, et
            soumettez-le à la relecture. Réponse sous 24 h ouvrées.
          </p>
          <LinkButton href="/partenaire/nouveau" className="mt-5">
            Créer mon premier décor
          </LinkButton>
        </Card>
      ) : (
        <ul className="space-y-3">
          {mine.map((d) => (
            <li key={d.id}>
              <Card className="flex flex-wrap items-center gap-4 p-4">
                <span className="size-16 shrink-0 overflow-hidden rounded-wk-md bg-wk-surface2">
                  {d.frame_url && (
                    /* eslint-disable-next-line @next/next/no-img-element */
                    <img src={d.frame_url} alt="" className="size-full object-cover" />
                  )}
                </span>

                <span className="min-w-[180px] flex-1">
                  <span className="flex flex-wrap items-center gap-2">
                    <b className="font-display text-[15px] font-bold">{d.title}</b>
                    <StatusPill status={d.status} />
                  </span>
                  {d.subtitle && (
                    <span className="mt-0.5 block text-[13px] text-wk-text3">{d.subtitle}</span>
                  )}
                  {d.review_note && (d.status === 'changes_requested' || d.status === 'rejected') && (
                    <span className="mt-2 block rounded-wk-sm bg-orange-50 px-2.5 py-1.5 text-[12.5px] leading-snug text-wk-text2">
                      <b className="text-wk-orange">Retour de l’équipe :</b> {d.review_note}
                    </span>
                  )}
                </span>

                <span className="flex items-center gap-4 text-[12.5px] font-semibold text-wk-text3">
                  <span className="inline-flex items-center gap-1">
                    <Icon name="download" className="size-3.5" /> {d.downloads}
                  </span>
                  <span className="inline-flex items-center gap-1">
                    <Icon name="eye" className="size-3.5" /> {d.views}
                  </span>
                </span>

                <span className="flex shrink-0 gap-2">
                  {d.status === 'published' && (
                    <Link
                      href={`/decor/${d.slug}`}
                      className="rounded-wk-sm border border-wk-border px-3 py-1.5 text-[12.5px] font-semibold text-wk-text2 transition hover:border-wk-border2"
                    >
                      Voir
                    </Link>
                  )}
                  {(d.status === 'draft' || d.status === 'changes_requested') && (
                    <SubmitButton id={d.id} />
                  )}
                </span>
              </Card>
            </li>
          ))}
        </ul>
      )}
    </AppShell>
  );
}
