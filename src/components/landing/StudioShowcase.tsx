import { LinkButton, Icon } from '@/components/ui';
import { DecorGrid } from '@/components/site/DecorGrid';
import { SectionHead } from './Sections';
import type { DecorWithStats } from '@/server/repo/decors';

/**
 * Le Studio — la seule brique du prototype qui EXISTE déjà.
 *
 * Plutôt qu'un faux éditeur inline, on montre les vrais décors publiés et on
 * envoie vers l'éditeur réel : ce qui est démontré est ce qui fonctionne.
 */
export function StudioShowcase({ decors }: { decors: DecorWithStats[] }) {
  return (
    <section id="studio" className="mx-auto max-w-6xl scroll-mt-20 px-5 py-20">
      <div className="flex flex-wrap items-end justify-between gap-6">
        <SectionHead
          eyebrow="Studio — disponible maintenant"
          title="Un modèle pour"
          accent="chaque occasion."
          lede="Vos invités choisissent un décor, ajoutent leur photo et téléchargent — en 30 secondes, sans compte. Positionnement, couleurs et typographies sont déjà réglés."
        />
        <LinkButton href="/decors" variant="outline">
          Tous les décors <Icon name="arrow" className="size-4" />
        </LinkButton>
      </div>

      <div className="mt-10">
        <DecorGrid decors={decors} />
      </div>

      <div className="mt-8 grid gap-4 sm:grid-cols-3">
        {[
          ['image', 'Sa photo, jamais téléversée', 'Le badge est fabriqué dans le navigateur de l’invité. Rien ne part sur nos serveurs.'],
          ['crop', 'Recadrage en deux gestes', 'Glisser pour déplacer, pincer pour zoomer. C’est tout ce qu’il faut.'],
          ['share', 'Partage WhatsApp direct', 'Un bouton, la feuille de partage native, et le badge circule.'],
        ].map(([icon, t, b]) => (
          <div key={t} className="rounded-wk-lg border border-wk-border bg-white p-5">
            <span className="grid size-9 place-items-center rounded-wk-md bg-wk-primary-l text-wk-primary">
              <Icon name={icon as 'image'} className="size-4.5" />
            </span>
            <h3 className="mt-3 font-display text-[15px] font-bold">{t}</h3>
            <p className="mt-1.5 text-[13.5px] leading-relaxed text-wk-text2">{b}</p>
          </div>
        ))}
      </div>
    </section>
  );
}
