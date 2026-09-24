<?php
/**
 * Raccourcir un lien sans compte, et se présenter à la fin.
 *
 * Le raccourcisseur vivait derrière la connexion. On y arrivait par un
 * bouton de la vitrine qui promettait « wkb.link », et on tombait sur un
 * formulaire de connexion : la promesse et l'écran ne se ressemblaient pas.
 *
 * Le mur est maintenant à la fin. Mais il a DEUX étages, et c'est le point
 * délicat : l'offre Découverte ne donne aucun lien court. Créer un compte
 * ne suffit donc pas, et l'écran l'annonce AVANT le premier champ plutôt
 * qu'après le formulaire. Personne ne remplit quatre lignes pour
 * s'entendre dire non.
 */

declare(strict_types=1);

$moi = utilisateur_courant();

/**
 * La première offre qui en donne, telle qu'elle est aujourd'hui.
 *
 * Déduite et non écrite : les offres se règlent maintenant depuis
 * l'administration, et une phrase qui annoncerait « à partir d'Impact »
 * mentirait le jour où quelqu'un ouvre les liens courts à Découverte.
 */
$porte = null;
foreach (formules_actives() as $cle => $f) {
    if ((int) ($f['liens_courts'] ?? 0) !== 0) {
        $porte = ['cle' => $cle] + $f;
        break;
    }
}

$erreur = null;
$valeurs = ['cible' => '', 'titre' => ''];

/** Ce qu'on avait commencé, s'il en reste quelque chose. */
$avant = brouillon_prendre('lien');
if ($avant) {
    $valeurs['cible'] = (string) ($avant['charge']['cible'] ?? '');
    $valeurs['titre'] = (string) ($avant['charge']['titre'] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();
    $valeurs['cible'] = trim((string) ($_POST['cible'] ?? ''));
    $valeurs['titre'] = trim((string) ($_POST['titre'] ?? ''));

    /**
     * La cible est vérifiée ICI, avant même qu'un compte existe.
     *
     * C'est le même filtre que sur l'écran connecté, pour la même raison :
     * un raccourcisseur est l'endroit exact où l'on glisse un
     * `javascript:` derrière un domaine de confiance. Le faire au moment
     * de la reprise seulement aurait laissé passer l'adresse en base.
     */
    $schema = strtolower((string) parse_url($valeurs['cible'], PHP_URL_SCHEME));
    $erreur = match (true) {
        !filter_var($valeurs['cible'], FILTER_VALIDATE_URL) || !in_array($schema, ['http', 'https'], true) =>
            'Indiquez une adresse complète, commençant par http:// ou https://',
        mb_strlen($valeurs['cible']) > 2000 => 'Cette adresse est trop longue.',
        mb_strlen($valeurs['titre']) > 120 => 'Ce nom est trop long.',
        default => null,
    };

    if ($erreur === null) {
        /**
         * Déjà connecté ? On ne lui redemande pas de l'être.
         *
         * Il arrive qu'on compose un lien depuis la vitrine en ayant sa
         * session ouverte dans le même navigateur. Le renvoyer vers la
         * connexion serait absurde : on pose le brouillon et on l'emmène
         * directement à l'écran de reprise.
         */
        if (!brouillon_poser('lien', ['cible' => $valeurs['cible'], 'titre' => $valeurs['titre']])) {
            $erreur = 'Trop de liens en attente depuis cette connexion. '
                    . 'Créez votre compte pour reprendre celui-ci.';
        } else {
            if ($moi) {
                rediriger('?p=liens&reprendre=1');
            }
            rediriger((string) ($_POST['vers'] ?? '') === 'connexion'
                ? '?p=connexion&suite=lien'
                : '?p=inscription&suite=lien');
        }
    }
}

vue('lien-court', [
    'titre' => 'Raccourcir un lien · ' . seo_reglage('seo_nom_site'),
    'description' => 'Une adresse courte à mettre sur une affiche ou dans un message, '
        . 'et le nombre de personnes qui l’ont réellement suivie.',
    'valeurs' => $valeurs,
    'erreur' => $erreur,
    'porte' => $porte,
    'moi' => $moi,
]);
