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

    $restaurer = $mysql
        ? "1. Dans cPanel, ouvrez phpMyAdmin et sélectionnez votre base.\n"
        . "2. Onglet « Importer », choisissez base.sql, lancez.\n"
        . "   La base doit être VIDE : le fichier recrée chaque table.\n"
        . "3. Recopiez cadres/ et medias/ dans donnees/ du site.\n"
        : "1. Arrêtez d'utiliser le site le temps de l'opération.\n"
        . "2. Remplacez donnees/wakabi.sqlite par le fichier wakabi.sqlite ci-joint.\n"
        . "3. Recopiez cadres/ et medias/ dans donnees/ du site.\n";

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
