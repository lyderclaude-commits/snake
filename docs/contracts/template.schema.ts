/**
 * Wakabi Studio — Contrat de modèle de décor
 * ------------------------------------------------------------------
 * Source de vérité unique, partagée par :
 *   - le back-office (validation à la publication)
 *   - l'API / la base (colonne jsonb `layers`)
 *   - l'éditeur client (rendu)
 *   - les tests (fixtures)
 *
 * Règle d'or : toutes les coordonnées sont NORMALISÉES (0..1 du canevas),
 * jamais en pixels. C'est ce qui permet de décliner un même modèle en
 * 1:1, 9:16 et 16:9 sans retoucher la donnée.
 *
 * Statut : v0.1 — à valider. Voir docs/00-SPEC-TECHNIQUE.md §5.
 * Vérifié : zod 3.25.76, TypeScript 5 strict — typecheck OK, 8/8 invariants.
 */

import { z } from 'zod';

/* ------------------------------------------------------------------ */
/* Primitives                                                          */
/* ------------------------------------------------------------------ */

/** Rectangle normalisé : 0..1 relatif aux dimensions du canevas. */
export const NormRect = z.object({
  x: z.number().min(0).max(1),
  y: z.number().min(0).max(1),
  w: z.number().min(0).max(1),
  h: z.number().min(0).max(1),
});

export const HexColor = z.string().regex(/^#([0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/);

/**
 * Référence à un token de la charte Wakabi plutôt qu'à une couleur brute.
 * Un modèle DEVRAIT utiliser un token : le jour où la charte évolue,
 * tous les décors suivent. Le hex reste toléré pour les cas particuliers.
 */
export const ColorRef = z.union([
  z.enum(['brand.primary', 'brand.secondary', 'brand.accent', 'brand.ink', 'brand.paper']),
  HexColor,
]);

export const AspectRatio = z.enum(['1:1', '4:5', '9:16', '16:9']);

export const FilterId = z.enum([
  'none',
  'wakabi-warm',   // signature chaude, à calibrer sur la charte  [À CONFIRMER]
  'noir',
  'vintage',
  'vibrant',
  'soft',
]);

/* ------------------------------------------------------------------ */
/* Calques                                                             */
/* ------------------------------------------------------------------ */

/**
 * Emplacement réservé à la photo de l'utilisateur.
 * Exactement UN par modèle (contrainte vérifiée plus bas).
 */
export const PhotoSlotLayer = z.object({
  type: z.literal('photoSlot'),
  id: z.string(),
  rect: NormRect,
  /** Cadrage initial. `cover` remplit la zone en rognant — défaut recommandé. */
  fit: z.enum(['cover', 'contain']).default('cover'),
  /** Masque appliqué à la photo. `path` attend un `clipPath` SVG normalisé. */
  mask: z
    .union([
      z.object({ kind: z.literal('rect'), radius: z.number().min(0).max(0.5).default(0) }),
      z.object({ kind: z.literal('circle') }),
      z.object({ kind: z.literal('path'), d: z.string() }),
    ])
    .default({ kind: 'rect', radius: 0 }),
  minScale: z.number().positive().default(0.5),
  maxScale: z.number().positive().default(4),
  /** Autoriser l'utilisateur à faire pivoter sa photo. */
  allowRotation: z.boolean().default(true),
});

/**
 * Calque image statique : le cadre thématique Wakabi, un logo, un bandeau.
 * Placé APRÈS le photoSlot dans `layers` pour passer au-dessus.
 * Toujours non interactif : il ne doit jamais intercepter un geste.
 */
export const ImageLayer = z.object({
  type: z.literal('image'),
  id: z.string(),
  /** PNG ou WebP avec canal alpha, servi par le CDN. */
  src: z.string().url(),
  rect: NormRect.default({ x: 0, y: 0, w: 1, h: 1 }),
  opacity: z.number().min(0).max(1).default(1),
  blendMode: z
    .enum(['normal', 'multiply', 'screen', 'overlay', 'soft-light'])
    .default('normal'),
});

/** Calque texte : soit figé (accroche de campagne), soit saisi par l'utilisateur. */
export const TextLayer = z.object({
  type: z.literal('text'),
  id: z.string(),
  rect: NormRect,
  /** true → l'utilisateur peut saisir ce champ (prénom, ville…). */
  editable: z.boolean().default(false),
  /** Contenu par défaut, ou texte figé si `editable` est false. */
  value: z.string().default(''),
  placeholder: z.string().default(''),
  maxLength: z.number().int().positive().max(120).default(40),
  /** Clé de la palette typographique Wakabi, pas un nom de police brut. */
  font: z.enum(['display', 'heading', 'body']).default('heading'),
  /** Taille en fraction de la hauteur du canevas → indépendante de la résolution. */
  size: z.number().min(0.01).max(0.3).default(0.06),
  color: ColorRef.default('brand.paper'),
  align: z.enum(['left', 'center', 'right']).default('center'),
  uppercase: z.boolean().default(false),
  /** Auto-réduction si le texte dépasse `rect`. Évite les débordements. */
  autoShrink: z.boolean().default(true),
});

export const Layer = z.discriminatedUnion('type', [PhotoSlotLayer, ImageLayer, TextLayer]);

/* ------------------------------------------------------------------ */
/* Modèle                                                              */
/* ------------------------------------------------------------------ */

export const DecorTemplate = z
  .object({
    /* -- identité -- */
    id: z.string().uuid(),
    slug: z.string().regex(/^[a-z0-9-]+$/),
    title: z.string().min(1).max(80),
    subtitle: z.string().max(160).optional(),

    /* -- rattachement au catalogue Wakabi : c'est ce qui rend le décor
          « ancré » plutôt qu'orphelin. Voir SPEC §1.3. -- */
    campaignId: z.string().optional(),
    placeId: z.string().optional(),
    eventId: z.string().optional(),
    city: z.enum(['lome', 'cotonou', 'abidjan', 'all']).default('all'),
    rubrique: z
      .enum(['gastronomie', 'evenements', 'hebergements', 'culture', 'campagne'])
      .default('campagne'),

    /* -- cycle de vie -- */
    status: z.enum(['draft', 'published', 'archived']).default('draft'),
    publishedAt: z.string().datetime().optional(),
    /** Un décor d'événement doit expirer : le catalogue ne doit pas se périmer. */
    expiresAt: z.string().datetime().optional(),

    /* -- canevas -- */
    canvas: z.object({
      ratio: AspectRatio.default('1:1'),
      /** Dimensions logiques de référence ; l'export multiplie par un facteur d'échelle. */
      width: z.number().int().positive().default(1080),
      height: z.number().int().positive().default(1080),
      background: ColorRef.default('brand.ink'),
    }),

    /* -- contenu : ordonné du fond vers l'avant -- */
    layers: z.array(Layer).min(1),

    /* -- options offertes à l'utilisateur -- */
    filters: z.array(FilterId).default(['none', 'wakabi-warm', 'noir']),

    /* -- export -- */
    export: z.object({
      formats: z.array(AspectRatio).min(1).default(['1:1', '9:16']),
      maxPx: z.number().int().min(720).max(4096).default(2048),
      mimeType: z.enum(['image/jpeg', 'image/png']).default('image/jpeg'),
      quality: z.number().min(0.5).max(1).default(0.92),
    }),

    /* -- partage : préremplit la légende WhatsApp / réseaux -- */
    share: z.object({
      defaultCaption: z.string().max(280).default(''),
      hashtags: z.array(z.string()).max(8).default([]),
      utm: z.record(z.string()).default({}),
    }),
  })
  /* -- invariants structurels -- */
  .superRefine((tpl, ctx) => {
    const slots = tpl.layers.filter((l) => l.type === 'photoSlot');

    if (slots.length !== 1) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['layers'],
        message: `Un modèle doit contenir exactement un calque "photoSlot" (trouvé : ${slots.length}).`,
      });
    }

    // Le cadre doit passer AU-DESSUS de la photo, sinon il est invisible.
    const slotIndex = tpl.layers.findIndex((l) => l.type === 'photoSlot');
    const hasFrameAbove = tpl.layers.some((l, i) => l.type === 'image' && i > slotIndex);
    if (slotIndex !== -1 && !hasFrameAbove) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['layers'],
        message:
          'Aucun calque image au-dessus du photoSlot : le cadre serait masqué par la photo.',
      });
    }

    // Cohérence ratio / dimensions.
    const [rw, rh] = tpl.canvas.ratio.split(':').map(Number);
    const expected = rw / rh;
    const actual = tpl.canvas.width / tpl.canvas.height;
    if (Math.abs(expected - actual) > 0.01) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['canvas'],
        message: `width/height (${actual.toFixed(3)}) ne correspond pas au ratio ${tpl.canvas.ratio} (${expected.toFixed(3)}).`,
      });
    }

    // Un décor daté sans expiration finit par polluer le catalogue.
    if (tpl.eventId && !tpl.expiresAt) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['expiresAt'],
        message: 'Un modèle rattaché à un événement doit définir une date d\'expiration.',
      });
    }
  });

export type DecorTemplate = z.infer<typeof DecorTemplate>;
export type Layer = z.infer<typeof Layer>;
export type PhotoSlotLayer = z.infer<typeof PhotoSlotLayer>;
export type ImageLayer = z.infer<typeof ImageLayer>;
export type TextLayer = z.infer<typeof TextLayer>;
export type FilterId = z.infer<typeof FilterId>;
