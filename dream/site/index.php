<?php
/**
 * Point d'entrée unique du site Live Y Dream.
 *
 * Le `.htaccess` envoie ici toute adresse qui n'est pas un fichier réel :
 * `/blog/mon-article`, `/admin/articles`, mais aussi les adresses de
 * l'ancien WordPress, qu'on redirige vers leur nouvelle place.
 */

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/html.php';
require __DIR__ . '/app/medias.php';
require __DIR__ . '/app/contenu.php';
require __DIR__ . '/app/wordpress.php';
require __DIR__ . '/app/site.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

$chemin = chemin_demande();
$segments = $chemin === '' ? [] : explode('/', $chemin);
$methode = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/* L’administration a son propre routeur (app/admin.php). */
if (($segments[0] ?? '') === 'admin') {
    require __DIR__ . '/app/admin.php';
    admin_router(array_slice($segments, 1), $methode);
    exit;
}

/* Les anciennes pages statiques (`services.html`) et WordPress (`?p=123`). */
if (preg_match('#^(index|services|campagnes|blog|a-propos|contact)\.html$#', $chemin, $m)) {
    rediriger($m[1] === 'index' ? '' : $m[1], 301);
}
if ($chemin === '' && isset($_GET['p']) && ctype_digit((string) $_GET['p'])) {
    $a = un('SELECT slug FROM articles WHERE wp_id = ? AND ' . sql_article_en_ligne(), [(int) $_GET['p']]);
    rediriger($a ? 'blog/' . rawurlencode($a['slug']) : 'blog', 301);
}

switch ($segments[0] ?? '') {
    case '':
        vue_site('accueil', [
            'page' => 'accueil',
            'campagnes' => campagnes_publiees(4),
            'canonique' => url_absolue(''),
        ]);

    case 'services':
    case 'a-propos':
        if (count($segments) > 1) {
            break;
        }
        vue_site($segments[0]);

    case 'campagnes':
        if (count($segments) > 1) {
            break;
        }
        vue_site('campagnes', ['campagnes' => campagnes_publiees()]);

    case 'blog':
        if (($segments[1] ?? '') === 'flux.xml') {
            header('Content-Type: application/rss+xml; charset=utf-8');
            require __DIR__ . '/app/vues/site/flux.php';
            exit;
        }
        if (isset($segments[1])) {
            $u = utilisateur_courant_si_session();
            $a = article_par_slug($segments[1], $u !== null);
            if (!$a) {
                break;
            }
            if ($a['statut'] === 'publie' && !$u) {
                q('UPDATE articles SET lectures = lectures + 1 WHERE id = ?', [$a['id']]);
            }
            $autres = tous('SELECT * FROM articles WHERE ' . sql_article_en_ligne() . ' AND id <> ?
                ORDER BY (categorie = ?) DESC, publie_le DESC LIMIT 3', [$a['id'], $a['categorie']]);
            vue_site('article', [
                'page' => 'article',
                'a' => $a,
                'autres' => $autres,
                'og_type' => 'article',
                'og_image' => article_image($a),
                'og_titre' => $a['titre'],
                'canonique' => url_absolue('blog/' . rawurlencode($a['slug'])),
                'noindex' => $a['statut'] !== 'publie',
                'jsonld' => [
                    '@type' => 'BlogPosting',
                    'headline' => $a['titre'],
                    'description' => article_extrait($a, 200),
                    'image' => absolu(article_image($a)),
                    'datePublished' => date('c', strtotime((string) $a['publie_le']) ?: time()),
                    'dateModified' => date('c', strtotime((string) $a['modifie_le']) ?: time()),
                    'author' => ['@type' => 'Person', 'name' => $a['auteur'] ?: reglage('nom_site')],
                    'publisher' => ['@id' => url_absolue('') . '#organisation'],
                    'mainEntityOfPage' => url_absolue('blog/' . rawurlencode($a['slug'])),
                ],
            ]);
        }
        $categorie = trim((string) ($_GET['categorie'] ?? ''));
        $tag = trim((string) ($_GET['tag'] ?? ''));
        $recherche = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
        $liste = articles_publies((int) ($_GET['page'] ?? 1), $categorie, $tag, $recherche);
        vue_site('blog', [
            'liste' => $liste,
            'categorie' => $categorie,
            'tag' => $tag,
            'recherche' => $recherche,
            'categories' => categories_articles(),
            'tags' => tags_articles(),
            'titre' => $categorie !== '' ? $categorie . ' — Blog | ' . reglage('nom_site') : null,
            // Les pages filtrées ou paginées ne doublonnent pas le blog dans les moteurs.
            'noindex' => $recherche !== '' || $tag !== '',
            'canonique' => url_absolue('blog' . ($categorie !== '' ? '?categorie=' . rawurlencode($categorie) : '')
                . ($liste['page'] > 1 ? ($categorie !== '' ? '&' : '?') . 'page=' . $liste['page'] : '')),
        ]);

    case 'contact':
        if (count($segments) > 1) {
            break;
        }
        $erreur = null;
        $envoye = isset($_GET['envoye']);
        $v = [];
        if ($methode === 'POST') {
            csrf_verifier();
            $erreur = contact_recevoir($_POST);
            if ($erreur === null) {
                rediriger('contact?envoye=1#formSuccess');
            }
            $v = $_POST;
        }
        vue_site('contact', ['erreur' => $erreur, 'envoye' => $envoye, 'v' => $v, 'noindex' => $envoye]);

    case 'mentions-legales':
        vue_site('legale', ['titre_page' => 'Mentions légales', 'texte' => reglage('mentions_legales'), 'defaut' => mentions_defaut()]);

    case 'confidentialite':
        vue_site('legale', ['titre_page' => 'Politique de confidentialité', 'texte' => reglage('confidentialite'), 'defaut' => confidentialite_defaut()]);

    case 'sitemap.xml':
        header('Content-Type: application/xml; charset=utf-8');
        echo sitemap();
        exit;

    case 'robots.txt':
        header('Content-Type: text/plain; charset=utf-8');
        echo "User-agent: *\nDisallow: /admin/\nAllow: /\n\nSitemap: " . url_absolue('sitemap.xml') . "\n";
        exit;
}

/**
 * Rien ne correspond : peut-être une adresse de l'ancien WordPress.
 * `/2024/03/mon-article/`, `/category/x/mon-article/` ou `/mon-article/`
 * pointent vers le même article ici — redirection permanente, pour que
 * Google transfère ce qu'il savait de l'ancienne adresse.
 */
if ($methode === 'GET') {
    $a = wp_article_pour_chemin($chemin);
    if ($a) {
        rediriger('blog/' . rawurlencode($a['slug']), 301);
    }
    if (preg_match('#^(category|tag)/([^/]+)#', $chemin, $m)) {
        rediriger('blog', 301);
    }
    if (preg_match('#^(feed|rss)/?$#', $chemin)) {
        rediriger('blog/flux.xml', 301);
    }
}
vue_site('introuvable', [], 404);

/** Ne démarre pas de session pour un simple visiteur : seulement s'il en a déjà une. */
function utilisateur_courant_si_session(): ?array
{
    return isset($_COOKIE['lyd_session']) ? utilisateur_courant() : null;
}

function mentions_defaut(): string
{
    $r = reglages();
    return '<h2>Éditeur du site</h2><p>' . e($r['nom_site']) . ' — agence de communication.<br>' . e($r['adresse']) . ', '
        . e($r['adresse2']) . '<br>Téléphone : ' . e($r['telephone']) . '<br>E-mail : <a href="mailto:' . e($r['email']) . '">'
        . e($r['email']) . '</a></p><h2>Hébergement</h2><p>Le site est hébergé par son prestataire d’hébergement. '
        . 'Ses coordonnées complètes sont à compléter depuis l’administration (Réglages → Pages légales).</p>'
        . '<h2>Propriété intellectuelle</h2><p>Les textes, visuels et réalisations présentés sur ce site appartiennent à '
        . e($r['nom_site']) . ' ou à ses clients. Toute reproduction sans autorisation est interdite.</p>';
}

function confidentialite_defaut(): string
{
    $r = reglages();
    return '<h2>Les données que nous recevons</h2><p>Lorsque vous remplissez le formulaire de contact, nous recevons votre nom, '
        . 'votre entreprise, votre e-mail, votre numéro WhatsApp et votre message. Ces informations servent uniquement à vous répondre '
        . 'et à préparer votre devis. Elles ne sont ni vendues ni transmises à des tiers.</p>'
        . '<h2>Durée de conservation</h2><p>Les demandes sont conservées le temps nécessaire au suivi commercial, puis supprimées.</p>'
        . '<h2>Mesure d’audience et cookies</h2><p>Le site ne dépose aucun cookie publicitaire ni traceur tiers. Un cookie technique est '
        . 'utilisé uniquement pour l’espace d’administration.</p>'
        . '<h2>Vos droits</h2><p>Vous pouvez demander l’accès, la correction ou la suppression de vos données en écrivant à '
        . '<a href="mailto:' . e($r['email']) . '">' . e($r['email']) . '</a>.</p>';
}
