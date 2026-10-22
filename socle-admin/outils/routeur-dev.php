<?php
/**
 * Le `.htaccess` rejoué pour le serveur intégré de PHP (développement et recette).
 *
 *     php -S 127.0.0.1:8080 -t socle-admin/exemple socle-admin/outils/routeur-dev.php
 */
$racine = (string) $_SERVER['DOCUMENT_ROOT'];
$chemin = ltrim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), '/');
if (preg_match('#^(app|donnees|outils)(/|$)|^socle/(?!assets/)|^config\.php$|(^|/)\.#', $chemin)) {
    http_response_code(403);
    exit('Forbidden');
}
if (str_starts_with($chemin, 'uploads/') && preg_match('#\.(php\d?|phtml|phar)$#i', $chemin)) {
    http_response_code(403);
    exit('Forbidden');
}
if ($chemin !== '' && is_file($racine . '/' . $chemin)) {
    return false;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $racine . '/index.php';
require $racine . '/index.php';
