import Link from 'next/link';
import { currentUser } from '@/server/auth';
import { WakabiMark } from '@/components/WakabiMark';
import { LinkButton } from '@/components/ui';

const SPACE: Record<string, { href: string; label: string }> = {
  user: { href: '/compte', label: 'Mon compte' },
  partner: { href: '/partenaire', label: 'Espace partenaire' },
  admin: { href: '/admin', label: 'Administration' },
};

export async function Nav({ variant = 'public' }: { variant?: 'public' | 'app' }) {
  const me = await currentUser();
  const space = me ? SPACE[me.role] : null;

  return (
    <header className="sticky top-0 z-40 border-b border-wk-border/70 bg-white/90 backdrop-blur">
      <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-3">
        <Link href="/" aria-label="Accueil Wakabi Studio">
          <WakabiMark tagline={variant === 'public'} />
        </Link>

        {variant === 'public' && (
          <nav className="hidden items-center gap-1 md:flex">
            {[
              ['/decors', 'Décors'],
              ['/#etapes', 'Comment ça marche'],
              ['/#partenaires', 'Partenaires'],
            ].map(([href, label]) => (
              <Link
                key={href}
                href={href}
                className="rounded-wk-sm px-3 py-1.5 text-[14px] font-medium text-wk-text2 transition hover:bg-wk-primary-l hover:text-wk-primary"
              >
                {label}
              </Link>
            ))}
          </nav>
        )}

        <div className="flex items-center gap-2">
          {space && (
            <LinkButton href={space.href} variant="ghost" size="sm">
              {space.label}
            </LinkButton>
          )}
          {!me && (
            <LinkButton href="/connexion" variant="ghost" size="sm">
              Se connecter
            </LinkButton>
          )}
          <LinkButton href="/decors" size="sm" className="hidden sm:inline-flex">
            Créer mon visuel
          </LinkButton>
        </div>
      </div>
    </header>
  );
}
