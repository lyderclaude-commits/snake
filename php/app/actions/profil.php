<?php
/**
 * L'espace profil, pour tous les rôles.
 *
 * Un compte n'appartient pas à l'application : la personne qui l'a créé doit
 * pouvoir corriger son nom, changer d'adresse, refaire son mot de passe et
 * s'en aller. Sans ces gestes, chaque correction passe par l'équipe — ce qui
 * met un humain sur le chemin d'un changement de numéro de téléphone.
 *
 * Trois formulaires distincts plutôt qu'un seul : identité, mot de passe,
 * suppression. Un mot de passe se change avec l'ancien sous la main ; un nom
 * se corrige sans. Les mêler obligerait à ressaisir son mot de passe pour
 * changer une ville.
 */
$u = exiger_role('participant', 'partenaire', 'equipe');

$erreur = null;
$message = $_GET['ok'] ?? null;

/* ---------------- l'identité ---------------- */

if ($page === 'profil-identite') {
    verifier_csrf();
    $v = [
        'nom' => trim((string) ($_POST['nom'] ?? '')),
        'email' => mb_strtolower(trim((string) ($_POST['email'] ?? ''))),
        'organisation' => trim((string) ($_POST['organisation'] ?? '')),
        'ville' => trim((string) ($_POST['ville'] ?? '')),
        'telephone' => trim((string) ($_POST['telephone'] ?? '')),
    ];
    $autre = utilisateur_par_email($v['email']);

    $erreur = match (true) {
        $v['nom'] === '' => 'Indiquez votre nom.',
        mb_strlen($v['nom']) > 120 => 'Ce nom est trop long.',
        !filter_var($v['email'], FILTER_VALIDATE_EMAIL) => 'Cette adresse e-mail n’est pas valide.',
        $autre && $autre['id'] !== $u['id'] => 'Un autre compte utilise déjà cette adresse.',
        $v['telephone'] !== '' && !preg_match('/^[0-9 +().-]{6,25}$/', $v['telephone']) =>
            'Ce numéro ne ressemble pas à un numéro de téléphone.',
        default => null,
    };

    if ($erreur === null) {
        /**
         * Changer d'adresse REDEMANDE une confirmation.
         *
         * Sans cela, une adresse confirmée servirait de laissez-passer
         * définitif : il suffirait de la remplacer par n'importe quelle
         * autre pour garder le bénéfice d'une vérification qui ne porte
         * plus sur rien. La garde ne s'applique évidemment que si l'on sait
         * envoyer le lien.
         */
        $change_adresse = $v['email'] !== mb_strtolower((string) $u['email']);
        $sql = 'UPDATE utilisateurs SET nom = ?, email = ?, organisation = ?, ville = ?, telephone = ?';
        $args = [$v['nom'], $v['email'], $v['organisation'] ?: null, $v['ville'] ?: null, $v['telephone'] ?: null];
        if ($change_adresse) {
            $sql .= ', email_verifie_le = NULL, verif_jeton = NULL, verif_expire_le = NULL';
        }
        db()->prepare($sql . ' WHERE id = ?')->execute([...$args, $u['id']]);

        $suite = '';
        if ($change_adresse && verification_exigee()) {
            $envoi = envoyer_verification(['id' => $u['id'], 'email' => $v['email'], 'nom' => $v['nom']]);
            $suite = $envoi['ok']
                ? ' Un lien de confirmation vient de partir vers ' . $v['email'] . '.'
                : ' L’envoi du lien de confirmation a échoué : ' . $envoi['message'];
        }
        rediriger('?p=profil&ok=' . rawurlencode('Profil enregistré.' . $suite));
    }
}

/* ---------------- le mot de passe ---------------- */

if ($page === 'profil-motdepasse') {
    verifier_csrf();
    $actuel = (string) ($_POST['actuel'] ?? '');
    $nouveau = (string) ($_POST['nouveau'] ?? '');

    $erreur = match (true) {
        // L'ancien mot de passe est exigé : un poste laissé ouvert ne doit
        // pas suffire à s'approprier un compte définitivement.
        !password_verify($actuel, (string) $u['mot_de_passe']) => 'Le mot de passe actuel est faux.',
        strlen($nouveau) < 8 => 'Le nouveau mot de passe doit faire au moins 8 caractères.',
        $nouveau === $actuel => 'Le nouveau mot de passe est identique à l’ancien.',
        default => null,
    };

    if ($erreur === null) {
        db()->prepare('UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?')
            ->execute([hacher($nouveau), $u['id']]);
        notifier((string) $u['id'], 'compte', 'Votre mot de passe a été changé',
            'Si ce n’est pas vous, écrivez-nous immédiatement.', '?p=profil');
        rediriger('?p=profil&ok=' . rawurlencode('Mot de passe changé.'));
    }
}

/* ---------------- la suppression ---------------- */

if ($page === 'profil-supprimer') {
    verifier_csrf();
    $confirmation = trim((string) ($_POST['confirmation'] ?? ''));

    /**
     * Un administrateur ne se supprime pas lui-même.
     *
     * La même règle que pour la suspension, et pour la même raison : une
     * installation sans administrateur ne se répare que par la base de
     * données.
     */
    if ($u['role'] === 'equipe') {
        $erreur = 'Un compte de l’équipe se supprime depuis l’écran Comptes, par quelqu’un d’autre.';
    } elseif ($confirmation !== $u['email']) {
        $erreur = 'Pour confirmer, recopiez votre adresse e-mail exactement.';
    } else {
        supprimer_compte((string) $u['id']);
        deconnecter();
        // Vers l'écran de connexion, et non la vitrine : c'est le seul
        // endroit où le message de départ sera lu — une page d'accueil
        // marchande n'est pas faite pour porter un accusé de réception.
        rediriger('?p=connexion&ok=' . rawurlencode(
            'Votre compte a été supprimé. Il ne reste rien de vos identifiants.'
        ));
    }
}

vue('profil', [
    'titre' => 'Mon profil',
    'erreur' => $erreur,
    'message' => $message,
    'bilan' => $u['role'] === 'partenaire' ? bilan_offre($u) : null,
    'campagnes' => $u['role'] === 'partenaire' ? count(decors_de((string) $u['id'])) : 0,
]);
