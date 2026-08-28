<?php
/**
 * Les sauvegardes : en faire une, la télécharger, faire le ménage.
 *
 * Réservé à l'équipe. Une archive contient tous les comptes et tous les
 * badges : c'est le fichier le plus sensible du site.
 */
$u = exiger_role('equipe');

/* ---------------- fabriquer ---------------- */

if ($page === 'sauvegarder') {
    verifier_csrf();
    try {
        $f = ecrire_sauvegarde(dossier_sauvegardes() . '/' . nom_sauvegarde());
        tourner_sauvegardes();
        rediriger('?p=sauvegardes&ok=' . rawurlencode(sprintf(
            '%s écrite (%d Ko). Téléchargez-la : gardée ici, elle disparaîtrait avec le serveur.',
            basename($f), (int) round(filesize($f) / 1024)
        )));
    } catch (Throwable $e) {
        rediriger('?p=sauvegardes&err=' . rawurlencode($e->getMessage()));
    }
}

/* ---------------- télécharger ---------------- */

if ($page === 'telecharger-sauvegarde') {
    /**
     * `basename` puis vérification du motif : deux garde-fous plutôt qu'un.
     *
     * Le nom vient de l'URL. Sans cette double précaution, `../../config.php`
     * livrerait les identifiants de la base à qui saurait le demander.
     */
    $nom = basename((string) ($_GET['f'] ?? ''));
    if (!preg_match('/^wakabi-\d{4}-\d{2}-\d{2}-\d{4}\.zip$/', $nom)) {
        rediriger('?p=sauvegardes&err=' . rawurlencode('Nom de sauvegarde inattendu.'));
    }
    $chemin = dossier_sauvegardes() . '/' . $nom;
    if (!is_file($chemin)) {
        rediriger('?p=sauvegardes&err=' . rawurlencode('Cette sauvegarde n’existe plus.'));
    }

    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $nom . '"');
    header('Content-Length: ' . filesize($chemin));
    // Une archive de comptes n'a rien à faire dans un cache intermédiaire.
    header('Cache-Control: private, no-store');
    readfile($chemin);
    exit;
}

/* ---------------- supprimer ---------------- */

if ($page === 'supprimer-sauvegarde') {
    verifier_csrf();
    $nom = basename((string) ($_POST['f'] ?? ''));
    if (preg_match('/^wakabi-\d{4}-\d{2}-\d{2}-\d{4}\.zip$/', $nom)) {
        @unlink(dossier_sauvegardes() . '/' . $nom);
    }
    rediriger('?p=sauvegardes&ok=' . rawurlencode('Sauvegarde supprimée du serveur.'));
}

/* ---------------- l'écran ---------------- */

vue('sauvegardes', [
    'titre' => 'Sauvegardes',
    'liste' => sauvegardes_presentes(),
    'mysql' => est_mysql(),
    'cle' => cle_sauvegarde(),
]);
