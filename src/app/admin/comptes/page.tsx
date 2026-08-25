import { redirect } from 'next/navigation';
import { currentUser } from '@/server/auth';
import { listUsers } from '@/server/repo/users';
import { AppShell, ADMIN_NAV } from '@/components/site/AppShell';
import { Card, Stat } from '@/components/ui';
import { changeRole, toggleSuspend } from '@/app/actions/users';

export const dynamic = 'force-dynamic';

const ROLES: [string, string][] = [
  ['user', 'Participant'],
  ['partner', 'Partenaire'],
  ['admin', 'Administration'],
];

export default async function ComptesPage() {
  const me = await currentUser();
  if (!me) redirect('/connexion');
  if (me.role !== 'admin') redirect(me.role === 'partner' ? '/partenaire' : '/compte');

  const users = listUsers();
  const n = (r: string) => users.filter((u) => u.role === r).length;

  return (
    <AppShell me={me} nav={ADMIN_NAV} title="Comptes" subtitle={`${users.length} inscrits.`}>
      <div className="mb-6 grid gap-3 sm:grid-cols-3">
        <Stat value={n('user')} label="participants" />
        <Stat value={n('partner')} label="partenaires" tone="primary" />
        <Stat value={n('admin')} label="administrateurs" tone="gold" />
      </div>

      <Card className="overflow-x-auto">
        <table className="w-full min-w-[720px] border-collapse text-[13.5px]">
          <thead>
            <tr className="border-b border-wk-border bg-wk-surface2 text-left">
              {['Compte', 'Structure', 'Rôle', 'État', ''].map((h) => (
                <th
                  key={h}
                  className="px-4 py-2.5 text-[11.5px] font-bold uppercase tracking-wide text-wk-text3"
                >
                  {h}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {users.map((u) => (
              <tr key={u.id} className="border-b border-wk-border last:border-0">
                <td className="px-4 py-3">
                  <b className="font-display font-bold">{u.name}</b>
                  <span className="block text-[12px] text-wk-text3">{u.email}</span>
                </td>
                <td className="px-4 py-3 text-wk-text2">
                  {u.organisation ?? '—'}
                  {u.city && <span className="block text-[11.5px] text-wk-text3">{u.city}</span>}
                </td>
                <td className="px-4 py-3">
                  {u.id === me.id ? (
                    <span className="text-[12.5px] font-semibold text-wk-text3">
                      Vous — non modifiable
                    </span>
                  ) : (
                    <form action={changeRole} className="flex items-center gap-2">
                      <input type="hidden" name="id" value={u.id} />
                      <select
                        name="role"
                        defaultValue={u.role}
                        className="rounded-wk-sm border border-wk-border bg-white px-2 py-1 text-[12.5px]"
                      >
                        {ROLES.map(([v, l]) => (
                          <option key={v} value={v}>
                            {l}
                          </option>
                        ))}
                      </select>
                      <button
                        type="submit"
                        className="rounded-wk-sm border border-wk-border px-2 py-1 text-[12px] font-semibold text-wk-text2 hover:border-wk-border2"
                      >
                        OK
                      </button>
                    </form>
                  )}
                </td>
                <td className="px-4 py-3">
                  {u.suspended ? (
                    <span className="rounded-wk-sm bg-red-50 px-2 py-0.5 text-[11px] font-bold text-wk-red">
                      Suspendu
                    </span>
                  ) : (
                    <span className="rounded-wk-sm bg-green-50 px-2 py-0.5 text-[11px] font-bold text-wk-green">
                      Actif
                    </span>
                  )}
                </td>
                <td className="px-4 py-3 text-right">
                  {u.id !== me.id && (
                    <form action={toggleSuspend}>
                      <input type="hidden" name="id" value={u.id} />
                      <input type="hidden" name="suspended" value={u.suspended ? '0' : '1'} />
                      <button
                        type="submit"
                        className="rounded-wk-sm border border-wk-border px-3 py-1 text-[12px] font-semibold text-wk-text2 transition hover:border-wk-red hover:text-wk-red"
                      >
                        {u.suspended ? 'Réactiver' : 'Suspendre'}
                      </button>
                    </form>
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>

      <p className="mt-4 text-[12.5px] leading-relaxed text-wk-text3">
        Suspendre un compte coupe immédiatement ses sessions ouvertes. Un administrateur ne peut
        ni se rétrograder ni se suspendre lui-même — sans quoi l’installation pourrait se
        retrouver sans administrateur.
      </p>
    </AppShell>
  );
}
