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

    vue('article', [
        'titre' => $a['titre'] . ' — Le blog Wakabi',
        'description' => $a['chapo'] ?: texte_extrait((string) $a['corps']),
        'og_titre' => $a['titre'],
        'og_image' => $a['couverture'] ?: url_og(null),
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
    'titre' => $cherche !== '' ? 'Recherche « ' . $cherche . ' » — Le blog' : 'Le blog — Wakabi Boost',
    'description' => 'Nos conseils pour remplir une salle à Lomé, Cotonou et Abidjan : '
        . 'campagnes de badges, affichage, présence à l’entrée.',
    'liste' => articles_publies(BLOG_PAR_PAGE, ($page_n - 1) * BLOG_PAR_PAGE, $cherche),
    'page_n' => $page_n,
    'cherche' => $cherche,
    'pages' => max(1, (int) ceil($total / BLOG_PAR_PAGE)),
    'total' => $total,
]);
