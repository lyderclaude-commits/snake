<?php
/**
 * Les réglages du transport e-mail, et son essai en conditions réelles.
 *
 * Le bouton d'essai n'est pas un ornement : un SMTP mal réglé ne se voit
 * nulle part ailleurs. Les messages partent en arrière-plan, personne ne
 * les attend, et on découvrirait la panne le jour où un partenaire dirait
 * n'avoir jamais reçu la décision sur son décor.
 */
$u = exiger_role('equipe');

$valeurs = reglages_courriel();
$message = null;
$erreur = null;

if ($post) {
    verifier_csrf();

    $saisie = [];
    foreach (array_keys(COURRIEL_DEFAUTS) as $cle) {
        $saisie[$cle] = trim((string) ($_POST[$cle] ?? ''));
    }
    $saisie['smtp_port'] = (string) max(1, min(65535, (int) ($saisie['smtp_port'] ?: 587)));
    if (!isset(COURRIEL_SECURITES[$saisie['smtp_securite']])) {
        $saisie['smtp_securite'] = 'tls';
    }
    /**
     * Un champ mot de passe laissé vide GARDE l'ancien.
     *
     * On ne réaffiche jamais un mot de passe dans une page : le renvoyer
     * dans le HTML, c'est le donner à tout ce qui lit la page — historique,
     * cache, capture d'écran. Vide veut donc dire « inchangé », et il faut
     * un geste explicite pour l'effacer.
     */
    if ($saisie['smtp_motdepasse'] === '') {
        if (($_POST['effacer_mdp'] ?? '') === '1') {
            $saisie['smtp_motdepasse'] = '';
        } else {
            unset($saisie['smtp_motdepasse']);
        }
    }

    $vers = trim((string) ($_POST['essai_vers'] ?? ''));
    $essai = ($_POST['action'] ?? '') === 'essai';

    if ($saisie['smtp_hote'] !== '' && $saisie['courriel_expediteur'] === '') {
        $erreur = 'Indiquez l’adresse expéditrice : un serveur SMTP refuse un message sans elle.';
    } elseif ($saisie['courriel_expediteur'] !== ''
              && !filter_var($saisie['courriel_expediteur'], FILTER_VALIDATE_EMAIL)) {
        $erreur = 'L’adresse expéditrice n’est pas une adresse valide.';
    } elseif ($saisie['courriel_repondre_a'] !== ''
              && !filter_var($saisie['courriel_repondre_a'], FILTER_VALIDATE_EMAIL)) {
        $erreur = 'L’adresse de réponse n’est pas une adresse valide.';
    } elseif ($essai && !filter_var($vers, FILTER_VALIDATE_EMAIL)) {
        $erreur = 'Indiquez une adresse valide pour recevoir l’essai.';
    } else {
        reglages_bdd_poser($saisie);
        $valeurs = reglages_courriel();

        if ($essai) {
            // Enregistré AVANT d'essayer : on teste ce qui est en place, pas
            // une variante qui n'existerait que le temps de la requête.
            $r = courriel_mis_en_page(
                $vers,
                $u['nom'],
                'Essai d’envoi — Wakabi Boost',
                'Le transport fonctionne',
                "Si vous lisez ce message, les réglages SMTP de Wakabi Boost sont bons.\n\n"
                . 'Envoyé depuis ' . base_url() . ' le ' . gmdate('d/m/Y à H:i') . ' UTC.',
                base_url() . '/index.php?p=reglages',
                'Revenir aux réglages'
            );
            if ($r['ok']) {
                $message = 'Réglages enregistrés. ' . $r['message']
                    . ' Regardez la boîte de ' . $vers . ' — indésirables compris.';
            } else {
                $erreur = 'Réglages enregistrés, mais l’essai a échoué. ' . $r['message'];
            }
        } else {
            $message = courriel_branche()
                ? 'Réglages enregistrés. Envoyez-vous un essai pour en avoir le cœur net.'
                : 'Réglages enregistrés. Le transport reste éteint tant que le serveur et l’adresse expéditrice ne sont pas renseignés.';
        }
    }
}

vue('reglages', [
    'titre' => 'Réglages',
    'valeurs' => $valeurs,
    'a_mot_de_passe' => (reglages_bdd(['smtp_motdepasse'])['smtp_motdepasse'] ?? '') !== '',
    'message' => $message,
    'erreur' => $erreur,
    'essai_vers' => $_POST['essai_vers'] ?? $u['email'],
]);
