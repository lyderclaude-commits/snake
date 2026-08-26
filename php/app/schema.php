<?php
/**
 * Schéma — identique en MySQL et en SQLite.
 *
 * Les deux moteurs sont supportés parce qu'ils répondent à deux besoins
 * différents : SQLite ne demande AUCUNE configuration (on décompresse, ça
 * marche), MySQL est ce que propose un cPanel et survit mieux à la charge.
 * Le reste de l'application ne sait pas lequel tourne.
 */

declare(strict_types=1);

function creer_schema(PDO $pdo, bool $mysql): void
{
    $id = $mysql ? 'VARCHAR(36)' : 'TEXT';
    $txt = $mysql ? 'TEXT' : 'TEXT';
    $court = $mysql ? 'VARCHAR(190)' : 'TEXT';
    $auto = $mysql ? 'INT AUTO_INCREMENT PRIMARY KEY' : 'INTEGER PRIMARY KEY AUTOINCREMENT';
    $moteur = $mysql ? ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4' : '';

    $tables = [

        "CREATE TABLE IF NOT EXISTS utilisateurs (
            id                $id PRIMARY KEY,
            email             $court NOT NULL UNIQUE,
            mot_de_passe      $txt NOT NULL,
            nom               $court NOT NULL,
            role              $court NOT NULL DEFAULT 'participant',
            organisation      $court NULL,
            ville             $court NULL,
            suspendu          INT NOT NULL DEFAULT 0,
            email_verifie_le  $court NULL,
            cree_le           $court NOT NULL
        )$moteur",

        "CREATE TABLE IF NOT EXISTS decors (
            id            $id PRIMARY KEY,
            slug          $court NOT NULL UNIQUE,
            titre         $court NOT NULL,
            sous_titre    $court NULL,
            ville         $court NOT NULL DEFAULT 'all',
            rubrique      $court NOT NULL DEFAULT 'campagne',
            statut        $court NOT NULL DEFAULT 'brouillon',
            cree_par      $court NOT NULL DEFAULT 'equipe',
            auteur_id     $id NULL,
            gabarit       $txt NOT NULL,
            cadre_url     $court NULL,
            publie_le     $court NULL,
            expire_le     $court NULL,
            soumis_le     $court NULL,
            relu_le       $court NULL,
            relu_par      $id NULL,
            motif         $txt NULL,
            cree_le       $court NOT NULL,
            maj_le        $court NOT NULL
        )$moteur",

        "CREATE TABLE IF NOT EXISTS evenements (
            id        $auto,
            decor_id  $id NOT NULL,
            genre     $court NOT NULL,
            cree_le   $court NOT NULL
        )$moteur",

        "CREATE TABLE IF NOT EXISTS creations (
            id             $id PRIMARY KEY,
            utilisateur_id $id NOT NULL,
            decor_id       $id NOT NULL,
            cree_le        $court NOT NULL
        )$moteur",

        "CREATE TABLE IF NOT EXISTS badges (
            jeton          $court PRIMARY KEY,
            decor_id       $id NOT NULL,
            utilisateur_id $id NULL,
            cree_le        $court NOT NULL,
            scanne_le      $court NULL,
            scanne_par     $id NULL,
            koris          INT NOT NULL DEFAULT 0
        )$moteur",

        "CREATE TABLE IF NOT EXISTS koris (
            id             $auto,
            utilisateur_id $id NOT NULL,
            montant        INT NOT NULL,
            motif          $court NOT NULL,
            badge_jeton    $court NULL,
            cree_le        $court NOT NULL
        )$moteur",

        "CREATE TABLE IF NOT EXISTS notifications (
            id             $id PRIMARY KEY,
            utilisateur_id $id NOT NULL,
            genre          $court NOT NULL,
            titre          $court NOT NULL,
            corps          $txt NULL,
            lien           $court NULL,
            lu_le          $court NULL,
            cree_le        $court NOT NULL
        )$moteur",

        "CREATE TABLE IF NOT EXISTS tentatives (
            id      $auto,
            cle     $court NOT NULL,
            cree_le $court NOT NULL
        )$moteur",

        "CREATE TABLE IF NOT EXISTS prevol (
            decor_id $id PRIMARY KEY,
            passe    INT NOT NULL,
            rapport  $txt NOT NULL,
            lance_le $court NOT NULL
        )$moteur",
    ];

    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }

    foreach ([
        'CREATE INDEX idx_evenements_decor ON evenements (decor_id)',
        'CREATE INDEX idx_creations_utilisateur ON creations (utilisateur_id)',
        'CREATE INDEX idx_notifications_utilisateur ON notifications (utilisateur_id)',
        'CREATE INDEX idx_tentatives_cle ON tentatives (cle)',
        'CREATE INDEX idx_decors_statut ON decors (statut)',
    ] as $sql) {
        // MySQL ne connaît pas IF NOT EXISTS sur les index avant la 8.0.29 :
        // relancer l'installation ne doit pas échouer pour si peu.
        try {
            $pdo->exec($sql);
        } catch (PDOException) {
        }
    }
}
