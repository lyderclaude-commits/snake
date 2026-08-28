<?php
/**
 * Les liens courts : en créer, les suivre, les supprimer.
 *
 * Le quota vient de l'offre — 0 sur Découverte, 20 sur Impact, 100 sur
 * Croissance, sans limite sur Mouvement. C'est une ligne vendue sur la
 * vitrine : elle se compte, et elle se refuse quand elle est pleine.
 */
$u = exiger_role('partenaire', 'equipe');

$max = quota($u, 'liens_courts');
$utilises = compter_liens((string) $u['id']);

/* ---------------- créer ---------------- */

if ($page === 'creer-lien') {
    verifier_csrf();
    $cible = trim((string) ($_POST['cible'] ?? ''));
    $titre = trim((string) ($_POST['titre'] ?? ''));
    $decor = trim((string) ($_POST['decor_id'] ?? ''));

    /**
     * La cible doit être une adresse web, et rien d'autre.
     *
     * Sans ce filtre, un lien court deviendrait un `javascript:` déguisé
     * derrière un domaine de confiance — le raccourcisseur d'URL est
     * l'endroit exact où ce genre de chose se glisse.
     */
    $schema = strtolower((string) parse_url($cible, PHP_URL_SCHEME));
    $erreur = match (true) {
        $max === 0 => 'Les liens courts arrivent avec l’offre Impact.',
        $max > 0 && $utilises >= $max =>
            'Votre offre ' . formule_libelle($u['formule'] ?? null) . ' couvre ' . $max
            . ' liens, et ils sont tous pris. Supprimez-en un, ou passez à l’offre supérieure.',
        !filter_var($cible, FILTER_VALIDATE_URL) || !in_array($schema, ['http', 'https'], true) =>
            'Indiquez une adresse complète, commençant par http:// ou https://',
        mb_strlen($cible) > 2000 => 'Cette adresse est trop longue.',
        default => null,
    };

    if ($erreur !== null) {
        rediriger('?p=liens&err=' . rawurlencode($erreur));
    }

    $d = $decor !== '' ? decor_par_id($decor) : null;
    // Un lien ne se rattache qu'à une campagne qui vous appartient.
    if ($d && $u['role'] === 'partenaire' && $d['auteur_id'] !== $u['id']) {
        $d = null;
    }
    $code = creer_lien((string) $u['id'], $cible, $titre, $d['id'] ?? null);
    rediriger('?p=liens&ok=' . rawurlencode('Lien créé : ' . lien_court_url($code)));
}

/* ---------------- supprimer ---------------- */

if ($page === 'supprimer-lien') {
    verifier_csrf();
    $code = trim((string) ($_POST['code'] ?? ''));
    rediriger('?p=liens&' . (supprimer_lien((string) $u['id'], $code)
        ? 'ok=' . rawurlencode('Lien supprimé. Les adresses déjà partagées ne mènent plus nulle part.')
        : 'err=' . rawurlencode('Ce lien n’existe pas, ou il n’est pas à vous.')));
}

/* ---------------- l'écran ---------------- */

vue('liens', [
    'titre' => 'Liens courts',
    'liste' => liens_de((string) $u['id']),
    'max' => $max,
    'utilises' => $utilises,
    'campagnes' => $u['role'] === 'partenaire' ? decors_de((string) $u['id']) : decors_catalogue(),
]);
