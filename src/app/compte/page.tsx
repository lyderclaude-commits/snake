import { redirect } from 'next/navigation';
import Link from 'next/link';
import { currentUser } from '@/server/auth';
import { listForUser } from '@/server/repo/creations';
import { koriBalance, koriHistory } from '@/server/repo/badges';
import { AppShell, USER_NAV } from '@/components/site/AppShell';
import { Card, LinkButton, Stat } from '@/components/ui';

export const dynamic = 'force-dynamic';

export default async function ComptePage() {
  const me = await currentUser();
  if (!me) redirect('/connexion');
  if (me.role === 'partner') redirect('/partenaire');
  if (me.role === 'admin') redirect('/admin');

  const creations = listForUser(me.id);
  const koris = koriBalance(me.id);
  const history = koriHistory(me.id, 6);

  return (
    <AppShell
      me={me}
      nav={USER_NAV}
      title={`Bonjour ${me.name.split(' ')[0]}`}
      subtitle="Retrouve ici les décors que tu as utilisés."
      action={<LinkButton href="/decors">Créer un visuel</LinkButton>}
    >
      <div className="mb-6 grid gap-3 sm:grid-cols-3">
        <Stat value={creations.length} label="visuels créés" tone="primary" />
        <Stat value={`${koris} ₵`} label="Koris gagnés" tone="gold" />
        <Stat
          value={creations[0] ? new Date(creations[0].created_at).toLocaleDateString('fr-FR') : '—'}
          label="dernière création"
        />
      </div>

      <Card className="mb-6 p-5">
        <div className="flex flex-wrap items-baseline justify-between gap-2">
          <h2 className="font-display text-[16px] font-bold">Mes Koris</h2>
          <span className="font-display text-[24px] font-extrabold text-wk-gold tabular-nums">
            {koris} ₵
          </span>
        </div>
        {history.length === 0 ? (
          <p className="mt-2 text-[13.5px] leading-relaxed text-wk-text2">
            Tu gagnes <b className="text-wk-text">50 Koris</b> à chaque fois que ton badge est
            scanné à l’entrée d’un événement. Télécharge un visuel, présente son QR sur place :
            c’est tout.
          </p>
        ) : (
          <ul className="mt-3 space-y-2">
            {history.map((h) => (
              <li key={h.id} className="flex items-baseline justify-between gap-3 text-[13.5px]">
                <span className="min-w-0 flex-1 truncate text-wk-text2">{h.reason}</span>
                <span className="shrink-0 font-semibold text-wk-green">+{h.amount} ₵</span>
                <span className="shrink-0 text-[11.5px] text-wk-text3">
                  {new Date(h.created_at).toLocaleDateString('fr-FR')}
                </span>
              </li>
            ))}
          </ul>
        )}
      </Card>

      {creations.length === 0 ? (
        <Card className="p-10 text-center">
          <p className="font-display text-[17px] font-bold">Aucune création pour l’instant</p>
          <p className="mx-auto mt-2 max-w-[42ch] text-[14px] text-wk-text2">
            Choisis un décor, ajoute ta photo et télécharge. Ça prend 30 secondes, et ta photo ne
            quitte jamais ton téléphone.
          </p>
          <LinkButton href="/decors" className="mt-5">
            Voir les décors
          </LinkButton>
        </Card>
      ) : (
        <ul className="grid gap-3 sm:grid-cols-2">
          {creations.map((c) => (
            <li key={c.id}>
              <Link
                href={`/decor/${c.slug}`}
                className="flex items-center gap-4 rounded-wk-lg border border-wk-border bg-white p-3 transition hover:border-wk-border2"
              >
                <span className="size-14 shrink-0 overflow-hidden rounded-wk-md bg-wk-surface2">
                  {c.frame_url && (
                    /* eslint-disable-next-line @next/next/no-img-element */
                    <img src={c.frame_url} alt="" className="size-full object-cover" />
                  )}
                </span>
                <span className="min-w-0 flex-1">
                  <b className="block truncate font-display text-[14.5px] font-bold">{c.title}</b>
                  <span className="text-[12.5px] text-wk-text3">
                    {new Date(c.created_at).toLocaleString('fr-FR', {
                      dateStyle: 'medium',
                      timeStyle: 'short',
                    })}
                  </span>
                </span>
                <span className="shrink-0 text-[13px] font-bold text-wk-primary">Refaire →</span>
              </Link>
            </li>
          ))}
        </ul>
      )}

      <Card className="mt-8 p-5">
        <p className="text-[13.5px] leading-relaxed text-wk-text2">
          <b className="text-wk-text">Ce qui est enregistré ici :</b> uniquement le décor que tu as
          utilisé et la date. <b className="text-wk-text">Pas ta photo</b> — elle est traitée dans
          ton navigateur et n’est jamais envoyée sur nos serveurs.
        </p>
      </Card>
    </AppShell>
  );
}
