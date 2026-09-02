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
// Tout le monde a un profil, y compris un scanner.
$u = utilisateur_courant() ?: exiger_role(...ROLES);

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

/* ---------------- la double authentification ---------------- */

/**
 * Trois gestes : proposer, confirmer, retirer.
 *
 * La confirmation est OBLIGATOIRE avant d'activer : sans elle, on
 * enregistre un secret que l'application du téléphone n'a peut-être
 * jamais reçu — et l'on découvre le problème à la connexion suivante,
 * enfermé dehors.
 */
if ($page === 'profil-otp') {
    verifier_csrf();
    if (!otp_proposable($u)) {
        rediriger('?p=profil&err=' . urlencode(
            'La double authentification est réservée aux comptes de l’équipe.'));
    }
    $quoi = (string) ($_POST['quoi'] ?? '');

    if ($quoi === 'preparer') {
        // Un secret NEUF à chaque préparation : reprendre l'ancien
        // laisserait valable un QR photographié puis abandonné.
        $secret = otp_secret_neuf();
        db()->prepare('UPDATE utilisateurs SET otp_secret = ?, otp_actif = 0 WHERE id = ?')
            ->execute([$secret, $u['id']]);
        rediriger('?p=profil&otp=1#otp');
    }

    if ($quoi === 'confirmer') {
        $secret = (string) ($u['otp_secret'] ?? '');
        if ($secret === '') {
            rediriger('?p=profil&err=' . urlencode('Commencez par préparer un secret.'));
        }
        if (!otp_verifier($secret, (string) ($_POST['code'] ?? ''))) {
            rediriger('?p=profil&otp=1&err=' . urlencode(
                'Ce code n’est pas le bon. Vérifiez que l’heure de votre téléphone est à jour, '
                . 'puis réessayez avec le code suivant.') . '#otp');
        }
        db()->prepare('UPDATE utilisateurs SET otp_actif = 1 WHERE id = ?')->execute([$u['id']]);
        journal_ecrire($u, 'compte.role', 'compte', (string) $u['id'], (string) $u['nom'],
            'Double authentification activée');
        rediriger('?p=profil&ok=' . urlencode(
            'Double authentification en service. Le code vous sera demandé à chaque connexion.'));
    }

    if ($quoi === 'retirer') {
        // On exige un code valable pour la RETIRER aussi : sinon une
        // session laissée ouverte sur un poste partagé suffit à la lever.
        if (!otp_verifier((string) ($u['otp_secret'] ?? ''), (string) ($_POST['code'] ?? ''))) {
            rediriger('?p=profil&err=' . urlencode(
                'Code incorrect : la double authentification reste en service.'));
        }
        db()->prepare('UPDATE utilisateurs SET otp_secret = NULL, otp_actif = 0 WHERE id = ?')
            ->execute([$u['id']]);
        journal_ecrire($u, 'compte.role', 'compte', (string) $u['id'], (string) $u['nom'],
            'Double authentification retirée');
        rediriger('?p=profil&ok=' . urlencode('Double authentification retirée.'));
    }

    rediriger('?p=profil');
}

/* ---------------- emporter ses données ---------------- */

/**
 * Tout ce que l'installation sait de vous, dans un fichier.
 *
 * La suppression existait ; l'export non — on pouvait donc partir, mais
 * pas partir AVEC. C'est le pendant naturel du droit de suppression, et
 * c'est aussi ce qui permet à un organisateur de reprendre ses chiffres
 * le jour où il s'en va.
 *
 * Ce qui n'y figure pas, délibérément : le mot de passe (haché, il
 * n'apprendrait rien et sa présence dans un fichier qui circule est un
 * risque pur), la clé d'API (elle se refabrique) et les jetons en cours.
 */
if ($page === 'profil-export') {
    $decors = decors_de((string) $u['id']);

    $sortie = [
        'exporte_le' => maintenant(),
        'installation' => base_url(),
        'compte' => [
            'nom' => $u['nom'],
            'email' => $u['email'],
            'telephone' => $u['telephone'] ?: null,
            'organisation' => $u['organisation'] ?: null,
            'ville' => $u['ville'] ?: null,
            'role' => $u['role'],
            'offre' => $u['formule'],
            'echeance_le' => echeance_de($u),
            'adresse_confirmee_le' => $u['email_verifie_le'] ?: null,
            'cree_le' => $u['cree_le'],
        ],
        'campagnes' => array_map(function (array $d): array {
            $p = presence((string) $d['id']);
            return [
                'titre' => $d['titre'],
                'slug' => $d['slug'],
                'statut' => $d['statut'],
                'ville' => $d['ville'] ?: null,
                'cree_le' => $d['cree_le'],
                'publie_le' => $d['publie_le'] ?: null,
                'vues' => (int) $d['vues'],
                'telechargements' => (int) $d['telechargements'],
                'badges_emis' => $p['emis'],
                'presences' => $p['scannes'],
            ];
        }, $decors),
        'badges_crees' => array_map(fn(array $c) => [
            'campagne' => $c['titre'] ?? null,
            'cree_le' => $c['cree_le'],
        ], creations_de((string) $u['id'], 1000)),
        'liens_courts' => array_map(fn(array $l) => [
            'code' => $l['code'],
            'cible' => $l['cible'],
            'titre' => $l['titre'] ?: null,
            'clics' => (int) $l['clics'],
            'cree_le' => $l['cree_le'],
        ], liens_de((string) $u['id'])),
        'koris' => koris_historique((string) $u['id'], 1000),
        'articles' => array_map(fn(array $a) => [
            'titre' => $a['titre'],
            'slug' => $a['slug'],
            'statut' => $a['statut'],
            'cree_le' => $a['cree_le'],
            'publie_le' => $a['publie_le'] ?: null,
        ], articles_de((string) $u['id'])),
        'factures' => array_map(fn(array $f) => [
            'numero' => $f['numero'],
            'offre' => $f['formule'],
            'montant' => (int) $f['montant'],
            'debut_le' => $f['debut_le'],
            'fin_le' => $f['fin_le'],
        ], factures_de((string) $u['id'])),
    ];

    $nom = 'wakabi-' . preg_replace('/[^a-z0-9]+/i', '-', (string) $u['nom']) . '-'
        . gmdate('Y-m-d') . '.json';
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nom . '"');
    // Un export ne se met JAMAIS en cache : il contient des données
    // personnelles, et un proxy partagé n'a rien à faire avec.
    header('Cache-Control: no-store, private');
    echo json_encode($sortie, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

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
    if (interne($u)) {
        $erreur = 'Un compte interne se supprime depuis l’écran Comptes, par quelqu’un d’autre.';
    } elseif ($confirmation !== $u['email']) {
        $erreur = 'Pour confirmer, recopiez votre adresse e-mail exactement.';
    } else {
        journal_ecrire($u, 'compte.supprime', 'compte', (string) $u['id'], (string) $u['nom'],
            'Suppression demandée par la personne elle-même');
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
    'interne' => interne($u),
    'campagnes' => $u['role'] === 'partenaire' ? count(decors_de((string) $u['id'])) : 0,
]);
