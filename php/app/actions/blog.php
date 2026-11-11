<?php
/**
 * Le blog, côté lecteur. Publique, sans compte, sans condition.
 *
 * C'est la seule partie du site qui se lit sans rien faire — et c'est
 * précisément ce qui la rend utile : un moteur de recherche indexe du
 * texte, pas un formulaire de génération de badge. Le guide se fait
 * trouver par là.
 *
 * TOUT ce qui paraît ici est un article de la table `articles` — écrit
 * dans Boost ou importé depuis le WordPress du guide. Une fois importé,
 * un article du guide EST un article d'ici : même rang, même page, même
 * forme d'adresse.
 */

/**
 * La page d'un article, quelle que soit sa source.
 *
 * Écrite une fois : la moitié de ce bloc est du balisage de partage, et
 * deux copies auraient divergé au premier ajout — un article du guide
 * partagé sans image pendant des mois, sans que personne ne s'en aperçoive.
 */
$montrer_article = function (array $a, string $canonique) use ($me): never {
    $_desc = seo_description(
        (string) $a['chapo'],
        texte_extrait((string) $a['corps']),
        (string) $a['titre'],
        'Un article du blog ' . seo_reglage('seo_nom_site')
            . ', pour remplir vos salles à Lomé, Cotonou et Abidjan.'
    );

    /* Le décor lié n'existe que pour un article d'ici : WordPress ne
       connaît pas nos campagnes. `decor_lie()` rend `null` sur un
       `decor_id` vide, donc rien à écrire de plus. */
    $decor = decor_lie($a);

    /**
     * « À lire aussi » puise dans les DEUX sources.
     *
     * Un article du guide qui ne renverrait que vers d'autres articles du
     * guide garderait les deux blogs séparés là où on vient justement de
     * les réunir — et le lecteur venu de WordPress ne découvrirait jamais
     * ce qui se publie ici.
     */
    $autres = array_slice(array_filter(
        blog_fusionne(1, 5)['liste'],
        static fn(array $x): bool => (string) $x['id'] !== (string) $a['id']
    ), 0, 3);

    vue('article', [
        'titre' => $a['titre'] . ' · Le blog ' . seo_reglage('seo_nom_site'),
        'description' => $_desc,
        'og_titre' => $a['titre'],
        'og_type' => 'article',
        'canonique' => $canonique,
        'og_image' => illustration_article($a),
        'og_article' => array_filter([
            'published_time' => $a['publie_le'] ?: null,
            'modified_time' => $a['maj_le'] ?: null,
            'author' => $a['auteur_nom'] ?: null,
            'section' => 'Le blog',
        ]),
        'fil' => [
            [seo_reglage('seo_nom_site'), base_url() . '/'],
            ['Le blog', url_canonique(['p' => 'blog'])],
            [(string) $a['titre'], $canonique],
        ],
        'jsonld' => [
            '@type' => 'BlogPosting',
            '@id' => $canonique . '#article',
            'mainEntityOfPage' => $canonique,
            'headline' => mb_substr((string) $a['titre'], 0, 110),
            'description' => $_desc,
            'image' => seo_image(illustration_article($a))['url'],
            'datePublished' => (string) ($a['publie_le'] ?: $a['cree_le']),
            'dateModified' => (string) ($a['maj_le'] ?: $a['cree_le']),
            'author' => ['@type' => 'Person', 'name' => (string) ($a['auteur_nom'] ?: 'La rédaction Wakabi')],
            'publisher' => ['@id' => base_url() . '/#organisation'],
            'inLanguage' => 'fr-FR',
        ],
        'a' => $a,
        'decor' => $decor,
        'lien_article' => $canonique,
        'autres' => $autres,
    ]);
};

/* ---------------- une adresse à l'ancienne marque ---------------- */

/**
 * Les liens partagés avec `&g=…`, l'ancienne marque d'un article du
 * guide, ont continué de fonctionner : le temps de l'import a pu être
 * long, et une adresse partagée ne se casse pas parce que le Site a
 * changé la sienne. Maintenant que l'article importé VIT ICI, l'adresse
 * qui fait foi est `&a=` — on déclare la 301 et on laisse le lecteur
 * suivre. Un doute guette : si le slug n'est pas chez nous pour un
 * article du guide, c'est qu'il n'est pas (encore) importé — 404,
 * comme pour n'importe quoi d'inconnu.
 */
$du_guide = trim((string) ($_GET['g'] ?? ''));
if ($du_guide !== '') {
    $a = article_par_slug($du_guide);
    $visible = $a && ($a['statut'] === 'publie'
        || droit($me, 'valider')
        || ($me && $a['auteur_id'] === $me['id']));
    if (!$visible) {
        http_response_code(404);
        vue('introuvable', ['titre' => 'Article introuvable']);
    }
    header('Location: ' . url_canonique(['p' => 'blog', 'a' => (string) $a['slug']]), true, 301);
    exit;
}

/* ---------------- un article d'ici ---------------- */

$slug = trim((string) ($_GET['a'] ?? ''));

if ($slug !== '') {
    $a = article_par_slug($slug);
    /**
     * Un article non publié n'est visible que de SON AUTEUR et de l'équipe.
     *
     * Une relecture avant publication doit être possible sans mettre
     * l'article en ligne ; le rendre introuvable pour tout le monde
     * obligerait à publier pour se relire. L'auteur y a droit aussi :
     * c'est là qu'il vérifie ce que la rédaction va lire.
     */
    $visible = $a && ($a['statut'] === 'publie'
        || droit($me, 'valider')
        || ($me && $a['auteur_id'] === $me['id']));
    if (!$visible) {
        http_response_code(404);
        vue('introuvable', ['titre' => 'Article introuvable']);
    }
    if ($a['statut'] === 'publie') {
        article_lu((string) $a['id']);
    }

    /**
     * Un article se présente comme un ARTICLE, pas comme une page de site.
     *
     * `og:type=article` fait apparaître la date et l'auteur dans les
     * aperçus et les résultats ; `website` les fait disparaître. Et
     * l'adresse canonique est celle de CET article — c'est la ligne qui
     * manquait, et qui rendait chaque partage muet.
     */
    $montrer_article($a, url_canonique(['p' => 'blog', 'a' => (string) $a['slug']]));
}

/**
 * La pagination par page entière, pas par défilement infini.
 *
 * Une page numérotée a une adresse : elle se partage, elle se met en
 * favori, et un moteur de recherche sait la parcourir. Un défilement
 * infini n'a rien de tout cela.
 */
/* Douze et non neuf : la grille en compte trois par rangée, et douze
   remplissent quatre rangées pleines plutôt que trois. */
const BLOG_PAR_PAGE = 12;
$page_n = max(1, (int) ($_GET['n'] ?? 1));
$cherche = trim((string) ($_GET['q'] ?? ''));
$fusion = blog_fusionne($page_n, BLOG_PAR_PAGE, $cherche);

vue('blog', [
    'titre' => $cherche !== ''
        ? 'Recherche « ' . $cherche . ' » · Le blog'
        : ($page_n > 1
            ? 'Le blog, page ' . $page_n . ' · ' . seo_reglage('seo_nom_site')
            : 'Le blog · ' . seo_reglage('seo_nom_site')),
    /**
     * Une page 2 porte SON titre et SON adresse.
     *
     * Se déclarer canonique vers la page 1 ferait disparaître des moteurs
     * tout ce qui n'est pas récent — c'est-à-dire le fond de catalogue,
     * qui est justement ce qui ramène du monde des mois plus tard.
     */
    'fil' => [[seo_reglage('seo_nom_site'), base_url() . '/'],
              ['Le blog', url_canonique(['p' => 'blog'])]],
    'jsonld' => [
        '@type' => 'Blog',
        '@id' => url_canonique(['p' => 'blog']) . '#blog',
        'name' => 'Le blog ' . seo_reglage('seo_nom_site'),
        'url' => url_canonique(['p' => 'blog']),
        'inLanguage' => 'fr-FR',
        'publisher' => ['@id' => base_url() . '/#organisation'],
    ],
    'description' => 'Nos conseils pour remplir une salle à Lomé, Cotonou et Abidjan : '
        . 'campagnes de badges, affichage, présence à l’entrée.',
    'liste' => $fusion['liste'],
    'page_n' => $page_n,
    'cherche' => $cherche,
    'pages' => $fusion['pages'],
    'total' => $fusion['total'],
]);
