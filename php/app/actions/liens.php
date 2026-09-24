<?php
/**
 * Les liens courts : en créer, les suivre, les supprimer.
 *
 * Le quota vient de l'offre — 0 sur Découverte, 20 sur Impact, 100 sur
 * Croissance, sans limite sur Mouvement. C'est une ligne vendue sur la
 * vitrine : elle se compte, et elle se refuse quand elle est pleine.
 */
$u = exiger_droit('liens');

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
    if ($d && !droit($u, 'decors_tous') && $d['auteur_id'] !== $u['id']) {
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

/* ---------------- oublier un brouillon ---------------- */

/**
 * Le renoncement, et il est explicite.
 *
 * Un brouillon en attente s'efface tout seul au bout de 48 h. Mais
 * quelqu'un qui ne prendra pas l'offre doit pouvoir le retirer de son
 * écran tout de suite, sans attendre deux jours une carte qui lui rappelle
 * ce qu'il n'a pas acheté.
 */
if ($page === 'oublier-brouillon') {
    verifier_csrf();
    $genre = (string) ($_POST['genre'] ?? '');
    if (brouillon_suite($genre) !== null) {
        brouillon_retirer($genre, brouillon_jeton());
    }
    rediriger('?p=liens&ok=' . rawurlencode('Brouillon supprimé.'));
}

/* ---------------- la reprise d'un brouillon ---------------- */

/**
 * Le lien composé avant d'avoir un compte.
 *
 * Deux issues, et une seule est un échec. Si l'offre en donne, on le crée
 * tout de suite : la personne a fait le chemin, elle ne va pas ressaisir
 * son adresse. Si l'offre n'en donne pas — c'est le cas de Découverte —
 * on GARDE le brouillon et on le montre en attente. L'effacer en disant
 * « il vous faut Impact » reviendrait à lui faire payer ET à lui faire
 * retaper.
 */
$repris = null;
$en_attente = null;
if (($_GET['reprendre'] ?? '') === '1') {
    if ($max === 0 || ($max > 0 && $utilises >= $max)) {
        $en_attente = brouillon_prendre('lien');
    } elseif ($b = brouillon_prendre('lien')) {
        $cible = (string) ($b['charge']['cible'] ?? '');
        $schema = strtolower((string) parse_url($cible, PHP_URL_SCHEME));
        if (filter_var($cible, FILTER_VALIDATE_URL) && in_array($schema, ['http', 'https'], true)) {
            $code = creer_lien((string) $u['id'], $cible, (string) ($b['charge']['titre'] ?? ''), null);
            brouillon_consommer('lien');
            rediriger('?p=liens&ok=' . rawurlencode(
                'Votre lien vous attendait, le voici : ' . lien_court_url($code)));
        }
        // Une adresse devenue invalide entre-temps : on l'oublie plutôt que
        // d'afficher une erreur à quelqu'un qui vient de créer son compte.
        brouillon_consommer('lien');
    }
}

/* ---------------- l'écran ---------------- */

vue('liens', [
    'titre' => 'Liens courts',
    'liste' => liens_de((string) $u['id']),
    'max' => $max,
    'utilises' => $utilises,
    'campagnes' => droit($u, 'decors_tous') ? decors_catalogue() : decors_de((string) $u['id']),
    'en_attente' => $en_attente,
    'porte' => (function (): ?array {
        // La première offre qui en donne, déduite et non écrite : les offres
        // se règlent depuis l'administration, et une phrase codée en dur
        // mentirait le jour où quelqu'un les ouvre à l'offre gratuite.
        foreach (formules_actives() as $cle => $f) {
            if ((int) ($f['liens_courts'] ?? 0) !== 0) {
                return ['cle' => $cle] + $f;
            }
        }
        return null;
    })(),
]);
