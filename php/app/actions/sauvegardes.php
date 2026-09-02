<?php
/**
 * Les sauvegardes : en faire une, la télécharger, faire le ménage.
 *
 * Réservé à l'équipe. Une archive contient tous les comptes et tous les
 * badges : c'est le fichier le plus sensible du site.
 */
$u = exiger_droit('reglages');

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

/* ---------------- restaurer ---------------- */

/**
 * Le geste le plus dangereux du produit, entouré comme il se doit.
 *
 * Deux temps volontairement séparés : on INSPECTE d'abord — l'écran dit
 * combien de comptes, de décors et d'articles l'archive contient, et de
 * quand elle date —, puis on confirme en recopiant un mot. Restaurer d'un
 * seul clic, c'est écraser une semaine de travail avec une archive de
 * l'an dernier et s'en apercevoir après.
 */
$inspection = null;
$archive_vue = '';

if ($page === 'restaurer') {
    verifier_csrf();
    $quoi = (string) ($_POST['quoi'] ?? 'inspecter');
    $nom = basename((string) ($_POST['f'] ?? ''));
    $chemin = dossier_sauvegardes() . '/' . $nom;

    if (!preg_match('/^[\w.-]+\.zip$/', $nom) || !is_file($chemin)) {
        rediriger('?p=sauvegardes&err=' . rawurlencode('Cette sauvegarde n’est pas sur le serveur.'));
    }

    try {
        if ($quoi === 'restaurer') {
            // Le mot à recopier est le nom du fichier : il est sous les
            // yeux, il ne se devine pas, et le recopier oblige à regarder
            // LAQUELLE on remet en place.
            if (trim((string) ($_POST['confirmation'] ?? '')) !== $nom) {
                rediriger('?p=sauvegardes&err=' . rawurlencode(
                    'Restauration annulée : le nom saisi ne correspond pas à « ' . $nom . ' ».'));
            }
            $bilan = restaurer_sauvegarde($chemin);

            // Le journal est écrit APRÈS, donc dans la base restaurée :
            // c'est la seule qui subsiste, et c'est là que la trace sert.
            journal_ecrire($u, 'sauvegarde.restauree', 'sauvegarde', null, $nom,
                $bilan['tables'] . ' table(s), ' . $bilan['cadres'] . ' cadre(s), '
                . $bilan['medias'] . ' média(s). Filet : ' . $bilan['filet']);

            // La session pointe sur un compte de l'ANCIENNE base : il peut
            // ne plus exister. On repart de l'écran de connexion plutôt que
            // de laisser quelqu'un errer avec une session fantôme.
            deconnecter();
            rediriger('?p=connexion&ok=' . rawurlencode(
                'Sauvegarde restaurée : ' . $bilan['tables'] . ' table(s), '
                . $bilan['cadres'] . ' cadre(s), ' . $bilan['medias'] . ' média(s). '
                . 'L’état d’avant a été mis de côté dans « ' . $bilan['filet'] . ' ». '
                . 'Reconnectez-vous avec les identifiants de l’archive.'));
        }

        $inspection = inspecter_sauvegarde($chemin);
        $archive_vue = $nom;
    } catch (Throwable $e) {
        rediriger('?p=sauvegardes&err=' . rawurlencode($e->getMessage()));
    }
}

/* ---------------- l'écran ---------------- */

vue('sauvegardes', [
    'titre' => 'Sauvegardes',
    'liste' => sauvegardes_presentes(),
    'mysql' => est_mysql(),
    'cle' => cle_sauvegarde(),
    'inspection' => $inspection,
    'archive_vue' => $archive_vue,
]);
