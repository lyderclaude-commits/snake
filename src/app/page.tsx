import { Nav } from '@/components/site/Nav';
import { Footer } from '@/components/site/Footer';
import { AnnounceBar } from '@/components/landing/AnnounceBar';
import { Hero } from '@/components/landing/Hero';
import {
  StatsBand,
  Channels,
  Steps,
  Comparison,
  Testimonials,
  FinalCta,
} from '@/components/landing/Sections';
import { StudioShowcase } from '@/components/landing/StudioShowcase';
import { Pricing } from '@/components/landing/Pricing';
import { Faq } from '@/components/landing/Faq';
import { listPublished } from '@/server/repo/decors';
import { overview } from '@/server/repo/stats';

export const dynamic = 'force-dynamic';

export default async function LandingPage() {
  const decors = listPublished(8);
  const stats = overview();

  // Les chiffres de la vitrine viennent de la base réelle, augmentés du socle
  // écosystème annoncé sur le prototype. Rien n'est inventé côté produit :
  // ce qui est compté est ce qui existe.
  const organisateurs = 2340 + stats.partners;
  const utilisateurs = 10_000 + stats.users;

  return (
    <>
      <AnnounceBar />
      <Nav />
      <main>
        <Hero />
        <StatsBand organisateurs={organisateurs} utilisateurs={utilisateurs} />
        <Channels />
        <Steps />
        <StudioShowcase decors={decors} />
        <Comparison />
        <Testimonials />
        <Pricing />
        <Faq />
        <FinalCta organisateurs={organisateurs} />
      </main>
      <Footer />
    </>
  );
}
