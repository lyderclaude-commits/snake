<?php
/**
 * Le `.htaccess` du paquet, rejoué pour le serveur intégré de PHP.
 *
 * `php -S` ne lit AUCUN `.htaccess` : les adresses lisibles, les liens
 * courts, `robots.txt` et `sitemap.xml` ne sont donc pas éprouvables sans
 * lui. Et un comportement qu'aucun scénario ne touche est un comportement
 * qu'on livre sans savoir — c'est exactement ce qui est arrivé au bouton
 * « Vérifier la forme courte », dont la fonction manquait.
 *
 * Ce fichier ne part PAS dans le zip (il vit dans `scripts/`), et ne sert
 * qu'au développement et à la recette. Il rejoue les mêmes règles, dans
 * le même ordre que `php/.htaccess`, et ne décide de rien par lui-même :
 * ce qu'il reproduit, c'est Apache, pas l'application.
 *
 *     php -S 127.0.0.1:3600 -t php scripts/routeur-php.php
 *
 * ATTENTION, la limite : il reproduit les règles, pas le serveur. Que la
 * forme lisible marche ici ne dit rien de l'hébergement de destination —
 * seul l'essai de l'écran des réglages, qui demande au vrai serveur de se
 * répondre, le prouve là-bas.
 */

declare(strict_types=1);

$racine = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
$chemin = ltrim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), '/');

/* `RewriteRule ^donnees/ - [F,L]` et ses voisines : ces dossiers ne sont
   jamais servis, même s'ils existent sur le disque. */
foreach (['donnees/', 'app/'] as $interdit) {
    if (str_starts_with($chemin, $interdit)) {
        http_response_code(403);
        exit('Forbidden');
    }
}
if ($chemin === 'config.php') {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Un fichier réel se sert tel quel — les deux gardes `!-f !-d`.
 *
 * C'est aussi ce qui laisse `/index.php?p=…` et `/` passer par le chemin
 * habituel : le serveur intégré les traite lui-même, et `SCRIPT_NAME`
 * garde la valeur que l'application attend.
 */
$disque = $racine . '/' . $chemin;
if ($chemin !== '' && (is_file($disque) || is_dir($disque))) {
    return false;
}
if ($chemin === '') {
    return false;
}

/**
 * Ce que le serveur raconte à l'application après une réécriture.
 *
 * Apache remplace le chemin par celui de la cible : `SCRIPT_NAME` vaut
 * `/index.php`, quelle que soit l'adresse demandée. Sans ces trois
 * lignes, `base_url()` déduirait sa base de `dirname('/decors/x')` et
 * fabriquerait des adresses sous `/decors` — toutes les feuilles de style
 * et tous les liens du site, décalés d'un cran.
 */
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $racine . '/index.php';

if ($chemin === 'robots.txt') {
    $_GET['p'] = 'robots';
} elseif ($chemin === 'sitemap.xml') {
    $_GET['p'] = 'sitemap';
} elseif (preg_match('~^([A-HJ-NP-Za-hj-np-z2-9]{6})$~', $chemin, $m)) {
    // Les liens courts passent AVANT la règle attrape-tout, comme dans le
    // `.htaccess`. C'est cet ordre qui explique le garde-fou de
    // `permaliens_jolie()` : un mot de six caractères de cet alphabet ne
    // peut pas servir d'adresse de page.
    $_GET['p'] = 'l';
    $_GET['c'] = $m[1];
} else {
    $_GET['wk'] = $chemin;
}

require $racine . '/index.php';
