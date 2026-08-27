<?php
/** Inscription : participant ou organisateur. */
$erreur = null;
$valeurs = ['nom' => '', 'email' => '', 'role' => 'participant', 'organisation' => '', 'ville' => 'lome'];

/**
 * L'offre cliquée sur la vitrine suit jusqu'ici.
 *
 * Elle n'est PAS appliquée au compte : une offre payante s'active après
 * paiement, pas en cochant une case. Elle sert à deux choses honnêtes :
 * dire à la personne où elle en est, et prévenir l'équipe qu'un
 * organisateur attend son activation.
 */
$offre = (string) ($_POST['offre'] ?? $_GET['offre'] ?? '');
if (!isset(FORMULES[$offre]) || $offre === 'decouverte') {
    $offre = '';
}
if ($offre !== '') {
    $valeurs['role'] = 'partenaire';
}

if ($post) {
    verifier_csrf();
    foreach (array_keys($valeurs) as $k) {
        $valeurs[$k] = trim((string) ($_POST[$k] ?? $valeurs[$k]));
    }
    $mdp = (string) ($_POST['mot_de_passe'] ?? '');

    if ($valeurs['nom'] === '') {
        $erreur = 'Indiquez votre nom.';
    } elseif (!filter_var($valeurs['email'], FILTER_VALIDATE_EMAIL)) {
        $erreur = 'Cette adresse e-mail n’est pas valide.';
    } elseif (strlen($mdp) < 8) {
        $erreur = 'Le mot de passe doit faire au moins 8 caractères.';
    } elseif (utilisateur_par_email($valeurs['email'])) {
        $erreur = 'Un compte existe déjà avec cette adresse.';
    } elseif (!in_array($valeurs['role'], ['participant', 'partenaire'], true)) {
        // Le rôle « equipe » ne s'obtient jamais par le formulaire public.
        $erreur = 'Choisissez un type de compte.';
    } else {
        $id = creer_utilisateur([
            'email' => $valeurs['email'],
            'mot_de_passe' => $mdp,
            'nom' => $valeurs['nom'],
            'role' => $valeurs['role'],
            'organisation' => $valeurs['organisation'] ?: null,
            'ville' => $valeurs['ville'] ?: null,
        ]);
        // Toute inscription publique démarre en Découverte, quelle que soit
        // l'offre cliquée : c'est l'équipe qui active le reste.
        if ($offre !== '') {
            foreach (equipe() as $membre) {
                notifier($membre['id'], 'compte', 'Une offre ' . formule_libelle($offre) . ' est demandée',
                    $valeurs['nom'] . ' (' . $valeurs['email'] . ') vient de s’inscrire en visant l’offre '
                    . formule_libelle($offre) . '. Son compte est en Découverte en attendant l’activation.',
                    '?p=comptes');
            }
        }
        connecter($id);
        rediriger($valeurs['role'] === 'partenaire' ? '?p=partenaire' : '?p=compte');
    }
}

vue('inscription', [
    'titre' => 'Créer un compte · Wakabi Boost',
    'erreur' => $erreur,
    'valeurs' => $valeurs,
    'offre' => $offre,
]);
