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
    // On revient d'où l'on vient : la liste, ou la fiche du compte.
    $retour = ($_POST['retour'] ?? '') === 'fiche'
        ? '?p=organisateur&id=' . rawurlencode($id)
        : '?p=comptes';
    if ($id === $u['id']) {
        // Se rétrograder ou se suspendre soi-même laisserait l'installation
        // sans administrateur. Refusé, quoi qu'il arrive.
        rediriger($retour . '&err=' . urlencode('Vous ne pouvez pas modifier votre propre compte.'));
    }
    if ($page === 'role') {
        $role = (string) ($_POST['role'] ?? '');
        $formule = (string) ($_POST['formule'] ?? 'decouverte');
        if (!in_array($role, ROLES, true)) {
            rediriger($retour . '&err=' . urlencode('Rôle inconnu.'));
        }
        if (!isset(FORMULES[$formule])) {
            rediriger($retour . '&err=' . urlencode('Offre inconnue.'));
        }
        db()->prepare('UPDATE utilisateurs SET role = ?, formule = ? WHERE id = ?')
            ->execute([$role, $formule, $id]);

        /**
         * La personne concernée l'apprend, et sait ce qui change.
         *
         * Son quota vient de bouger, mais aussi le filigrane de ses badges,
         * ses Koris et sa redirection : le message les nomme, sinon elle
         * découvrirait le changement sur un badge déjà partagé.
         */
        $f = FORMULES[$formule];
        $corps = 'Rôle : ' . role_libelle($role) . '. Offre : ' . formule_libelle($formule) . ".\n\n"
            . 'Elle couvre ' . ($f['campagnes'] < 0 ? 'un nombre illimité de campagnes' : $f['campagnes'] . ' campagne(s) active(s)')
            . ' et ' . ($f['telechargements'] < 0 ? 'des téléchargements sans limite' : $f['telechargements'] . ' téléchargements par mois')
            . '. Le filigrane Wakabi ' . ($f['sans_filigrane'] ? 'ne figure plus' : 'figure') . ' sur vos badges, '
            . 'les Koris sont ' . ($f['koris'] ? 'crédités' : 'désactivés') . ' au scan, et la redirection après '
            . 'téléchargement est ' . ($f['redirection'] ? 'active' : 'indisponible') . '. '
            . 'Le détail complet est sur votre tableau de bord.';
        notifier($id, 'compte', 'Votre offre est maintenant ' . formule_libelle($formule),
            $corps, $role === 'partenaire' ? '?p=partenaire' : '?p=compte');
    } else {
        db()->prepare('UPDATE utilisateurs SET suspendu = 1 - suspendu WHERE id = ?')->execute([$id]);
    }
    rediriger($retour . '&ok=' . urlencode('Compte mis à jour.'));
}

/* ---------------- la soupape de téléchargements ---------------- */

if ($page === 'bonus') {
    verifier_csrf();
    $id = (string) ($_POST['id'] ?? '');
    $bonus = max(0, min(100000, (int) ($_POST['bonus'] ?? 0)));
    $avant = (int) (utilisateur_par_id($id)['bonus_telechargements'] ?? 0);
    db()->prepare('UPDATE utilisateurs SET bonus_telechargements = ? WHERE id = ?')->execute([$bonus, $id]);

    // Prévenir seulement si l'on DONNE : reprendre un bonus n'est pas une
    // bonne nouvelle à annoncer par notification automatique.
    if ($bonus > $avant) {
        notifier($id, 'compte', 'Des téléchargements vous ont été accordés',
            ($bonus - $avant) . ' téléchargements s’ajoutent à votre offre pour ce mois-ci.',
            '?p=partenaire');
    }
    rediriger('?p=organisateur&id=' . rawurlencode($id) . '&ok='
        . urlencode($bonus > 0 ? "Soupape réglée à $bonus téléchargements." : 'Soupape retirée.'));
}

/* ---------------- la note interne ---------------- */

if ($page === 'note-compte') {
    verifier_csrf();
    $id = (string) ($_POST['id'] ?? '');
    db()->prepare('UPDATE utilisateurs SET note_equipe = ? WHERE id = ?')
        ->execute([mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 4000) ?: null, $id]);
    rediriger('?p=organisateur&id=' . rawurlencode($id) . '&ok=' . urlencode('Note enregistrée.'));
}

/* ---------------- la fiche d'un compte ---------------- */

if ($page === 'organisateur') {
    $fiche = fiche_compte((string) ($_GET['id'] ?? ''));
    if (!$fiche) {
        rediriger('?p=comptes&err=' . urlencode('Ce compte n’existe pas.'));
    }
    vue('organisateur', ['titre' => $fiche['compte']['nom'], 'fiche' => $fiche]);
}

vue('comptes', ['titre' => 'Comptes', 'liste' => comptes_tous()]);
