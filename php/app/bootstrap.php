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

/* ---------------- base de données ---------------- */

function db(): PDO
{
    static $pdo = null;
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

function est_mysql(): bool
{
    return (config()['sgbd'] ?? 'sqlite') === 'mysql';
}

/* ---------------- utilitaires ---------------- */

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
