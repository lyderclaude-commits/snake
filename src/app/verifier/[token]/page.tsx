import Link from 'next/link';
import { consume } from '@/server/repo/email';
import { currentUser } from '@/server/auth';
import { Card, LinkButton } from '@/components/ui';
import { WakabiMark } from '@/components/WakabiMark';

export const dynamic = 'force-dynamic';

export default async function VerifierPage({
  params,
}: {
  params: Promise<{ token: string }>;
}) {
  const { token } = await params;
  const ok = consume(token);
  const me = await currentUser();
  const home = me?.role === 'partner' ? '/partenaire' : me?.role === 'admin' ? '/admin' : '/compte';

  return (
    <main className="mx-auto flex min-h-dvh max-w-md flex-col justify-center px-5 py-12">
      <Link href="/" className="mx-auto mb-8">
        <WakabiMark />
      </Link>
      <Card className="p-7 text-center shadow-wk">
        <p className="text-[40px] leading-none">{ok ? '✅' : '⚠️'}</p>
        <h1 className="mt-3 font-display text-[22px] font-extrabold tracking-tight">
          {ok ? 'Adresse vérifiée' : 'Lien invalide ou expiré'}
        </h1>
        <p className="mx-auto mt-2 max-w-[38ch] text-[14px] text-wk-text2">
          {ok
            ? 'Votre adresse est confirmée. Vous pouvez soumettre vos décors à la relecture.'
            : 'Les liens de vérification expirent au bout de 48 heures. Demandez-en un nouveau depuis votre espace.'}
        </p>
        <LinkButton href={home} className="mt-6">
          Aller à mon espace
        </LinkButton>
      </Card>
    </main>
  );
}
