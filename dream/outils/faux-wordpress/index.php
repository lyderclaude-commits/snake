<?php
/**
 * Un faux WordPress, pour la recette de l'import.
 *
 * Il répond comme `/wp-json/wp/v2` : pagination par `page` et `per_page`,
 * en-têtes `X-WP-Total` et `X-WP-TotalPages`, `_embed` (image mise en
 * avant, catégories, tags, auteur), et un 400 au-delà de la dernière page.
 * Le contenu reprend ce que Gutenberg produit vraiment — figures et
 * `srcset`, galerie, vidéo YouTube — plus quelques pièges : un script, un
 * `onclick`, un `h1`, un lien `javascript:`.
 *
 *     php -S 127.0.0.1:8090 dream/outils/faux-wordpress/index.php
 */
$hote = 'http://' . ($_SERVER['HTTP_HOST'] ?? '127.0.0.1:8090');
$chemin = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$N = (int) (getenv('FAUX_WP_ARTICLES') ?: 23);

/* Les images : un dégradé de la taille demandée, fabriqué à la volée. */
if (preg_match('#^/wp-content/uploads/2024/(\d+)/photo-(\d+)(?:-(\d+)x(\d+))?\.jpg$#', $chemin, $m)) {
    if ((int) $m[2] === 999) { http_response_code(404); exit; } // une image morte, pour tester l'échec
    $w = (int) ($m[3] ?? 0) ?: 1600; $h = (int) ($m[4] ?? 0) ?: 1000;
    $im = imagecreatetruecolor($w, $h);
    $c = imagecolorallocate($im, (int) $m[2] * 37 % 255, 120, 200);
    imagefilledrectangle($im, 0, 0, $w, $h, $c);
    imagestring($im, 5, 20, 20, 'photo ' . $m[2] . ' ' . $w . 'x' . $h, imagecolorallocate($im, 255, 255, 255));
    header('Content-Type: image/jpeg');
    imagejpeg($im, null, 80);
    exit;
}

$cats = [1 => 'Non classé', 2 => 'Stratégie', 3 => 'Conseils', 4 => 'Événementiel'];
$tags = [10 => 'Togo', 11 => 'Community Management', 12 => 'Lomé'];

$post = function (int $i) use ($hote, $cats, $tags): array {
    $mois = str_pad((string) (($i % 12) + 1), 2, '0', STR_PAD_LEFT);
    $u = "$hote/wp-content/uploads/2024/$mois";
    $slug = "article-numero-$i";
    $cat = [2, 3, 4, 1][$i % 4];
    $contenu = <<<HTML
<p>Introduction de l’article $i, avec un <a href="$hote/article-numero-1/">lien vers le premier article</a> et un <a href="javascript:alert(1)">lien piégé</a>.</p>
<h1>Un h1 dans le contenu</h1>
<script>alert('xss')</script>
<div class="wp-block-group"><div class="wp-block-group__inner-container"><p onclick="alert(2)">Paragraphe dans un groupe Gutenberg &amp; ses accents : élève, œuvre.</p></div></div>
<figure class="wp-block-image size-large"><img decoding="async" width="300" height="188" src="$u/photo-$i-300x188.jpg" srcset="$u/photo-$i-300x188.jpg 300w, $u/photo-$i-1024x640.jpg 1024w, $u/photo-$i.jpg 1600w" sizes="(max-width: 300px) 100vw, 300px" class="wp-image-$i" alt="Visuel $i"/><figcaption class="wp-element-caption">Légende de l’image $i</figcaption></figure>
<ul><li>Premier point</li><li><strong>Deuxième</strong> point</li></ul>
<figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube"><div class="wp-block-embed__wrapper"><iframe title="Vidéo" width="500" height="281" src="https://www.youtube.com/embed/dQw4w9WgXcQ?feature=oembed" frameborder="0" allowfullscreen></iframe></div></figure>
<p><a href="$u/photo-$i.jpg"><img src="$u/photo-$i-150x150.jpg" alt=""/></a></p>
HTML;
    if ($i === 5) {
        $contenu .= "\n<p><img src=\"$u/photo-999.jpg\" alt=\"morte\"/></p>";
    }
    return [
        'id' => 100 + $i,
        'date' => sprintf('2024-%s-%02dT10:%02d:00', $mois, ($i % 27) + 1, $i % 60),
        'date_gmt' => sprintf('2024-%s-%02dT10:%02d:00', $mois, ($i % 27) + 1, $i % 60),
        'slug' => $slug,
        'status' => 'publish',
        'link' => "$hote/2024/$mois/$slug/",
        'title' => ['rendered' => "Article n°$i &#8211; l&rsquo;exemple"],
        'content' => ['rendered' => $contenu, 'protected' => false],
        'excerpt' => ['rendered' => "<p>Résumé de l’article $i [&hellip;]</p>\n"],
        'author' => 1,
        'featured_media' => $i === 7 ? 0 : 500 + $i,
        'categories' => [$cat],
        'tags' => [10, 11 + ($i % 2)],
        '_embedded' => [
            'author' => [['id' => 1, 'name' => 'Ulrich']],
            'wp:featuredmedia' => $i === 7 ? [] : [['id' => 500 + $i, 'source_url' => "$u/photo-$i.jpg", 'media_type' => 'image']],
            'wp:term' => [
                [['id' => $cat, 'name' => $cats[$cat], 'taxonomy' => 'category']],
                [['id' => 10, 'name' => $tags[10], 'taxonomy' => 'post_tag'], ['id' => 11 + ($i % 2), 'name' => $tags[11 + ($i % 2)], 'taxonomy' => 'post_tag']],
            ],
        ],
    ];
};

if ($chemin === '/wp-json/wp/v2/posts') {
    $par = min(100, max(1, (int) ($_GET['per_page'] ?? 10)));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $pages = (int) ceil($N / $par);
    header('Content-Type: application/json; charset=UTF-8');
    header("X-WP-Total: $N");
    header("X-WP-TotalPages: $pages");
    if ($page > $pages) {
        http_response_code(400);
        echo json_encode(['code' => 'rest_post_invalid_page_number', 'message' => 'Le nombre de page demandé est plus grand que le nombre de pages disponible.']);
        exit;
    }
    $l = [];
    for ($i = ($page - 1) * $par + 1; $i <= min($N, $page * $par); $i++) {
        $p = $post($i);
        if (empty($_GET['_embed'])) {
            unset($p['_embedded']);
        }
        $l[] = $p;
    }
    echo json_encode($l, JSON_UNESCAPED_SLASHES);
    exit;
}
http_response_code(404);
header('Content-Type: application/json');
echo '{"code":"rest_no_route"}';
