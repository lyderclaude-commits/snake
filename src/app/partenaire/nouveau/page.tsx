import { redirect } from 'next/navigation';
import { currentUser } from '@/server/auth';
import { AppShell, PARTNER_NAV, ADMIN_NAV } from '@/components/site/AppShell';
import { NewDecorForm } from '@/components/partner/NewDecorForm';

export const dynamic = 'force-dynamic';

export default async function NouveauDecorPage() {
  const me = await currentUser();
  if (!me) redirect('/connexion');
  if (me.role === 'user') redirect('/compte');

  return (
    <AppShell
      me={me}
      nav={me.role === 'admin' ? ADMIN_NAV : PARTNER_NAV}
      title="Nouveau décor"
      subtitle="Choisissez un gabarit, téléversez votre cadre, décrivez la campagne."
    >
      <NewDecorForm isAdmin={me.role === 'admin'} />
    </AppShell>
  );
}
