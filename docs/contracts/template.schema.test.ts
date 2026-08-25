/**
 * Vérifie les invariants structurels du contrat DecorTemplate.
 * Vérifié avec zod 3.25.76 / TypeScript 5 (strict) — 8/8 au 25/08/2026.
 * À rebrancher sur Vitest lors de la mise en place du dépôt applicatif.
 */
import { DecorTemplate, canTransition } from './template.schema.js';

const base = {
  id: '3f2504e0-4f89-41d3-9a0c-0305e82c3301',
  slug: 'festival-lome-2026',
  title: 'Festival de Lomé 2026',
  city: 'lome' as const,
  canvas: { ratio: '1:1' as const, width: 1080, height: 1080 },
  layers: [
    { type: 'photoSlot' as const, id: 'photo', rect: { x: 0.1, y: 0.12, w: 0.8, h: 0.6 } },
    { type: 'image' as const, id: 'frame', src: 'https://cdn.wakabi.test/frames/festival.webp' },
    { type: 'text' as const, id: 'name', rect: { x: 0.1, y: 0.78, w: 0.8, h: 0.08 },
      editable: true, placeholder: 'Ton prénom', maxLength: 20 },
  ],
  export: {}, share: {},
};

function check(label: string, input: unknown, expectOk: boolean) {
  const r = DecorTemplate.safeParse(input);
  const pass = r.success === expectOk;
  console.log(`${pass ? 'PASS' : 'FAIL'}  ${label}`);
  if (!r.success && !expectOk) console.log(`        └─ ${r.error.issues[0].message}`);
  if (!r.success && expectOk) console.log(`        └─ UNEXPECTED: ${JSON.stringify(r.error.issues, null, 2)}`);
  if (!pass) process.exitCode = 1;
}

check('modèle valide', base, true);
check('zéro photoSlot', { ...base, layers: base.layers.filter(l => l.type !== 'photoSlot') }, false);
check('deux photoSlots', { ...base, layers: [...base.layers, base.layers[0]] }, false);
check('cadre sous la photo', { ...base, layers: [base.layers[1], base.layers[0], base.layers[2]] }, false);
check('ratio incohérent', { ...base, canvas: { ratio: '9:16', width: 1080, height: 1080 } }, false);
check('événement sans expiration', { ...base, eventId: 'evt_42' }, false);
check('événement avec expiration', { ...base, eventId: 'evt_42', expiresAt: '2026-12-01T00:00:00Z' }, true);
check('slug invalide (majuscules)', { ...base, slug: 'Festival Lome' }, false);
check('partenaire sans redirection', { ...base, partnerId: 'ptn_7' }, false);
check('partenaire avec redirection',
  { ...base, partnerId: 'ptn_7', share: { redirectUrl: 'https://wakabileguide.com/p/le-maquis' } }, true);
check('filtre retiré du périmètre v1', { ...base, filters: ['none', 'vintage'] }, false);

/* ---- Modération ------------------------------------------------------ */
const T0 = '2026-08-25T10:00:00Z';
check('partenaire publié SANS relecture',
  { ...base, createdBy: 'partner', status: 'published', publishedAt: T0 }, false);
check('partenaire publié APRÈS relecture',
  { ...base, createdBy: 'partner', status: 'published', publishedAt: T0,
    moderation: { reviewedBy: 'wp_12', reviewedAt: T0 } }, true);
check('équipe Wakabi publie directement',
  { ...base, createdBy: 'wakabi-team', status: 'published', publishedAt: T0 }, true);
check('refus sans motif', { ...base, status: 'rejected' }, false);
check('refus avec motif',
  { ...base, status: 'rejected', moderation: { reviewNote: 'Visuel non libre de droits.' } }, true);
check('correction demandée sans motif', { ...base, status: 'changes_requested' }, false);
check('en relecture sans date de soumission', { ...base, status: 'pending_review' }, false);
check('en relecture avec date',
  { ...base, status: 'pending_review', moderation: { submittedAt: T0, submittedBy: 'wp_44' } }, true);
check('publié sans publishedAt', { ...base, status: 'published' }, false);
check('partenaire redirige vers son propre site',
  { ...base, createdBy: 'partner', partnerId: 'ptn_7',
    share: { redirectUrl: 'https://mon-restaurant.tg/promo' } }, false);
check('partenaire redirige vers Wakabi',
  { ...base, createdBy: 'partner', partnerId: 'ptn_7',
    share: { redirectUrl: 'https://wakabileguide.com/p/le-maquis' } }, true);
check('équipe Wakabi peut rediriger ailleurs (co-branding)',
  { ...base, createdBy: 'wakabi-team', partnerId: 'ptn_7',
    share: { redirectUrl: 'https://festival-partenaire.tg' } }, true);

/* ---- Transitions ----------------------------------------------------- */
function t(from: any, to: any, actor: any, expect: boolean, label: string) {
  const got = canTransition(from, to, actor);
  const pass = got === expect;
  console.log(`${pass ? 'PASS' : 'FAIL'}  ${label}`);
  if (!pass) process.exitCode = 1;
}
console.log('');
t('draft', 'pending_review', 'partner', true,  'partenaire soumet son brouillon');
t('draft', 'published', 'partner', false,      'partenaire NE PEUT PAS publier directement');
t('draft', 'published', 'wakabi-team', true,   'équipe publie directement');
t('pending_review', 'published', 'wakabi-team', true, 'équipe approuve');
t('pending_review', 'published', 'partner', false,    'partenaire NE PEUT PAS s\'auto-approuver');
t('pending_review', 'changes_requested', 'wakabi-team', true, 'équipe demande des corrections');
t('changes_requested', 'pending_review', 'partner', true,     'partenaire re-soumet après correction');
t('published', 'archived', 'wakabi-team', true,  'équipe archive');
t('published', 'archived', 'partner', false,     'partenaire NE PEUT PAS archiver un décor publié');
console.log('');

// Vérifie que les valeurs par défaut sont bien appliquées.
const ok = DecorTemplate.parse(base);
console.log('\nDéfauts appliqués :');
console.log('  export.maxPx      =', ok.export.maxPx);
console.log('  export.quality    =', ok.export.quality);
console.log('  export.formats    =', ok.export.formats);
console.log('  filters           =', ok.filters);
console.log('  status            =', ok.status);
console.log('  photoSlot.fit     =', (ok.layers[0] as any).fit);
console.log('  photoSlot.mask    =', JSON.stringify((ok.layers[0] as any).mask));
console.log('  text.font/size    =', (ok.layers[2] as any).font, (ok.layers[2] as any).size);
console.log('  watermark         =', JSON.stringify(ok.watermark));
console.log('  redirectLabel     =', ok.share.redirectLabel);
console.log('  createdBy         =', ok.createdBy);
console.log('  allowRotation     =', (ok.layers[0] as any).allowRotation);
