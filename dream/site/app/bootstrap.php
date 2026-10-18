<?php
/**
 * Le socle : configuration, base de données, session, sécurité, aides.
 *
 * Le site tourne sur un hébergement mutualisé PHP, sans terminal ni
 * Composer : tout tient dans PHP 8.1+, PDO SQLite et GD. La base est un
 * fichier dans `donnees/`, un dossier que le `.htaccess` interdit au web.
 */

declare(strict_types=1);

const VERSION = '1.0.0';
define('RACINE', dirname(__DIR__));

/**
 * `config.php`, à la racine et facultatif, peut fixer l'adresse publique et
 * le dossier des données. Sans lui, l'adresse se déduit de la requête —
 * ce qui suffit tant que le site n'est servi que sous un seul nom.
 */
$GLOBALS['CONFIG'] = is_file(RACINE . '/config.php') ? (array) require RACINE . '/config.php' : [];

function config(string $cle, mixed $defaut = null): mixed
{
    return $GLOBALS['CONFIG'][$cle] ?? $defaut;
}

function dossier_donnees(): string
{
    $d = (string) config('dossier_donnees', RACINE . '/donnees');
    if (!is_dir($d)) {
        @mkdir($d, 0775, true);
    }
    return $d;
}

date_default_timezone_set((string) config('fuseau', 'Africa/Lome'));
mb_internal_encoding('UTF-8');

/* ------------------------------------------------------------------ */
/* Base de données                                                     */
/* ------------------------------------------------------------------ */

function db(): PDO
{
    static $pdo = null;
    if ($pdo) {
        return $pdo;
    }
    $pdo = new PDO('sqlite:' . dossier_donnees() . '/lyd.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    // WAL : les lectures du site ne bloquent plus pendant qu'on écrit.
    $pdo->exec('PRAGMA journal_mode = WAL');
    assurer_schema($pdo);
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function un(string $sql, array $params = []): ?array
{
    $r = q($sql, $params)->fetch();
    return $r === false ? null : $r;
}

function tous(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function valeur(string $sql, array $params = []): mixed
{
    $v = q($sql, $params)->fetchColumn();
    return $v === false ? null : $v;
}

function maintenant(?int $t = null): string
{
    return date('Y-m-d H:i:s', $t ?? time());
}

/**
 * Le schéma, créé au premier appel et complété version après version.
 * `user_version` dit où en est la base : on ne rejoue que ce qui manque.
 */
function assurer_schema(PDO $pdo): void
{
    $v = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
    if ($v >= 2) {
        return;
    }
    if ($v === 1) {
        // v2 : un article importé puis retouché ici le dit explicitement.
        $pdo->exec('ALTER TABLE articles ADD COLUMN retouche INTEGER NOT NULL DEFAULT 0');
        $pdo->exec('PRAGMA user_version = 2');
        return;
    }
    $pdo->exec(<<<SQL
    CREATE TABLE IF NOT EXISTS utilisateurs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nom TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE COLLATE NOCASE,
        mdp TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'editeur',
        actif INTEGER NOT NULL DEFAULT 1,
        cree_le TEXT NOT NULL,
        vu_le TEXT
    );
    CREATE TABLE IF NOT EXISTS articles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT NOT NULL UNIQUE,
        titre TEXT NOT NULL,
        extrait TEXT NOT NULL DEFAULT '',
        contenu TEXT NOT NULL DEFAULT '',
        image TEXT NOT NULL DEFAULT '',
        categorie TEXT NOT NULL DEFAULT '',
        tags TEXT NOT NULL DEFAULT '[]',
        auteur TEXT NOT NULL DEFAULT '',
        statut TEXT NOT NULL DEFAULT 'brouillon',
        publie_le TEXT,
        cree_le TEXT NOT NULL,
        modifie_le TEXT NOT NULL,
        wp_id INTEGER UNIQUE,
        wp_lien TEXT,
        wp_chemin TEXT,
        importe_le TEXT,
        retouche INTEGER NOT NULL DEFAULT 0,
        lectures INTEGER NOT NULL DEFAULT 0
    );
    CREATE INDEX IF NOT EXISTS articles_publies ON articles(statut, publie_le);
    CREATE INDEX IF NOT EXISTS articles_wp_chemin ON articles(wp_chemin);
    CREATE TABLE IF NOT EXISTS campagnes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        titre TEXT NOT NULL,
        client TEXT NOT NULL DEFAULT '',
        categorie TEXT NOT NULL DEFAULT 'digital',
        annee TEXT NOT NULL DEFAULT '',
        extrait TEXT NOT NULL DEFAULT '',
        description TEXT NOT NULL DEFAULT '',
        image TEXT NOT NULL DEFAULT '',
        vedette INTEGER NOT NULL DEFAULT 0,
        resultats TEXT NOT NULL DEFAULT '[]',
        tags TEXT NOT NULL DEFAULT '[]',
        publie INTEGER NOT NULL DEFAULT 1,
        ordre INTEGER NOT NULL DEFAULT 0,
        cree_le TEXT NOT NULL,
        modifie_le TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS medias (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        chemin TEXT NOT NULL UNIQUE,
        nom TEXT NOT NULL,
        type TEXT NOT NULL,
        taille INTEGER NOT NULL DEFAULT 0,
        largeur INTEGER NOT NULL DEFAULT 0,
        hauteur INTEGER NOT NULL DEFAULT 0,
        source_url TEXT UNIQUE,
        cree_le TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        nom TEXT NOT NULL,
        entreprise TEXT NOT NULL DEFAULT '',
        email TEXT NOT NULL,
        whatsapp TEXT NOT NULL DEFAULT '',
        besoin TEXT NOT NULL DEFAULT '',
        budget TEXT NOT NULL DEFAULT '',
        message TEXT NOT NULL DEFAULT '',
        ip TEXT NOT NULL DEFAULT '',
        lu INTEGER NOT NULL DEFAULT 0,
        cree_le TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS reglages (
        cle TEXT PRIMARY KEY,
        valeur TEXT NOT NULL
    );
    CREATE TABLE IF NOT EXISTS tentatives (
        cle TEXT NOT NULL,
        quand INTEGER NOT NULL
    );
    CREATE INDEX IF NOT EXISTS tentatives_cle ON tentatives(cle, quand);
    SQL);
    $pdo->exec('PRAGMA user_version = 2');
}

/* ------------------------------------------------------------------ */
/* Réglages                                                            */
/* ------------------------------------------------------------------ */

/** Les valeurs d'usine, reprises du site livré. */
function reglages_defaut(): array
{
    return [
        'nom_site' => 'Live Y Dream',
        'slogan' => "L'agence qui fait rêver vos clients",
        'telephone' => '+228 70 08 77 77',
        'telephone2' => '+228 90 37 45 60',
        'email' => 'contact@liveydream.com',
        'email2' => 'customercare@liveydream.com',
        'adresse' => '177 Rue HDN, Hédzranawoé',
        'adresse2' => 'Lomé, Togo (non loin de STEA)',
        'whatsapp' => '22870087777',
        'horaires_semaine' => '8h30 — 18h00',
        'horaires_samedi' => '9h00 — 14h00',
        'horaires_dimanche' => 'Fermé',
        'facebook' => 'https://www.facebook.com/228LiveYDream',
        'instagram' => 'https://instagram.com/liveydream',
        'linkedin' => 'https://linkedin.com/company/liveydream',
        'tiktok' => 'https://tiktok.com/@liveydream',
        'hero_titre' => 'VOS CLIENTS NE PEUVENT PAS VOUS IGNORER',
        'hero_sous_titre' => 'En Afrique, la visibilité est le vrai pouvoir. Nous transformons votre marque en une force incontournable.',
        'stat1' => '10+', 'stat1_libelle' => "Ans d'expertise",
        'stat2' => '200+', 'stat2_libelle' => 'Marques accompagnées',
        'stat3' => '4', 'stat3_libelle' => 'Pays présents',
        'stat4' => '15+', 'stat4_libelle' => "Experts dans l'équipe",
        'site_url' => '',
        'meta_description' => "Depuis 2013, Live Y Dream est l'agence de communication de référence à Lomé, Togo. Marketing digital, création graphique, production vidéo, événementiel, community management.",
        'verif_google' => '',
        'contact_destinataire' => 'contact@liveydream.com',
        'wp_racine' => 'https://liveydream.com/wp-json/wp/v2',
        'mentions_legales' => '',
        'confidentialite' => '',
    ];
}

function reglages(): array
{
    static $cache = null;
    if ($cache === null || isset($GLOBALS['_reglages_sales'])) {
        unset($GLOBALS['_reglages_sales']);
        $cache = reglages_defaut();
        foreach (tous('SELECT cle, valeur FROM reglages') as $r) {
            $cache[$r['cle']] = $r['valeur'];
        }
    }
    return $cache;
}

function reglage(string $cle): string
{
    return (string) (reglages()[$cle] ?? '');
}

function reglages_enregistrer(array $valeurs): void
{
    $st = db()->prepare('INSERT INTO reglages (cle, valeur) VALUES (?, ?)
        ON CONFLICT(cle) DO UPDATE SET valeur = excluded.valeur');
    foreach ($valeurs as $k => $v) {
        $st->execute([$k, (string) $v]);
    }
    $GLOBALS['_reglages_sales'] = true;
}

/* ------------------------------------------------------------------ */
/* Adresses                                                            */
/* ------------------------------------------------------------------ */

/** Le chemin sous lequel le site est servi : '' à la racine, '/lyd' dans un sous-dossier. */
function base_chemin(): string
{
    static $b = null;
    if ($b === null) {
        $b = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'))), '/');
    }
    return $b;
}

/** L'adresse absolue du site, sans barre finale. */
function base_url(): string
{
    $fixe = rtrim((string) (config('site_url') ?: reglage('site_url')), '/');
    if ($fixe !== '') {
        return $fixe;
    }
    $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $hote = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    return ($https ? 'https' : 'http') . '://' . $hote . base_chemin();
}

function url(string $chemin = ''): string
{
    return base_chemin() . '/' . ltrim($chemin, '/');
}

function url_absolue(string $chemin = ''): string
{
    return base_url() . '/' . ltrim($chemin, '/');
}

/** Un fichier statique, avec sa date en paramètre pour casser le cache à chaque mise à jour. */
function actif(string $chemin): string
{
    $f = RACINE . '/' . ltrim($chemin, '/');
    return url($chemin) . (is_file($f) ? '?v=' . filemtime($f) : '');
}

/** Une image enregistrée : chemin local (`uploads/…`, `assets/…`) ou adresse complète. */
function image_url(string $v): string
{
    $v = trim($v);
    if ($v === '' || preg_match('#^https?://#i', $v)) {
        return $v;
    }
    return url($v);
}

function rediriger(string $chemin, int $code = 303): never
{
    $cible = preg_match('#^https?://#', $chemin) ? $chemin : url($chemin);
    header('Location: ' . $cible, true, $code);
    exit;
}

/* ------------------------------------------------------------------ */
/* Texte                                                               */
/* ------------------------------------------------------------------ */

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function slugifier(string $texte): string
{
    $s = mb_strtolower(trim($texte));
    if (function_exists('transliterator_transliterate')) {
        $s = (string) transliterator_transliterate('Any-Latin; Latin-ASCII', $s);
    } else {
        $s = strtr($s, [
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ã' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e',
            'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u', 'ÿ' => 'y', 'ñ' => 'n', 'œ' => 'oe', 'æ' => 'ae',
        ]);
    }
    $s = (string) preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-') ?: 'article';
}

const MOIS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août',
    'septembre', 'octobre', 'novembre', 'décembre'];

function date_fr(?string $d): string
{
    $t = $d ? strtotime($d) : false;
    if ($t === false) {
        return '';
    }
    return (int) date('j', $t) . ' ' . MOIS[(int) date('n', $t) - 1] . ' ' . date('Y', $t);
}

/** « il y a 3 min », pour les listes de l'administration. */
function il_y_a(?string $d): string
{
    $t = $d ? strtotime($d) : false;
    if ($t === false) {
        return '—';
    }
    $s = max(0, time() - $t);
    return match (true) {
        $s < 60 => "à l'instant",
        $s < 3600 => 'il y a ' . intdiv($s, 60) . ' min',
        $s < 86400 => 'il y a ' . intdiv($s, 3600) . ' h',
        $s < 86400 * 7 => 'il y a ' . intdiv($s, 86400) . ' j',
        default => date_fr(date('Y-m-d', $t)),
    };
}

function tronquer(string $s, int $n): string
{
    $s = trim((string) preg_replace('/\s+/u', ' ', $s));
    return mb_strlen($s) > $n ? rtrim(mb_substr($s, 0, $n - 1)) . '…' : $s;
}

function texte_nu(string $html): string
{
    $t = html_entity_decode(strip_tags((string) preg_replace('#<(script|style)\b.*?</\1>#is', '', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim((string) preg_replace('/\s+/u', ' ', $t));
}

function temps_lecture(string $html): string
{
    $mots = count(preg_split('/\s+/u', texte_nu($html), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    return max(1, (int) round($mots / 200)) . ' min';
}

function initiales(string $nom): string
{
    $mots = preg_split('/\s+/u', trim($nom), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
    $i = mb_substr($mots[0], 0, 1) . (isset($mots[1]) ? mb_substr($mots[1], 0, 1) : '');
    return mb_strtoupper($i);
}

function json_liste(?string $json): array
{
    $v = json_decode((string) $json, true);
    return is_array($v) ? array_values(array_filter($v, 'is_scalar')) : [];
}

/** « a, b, c » saisi dans un champ → liste propre, sans doublon. */
function liste_depuis_saisie(string $s): array
{
    $l = array_map('trim', explode(',', $s));
    return array_values(array_unique(array_filter($l, fn($x) => $x !== '')));
}

/* ------------------------------------------------------------------ */
/* Session, jeton anti-falsification, messages flash                   */
/* ------------------------------------------------------------------ */

function demarrer_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    session_name('lyd_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => base_chemin() . '/',
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $dossier = dossier_donnees() . '/sessions';
    if (!is_dir($dossier)) {
        @mkdir($dossier, 0700, true);
    }
    if (is_writable($dossier)) {
        session_save_path($dossier);
    }
    session_start();
}

function csrf_jeton(): string
{
    demarrer_session();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_champ(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_jeton()) . '">';
}

function csrf_verifier(): void
{
    $recu = (string) ($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '');
    if (!hash_equals(csrf_jeton(), $recu)) {
        http_response_code(419);
        exit('La page a expiré. Revenez en arrière, rechargez-la et recommencez.');
    }
}

function flash(string $type, string $texte): void
{
    demarrer_session();
    $_SESSION['flash'][] = [$type, $texte];
}

function flashes(): array
{
    demarrer_session();
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/** Garder la saisie d'un formulaire refusé, pour ne pas la faire retaper. */
function memoriser_saisie(array $v): void
{
    demarrer_session();
    $_SESSION['saisie'] = $v;
}

function saisie_memorisee(): array
{
    demarrer_session();
    $v = $_SESSION['saisie'] ?? [];
    unset($_SESSION['saisie']);
    return $v;
}

/* ------------------------------------------------------------------ */
/* Freins : connexion et formulaire de contact                         */
/* ------------------------------------------------------------------ */

function ip_client(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function tentatives_recentes(string $cle, int $fenetre): int
{
    q('DELETE FROM tentatives WHERE quand < ?', [time() - 86400]);
    return (int) valeur('SELECT COUNT(*) FROM tentatives WHERE cle = ? AND quand > ?', [$cle, time() - $fenetre]);
}

function tentative_noter(string $cle): void
{
    q('INSERT INTO tentatives (cle, quand) VALUES (?, ?)', [$cle, time()]);
}

function tentatives_oublier(string $cle): void
{
    q('DELETE FROM tentatives WHERE cle = ?', [$cle]);
}

/* ------------------------------------------------------------------ */
/* Comptes                                                             */
/* ------------------------------------------------------------------ */

const ROLES = ['admin' => 'Administrateur', 'editeur' => 'Éditeur'];

function utilisateur_courant(): ?array
{
    static $u = false;
    if ($u !== false) {
        return $u;
    }
    demarrer_session();
    $id = (int) ($_SESSION['uid'] ?? 0);
    $u = $id ? un('SELECT * FROM utilisateurs WHERE id = ? AND actif = 1', [$id]) : null;
    if (!$u && $id) {
        unset($_SESSION['uid']);
    }
    return $u;
}

function connecter(array $u): void
{
    demarrer_session();
    session_regenerate_id(true);
    $_SESSION['uid'] = (int) $u['id'];
    q('UPDATE utilisateurs SET vu_le = ? WHERE id = ?', [maintenant(), $u['id']]);
}

function deconnecter(): void
{
    demarrer_session();
    $_SESSION = [];
    session_regenerate_id(true);
}

function est_admin(?array $u): bool
{
    return $u !== null && $u['role'] === 'admin';
}

function exiger_connexion(bool $admin = false): array
{
    $u = utilisateur_courant();
    if (!$u) {
        rediriger('admin/connexion?suite=' . rawurlencode(chemin_demande()));
    }
    if ($admin && !est_admin($u)) {
        http_response_code(403);
        flash('err', 'Cet écran est réservé aux administrateurs.');
        rediriger('admin');
    }
    return $u;
}

/** Le chemin demandé, relatif à la racine du site, sans la chaîne de requête. */
function chemin_demande(): string
{
    $p = rawurldecode((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH));
    $b = base_chemin();
    if ($b !== '' && str_starts_with($p, $b)) {
        $p = substr($p, strlen($b));
    }
    $p = trim($p, '/');
    return $p === 'index.php' ? '' : $p;
}
