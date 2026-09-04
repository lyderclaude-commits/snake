<?php
/**
 * Le blog, côté lecteur. Publique, sans compte, sans condition.
 *
 * C'est la seule partie du site qui se lit sans rien faire — et c'est
 * précisément ce qui la rend utile : un moteur de recherche indexe du
 * texte, pas un formulaire de génération de badge. Le guide se fait
 * trouver par là.
 */
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
    $lien_canonique = url_canonique(['p' => 'blog', 'a' => (string) $a['slug']]);

    /**
     * Le chapô n'est pas obligatoire, et un corps peut tenir en une ligne.
     * On descend donc jusqu'à ce qui reste vrai de tout article du blog.
     */
    $_desc = seo_description(
        (string) $a['chapo'],
        texte_extrait((string) $a['corps']),
        (string) $a['titre'],
        'Un article du blog ' . seo_reglage('seo_nom_site')
            . ', pour remplir vos salles à Lomé, Cotonou et Abidjan.'
    );
    vue('article', [
        'titre' => $a['titre'] . ' — Le blog ' . seo_reglage('seo_nom_site'),
        'description' => $_desc,
        'og_titre' => $a['titre'],
        'og_type' => 'article',
        'canonique' => $lien_canonique,
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
            [(string) $a['titre'], $lien_canonique],
        ],
        'jsonld' => [
            '@type' => 'BlogPosting',
            '@id' => $lien_canonique . '#article',
            'mainEntityOfPage' => $lien_canonique,
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
        // Vérifié à la LECTURE, pas à l'écriture : un décor archivé depuis
        // la parution ne laisse pas une carte qui mène à une page morte.
        'decor' => decor_lie($a),
        'lien_article' => base_url() . '/index.php?p=blog&a=' . rawurlencode((string) $a['slug']),
        'autres' => array_slice(array_filter(
            articles_publies(4),
            fn(array $x) => $x['id'] !== $a['id']
        ), 0, 3),
    ]);
}

/**
 * La pagination par page entière, pas par défilement infini.
 *
 * Une page numérotée a une adresse : elle se partage, elle se met en
 * favori, et un moteur de recherche sait la parcourir. Un défilement
 * infini n'a rien de tout cela.
 */
const BLOG_PAR_PAGE = 9;
$page_n = max(1, (int) ($_GET['n'] ?? 1));
$cherche = trim((string) ($_GET['q'] ?? ''));
$total = compter_articles_publies_cherches($cherche);

vue('blog', [
    'titre' => $cherche !== ''
        ? 'Recherche « ' . $cherche . ' » — Le blog'
        : ($page_n > 1
            ? 'Le blog, page ' . $page_n . ' — ' . seo_reglage('seo_nom_site')
            : 'Le blog — ' . seo_reglage('seo_nom_site')),
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
    'liste' => articles_publies(BLOG_PAR_PAGE, ($page_n - 1) * BLOG_PAR_PAGE, $cherche),
    'page_n' => $page_n,
    'cherche' => $cherche,
    'pages' => max(1, (int) ceil($total / BLOG_PAR_PAGE)),
    'total' => $total,
]);
