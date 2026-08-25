import { redirect } from 'next/navigation';
import { currentUser } from '@/server/auth';
import { recentScans } from '@/server/repo/badges';
import { AppShell, ADMIN_NAV, PARTNER_NAV } from '@/components/site/AppShell';
import { Card } from '@/components/ui';
import { ScanForm } from '@/components/admin/ScanForm';

export const dynamic = 'force-dynamic';

export default async function ScanPage() {
  const me = await currentUser();
  if (!me) redirect('/connexion');
  if (me.role === 'user') redirect('/compte');

  const scans = recentScans();

  return (
    <AppShell
      me={me}
      nav={me.role === 'admin' ? ADMIN_NAV : PARTNER_NAV}
      title="Contrôle d’entrée"
      subtitle="Scannez le QR du badge, ou saisissez son code."
    >
      <div className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-start">
        <ScanForm />

        <Card className="p-5">
          <h2 className="mb-3 font-display text-[15px] font-bold">Derniers passages</h2>
          {scans.length === 0 ? (
            <p className="text-[13px] text-wk-text3">Aucune entrée validée pour l’instant.</p>
          ) : (
            <ul className="space-y-2.5">
              {scans.map((s) => (
                <li key={s.token} className="flex items-baseline gap-2 text-[13px]">
                  <span className="font-mono text-[11.5px] text-wk-text3">
                    {new Date(s.scanned_at).toLocaleTimeString('fr-FR', {
                      hour: '2-digit',
                      minute: '2-digit',
                    })}
                  </span>
                  <span className="min-w-0 flex-1">
                    <b className="block truncate font-semibold">{s.who ?? 'Badge anonyme'}</b>
                    <span className="block truncate text-[12px] text-wk-text3">{s.decor}</span>
                  </span>
                </li>
              ))}
            </ul>
          )}
        </Card>
      </div>
    </AppShell>
  );
}
