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

/**
 * Version du schéma.
 *
 * Une installation déjà en service ne repasse jamais par install.php : sans
 * ce numéro, une colonne ajoutée après coup n'existerait que chez les
 * nouveaux. Le numéro est gardé dans un fichier du dossier de données, donc
 * lisible sans toucher à la base — et la migration ne coûte qu'un stat de
 * fichier par requête.
 */
const SCHEMA_VERSION = 4;

function assurer_schema(): void
{
    $marque = dossier_donnees() . '/version-schema.txt';
    $vue = is_file($marque) ? (int) file_get_contents($marque) : 0;
    if ($vue >= SCHEMA_VERSION) {
        return;
    }
    migrer_schema(db(), est_mysql());
    @file_put_contents($marque, (string) SCHEMA_VERSION);
}

/**
 * Rattrape les colonnes ajoutées après la première installation.
 *
 * Chaque ALTER est tenté puis ignoré s'il échoue : MySQL comme SQLite
 * refusent d'ajouter une colonne qui existe déjà, et c'est exactement le
 * cas qu'on veut traverser sans bruit.
 */
function migrer_schema(PDO $pdo, bool $mysql): void
{
    $court = $mysql ? 'VARCHAR(190)' : 'TEXT';

    foreach ([
        // v2 — la formule commerciale du compte, alignée sur les offres.
        "ALTER TABLE utilisateurs ADD COLUMN formule $court NOT NULL DEFAULT 'decouverte'",
    ] as $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException) {
        }
    }

    migrer_donnees($pdo);
}

/**
 * Les rattrapages qui portent sur le CONTENU, pas sur la forme des tables.
 *
 * v3 — le dézoom sous le cadrage « cover » devient possible. Les décors déjà
 * enregistrés portent encore `minScale: 0.5` dans leur gabarit : sans ce
 * passage, la moitié basse du curseur resterait morte sur tout ce qui a été
 * créé avant la mise à jour, et l'invité ne pourrait toujours pas faire
 * tenir une image entière.
 *
 * v4 — la fenêtre photo suit l'ouverture du cadre. Les décors enregistrés
 * avant portent une zone photo qui couvre le canevas ENTIER : la photo de
 * l'invité y est cadrée sur toute la surface alors que le cadre ne laisse
 * voir qu'une bande, et son visage tombe où il veut. Pour les décors bâtis
 * sur un cadre FOURNI, l'ouverture est connue au millimètre : on la pose.
 * Un cadre téléversé, lui, n'est pas touché — personne ici ne sait ce qu'il
 * contient, et son auteur peut le relever d'un bouton dans le formulaire.
 *
 * Idempotent par construction : on ne touche que ce qui dépasse encore.
 */
function migrer_donnees(PDO $pdo): void
{
    try {
        $decors = $pdo->query('SELECT id, gabarit FROM decors')->fetchAll();
    } catch (PDOException) {
        return;
    }

    $maj = $pdo->prepare('UPDATE decors SET gabarit = ? WHERE id = ?');
    foreach ($decors as $d) {
        $g = json_decode((string) $d['gabarit'], true);
        if (!is_array($g) || !isset($g['layers'])) {
            continue;
        }
        // Le cadre du décor, s'il vient de ceux que l'application fournit.
        // Le garde `function_exists` n'est pas de la superstition : une
        // migration qui provoque une erreur fatale laisse une installation à
        // moitié à jour, et c'est le pire état possible.
        $ouverture = null;
        if (function_exists('fenetres_relevees')) {
            foreach ($g['layers'] as $l) {
                if (($l['type'] ?? '') === 'image' && ($l['src'] ?? '') !== '') {
                    $ouverture = fenetres_relevees()[basename((string) $l['src'])] ?? null;
                }
            }
        }

        $change = false;
        foreach ($g['layers'] as $k => $l) {
            if (($l['type'] ?? '') !== 'photoSlot') {
                continue;
            }
            if ((float) ($l['minScale'] ?? 1) > 0.2) {
                $g['layers'][$k]['minScale'] = 0.2;
                $change = true;
            }
            $r = $l['rect'] ?? [];
            $entier = (float) ($r['x'] ?? 0) <= 0.001 && (float) ($r['y'] ?? 0) <= 0.001
                   && (float) ($r['w'] ?? 1) >= 0.999 && (float) ($r['h'] ?? 1) >= 0.999;
            if ($ouverture && $entier) {
                $g['layers'][$k]['rect'] = [
                    'x' => $ouverture['photo_x'], 'y' => $ouverture['photo_y'],
                    'w' => $ouverture['photo_w'], 'h' => $ouverture['photo_h'],
                ];
                $g['layers'][$k]['mask'] = match ($ouverture['photo_forme']) {
                    'cercle' => ['kind' => 'circle'],
                    'arrondi' => ['kind' => 'rect', 'radius' => 0.08],
                    default => ['kind' => 'rect', 'radius' => 0],
                };
                $change = true;
            }
        }
        if ($change) {
            $maj->execute([
                json_encode($g, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                $d['id'],
            ]);
        }
    }
}

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
            formule           $court NOT NULL DEFAULT 'decouverte',
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
