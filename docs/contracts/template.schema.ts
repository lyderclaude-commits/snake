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
  z.enum([
    'brand.primary',   // #2563EB — bleu Wakabi
    'brand.secondary', // #0D9488 — teal
    'brand.accent',    // #F97316 — orange
    'brand.kori',      // #D97706 — or, réservé aux mentions Kori
    'brand.ink',       // texte sombre, sur aplat clair
    'brand.paper',     // texte clair, sur photo
  ]),
  HexColor,
]);

export const AspectRatio = z.enum(['1:1', '4:5', '9:16', '16:9']);

/**
 * Signature officielle de la marque, tranchée le 25/08/2026.
 * C'est la formule du logo. Le site utilise encore « bons coins » à sept
 * endroits (titres et métadonnées) — voir docs/02-CHARTE-WAKABI.md §2 bis.
 *
 * Ne jamais écrire cette chaîne en dur ailleurs : elle est incrustée dans
 * chaque visuel exporté, donc une divergence deviendrait définitive sur
 * des milliers de partages.
 */
export const WAKABI_TAGLINE = 'LE GUIDE DES BONS PLANS';

/**
 * Domaines vers lesquels un décor créé par un PARTENAIRE peut rediriger.
 * L'équipe Wakabi n'est pas contrainte (campagnes co-brandées, etc.).
 * À déplacer en configuration si la liste doit bouger sans déploiement.
 */
export const WAKABI_REDIRECT_HOSTS = [
  'wakabileguide.com',
  'studio.wakabileguide.com',
] as const;

/**
 * Filtres. Volontairement réduits : mougni, qui domine la catégorie, n'en
 * propose AUCUN — son éditeur se limite à zoom + position. Les filtres
 * allongent le parcours et contredisent le budget des 30 secondes.
 * On garde une signature Wakabi activable d'un geste, et c'est tout.
 * Voir docs/03-ANALYSE-MOUGNI.md §3 et §7.
 */
export const FilterId = z.enum([
  'none',
  'wakabi-blue',   // signature froide, calée sur --wk-primary #2563EB
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
  /**
   * Autoriser la rotation. Désactivé par défaut : mougni ne l'offre pas, et
   * chaque geste supplémentaire coûte sur le budget des 30 secondes.
   */
  allowRotation: z.boolean().default(false),
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
    /**
     * ATTENTION — ces identifiants sont des MÉTADONNÉES DESCRIPTIVES, pas des
     * jointures vivantes. Le WordPress de Wakabi sert le contenu éditorial
     * (articles, villes, partenaires) ; il n'expose ni événements ni
     * établissements — ces données vivent dans le back-end de l'app mobile,
     * hors de portée pour l'instant.
     *
     * L'ancrage réel passe donc par `share.redirectUrl` (une URL), et non
     * par une résolution d'identifiant. C'est plus simple, ça ne dépend
     * d'aucune API, et ça fonctionne dès la v1.
     */
    partnerId: z.string().optional(),
    placeId: z.string().optional(),
    eventId: z.string().optional(),
    /**
     * Qui a créé ce modèle. Le participant crée son badge SANS compte ;
     * seul l'organisateur doit être authentifié pour publier une campagne.
     * Deux rôles, deux niveaux de friction — l'arbitrage de mougni, et il
     * est juste.
     */
    createdBy: z.enum(['wakabi-team', 'partner']).default('wakabi-team'),
    /** ID utilisateur WordPress de l'auteur. */
    authorId: z.string().optional(),
    city: z.enum(['lome', 'cotonou', 'abidjan', 'all']).default('all'),
    rubrique: z
      .enum(['gastronomie', 'evenements', 'hebergements', 'culture', 'campagne'])
      .default('campagne'),

    /* ================================================================
       CYCLE DE VIE & MODÉRATION
       ----------------------------------------------------------------
       Les décors sont créés par l'équipe Wakabi ET par les partenaires,
       ces derniers passant obligatoirement par une relecture.

         draft ──submit──▶ pending_review ──approve──▶ published
           ▲                    │                          │
           │                    ├──request_changes──▶ changes_requested
           └────────────────────┘                          │
                                └──reject──▶ rejected      ├─archive─▶ archived
                                                           └─expiresAt─▶ expired

       Correspondance WordPress : draft / pending / publish sont natifs ;
       seuls changes_requested, rejected et expired demandent un
       register_post_status(). Voir docs/04-MODERATION.md.
       ================================================================ */
    status: z
      .enum([
        'draft',
        'pending_review',
        'changes_requested',
        'published',
        'rejected',
        'archived',
        'expired',
      ])
      .default('draft'),
    publishedAt: z.string().datetime().optional(),

    /**
     * Traçabilité de la relecture. Un décor de partenaire ne peut pas
     * atteindre `published` sans être passé par ici — invariant vérifié.
     */
    moderation: z
      .object({
        submittedAt: z.string().datetime().optional(),
        /** ID utilisateur WordPress de l'auteur de la soumission. */
        submittedBy: z.string().optional(),
        reviewedAt: z.string().datetime().optional(),
        /** ID utilisateur WordPress du relecteur Wakabi. */
        reviewedBy: z.string().optional(),
        /**
         * Motif, obligatoire pour un refus ou une demande de correction :
         * un partenaire doit toujours savoir quoi corriger.
         */
        reviewNote: z.string().max(600).optional(),
        /** Résumé du dernier contrôle automatique (§ Preflight). */
        preflightPassed: z.boolean().optional(),
      })
      .default({}),
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
    filters: z.array(FilterId).default(['none', 'wakabi-blue']),

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
      /**
       * Redirection après téléchargement — la brique d'ancrage.
       * mougni facture cette fonctionnalité dès 5 000 FCFA/mois ; chez nous
       * elle est gratuite et structurante : chaque badge téléchargé renvoie
       * vers la fiche Wakabi du partenaire ou de l'événement.
       * C'est le seul moment où l'attention de l'utilisateur est totale.
       */
      redirectUrl: z.string().url().optional(),
      redirectLabel: z.string().max(60).default('Découvrir sur Wakabi'),
    }),

    /**
     * Filigrane Wakabi. Systématique par défaut : l'outil est gratuit, donc
     * le logo voyage sur chaque partage — pure visibilité, sans l'arbitrage
     * commercial que mougni doit faire (son watermark est ce qu'on paie pour
     * retirer). Ne peut être levé que sur décision explicite.
     */
    watermark: z
      .object({
        enabled: z.boolean().default(true),
        position: z
          .enum(['bottom-right', 'bottom-left', 'bottom-center'])
          .default('bottom-right'),
        opacity: z.number().min(0.3).max(1).default(0.9),
        /**
         * Le logo Wakabi n'existe qu'en bitmap (`logo-w.png`) — pas de SVG
         * disponible. Trois variantes, par ordre de préférence :
         *
         *  - `lockup`   : pin + « WAKABI » + signature. Illisible sous ~320 px
         *                 de large : à réserver aux décors où le filigrane
         *                 est grand.
         *  - `wordmark` : pin + « WAKABI », sans la signature. Le défaut —
         *                 c'est la réduction standard d'un logo à trois
         *                 étages, et elle reste lisible en petit.
         *  - `text`     : « WAKABI » suivi de WAKABI_TAGLINE, composé en
         *                 Bricolage Grotesque. AUCUN fichier requis : permet
         *                 de développer J0/J1 sans attendre l'asset, et reste
         *                 un repli honnête si le bitmap est trop pauvre.
         */
        variant: z.enum(['wordmark', 'lockup', 'text']).default('wordmark'),
      })
      .default({
        enabled: true,
        position: 'bottom-right',
        opacity: 0.9,
        variant: 'wordmark',
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

    /* ---- Modération ---------------------------------------------- */

    // Le point central : un décor de partenaire ne peut pas être publié
    // sans relecture. C'est la règle que l'équipe a demandée.
    if (
      tpl.createdBy === 'partner' &&
      tpl.status === 'published' &&
      !tpl.moderation.reviewedBy
    ) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['moderation', 'reviewedBy'],
        message:
          'Un décor créé par un partenaire ne peut pas être publié sans relecture Wakabi.',
      });
    }

    // Un refus ou une demande de correction sans motif laisse le
    // partenaire sans rien à corriger.
    if (
      (tpl.status === 'rejected' || tpl.status === 'changes_requested') &&
      !tpl.moderation.reviewNote
    ) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['moderation', 'reviewNote'],
        message: `Le statut "${tpl.status}" exige un motif dans moderation.reviewNote.`,
      });
    }

    // Cohérence de la file d'attente : sans date de soumission, le décor
    // n'apparaît nulle part dans la file du relecteur.
    if (tpl.status === 'pending_review' && !tpl.moderation.submittedAt) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['moderation', 'submittedAt'],
        message: 'Un décor en relecture doit porter moderation.submittedAt.',
      });
    }

    if (tpl.status === 'published' && !tpl.publishedAt) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['publishedAt'],
        message: 'Un décor publié doit porter publishedAt.',
      });
    }

    // Toute la stratégie tient à ce que le badge ramène du monde sur
    // Wakabi (SPEC §1.3). Un décor publié qui ne renvoie nulle part est un
    // visuel offert sans contrepartie.
    if (tpl.status === 'published' && !tpl.share.redirectUrl) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['share', 'redirectUrl'],
        message:
          'Un décor publié doit définir share.redirectUrl : sans redirection, le badge ne ramène personne sur Wakabi.',
      });
    }

    // Un partenaire ne peut pas détourner la redirection vers son propre
    // site : le badge doit ramener sur Wakabi, sinon le partenaire récupère
    // le trafic sans que la plateforme en garde quoi que ce soit. C'est
    // toute la logique de l'ancrage (SPEC §1.3).
    if (tpl.createdBy === 'partner' && tpl.share.redirectUrl) {
      let host = '';
      try {
        host = new URL(tpl.share.redirectUrl).hostname.toLowerCase();
      } catch {
        host = '';
      }
      const allowed = WAKABI_REDIRECT_HOSTS.some(
        (h) => host === h || host.endsWith('.' + h),
      );
      if (!allowed) {
        ctx.addIssue({
          code: z.ZodIssueCode.custom,
          path: ['share', 'redirectUrl'],
          message:
            `Un décor de partenaire doit rediriger vers un domaine Wakabi (${WAKABI_REDIRECT_HOSTS.join(', ')}), pas vers "${host || 'URL invalide'}".`,
        });
      }
    }

    // La redirection est la brique d'ancrage : un décor rattaché à un
    // partenaire qui ne renvoie nulle part perd tout son intérêt.
    if (tpl.partnerId && !tpl.share.redirectUrl) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        path: ['share', 'redirectUrl'],
        message:
          'Un modèle rattaché à un partenaire doit définir share.redirectUrl (fiche Wakabi).',
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

/* ------------------------------------------------------------------ */
/* Transitions autorisées                                              */
/* ------------------------------------------------------------------ */

export type DecorStatus = DecorTemplate['status'];
export type ActorRole = 'wakabi-team' | 'partner';

/**
 * Qui a le droit de faire passer un décor d'un état à un autre.
 * Le partenaire soumet et corrige ; seule l'équipe Wakabi publie,
 * refuse ou demande des corrections. C'est exactement la mécanique
 * Contributeur → en attente → publication de WordPress.
 */
const TRANSITIONS: Record<DecorStatus, Partial<Record<DecorStatus, ActorRole[]>>> = {
  draft: {
    pending_review: ['partner'],                 // soumettre
    published:      ['wakabi-team'],             // publication directe
    archived:       ['wakabi-team', 'partner'],
  },
  pending_review: {
    published:         ['wakabi-team'],          // approuver
    changes_requested: ['wakabi-team'],
    rejected:          ['wakabi-team'],
    draft:             ['partner'],              // retirer sa soumission
  },
  changes_requested: {
    pending_review: ['partner'],                 // re-soumettre après correction
    draft:          ['partner'],
    rejected:       ['wakabi-team'],
  },
  published: {
    archived: ['wakabi-team'],
    expired:  ['wakabi-team'],                   // aussi posé par la tâche planifiée
    draft:    ['wakabi-team'],                   // dépublier pour retouche
  },
  rejected:  { draft: ['partner', 'wakabi-team'] },
  archived:  { draft: ['wakabi-team'] },
  expired:   { archived: ['wakabi-team'], draft: ['wakabi-team'] },
};

/** Une transition est-elle permise à cet acteur ? */
export function canTransition(
  from: DecorStatus,
  to: DecorStatus,
  actor: ActorRole,
): boolean {
  return TRANSITIONS[from]?.[to]?.includes(actor) ?? false;
}

/* ------------------------------------------------------------------ */
/* Contrôle automatique avant relecture humaine                        */
/* ------------------------------------------------------------------ */

/**
 * Rapport de « pré-vol ». Il tourne à la soumission, avant qu'un humain
 * ne regarde quoi que ce soit : l'objectif est que le relecteur Wakabi
 * n'ait à juger que ce qu'une machine ne peut pas juger — les droits sur
 * les visuels, la pertinence éditoriale, le ton.
 *
 * Voir docs/04-MODERATION.md pour le détail de chaque contrôle.
 */
export const PreflightReport = z.object({
  templateId: z.string(),
  ranAt: z.string().datetime(),
  passed: z.boolean(),
  checks: z.array(
    z.object({
      id: z.enum([
        'schema',            // le modèle valide le schéma Zod
        'photo-visible',     // le cadre ne recouvre pas la zone photo
        'text-fits',         // aucun texte ne déborde de son rect
        'watermark-clear',   // le filigrane n'est pas masqué
        'asset-format',      // PNG/WebP, canal alpha présent, pas de SVG
        'asset-weight',      // poids du cadre sous la limite
        'contrast',          // texte lisible sur photo claire ET sombre
      ]),
      status: z.enum(['pass', 'warn', 'fail']),
      message: z.string().max(300),
    }),
  ),
});

export type PreflightReport = z.infer<typeof PreflightReport>;

export type DecorTemplate = z.infer<typeof DecorTemplate>;
export type Layer = z.infer<typeof Layer>;
export type PhotoSlotLayer = z.infer<typeof PhotoSlotLayer>;
export type ImageLayer = z.infer<typeof ImageLayer>;
export type TextLayer = z.infer<typeof TextLayer>;
export type FilterId = z.infer<typeof FilterId>;
