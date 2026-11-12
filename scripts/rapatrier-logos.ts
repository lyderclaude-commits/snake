/**
 * Rapatrier les logos des partenaires, une fois pour toutes.
 *
 * La page d'accueil les chargeait depuis `admin.wakabileguide.com` :
 * trois requêtes vers un domaine que le site ne tient pas, et trois
 * images qui disparaissent le jour où ce domaine bouge. Ce script les
 * descend dans `php/public/partenaires/`, d'où le zip les emporte.
 *
 * IL LES RÉDUIT AUSSI. Les originaux font jusqu'à 768 pixels de côté
 * pour paraître à quarante-quatre : on télécharge aujourd'hui neuf
 * dixièmes de pixels que le navigateur jette. Deux fois la hauteur
 * d'affichage suffit, écrans à forte densité compris.
 *
 * À lancer depuis une machine qui atteint le guide :
 *
 *     npx tsx scripts/rapatrier-logos.ts
 *
 * Il est rejouable : un fichier déjà là est réécrit, jamais dupliqué.
 * Et si le guide ne répond pas, il le dit et ne laisse pas un fichier à
 * moitié écrit derrière lui.
 */
import { mkdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import sharp from 'sharp';

const DOSSIER = join(process.cwd(), 'php', 'public', 'partenaires');

/** [adresse d'origine, nom du fichier, hauteur d'affichage en pixels] */
const LOGOS: [string, string, number][] = [
  ['https://admin.wakabileguide.com/wp-content/uploads/2026/05/SUDCOM-1-768x768-1.webp',
   'sudcom.webp', 44],
  ['https://admin.wakabileguide.com/wp-content/uploads/2026/05/LOGO-GNADOE-768x345-1-1.webp',
   'gnadoe.webp', 36],
  ['https://admin.wakabileguide.com/wp-content/uploads/2026/05/logo-actu-lome-01_1_11zon-scaled-1.webp',
   'actu-lome.webp', 36],
];

const main = async (): Promise<void> => {
  mkdirSync(DOSSIER, { recursive: true });
  let rates = 0;
  for (const [adresse, nom, hauteur] of LOGOS) {
    try {
      const r = await fetch(adresse);
      if (!r.ok) {
        throw new Error(`HTTP ${r.status}`);
      }
      const brut = Buffer.from(await r.arrayBuffer());
      /* Deux fois la hauteur d'affichage : net sur un écran à forte
         densité, sans payer le reste. `withoutEnlargement` protège un
         logo déjà petit d'un agrandissement qui ne lui apporterait que
         du flou. */
      const reduit = await sharp(brut)
        .resize({ height: hauteur * 2, withoutEnlargement: true })
        .webp({ quality: 82 })
        .toBuffer();
      writeFileSync(join(DOSSIER, nom), reduit);
      console.log(`  ✓ ${nom.padEnd(16)} ${Math.round(brut.length / 1024)} Ko `
        + `→ ${Math.round(reduit.length / 1024)} Ko`);
    } catch (e) {
      rates++;
      console.log(`  ✗ ${nom.padEnd(16)} ${(e as Error).message}`);
    }
  }
  console.log(rates === 0
    ? `\n  ${LOGOS.length} logos rapatriés dans php/public/partenaires/.`
    : `\n  ${rates} logo(s) manquant(s) : la page n’affichera que ceux qui sont là.`);
  process.exitCode = rates === 0 ? 0 : 1;
};

main();
