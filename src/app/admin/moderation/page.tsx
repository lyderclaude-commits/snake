import { redirect } from 'next/navigation';
import { currentUser } from '@/server/auth';
import { listPending, parseTemplate } from '@/server/repo/decors';
import { AppShell, ADMIN_NAV } from '@/components/site/AppShell';
import { Card } from '@/components/ui';
import { ReviewPanel } from '@/components/admin/ReviewPanel';

export const dynamic = 'force-dynamic';

export default async function ModerationPage() {
  const me = await currentUser();
  if (!me) redirect('/connexion');
  if (me.role !== 'admin') redirect(me.role === 'partner' ? '/partenaire' : '/compte');

  const pending = listPending();

  return (
    <AppShell
      me={me}
      nav={ADMIN_NAV}
      title="Relecture"
      subtitle="Ce que la machine ne peut pas juger : les droits, le ton, la pertinence."
    >
      {pending.length === 0 ? (
        <Card className="p-10 text-center">
          <p className="font-display text-[17px] font-bold">La file est vide 🎉</p>
          <p className="mx-auto mt-2 max-w-[42ch] text-[14px] text-wk-text2">
            Aucun décor n’attend de relecture. Les soumissions des partenaires apparaîtront ici.
          </p>
        </Card>
      ) : (
        <div className="space-y-5">
          {pending.map((d) => {
            const tpl = parseTemplate(d);
            return (
              <ReviewPanel
                key={d.id}
                id={d.id}
                title={d.title}
                subtitle={d.subtitle}
                author={d.author_name ?? '—'}
                slug={d.slug}
                frameUrl={d.frame_url}
                submittedAt={d.submitted_at}
                ratio={
                  tpl ? `${tpl.canvas.width} / ${tpl.canvas.height}` : '1 / 1'
                }
                redirectUrl={tpl?.share.redirectUrl ?? null}
                expiresAt={d.expires_at}
                schemaOk={!!tpl}
              />
            );
          })}
        </div>
      )}
    </AppShell>
  );
}
