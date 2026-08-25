import type { Metadata } from 'next';
import { Nav } from '@/components/site/Nav';
import { Footer } from '@/components/site/Footer';
import { DecorGrid } from '@/components/site/DecorGrid';
import { listPublished } from '@/server/repo/decors';

export const dynamic = 'force-dynamic';

export const metadata: Metadata = {
  title: 'Tous les décors — Wakabi Boost',
  description:
    'Choisis un décor Wakabi, ajoute ta photo et partage. Gratuit, sans inscription.',
};

const CITIES: Record<string, string> = {
  all: 'Toutes les villes',
  lome: 'Lomé',
  cotonou: 'Cotonou',
  abidjan: 'Abidjan',
};

export default async function DecorsPage({
  searchParams,
}: {
  searchParams: Promise<{ ville?: string }>;
}) {
  const { ville } = await searchParams;
  const all = listPublished();
  const decors = ville && ville !== 'all' ? all.filter((d) => d.city === ville) : all;

  const villes = ['all', ...new Set(all.map((d) => d.city).filter((c) => c !== 'all'))];

  return (
    <>
      <Nav variant="app" />
      <main className="mx-auto min-h-dvh max-w-6xl px-5 pb-24 pt-10">
        <h1 className="font-display text-[clamp(1.9rem,5vw,2.8rem)] font-extrabold leading-tight tracking-[-0.035em]">
          Choisis ton décor
        </h1>
        <p className="mt-2 max-w-[52ch] text-[16px] text-wk-text2">
          Ajoute ta photo, recadre, télécharge. Gratuit, sans inscription — et ta photo ne quitte
          jamais ton téléphone.
        </p>

        {villes.length > 1 && (
          <div className="mt-6 flex flex-wrap gap-2">
            {villes.map((v) => {
              const on = (ville ?? 'all') === v;
              return (
                <a
                  key={v}
                  href={v === 'all' ? '/decors' : `/decors?ville=${v}`}
                  className={`rounded-full border px-3.5 py-1.5 text-[13px] font-semibold transition ${
                    on
                      ? 'border-wk-primary bg-wk-primary text-white'
                      : 'border-wk-border bg-white text-wk-text2 hover:border-wk-border2'
                  }`}
                >
                  {CITIES[v] ?? v}
                </a>
              );
            })}
          </div>
        )}

        <p className="mt-6 text-[13px] font-semibold text-wk-text3">
          {decors.length} décor{decors.length > 1 ? 's' : ''} disponible
          {decors.length > 1 ? 's' : ''}
        </p>
        <div className="mt-4">
          <DecorGrid decors={decors} />
        </div>
      </main>
      <Footer />
    </>
  );
}
