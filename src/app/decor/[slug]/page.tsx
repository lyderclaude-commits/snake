import { notFound } from 'next/navigation';
import type { Metadata } from 'next';
import { TEMPLATES, getTemplate } from '@/core/templates';
import { DecorStudio } from '@/components/DecorStudio';

export function generateStaticParams() {
  return TEMPLATES.map((t) => ({ slug: t.slug }));
}

export async function generateMetadata({
  params,
}: {
  params: Promise<{ slug: string }>;
}): Promise<Metadata> {
  const { slug } = await params;
  const tpl = getTemplate(slug);
  if (!tpl) return { title: 'Décor introuvable — Wakabi Studio' };
  return {
    title: `${tpl.title} — Wakabi Studio`,
    description: tpl.subtitle ?? tpl.share.defaultCaption,
    openGraph: {
      title: `${tpl.title} — Wakabi Studio`,
      description: tpl.subtitle ?? tpl.share.defaultCaption,
      locale: 'fr_FR',
    },
  };
}

export default async function DecorPage({
  params,
}: {
  params: Promise<{ slug: string }>;
}) {
  const { slug } = await params;
  const tpl = getTemplate(slug);
  if (!tpl) notFound();
  return <DecorStudio tpl={tpl} />;
}
