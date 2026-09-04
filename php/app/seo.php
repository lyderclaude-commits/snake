<?php
/**
 * Ce que le site dit de lui-même aux robots et aux messageries.
 *
 * Un lien collé dans WhatsApp est le principal canal de ce produit : c'est
 * par lui que circule une soirée. Une miniature vide n'est donc pas un
 * détail de finition, c'est le partage qui ne marche pas.
 *
 * Le défaut d'origine tenait en une ligne. L'adresse canonique était
 * reconstruite en ne gardant que `p` et `slug` — or un ARTICLE s'identifie
 * par `a`. Chaque article annonçait donc l'index du blog comme sa propre
 * adresse : WhatsApp allait chercher l'index, n'y trouvait pas ce qu'on
 * avait partagé, et rendait une carte nue. Google, lui, lisait « cette page
 * est un doublon de /blog » — c'est-à-dire qu'aucun article ne pouvait
 * jamais remonter dans les résultats.
 *
 * La leçon est dans la forme de la correction : on ne RECONSTRUIT plus une
 * adresse à partir d'une liste de paramètres qu'on espère complète. On part
 * de la requête reçue et l'on RETIRE ce qui est éphémère. Une liste qu'on
 * complète est une liste qu'on oubliera de compléter ; une liste qui
 * retire ne peut oublier que du bruit.
 */

declare(strict_types=1);

/**
 * Les paramètres qui ne désignent pas une page, et sortent de l'adresse.
 *
 * Des messages d'un instant, des jetons à usage unique, des empreintes de
 * cache. Les garder ferait de chaque visite une page différente aux yeux
 * d'un moteur, et de chaque partage un lien qui ne compte pas avec le
 * précédent.
 */
const SEO_BRUIT = ['ok', 'err', 'j', 'jeton', 'csrf', 'v', 'cle', 'retour', 'ouvert'];

/** Les réglages, et ce qu'ils valent tant que personne n'y a touché. */
const SEO_DEFAUTS = [
    'seo_nom_site'      => 'Wakabi Boost',
    'seo_titre_suffixe' => 'Wakabi Boost',
    'seo_description'   => 'Créez votre badge et partagez-le. Wakabi Boost, le guide des '
                         . 'bons plans de Lomé, Cotonou et Abidjan.',
    'seo_image'         => '',
    'seo_indexable'     => '1',
    'seo_verif_google'  => '',
    'seo_verif_bing'    => '',
    'seo_organisation'  => 'Wakabi',
    'seo_telephone'     => '',
    'seo_ville'         => 'Lomé',
    'seo_pays'          => 'TG',
    'seo_reseaux'       => '',
];

function seo_reglages(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $lus = reglages_bdd(array_keys(SEO_DEFAUTS));
    $cache = [];
    foreach (SEO_DEFAUTS as $cle => $defaut) {
        $v = trim((string) ($lus[$cle] ?? ''));
        $cache[$cle] = $v !== '' ? $v : $defaut;
    }
    return $cache;
}

function seo_reglage(string $cle): string
{
    return seo_reglages()[$cle] ?? '';
}

/* ------------------------------------------------------------------ */
/* L'adresse d'une page                                                */
/* ------------------------------------------------------------------ */

/**
 * L'adresse canonique de la page en cours.
 *
 * Construite par SOUSTRACTION : on prend la requête telle qu'elle est
 * arrivée et l'on enlève le bruit. C'est ce qui répare le défaut d'origine
 * — et surtout ce qui empêche qu'il revienne le jour où l'on ajoutera un
 * écran avec un paramètre de plus.
 *
 * Une seule forme, toujours : `base/index.php?p=…`. Le menu écrit `/?p=…`,
 * ce qui est la même page ; deux formes pour une page, c'est un doublon
 * qu'un moteur compte deux fois et note deux fois moins bien.
 */
function url_canonique(?array $params = null): string
{
    $q = $params ?? $_GET;
    foreach (SEO_BRUIT as $bruit) {
        unset($q[$bruit]);
    }
    // `p` d'abord : une adresse se lit, et l'on veut voir tout de suite de
    // quelle page il s'agit.
    $p = (string) ($q['p'] ?? 'accueil');
    unset($q['p']);
    ksort($q);

    $suite = '';
    foreach ($q as $cle => $valeur) {
        if (is_array($valeur) || $valeur === '' || $valeur === null) {
            continue;
        }
        $suite .= '&' . rawurlencode((string) $cle) . '=' . rawurlencode((string) $valeur);
    }
    return base_url() . '/index.php?p=' . rawurlencode($p) . $suite;
}

/**
 * Les pages qu'un moteur a le droit d'indexer.
 *
 * Tout ce qui est PUBLIC et stable. Le reste — espaces privés, écrans
 * d'administration, résultats de recherche, pages de jeton — porte
 * `noindex`. Un écran d'administration indexé n'est pas une faille (il
 * demande une session), mais il encombre les résultats du nom du site avec
 * des pages que personne ne peut ouvrir.
 *
 * Liste POSITIVE, contrairement à l'adresse canonique, et pour la raison
 * inverse : oublier d'y inscrire une page nouvelle la laisse hors des
 * moteurs, ce qui se répare ; l'oublier dans une liste d'exclusions la
 * publierait, ce qui ne se répare pas.
 */
const SEO_PAGES_PUBLIQUES = ['accueil', 'decors', 'decor', 'blog', 'inscription', 'connexion'];

function seo_indexable(string $page): bool
{
    if (seo_reglage('seo_indexable') !== '1') {
        return false;
    }
    if (!in_array($page, SEO_PAGES_PUBLIQUES, true)) {
        return false;
    }
    // Une recherche n'est pas une page : elle en fabrique une par mot tapé.
    return trim((string) ($_GET['q'] ?? '')) === '';
}

/* ------------------------------------------------------------------ */
/* La description                                                      */
/* ------------------------------------------------------------------ */

/**
 * Les bornes d'une description qui sert à quelque chose.
 *
 * En dessous du minimum, Google écarte la nôtre et compose la sienne avec
 * ce qu'il trouve sur la page — souvent le menu. Au-dessus du maximum, il
 * coupe, et la phrase se termine par une virgule dans les résultats.
 */
const SEO_DESC_MIN = 70;
const SEO_DESC_MAX = 160;

/**
 * Compose une description à partir de ce qu'on a, puis de ce qu'on sait.
 *
 * Le premier morceau est ce que l'auteur a écrit : son chapô, son
 * sous-titre. On le garde tel quel s'il se suffit. Sinon on le COMPLÈTE
 * avec les morceaux suivants, du plus précis au plus général, au lieu de
 * le remplacer : « Soirée blanche » suivi de la promesse du site dit plus
 * que l'un ou l'autre seul.
 *
 * Un décor sans sous-titre et un article sans chapô sont la règle, pas
 * l'exception — on écrit vite, et le champ n'est pas obligatoire. Cette
 * fonction est donc ce qui sépare un catalogue dont chaque page se
 * présente d'un catalogue de vignettes muettes.
 */
function seo_description(string ...$morceaux): string
{
    $phrase = '';
    foreach ($morceaux as $morceau) {
        $morceau = trim(preg_replace('~\s+~u', ' ', $morceau) ?? '');
        if ($morceau === '') {
            continue;
        }
        if ($phrase === '') {
            $phrase = $morceau;
        } else {
            // Deux phrases se recollent avec une ponctuation, jamais nue.
            $fin = mb_substr($phrase, -1);
            $phrase .= (strpos('.!?…:', $fin) === false ? ' — ' : ' ') . $morceau;
        }
        if (mb_strlen($phrase) >= SEO_DESC_MIN) {
            break;
        }
    }
    return seo_couper($phrase, SEO_DESC_MAX);
}

/**
 * Coupe au dernier mot entier, et pose une ellipse.
 *
 * Couper au caractère près donne « ... à Lomé, Coton » ; le lecteur voit
 * la troncature avant de lire la phrase.
 */
function seo_couper(string $texte, int $max): string
{
    $texte = trim($texte);
    if (mb_strlen($texte) <= $max) {
        return $texte;
    }
    $court = mb_substr($texte, 0, $max - 1);
    $espace = mb_strrpos($court, ' ');
    if ($espace !== false && $espace > $max / 2) {
        $court = mb_substr($court, 0, $espace);
    }
    return rtrim($court, " ,;:—-") . '…';
}

/* ------------------------------------------------------------------ */
/* L'image de partage                                                  */
/* ------------------------------------------------------------------ */

/** La largeur en deçà de laquelle une messagerie rend une vignette timbre-poste. */
const SEO_IMAGE_MIN = 600;

/**
 * L'image à annoncer, avec ses VRAIES dimensions.
 *
 * Les annoncer fausses est une manière discrète de perdre sa vignette :
 * Facebook et WhatsApp font confiance aux nombres déclarés, et une image
 * de 240 px annoncée en 1200 est écartée. Le code annonçait 1200×630 pour
 * toute image, y compris une couverture d'article de 240 px de large.
 *
 * En dessous de 600 px, on ne cherche pas à sauver l'image : on retombe
 * sur la carte de la maison, qui fait 1200×630 et rend une belle
 * miniature. Une petite image donne une carte minuscule, ce qui est pire
 * qu'une carte générique.
 *
 * Le TYPE aussi est déduit, jamais supposé : la carte engendrée par
 * `?p=og` sort en JPEG, et l'annoncer en PNG est le genre d'écart qui fait
 * écarter une vignette sans rien dire à personne.
 *
 * @return array{url: string, largeur: int, hauteur: int, type: string}
 */
function seo_image(?string $url = null): array
{
    // La carte de la maison : engendrée par `?p=og`, donc 1200×630 en JPEG.
    $secours = ['url' => seo_reglage('seo_image') ?: url_og(),
                'largeur' => OG_LARGEUR, 'hauteur' => OG_HAUTEUR, 'type' => 'image/jpeg'];
    if (seo_reglage('seo_image') !== '') {
        $secours = seo_mesurer(seo_reglage('seo_image')) ?? $secours;
    }
    if ($url === null || trim($url) === '') {
        return $secours;
    }

    $mesure = seo_mesurer($url);
    if ($mesure === null) {
        // Une adresse qu'on ne sait pas mesurer — la carte de `?p=og` —
        // vaut 1200×630 par construction.
        return ['url' => $url, 'largeur' => OG_LARGEUR, 'hauteur' => OG_HAUTEUR,
                'type' => 'image/jpeg'];
    }
    return $mesure['largeur'] < SEO_IMAGE_MIN ? $secours : $mesure;
}

/**
 * Les dimensions et le type d'une image de la maison, ou `null`.
 *
 * @return array{url: string, largeur: int, hauteur: int, type: string}|null
 */
function seo_mesurer(string $url): ?array
{
    $chemin = chemin_image($url);
    if (!$chemin || !is_file($chemin)) {
        return null;
    }
    $t = @getimagesize($chemin);
    if (!$t) {
        return null;
    }
    return ['url' => $url, 'largeur' => (int) $t[0], 'hauteur' => (int) $t[1],
            'type' => (string) ($t['mime'] ?? 'image/png')];
}

/* ------------------------------------------------------------------ */
/* Les données structurées                                             */
/* ------------------------------------------------------------------ */

/** Un bloc JSON-LD, échappé pour vivre dans un `<script>`. */
function jsonld(array $bloc): string
{
    return json_encode(
        $bloc,
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
    ) ?: '{}';
}

/**
 * La maison, telle que Google la comprend.
 *
 * Sert deux fois : le panneau de connaissance à droite des résultats, et
 * l'attribution d'un article à son éditeur. Les profils sociaux sont un
 * réglage parce qu'ils changent — et qu'un profil mort dans le balisage
 * vaut moins que pas de profil du tout.
 */
function jsonld_organisation(): array
{
    $r = seo_reglages();
    $bloc = [
        '@type' => 'Organization',
        '@id' => base_url() . '/#organisation',
        'name' => $r['seo_organisation'],
        'url' => base_url() . '/',
        'logo' => url('public/logo.png'),
    ];
    if ($r['seo_telephone'] !== '') {
        $bloc['telephone'] = $r['seo_telephone'];
    }
    if ($r['seo_ville'] !== '') {
        $bloc['address'] = ['@type' => 'PostalAddress',
                            'addressLocality' => $r['seo_ville'],
                            'addressCountry' => $r['seo_pays']];
    }
    $reseaux = array_values(array_filter(array_map(
        'trim',
        preg_split('/[\s,]+/', $r['seo_reseaux']) ?: []
    )));
    if ($reseaux) {
        $bloc['sameAs'] = $reseaux;
    }
    return $bloc;
}

/** Le fil d'Ariane, pour que le résultat montre « wakabi › blog › l'article ». */
function jsonld_fil(array $etapes): array
{
    $items = [];
    foreach (array_values($etapes) as $i => [$nom, $adresse]) {
        $items[] = ['@type' => 'ListItem', 'position' => $i + 1,
                    'name' => $nom, 'item' => $adresse];
    }
    return ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
}

/* ------------------------------------------------------------------ */
/* robots.txt et sitemap.xml                                           */
/* ------------------------------------------------------------------ */

function robots_txt(): string
{
    if (seo_reglage('seo_indexable') !== '1') {
        // Une installation de recette ne doit pas se retrouver dans Google
        // à côté de la vraie : deux fois le même contenu, et c'est parfois
        // la copie qui gagne.
        return "User-agent: *\nDisallow: /\n";
    }
    $lignes = ["User-agent: *"];
    // On n'interdit pas l'accès — ces écrans demandent une session — on
    // évite qu'ils encombrent les résultats et qu'un robot use le serveur
    // à les demander.
    foreach (['admin', 'comptes', 'reglages', 'sauvegardes', 'journal', 'relecture',
              'regie', 'diffusion', 'liens', 'profil', 'compte', 'partenaire',
              'blog-admin', 'catalogue', 'scan', 'api', 'api-doc'] as $p) {
        $lignes[] = 'Disallow: /index.php?p=' . $p;
    }
    $lignes[] = 'Disallow: /donnees/';
    $lignes[] = '';
    $lignes[] = 'Sitemap: ' . base_url() . '/index.php?p=sitemap';
    return implode("\n", $lignes) . "\n";
}

/**
 * Le plan du site : la vitrine, le blog, et chaque décor publié.
 *
 * Un moteur trouve seul ce qui est lié depuis l'accueil, mais le catalogue
 * est paginé et le blog aussi : sans plan, les pages 3 et suivantes
 * attendent des mois. C'est exactement le contenu qu'on veut voir remonter.
 */
function sitemap_xml(): string
{
    $x = ['<?xml version="1.0" encoding="UTF-8"?>',
          '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'];

    $ajouter = function (string $adresse, ?string $date, string $frequence, string $poids) use (&$x): void {
        $x[] = '  <url>';
        $x[] = '    <loc>' . htmlspecialchars($adresse, ENT_XML1) . '</loc>';
        if ($date) {
            $x[] = '    <lastmod>' . gmdate('Y-m-d', strtotime($date)) . '</lastmod>';
        }
        $x[] = '    <changefreq>' . $frequence . '</changefreq>';
        $x[] = '    <priority>' . $poids . '</priority>';
        $x[] = '  </url>';
    };

    $ajouter(base_url() . '/', null, 'weekly', '1.0');
    $ajouter(url_canonique(['p' => 'decors']), null, 'daily', '0.9');
    $ajouter(url_canonique(['p' => 'blog']), null, 'daily', '0.8');

    foreach (decors_publies(500) as $d) {
        $ajouter(url_canonique(['p' => 'decor', 'slug' => (string) $d['slug']]),
                 (string) ($d['maj_le'] ?? $d['publie_le'] ?? ''), 'weekly', '0.7');
    }
    foreach (articles_publies(500) as $a) {
        $ajouter(url_canonique(['p' => 'blog', 'a' => (string) $a['slug']]),
                 (string) ($a['maj_le'] ?? $a['publie_le'] ?? ''), 'monthly', '0.6');
    }

    $x[] = '</urlset>';
    return implode("\n", $x) . "\n";
}
