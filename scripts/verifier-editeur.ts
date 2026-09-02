/**
 * L'éditeur d'article, mis à l'épreuve de l'aller-retour.
 *
 * L'éditeur traduit dans les deux sens — les marques vers un DOM
 * éditable, et le DOM vers les marques. Deux traducteurs qui divergent
 * font un aperçu qui ment : on écrit quelque chose, on relit autre chose,
 * et la page publiée est une troisième version. C'est le genre de défaut
 * qu'on ne voit qu'après avoir publié.
 *
 * Trois choses sont vérifiées ici, sur un corpus :
 *
 *  1. **L'aller-retour est stable.** marques → DOM → marques rend le même
 *     texte. Sinon, ouvrir un article et l'enregistrer sans y toucher le
 *     modifierait — la pire forme de perte, parce que silencieuse.
 *  2. **Le rendu correspond à celui de PHP.** L'éditeur montre ce que
 *     `texte_riche()` publiera, balise pour balise.
 *  3. **Ce qui vient d'ailleurs ne survit pas.** Du HTML collé — styles,
 *     scripts, tableaux de Word — est ramené à du texte par la
 *     sérialisation. C'est ce qui permet au serveur de continuer à ne
 *     recevoir que du texte.
 *
 * Le tout dans un VRAI navigateur : le DOM d'un éditeur `contenteditable`
 * n'est pas celui qu'on croit, et le simuler prouverait seulement que la
 * simulation est d'accord avec elle-même.
 *
 *   npx tsx scripts/verifier-editeur.ts
 */
import { chromium } from 'playwright-core';
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { mkdtempSync, writeFileSync, rmSync, readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { build } from 'esbuild';

const EXE = process.env.CHROMIUM ?? '/opt/pw-browsers/chromium-1194/chrome-linux/chrome';
const lancerPhp = promisify(execFile);

let pass = 0;
let fail = 0;
const ok = (label: string, cond: boolean, detail = '') => {
  cond ? pass++ : fail++;
  console.log(`  ${cond ? '✓' : '✗'} ${label}${detail ? ' — ' + detail : ''}`);
};

/**
 * Un nom de média BIEN FORMÉ mais qui ne désigne aucun fichier.
 *
 * Les deux côtés prennent alors le même chemin — celui où l'on ne peut
 * fabriquer aucune vignette — et l'on compare ce qui doit l'être : la
 * figure, sa largeur, sa légende. Pointer un vrai fichier ferait dépendre
 * le vérifieur de l'état d'une installation de développement.
 */
const FAUX = '00000000-0000-4000-8000-000000000000.png';

/** Le corpus : chaque forme reconnue, et quelques pièges. */
const CORPUS: [string, string][] = [
  ['un paragraphe simple', 'Une soirée au Maquis Akwaba, un samedi de mars.'],
  ['deux paragraphes', 'Le premier paragraphe.\n\nLe second, après une ligne vide.'],
  ['un intertitre', '## Ce qui a marché\n\nQuatre cents personnes, sans une affiche.'],
  ['un sous-titre', '### Le détail\n\nEt le texte qui suit.'],
  ['du gras', 'Nous étions **quatre cents**, ce soir-là.'],
  ['de l’italique', 'C’était *presque* trop de monde.'],
  ['du code', 'Le paramètre `slug` ne change plus après publication.'],
  ['une liste', '- 1 200 badges créés\n- 62 % présentés à l’entrée\n- 0 franc d’impression'],
  ['une citation', '> On a su qui venait avant d’ouvrir les portes.'],
  ['un lien', 'Tout est là : [le guide](https://wakabileguide.com/).'],
  ['tout ensemble',
    '## Ce qui a marché\n\nÀ Lomé, **400 personnes** sans une seule affiche.\n\n'
    + '- 1 200 badges en 9 jours\n- 62 % à l’entrée\n\n'
    + '> On a su qui venait.\n\n### Ce qu’on referait\n\n'
    + 'Publier plus tôt, et lire [le guide](https://wakabileguide.com/) avant.'],
  ['des accents et des apostrophes', 'Ça n’a pas marché : l’été, à Cotonou, personne ne sort.'],
  ['une image', 'Avant.\n\n![La salle au complet](?p=media&f=' + FAUX + ')\n\nAprès.'],
  ['une image sans légende', '![](?p=media&f=' + FAUX + ')'],
  ['une image recadrée', '![Le podium](?p=media&f=' + FAUX + '&c=299-248-701-752)'],
  ['une image redimensionnée', '![Le podium](?p=media&f=' + FAUX + '&t=60)'],
  ['une image recadrée ET redimensionnée',
    '![Le podium](?p=media&f=' + FAUX + '&c=100-100-800-600&t=45)'],
];

const main = async () => {
  console.log('\n━━ L’éditeur d’article, à l’aller et au retour ━━\n');

  const dossier = mkdtempSync(join(tmpdir(), 'wakabi-editeur-'));

  /* Le module de l'éditeur, seul, chargeable dans une page. */
  await build({
    entryPoints: ['php/studio/editeur.ts'],
    bundle: true, format: 'iife', globalName: 'Editeur', target: 'es2019',
    outfile: join(dossier, 'editeur.js'), logLevel: 'silent',
  });
  writeFileSync(join(dossier, 'essai.html'),
    '<!doctype html><meta charset="utf-8"><div id="zone" contenteditable="true"></div>'
    + `<script>${readFileSync(join(dossier, 'editeur.js'), 'utf8')}</script>`);

  const navigateur = await chromium.launch({ executablePath: EXE });
  const p = await navigateur.newPage();
  await p.goto('file://' + join(dossier, 'essai.html'));

  /* ---- 1. l'aller-retour est stable ---- */

  for (const [nom, texte] of CORPUS) {
    const retour = await p.evaluate((t) => {
      const z = document.getElementById('zone') as HTMLElement;
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      const E = (window as any).Editeur;
      z.innerHTML = E.marquesVersHtml(t);
      return E.htmlVersMarques(z) as string;
    }, texte);
    ok(`aller-retour stable : ${nom}`, retour === texte,
       retour === texte ? '' : JSON.stringify(retour).slice(0, 76));
  }

  /* ---- 2. le rendu est celui que PHP publiera ---- */

  const fichier = join(dossier, 'rendu.php');
  writeFileSync(fichier, `<?php
    require ${JSON.stringify(process.cwd() + '/php')} . '/app/bootstrap.php';
    require RACINE . '/app/texte.php';
    // Sans lui, \`image_article()\` se tait : c'est \`image_reduite()\` qui
    // décide de ce qu'un \`<img>\` contient, et la page publiée le charge.
    require RACINE . '/app/images.php';
    foreach (json_decode(file_get_contents(${JSON.stringify(join(dossier, 'corpus.json'))}), true) as $t) {
      echo str_replace("\\n", '', texte_riche($t)), "\\n";
    }
  `);
  writeFileSync(join(dossier, 'corpus.json'), JSON.stringify(CORPUS.map(([, t]) => t)));
  const cotePhp = (await lancerPhp('php', [fichier])).stdout.trimEnd().split('\n');

  const coteNavigateur = await p.evaluate((textes) => textes.map((t) => {
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    return ((window as any).Editeur.marquesVersHtml(t) as string);
  }), CORPUS.map(([, t]) => t));

  /**
   * Le HTML de l'éditeur n'est pas censé être identique à celui de la
   * page : l'éditeur n'y met ni `target` ni `rel`, qui n'ont aucun sens
   * dans un champ de saisie. On compare donc la STRUCTURE — les balises,
   * dans l'ordre, avec leur texte.
   */
  const structure = (html: string): string =>
    html
      .replace(/\s+(target|rel|loading|decoding|contenteditable|srcset|sizes|width|height|data-t)="[^"]*"/g, '')
      /**
       * L'adresse d'une image DIFFÈRE des deux côtés, et c'est voulu :
       * l'éditeur demande le média, la page publiée demande la vignette
       * fabriquée à partir du même fichier. Ce qui doit coïncider est le
       * FICHIER, la figure qui l'entoure, sa largeur et sa légende.
       */
      .replace(/src="[^"]*?([0-9a-f]{8}(?:-[0-9a-f]{4}){3}-[0-9a-f]{12})[^"]*"/g, 'src="$1"')
      .replace(/>\s+</g, '><').trim();

  let ecarts = 0;
  CORPUS.forEach(([nom], i) => {
    if (structure(cotePhp[i] ?? '') !== structure(coteNavigateur[i] ?? '')) {
      ecarts++;
      console.log(`      php  : ${structure(cotePhp[i] ?? '').slice(0, 96)}`);
      console.log(`      écran: ${structure(coteNavigateur[i] ?? '').slice(0, 96)}  (${nom})`);
    }
  });
  ok('l’éditeur affiche ce que PHP publiera, balise pour balise',
     ecarts === 0, ecarts === 0 ? `${CORPUS.length} formes` : `${ecarts} écart(s)`);

  /* ---- 3. ce qui vient d'ailleurs ne survit pas ---- */

  const collages: [string, string, (s: string) => boolean][] = [
    ['un script', '<p>Bonjour<script>window.x=1</script> tout le monde</p>',
     (s) => s === 'Bonjour tout le monde'],
    ['un style en dur', '<p><span style="color:red;font-size:40px">Rouge</span></p>',
     (s) => s === 'Rouge'],
    ['un tableau de traitement de texte',
     '<table><tr><td>Une</td><td>cellule</td></tr></table>',
     (s) => s === 'Une cellule'],
    ['un lien javascript:', '<p><a href="javascript:alert(1)">cliquez</a></p>',
     (s) => !/javascript/.test(s) && s.includes('cliquez')],
    ['une image', '<p>Avant<img src="x" onerror="alert(1)">après</p>',
     (s) => !/img|onerror/.test(s)],
    ['du gras de navigateur', '<p><b>gras</b> et <i>penché</i></p>',
     (s) => s === '**gras** et *penché*'],
  ];

  for (const [nom, html, attendu] of collages) {
    const marques = await p.evaluate((h) => {
      const z = document.getElementById('zone') as HTMLElement;
      z.innerHTML = h;
      // eslint-disable-next-line @typescript-eslint/no-explicit-any
      return (window as any).Editeur.htmlVersMarques(z) as string;
    }, html);
    ok(`${nom} ne ressort pas en HTML`, attendu(marques), JSON.stringify(marques).slice(0, 60));
  }

  /**
   * Et le tour complet : ce qu'un collage hostile finit par produire sur
   * la page publiée. C'est la seule question qui compte vraiment.
   */
  const marquesHostiles = await p.evaluate(() => {
    const z = document.getElementById('zone') as HTMLElement;
    z.innerHTML = '<p>Bonjour <script>window.x=1</script>'
                + '<a href="javascript:alert(1)">ici</a>'
                + '<img src=x onerror="alert(2)"></p>';
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    return (window as any).Editeur.htmlVersMarques(z) as string;
  });
  writeFileSync(fichier, `<?php
    require ${JSON.stringify(process.cwd() + '/php')} . '/app/bootstrap.php';
    require RACINE . '/app/texte.php';
    echo texte_riche(${JSON.stringify(marquesHostiles)});
  `);
  const publie = (await lancerPhp('php', [fichier])).stdout;
  ok('rien d’exécutable n’atteint la page publiée',
     !/<script|javascript:|onerror/i.test(publie), publie.replace(/\s+/g, ' ').slice(0, 70));

  await navigateur.close();
  rmSync(dossier, { recursive: true, force: true });
  console.log(`\n━━ ${pass} réussis, ${fail} échoués ━━\n`);
  process.exit(fail ? 1 : 0);
};

main();
