<?php
/**
 * Les rapports : un seul écran, trois portées, deux exports.
 *
 * La même adresse pour l'équipe et pour l'organisateur, comme la
 * facturation : un lien « voir le rapport » envoyé par courriel arrive au
 * bon endroit sans qu'on ait à expliquer lequel. Ce que le compte voit est
 * décidé par `rapport_portee()`, et par rien d'autre.
 *
 * CE QU'UN COMPTE DE LA MAISON N'A PAS
 *
 * Un éditeur ou un scanner travaille POUR le guide : il n'a ni décors à
 * lui, ni campagnes, ni audience. Lui ouvrir un écran qui parlerait de
 * « vos décors » n'aurait aucun sens, et lui donner celui de la plateforme
 * serait lui ouvrir des chiffres qui ne le regardent pas. Il est renvoyé
 * chez lui, exactement comme sur la facturation.
 */

declare(strict_types=1);

$u = exiger_role(...ROLES);
$equipe = droit($u, 'decors_tous');

if (!$equipe && (interne($u) || !droit($u, 'decors_siens'))) {
    rediriger(accueil_de($u));
}

/**
 * Les paramètres, réduits à des valeurs simples.
 *
 * `?decor[]=x` ferait arriver un TABLEAU là où le code attend une chaîne :
 * PHP le convertit en « Array » avec un avertissement, et l'avertissement
 * finit dans le journal du serveur à chaque visite d'un curieux. Un filtre
 * d'une ligne vaut mieux qu'une cascade de conversions défensives.
 */
$q = array_filter($_GET, 'is_scalar');
$r = rapport($u, $q);

/* ---------------- les exports ---------------- */

$sortie = (string) ($q['export'] ?? '');

if ($sortie === 'pdf') {
    $pdf = rapport_pdf($r);
    journal_ecrire($u, 'rapport.export', 'rapport', $r['portee']['cle'],
        (string) $r['portee']['titre'], $r['periode']['libelle'] . ' · PDF');
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . rapport_nom_fichier($r, 'pdf') . '"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

if ($sortie === 'csv') {
    $csv = rapport_csv($r);
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . rapport_nom_fichier($r, 'csv') . '"');
    header('Content-Length: ' . strlen($csv));
    echo $csv;
    exit;
}

/* ---------------- l'écran ---------------- */

/**
 * De quoi remplir le sélecteur de portée.
 *
 * L'équipe choisit un organisateur ; tout le monde choisit un décor. La
 * liste des décors est celle de la PORTÉE COURANTE, pas celle de la base :
 * un organisateur n'y voit que les siens, et le sélecteur ne peut donc pas
 * servir à découvrir les décors des autres.
 */
$mes_decors = $equipe
    ? db()->query('SELECT id, slug, titre FROM decors ORDER BY COALESCE(evenement_le, cree_le) DESC')
        ->fetchAll()
    : (function (array $u): array {
        $s = db()->prepare('SELECT id, slug, titre FROM decors WHERE auteur_id = ?
                            ORDER BY COALESCE(evenement_le, cree_le) DESC');
        $s->execute([(string) $u['id']]);
        return $s->fetchAll();
    })($u);

vue('rapports', [
    'titre' => 'Rapports · ' . $r['portee']['titre'],
    'moi' => $u,
    'equipe' => $equipe,
    'r' => $r,
    'decors_choisis' => array_slice($mes_decors, 0, 200),
    'organisateurs' => $equipe ? organisateurs_pour_rapport() : [],
]);
