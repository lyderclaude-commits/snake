<?php
/**
 * Mot de passe oublié — demander un lien, puis en poser un nouveau.
 *
 * C'était le manque le plus coûteux du produit : sans cet écran, celui qui
 * perd son mot de passe ne peut rien faire seul. Il écrit à l'équipe, qui
 * lui en fabrique un à la main et le lui transmet — un samedi soir, à
 * l'heure où l'on ouvre les portes. Chaque cas coûtait un aller-retour
 * humain que personne n'avait prévu.
 *
 * Deux règles tiennent tout l'écran :
 *
 *  - **La réponse est la même quoi qu'il arrive.** « Si un compte existe à
 *    cette adresse, le lien vient de partir. » Dire « cette adresse est
 *    inconnue » transformerait le formulaire en annuaire : on y teste des
 *    adresses jusqu'à trouver celles qui ont un compte.
 *  - **Le lien vaut mot de passe.** Deux heures, un seul usage, et il
 *    remplace le précédent — demander deux fois n'en laisse qu'un valable.
 */

$erreur = null;
$fait = false;
$valeurs = ['email' => ''];

/* ---------------- demander le lien ---------------- */

if ($page === 'oubli') {
    if (utilisateur_courant()) {
        rediriger(accueil_de(utilisateur_courant()));
    }

    if ($post) {
        verifier_csrf();
        $valeurs['email'] = trim((string) ($_POST['email'] ?? ''));
        $cle = 'oubli|' . cle_debit($valeurs['email']);

        if (!courriel_branche()) {
            // Le dire franchement plutôt que d'annoncer un envoi qui
            // n'aura pas lieu : quelqu'un attendrait un message toute la
            // soirée. L'équipe, elle, sait poser un mot de passe à la main.
            $erreur = 'Le transport e-mail n’est pas encore réglé sur cette installation : '
                . 'aucun message ne peut partir. Écrivez à l’équipe, elle vous ouvrira le compte.';
        } elseif (debit_depasse($cle)) {
            $erreur = 'Trop de demandes. Réessayez dans 15 minutes.';
        } elseif (!filter_var($valeurs['email'], FILTER_VALIDATE_EMAIL)) {
            $erreur = 'Cette adresse e-mail n’est pas valide.';
        } else {
            debit_noter($cle);
            $u = utilisateur_par_email($valeurs['email']);

            // Un compte suspendu ne reçoit rien : lui rendre l'accès par ce
            // chemin annulerait la suspension sans que personne ne l'ait
            // décidé.
            if ($u && !((int) $u['suspendu'])) {
                $jeton = creer_jeton_oubli((string) $u['id']);
                envoyer_courriel(
                    (string) $u['email'],
                    (string) $u['nom'],
                    'Reprendre la main sur votre compte Wakabi Boost',
                    "Bonjour " . $u['nom'] . ",\n\n"
                    . "Quelqu’un — vous, sans doute — a demandé un nouveau mot de passe pour "
                    . "le compte " . $u['email'] . ".\n\n"
                    . "Choisissez-en un ici, dans les " . OUBLI_HEURES . " heures :\n"
                    . lien_oubli($jeton) . "\n\n"
                    . "Si ce n’était pas vous, ignorez ce message : rien n’a changé, et "
                    . "votre mot de passe actuel fonctionne toujours. Le lien ne sert qu’une "
                    . "fois et expire tout seul.\n\n"
                    . "— L’équipe Wakabi\n"
                );
            }
            $fait = true;
        }
    }

    vue('oubli', [
        'titre' => 'Mot de passe oublié · Wakabi Boost',
        'erreur' => $erreur,
        'fait' => $fait,
        'valeurs' => $valeurs,
    ]);
}

/* ---------------- poser le nouveau ---------------- */

$jeton = (string) ($_POST['jeton'] ?? $_GET['j'] ?? '');
$compte = compte_du_jeton_oubli($jeton);

if ($post && $compte) {
    verifier_csrf();
    $mdp = (string) ($_POST['mot_de_passe'] ?? '');
    $encore = (string) ($_POST['confirmation'] ?? '');

    $erreur = match (true) {
        strlen($mdp) < 8 => 'Le mot de passe doit faire au moins 8 caractères.',
        $mdp !== $encore => 'Les deux mots de passe ne sont pas identiques.',
        default => null,
    };

    if ($erreur === null && consommer_jeton_oubli($jeton, $mdp)) {
        journal_ecrire(null, 'compte.role', 'compte', (string) $compte['id'],
            (string) $compte['nom'], 'Mot de passe réinitialisé par lien e-mail');
        notifier((string) $compte['id'], 'compte', 'Votre mot de passe a été changé',
            'Si ce n’est pas vous, écrivez-nous tout de suite : quelqu’un a eu accès à votre '
            . 'boîte e-mail.', '?p=profil', false);
        rediriger('?p=connexion&ok=' . urlencode(
            'Mot de passe enregistré. Connectez-vous avec le nouveau.'));
    }
}

vue('reinitialiser', [
    'titre' => 'Nouveau mot de passe · Wakabi Boost',
    'erreur' => $erreur,
    'jeton' => $jeton,
    'compte' => $compte,
]);
