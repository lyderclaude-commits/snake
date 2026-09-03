<?php
/**
 * Socle commun — configuration, base, session, utilitaires.
 *
 * Tout fichier de l'application commence par l'inclure. Il ne suppose ni
 * Composer, ni autoloader, ni extension exotique : PDO, GD et les sessions
 * suffisent, et c'est ce que tout hébergement mutualisé propose.
 */

declare(strict_types=1);

define('RACINE', dirname(__DIR__));

/* ---------------- configuration ---------------- */

function config(): array
{
    static $c = null;
    if ($c !== null) {
        return $c;
    }
    /**
     * `WAKABI_CONFIG` détourne la configuration, pour les essais.
     *
     * Vérifier le transport e-mail demande une base à soi : écrire dans
     * celle de développement pour éprouver un envoi serait un drôle de
     * remède. La variable n'est lisible que dans l'environnement du
     * processus — quelqu'un capable de l'y poser tient déjà le serveur.
     */
    $essai = getenv('WAKABI_CONFIG');
    if (is_string($essai) && $essai !== '' && is_file($essai)) {
        return $c = require $essai;
    }

    $fichier = RACINE . '/config.php';
    if (!is_file($fichier)) {
        // Pas encore installé : l'installateur prend la main.
        if (basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php') {
            header('Location: install.php');
            exit;
        }
        return $c = [];
    }
    return $c = require $fichier;
}

function reglage(string $cle, mixed $defaut = null): mixed
{
    return config()[$cle] ?? $defaut;
}

/**
 * Adresse publique du site.
 *
 * Elle finit dans le QR de chaque badge, donc dans un fichier que l'invité
 * garde et partage. Une erreur ici est définitive : on ne rappelle pas une
 * image déjà partagée. D'où la déduction automatique plutôt qu'une saisie.
 */
function base_url(): string
{
    $config = reglage('base_url');
    if ($config) {
        return rtrim($config, '/');
    }
    $https = ($_SERVER['HTTPS'] ?? '') === 'on'
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
        || ($_SERVER['SERVER_PORT'] ?? '') === '443';
    $hote = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
    return ($https ? 'https://' : 'http://') . $hote . $base;
}

function url(string $chemin = ''): string
{
    return base_url() . '/' . ltrim($chemin, '/');
}

/**
 * L'adresse d'un fichier servi tel quel, avec une empreinte de sa version.
 *
 * Sans elle, une mise à jour ne change RIEN pour qui a déjà visité le
 * site : le navigateur garde sa copie de `wakabi.css`, et l'écran arrive
 * avec le nouveau HTML sur l'ancien style — des liens bleus soulignés là
 * où l'on attendait des cartes. Le pire est que ça ne se voit pas depuis
 * un navigateur neuf, donc jamais pendant qu'on développe.
 *
 * L'empreinte est la date de modification du fichier : elle change quand
 * le fichier change, et jamais autrement. Un fichier absent rend l'adresse
 * nue plutôt qu'une erreur — un style manquant vaut mieux qu'une page
 * blanche.
 */
function actif(string $chemin): string
{
    $disque = RACINE . '/' . ltrim($chemin, '/');
    $t = @filemtime($disque);
    return url($chemin) . ($t ? '?v=' . substr(dechex($t), -6) : '');
}

/* ---------------- dossiers de données ---------------- */

function dossier_donnees(): string
{
    $d = reglage('dossier_donnees') ?: RACINE . '/donnees';
    if (!is_dir($d)) {
        @mkdir($d, 0775, true);
    }
    return $d;
}

function dossier_cadres(): string
{
    $d = dossier_donnees() . '/cadres';
    if (!is_dir($d)) {
        @mkdir($d, 0775, true);
    }
    return $d;
}

/**
 * Les images téléversées AUTRES que les cadres : couvertures d'articles.
 *
 * Un dossier à part, parce que la règle de service n'est pas la même : un
 * cadre est un calque à trous qu'on superpose, une couverture est une
 * photo qu'on affiche. Les mélanger obligerait à deviner lequel est
 * lequel au moment de le servir.
 */
function dossier_medias(): string
{
    $d = dossier_donnees() . '/medias';
    if (!is_dir($d)) {
        @mkdir($d, 0775, true);
    }
    return $d;
}

/* ---------------- base de données ---------------- */

/**
 * La connexion partagée — et le seul endroit qui la garde.
 *
 * Séparée de `db()` pour une raison précise : la restauration d'une
 * sauvegarde remplace le fichier SQLite SOUS la connexion, et doit donc
 * pouvoir la fermer sans en rouvrir une aussitôt sur l'ancien fichier.
 * Un `db()` qui ferme puis rouvre dans le même souffle ne ferme rien —
 * l'écran suivant montrerait encore les données d'avant.
 */
function pdo_partage(bool $fermer = false): ?PDO
{
    static $pdo = null;
    if ($fermer) {
        $pdo = null;
        return null;
    }
    if ($pdo !== null) {
        return $pdo;
    }

    $c = config();
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if (($c['sgbd'] ?? 'sqlite') === 'mysql') {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $c['hote'] ?? 'localhost',
            (int) ($c['port'] ?? 3306),
            $c['base'] ?? ''
        );
        $pdo = new PDO($dsn, $c['utilisateur'] ?? '', $c['motdepasse'] ?? '', $options);
    } else {
        $pdo = new PDO('sqlite:' . ($c['fichier'] ?? dossier_donnees() . '/wakabi.sqlite'), null, null, $options);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    return $pdo;
}

function db(): PDO
{
    /** @var PDO */
    return pdo_partage();
}

/** Ferme la connexion partagée. Ne sert qu'à la restauration. */
function db_fermer(): void
{
    pdo_partage(true);
}

function est_mysql(): bool
{
    return (config()['sgbd'] ?? 'sqlite') === 'mysql';
}

/* ---------------- utilitaires ---------------- */

/**
 * La version du produit.
 *
 * Elle nomme l'archive livrée et s'affiche en pied de page : quand
 * quelqu'un écrit « ça ne marche pas », la première question est « quelle
 * version ? », et personne ne sait y répondre si le produit ne le dit pas
 * lui-même.
 */
const VERSION = '1';

/** Une date lisible ici : `12/03/2026`. Vide si l'on ne sait pas la lire. */
function date_fr(?string $iso): string
{
    $t = strtotime((string) $iso);
    return $t ? gmdate('d/m/Y', $t) : '';
}

/**
 * L'horodatage du projet : UTC, ISO 8601, triable comme du texte.
 *
 * L'argument sert aux échéances — « dans 48 heures » s'écrit alors dans le
 * même format que tout le reste, donc se compare avec un simple `<`.
 */
function maintenant(?int $horodatage = null): string
{
    return gmdate('Y-m-d\TH:i:s\Z', $horodatage ?? time());
}

function nouvel_id(): string
{
    // UUID v4, sans dépendance.
    $o = random_bytes(16);
    $o[6] = chr(ord($o[6]) & 0x0f | 0x40);
    $o[8] = chr(ord($o[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($o), 4));
}

/** Échappement HTML — à utiliser pour TOUTE valeur venue de la base. */
function e(?string $s): string
{
    return htmlspecialchars($s ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function slugifier(string $t): string
{
    $t = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t) ?: $t;
    $t = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $t) ?? '');
    return trim($t, '-') ?: 'decor';
}

function json_lire(string $s): array
{
    $d = json_decode($s, true);
    return is_array($d) ? $d : [];
}

/* ---------------- session ---------------- */

function demarrer_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = str_starts_with(base_url(), 'https://');
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        // Sans HTTPS le drapeau Secure empêcherait toute connexion : il suit
        // donc l'adresse réelle du site plutôt qu'une constante.
        'secure' => $https,
    ]);
    session_name('wakabi');
    session_start();
}

/* ---------------- réponses ---------------- */

function rediriger(string $chemin): never
{
    header('Location: ' . url($chemin));
    exit;
}

function json_repondre(array $donnees, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Jeton anti-CSRF, un par session. */
function jeton_csrf(): string
{
    demarrer_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function verifier_csrf(): void
{
    demarrer_session();
    $recu = $_POST['csrf'] ?? '';
    if (!is_string($recu) || !hash_equals($_SESSION['csrf'] ?? '', $recu)) {
        http_response_code(400);
        exit('Requête invalide. Rechargez la page et réessayez.');
    }
}
