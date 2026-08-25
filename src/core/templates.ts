/**
 * Catalogue de démonstration — jalon J0.
 *
 * Trois modèles codés en dur, pour faire tourner la chaîne de bout en bout.
 * En J2 ils viendront du WordPress existant (custom post type
 * `decor_template`, exposé via `wakabi/v1/decors`), sans changer une ligne
 * de l'éditeur : c'est tout l'intérêt d'avoir un contrat de données.
 *
 * Les cadres PNG sont des PLACEHOLDERS aux couleurs de la charte.
 * L'équipe Wakabi les remplacera par ses visuels de campagne.
 */

import { DecorTemplate } from './template.schema';

const raw = [
  {
    id: '11111111-1111-4111-8111-111111111111',
    slug: 'jy-serai-lome',
    title: "J'y serai — Lomé",
    subtitle: 'Annonce ta présence à la prochaine sortie',
    city: 'lome',
    rubrique: 'evenements',
    status: 'published',
    publishedAt: '2026-08-25T09:00:00Z',
    createdBy: 'wakabi-team',
    canvas: { ratio: '1:1', width: 1080, height: 1080, background: 'brand.ink' },
    layers: [
      { type: 'photoSlot', id: 'photo', rect: { x: 0, y: 0, w: 1, h: 1 } },
      { type: 'image', id: 'frame', src: 'https://wakabileguide.com/frames/jy-serai.png' },
      {
        type: 'text', id: 'claim', value: "J'Y SERAI", uppercase: true,
        rect: { x: 0.07, y: 0.795, w: 0.66, h: 0.09 },
        size: 0.062, align: 'left', color: 'brand.paper', font: 'display',
      },
      {
        type: 'text', id: 'event', editable: true, placeholder: "Nom de l'événement",
        value: 'Festival des Divinités Noires', maxLength: 42,
        rect: { x: 0.07, y: 0.888, w: 0.66, h: 0.055 },
        size: 0.032, align: 'left', color: 'brand.paper', font: 'body',
      },
    ],
    export: { formats: ['1:1'], maxPx: 2048 },
    share: {
      defaultCaption: "J'y serai ! On se retrouve là-bas 🎉",
      hashtags: ['Wakabi', 'JySerai', 'Lome'],
      redirectUrl: 'https://wakabileguide.com/',
      redirectLabel: "Voir l'événement sur Wakabi",
    },
  },
  {
    id: '22222222-2222-4222-8222-222222222222',
    slug: 'bon-plan-du-moment',
    title: 'Bon plan du moment',
    subtitle: 'Partage ta trouvaille de la semaine',
    city: 'all',
    rubrique: 'gastronomie',
    status: 'published',
    publishedAt: '2026-08-25T09:00:00Z',
    createdBy: 'wakabi-team',
    canvas: { ratio: '1:1', width: 1080, height: 1080, background: 'brand.ink' },
    layers: [
      { type: 'photoSlot', id: 'photo', rect: { x: 0, y: 0, w: 1, h: 1 } },
      { type: 'image', id: 'frame', src: 'https://wakabileguide.com/frames/bon-plan.png' },
      {
        type: 'text', id: 'title', editable: true, placeholder: 'Le nom du bon coin',
        value: 'Chez Léna', maxLength: 28,
        rect: { x: 0.07, y: 0.795, w: 0.66, h: 0.08 },
        size: 0.052, align: 'left', color: 'brand.paper', font: 'display',
      },
      {
        type: 'text', id: 'note', editable: true, placeholder: 'Ce qui rend ce coin unique',
        value: 'La meilleure viande de porc de Lomé', maxLength: 52,
        rect: { x: 0.07, y: 0.877, w: 0.66, h: 0.05 },
        size: 0.027, align: 'left', color: 'brand.paper', font: 'body',
      },
    ],
    export: { formats: ['1:1'], maxPx: 2048 },
    share: {
      defaultCaption: 'Mon bon plan de la semaine 👌',
      hashtags: ['Wakabi', 'BonPlan'],
      redirectUrl: 'https://wakabileguide.com/',
      redirectLabel: 'Trouver ce lieu sur Wakabi',
    },
  },
  {
    id: '33333333-3333-4333-8333-333333333333',
    slug: 'story-wakabi',
    title: 'Story — statut WhatsApp',
    subtitle: 'Le format vertical, pour ton statut',
    city: 'all',
    rubrique: 'campagne',
    status: 'published',
    publishedAt: '2026-08-25T09:00:00Z',
    createdBy: 'wakabi-team',
    canvas: { ratio: '9:16', width: 1080, height: 1920, background: 'brand.ink' },
    layers: [
      { type: 'photoSlot', id: 'photo', rect: { x: 0, y: 0, w: 1, h: 1 } },
      { type: 'image', id: 'frame', src: 'https://wakabileguide.com/frames/story.png' },
      {
        type: 'text', id: 'claim', value: 'Vis ta ville à 100%', uppercase: true,
        rect: { x: 0.08, y: 0.052, w: 0.84, h: 0.06 },
        size: 0.031, align: 'center', color: 'brand.paper', font: 'display',
      },
      {
        type: 'text', id: 'place', editable: true, placeholder: 'Où es-tu ?',
        value: 'Grand-Popo', maxLength: 30,
        rect: { x: 0.07, y: 0.828, w: 0.62, h: 0.06 },
        size: 0.034, align: 'left', color: 'brand.paper', font: 'display',
      },
      {
        type: 'text', id: 'who', editable: true, placeholder: 'Ton prénom',
        value: 'Kossi', maxLength: 22,
        rect: { x: 0.07, y: 0.886, w: 0.62, h: 0.04 },
        size: 0.021, align: 'left', color: 'brand.paper', font: 'body',
      },
    ],
    export: { formats: ['9:16'], maxPx: 2048 },
    share: {
      defaultCaption: 'Mon prochain bon coin 🌍',
      hashtags: ['Wakabi', 'VisTaVille'],
      redirectUrl: 'https://wakabileguide.com/',
      redirectLabel: 'Découvrir sur Wakabi',
    },
  },
];

/**
 * Chaque modèle passe par le schéma Zod — y compris ceux que nous écrivons
 * nous-mêmes. Une erreur ici fait échouer le build plutôt que la production.
 */
export const TEMPLATES: DecorTemplate[] = raw.map((t) => DecorTemplate.parse(t));

export function getTemplate(slug: string): DecorTemplate | undefined {
  return TEMPLATES.find((t) => t.slug === slug);
}

/**
 * Les cadres sont servis localement. Les `src` du catalogue pointent vers le
 * CDN de production ; en démonstration on les remappe sur /frames.
 * En J2, cette fonction disparaît : le back-office servira les vraies URL.
 */
export function assetUrl(src: string): string {
  const name = src.split('/').pop() ?? '';
  return `/frames/${name}`;
}
