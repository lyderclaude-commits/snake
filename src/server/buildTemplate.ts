import 'server-only';
import { DecorTemplate, WAKABI_REDIRECT_HOSTS } from '@/core/template.schema';
import { randomUUID } from 'node:crypto';

/**
 * Fabrique un DecorTemplate valide à partir du formulaire partenaire.
 *
 * Le partenaire ne place pas ses calques à la main : il choisit un GABARIT
 * dont les positions sont déjà réglées, et téléverse son cadre. C'est ce qui
 * permet d'ouvrir la création sans exiger de compétence graphique — et ce qui
 * garantit que le résultat passe le pré-vol.
 */

export type LayoutId = 'bandeau' | 'angle' | 'story';

export const LAYOUTS: { id: LayoutId; label: string; hint: string; ratio: '1:1' | '9:16' }[] = [
  { id: 'bandeau', label: 'Bandeau bas', hint: 'Carré · texte sur une bande en bas', ratio: '1:1' },
  { id: 'angle', label: 'Coin & voile', hint: 'Carré · texte en bas à gauche', ratio: '1:1' },
  { id: 'story', label: 'Story verticale', hint: '9:16 · pour le statut WhatsApp', ratio: '9:16' },
];

interface Input {
  slug: string;
  title: string;
  subtitle?: string;
  city: string;
  rubrique: string;
  layout: LayoutId;
  frameUrl: string;
  claim: string;
  fieldLabel: string;
  fieldValue: string;
  redirectUrl: string;
  redirectLabel?: string;
  caption?: string;
  expiresAt?: string | null;
  createdBy: 'wakabi-team' | 'partner';
  partnerId?: string;
}

const CANVAS = {
  bandeau: { w: 1080, h: 1080, ratio: '1:1' as const },
  angle: { w: 1080, h: 1080, ratio: '1:1' as const },
  story: { w: 1080, h: 1920, ratio: '9:16' as const },
};

/**
 * Positions des textes par gabarit.
 *
 * Calées pour ne heurter NI le filigrane (bas-droite, ~21 % de large) NI le
 * QR Code (bas-gauche, ~19 % avec sa zone de silence). D'où un texte qui
 * commence à 25 % et s'arrête à 73 % : la bande centrale est la seule
 * vraiment libre.
 */
const TEXTS = {
  bandeau: {
    claim: { x: 0.25, y: 0.795, w: 0.48, h: 0.09, size: 0.058, font: 'display' as const },
    field: { x: 0.25, y: 0.888, w: 0.48, h: 0.055, size: 0.03, font: 'body' as const },
  },
  angle: {
    claim: { x: 0.25, y: 0.79, w: 0.48, h: 0.08, size: 0.05, font: 'display' as const },
    field: { x: 0.25, y: 0.872, w: 0.48, h: 0.05, size: 0.026, font: 'body' as const },
  },
  story: {
    claim: { x: 0.25, y: 0.826, w: 0.48, h: 0.06, size: 0.032, font: 'display' as const },
    field: { x: 0.25, y: 0.884, w: 0.48, h: 0.04, size: 0.02, font: 'body' as const },
  },
};

export function buildTemplate(i: Input) {
  const c = CANVAS[i.layout];
  const t = TEXTS[i.layout];

  return DecorTemplate.parse({
    id: randomUUID(),
    slug: i.slug,
    title: i.title,
    subtitle: i.subtitle || undefined,
    city: i.city,
    rubrique: i.rubrique,
    status: 'draft',
    createdBy: i.createdBy,
    partnerId: i.partnerId,
    expiresAt: i.expiresAt || undefined,
    canvas: { ratio: c.ratio, width: c.w, height: c.h, background: 'brand.ink' },
    layers: [
      { type: 'photoSlot', id: 'photo', rect: { x: 0, y: 0, w: 1, h: 1 } },
      { type: 'image', id: 'frame', src: absolute(i.frameUrl) },
      {
        type: 'text', id: 'claim', value: i.claim, uppercase: i.layout === 'bandeau',
        rect: t.claim, size: t.claim.size, align: 'left',
        color: 'brand.paper', font: t.claim.font,
      },
      {
        type: 'text', id: 'field', editable: true, placeholder: i.fieldLabel,
        value: i.fieldValue, maxLength: 42,
        rect: t.field, size: t.field.size, align: 'left',
        color: 'brand.paper', font: t.field.font,
      },
    ],
    export: { formats: [c.ratio], maxPx: 2048 },
    share: {
      defaultCaption: i.caption ?? '',
      hashtags: ['Wakabi', 'JySerai'],
      redirectUrl: i.redirectUrl,
      redirectLabel: i.redirectLabel || 'Découvrir sur Wakabi',
    },
  });
}

/** Le schéma exige une URL absolue pour la source d'un calque image. */
function absolute(path: string): string {
  if (/^https?:\/\//.test(path)) return path;
  return `https://${WAKABI_REDIRECT_HOSTS[0]}${path.startsWith('/') ? '' : '/'}${path}`;
}
