/**
 * Vérifie les invariants structurels du contrat DecorTemplate.
 * Vérifié avec zod 3.25.76 / TypeScript 5 (strict) — 8/8 au 25/08/2026.
 * À rebrancher sur Vitest lors de la mise en place du dépôt applicatif.
 */
import { DecorTemplate } from './template.schema.js';

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
