import { notFound } from 'next/navigation';
import type { Metadata } from 'next';
import { findBySlug, parseTemplate, track } from '@/server/repo/decors';
import { currentUser } from '@/server/auth';
import { DecorStudio } from '@/components/DecorStudio';

export const dynamic = 'force-dynamic';

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const { slug } = await params;
  const row = findBySlug(slug);
  if (!row) return { title: 'Décor introuvable — Wakabi Boost' };
  return {
    title: `${row.title} — Wakabi Boost`,
    description: row.subtitle ?? 'Crée ton badge et partage-le.',
    openGraph: {
      title: `${row.title} — Wakabi Boost`,
      description: row.subtitle ?? 'Crée ton badge et partage-le.',
      locale: 'fr_FR',
    },
  };
}

export default async function DecorPage({ params }: { params: Promise<{ slug: string }> }) {
  const { slug } = await params;
  const row = findBySlug(slug);
  if (!row || row.status !== 'published') notFound();

  const tpl = parseTemplate(row);
  if (!tpl) notFound();

  // Vue comptée côté serveur : un compteur client serait trivial à gonfler.
  track(row.id, 'view');

  const me = await currentUser();

  return (
    <DecorStudio
      tpl={tpl}
      decorId={row.id}
      frameUrl={row.frame_url}
      signedIn={!!me}
      stats={{ downloads: row.downloads, views: row.views }}
    />
  );
}
