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
require __DIR__ . '/app/courriel.php';
require __DIR__ . '/app/traduction.php';
require __DIR__ . '/app/site.php';
require __DIR__ . '/app/langue.php';
require __DIR__ . '/app/accueil.php';
require __DIR__ . '/app/push.php';

installer_accueil();

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

/**
 * La langue : `/en/…` pour l'anglais, tout le reste en français.
 *
 * Les adresses anglaises ont leurs propres mots (`/en/campaigns`) ; ici on
 * les ramène aux pages internes, qui ne connaissent que le français. Une
 * adresse mal formée (`/en/campagnes`, `/campaigns`) est redirigée vers la
 * bonne, pour qu'une page n'ait jamais deux adresses.
 */
$routes_fr = array_flip(ROUTES_EN);
if (($segments[0] ?? '') === 'en') {
    langue('en');
    array_shift($segments);
    $tete = $segments[0] ?? '';
    if (!isset($routes_fr[$tete]) && isset(ROUTES_EN[$tete]) && $methode === 'GET') {
        rediriger(implode('/', $segments) . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : ''), 301);
    }
    if (isset($routes_fr[$tete])) {
        $segments[0] = $routes_fr[$tete];
    }
    $segments = array_values(array_filter($segments, fn($x) => $x !== ''));
} elseif (isset($segments[0]) && isset($routes_fr[$segments[0]]) && !isset(ROUTES_EN[$segments[0]]) && $methode === 'GET') {
    langue('en');
    rediriger(implode('/', array_merge([$routes_fr[$segments[0]]], array_slice($segments, 1))), 301);
}
$chemin = implode('/', $segments);

/* Le sélecteur de langue passe `?langue=` : on retient le choix, puis on rend une adresse propre. */
if (isset($_GET['langue']) && isset(LANGUES[$_GET['langue']])) {
    langue((string) $_GET['langue']);
    langue_retenir(langue());
    $q = $_GET;
    unset($q['langue']);
    rediriger($chemin . ($q ? '?' . http_build_query($q) : ''), 302);
}
if ($methode === 'GET' && in_array($segments[0] ?? '', array_keys(ROUTES_EN), true)) {
    if ($chemin === '' && langue() === 'fr' && !isset($_COOKIE['lyd_langue']) && langue_preferee() === 'en' && !$_GET) {
        langue('en');
        rediriger('', 302);
    }
    langue_retenir(langue());
}
/** La page courante, écrite en interne : le sélecteur de langue et `hreflang` en ont besoin. */
$q = $_GET;
unset($q['envoye'], $q['langue']);
$GLOBALS['page_interne'] = $chemin . ($q ? '?' . http_build_query($q) : '');

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
            'campagnes' => array_map('campagne_l', campagnes_publiees(5)),
            'services' => services_visibles(),
            'temoignages' => temoignages_visibles(),
            'clients' => clients_visibles(),
            'canonique' => url_absolue(''),
        ]);

    case 'services':
        if (count($segments) > 1) {
            break;
        }
        vue_site('services', ['services' => services_visibles()]);

    case 'a-propos':
        if (count($segments) > 1) {
            break;
        }
        vue_site('a-propos');

    case 'campagnes':
        if (count($segments) > 1) {
            break;
        }
        vue_site('campagnes', ['campagnes' => array_map('campagne_l', campagnes_publiees())]);

    case 'blog':
        if (($segments[1] ?? '') === 'flux.xml') {
            header('Content-Type: application/rss+xml; charset=utf-8');
            require __DIR__ . '/app/vues/site/flux.php';
            exit;
        }
        /**
         * Une lecture, signalée par la page elle-même une fois affichée.
         *
         * Compter au chargement côté serveur comptait les robots, les
         * aperçus de liens de WhatsApp, et ne comptait PAS l'équipe connectée
         * — si bien qu'en vérifiant le site soi-même, le compteur ne bougeait
         * jamais. La page envoie un signal ; le navigateur n'en envoie qu'un
         * par onglet et par article.
         */
        if (isset($segments[2]) && $segments[2] === 'vue' && $methode === 'POST') {
            $a = article_par_slug($segments[1]);
            if ($a && !est_robot()) {
                q('UPDATE articles SET lectures = lectures + 1 WHERE id = ?', [$a['id']]);
            }
            http_response_code(204);
            exit;
        }
        if (isset($segments[1])) {
            if (isset($segments[2])) {
                break;
            }
            $u = utilisateur_courant_si_session();
            $a = article_par_slug($segments[1], $u !== null);
            if (!$a) {
                break;
            }
            $traduit = article_traduit($a);
            $autres = tous('SELECT * FROM articles WHERE ' . sql_article_en_ligne() . ' AND id <> ?
                ORDER BY (categorie = ?) DESC, publie_le DESC LIMIT 3', [$a['id'], $a['categorie']]);
            vue_site('article', [
                'page' => 'article',
                'a' => $a,
                'autres' => $autres,
                'og_type' => 'article',
                'og_image' => article_image($a),
                'og_titre' => article_l($a, 'titre'),
                // Sans traduction, la page anglaise n'est qu'un doublon : elle renvoie à l'original.
                'canonique' => url_absolue('blog/' . rawurlencode($a['slug']), $traduit ? null : 'fr'),
                'langues' => $traduit ? null : ['fr'],
                'noindex' => $a['statut'] !== 'publie',
                'jsonld' => [
                    '@type' => 'BlogPosting',
                    'headline' => article_l($a, 'titre'),
                    'inLanguage' => langue() === 'en' && $traduit ? 'en' : 'fr',
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
            'titre' => $categorie !== '' ? t($categorie) . ' — Blog | ' . reglage('nom_site') : null,
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
            $id = null;
            $erreur = contact_recevoir($_POST, $id);
            if ($erreur === null) {
                /**
                 * Le visiteur n'attend pas l'e-mail ni les notifications.
                 *
                 * Sur PHP-FPM (la plupart des hébergeurs), la réponse part
                 * d'abord et les envois se font ensuite. Ailleurs, ils se
                 * font avant la redirection : plus lent, mais rien ne se perd.
                 */
                if ($id !== null && function_exists('fastcgi_finish_request')) {
                    header('Location: ' . url('contact?envoye=1#formSuccess'), true, 303);
                    fastcgi_finish_request();
                    contact_notifier($id);
                    exit;
                }
                if ($id !== null) {
                    contact_notifier($id);
                }
                rediriger('contact?envoye=1#formSuccess');
            }
            $v = $_POST;
        }
        vue_site('contact', ['erreur' => $erreur, 'envoye' => $envoye, 'v' => $v, 'noindex' => $envoye]);

    case 'mentions-legales':
        vue_site('legale', ['titre_page' => t('Mentions légales'), 'texte' => reglage_l('mentions_legales'), 'defaut' => mentions_defaut()]);

    case 'confidentialite':
        vue_site('legale', ['titre_page' => t('Politique de confidentialité'), 'texte' => reglage_l('confidentialite'), 'defaut' => confidentialite_defaut()]);

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
if ($methode === 'GET' && langue() === 'fr') {
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
    return '<h2>' . e(t('Éditeur du site')) . '</h2><p>' . e(t('%s — agence de communication.', $r['nom_site'])) . '<br>'
        . e($r['adresse']) . ', ' . e(reglage_l('adresse2')) . '<br>' . e(t('Téléphone : %s', $r['telephone'])) . '<br>'
        . e(t('E-mail :')) . ' <a href="mailto:' . e($r['email']) . '">' . e($r['email']) . '</a></p>'
        . '<h2>' . e(t('Hébergement')) . '</h2><p>' . e(t('Le site est hébergé par son prestataire d’hébergement. Ses coordonnées complètes sont à compléter depuis l’administration (Réglages → Pages légales).')) . '</p>'
        . '<h2>' . e(t('Propriété intellectuelle')) . '</h2><p>'
        . e(t('Les textes, visuels et réalisations présentés sur ce site appartiennent à %s ou à ses clients. Toute reproduction sans autorisation est interdite.', $r['nom_site'])) . '</p>';
}

function confidentialite_defaut(): string
{
    $r = reglages();
    $bloc = fn(string $titre, string $texte): string => '<h2>' . e(t($titre)) . '</h2><p>' . e(t($texte)) . '</p>';
    return $bloc('Les données que nous recevons', 'Lorsque vous remplissez le formulaire de contact, nous recevons votre nom, votre entreprise, votre e-mail, votre numéro WhatsApp et votre message. Ces informations servent uniquement à vous répondre et à préparer votre devis. Elles ne sont ni vendues ni transmises à des tiers.')
        . $bloc('Durée de conservation', 'Les demandes sont conservées le temps nécessaire au suivi commercial, puis supprimées.')
        . $bloc('Mesure d’audience et cookies', 'Le site ne dépose aucun cookie publicitaire ni traceur tiers. Des cookies techniques servent uniquement à retenir la langue choisie et à l’espace d’administration.')
        . '<h2>' . e(t('Vos droits')) . '</h2><p>' . e(t('Vous pouvez demander l’accès, la correction ou la suppression de vos données en écrivant à'))
        . ' <a href="mailto:' . e($r['email']) . '">' . e($r['email']) . '</a>.</p>';
}
