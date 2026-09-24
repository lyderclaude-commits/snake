<?php
/**
 * Ce qu'on a commencé sans compte, et qui survit à la connexion.
 *
 * Le produit demandait un compte AVANT de laisser faire quoi que ce soit :
 * `exiger_droit()` renvoyait sur la connexion, et la connexion renvoyait
 * sur le tableau de bord. Entre les deux, ce que la personne venait
 * d'écrire n'existait plus, et rien ne le lui disait. Elle recommençait, ou
 * elle partait.
 *
 * Le mur est maintenant à la fin : on compose son lien court ou son décor,
 * et c'est au moment de le créer qu'on se présente. Pour que cela tienne,
 * il faut retrouver le travail de quelqu'un qui n'existe pas encore. D'où
 * ce jeton, qui vit dans un cookie et n'est rattaché à personne.
 *
 * Un tel brouillon est donc, par construction, une écriture ANONYME dans
 * la base. Trois choses l'encadrent, et aucune n'est facultative :
 * une péremption courte, un plafond par adresse, et un seul brouillon par
 * genre et par jeton.
 */

declare(strict_types=1);

/** Combien de temps un brouillon attend son compte. */
const BROUILLON_HEURES = 48;

/** Combien de brouillons une même adresse peut laisser en attente. */
const BROUILLON_PAR_IP = 20;

/** Le cookie qui porte le jeton. */
const BROUILLON_COOKIE = 'wkb_brouillon';

/**
 * Le jeton de ce visiteur, créé au besoin.
 *
 * `$creer = false` pour LIRE : une page publique qui se contente de
 * regarder s'il y a un brouillon ne doit pas poser un cookie à tous ceux
 * qui passent. On n'en pose un qu'au moment où quelqu'un écrit vraiment
 * quelque chose.
 */
function brouillon_jeton(bool $creer = false): string
{
    $j = (string) ($_COOKIE[BROUILLON_COOKIE] ?? '');
    // Un jeton doit ressembler à ce qu'on écrit : 48 caractères hexadécimaux.
    // Tout le reste vient d'ailleurs et ne désigne rien chez nous.
    if (preg_match('/^[0-9a-f]{48}$/', $j)) {
        return $j;
    }
    if (!$creer) {
        return '';
    }
    $j = bin2hex(random_bytes(24));
    if (!headers_sent()) {
        setcookie(BROUILLON_COOKIE, $j, [
            'expires' => time() + BROUILLON_HEURES * 3600,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']),
            // `Lax` et non `Strict` : on revient sur le site depuis le lien
            // de confirmation reçu par courriel, et `Strict` n'enverrait
            // pas le cookie sur cette première navigation.
            'samesite' => 'Lax',
            'httponly' => true,
        ]);
    }
    $_COOKIE[BROUILLON_COOKIE] = $j;
    return $j;
}

/** L'adresse du visiteur, telle qu'on la compte. */
function brouillon_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

/**
 * Pose un brouillon, en remplaçant celui du même genre.
 *
 * Un seul par genre et par jeton : quelqu'un qui compose trois liens sans
 * jamais créer de compte ne doit pas laisser trois lignes derrière lui.
 * C'est le dernier qui compte, parce que c'est celui qu'il a sous les yeux.
 *
 * Rend `false` quand le plafond par adresse est atteint. L'écran le dit
 * alors plutôt que de faire semblant d'avoir enregistré.
 */
function brouillon_poser(string $genre, array $charge, ?string $fichier = null): bool
{
    $jeton = brouillon_jeton(true);
    $ip = brouillon_ip();

    brouillon_retirer($genre, $jeton);

    if ($ip !== '') {
        $q = db()->prepare('SELECT COUNT(*) FROM brouillons WHERE ip = ? AND expire_le > ?');
        $q->execute([$ip, maintenant()]);
        if ((int) $q->fetchColumn() >= BROUILLON_PAR_IP) {
            return false;
        }
    }

    db()->prepare(
        'INSERT INTO brouillons (id, jeton, genre, charge, fichier, ip, cree_le, expire_le)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        nouvel_id(), $jeton, $genre,
        json_encode($charge, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $fichier, $ip, maintenant(),
        maintenant(time() + BROUILLON_HEURES * 3600),
    ]);
    return true;
}

/** Le brouillon de ce genre, ou null. Il n'est pas consommé. */
function brouillon_prendre(string $genre): ?array
{
    $jeton = brouillon_jeton();
    if ($jeton === '') {
        return null;
    }
    $q = db()->prepare(
        'SELECT * FROM brouillons WHERE jeton = ? AND genre = ? AND expire_le > ?
         ORDER BY cree_le DESC LIMIT 1'
    );
    $q->execute([$jeton, $genre, maintenant()]);
    $l = $q->fetch(PDO::FETCH_ASSOC);
    if (!$l) {
        return null;
    }
    $charge = json_decode((string) $l['charge'], true);
    $l['charge'] = is_array($charge) ? $charge : [];
    return $l;
}

/**
 * Le prend et l'efface, fichier compris.
 *
 * À n'appeler qu'une fois le travail VRAIMENT repris : effacer avant
 * d'avoir créé le lien ou le décor perdrait le brouillon sur la première
 * erreur de saisie, c'est-à-dire exactement le défaut qu'on répare.
 */
function brouillon_consommer(string $genre): ?array
{
    $b = brouillon_prendre($genre);
    if ($b) {
        brouillon_retirer($genre, (string) $b['jeton']);
    }
    return $b;
}

/** Efface les brouillons d'un genre pour un jeton, et leurs fichiers. */
function brouillon_retirer(string $genre, string $jeton): void
{
    if ($jeton === '') {
        return;
    }
    $q = db()->prepare('SELECT fichier FROM brouillons WHERE jeton = ? AND genre = ?');
    $q->execute([$jeton, $genre]);
    foreach ($q->fetchAll(PDO::FETCH_COLUMN) as $f) {
        brouillon_fichier_effacer((string) $f);
    }
    db()->prepare('DELETE FROM brouillons WHERE jeton = ? AND genre = ?')->execute([$jeton, $genre]);
}

/** Le dossier de quarantaine : des fichiers déposés par personne. */
function dossier_brouillons(): string
{
    $d = dossier_donnees() . '/brouillons';
    if (!is_dir($d)) {
        @mkdir($d, 0775, true);
    }
    return $d;
}

/**
 * Range un fichier téléversé sans compte, et rend son nom.
 *
 * Il ne va PAS dans `donnees/cadres/`, où vivent les cadres des décors
 * publiés : un fichier qui n'appartient encore à personne n'a rien à faire
 * au milieu de ceux qui sont servis. Il y entrera quand un compte existera.
 */
function brouillon_fichier_ranger(string $source, string $extension): ?string
{
    $nom = bin2hex(random_bytes(16)) . '.' . ($extension === 'webp' ? 'webp' : 'png');
    return @move_uploaded_file($source, dossier_brouillons() . '/' . $nom) ? $nom : null;
}

/** L'adresse à laquelle un fichier de quarantaine se regarde. */
function brouillon_fichier_url(string $nom): string
{
    return url('?p=brouillon-cadre&f=' . rawurlencode($nom));
}

/** Le nom d'un fichier de quarantaine, retrouvé dans son adresse. */
function brouillon_fichier_nom(string $url): string
{
    if (!preg_match('/[?&]f=([0-9a-f]{32}\.(?:png|webp))/', $url, $m)) {
        return '';
    }
    return $m[1];
}

/**
 * Note un fichier déposé sans compte, sans toucher aux autres.
 *
 * `brouillon_poser()` remplace le brouillon du même genre : c'est ce qu'on
 * veut pour un formulaire, et exactement ce qu'on ne veut pas pour des
 * fichiers, qu'on dépose un par un. Chacun a donc sa ligne, de genre
 * `fichier`. Elle sert à deux choses, et il faut les deux : dire à QUI le
 * fichier appartient quand on le sert, et le faire périmer avec le reste
 * même si le formulaire n'est jamais envoyé.
 */
function brouillon_fichier_noter(string $nom): void
{
    $jeton = brouillon_jeton(true);
    db()->prepare(
        'INSERT INTO brouillons (id, jeton, genre, charge, fichier, ip, cree_le, expire_le)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
        nouvel_id(), $jeton, 'fichier', '{}', $nom, brouillon_ip(), maintenant(),
        maintenant(time() + BROUILLON_HEURES * 3600),
    ]);
}

/**
 * Ce fichier appartient-il à celui qui le demande ?
 *
 * Un nom de quarantaine est tiré au sort sur seize octets : le deviner est
 * hors de portée. Mais « impossible à deviner » n'est pas une autorisation,
 * et une adresse recopiée circule. Le jeton du cookie tranche.
 */
function brouillon_fichier_permis(string $nom): bool
{
    $jeton = brouillon_jeton();
    if ($jeton === '' || !preg_match('/^[0-9a-f]{32}\.(png|webp)$/', $nom)) {
        return false;
    }
    $q = db()->prepare(
        'SELECT COUNT(*) FROM brouillons WHERE jeton = ? AND fichier = ? AND expire_le > ?'
    );
    $q->execute([$jeton, $nom, maintenant()]);
    return (int) $q->fetchColumn() > 0;
}

function brouillon_fichier_effacer(?string $nom): void
{
    if ($nom === null || $nom === '' || !preg_match('/^[0-9a-f]{32}\.(png|webp)$/', $nom)) {
        return;
    }
    @unlink(dossier_brouillons() . '/' . $nom);
}

/**
 * Sort un fichier de quarantaine vers les cadres, et rend son adresse.
 *
 * C'est le moment où le fichier cesse d'être anonyme : quelqu'un vient de
 * créer un compte, et le décor sera le sien.
 */
function brouillon_fichier_adopter(?string $nom): string
{
    // On accepte le nom seul comme l'adresse complète : le brouillon d'un
    // décor porte la seconde, puisque c'est elle que l'aperçu a chargée.
    if ($nom !== null && str_contains($nom, 'f=')) {
        $nom = brouillon_fichier_nom($nom);
    }
    if ($nom === null || $nom === '' || !preg_match('/^[0-9a-f]{32}\.(png|webp)$/', $nom)) {
        return '';
    }
    $de = dossier_brouillons() . '/' . $nom;
    if (!is_file($de)) {
        return '';
    }
    /**
     * Il change de nom en changeant de dossier.
     *
     * `?p=cadre` n'accepte que des noms d'UUID, et c'est ce qui empêche de
     * lui réclamer un fichier de quarantaine par son nom à lui. Garder
     * l'ancien nom aurait rendu le cadre adopté introuvable.
     */
    $neuf = nouvel_id() . '.' . (str_ends_with($nom, '.webp') ? 'webp' : 'png');
    if (!@rename($de, dossier_cadres() . '/' . $neuf)) {
        return '';
    }
    $nom = $neuf;
    /**
     * Recompressé à l'adoption, et non au dépôt.
     *
     * Le même geste que sur le chemin connecté, au même endroit de la
     * vie du fichier : un cadre sorti de Canva fait couramment 3 Mo pour
     * 1080 px, et chaque invité paierait ce transfert. Le faire en
     * quarantaine aurait dépensé ce travail pour des fichiers dont la
     * moitié n'aura jamais de compte.
     */
    $nom = compresser_cadre(dossier_cadres(), $nom)['nom'];
    // La même adresse que pour un cadre téléversé normalement : `donnees/`
    // n'est pas servi directement, c'est la route `?p=cadre` qui le fait.
    return url('?p=cadre&f=' . $nom);
}

/**
 * Adopte les images d'une liste de calques, et rend la liste corrigée.
 *
 * Un calque image déposé sans compte porte une adresse de quarantaine, que
 * seul son déposant peut ouvrir. Laissée telle quelle dans un décor publié,
 * elle donnerait une image visible par son auteur et par personne d'autre :
 * le pire des défauts, parce qu'il ne se voit pas depuis le compte qui l'a
 * créé.
 *
 * La chaîne est retournée telle quelle si elle ne porte rien de la
 * quarantaine : c'est le cas de tous les décors faits en étant connecté.
 */
function brouillon_calques_adopter(string $json): string
{
    if (!str_contains($json, 'brouillon-cadre')) {
        return $json;
    }
    $calques = json_decode($json, true);
    if (!is_array($calques)) {
        return $json;
    }

    /** Toute cha\u00eene qui d\u00e9signe un fichier de quarantaine devient son adresse d\u00e9finitive. */
    $muer = static function (&$valeur) use (&$muer): void {
        if (is_array($valeur)) {
            foreach ($valeur as &$v) {
                $muer($v);
            }
            return;
        }
        if (!is_string($valeur) || !str_contains($valeur, 'brouillon-cadre')) {
            return;
        }
        $neuf = brouillon_fichier_adopter($valeur);
        // Introuvable ou d\u00e9j\u00e0 adopt\u00e9e : on laisse l'adresse en place plut\u00f4t
        // que de vider le calque. Le d\u00e9faut se verra ; un calque disparu, non.
        if ($neuf !== '') {
            $valeur = $neuf;
        }
    };
    $muer($calques);

    return (string) json_encode($calques, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Le ménage, appelé par le cron quotidien.
 *
 * Sans lui, `donnees/brouillons/` devient un dépôt de fichiers que
 * n'importe qui peut remplir : c'est la seule écriture du produit qui ne
 * demande pas de compte, et elle doit donc s'effacer toute seule.
 */
function brouillons_perimer(): array
{
    $q = db()->prepare('SELECT id, fichier FROM brouillons WHERE expire_le <= ?');
    $q->execute([maintenant()]);
    $lignes = $q->fetchAll(PDO::FETCH_ASSOC);
    foreach ($lignes as $l) {
        brouillon_fichier_effacer($l['fichier'] === null ? null : (string) $l['fichier']);
    }
    db()->prepare('DELETE FROM brouillons WHERE expire_le <= ?')->execute([maintenant()]);

    /**
     * Les fichiers orphelins, aussi.
     *
     * Un enregistrement interrompu entre le dépôt du fichier et l'écriture
     * de la ligne laisserait un fichier que plus rien ne désigne. Rare, et
     * précisément pour cela jamais rattrapé à la main.
     */
    $orphelins = 0;
    $limite = time() - BROUILLON_HEURES * 3600;
    foreach (glob(dossier_brouillons() . '/*') ?: [] as $f) {
        if (is_file($f) && filemtime($f) < $limite) {
            @unlink($f);
            $orphelins++;
        }
    }
    return ['brouillons' => count($lignes), 'orphelins' => $orphelins];
}

/**
 * Où renvoyer quelqu'un après le mur, selon ce qu'il était en train de faire.
 *
 * Une seule table, lue par la connexion comme par l'inscription : deux
 * listes auraient divergé, et un des deux chemins aurait fini par oublier
 * le brouillon.
 */
const BROUILLON_SUITES = [
    'lien'  => '?p=liens&reprendre=1',
    'decor' => '?p=nouveau&reprendre=1',
];

/** L'adresse de reprise pour une suite demandée, ou null si elle est inconnue. */
function brouillon_suite(?string $suite): ?string
{
    $s = (string) ($suite ?? '');
    return BROUILLON_SUITES[$s] ?? null;
}
