<?php
/**
 * Le site exemple : un accueil (réalisations), un blog, une page Contact.
 *
 * Tout ce qui touche à l'administration, à la base, aux e-mails et aux
 * notifications vient du socle. Ce fichier ne fait que router les pages
 * publiques et appeler les aides du socle.
 */

declare(strict_types=1);

require __DIR__ . '/socle/demarrer.php';
socle_router();

/** Rend une page publique dans le gabarit du site. */
function page(string $vue, array $donnees = [], int $code = 200): never
{
    http_response_code($code);
    extract($donnees, EXTR_SKIP);
    ob_start();
    require __DIR__ . '/app/vues/' . $vue . '.php';
    $contenu = ob_get_clean();
    require __DIR__ . '/app/vues/gabarit.php';
    exit;
}

$chemin = chemin_demande();
$s = $chemin === '' ? [] : explode('/', $chemin);
wp_rediriger_ancienne_adresse($chemin, false);

switch ($s[0] ?? '') {
    case '':
        page('accueil', ['realisations' => elements('realisations')]);

    case 'blog':
        if (isset($s[2]) && $s[2] === 'vue' && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            article_lecture($s[1]);
        }
        if (isset($s[1]) && !isset($s[2])) {
            $a = article_par_slug($s[1], utilisateur_courant_si_session() !== null);
            if ($a) {
                page('article', ['a' => $a, 'titre' => $a['titre']]);
            }
            break;
        }
        if (!isset($s[1])) {
            page('blog', ['liste' => articles_publies((int) ($_GET['page'] ?? 1)), 'titre' => 'Blog']);
        }
        break;

    case 'contact':
        page('contact', contact_traiter('contact') + ['faq' => elements('faq'), 'titre' => 'Contact']);

    case 'sitemap.xml':
        header('Content-Type: application/xml; charset=utf-8');
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach (['', 'blog', 'contact'] as $p) {
            echo '<url><loc>' . e(url_absolue($p)) . '</loc></url>';
        }
        foreach (tous('SELECT slug FROM articles WHERE ' . sql_article_en_ligne()) as $a) {
            echo '<url><loc>' . e(url_absolue('blog/' . rawurlencode($a['slug']))) . '</loc></url>';
        }
        echo "</urlset>\n";
        exit;

    case 'robots.txt':
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nDisallow: /admin/\n\nSitemap: " . url_absolue('sitemap.xml') . "\n";
        exit;
}

wp_rediriger_ancienne_adresse($chemin);
page('introuvable', ['titre' => 'Page introuvable'], 404);
