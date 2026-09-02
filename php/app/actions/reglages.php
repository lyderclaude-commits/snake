<?php
/**
 * Les réglages du transport e-mail, et son essai en conditions réelles.
 *
 * Le bouton d'essai n'est pas un ornement : un SMTP mal réglé ne se voit
 * nulle part ailleurs. Les messages partent en arrière-plan, personne ne
 * les attend, et on découvrirait la panne le jour où un partenaire dirait
 * n'avoir jamais reçu la décision sur son décor.
 */
$u = exiger_droit('reglages');

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

    /**
     * Le domaine des liens courts, normalisé plutôt que refusé.
     *
     * Quelqu'un qui tape « wkb.link » a raison ; lui répondre « adresse
     * invalide » parce qu'il manque `https://` serait pédant. On complète,
     * et on ne refuse que ce qui ne peut pas être un domaine.
     *
     * Un champ ABSENT n'est pas un champ vidé : les deux formulaires de
     * cette page enregistrent par le même chemin, et sans ce garde,
     * enregistrer le transport effacerait le domaine des liens.
     */
    $domaine = isset($_POST['domaine_liens']) ? trim((string) $_POST['domaine_liens']) : null;
    if ($domaine !== null && $domaine !== '') {
        if (!preg_match('~^https?://~i', $domaine)) {
            $domaine = 'https://' . $domaine;
        }
        $domaine = rtrim($domaine, '/');
        $hote = parse_url($domaine, PHP_URL_HOST);
        if (!$hote || !str_contains($hote, '.')) {
            $erreur = 'Ce domaine ne ressemble pas à un domaine. Exemple : wkb.link';
        }
    }
    if ($erreur === null && $domaine !== null) {
        $saisie['domaine_liens'] = $domaine;
    }

    $vers = trim((string) ($_POST['essai_vers'] ?? ''));
    $essai = ($_POST['action'] ?? '') === 'essai';
    $tester_liens = ($_POST['action'] ?? '') === 'liens';
    $alleger = ($_POST['action'] ?? '') === 'images';

    if ($erreur !== null) {
        // Le domaine des liens a déjà été refusé plus haut : on n'ira pas
        // enregistrer le reste par-dessus une saisie qu'on vient de rejeter.
    } elseif ($saisie['smtp_hote'] !== '' && $saisie['courriel_expediteur'] === '') {
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
        } elseif ($tester_liens) {
            /**
             * On DEMANDE à l'installation de se répondre, on ne suppose pas.
             *
             * `mod_rewrite` peut être absent, ou `AllowOverride` interdire
             * le `.htaccess` : la règle serait alors ignorée en silence, et
             * l'application distribuerait des adresses qui ne mènent nulle
             * part. Le résultat est enregistré, et c'est lui qui décide de
             * la forme des liens ensuite.
             */
            $marche = chemin_court_marche();
            reglages_bdd_poser(['liens_chemin_court' => $marche ? '1' : '']);
            $message = $marche
                ? 'La forme courte répond : vos liens s’écrivent maintenant ' . lien_court_url('AbC123') . '.'
                : null;
            $erreur = $marche ? null :
                'La forme courte ne répond pas. Votre hébergement ignore le fichier .htaccess, ou '
                . 'mod_rewrite n’y est pas actif. Les liens gardent la forme longue, qui marche partout.';
        } elseif ($alleger) {
            /**
             * Alléger ce qui est DÉJÀ en ligne.
             *
             * Les décors ET les articles publiés avant cette version
             * portent encore le fichier tel qu'il a été téléversé. Par
             * lots, parce qu'un mutualisé coupe un script à trente
             * secondes.
             */
            $b = alleger_images();
            $message = $b['traites'] === 0
                ? 'Toutes les images sont déjà optimisées. Rien à faire.'
                : sprintf(
                    '%d image(s) traitée(s), %d allégée(s) : %s au lieu de %s.%s',
                    $b['traites'], $b['allegees'], poids($b['apres']), poids($b['avant']),
                    $b['restants'] > 0
                        ? ' Il en reste ' . $b['restants'] . ' — relancez pour continuer.'
                        : ' C’est terminé.'
                );
        } else {
            $message = courriel_branche()
                ? 'Réglages enregistrés. Envoyez-vous un essai pour en avoir le cœur net.'
                : 'Réglages enregistrés. Le transport reste éteint tant que le serveur et l’adresse expéditrice ne sont pas renseignés.';
        }
    }
}

$liens = reglages_bdd(['domaine_liens', 'liens_chemin_court']);

vue('reglages', [
    'titre' => 'Réglages',
    'valeurs' => $valeurs,
    'domaine_liens' => (string) ($liens['domaine_liens'] ?? ''),
    'chemin_court' => ($liens['liens_chemin_court'] ?? '') === '1',
    'exemple_lien' => lien_court_url('AbC123'),
    'a_mot_de_passe' => (reglages_bdd(['smtp_motdepasse'])['smtp_motdepasse'] ?? '') !== '',
    'message' => $message,
    'erreur' => $erreur,
    'essai_vers' => $_POST['essai_vers'] ?? $u['email'],
]);
