<?php
/** Connexion — avec limitation de débit en base. */

/**
 * Déjà connecté ? On ne propose pas de se reconnecter.
 *
 * Un formulaire de connexion servi à quelqu'un qui l'est déjà se lit comme
 * une déconnexion silencieuse : on retape son mot de passe, ou l'on croit
 * que la session a sauté. On le renvoie chez lui, tout simplement.
 */
if (utilisateur_courant()) {
    rediriger(accueil_de(utilisateur_courant()));
}

$erreur = null;
$valeurs = ['email' => ''];

/**
 * Le second facteur attend dans la session, entre les deux écrans.
 *
 * Le mot de passe a été vérifié, mais la personne n'est PAS connectée :
 * `$_SESSION['utilisateur']` reste vide tant que le code n'est pas donné.
 * Sans cette séparation, une session valable existerait entre les deux
 * écrans — et il suffirait de ne pas remplir le second formulaire.
 */
demarrer_session();
$attente = (string) ($_SESSION['otp_attente'] ?? '');
$en_attente = $attente !== '' ? utilisateur_par_id($attente) : null;

/* ---------------- second facteur ---------------- */

if ($post && $en_attente && isset($_POST['code'])) {
    verifier_csrf();
    $cle = 'otp|' . cle_debit((string) $en_attente['email']);

    if (debit_depasse($cle)) {
        $erreur = 'Trop de tentatives. Réessayez dans 15 minutes.';
    } elseif (otp_verifier((string) $en_attente['otp_secret'], (string) $_POST['code'])) {
        debit_effacer($cle);
        unset($_SESSION['otp_attente']);
        connecter((string) $en_attente['id']);
        rediriger(accueil_de($en_attente));
    } else {
        debit_noter($cle);
        $erreur = 'Ce code n’est pas le bon. Il change toutes les 30 secondes — '
            . 'attendez le suivant et ressaisissez-le.';
    }
}

if (($_GET['annuler'] ?? '') === '1') {
    unset($_SESSION['otp_attente']);
    rediriger('?p=connexion');
}

/* ---------------- premier facteur ---------------- */

if ($post && !isset($_POST['code'])) {
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
            if (otp_actif($u)) {
                $_SESSION['otp_attente'] = $u['id'];
                $en_attente = $u;
            } else {
                connecter($u['id']);
                rediriger(accueil_de($u));
            }
        } else {
            debit_noter($cle);
            $erreur = 'Adresse ou mot de passe incorrect.';
        }
    }
}

vue('connexion', [
    'titre' => 'Connexion · Wakabi Boost',
    'erreur' => $erreur,
    'valeurs' => $valeurs,
    'en_attente' => $en_attente,
]);
