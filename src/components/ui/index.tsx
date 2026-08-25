import Link from 'next/link';
import type { ReactNode } from 'react';

/** Primitives d'interface — toutes les couleurs viennent des tokens de charte. */

const BTN = {
  primary: 'bg-wk-primary text-white hover:bg-wk-primary-d shadow-wk',
  outline: 'border-2 border-wk-primary text-wk-primary hover:bg-wk-primary-l',
  ghost: 'border border-wk-border text-wk-text2 hover:border-wk-border2 bg-white',
  danger: 'bg-wk-red text-white hover:opacity-90',
  success: 'bg-wk-green text-white hover:opacity-90',
} as const;

const SIZE = {
  sm: 'px-3 py-1.5 text-[12.5px]',
  md: 'px-4 py-2.5 text-[14px]',
  lg: 'px-6 py-3.5 text-[16px]',
} as const;

type BtnProps = {
  variant?: keyof typeof BTN;
  size?: keyof typeof SIZE;
  className?: string;
  children: ReactNode;
};

export function Button({
  variant = 'primary',
  size = 'md',
  className = '',
  children,
  ...rest
}: BtnProps & React.ButtonHTMLAttributes<HTMLButtonElement>) {
  return (
    <button
      className={`inline-flex items-center justify-center gap-2 rounded-wk-md font-display font-bold transition disabled:cursor-not-allowed disabled:opacity-45 ${BTN[variant]} ${SIZE[size]} ${className}`}
      {...rest}
    >
      {children}
    </button>
  );
}

export function LinkButton({
  href,
  variant = 'primary',
  size = 'md',
  className = '',
  children,
}: BtnProps & { href: string }) {
  return (
    <Link
      href={href}
      className={`inline-flex items-center justify-center gap-2 rounded-wk-md font-display font-bold transition ${BTN[variant]} ${SIZE[size]} ${className}`}
    >
      {children}
    </Link>
  );
}

export function Card({ className = '', children }: { className?: string; children: ReactNode }) {
  return (
    <div className={`rounded-wk-lg border border-wk-border bg-white ${className}`}>{children}</div>
  );
}

export function Field({
  label,
  hint,
  error,
  children,
}: {
  label: string;
  hint?: string;
  error?: string;
  children: ReactNode;
}) {
  return (
    <label className="block">
      <span className="mb-1.5 block text-[13px] font-semibold text-wk-text2">{label}</span>
      {children}
      {hint && !error && <span className="mt-1 block text-[12px] text-wk-text3">{hint}</span>}
      {error && (
        <span className="mt-1 block text-[12px] font-medium text-wk-red">{error}</span>
      )}
    </label>
  );
}

export const inputClass =
  'w-full rounded-wk-sm border border-wk-border bg-wk-bg2 px-3 py-2.5 text-[15px] text-wk-text outline-none transition focus:border-wk-primary focus:bg-white';

const STATUS: Record<string, { label: string; cls: string }> = {
  draft: { label: 'Brouillon', cls: 'bg-wk-surface2 text-wk-text2' },
  pending_review: { label: 'En relecture', cls: 'bg-orange-50 text-wk-gold' },
  changes_requested: { label: 'À corriger', cls: 'bg-orange-50 text-wk-orange' },
  published: { label: 'Publié', cls: 'bg-green-50 text-wk-green' },
  rejected: { label: 'Refusé', cls: 'bg-red-50 text-wk-red' },
  archived: { label: 'Archivé', cls: 'bg-wk-surface2 text-wk-text3' },
  expired: { label: 'Expiré', cls: 'bg-wk-surface2 text-wk-text3' },
};

export function StatusPill({ status }: { status: string }) {
  const s = STATUS[status] ?? { label: status, cls: 'bg-wk-surface2 text-wk-text2' };
  return (
    <span className={`inline-block rounded-wk-sm px-2 py-0.5 text-[11px] font-bold ${s.cls}`}>
      {s.label}
    </span>
  );
}

export function Stat({ value, label, tone = 'ink' }: { value: string | number; label: string; tone?: 'ink' | 'primary' | 'green' | 'gold' }) {
  const c = { ink: 'text-wk-text', primary: 'text-wk-primary', green: 'text-wk-green', gold: 'text-wk-gold' }[tone];
  return (
    <Card className="p-4">
      <b className={`block font-display text-[1.9rem] font-extrabold leading-none tabular-nums tracking-tight ${c}`}>
        {value}
      </b>
      <span className="mt-1.5 block text-[12.5px] leading-snug text-wk-text3">{label}</span>
    </Card>
  );
}

/** Icônes en SVG inline (tracés Lucide) — aucune requête réseau. */
export function Icon({ name, className = 'size-5' }: { name: keyof typeof PATHS; className?: string }) {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"
         strokeLinecap="round" strokeLinejoin="round" className={className} aria-hidden="true">
      {PATHS[name]}
    </svg>
  );
}

const PATHS = {
  image: <><rect width="18" height="18" x="3" y="3" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></>,
  crop: <><path d="M6 2v14a2 2 0 0 0 2 2h14"/><path d="M18 22V8a2 2 0 0 0-2-2H2"/></>,
  share: <><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" x2="12" y1="2" y2="15"/></>,
  shield: <><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></>,
  download: <><path d="M12 15V3"/><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5"/></>,
  eye: <><path d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.88 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.88 0"/><circle cx="12" cy="12" r="3"/></>,
  check: <path d="M20 6 9 17l-5-5"/>,
  arrow: <><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></>,
  store: <><path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7"/><path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"/><path d="M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4"/><path d="M2 7h20"/></>,
  users: <><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></>,
  clock: <><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></>,
  pin: <><path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/></>,
} as const;
