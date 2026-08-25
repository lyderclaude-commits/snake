import Link from 'next/link';
import { Icon } from '@/components/ui';
import type { DecorWithStats } from '@/server/repo/decors';

/** Vignette de décor, avec les deux compteurs publics — comme mougni. */
export function DecorCard({ d }: { d: DecorWithStats }) {
  const ratio = (() => {
    try {
      const t = JSON.parse(d.template_json) as { canvas: { width: number; height: number } };
      return `${t.canvas.width} / ${t.canvas.height}`;
    } catch {
      return '1 / 1';
    }
  })();

  return (
    <Link
      href={`/decor/${d.slug}`}
      className="group block overflow-hidden rounded-wk-lg border border-wk-border bg-white shadow-wk-sm transition hover:-translate-y-0.5 hover:shadow-wk"
    >
      <div className="relative bg-wk-surface2" style={{ aspectRatio: ratio }}>
        {d.frame_url && (
          /* eslint-disable-next-line @next/next/no-img-element */
          <img
            src={d.frame_url}
            alt=""
            loading="lazy"
            className="absolute inset-0 size-full object-cover"
          />
        )}
      </div>
      <div className="p-3">
        <p className="truncate font-display text-[14px] font-bold leading-tight">{d.title}</p>
        {d.subtitle && (
          <p className="mt-1 line-clamp-1 text-[12px] text-wk-text3">{d.subtitle}</p>
        )}
        <div className="mt-2.5 flex items-center justify-between">
          <span className="flex items-center gap-3 text-[11.5px] font-semibold text-wk-text3">
            <span className="inline-flex items-center gap-1">
              <Icon name="download" className="size-3.5" />
              {d.downloads}
            </span>
            <span className="inline-flex items-center gap-1">
              <Icon name="eye" className="size-3.5" />
              {d.views}
            </span>
          </span>
          <span className="rounded-wk-sm bg-wk-primary px-2.5 py-1 text-[11px] font-bold text-white transition group-hover:bg-wk-primary-d">
            Utiliser
          </span>
        </div>
      </div>
    </Link>
  );
}

export function DecorGrid({ decors }: { decors: DecorWithStats[] }) {
  if (!decors.length) {
    return (
      <p className="rounded-wk-lg border border-dashed border-wk-border2 bg-white p-8 text-center text-[14px] text-wk-text3">
        Aucun décor publié pour l’instant.
      </p>
    );
  }
  return (
    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
      {decors.map((d) => (
        <DecorCard key={d.id} d={d} />
      ))}
    </div>
  );
}
