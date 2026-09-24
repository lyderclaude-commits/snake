/**
 * Les brouillons anonymes, et les offres qu'on retire.
 *
 * Deux mécaniques que l'écran ne montre pas, et que seul un test qui
 * regarde la base peut éprouver.
 *
 * Le brouillon est la SEULE écriture du produit qui ne demande pas de
 * compte : n'importe qui peut en poser un, fichier compris. Trois choses
 * l'encadrent, et il faut vérifier les trois plutôt que les croire :
 *
 *  1. **Il périme.** 48 h, puis la ligne ET le fichier disparaissent.
 *  2. **Il se plafonne.** Une adresse ne laisse pas cent brouillons.
 *  3. **Il n'appartient qu'à son jeton.** Un autre jeton ne le voit pas,
 *     et un jeton bricolé n'ouvre rien.
 *
 * Et les fichiers ORPHELINS : un enregistrement interrompu entre le dépôt
 * du fichier et l'écriture de la ligne laisserait un fichier que plus rien
 * ne désigne. Rare, et pour cette raison jamais rattrapé à la main.
 *
 * Côté offres, le piège est commercial et non technique : supprimer une
 * offre que des comptes portent les basculerait en silence sur Découverte,
 * c'est-à-dire qu'on reprendrait ce qu'ils ont payé sans le leur dire.
 *
 *   npx tsx scripts/verifier-portes.ts
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

const SCENARIO = `<?php
declare(strict_types=1);
define('RACINE', ${JSON.stringify(RACINE)});
require ${JSON.stringify(RACINE)} . '/app/bootstrap.php';
foreach (['schema','auth','gabarit','depot','prevol','courriel','og','zip','sauvegarde',
          'texte','regie','carnet','canaux','images','push','qr','icones','avatars',
          'journal','abonnement','pdf','facture','rapport','segment','sponsor','sondage',
          'brouillon','api'] as $m) {
    require RACINE . "/app/$m.php";
}
assurer_schema();
$out = [];

/* ================= les brouillons ================= */

/**
 * On pose les jetons à la main plutôt que par le cookie : le scénario n'a
 * pas de navigateur, et c'est le comportement de la BASE qu'on éprouve.
 */
$poser = static function (string $jeton, string $genre, array $charge,
                          ?string $fichier, string $ip, int $dansHeures): string {
    $id = nouvel_id();
    db()->prepare('INSERT INTO brouillons (id, jeton, genre, charge, fichier, ip, cree_le, expire_le)
                   VALUES (?,?,?,?,?,?,?,?)')
        ->execute([$id, $jeton, $genre, json_encode($charge, JSON_UNESCAPED_UNICODE),
                   $fichier, $ip, maintenant(), maintenant(time() + $dansHeures * 3600)]);
    return $id;
};

$jetonA = str_repeat('a1', 24);
$jetonB = str_repeat('b2', 24);

/* Un fichier en quarantaine, pour de vrai : on veut le voir disparaître. */
$nomFichier = str_repeat('c3', 16) . '.png';
@mkdir(dossier_brouillons(), 0775, true);
file_put_contents(dossier_brouillons() . '/' . $nomFichier, "\\x89PNG faux mais pesant");

$poser($jetonA, 'lien', ['cible' => 'https://billetterie.example.tg/1'], null, '10.0.0.1', 48);
$poser($jetonA, 'decor', ['titre' => 'Gala'], $nomFichier, '10.0.0.1', 48);
$poser($jetonB, 'lien', ['cible' => 'https://autre.example.tg/2'], null, '10.0.0.2', 48);

/* Un brouillon déjà périmé, et son fichier. */
$nomPerime = str_repeat('d4', 16) . '.png';
file_put_contents(dossier_brouillons() . '/' . $nomPerime, 'perime');
$poser($jetonB, 'decor', ['titre' => 'Vieux'], $nomPerime, '10.0.0.2', -1);

/* Un fichier orphelin : aucune ligne ne le désigne, et il est vieux. */
$orphelin = str_repeat('e5', 16) . '.png';
file_put_contents(dossier_brouillons() . '/' . $orphelin, 'orphelin');
touch(dossier_brouillons() . '/' . $orphelin, time() - 72 * 3600);

/* --- ce que chaque jeton voit --- */
$_COOKIE[BROUILLON_COOKIE] = $jetonA;
$out['a_voit_lien']   = brouillon_prendre('lien')['charge']['cible'] ?? null;
$out['a_voit_decor']  = brouillon_prendre('decor')['charge']['titre'] ?? null;
$_COOKIE[BROUILLON_COOKIE] = $jetonB;
$out['b_voit_lien']   = brouillon_prendre('lien')['charge']['cible'] ?? null;
$_COOKIE[BROUILLON_COOKIE] = str_repeat('f6', 24);
$out['inconnu_voit']  = brouillon_prendre('lien') === null;
$_COOKIE[BROUILLON_COOKIE] = 'pas-un-jeton';
$out['bricole_voit']  = brouillon_prendre('lien') === null;

/* --- le plafond par adresse --- */
$refus = null;
$_SERVER['REMOTE_ADDR'] = '10.9.9.9';
for ($i = 0; $i < BROUILLON_PAR_IP + 4; $i++) {
    /**
     * Un jeton DIFFÉRENT à chaque tour, et vraiment différent.
     *
     * Un seul brouillon par genre et par jeton : deux tours qui partagent
     * un jeton s'effacent l'un l'autre, et le plafond semblerait tomber
     * un cran trop tard. Le format %048x donne quarante-huit caractères
     * hexadécimaux, c'est-à-dire la forme exacte qu'on accepte.
     */
    $_COOKIE[BROUILLON_COOKIE] = sprintf('%048x', $i);
    if (!brouillon_poser('lien', ['cible' => 'https://x.tg/' . $i])) {
        $refus = $i;
        break;
    }
}
$out['plafond_atteint_a'] = $refus;
$out['plafond'] = BROUILLON_PAR_IP;

/* --- la purge --- */
$out['fichier_perime_avant'] = is_file(dossier_brouillons() . '/' . $nomPerime);
$out['orphelin_avant'] = is_file(dossier_brouillons() . '/' . $orphelin);
$out['vivant_avant'] = is_file(dossier_brouillons() . '/' . $nomFichier);

$purge = brouillons_perimer();
$out['purge'] = $purge;
$out['fichier_perime_apres'] = is_file(dossier_brouillons() . '/' . $nomPerime);
$out['orphelin_apres'] = is_file(dossier_brouillons() . '/' . $orphelin);
$out['vivant_apres'] = is_file(dossier_brouillons() . '/' . $nomFichier);

$_COOKIE[BROUILLON_COOKIE] = $jetonA;
$out['a_survit'] = brouillon_prendre('decor')['charge']['titre'] ?? null;

/* --- l'adoption sort le fichier de quarantaine --- */
$adresse = brouillon_fichier_adopter($nomFichier);
$out['adopte_adresse'] = $adresse;
$out['adopte_quitte_quarantaine'] = !is_file(dossier_brouillons() . '/' . $nomFichier);
$out['adopte_arrive_aux_cadres'] = count(glob(dossier_cadres() . '/*')) > 0;
$out['adopte_refuse_nom_bricole'] = brouillon_fichier_adopter('../../config.php');

/* --- consommer efface --- */
$_COOKIE[BROUILLON_COOKIE] = $jetonA;
brouillon_consommer('decor');
$out['a_apres_consommation'] = brouillon_prendre('decor') === null;

/* ================= les offres ================= */

$out['semees'] = array_keys(formules());

/* Un compte qui porte Croissance : elle ne doit plus se supprimer. */
creer_utilisateur(['email' => 'ama@gala-akwaba.tg', 'mot_de_passe' => 'un-mot-de-passe-solide',
    'nom' => 'Ama Kodjo', 'role' => 'partenaire', 'formule' => 'croissance']);
$out['portee_croissance'] = formule_portee('croissance');
$out['portee_mouvement'] = formule_portee('mouvement');

/* Une offre neuve, avec une capacité que la constante ne connaît pas encore. */
db()->prepare('INSERT INTO formules (cle, nom, prix, lancement, rang, actif, tag, cta, phare, capacites, cree_le)
               VALUES (?,?,?,?,?,?,?,?,?,?,?)')
    ->execute(['essentiel', 'Essentiel', 3000, 1500, 1, 1, 'Pour démarrer', 'Choisir Essentiel', 0,
               json_encode(['campagnes' => 2, 'liens_courts' => 5]), maintenant()]);
formules_oublier();

$f = formules();
$out['neuve_lue'] = $f['essentiel']['liens_courts'] ?? null;
/* Les clés absentes retombent sur Découverte, jamais sur « oui ». */
$out['neuve_regie'] = $f['essentiel']['regie'] ?? 'absente';
$out['neuve_filigrane'] = $f['essentiel']['sans_filigrane'] ?? 'absente';
$out['ordre'] = array_keys($f);

/* Retirée de la vitrine : les comptes qui la portent ne perdent rien. */
db()->exec("UPDATE formules SET actif = 0 WHERE cle = 'croissance'");
formules_oublier();
$u = utilisateur_par_email('ama@gala-akwaba.tg');
$out['retiree_hors_vitrine'] = !isset(formules_actives()['croissance']);
$out['retiree_garde_ses_droits'] = capacite($u, 'regie');
$out['retiree_garde_son_quota'] = quota($u, 'liens_courts');
$out['retiree_ne_se_demande_plus'] = offre_demander($u, 'croissance');

/* La première offre qui débloque une ligne ne regarde que ce qui se vend. */
$out['qui_debloque_regie'] = offre_qui_debloque('regie');

echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\\n";
`;

const main = async () => {
  console.log('\n━━ Les portes : brouillons anonymes et offres retirées ━━\n');
  const dossier = mkdtempSync(join(tmpdir(), 'wakabi-portes-'));
  try {
    writeFileSync(join(dossier, 'config.php'), `<?php return ['sgbd' => 'sqlite',
      'dossier_donnees' => ${JSON.stringify(join(dossier, 'donnees'))},
      'fichier' => ${JSON.stringify(join(dossier, 'donnees', 'wakabi.sqlite'))}];`);
    const script = join(dossier, 'scenario.php');
    writeFileSync(script, SCENARIO);

    const { stdout } = await lancer('php', [script], {
      env: { ...process.env, WAKABI_CONFIG: join(dossier, 'config.php') },
      maxBuffer: 20 * 1024 * 1024,
    });
    // eslint-disable-next-line @typescript-eslint/no-explicit-any
    const r = JSON.parse(stdout.trim().split('\n').pop() ?? '{}') as Record<string, any>;

    /* ---------------- les brouillons ---------------- */
    console.log('  ── ce qu’un jeton voit, et ce qu’il ne voit pas ──');
    ok('un jeton retrouve son lien', r.a_voit_lien === 'https://billetterie.example.tg/1', String(r.a_voit_lien));
    ok('et son décor, qui est d’un autre genre', r.a_voit_decor === 'Gala', String(r.a_voit_decor));
    ok('un autre jeton voit le SIEN, pas celui du voisin',
       r.b_voit_lien === 'https://autre.example.tg/2', String(r.b_voit_lien));
    ok('un jeton inconnu n’ouvre rien', r.inconnu_voit === true);
    ok('un jeton bricolé non plus, et sans requête', r.bricole_voit === true);

    console.log('\n  ── le plafond par adresse ──');
    ok('une adresse ne laisse pas cent brouillons en attente',
       typeof r.plafond_atteint_a === 'number',
       `refusé au ${r.plafond_atteint_a}e, plafond ${r.plafond}`);
    ok('et le refus tombe AU plafond, pas au-delà',
       r.plafond_atteint_a === r.plafond, `${r.plafond_atteint_a} / ${r.plafond}`);

    console.log('\n  ── la purge, qui n’a personne pour la faire à la main ──');
    ok('le fichier périmé existait bien avant', r.fichier_perime_avant === true);
    ok('l’orphelin aussi', r.orphelin_avant === true);
    ok('un brouillon périmé est effacé',
       (r.purge?.brouillons ?? 0) >= 1, `${r.purge?.brouillons} brouillon(s)`);
    ok('son fichier part avec lui', r.fichier_perime_apres === false);
    ok('un fichier qu’aucune ligne ne désigne est ramassé aussi',
       r.orphelin_apres === false && (r.purge?.orphelins ?? 0) >= 1,
       `${r.purge?.orphelins} orphelin(s)`);
    ok('et un brouillon encore valable est intact', r.vivant_apres === true);
    ok('sa ligne aussi', r.a_survit === 'Gala', String(r.a_survit));

    console.log('\n  ── l’adoption : le fichier cesse d’être anonyme ──');
    ok('le fichier quitte la quarantaine', r.adopte_quitte_quarantaine === true);
    ok('et arrive parmi les cadres servis', r.adopte_arrive_aux_cadres === true);
    ok('sous la même adresse qu’un cadre téléversé normalement',
       typeof r.adopte_adresse === 'string' && r.adopte_adresse.includes('p=cadre'),
       String(r.adopte_adresse));
    ok('un nom bricolé n’adopte rien', r.adopte_refuse_nom_bricole === '');
    ok('consommer un brouillon l’efface', r.a_apres_consommation === true);

    /* ---------------- les offres ---------------- */
    console.log('\n  ── les offres, semées puis tenues ──');
    ok('la table se sème depuis la constante',
       Array.isArray(r.semees) && r.semees.includes('decouverte') && r.semees.includes('mouvement'),
       (r.semees ?? []).join(' · '));
    ok('une offre neuve se lit avec ses compteurs', r.neuve_lue === 5, String(r.neuve_lue));
    ok('une capacité absente vaut « non », jamais « oui »',
       r.neuve_regie === false && r.neuve_filigrane === false,
       `regie=${r.neuve_regie} filigrane=${r.neuve_filigrane}`);
    ok('et elle se range à l’ordre demandé',
       (r.ordre ?? [])[1] === 'essentiel', (r.ordre ?? []).join(' · '));

    console.log('\n  ── retirer de la vitrine n’est pas reprendre ──');
    ok('une offre portée par un compte est comptée comme telle',
       r.portee_croissance === 1, `${r.portee_croissance} compte(s)`);
    ok('une offre que personne ne porte est comptée à zéro',
       r.portee_mouvement === 0, `${r.portee_mouvement} compte(s)`);
    ok('retirée de la vente, elle disparaît de la vitrine', r.retiree_hors_vitrine === true);
    ok('mais celui qui la porte garde ses capacités', r.retiree_garde_ses_droits === true);
    ok('et son quota, au chiffre près', r.retiree_garde_son_quota === 100,
       String(r.retiree_garde_son_quota));
    ok('personne ne peut plus la demander, même en recopiant sa clé',
       r.retiree_ne_se_demande_plus === false);
    ok('et « quelle offre débloque la régie » ne la propose plus',
       r.qui_debloque_regie !== 'croissance', String(r.qui_debloque_regie));
  } finally {
    rmSync(dossier, { recursive: true, force: true });
  }

  console.log(`\n━━ ${pass} réussis, ${fail} échoués ━━\n`);
  process.exitCode = fail ? 1 : 0;
};

main();
