/**
 * Les adresses : l'aller-retour, et ce qui ne doit JAMAIS être traduit.
 *
 * Deux fonctions se font face dans `app/permaliens.php` : celle qui ÉCRIT
 * une adresse lisible, et celle qui la RELIT. Elles ne s'appellent pas, et
 * ne peuvent pas s'appeler : l'une part de paramètres, l'autre d'un chemin.
 * Rien dans le code ne les force donc à s'accorder, et le jour où elles
 * divergeraient, le site distribuerait des adresses qu'il ne sait plus
 * lire. Un visiteur verrait une page introuvable là où le catalogue lui
 * promettait un décor, et personne ne le remarquerait en développant.
 *
 * Ce vérifieur ferme cet écart, et lui seul peut le faire : la mesure est
 * entièrement en PHP, invisible depuis un navigateur. Il éprouve aussi la
 * frontière, qui est le vrai garde-fou du réglage : ni l'espace de travail,
 * ni l'API, ni les vignettes de partage ne doivent changer d'adresse.
 *
 *   npx tsx scripts/verifier-permaliens.ts
 */

import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { mkdtempSync, mkdirSync, writeFileSync, rmSync } from 'node:fs';
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

/** Ce qu'on écrit, et ce qu'on doit relire. */
const ALLERS_RETOURS: [string, string][] = [
  ['?p=accueil', ''],
  ['?p=decors', 'decors'],
  ['?p=decors&n=2', 'decors/page/2'],
  // La page 1 n'a pas de numéro : deux adresses pour la même page seraient
  // un doublon qu'un moteur compte deux fois.
  ['?p=decors&n=1', 'decors'],
  ['?p=decor&slug=soiree-blanche-lome', 'decors/soiree-blanche-lome'],
  // Un décor peut s'appeler « Page ». L'ambiguïté avec la pagination
  // n'existe qu'à trois segments, où `page` est suivi d'un nombre.
  ['?p=decor&slug=page', 'decors/page'],
  ['?p=blog', 'blog'],
  ['?p=blog&n=3', 'blog/page/3'],
  ['?p=blog&a=remplir-une-salle', 'blog/remplir-une-salle'],
  // Les articles venus du WordPress du guide passent par `guide/` : rien
  // n'empêche deux articles de porter le même slug de chaque côté.
  ['?p=blog&g=venu-du-guide', 'blog/guide/venu-du-guide'],
  ['?p=boost-push', 'push'],
  ['?p=boost-regie', 'regie'],
  ['?p=boost-liens', 'liens-courts'],
  ['?p=cgu', 'cgu'],
  ['?p=confidentialite', 'confidentialite'],
  // Ce qui n'a pas de place dans le chemin repart en requête : la
  // traduction ne perd jamais rien.
  ['?p=decors&q=lome', 'decors?q=lome'],
  ['?p=blog&a=x&ok=1', 'blog/x?ok=1'],
];

/**
 * Ce qui doit garder sa forme, et pourquoi.
 *
 * L'espace de travail ne se partage pas et chaque adresse réécrite y est
 * une occasion de casser un formulaire. Les vignettes de partage, elles,
 * sont déjà dans des caches de messageries qu'on ne peut pas invalider :
 * changer leur adresse, c'est vider la carte de chaque lien déjà posté.
 */
const JAMAIS_TRADUITS = ['?p=admin', '?p=reglages', '?p=partenaire', '?p=regie',
  '?p=og&slug=x', '?p=vignette&f=y', '?p=api-badge', '?p=media&f=z',
  '?p=l&c=AbC123', '?p=qr&jeton=J'];

const main = async () => {
  console.log('\n━━ Les adresses : aller-retour et frontière ━━\n');

  /* Une base à soi : ce vérifieur change un réglage, et il n'a aucune
     raison de toucher celui de l'installation de développement. */
  const dossier = mkdtempSync(join(tmpdir(), 'wakabi-permaliens-'));
  mkdirSync(join(dossier, 'donnees'), { recursive: true });
  writeFileSync(join(dossier, 'config.php'), `<?php return ['sgbd' => 'sqlite',
    'dossier_donnees' => ${JSON.stringify(join(dossier, 'donnees'))},
    'fichier' => ${JSON.stringify(join(dossier, 'donnees', 'wakabi.sqlite'))}];`);

  const script = `<?php
    require ${JSON.stringify(RACINE)} . '/app/bootstrap.php';
    require RACINE . '/app/schema.php';
    require RACINE . '/app/gabarit.php';
    require RACINE . '/app/auth.php';
    require RACINE . '/app/depot.php';
    assurer_schema();
    reglages_bdd_poser(['permaliens_forme' => 'lisible', 'permaliens_verifie' => maintenant()]);

    $sortie = ['allers' => [], 'jamais' => [], 'barre' => []];
    foreach (${JSON.stringify(ALLERS_RETOURS.map((x) => x[0]))} as $c) {
        $joli = permaliens_jolie($c);
        $retour = null;
        if ($joli !== null) {
            [$chemin, $q] = array_pad(explode('?', $joli, 2), 2, '');
            $r = permaliens_resoudre($chemin) ?? [];
            parse_str($q, $reste);
            $retour = $r + $reste;
            ksort($retour);
        }
        $sortie['allers'][$c] = ['joli' => $joli, 'retour' => $retour];
    }
    foreach (${JSON.stringify(JAMAIS_TRADUITS)} as $c) {
        $sortie['jamais'][$c] = permaliens_jolie($c);
    }

    /* La barre finale : les deux formes doivent se relire, quel que soit
       le réglage. Une page qui répondrait à l'une et pas à l'autre ferait
       un 404 sur un lien recopié à une barre près. */
    reglages_bdd_poser(['permaliens_barre' => '1']);
    $sortie['barre']['ecrit'] = permaliens_jolie('?p=decors');
    foreach (['decors', 'decors/', '/decors', 'DECORS'] as $c) {
        $sortie['barre'][$c] = permaliens_resoudre($c);
    }

    /* Et la résolution ne consulte AUCUN réglage : c'est la règle qui rend
       le retour en arrière sans danger. */
    reglages_bdd_poser(['permaliens_forme' => 'simple']);
    $sortie['eteint'] = [
        'ecrit' => permaliens_jolie('?p=decors'),
        'relit' => permaliens_resoudre('decors/soiree-blanche'),
    ];
    echo json_encode($sortie, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
  `;
  const fichier = join(dossier, 'controle.php');
  writeFileSync(fichier, script);

  const { stdout } = await lancer('php', [fichier], {
    env: { ...process.env, WAKABI_CONFIG: join(dossier, 'config.php') },
  });
  const r = JSON.parse(stdout) as {
    allers: Record<string, { joli: string | null; retour: Record<string, string> | null }>;
    jamais: Record<string, string | null>;
    barre: Record<string, unknown>;
    eteint: { ecrit: string | null; relit: Record<string, string> | null };
  };

  /* --- 1. ce qu'on écrit est bien ce qu'on attend --- */
  for (const [simple, attendu] of ALLERS_RETOURS) {
    const vu = r.allers[simple]?.joli;
    ok(`${simple}  s’écrit  /${attendu}`, vu === attendu, vu === undefined ? 'absent' : `/${vu}`);
  }

  /* --- 2. et se relit à l'identique --- */
  let divergent = 0;
  for (const [simple] of ALLERS_RETOURS) {
    const attendu: Record<string, string> = { p: 'accueil' };
    for (const [c, v] of new URLSearchParams(simple.slice(1))) attendu[c] = v;
    // La page 1 ne s'écrit pas, donc ne se relit pas : c'est voulu.
    if (attendu.n === '1') delete attendu.n;
    const retour = r.allers[simple]?.retour ?? {};
    const pareil = Object.keys(attendu).length === Object.keys(retour).length
      && Object.entries(attendu).every(([c, v]) => retour[c] === v);
    if (!pareil) {
      divergent++;
      console.log(`      ${simple} → ${JSON.stringify(retour)} au lieu de ${JSON.stringify(attendu)}`);
    }
  }
  ok(`les ${ALLERS_RETOURS.length} aller-retours rendent EXACTEMENT les mêmes paramètres`,
     divergent === 0, divergent ? `${divergent} divergence(s)` : '');

  /* --- 3. la frontière --- */
  const traduits = Object.entries(r.jamais).filter(([, v]) => v !== null);
  ok('l’espace de travail, l’API et les vignettes gardent leur adresse',
     traduits.length === 0, traduits.map(([c, v]) => `${c} → /${v}`).join(' · '));

  /* --- 4. la barre finale --- */
  ok('la barre finale, quand elle est demandée, s’écrit', r.barre.ecrit === 'decors/');
  ok('et les quatre écritures d’une même adresse se relisent toutes',
     ['decors', 'decors/', '/decors', 'DECORS']
       .every((c) => JSON.stringify(r.barre[c]) === JSON.stringify({ p: 'decors' })),
     JSON.stringify(r.barre));

  /* --- 5. la règle qui tient tout le reste --- */
  ok('réglage éteint, le site écrit de nouveau la forme simple', r.eteint.ecrit === null);
  ok('mais relit TOUJOURS une adresse lisible déjà partagée',
     JSON.stringify(r.eteint.relit) === JSON.stringify({ p: 'decor', slug: 'soiree-blanche' }),
     JSON.stringify(r.eteint.relit));

  rmSync(dossier, { recursive: true, force: true });
  console.log(`\n━━ ${pass} réussis, ${fail} échoués ━━\n`);
  process.exitCode = fail ? 1 : 0;
};

main().catch((e) => { console.error(e); process.exit(1); });
