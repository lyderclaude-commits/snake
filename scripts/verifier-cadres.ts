/**
 * Le compresseur de cadres : ce qu'il promet, et ce qu'il coûtait.
 *
 * `compresser_cadre()` tourne une fois par téléversement, et c'est le seul
 * endroit du produit où l'invité attend devant un écran pendant que le
 * serveur calcule. Deux défauts y vivaient, tous deux invisibles depuis un
 * navigateur, et tous deux coûteux :
 *
 *  1. UNE RÉDUCTION QUI GROSSISSAIT LES FICHIERS. Un cadre 9:16 fait
 *     1080 x 1920 ; ses 1920 px le faisaient ramener à 1600. Or
 *     rééchantillonner un graphisme à bords nets transforme chaque arête
 *     en dégradé : `story.png` passait de 23 à 107 Ko, `tiktok.png` de 20 à
 *     133. Le fichier gardé était alors l'ORIGINAL, et les trois secondes
 *     de calcul partaient à la poubelle.
 *
 *  2. UN PNG CALCULÉ POUR RIEN. Un encodage en niveau 9, le réglage le plus
 *     lent de GD, systématiquement perdu contre le WebP.
 *
 * Aucune de ces deux choses ne se voit à l'écran : le Studio finissait par
 * afficher le cadre, et personne ne pouvait dire d'où venaient les
 * secondes. D'où ce vérifieur, qui mesure les PROPRIÉTÉS plutôt que les
 * millisecondes — un chrono dans une recette ment dès qu'on change de
 * machine, une dimension et un poids, jamais.
 *
 *   npx tsx scripts/verifier-cadres.ts
 */

import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { mkdtempSync, writeFileSync, rmSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const lancer = promisify(execFile);
const RACINE = process.cwd() + '/php';

let pass = 0;
let fail = 0;
const ok = (label: string, cond: boolean, detail = '') => {
  cond ? pass++ : fail++;
  console.log(`  ${cond ? '✓' : '✗'} ${label}${detail ? ' — ' + detail : ''}`);
};

interface Sortie {
  cadres: { nom: string; avant: number; apres: number; l: number; h: number; ext: string }[];
  photo: { avant: number; apres: number; l: number; h: number; ext: string };
  webp: boolean;
}

const main = async () => {
  console.log('\n━━ Les cadres : ce que le compresseur rend vraiment ━━\n');

  const dossier = mkdtempSync(join(tmpdir(), 'wakabi-cadres-'));

  /**
   * Une photo, fabriquée plutôt que trouvée.
   *
   * Le cas qui compte n'est pas le cadre : c'est la PHOTO d'article, celle
   * qui doit continuer d'être réduite. Une correction qui accélérerait les
   * cadres en laissant passer un 3000 x 2000 entier serait une régression
   * déguisée en optimisation.
   */
  const photo = join(dossier, 'photo.png');
  const script = `<?php
    require ${JSON.stringify(RACINE)} . '/app/bootstrap.php';
    require RACINE . '/app/images.php';

    /* Une photo : des dégradés et du grain, pas d'aplats. */
    $im = imagecreatetruecolor(3000, 2000);
    for ($y = 0; $y < 2000; $y += 2) {
        for ($x = 0; $x < 3000; $x += 2) {
            $c = imagecolorallocate($im,
                (int) (120 + 80 * sin($x / 300)) & 255,
                (int) (110 + 70 * cos($y / 260)) & 255,
                (int) (140 + 60 * sin(($x + $y) / 400)) & 255);
            imagefilledrectangle($im, $x, $y, $x + 1, $y + 1, $c);
        }
    }
    imagepng($im, ${JSON.stringify(photo)});
    imagedestroy($im);

    $sortie = ['cadres' => [], 'webp' => webp_disponible()];
    foreach (glob(RACINE . '/public/cadres/*.png') as $c) {
        $copie = ${JSON.stringify(dossier)} . '/' . basename($c);
        copy($c, $copie);
        $r = compresser_cadre(${JSON.stringify(dossier)}, basename($c));
        $t = @getimagesize(${JSON.stringify(dossier)} . '/' . $r['nom']) ?: [0, 0];
        $sortie['cadres'][] = ['nom' => basename($c), 'avant' => $r['avant'], 'apres' => $r['apres'],
            'l' => (int) $t[0], 'h' => (int) $t[1], 'ext' => pathinfo($r['nom'], PATHINFO_EXTENSION)];
    }
    $r = compresser_cadre(${JSON.stringify(dossier)}, 'photo.png');
    $t = @getimagesize(${JSON.stringify(dossier)} . '/' . $r['nom']) ?: [0, 0];
    $sortie['photo'] = ['avant' => $r['avant'], 'apres' => $r['apres'],
        'l' => (int) $t[0], 'h' => (int) $t[1], 'ext' => pathinfo($r['nom'], PATHINFO_EXTENSION)];
    echo json_encode($sortie);
  `;
  const fichier = join(dossier, 'controle.php');
  writeFileSync(fichier, script);

  const { stdout } = await lancer('php', [fichier], { maxBuffer: 8 * 1024 * 1024 });
  const r = JSON.parse(stdout) as Sortie;

  ok('le WebP est disponible sur cette installation', r.webp);
  ok('les huit cadres livrés passent par le compresseur', r.cadres.length === 8,
     `${r.cadres.length} cadres`);

  /**
   * CHAQUE cadre doit SORTIR plus léger qu'il n'est entré.
   *
   * C'est l'assertion qui aurait attrapé le défaut : avant la correction,
   * `tiktok.png` et `228-playground-story.png` ressortaient à l'octet près
   * identiques, parce qu'aucun des deux candidats calculés n'avait battu
   * l'original. Trois secondes de calcul pour rien, et rien à l'écran pour
   * le dire.
   */
  const inchanges = r.cadres.filter((c) => c.apres >= c.avant);
  ok('aucun cadre ne ressort tel qu’il est entré', inchanges.length === 0,
     inchanges.map((c) => `${c.nom} ${Math.round(c.avant / 1024)} Ko`).join(' · '));

  const total = r.cadres.reduce((s, c) => s + c.apres, 0);
  ok('les huit cadres tiennent ensemble sous 300 Ko', total < 300 * 1024,
     `${Math.round(total / 1024)} Ko`);

  /**
   * Un cadre 9:16 garde ses 1920 px.
   *
   * C'est la correction elle-même, énoncée comme une propriété : ramener
   * 1920 à 1600 coûtait 17 % de pixels et rendait le fichier quatre à six
   * fois plus lourd. Si quelqu'un rétablissait un jour la réduction
   * systématique, ce sont ces trois lignes qui le diraient.
   */
  const hauts = r.cadres.filter((c) => c.h >= 1600);
  ok('les cadres 9:16 gardent leur taille d’origine', hauts.length >= 3
     && hauts.every((c) => c.h === 1920),
     hauts.map((c) => `${c.nom} ${c.l}x${c.h}`).join(' · ') || 'aucun cadre haut trouvé');

  const story = r.cadres.find((c) => c.nom === 'story.png');
  ok('et le plus lourd d’entre eux descend sous 40 Ko',
     !!story && story.apres < 40 * 1024 && story.ext === 'webp',
     story ? `${Math.round(story.avant / 1024)} → ${Math.round(story.apres / 1024)} Ko (${story.ext})` : 'absent');

  /**
   * La photo, elle, DOIT toujours être réduite.
   *
   * Le seuil ne dit pas « ne réduis plus jamais », il dit « ne réduis que
   * quand ça rapporte ». Trois mille pixels de côté, ça rapporte.
   */
  ok('une photo de 3000 px est toujours ramenée à la taille maximale',
     r.photo.l === 1600 && r.photo.h === 1067,
     `${r.photo.l}x${r.photo.h}`);
  ok('et elle en ressort nettement plus légère',
     r.photo.apres < r.photo.avant / 4,
     `${Math.round(r.photo.avant / 1024)} → ${Math.round(r.photo.apres / 1024)} Ko`);

  rmSync(dossier, { recursive: true, force: true });
  console.log(`\n━━ ${pass} réussis, ${fail} échoués ━━\n`);
  process.exitCode = fail ? 1 : 0;
};

main().catch((e) => { console.error(e); process.exit(1); });
