/**
 * Vérifie que le gabarit produit par PHP satisfait le VRAI schéma zod.
 *
 * Les deux implémentations doivent produire exactement la même structure.
 * Sans ce contrôle, une valeur par défaut que zod applique et que PHP oublie
 * ne se manifeste qu'au rendu, dans le navigateur, sous forme d'écran vide.
 */
import { execFileSync } from 'node:child_process';
import { DecorTemplate } from '../src/core/template.schema';

let ok = 0, ko = 0;

/** Chemins où les deux objets diffèrent — zod ayant appliqué ses défauts. */
function ecarts(attendu: unknown, obtenu: unknown, chemin = ''): string[] {
  if (attendu === obtenu) return [];
  if (attendu === null || obtenu === null || typeof attendu !== 'object' || typeof obtenu !== 'object') {
    return [`${chemin} : zod=${JSON.stringify(attendu)} php=${JSON.stringify(obtenu)}`];
  }
  const out: string[] = [];
  for (const cle of new Set([...Object.keys(attendu), ...Object.keys(obtenu)])) {
    out.push(...ecarts(
      (attendu as Record<string, unknown>)[cle],
      (obtenu as Record<string, unknown>)[cle],
      chemin ? `${chemin}.${cle}` : cle,
    ));
  }
  return out;
}

for (const disposition of ['bandeau', 'angle', 'story', 'instagram', 'facebook', 'tiktok', 'vierge']) {
  const brut = execFileSync('php', ['scripts/gabarit-php.php', disposition]).toString();
  const php = JSON.parse(brut);

  // Le vocabulaire du statut et du rôle est en français côté PHP, par choix :
  // il vit dans la base et dans l'interface. Le renderer ne le lit jamais.
  // On le traduit ici pour ne comparer que ce qui compte : la structure.
  const pourZod = {
    ...php,
    status: { brouillon: 'draft', en_relecture: 'pending_review', publie: 'published' }[php.status as string] ?? php.status,
    createdBy: php.createdBy === 'equipe' ? 'wakabi-team' : 'partner',
  };
  for (const cle of ['partnerId', 'expiresAt', 'subtitle']) {
    if (pourZod[cle] === null) delete pourZod[cle];
  }

  const r = DecorTemplate.safeParse(pourZod);
  if (!r.success) {
    ko++;
    console.log(`  ✗ ${disposition} — le schéma refuse le gabarit PHP`);
    for (const i of r.error.issues.slice(0, 4)) {
      console.log(`      ${i.path.join('.')} : ${i.message}`);
    }
    continue;
  }

  // Le gabarit doit être COMPLET : zod ne doit rien avoir à ajouter, sinon
  // le renderer lira des champs absents côté PHP.
  // Seul ce que le renderer lit doit coïncider — le reste est vocabulaire.
  const LU_PAR_LE_RENDERER = ['canvas', 'layers', 'watermark', 'qr', 'export', 'filters'] as const;
  const manquants: string[] = [];
  for (const cle of LU_PAR_LE_RENDERER) {
    manquants.push(...ecarts((r.data as Record<string, unknown>)[cle], pourZod[cle], cle));
  }
  if (manquants.length) {
    ko++;
    console.log(`  ✗ ${disposition} — ${manquants.length} valeur(s) que zod ajoute et que PHP oublie :`);
    for (const m of manquants.slice(0, 8)) console.log(`      ${m}`);
  } else {
    ok++;
    console.log(`  ✓ ${disposition} — accepté par le schéma, aucun défaut manquant`);
  }
}

console.log(`\n━━ ${ok} conformes, ${ko} non conformes ━━`);
process.exitCode = ko ? 1 : 0;
