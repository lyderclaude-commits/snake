<?php
/** Connexion — avec limitation de débit en base. */
$erreur = null;
$valeurs = ['email' => ''];

if ($post) {
    verifier_csrf();
    $valeurs['email'] = trim((string) ($_POST['email'] ?? ''));
    $mdp = (string) ($_POST['mot_de_passe'] ?? '');
    $cle = cle_debit($valeurs['email']);

    if (debit_depasse($cle)) {
        $erreur = 'Trop de tentatives. Réessayez dans 15 minutes.';
    } else {
        $u = utilisateur_par_email($valeurs['email']);
        // password_verify est à temps constant : un e-mail inconnu et un
        // mauvais mot de passe se ressemblent, y compris en durée.
        if ($u && !((int) $u['suspendu']) && password_verify($mdp, $u['mot_de_passe'])) {
            debit_effacer($cle);
            connecter($u['id']);
            rediriger(accueil_de($u));
        }
        debit_noter($cle);
        $erreur = 'Adresse ou mot de passe incorrect.';
    }
}

vue('connexion', ['titre' => 'Connexion · Wakabi Boost', 'erreur' => $erreur, 'valeurs' => $valeurs]);
