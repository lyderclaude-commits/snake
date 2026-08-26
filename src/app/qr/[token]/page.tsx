import Link from 'next/link';
import type { Metadata } from 'next';
import { find } from '@/server/repo/badges';
import { currentUser } from '@/server/auth';
import { Card, LinkButton } from '@/components/ui';
import { WakabiMark } from '@/components/WakabiMark';

export const dynamic = 'force-dynamic';

export const metadata: Metadata = {
  title: 'Badge Wakabi Boost',
  robots: { index: false, follow: false },
};

/**
 * Ce que voit quelqu'un qui scanne le QR d'un badge avec son téléphone.
 *
 * Elle ne valide RIEN. Valider une entrée reste réservé à `/scan`, derrière
 * un compte équipe : un QR imprimé sur une image que l'invité partage
 * publiquement ne peut pas être ce qui ouvre la porte.
 *
 * L'agent d'entrée, lui, arrive ici connecté : on lui offre le lien direct
 * vers le contrôle d'entrée, code déjà rempli.
 */
export default async function BadgePage({ params }: { params: Promise<{ token: string }> }) {
  const { token } = await params;
  const badge = find(token);
  const me = await currentUser();
  const staff = me?.role === 'admin' || me?.role === 'partner';

  const etat = !badge ? 'inconnu' : badge.scanned_at ? 'utilise' : 'valide';

  const titre = {
    inconnu: 'Badge introuvable',
    utilise: 'Badge déjà utilisé',
    valide: 'Badge valide',
  }[etat];

  const texte = {
    inconnu: 'Ce code ne correspond à aucun badge émis. Vérifiez les 10 caractères, ou recréez votre visuel.',
    utilise: 'Ce badge a déjà servi à une entrée. Un badge ne vaut qu’un passage.',
    valide: 'Présentez ce code à l’entrée. C’est le passage à l’entrée qui crédite vos Koris, pas le téléchargement.',
  }[etat];

  return (
    <main className="mx-auto flex min-h-dvh max-w-md flex-col justify-center px-5 py-12">
      <Link href="/" className="mx-auto mb-8">
        <WakabiMark />
      </Link>

      <Card className="p-7 text-center shadow-wk">
        <p className="text-[40px] leading-none">
          {etat === 'valide' ? '🎟️' : etat === 'utilise' ? '↩️' : '⚠️'}
        </p>
        <h1 className="mt-3 font-display text-[22px] font-extrabold tracking-tight">{titre}</h1>

        <p className="mt-4 font-mono text-[20px] font-bold uppercase tracking-[0.18em] text-wk-text">
          {token.trim().toUpperCase().slice(0, 10)}
        </p>

        {badge && (
          <p className="mt-1 text-[13px] text-wk-text2">
            {badge.decor_title}
            {badge.user_name ? ` · ${badge.user_name}` : ''}
          </p>
        )}

        <p className="mx-auto mt-4 max-w-[38ch] text-[14px] text-wk-text2">{texte}</p>

        {badge?.scanned_at && (
          <p className="mt-2 text-[12.5px] text-wk-text3">
            Entrée validée le{' '}
            {new Date(badge.scanned_at).toLocaleString('fr-FR', {
              day: '2-digit',
              month: 'long',
              hour: '2-digit',
              minute: '2-digit',
            })}
          </p>
        )}

        {staff && etat === 'valide' ? (
          <LinkButton href={`/scan?code=${encodeURIComponent(token.toUpperCase())}`} className="mt-6">
            Valider l’entrée
          </LinkButton>
        ) : (
          <LinkButton
            href={badge ? `/decor/${badge.decor_slug}` : '/decors'}
            className="mt-6"
          >
            {badge ? 'Voir ce décor' : 'Voir les décors'}
          </LinkButton>
        )}
      </Card>

      {!staff && (
        <p className="mt-5 text-center text-[12.5px] text-wk-text3">
          Vous tenez l’entrée&nbsp;?{' '}
          <Link href="/connexion" className="font-semibold text-wk-primary hover:underline">
            Connectez-vous
          </Link>{' '}
          pour valider les passages.
        </p>
      )}
    </main>
  );
}
