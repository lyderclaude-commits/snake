<?php
/**
 * Les sauvegardes.
 *
 * Ce qui disparaît si le serveur brûle : les comptes, les campagnes, les
 * badges émis, les présences scannées — et les cadres, qui sont des fichiers
 * et que personne ne pense à sauver avec la base.
 *
 * Une sauvegarde contient donc les DEUX, et sous la forme qui sert vraiment
 * à restaurer :
 *
 *  - **MySQL** → `base.sql`, en dialecte MySQL, à réimporter dans
 *    phpMyAdmin. C'est le seul geste qu'un hébergement mutualisé propose.
 *  - **SQLite** → `wakabi.sqlite`, le fichier lui-même, copié par
 *    `VACUUM INTO` : une copie cohérente même si quelqu'un écrit pendant ce
 *    temps, là où un `copy()` pourrait attraper une base à moitié écrite.
 *  - `cadres/` → les fichiers téléversés, tels quels.
 *  - `LISEZ-MOI.txt` → comment restaurer. Une sauvegarde qu'on ne sait pas
 *    remettre en place n'est qu'un fichier de plus.
 *
 * Une sauvegarde gardée sur le serveur qu'elle sauvegarde ne sauvegarde
 * rien : l'écran le dit, et le bouton de téléchargement est le geste
 * principal.
 */

declare(strict_types=1);

/** Les copies automatiques conservées avant d'effacer la plus ancienne. */
const SAUVEGARDES_GARDEES = 7;

function dossier_sauvegardes(): string
{
    $d = dossier_donnees() . '/sauvegardes';
    if (!is_dir($d)) {
        @mkdir($d, 0775, true);
    }
    return $d;
}

/** Le nom d'une archive : triable, lisible, sans surprise sur un FTP. */
function nom_sauvegarde(): string
{
    return 'wakabi-' . gmdate('Y-m-d-Hi') . '.zip';
}

/**
 * Écrit une sauvegarde complète et rend son chemin.
 *
 * @throws RuntimeException si l'archive ne peut pas être écrite — le seul
 *         cas où il vaut mieux crier que rendre un fichier tronqué.
 */
function ecrire_sauvegarde(string $chemin): string
{
    $zip = new EcrivainZip($chemin);
    $mysql = est_mysql();

    if ($mysql) {
        $zip->ajouter('base.sql', dump_mysql(db()));
    } else {
        $copie = $chemin . '.sqlite';
        // `VACUUM INTO` prend un instantané cohérent : la base peut être
        // écrite pendant ce temps sans que la copie s'en trouve coupée en
        // deux. `copy()` n'offre pas cette garantie.
        try {
            db()->exec("VACUUM INTO " . db()->quote($copie));
        } catch (PDOException) {
            // SQLite d'avant 3.27 : on retombe sur une copie simple, en
            // prévenant dans le LISEZ-MOI plutôt qu'en échouant.
            @copy((string) (config()['fichier'] ?? dossier_donnees() . '/wakabi.sqlite'), $copie);
        }
        $zip->ajouterFichier('wakabi.sqlite', $copie);
        @unlink($copie);
    }

    $cadres = 0;
    foreach (glob(dossier_cadres() . '/*') ?: [] as $f) {
        if (is_file($f) && $zip->ajouterFichier('cadres/' . basename($f), $f)) {
            $cadres++;
        }
    }

    /**
     * Les médias aussi : ce sont des ORIGINAUX, pas un cache.
     *
     * Une couverture d'article n'existe qu'ici — le fichier téléversé a
     * été effacé du poste de la personne qui l'a envoyé depuis longtemps.
     * Les vignettes, elles, restent dehors : elles se refabriquent seules
     * à la première visite, et les inclure doublerait l'archive pour rien.
     */
    $medias = 0;
    foreach (glob(dossier_medias() . '/*') ?: [] as $f) {
        if (is_file($f) && $zip->ajouterFichier('medias/' . basename($f), $f)) {
            $medias++;
        }
    }

    $zip->ajouter('LISEZ-MOI.txt', notice_sauvegarde($mysql, $cadres, $medias));
    $zip->fermer();

    if (!is_file($chemin) || filesize($chemin) < 100) {
        throw new RuntimeException('L’archive écrite est vide : le dossier donnees/ est-il accessible en écriture ?');
    }
    return $chemin;
}

/** Les sauvegardes présentes sur le serveur, la plus récente d'abord. */
function sauvegardes_presentes(): array
{
    $liste = [];
    foreach (glob(dossier_sauvegardes() . '/wakabi-*.zip') ?: [] as $f) {
        $liste[] = ['nom' => basename($f), 'taille' => (int) filesize($f), 'date' => (int) filemtime($f)];
    }
    usort($liste, fn($a, $b) => $b['date'] <=> $a['date']);
    return $liste;
}

/**
 * N'en garde que les plus récentes.
 *
 * Sans rotation, une sauvegarde quotidienne remplit le quota de l'hébergeur
 * en quelques mois — et un disque plein empêche d'écrire la sauvegarde
 * suivante, c'est-à-dire exactement au moment où l'on en aurait besoin.
 */
function tourner_sauvegardes(int $garder = SAUVEGARDES_GARDEES): int
{
    $liste = sauvegardes_presentes();
    $supprimees = 0;
    foreach (array_slice($liste, $garder) as $vieille) {
        if (@unlink(dossier_sauvegardes() . '/' . $vieille['nom'])) {
            $supprimees++;
        }
    }
    return $supprimees;
}

/**
 * La clé que le cron présente. Créée à la première demande.
 *
 * Une adresse qui déclenche une sauvegarde doit être protégée : sans clé,
 * n'importe qui pourrait la marteler et remplir le disque. Elle vit dans les
 * réglages, à côté du reste.
 */
function cle_sauvegarde(): string
{
    $cle = reglages_bdd(['cle_sauvegarde'])['cle_sauvegarde'] ?? '';
    if ($cle === '') {
        $cle = bin2hex(random_bytes(16));
        reglages_bdd_poser(['cle_sauvegarde' => $cle]);
    }
    return $cle;
}

/* ------------------------------------------------------------------ */
/* Le dump MySQL                                                       */
/* ------------------------------------------------------------------ */

/**
 * Un dump que phpMyAdmin réimporte tel quel.
 *
 * Écrit en PHP et non avec `mysqldump` : la fonction `exec()` est désactivée
 * sur la plupart des mutualisés, et une sauvegarde qui dépend d'un binaire
 * absent ne se découvre qu'au moment de restaurer.
 *
 * `SET FOREIGN_KEY_CHECKS` et la transaction encadrent l'import : sans eux,
 * l'ordre des tables déciderait de la réussite, et phpMyAdmin les insère
 * dans l'ordre alphabétique.
 */
function dump_mysql(PDO $pdo): string
{
    $out = "-- Sauvegarde Wakabi Boost — " . gmdate('Y-m-d H:i') . " UTC\n"
         . "-- À réimporter dans phpMyAdmin, dans une base VIDE.\n\n"
         . "SET NAMES utf8mb4;\n"
         . "SET FOREIGN_KEY_CHECKS = 0;\n"
         . "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n\n";

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        $nom = '`' . str_replace('`', '``', (string) $table) . '`';
        $creation = $pdo->query("SHOW CREATE TABLE $nom")->fetch(PDO::FETCH_NUM)[1] ?? '';

        $out .= "\n-- --------------------------------------------------\n";
        $out .= "DROP TABLE IF EXISTS $nom;\n" . $creation . ";\n\n";

        // Par paquets : une table de cent mille badges chargée d'un coup
        // dépasserait la mémoire, et une requête `INSERT` d'un mégaoctet
        // dépasserait `max_allowed_packet`.
        $s = $pdo->query("SELECT * FROM $nom");
        $lot = [];
        $colonnes = null;
        while ($ligne = $s->fetch(PDO::FETCH_ASSOC)) {
            $colonnes ??= array_map(fn($c) => '`' . str_replace('`', '``', $c) . '`', array_keys($ligne));
            $lot[] = '(' . implode(',', array_map(fn($v) => valeur_sql($pdo, $v), $ligne)) . ')';
            if (count($lot) >= 200) {
                $out .= "INSERT INTO $nom (" . implode(',', $colonnes) . ") VALUES\n" . implode(",\n", $lot) . ";\n";
                $lot = [];
            }
        }
        if ($lot) {
            $out .= "INSERT INTO $nom (" . implode(',', $colonnes ?? []) . ") VALUES\n" . implode(",\n", $lot) . ";\n";
        }
    }

    return $out . "\nSET FOREIGN_KEY_CHECKS = 1;\n";
}

/** Une valeur, échappée par le pilote plutôt qu'à la main. */
function valeur_sql(PDO $pdo, mixed $v): string
{
    if ($v === null) {
        return 'NULL';
    }
    if (is_int($v) || is_float($v)) {
        return (string) $v;
    }
    if (is_bool($v)) {
        return $v ? '1' : '0';
    }
    return $pdo->quote((string) $v);
}

/* ------------------------------------------------------------------ */
/* La notice                                                           */
/* ------------------------------------------------------------------ */

function notice_sauvegarde(bool $mysql, int $cadres, int $medias = 0): string
{
    $base = $mysql
        ? "  base.sql        le contenu complet de la base MySQL\n"
        : "  wakabi.sqlite   la base entière, en un fichier\n";

    $manuel = $mysql
        ? "1. Dans cPanel, ouvrez phpMyAdmin et sélectionnez votre base.\n"
        . "2. Onglet « Importer », choisissez base.sql, lancez.\n"
        . "   La base doit être VIDE : le fichier recrée chaque table.\n"
        . "3. Recopiez cadres/ et medias/ dans donnees/ du site.\n"
        : "1. Arrêtez d'utiliser le site le temps de l'opération.\n"
        . "2. Remplacez donnees/wakabi.sqlite par le fichier wakabi.sqlite ci-joint.\n"
        . "3. Recopiez cadres/ et medias/ dans donnees/ du site.\n";

    $restaurer = "LE PLUS SIMPLE — depuis le site lui-même\n"
        . "  Administration > Sauvegardes. Chaque archive présente sur le\n"
        . "  serveur porte un bouton « Restaurer ». L'écran montre d'abord ce\n"
        . "  qu'elle contient, puis demande de recopier son nom. Une copie de\n"
        . "  l'état actuel est prise juste avant : se tromper reste rattrapable.\n"
        . "  Si cette archive n'est plus sur le serveur, reversez-la dans\n"
        . "  donnees/sauvegardes/ et elle réapparaîtra dans la liste.\n\n"
        . "À LA MAIN — si le site ne répond plus du tout\n"
        . $manuel;

    return "SAUVEGARDE WAKABI BOOST\n"
        . "=======================\n\n"
        . "Faite le " . gmdate('d/m/Y à H:i') . " UTC.\n\n"
        . "CE QUE CONTIENT CETTE ARCHIVE\n"
        . $base
        . "  cadres/         les $cadres fichiers de cadres téléversés\n"
        . "  medias/         les $medias couvertures d'articles\n\n"
        . "CE QU'ELLE NE CONTIENT PAS\n"
        . "  config.php — il décrit VOTRE serveur (identifiants de base de\n"
        . "  données, chemins) et n'a rien à faire dans une archive qui\n"
        . "  circule. Gardez-en une copie à part.\n\n"
        . "  Les vignettes de partage et les images redimensionnées : elles\n"
        . "  se recalculent toutes seules à la première visite.\n\n"
        . "RESTAURER\n"
        . $restaurer
        . "\n"
        . "IMPORTANT\n"
        . "  Une sauvegarde qui reste sur le serveur qu'elle sauvegarde ne\n"
        . "  sauvegarde rien. Téléchargez-la, et gardez-en une copie\n"
        . "  ailleurs — un disque, un espace en ligne, peu importe, mais\n"
        . "  pas la même machine.\n";
}

/* ------------------------------------------------------------------ */
/* Restaurer                                                           */
/* ------------------------------------------------------------------ */

/**
 * Ce qu'une archive contient, SANS rien toucher.
 *
 * On regarde avant d'agir : le nombre de comptes et de décors qu'on
 * s'apprête à remettre, la date, le moteur. Restaurer à l'aveugle, c'est
 * découvrir après coup qu'on vient d'écraser une semaine avec une archive
 * de l'an dernier — et il n'y a pas de retour en arrière.
 *
 * @return array{moteur: string, date: string, comptes: int, decors: int,
 *                articles: int, cadres: int, medias: int, notice: ?string}
 */
function inspecter_sauvegarde(string $archive): array
{
    $z = new LecteurZip($archive);
    $noms = $z->noms();

    $vu = [
        'moteur' => $z->contient('base.sql') ? 'mysql' : ($z->contient('wakabi.sqlite') ? 'sqlite' : ''),
        'date' => gmdate('d/m/Y à H:i', (int) @filemtime($archive)) . ' UTC',
        'comptes' => 0, 'decors' => 0, 'articles' => 0,
        'cadres' => count(array_filter($noms, fn(string $n) => str_starts_with($n, 'cadres/'))),
        'medias' => count(array_filter($noms, fn(string $n) => str_starts_with($n, 'medias/'))),
        'notice' => $z->lire('LISEZ-MOI.txt'),
    ];
    if ($vu['moteur'] === '') {
        throw new RuntimeException('Cette archive ne contient aucune base : '
            . 'ce n’est pas une sauvegarde Wakabi Boost.');
    }

    if ($vu['moteur'] === 'sqlite') {
        // On ouvre la copie à part, en lecture : compter dans un fichier
        // qu'on n'a pas encore mis en place est la seule façon honnête de
        // dire à quelqu'un ce qu'il s'apprête à restaurer.
        $tmp = dossier_sauvegardes() . '/.inspection-' . bin2hex(random_bytes(6)) . '.sqlite';
        if ($z->extraire('wakabi.sqlite', $tmp) > 0) {
            try {
                $pdo = new PDO('sqlite:' . $tmp, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
                foreach (['comptes' => 'utilisateurs', 'decors' => 'decors', 'articles' => 'articles'] as $k => $t) {
                    try {
                        $vu[$k] = (int) $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
                    } catch (PDOException) {
                    }
                }
            } catch (PDOException) {
            }
            @unlink($tmp);
        }
    } else {
        // Un dump SQL : on compte les lignes d'INSERT, ce qui suffit à
        // situer l'ordre de grandeur sans exécuter quoi que ce soit.
        $sql = (string) $z->lire('base.sql');
        foreach (['comptes' => 'utilisateurs', 'decors' => 'decors', 'articles' => 'articles'] as $k => $t) {
            $vu[$k] = substr_count($sql, "INSERT INTO `$t`");
        }
    }

    return $vu;
}

/**
 * Remet une archive en place. Le geste le plus dangereux du produit.
 *
 * Trois précautions, dans cet ordre :
 *
 *  1. **Une sauvegarde de l'état actuel est prise d'abord**, et son nom
 *     est rendu. Se tromper d'archive doit rester rattrapable ; sans ce
 *     filet, la restauration est un aller simple.
 *  2. **La base est remplacée avant les fichiers.** Si l'écriture échoue,
 *     on s'arrête là : mieux vaut une base intacte avec des cadres à
 *     moitié remplacés que l'inverse.
 *  3. **Les cadres et médias sont AJOUTÉS, pas substitués.** Un fichier
 *     présent des deux côtés est réécrit ; un fichier que l'archive ne
 *     connaît pas reste. On ne supprime rien : une image orpheline ne
 *     coûte que de la place, une image manquante casse un badge déjà
 *     entre les mains d'un invité.
 *
 * @return array{filet: string, tables: int, cadres: int, medias: int}
 */
function restaurer_sauvegarde(string $archive): array
{
    $vu = inspecter_sauvegarde($archive);
    $z = new LecteurZip($archive);

    // 1. le filet
    $filet = ecrire_sauvegarde(dossier_sauvegardes() . '/avant-restauration-'
        . gmdate('Ymd-His') . '.zip');

    // 2. la base
    $tables = 0;
    if ($vu['moteur'] === 'sqlite') {
        if (est_mysql()) {
            throw new RuntimeException('Cette archive vient d’une installation SQLite, '
                . 'et celle-ci tourne sur MySQL. Les deux ne se remplacent pas l’une l’autre.');
        }
        $cible = (string) (config()['fichier'] ?? dossier_donnees() . '/wakabi.sqlite');
        $neuf = $cible . '.neuf';
        if ($z->extraire('wakabi.sqlite', $neuf) < 1) {
            throw new RuntimeException('La base contenue dans l’archive est illisible.');
        }
        // On ferme la connexion avant de remplacer le fichier sous elle :
        // sur certains systèmes, écraser une base ouverte laisse la
        // connexion pointer sur l'ancien inode, et l'écran suivant montre
        // encore les données d'avant.
        db_fermer();
        if (!@rename($neuf, $cible)) {
            @unlink($neuf);
            throw new RuntimeException('Impossible de remplacer donnees/wakabi.sqlite — '
                . 'le dossier est-il accessible en écriture ?');
        }
        $tables = (int) db()->query(
            "SELECT COUNT(*) FROM sqlite_master WHERE type = 'table'")->fetchColumn();
    } else {
        if (!est_mysql()) {
            throw new RuntimeException('Cette archive vient d’une installation MySQL, '
                . 'et celle-ci tourne sur SQLite. Les deux ne se remplacent pas l’une l’autre.');
        }
        $sql = (string) $z->lire('base.sql');
        if (trim($sql) === '') {
            throw new RuntimeException('Le dump contenu dans l’archive est vide.');
        }
        $pdo = db();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (decouper_sql($sql) as $requete) {
            if (trim($requete) === '') {
                continue;
            }
            $pdo->exec($requete);
            if (stripos($requete, 'CREATE TABLE') !== false) {
                $tables++;
            }
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    // 3. les fichiers
    $cadres = $medias = 0;
    foreach ($z->noms() as $nom) {
        if (str_starts_with($nom, 'cadres/') && strlen($nom) > 7) {
            $cadres += $z->extraire($nom, dossier_cadres() . '/' . basename($nom)) > 0 ? 1 : 0;
        } elseif (str_starts_with($nom, 'medias/') && strlen($nom) > 7) {
            $medias += $z->extraire($nom, dossier_medias() . '/' . basename($nom)) > 0 ? 1 : 0;
        }
    }

    // Les vignettes décrivent les images d'AVANT : on les jette plutôt que
    // de servir le cache d'une base qui n'existe plus.
    foreach (glob(dossier_vignettes() . '/*') ?: [] as $f) {
        @unlink($f);
    }

    return ['filet' => basename($filet), 'tables' => $tables,
            'cadres' => $cadres, 'medias' => $medias];
}

/**
 * Découpe un dump en requêtes, en respectant les chaînes.
 *
 * Un simple `explode(';')` couperait au milieu d'un texte contenant un
 * point-virgule — et il y en a dans les articles, dans les notes, et dans
 * la moindre adresse recopiée.
 */
function decouper_sql(string $sql): array
{
    $out = [];
    $courant = '';
    $dans = false;
    $echappe = false;
    $n = strlen($sql);
    for ($i = 0; $i < $n; $i++) {
        $c = $sql[$i];
        $courant .= $c;
        if ($echappe) {
            $echappe = false;
            continue;
        }
        if ($c === '\\') {
            $echappe = true;
            continue;
        }
        if ($c === "'") {
            $dans = !$dans;
            continue;
        }
        if ($c === ';' && !$dans) {
            $out[] = $courant;
            $courant = '';
        }
    }
    if (trim($courant) !== '') {
        $out[] = $courant;
    }
    return $out;
}
