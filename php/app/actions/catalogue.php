<?php
/**
 * Le catalogue de l'équipe — voir, publier, archiver, supprimer.
 *
 * C'est l'écran depuis lequel l'équipe gère TOUS les décors, y compris ceux
 * des partenaires. Il complète la file de relecture, qui ne montre que ce
 * qui attend une décision.
 */

$u = exiger_droit('decors_tous');

/* ---------------- changement de statut ---------------- */

if ($page === 'statut') {
    verifier_csrf();
    $id = (string) ($_POST['id'] ?? '');
    $vers = (string) ($_POST['vers'] ?? '');
    $d = decor_par_id($id);
    if (!$d) {
        rediriger('?p=catalogue&err=' . urlencode('Décor introuvable.'));
    }
    try {
        decor_transition($id, $vers, $u, (string) ($_POST['motif'] ?? ''));
    } catch (RuntimeException $e) {
        rediriger('?p=catalogue&err=' . urlencode($e->getMessage()));
    }
    if ($d['auteur_id'] && $d['auteur_id'] !== $u['id']) {
        notifier($d['auteur_id'], 'decision', 'Votre décor a changé de statut',
                 $d['titre'] . ' : ' . statut_libelle($vers), '?p=partenaire');
    }
    rediriger('?p=catalogue&ok=' . urlencode($d['titre'] . ' : ' . statut_libelle($vers)));
}

/* ---------------- suppression ---------------- */

if ($page === 'supprimer') {
    verifier_csrf();
    $id = (string) ($_POST['id'] ?? '');
    $d = decor_par_id($id);
    if (!$d) {
        rediriger('?p=catalogue&err=' . urlencode('Décor introuvable.'));
    }

    // Deuxième garde-fou : le formulaire exige de retaper le titre. Supprimer
    // un décor détruit des badges déjà entre les mains d'invités — ça ne doit
    // pas tenir à un clic mal placé.
    $confirme = trim((string) ($_POST['confirmation'] ?? ''));
    if ($confirme !== $d['titre']) {
        rediriger('?p=catalogue&err=' . urlencode(
            'Suppression annulée : le titre saisi ne correspond pas à « ' . $d['titre'] . ' ».'
        ));
    }

    try {
        $bilan = decor_supprimer($id);
    } catch (RuntimeException $e) {
        rediriger('?p=catalogue&err=' . urlencode($e->getMessage()));
    }

    journal_ecrire($u, 'decor.supprime', 'decor', $id, (string) $bilan['titre'],
        $bilan['badges'] ? $bilan['badges'] . ' badge(s) détruit(s)' : null);

    $message = sprintf(
        '« %s » supprimé%s%s.',
        $bilan['titre'],
        $bilan['badges'] ? ', ' . $bilan['badges'] . ' badge(s) détruit(s)' : '',
        $bilan['cadre'] ? ', cadre effacé du disque' : ''
    );
    rediriger('?p=catalogue&ok=' . urlencode($message));
}

/* ---------------- la liste ---------------- */

$filtre = (string) ($_GET['statut'] ?? '');
$cherche = (string) ($_GET['q'] ?? '');
$page_n = max(1, (int) ($_GET['n'] ?? 1));
$combien = decors_catalogue_combien($filtre ?: null, $cherche);

vue('catalogue', [
    'titre' => 'Tous les décors',
    'liste' => decors_catalogue($filtre ?: null, $cherche, $page_n),
    'compteurs' => decors_par_statut(),
    'filtre' => $filtre,
    'cherche' => $cherche,
    'page_n' => $page_n,
    'combien' => $combien,
    'pages' => max(1, (int) ceil($combien / CATALOGUE_PAR_PAGE)),
]);
