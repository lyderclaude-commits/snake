import { redirect } from 'next/navigation';
import Link from 'next/link';
import { currentUser } from '@/server/auth';
import { overview, dailyDownloads } from '@/server/repo/stats';
import { listAll, listPending } from '@/server/repo/decors';
import { recentScans } from '@/server/repo/badges';
import { db } from '@/server/db';
import { AppShell, ADMIN_NAV } from '@/components/site/AppShell';
import { Card, LinkButton, Stat, StatusPill, Icon } from '@/components/ui';

export const dynamic = 'force-dynamic';

export default async function AdminPage() {
  const me = await currentUser();
  if (!me) redirect('/connexion');
  if (me.role !== 'admin') redirect(me.role === 'partner' ? '/partenaire' : '/compte');

  const s = overview();
  const pending = listPending();
  const recent = listAll().slice(0, 8);
  const daily = dailyDownloads(14);
  const badgeStats = db()
    .prepare(
      "SELECT COUNT(*) AS issued, SUM(CASE WHEN scanned_at IS NOT NULL THEN 1 ELSE 0 END) AS scanned FROM badges",
    )
    .get() as { issued: number; scanned: number | null };
  const scanned = badgeStats.scanned ?? 0;
  const lastScans = recentScans(5);
  const peak = Math.max(1, ...daily.map((d) => d.n));

  return (
    <AppShell
      me={me}
      nav={ADMIN_NAV}
      title="Tableau de bord"
      subtitle="Vue d’ensemble de Wakabi Boost."
      action={<LinkButton href="/partenaire/nouveau">Nouveau décor</LinkButton>}
    >
      {pending.length > 0 && (
        <Link
          href="/admin/moderation"
          className="mb-6 flex items-center gap-3 rounded-wk-lg border-2 border-wk-gold bg-orange-50 p-4 transition hover:bg-orange-100"
        >
          <Icon name="clock" className="size-5 shrink-0 text-wk-gold" />
          <span className="flex-1 text-[14px]">
            <b className="font-display">
              {pending.length} décor{pending.length > 1 ? 's' : ''} en attente de relecture
            </b>
            <span className="block text-[13px] text-wk-text2">
              Engagement : réponse sous 24 h ouvrées.
            </span>
          </span>
          <span className="shrink-0 font-display text-[13.5px] font-bold text-wk-gold">
            Traiter →
          </span>
        </Link>
      )}

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Stat value={s.decorsPublished} label="décors publiés" tone="primary" />
        <Stat value={s.downloads} label="badges téléchargés" tone="green" />
        <Stat value={s.views} label="vues du catalogue" />
        <Stat value={scanned} label="présences scannées" tone="gold" />
      </div>

      <Card className="mt-4 flex flex-wrap items-center gap-4 p-4">
        <span className="text-[22px]">🪙</span>
        <p className="min-w-[240px] flex-1 text-[13.5px] leading-relaxed text-wk-text2">
          <b className="text-wk-text">Présence réelle :</b> {scanned} scannés sur{' '}
          {badgeStats.issued} badges émis
          {badgeStats.issued > 0 && (
            <> — soit {Math.round((scanned / badgeStats.issued) * 100)} %</>
          )}
          .{' '}
          {lastScans.length > 0 && (
            <>Dernier passage : {lastScans[0].who ?? 'anonyme'} · {lastScans[0].decor}.</>
          )}
        </p>
        <span className="text-[13px] font-semibold text-wk-text3">
          {s.partners} partenaire{s.partners > 1 ? 's' : ''}
        </span>
      </Card>

      <Card className="mt-6 p-5">
        <div className="mb-4 flex items-baseline justify-between">
          <h2 className="font-display text-[16px] font-bold">Téléchargements — 14 derniers jours</h2>
          <span className="text-[12.5px] text-wk-text3">{s.downloads} au total</span>
        </div>
        {s.downloads === 0 ? (
          <p className="py-6 text-center text-[13.5px] text-wk-text3">Aucun téléchargement encore.</p>
        ) : (
          <div className="flex h-32 items-end gap-1.5">
            {daily.map((d) => (
              <div key={d.day} className="group relative flex-1">
                <div
                  className={`rounded-t-sm transition ${
                    d.n ? 'bg-wk-primary group-hover:bg-wk-primary-d' : 'bg-wk-border'
                  }`}
                  style={{ height: `${d.n ? Math.max(6, (d.n / peak) * 118) : 3}px` }}
                />
                <span className="pointer-events-none absolute -top-7 left-1/2 hidden -translate-x-1/2 whitespace-nowrap rounded bg-wk-text px-2 py-0.5 text-[11px] text-white group-hover:block">
                  {d.n} · {d.day.slice(5)}
                </span>
              </div>
            ))}
          </div>
        )}
      </Card>

      <h2 className="mb-3 mt-8 font-display text-[16px] font-bold">Décors récents</h2>
      <Card className="overflow-x-auto">
        <table className="w-full min-w-[560px] border-collapse text-[13.5px]">
          <thead>
            <tr className="border-b border-wk-border bg-wk-surface2 text-left">
              {['Décor', 'Auteur', 'Statut', '⬇', '👁'].map((h) => (
                <th key={h} className="px-4 py-2.5 text-[11.5px] font-bold uppercase tracking-wide text-wk-text3">
                  {h}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {recent.map((d) => (
              <tr key={d.id} className="border-b border-wk-border last:border-0">
                <td className="px-4 py-3">
                  <b className="font-display font-bold">{d.title}</b>
                  <span className="block text-[12px] text-wk-text3">/{d.slug}</span>
                </td>
                <td className="px-4 py-3 text-wk-text2">
                  {d.author_name ?? '—'}
                  <span className="block text-[11.5px] text-wk-text3">
                    {d.created_by === 'partner' ? 'Partenaire' : 'Équipe'}
                  </span>
                </td>
                <td className="px-4 py-3"><StatusPill status={d.status} /></td>
                <td className="px-4 py-3 tabular-nums text-wk-text2">{d.downloads}</td>
                <td className="px-4 py-3 tabular-nums text-wk-text2">{d.views}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>
    </AppShell>
  );
}
