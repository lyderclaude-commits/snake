/**
 * Amorce la base : comptes de démonstration et les trois décors publiés.
 * Idempotent — relançable sans dupliquer.
 */
import { db, nowIso } from '../src/server/db';
import { createUser, findByEmail } from '../src/server/repo/users';
import * as decors from '../src/server/repo/decors';
import { TEMPLATES } from '../src/core/templates';

const ACCOUNTS = [
  { email: 'admin@wakabileguide.com', password: 'wakabi2026', name: 'Équipe Wakabi', role: 'admin' as const, city: 'lome' },
  { email: 'partenaire@chezlena.tg',  password: 'wakabi2026', name: 'Léna Adjovi',   role: 'partner' as const, organisation: 'Chez Léna', city: 'lome' },
  { email: 'kossi@exemple.tg',        password: 'wakabi2026', name: 'Kossi',          role: 'user' as const,    city: 'lome' },
];

const run = () => {
  db();
  const ids: Record<string, string> = {};
  for (const a of ACCOUNTS) {
    const existing = findByEmail(a.email);
    ids[a.role] = existing ? existing.id : createUser(a);
    console.log(`  ${existing ? '·' : '✓'} ${a.role.padEnd(7)} ${a.email}`);
  }

  for (const t of TEMPLATES) {
    if (decors.slugExists(t.slug)) { console.log(`  · décor ${t.slug}`); continue; }
    const id = decors.create({
      slug: t.slug, title: t.title, subtitle: t.subtitle, city: t.city,
      rubrique: t.rubrique, template: t,
      frameUrl: `/frames/${(t.layers.find((l) => l.type === 'image') as { src: string }).src.split('/').pop()}`,
      authorId: ids.admin, createdBy: 'wakabi-team',
    });
    decors.transition(id, 'published', { id: ids.admin, role: 'wakabi-team' });
    console.log(`  ✓ décor ${t.slug}`);
  }

  // Un décor en attente, pour que la file de relecture ne soit pas vide
  if (!decors.slugExists('soiree-maquis-akwaba')) {
    const base = TEMPLATES[1];
    const id = decors.create({
      slug: 'soiree-maquis-akwaba',
      title: 'Soirée au Maquis Akwaba',
      subtitle: 'Notre soirée live du samedi',
      city: 'abidjan', rubrique: 'evenements',
      template: { ...base, id: '44444444-4444-4444-8444-444444444444', slug: 'soiree-maquis-akwaba',
        title: 'Soirée au Maquis Akwaba', createdBy: 'partner', partnerId: 'ptn_akwaba',
        share: { ...base.share, redirectUrl: 'https://wakabileguide.com/p/maquis-akwaba' } },
      frameUrl: '/frames/bon-plan.png',
      authorId: ids.partner, createdBy: 'partner',
    });
    decors.transition(id, 'pending_review', { id: ids.partner, role: 'partner' });
    console.log('  ✓ décor soiree-maquis-akwaba (en attente de relecture)');
  }

  // Quelques événements, pour que les compteurs affichent autre chose que zéro
  const published = decors.listPublished();
  const seedCounts = [[41, 128], [17, 63], [9, 34]];
  published.forEach((d, i) => {
    if (d.downloads > 0) return;
    const [dl, vw] = seedCounts[i] ?? [5, 20];
    for (let k = 0; k < dl; k++) decors.track(d.id, 'download');
    for (let k = 0; k < vw; k++) decors.track(d.id, 'view');
  });
  console.log(`\n  Base prête — ${nowIso().slice(0, 10)}`);
};

run();
