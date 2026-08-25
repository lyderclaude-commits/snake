import Link from 'next/link';
import { signOut } from '@/app/actions/auth';
import { WakabiMark } from '@/components/WakabiMark';
import type { SessionUser } from '@/server/auth';

/** Coquille des espaces connectés : barre latérale + en-tête. */
export function AppShell({
  me,
  nav,
  title,
  subtitle,
  action,
  children,
}: {
  me: SessionUser;
  nav: { href: string; label: string }[];
  title: string;
  subtitle?: string;
  action?: React.ReactNode;
  children: React.ReactNode;
}) {
  return (
    <div className="min-h-dvh bg-wk-bg2">
      <header className="border-b border-wk-border bg-white">
        <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-3">
          <Link href="/">
            <WakabiMark tagline={false} />
          </Link>
          <div className="flex items-center gap-3">
            <span className="hidden text-right sm:block">
              <b className="block text-[13px] font-semibold leading-tight">{me.name}</b>
              <span className="text-[11.5px] text-wk-text3">
                {me.organisation ?? ROLE_LABEL[me.role]}
              </span>
            </span>
            <form action={signOut}>
              <button
                type="submit"
                className="rounded-wk-sm border border-wk-border bg-white px-3 py-1.5 text-[12.5px] font-semibold text-wk-text2 transition hover:border-wk-border2"
              >
                Déconnexion
              </button>
            </form>
          </div>
        </div>
      </header>

      <div className="mx-auto max-w-6xl gap-8 px-5 py-8 lg:grid lg:grid-cols-[190px_minmax(0,1fr)]">
        <nav className="mb-6 flex gap-1.5 overflow-x-auto lg:mb-0 lg:flex-col">
          {nav.map((n) => (
            <Link
              key={n.href}
              href={n.href}
              className="shrink-0 rounded-wk-sm px-3 py-2 text-[13.5px] font-semibold text-wk-text2 transition hover:bg-white hover:text-wk-primary"
            >
              {n.label}
            </Link>
          ))}
        </nav>

        <main className="min-w-0">
          <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
            <div>
              <h1 className="font-display text-[clamp(1.5rem,4vw,2rem)] font-extrabold leading-tight tracking-[-0.03em]">
                {title}
              </h1>
              {subtitle && <p className="mt-1 text-[15px] text-wk-text2">{subtitle}</p>}
            </div>
            {action}
          </div>
          {children}
        </main>
      </div>
    </div>
  );
}

const ROLE_LABEL: Record<string, string> = {
  user: 'Compte participant',
  partner: 'Espace partenaire',
  admin: 'Administration',
};

export const USER_NAV = [
  { href: '/compte', label: 'Mes créations' },
  { href: '/decors', label: 'Tous les décors' },
];

export const PARTNER_NAV = [
  { href: '/partenaire', label: 'Mes campagnes' },
  { href: '/partenaire/nouveau', label: 'Nouveau décor' },
  { href: '/decors', label: 'Catalogue public' },
];

export const ADMIN_NAV = [
  { href: '/admin', label: 'Tableau de bord' },
  { href: '/admin/moderation', label: 'Relecture' },
  { href: '/admin/comptes', label: 'Comptes' },
  { href: '/decors', label: 'Catalogue public' },
];
