<?php
/**
 * Le socle : configuration, définition du site, base de données, session,
 * sécurité, aides.
 *
 * Le socle tourne sur un hébergement mutualisé PHP, sans terminal ni
 * Composer : tout tient dans PHP 8.1+, PDO SQLite et GD. La base est un
 * fichier dans `donnees/`, un dossier que le `.htaccess` interdit au web.
 *
 * Tout ce qui est propre à UN site — son nom, sa couleur, ses langues, ses
 * types de contenus, ses réglages — vit dans `app/definition.php`, à la
 * racine du site. Le socle ne connaît rien d'autre.
 */

declare(strict_types=1);

const SOCLE_VERSION = '1.0.0';
/** Le dossier du socle (`…/socle`) et la racine du site qui le contient. */
define('SOCLE', dirname(__DIR__));
if (!defined('RACINE')) {
    // Le dossier du `index.php` qui a chargé le socle — même si `socle/` est un lien
    // symbolique (pendant le développement), auquel cas `__DIR__` pointe ailleurs.
    $_entree = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
    define('RACINE', $_entree !== '' && is_file(dirname($_entree) . '/socle/demarrer.php') ? dirname($_entree) : dirname(SOCLE));
    unset($_entree);
}

/**
 * `config.php`, à la racine et facultatif, porte ce qui dépend du serveur :
 * l'adresse publique, le dossier des données. Il n'est jamais versionné.
 */
$GLOBALS['CONFIG'] = is_file(RACINE . '/config.php') ? (array) require RACINE . '/config.php' : [];

function config(string $cle, mixed $defaut = null): mixed
{
    return $GLOBALS['CONFIG'][$cle] ?? $defaut;
}

/**
 * La définition du site (`app/definition.php`) : ce qui fait de ce socle
 * l'administration de CE site. Lue une fois ; `def('contact.destinataire')`
 * descend dans les tableaux.
 */
$GLOBALS['DEFINITION'] = is_file(RACINE . '/app/definition.php') ? (array) require RACINE . '/app/definition.php' : [];

function def(string $chemin, mixed $defaut = null): mixed
{
    $v = $GLOBALS['DEFINITION'];
    foreach (explode('.', $chemin) as $k) {
        if (!is_array($v) || !array_key_exists($k, $v)) {
            return $defaut;
        }
        $v = $v[$k];
    }
    return $v;
}

/** Un module du socle est-il branché ? (`articles`, `messages`, `wordpress`, `traduction`, `push`) */
function module(string $nom): bool
{
    $actifs = (array) def('modules', []);
    $on = (bool) ($actifs[$nom] ?? true);
    return match ($nom) {
        // L'import WordPress et DeepL travaillent sur les articles ; DeepL traduit vers l'anglais.
        'wordpress' => $on && module('articles'),
        'traduction' => $on && module('articles') && isset(LANGUES['en']),
        'push' => $on && module('messages'),
        default => $on,
    };
}

/** Un texte affiché au public, passé au dictionnaire du site s'il en a un. */
function socle_t(string $s): string
{
    $f = def('traduire');
    return is_callable($f) ? (string) $f($s) : $s;
}

define('VERSION', (string) def('version', SOCLE_VERSION));
define('LANGUES', (array) def('langues', ['fr' => 'Français']));
/** Les pages publiques et leur adresse anglaise (`campagnes` → `campaigns`). */
define('ROUTES_EN', (array) def('routes_en', []));
/** Le destinataire fixe des demandes de contact : il ne se règle pas, il se déclare. */
define('CONTACT_EMAIL', strtolower((string) config('contact_email', def('contact.destinataire', ''))));

function dossier_donnees(): string
{
    $d = (string) config('dossier_donnees', RACINE . '/donnees');
    if (!is_dir($d)) {
        @mkdir($d, 0775, true);
    }
    return $d;
}

date_default_timezone_set((string) config('fuseau', def('fuseau', 'Europe/Paris')));
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
    $pdo = new PDO('sqlite:' . dossier_donnees() . '/' . def('base', 'site.sqlite'), null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    // WAL : les lectures du site ne bloquent plus pendant qu'on écrit.
    $pdo->exec('PRAGMA journal_mode = WAL');
    migrations_jouer($pdo);
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

function table_existe(PDO $pdo, string $table): bool
{
    $st = $pdo->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = ?");
    $st->execute([$table]);
    return (bool) $st->fetchColumn();
}

/** Ajoute une colonne si elle manque : une migration se rejoue sans casser. */
function colonne_ajouter(PDO $pdo, string $table, string $colonne, string $type): void
{
    $cols = array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array($colonne, $cols, true)) {
        $pdo->exec("ALTER TABLE $table ADD COLUMN $colonne $type");
    }
}

/**
 * Les migrations : celles du socle, puis celles du site (`migrations` dans
 * la définition), chacune jouée une seule fois et notée par son nom.
 * Un nom, et non un numéro : le socle et chaque site avancent chacun à
 * leur rythme sans se marcher dessus.
 */
function migrations_jouer(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS migrations (nom TEXT PRIMARY KEY, jouee_le TEXT NOT NULL)');
    $faites = array_flip($pdo->query('SELECT nom FROM migrations')->fetchAll(PDO::FETCH_COLUMN));
    $toutes = ['socle-1' => 'migration_socle_1'] + (array) def('migrations', []);
    foreach ($toutes as $nom => $f) {
        if (isset($faites[$nom])) {
            continue;
        }
        $pdo->beginTransaction();
        try {
            $f($pdo);
            $pdo->prepare('INSERT INTO migrations (nom, jouee_le) VALUES (?, ?)')->execute([$nom, maintenant()]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}

/**
 * Le schéma du socle. Sur une base neuve, il crée tout ; sur une base plus
 * ancienne (un site qui existait avant le socle), il complète les tables
 * colonne par colonne.
 */
function migration_socle_1(PDO $pdo): void
{
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
        lectures INTEGER NOT NULL DEFAULT 0,
        titre_en TEXT NOT NULL DEFAULT '',
        extrait_en TEXT NOT NULL DEFAULT '',
        contenu_en TEXT NOT NULL DEFAULT '',
        traduction_auto INTEGER NOT NULL DEFAULT 0,
        traduit_le TEXT
    );
    CREATE INDEX IF NOT EXISTS articles_publies ON articles(statut, publie_le);
    CREATE INDEX IF NOT EXISTS articles_wp_chemin ON articles(wp_chemin);
    CREATE TABLE IF NOT EXISTS elements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        collection TEXT NOT NULL,
        donnees TEXT NOT NULL DEFAULT '{}',
        en TEXT NOT NULL DEFAULT '{}',
        ordre INTEGER NOT NULL DEFAULT 0,
        visible INTEGER NOT NULL DEFAULT 1,
        cree_le TEXT NOT NULL,
        modifie_le TEXT NOT NULL
    );
    CREATE INDEX IF NOT EXISTS elements_collection ON elements(collection, visible, ordre);
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
        email TEXT NOT NULL,
        message TEXT NOT NULL DEFAULT '',
        donnees TEXT NOT NULL DEFAULT '{}',
        ip TEXT NOT NULL DEFAULT '',
        lu INTEGER NOT NULL DEFAULT 0,
        courriel TEXT NOT NULL DEFAULT '',
        langue TEXT NOT NULL DEFAULT 'fr',
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
    CREATE TABLE IF NOT EXISTS push_abonnements (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        utilisateur_id INTEGER NOT NULL REFERENCES utilisateurs(id) ON DELETE CASCADE,
        endpoint TEXT NOT NULL UNIQUE,
        p256dh TEXT NOT NULL,
        auth TEXT NOT NULL,
        appareil TEXT NOT NULL DEFAULT '',
        cree_le TEXT NOT NULL,
        dernier_envoi TEXT,
        dernier_resultat TEXT NOT NULL DEFAULT ''
    );
    SQL);
    // Une base d'avant le socle : les colonnes ajoutées au fil des versions.
    foreach ([
        ['articles', 'retouche', 'INTEGER NOT NULL DEFAULT 0'],
        ['articles', 'lectures', 'INTEGER NOT NULL DEFAULT 0'],
        ['articles', 'titre_en', "TEXT NOT NULL DEFAULT ''"],
        ['articles', 'extrait_en', "TEXT NOT NULL DEFAULT ''"],
        ['articles', 'contenu_en', "TEXT NOT NULL DEFAULT ''"],
        ['articles', 'traduction_auto', 'INTEGER NOT NULL DEFAULT 0'],
        ['articles', 'traduit_le', 'TEXT'],
        ['messages', 'donnees', "TEXT NOT NULL DEFAULT '{}'"],
        ['messages', 'courriel', "TEXT NOT NULL DEFAULT ''"],
        ['messages', 'langue', "TEXT NOT NULL DEFAULT 'fr'"],
    ] as [$t, $c, $type]) {
        colonne_ajouter($pdo, $t, $c, $type);
    }
}

/* ------------------------------------------------------------------ */
/* Réglages                                                            */
/* ------------------------------------------------------------------ */

/**
 * Les valeurs d'usine : celles du socle, puis celles que la définition du
 * site donne à ses propres réglages (`defaut` de chaque champ).
 */
function reglages_defaut(): array
{
    static $d = null;
    if ($d !== null) {
        return $d;
    }
    $d = [
        'nom_site' => (string) def('nom', 'Mon site'),
        'site_url' => '',
        // Les demandes partent toujours au destinataire déclaré ; ceci ajoute des copies.
        'contact_copies' => '',
        'courriel_expediteur' => '',
        'smtp_hote' => '',
        'smtp_port' => '587',
        'smtp_chiffrement' => 'tls',
        'smtp_utilisateur' => '',
        'smtp_mdp' => '',
        'wp_racine' => (string) def('wordpress', ''),
        // DeepL : la clé (jamais réaffichée), l'anglais visé, et la traduction à la publication.
        'deepl_cle' => '',
        'deepl_cible' => 'EN-GB',
        'deepl_auto' => '0',
    ];
    foreach (reglages_champs() as $k => $c) {
        if (array_key_exists('defaut', $c)) {
            $d[$k] = (string) $c['defaut'];
        }
        if (!empty($c['traduit']) && array_key_exists('defaut_en', $c)) {
            $d[$k . '_en'] = (string) $c['defaut_en'];
        }
    }
    return $d;
}

/**
 * Tous les champs de réglages déclarés par le site, à plat :
 * `cle => [libelle, type, aide, defaut, traduit…]`.
 */
function reglages_champs(): array
{
    $l = [];
    foreach ((array) def('reglages', []) as $onglet) {
        foreach ((array) ($onglet['sections'] ?? []) as $section) {
            foreach ((array) ($section['champs'] ?? []) as $k => $c) {
                $l[$k] = (array) $c;
            }
        }
    }
    return $l;
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

/** Un réglage dans la langue de la page : `cle_en` s'il est rempli, sinon `cle`. */
function reglage_l(string $cle): string
{
    if (langue() !== 'fr') {
        $v = reglage($cle . '_' . langue());
        if (trim($v) !== '') {
            return $v;
        }
    }
    return reglage($cle);
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

/* ------------------------------------------------------------------ */
/* Langue                                                              */
/* ------------------------------------------------------------------ */

/*
 * Le code parle toujours français (`url('campagnes')`) : c'est `url()` qui
 * traduit l'adresse quand la page est servie en anglais (`ROUTES_EN`, tiré
 * de la définition). Une vue écrite une fois sert donc les deux langues.
 */

/** Le nom d'une langue, en français, pour l'administration : « anglais ». */
function langue_nom(string $code): string
{
    return ['fr' => 'français', 'en' => 'anglais', 'es' => 'espagnol', 'pt' => 'portugais', 'de' => 'allemand', 'it' => 'italien', 'ar' => 'arabe'][$code]
        ?? mb_strtolower((string) (LANGUES[$code] ?? $code));
}

function langue(?string $poser = null): string
{
    static $l = 'fr';
    if ($poser !== null && isset(LANGUES[$poser])) {
        $l = $poser;
    }
    return $l;
}

/** Un chemin interne (français) écrit pour une langue donnée : `campagnes#x` → `en/campaigns#x`. */
function chemin_en_langue(string $chemin, string $l): string
{
    $chemin = ltrim($chemin, '/');
    if ($l === 'fr') {
        return $chemin;
    }
    if (!preg_match('~^([^/?#]*)(.*)$~s', $chemin, $m) || !array_key_exists($m[1], ROUTES_EN)) {
        return $chemin; // l'administration, les fichiers, les images : une seule adresse
    }
    $reste = $m[2];
    $tete = ROUTES_EN[$m[1]];
    if ($tete === '') {
        // L'accueil : `en`, puis `?…` ou `#…` collés, jamais `en/`.
        return 'en' . $reste;
    }
    return 'en/' . $tete . $reste;
}

function url_langue(string $chemin, string $l): string
{
    return base_chemin() . '/' . chemin_en_langue($chemin, $l);
}

function url(string $chemin = ''): string
{
    return url_langue($chemin, langue());
}

function url_absolue(string $chemin = '', ?string $l = null): string
{
    return base_url() . '/' . chemin_en_langue($chemin, $l ?? langue());
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

function slugifier(string $texte, string $repli = 'article'): string
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
    return trim($s, '-') ?: $repli;
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
    session_name((string) def('session', 'socle_session'));
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

/** Ne démarre pas de session pour un simple visiteur : seulement s'il en a déjà une (l'équipe connectée). */
function utilisateur_courant_si_session(): ?array
{
    return isset($_COOKIE[(string) def('session', 'socle_session')]) ? utilisateur_courant() : null;
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

/* ------------------------------------------------------------------ */
/* Marque                                                              */
/* ------------------------------------------------------------------ */

/** La couleur principale de l'administration (boutons, liens, onglets). */
function marque_couleur(): string
{
    $c = (string) def('marque.couleur', '#2563EB');
    return preg_match('/^#[0-9a-fA-F]{3,8}$/', $c) ? $c : '#2563EB';
}

/** L'icône des notifications et de l'écran d'accueil du téléphone (PNG carré, 192 px). */
function marque_icone(): string
{
    return (string) def('marque.icone', 'socle/assets/icone-notification.png');
}

/** Le logo de la barre d'administration : le HTML déclaré, sinon le nom du site. */
function marque_logo(): string
{
    $h = (string) def('marque.logo', '');
    return $h !== '' ? $h : '<span class="logo-texte">' . e(reglage('nom_site')) . '</span>';
}
