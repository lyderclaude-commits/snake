<?php
/**
 * Le socle d'administration : à inclure en tête du `index.php` du site.
 *
 *     require __DIR__ . '/socle/demarrer.php';
 *     socle_router();          // l'administration, le service worker
 *     // … puis les pages publiques du site
 *
 * Le site se décrit dans `app/definition.php` (voir le README du socle).
 */

declare(strict_types=1);

require __DIR__ . '/noyau/base.php';
require __DIR__ . '/noyau/html.php';
require __DIR__ . '/noyau/medias.php';
require __DIR__ . '/noyau/elements.php';
require __DIR__ . '/noyau/articles.php';
require __DIR__ . '/noyau/courriel.php';
require __DIR__ . '/noyau/messages.php';
require __DIR__ . '/noyau/push.php';
require __DIR__ . '/noyau/traduction.php';
require __DIR__ . '/noyau/wordpress.php';

// Les fichiers du site lui-même (`fichiers` dans la définition), chargés après le socle.
foreach ((array) def('fichiers', []) as $_f) {
    require RACINE . '/' . ltrim((string) $_f, '/');
}
unset($_f);

/**
 * Ce que le socle sert de lui-même : l'administration (`/admin/…`) et le
 * service worker des notifications (`/sw.js`, à la racine pour que sa
 * portée couvre l'administration). Ne rend la main que pour le reste.
 */
function socle_router(): void
{
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    $installer = def('installer');
    if (is_callable($installer)) {
        $installer();
    }
    $chemin = chemin_demande();
    if ($chemin === 'sw.js') {
        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: no-cache');
        header('Service-Worker-Allowed: ' . base_chemin() . '/');
        readfile(SOCLE . '/assets/sw.js');
        exit;
    }
    $segments = $chemin === '' ? [] : explode('/', $chemin);
    if (($segments[0] ?? '') === 'admin') {
        require SOCLE . '/admin/routeur.php';
        admin_router(array_slice($segments, 1), (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        exit;
    }
}
