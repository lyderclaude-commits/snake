<?php
/**
 * Les images du site : téléversées depuis l'administration, ou rapatriées
 * depuis une adresse extérieure (WordPress, Unsplash).
 *
 * Toutes finissent au même endroit, `uploads/AAAA/MM/`, et dans la même
 * table. Une image extérieure gardée telle quelle meurt le jour où le site
 * d'origine ferme — et le WordPress actuel fermera, puisque ce site-ci le
 * remplace. D'où le rapatriement systématique.
 */

declare(strict_types=1);

const MEDIA_TYPES = [
    IMAGETYPE_JPEG => ['jpg', 'image/jpeg'],
    IMAGETYPE_PNG => ['png', 'image/png'],
    IMAGETYPE_GIF => ['gif', 'image/gif'],
    IMAGETYPE_WEBP => ['webp', 'image/webp'],
];
const MEDIA_POIDS_MAX = 15 * 1024 * 1024;
/** Au-delà, l'image est réduite : une photo d'appareil de 6000 px ne sert à rien sur un écran. */
const MEDIA_LARGEUR_MAX = 2000;

function dossier_uploads(): string
{
    $d = RACINE . '/uploads/' . date('Y') . '/' . date('m');
    if (!is_dir($d)) {
        @mkdir($d, 0775, true);
    }
    return $d;
}

/**
 * Enregistre un fichier image déjà sur le disque (téléversé ou téléchargé).
 *
 * Le fichier est VÉRIFIÉ par son contenu, jamais par son extension : un
 * `photo.jpg` qui contient du PHP n'a pas de dimensions d'image et
 * s'arrête ici.
 *
 * @return array{ok: bool, erreur?: string, media?: array}
 */
function media_enregistrer_fichier(string $tmp, string $nom_origine, ?string $source_url = null): array
{
    $info = @getimagesize($tmp);
    if (!$info || !isset(MEDIA_TYPES[$info[2]])) {
        return ['ok' => false, 'erreur' => 'Ce fichier n’est pas une image JPEG, PNG, GIF ou WebP.'];
    }
    [$ext, $mime] = MEDIA_TYPES[$info[2]];
    [$largeur, $hauteur] = [(int) $info[0], (int) $info[1]];

    $base = slugifier(pathinfo($nom_origine, PATHINFO_FILENAME));
    $base = substr($base, 0, 60) ?: 'image';
    $dossier = dossier_uploads();
    $nom = $base . '.' . $ext;
    for ($i = 2; is_file($dossier . '/' . $nom); $i++) {
        $nom = $base . '-' . $i . '.' . $ext;
    }
    $cible = $dossier . '/' . $nom;

    $reduite = false;
    if ($largeur > MEDIA_LARGEUR_MAX && $info[2] !== IMAGETYPE_GIF && function_exists('imagecreatefromstring')) {
        $img = @imagecreatefromstring((string) file_get_contents($tmp));
        if ($img) {
            $petite = imagescale($img, MEDIA_LARGEUR_MAX);
            if ($petite) {
                if ($info[2] === IMAGETYPE_PNG || $info[2] === IMAGETYPE_WEBP) {
                    imagealphablending($petite, false);
                    imagesavealpha($petite, true);
                }
                $reduite = match ($info[2]) {
                    IMAGETYPE_JPEG => imagejpeg($petite, $cible, 84),
                    IMAGETYPE_PNG => imagepng($petite, $cible, 7),
                    IMAGETYPE_WEBP => function_exists('imagewebp') && imagewebp($petite, $cible, 84),
                    default => false,
                };
                if ($reduite) {
                    $largeur = imagesx($petite);
                    $hauteur = imagesy($petite);
                }
            }
        }
    }
    if (!$reduite) {
        $ok = is_uploaded_file($tmp) ? move_uploaded_file($tmp, $cible) : copy($tmp, $cible);
        if (!$ok) {
            return ['ok' => false, 'erreur' => 'Impossible d’écrire dans le dossier uploads/ : vérifiez ses droits.'];
        }
    }
    @chmod($cible, 0644);

    $chemin = 'uploads/' . date('Y') . '/' . date('m') . '/' . $nom;
    q('INSERT INTO medias (chemin, nom, type, taille, largeur, hauteur, source_url, cree_le) VALUES (?,?,?,?,?,?,?,?)',
        [$chemin, $nom, $mime, (int) filesize($cible), $largeur, $hauteur, $source_url, maintenant()]);
    return ['ok' => true, 'media' => un('SELECT * FROM medias WHERE id = ?', [(int) db()->lastInsertId()])];
}

/** Un fichier reçu par formulaire (`$_FILES['…']`). */
function media_televerser(array $f): array
{
    $err = (int) ($f['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        return ['ok' => false, 'erreur' => 'Image trop lourde pour l’hébergement (limite : ' . ini_get('upload_max_filesize') . ').'];
    }
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file((string) $f['tmp_name'])) {
        return ['ok' => false, 'erreur' => 'Aucune image reçue.'];
    }
    if ((int) $f['size'] > MEDIA_POIDS_MAX) {
        return ['ok' => false, 'erreur' => 'Image trop lourde (15 Mo au plus).'];
    }
    return media_enregistrer_fichier((string) $f['tmp_name'], (string) $f['name']);
}

/**
 * Une requête HTTP GET, avec repli quand cURL manque (cas fréquent sur un mutualisé).
 *
 * @return array{ok: bool, code: int, corps: string, entetes: array<string, string>, erreur: string}
 */
function http_get(string $url, int $delai = 20, int $max = MEDIA_POIDS_MAX): array
{
    $entetes = [];
    $corps = '';
    $code = 0;
    $erreur = '';
    $agent = 'Socle/' . SOCLE_VERSION . ' (+import)';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 4,
            CURLOPT_TIMEOUT => $delai,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_USERAGENT => $agent,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $l) use (&$entetes): int {
                if (str_contains($l, ':')) {
                    [$k, $v] = explode(':', $l, 2);
                    $entetes[strtolower(trim($k))] = trim($v);
                }
                return strlen($l);
            },
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => static fn($c, $dlt, $dl) => $dl > $max ? 1 : 0,
        ]);
        $r = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($r === false) {
            $erreur = curl_error($ch);
        } else {
            $corps = (string) $r;
        }
        curl_close($ch);
    } else {
        $ctx = stream_context_create([
            'http' => ['method' => 'GET', 'timeout' => $delai, 'ignore_errors' => true, 'follow_location' => 1,
                'header' => 'User-Agent: ' . $agent],
        ]);
        $r = @file_get_contents($url, false, $ctx, 0, $max + 1);
        foreach ($http_response_header ?? [] as $l) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $l, $m)) {
                $code = (int) $m[1];
                $entetes = [];
            } elseif (str_contains($l, ':')) {
                [$k, $v] = explode(':', $l, 2);
                $entetes[strtolower(trim($k))] = trim($v);
            }
        }
        if ($r === false) {
            $erreur = 'connexion impossible';
        } else {
            $corps = $r;
        }
    }
    return ['ok' => $code >= 200 && $code < 300 && $erreur === '', 'code' => $code, 'corps' => $corps,
        'entetes' => $entetes, 'erreur' => $erreur];
}

/**
 * Rapatrie une image distante, une seule fois.
 *
 * La même adresse rencontrée deux fois (une couverture reprise dans le
 * texte, un import relancé) renvoie la copie déjà faite : `source_url` est
 * unique. Rend le chemin local, ou null si l'image n'a pas pu être lue —
 * l'appelant garde alors l'adresse d'origine plutôt qu'un trou.
 */
function media_depuis_url(string $url, ?string &$erreur = null): ?string
{
    $url = trim(html_entity_decode($url, ENT_QUOTES, 'UTF-8'));
    if (str_starts_with($url, '//')) {
        $url = 'https:' . $url;
    }
    if (!preg_match('#^https?://#i', $url)) {
        $erreur = 'adresse non http';
        return null;
    }
    $deja = valeur('SELECT chemin FROM medias WHERE source_url = ?', [$url]);
    if ($deja && is_file(RACINE . '/' . $deja)) {
        return (string) $deja;
    }
    if ($deja) {
        q('DELETE FROM medias WHERE source_url = ?', [$url]);
    }
    $r = http_get($url);
    if (!$r['ok']) {
        $erreur = $r['erreur'] ?: 'HTTP ' . $r['code'];
        return null;
    }
    if (strlen($r['corps']) > MEDIA_POIDS_MAX) {
        $erreur = 'trop lourde';
        return null;
    }
    $tmp = tempnam(sys_get_temp_dir(), 'socle');
    file_put_contents($tmp, $r['corps']);
    $nom = basename((string) parse_url($url, PHP_URL_PATH)) ?: 'image';
    // Unsplash et consorts servent `photo-123?w=800` : sans extension, on nomme d'après l'hôte.
    if (!str_contains($nom, '.')) {
        $nom = preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST)) . '-' . $nom;
    }
    $res = media_enregistrer_fichier($tmp, $nom, $url);
    @unlink($tmp);
    if (!$res['ok']) {
        $erreur = $res['erreur'];
        return null;
    }
    return (string) $res['media']['chemin'];
}

/** Un média est-il encore utilisé quelque part ? (pour avertir avant suppression) */
function media_utilisations(string $chemin): int
{
    $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $chemin) . '%';
    return (int) valeur("SELECT (SELECT COUNT(*) FROM articles WHERE image = ? OR contenu LIKE ? ESCAPE '\\')
        + (SELECT COUNT(*) FROM elements WHERE donnees LIKE ? ESCAPE '\\')", [$chemin, $like, $like]);
}

function media_supprimer(int $id): bool
{
    $m = un('SELECT * FROM medias WHERE id = ?', [$id]);
    if (!$m) {
        return false;
    }
    $f = RACINE . '/' . $m['chemin'];
    if (str_starts_with($m['chemin'], 'uploads/') && is_file($f)) {
        @unlink($f);
    }
    q('DELETE FROM medias WHERE id = ?', [$id]);
    return true;
}

function taille_lisible(int $o): string
{
    return $o >= 1048576 ? number_format($o / 1048576, 1, ',', ' ') . ' Mo' : max(1, (int) round($o / 1024)) . ' Ko';
}
