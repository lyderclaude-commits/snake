import Link from 'next/link';
import { TEMPLATES, assetUrl } from '@/core/templates';
import { WakabiMark } from '@/components/WakabiMark';

export default function GalleryPage() {
  return (
    <main className="mx-auto min-h-dvh w-full max-w-5xl px-5 pb-24">
      <header className="flex items-center justify-between py-6">
        <WakabiMark />
        <span className="rounded-full border border-wk-border bg-white px-3 py-1.5 text-[11px] font-semibold text-wk-text2">
          Aperçu · J0–J1
        </span>
      </header>

      <section className="pt-6 pb-10">
        <p className="mb-3 inline-flex items-center gap-2 rounded-full bg-wk-primary-l px-3 py-1.5 text-[12px] font-semibold text-wk-primary">
          <span className="inline-block size-1.5 rounded-full bg-wk-primary" />
          En direct depuis Lomé, Togo
        </p>
        <h1 className="font-display text-[clamp(2rem,6.5vw,3.4rem)] font-extrabold leading-[1.02] tracking-[-0.035em] text-balance">
          Crée ton visuel,
          <br />
          <span className="text-wk-primary">partage ton bon coin.</span>
        </h1>
        <p className="mt-4 max-w-[46ch] text-[17px] leading-relaxed text-wk-text2">
          Choisis un décor, ajoute ta photo, télécharge. Gratuit, sans inscription — et ça
          prend 30&nbsp;secondes.
        </p>
      </section>

      <h2 className="mb-4 font-display text-[15px] font-bold tracking-[0.02em] text-wk-text2">
        {TEMPLATES.length} décors disponibles
      </h2>

      <ul className="grid grid-cols-2 gap-4 sm:grid-cols-3">
        {TEMPLATES.map((t) => {
          const frame = t.layers.find((l) => l.type === 'image');
          return (
            <li key={t.id}>
              <Link
                href={`/decor/${t.slug}`}
                className="group block overflow-hidden rounded-wk-lg border border-wk-border bg-white shadow-wk-sm transition hover:-translate-y-0.5 hover:shadow-wk"
              >
                <div
                  className="relative bg-wk-surface2"
                  style={{ aspectRatio: `${t.canvas.width} / ${t.canvas.height}` }}
                >
                  {frame && frame.type === 'image' && (
                    /* eslint-disable-next-line @next/next/no-img-element */
                    <img
                      src={assetUrl(frame.src)}
                      alt=""
                      className="absolute inset-0 size-full object-cover"
                      loading="lazy"
                    />
                  )}
                  <span className="absolute left-2 top-2 rounded-full bg-white/92 px-2 py-0.5 text-[10px] font-bold text-wk-text2">
                    {t.canvas.ratio}
                  </span>
                </div>
                <div className="p-3">
                  <p className="font-display text-[14px] font-bold leading-tight text-wk-text">
                    {t.title}
                  </p>
                  <p className="mt-1 line-clamp-2 text-[12px] leading-snug text-wk-text3">
                    {t.subtitle}
                  </p>
                  <span className="mt-2.5 inline-block rounded-wk-md bg-wk-primary px-3 py-1.5 text-[11px] font-bold text-white transition group-hover:bg-wk-primary-d">
                    Créer mon visuel
                  </span>
                </div>
              </Link>
            </li>
          );
        })}
      </ul>

      <p className="mt-10 rounded-wk-lg border border-wk-border bg-white p-4 text-[13px] leading-relaxed text-wk-text2">
        <strong className="text-wk-text">Cadres de démonstration.</strong> Ces trois décors
        utilisent des visuels provisoires aux couleurs de la charte, pour faire tourner la
        chaîne de bout en bout. En J2 le catalogue viendra du WordPress existant, et l'équipe
        publiera ses propres campagnes sans développeur.
      </p>
    </main>
  );
}
