<?php
/** Gestion des comptes : création par l'équipe, rôle, offre, suspension. */
$u = exiger_role('equipe');

/* ---------------- créer un compte ---------------- */

if ($page === 'creer-compte') {
    verifier_csrf();

    $v = [
        'nom' => trim((string) ($_POST['nom'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'role' => (string) ($_POST['role'] ?? 'partenaire'),
        'formule' => (string) ($_POST['formule'] ?? 'decouverte'),
        'organisation' => trim((string) ($_POST['organisation'] ?? '')),
        'ville' => trim((string) ($_POST['ville'] ?? 'lome')),
    ];
    $mdp = (string) ($_POST['mot_de_passe'] ?? '');

    $erreur = match (true) {
        $v['nom'] === '' => 'Indiquez le nom de la personne.',
        !filter_var($v['email'], FILTER_VALIDATE_EMAIL) => 'Cette adresse e-mail n’est pas valide.',
        strlen($mdp) < 8 => 'Le mot de passe doit faire au moins 8 caractères.',
        !in_array($v['role'], ROLES, true) => 'Rôle inconnu.',
        !isset(FORMULES[$v['formule']]) => 'Offre inconnue.',
        utilisateur_par_email($v['email']) !== null => 'Un compte existe déjà avec cette adresse.',
        default => null,
    };

    if ($erreur !== null) {
        // On réaffiche le formulaire rempli plutôt que de rediriger : refaire
        // sa saisie à cause d'un doublon d'adresse est une punition inutile.
        vue('comptes', [
            'titre' => 'Comptes',
            'liste' => comptes_tous(),
            'erreur' => $erreur,
            'valeurs' => $v,
            'ouvert' => true,
        ]);
    }

    creer_utilisateur([
        'email' => $v['email'],
        'mot_de_passe' => $mdp,
        'nom' => $v['nom'],
        'role' => $v['role'],
        'formule' => $v['formule'],
        'organisation' => $v['organisation'] ?: null,
        'ville' => $v['ville'] ?: null,
    ]);

    rediriger('?p=comptes&ok=' . urlencode(
        'Compte créé pour ' . $v['nom'] . ' (' . role_libelle($v['role'])
        . ', offre ' . formule_libelle($v['formule']) . '). '
        . 'Transmettez-lui son adresse et son mot de passe : ils ne sont affichés nulle part ailleurs.'
    ));
}

/* ---------------- modifier un compte existant ---------------- */

if ($page === 'role' || $page === 'suspendre') {
    verifier_csrf();
    $id = (string) ($_POST['id'] ?? '');
    if ($id === $u['id']) {
        // Se rétrograder ou se suspendre soi-même laisserait l'installation
        // sans administrateur. Refusé, quoi qu'il arrive.
        rediriger('?p=comptes&err=' . urlencode('Vous ne pouvez pas modifier votre propre compte.'));
    }
    if ($page === 'role') {
        $role = (string) ($_POST['role'] ?? '');
        $formule = (string) ($_POST['formule'] ?? 'decouverte');
        if (!in_array($role, ROLES, true)) {
            rediriger('?p=comptes&err=' . urlencode('Rôle inconnu.'));
        }
        if (!isset(FORMULES[$formule])) {
            rediriger('?p=comptes&err=' . urlencode('Offre inconnue.'));
        }
        db()->prepare('UPDATE utilisateurs SET role = ?, formule = ? WHERE id = ?')
            ->execute([$role, $formule, $id]);

        // La personne concernée l'apprend : son quota de campagnes vient de
        // changer, elle doit le savoir avant de buter dessus.
        notifier($id, 'compte', 'Votre compte a été mis à jour',
            'Rôle : ' . role_libelle($role) . '. Offre : ' . formule_libelle($formule) . '.',
            $role === 'partenaire' ? '?p=partenaire' : '?p=compte');
    } else {
        db()->prepare('UPDATE utilisateurs SET suspendu = 1 - suspendu WHERE id = ?')->execute([$id]);
    }
    rediriger('?p=comptes&ok=' . urlencode('Compte mis à jour.'));
}

vue('comptes', ['titre' => 'Comptes', 'liste' => comptes_tous()]);
