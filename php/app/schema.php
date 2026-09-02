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
const SCHEMA_VERSION = 11;

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
 *
 * **Une colonne ajoutée ici doit AUSSI l'être dans `creer_schema()`.** Les
 * deux fonctions servent deux publics : celle-ci rattrape les
 * installations en service, l'autre bâtit les neuves — qui ne passent
 * jamais par les ALTER. N'en corriger qu'une donne une base qui marche
 * chez les anciens et casse à la première installation propre, c'est-à-dire
 * chez le client qui découvre le produit.
 */
function migrer_schema(PDO $pdo, bool $mysql): void
{
    $txt = $mysql ? 'TEXT' : 'TEXT';
    /**
     * D'abord les tables, ENSUITE les colonnes.
     *
     * `creer_schema()` n'était joué qu'à l'installation : une table ajoutée
     * après coup n'aurait donc existé que chez les nouveaux, et l'écran qui
     * s'en sert serait tombé en erreur chez tous les autres. Tout y est en
     * `CREATE TABLE IF NOT EXISTS`, donc le rejouer ne coûte rien et ferme
     * le trou pour de bon.
     */
    creer_schema($pdo, $mysql);

    $court = $mysql ? 'VARCHAR(190)' : 'TEXT';
    $id = $mysql ? 'VARCHAR(36)' : 'TEXT';

    foreach ([
        // v2 — la formule commerciale du compte, alignée sur les offres.
        "ALTER TABLE utilisateurs ADD COLUMN formule $court NOT NULL DEFAULT 'decouverte'",
        // v5 — la vérification d'adresse : un jeton à la fois, daté.
        "ALTER TABLE utilisateurs ADD COLUMN verif_jeton $court NULL",
        "ALTER TABLE utilisateurs ADD COLUMN verif_expire_le $court NULL",
        // v6 — l'offre s'applique vraiment : une soupape de téléchargements
        // accordée à la main, et la clé de lecture de l'offre Mouvement.
        'ALTER TABLE utilisateurs ADD COLUMN bonus_telechargements INT NOT NULL DEFAULT 0',
        "ALTER TABLE utilisateurs ADD COLUMN cle_api $court NULL",
        "ALTER TABLE utilisateurs ADD COLUMN note_equipe $txt NULL",
        // v7 — l'espace profil : de quoi joindre quelqu'un autrement que par
        // courriel, et savoir quand il est passé pour la dernière fois.
        "ALTER TABLE utilisateurs ADD COLUMN telephone $court NULL",
        "ALTER TABLE utilisateurs ADD COLUMN vu_le $court NULL",
        // v9 — un article se relit comme un décor : mêmes états, même
        // vocabulaire, et un motif quand on refuse ou qu'on renvoie.
        "ALTER TABLE articles ADD COLUMN motif $txt NULL",
        "ALTER TABLE articles ADD COLUMN soumis_le $court NULL",
        "ALTER TABLE articles ADD COLUMN relu_le $court NULL",
        "ALTER TABLE articles ADD COLUMN relu_par $id NULL",
        // v11 — mot de passe oublié, échéances d'abonnement, double
        // authentification de l'équipe. Chacune est AUSSI dans
        // `creer_schema()` : voir l'avertissement en tête de fonction.
        "ALTER TABLE utilisateurs ADD COLUMN mdp_jeton $court NULL",
        "ALTER TABLE utilisateurs ADD COLUMN mdp_expire_le $court NULL",
        "ALTER TABLE utilisateurs ADD COLUMN echeance_le $court NULL",
        "ALTER TABLE utilisateurs ADD COLUMN rappel_echeance $court NULL",
        "ALTER TABLE utilisateurs ADD COLUMN otp_secret $court NULL",
        'ALTER TABLE utilisateurs ADD COLUMN otp_actif INT NOT NULL DEFAULT 0',
    ] as $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException) {
        }
    }

    migrer_donnees($pdo);
    promouvoir_fondateur($pdo);
}

/**
 * v10 — le plus ancien compte d'équipe devient super-administrateur.
 *
 * Le rôle est né après ces installations : sans ce passage, elles se
 * retrouveraient sans personne pour créer un compte d'équipe, et la seule
 * issue serait un UPDATE à la main dans phpMyAdmin. On promeut le plus
 * ANCIEN, parce que c'est celui qu'a créé l'installateur — le compte
 * fondateur, celui dont on a le mot de passe.
 *
 * Idempotent : dès qu'un super-administrateur existe, la fonction ne fait
 * plus rien.
 */
function promouvoir_fondateur(PDO $pdo): void
{
    try {
        $deja = (int) $pdo->query(
            "SELECT COUNT(*) FROM utilisateurs WHERE role = 'super_admin'"
        )->fetchColumn();
        if ($deja > 0) {
            return;
        }
        $id = $pdo->query(
            "SELECT id FROM utilisateurs WHERE role = 'equipe' ORDER BY cree_le ASC LIMIT 1"
        )->fetchColumn();
        if ($id) {
            $pdo->prepare("UPDATE utilisateurs SET role = 'super_admin' WHERE id = ?")->execute([$id]);
        }
    } catch (PDOException) {
        // Table absente : c'est une installation neuve, `install.php` s'en
        // charge et crée directement un super-administrateur.
    }
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
            verif_jeton       $court NULL,
            verif_expire_le   $court NULL,
            bonus_telechargements INT NOT NULL DEFAULT 0,
            cle_api           $court NULL,
            note_equipe       $txt NULL,
            telephone         $court NULL,
            vu_le             $court NULL,
            mdp_jeton         $court NULL,
            mdp_expire_le     $court NULL,
            echeance_le       $court NULL,
            rappel_echeance   $court NULL,
            otp_secret        $court NULL,
            otp_actif         INT NOT NULL DEFAULT 0,
            cree_le           $court NOT NULL
        )$moteur",

        /**
         * Le journal : qui a fait quoi, quand.
         *
         * Avec un seul administrateur, il ne sert à rien. Avec un
         * coordinateur, un éditeur et un scanner qui arbitrent chacun de
         * leur côté, c'est la première question qu'on se pose le jour où un
         * décor a disparu — et sans trace écrite, la réponse est « on ne
         * sait pas », ce qui abîme la confiance dans l'équipe entière.
         *
         * Le nom de l'acteur est RECOPIÉ, pas seulement son identifiant :
         * un compte supprimé ne doit pas effacer ce qu'il a fait.
         */
        "CREATE TABLE IF NOT EXISTS journal (
            id           $id PRIMARY KEY,
            acteur_id    $id NULL,
            acteur_nom   $court NULL,
            acteur_role  $court NULL,
            action       $court NOT NULL,
            objet_type   $court NULL,
            objet_id     $id NULL,
            objet_titre  $court NULL,
            detail       $txt NULL,
            cree_le      $court NOT NULL
        )$moteur",

        /**
         * Les échéances d'abonnement, et leur trace.
         *
         * Une facture n'est pas un ornement comptable : c'est ce qu'un
         * organisateur réclame pour se faire rembourser par son propre
         * employeur, et ce qui permet à l'équipe de dire « vous avez payé
         * jusqu'au 12 » plutôt que de chercher dans ses souvenirs.
         *
         * Le montant est FIGÉ à l'émission : changer le tarif d'une offre
         * ne doit pas réécrire les factures déjà remises.
         */
        "CREATE TABLE IF NOT EXISTS factures (
            id             $id PRIMARY KEY,
            numero         $court NOT NULL UNIQUE,
            utilisateur_id $id NOT NULL,
            client_nom     $court NULL,
            client_org     $court NULL,
            formule        $court NOT NULL,
            montant        INT NOT NULL DEFAULT 0,
            debut_le       $court NOT NULL,
            fin_le         $court NOT NULL,
            reglee_le      $court NULL,
            note           $txt NULL,
            emise_par      $id NULL,
            cree_le        $court NOT NULL
        )$moteur",

        /**
         * Confier UNE campagne sans ouvrir tout le compte.
         *
         * Un organisateur qui fait appel à un graphiste pour une soirée ne
         * veut pas lui donner ses statistiques, ses liens et sa régie. Cette
         * table dit : cette personne-là, sur ce décor-là, et rien d'autre.
         */
        "CREATE TABLE IF NOT EXISTS equipiers (
            id             $id PRIMARY KEY,
            decor_id       $id NOT NULL,
            utilisateur_id $id NOT NULL,
            invite_par     $id NULL,
            cree_le        $court NOT NULL
        )$moteur",

        /**
         * Les réglages que l'équipe change SANS toucher à config.php.
         *
         * `config.php` est écrit par l'installateur, en 0600, et une
         * décompression du zip par-dessus ne doit surtout pas le réécrire :
         * ce n'est pas l'endroit d'un réglage qu'on ajuste depuis
         * l'application. Le transport e-mail, lui, se règle et se teste en
         * ligne — donc ici.
         */
        /**
         * Les liens courts : une adresse à soi, et le compte des clics.
         *
         * `code` est la partie visible — `wkb.link/AbC123`. Il est unique,
         * et c'est la clé sur laquelle on redirige : un index dessus, sinon
         * chaque clic ferait un balayage complet de la table.
         */
        "CREATE TABLE IF NOT EXISTS liens (
            id         $id PRIMARY KEY,
            code       $court NOT NULL UNIQUE,
            cible      $txt NOT NULL,
            titre      $court NULL,
            auteur_id  $id NOT NULL,
            decor_id   $id NULL,
            clics      INT NOT NULL DEFAULT 0,
            dernier_clic $court NULL,
            cree_le    $court NOT NULL
        )$moteur",

        "CREATE TABLE IF NOT EXISTS reglages (
            cle    $court PRIMARY KEY,
            valeur $txt NULL,
            maj_le $court NOT NULL
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

        /**
         * Les abonnements aux notifications du navigateur.
         *
         * Un abonnement n'appartient pas à une personne mais à un
         * NAVIGATEUR : la même personne sur son téléphone et sur son poste
         * en a deux, et un poste partagé peut en porter un sans compte du
         * tout — d'où `utilisateur_id` nullable. C'est ce qui permet de
         * prévenir un visiteur qui n'a pas encore créé de compte.
         *
         * `endpoint` est l'adresse que le service de push nous donne ; elle
         * dépasse allègrement les 190 caractères indexables d'un utf8mb4,
         * et c'est pourtant la clé qui doit être unique — sans quoi la
         * table doublerait à chaque visite. On indexe donc son empreinte,
         * de longueur fixe, et c'est par elle qu'on retrouve une ligne.
         */
        "CREATE TABLE IF NOT EXISTS push (
            id             $id PRIMARY KEY,
            empreinte      $court NOT NULL UNIQUE,
            utilisateur_id $id NULL,
            endpoint       $txt NOT NULL,
            p256dh         $court NOT NULL,
            auth           $court NOT NULL,
            agent          $court NULL,
            cree_le        $court NOT NULL,
            vu_le          $court NULL
        )$moteur",

        /**
         * Le blog, lisible par tout le monde depuis l'accueil.
         *
         * Un article n'est pas une notification : il reste, il se relit, et
         * il se partage. `slug` est ce qui apparaît dans l'adresse et ne
         * change plus une fois l'article publié — un lien partagé ne doit
         * pas casser parce que le titre a été retouché.
         */
        "CREATE TABLE IF NOT EXISTS articles (
            id         $id PRIMARY KEY,
            slug       $court NOT NULL UNIQUE,
            titre      $court NOT NULL,
            chapo      $txt NULL,
            corps      $txt NOT NULL,
            couverture $court NULL,
            statut     $court NOT NULL DEFAULT 'brouillon',
            auteur_id  $id NULL,
            auteur_nom $court NULL,
            vues       INT NOT NULL DEFAULT 0,
            motif      $txt NULL,
            soumis_le  $court NULL,
            relu_le    $court NULL,
            relu_par   $id NULL,
            publie_le  $court NULL,
            cree_le    $court NOT NULL,
            maj_le     $court NOT NULL
        )$moteur",

        /**
         * La régie : une campagne e-mail, de sa rédaction à son envoi.
         *
         * `statut` reprend mot pour mot le vocabulaire des décors —
         * brouillon, en_relecture, corrections, refuse, envoye — parce que
         * c'est le même geste : quelqu'un propose, l'équipe décide. Un
         * deuxième vocabulaire pour la même idée obligerait à se souvenir
         * lequel s'applique où.
         */
        "CREATE TABLE IF NOT EXISTS campagnes_email (
            id            $id PRIMARY KEY,
            auteur_id     $id NULL,
            sujet         $court NOT NULL,
            titre         $court NOT NULL,
            corps         $txt NOT NULL,
            lien          $court NULL,
            lien_libelle  $court NULL,
            cible         $court NOT NULL DEFAULT 'mes-invites',
            liste         $txt NULL,
            statut        $court NOT NULL DEFAULT 'brouillon',
            motif         $txt NULL,
            destinataires INT NOT NULL DEFAULT 0,
            envoyes       INT NOT NULL DEFAULT 0,
            echecs        INT NOT NULL DEFAULT 0,
            ouvertures    INT NOT NULL DEFAULT 0,
            soumis_le     $court NULL,
            relu_le       $court NULL,
            relu_par      $id NULL,
            envoye_le     $court NULL,
            cree_le       $court NOT NULL,
            maj_le        $court NOT NULL
        )$moteur",

        /**
         * Un destinataire, une ligne — et c'est ce qui rend l'envoi repris.
         *
         * Un hébergement mutualisé coupe un script à trente secondes :
         * envoyer deux mille messages dans une boucle finirait à la moitié,
         * sans qu'on sache laquelle. La liste est donc figée AVANT le
         * premier envoi, et chaque ligne porte son état. Une reprise sait
         * exactement où elle s'est arrêtée, et personne ne reçoit deux fois.
         *
         * `jeton` sert au désabonnement et au pixel d'ouverture : il
         * identifie la ligne sans écrire l'adresse dans une URL, qui
         * traverse les journaux de tous les intermédiaires.
         */
        "CREATE TABLE IF NOT EXISTS envois_email (
            id         $id PRIMARY KEY,
            campagne_id $id NOT NULL,
            email      $court NOT NULL,
            nom        $court NULL,
            jeton      $court NOT NULL UNIQUE,
            statut     $court NOT NULL DEFAULT 'attente',
            message    $txt NULL,
            ouvert_le  $court NULL,
            envoye_le  $court NULL,
            cree_le    $court NOT NULL
        )$moteur",

        /**
         * Les désabonnements, GLOBAUX et définitifs.
         *
         * Par adresse et non par campagne : quelqu'un qui demande à ne plus
         * recevoir de courrier marketing le demande pour de bon, pas
         * seulement à l'organisateur du jour. Le contraire — se
         * réabonner tout seul à la campagne suivante — est exactement ce
         * qui fait signaler un expéditeur comme indésirable, et c'est le
         * domaine du guide qui en paie le prix.
         */
        "CREATE TABLE IF NOT EXISTS desabonnements (
            email   $court PRIMARY KEY,
            motif   $court NULL,
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
        'CREATE INDEX idx_liens_auteur ON liens (auteur_id)',
        'CREATE INDEX idx_push_utilisateur ON push (utilisateur_id)',
        'CREATE INDEX idx_articles_statut ON articles (statut)',
        'CREATE INDEX idx_envois_campagne ON envois_email (campagne_id)',
        'CREATE INDEX idx_campagnes_email_auteur ON campagnes_email (auteur_id)',
        'CREATE INDEX idx_journal_date ON journal (cree_le)',
        'CREATE INDEX idx_factures_utilisateur ON factures (utilisateur_id)',
        'CREATE INDEX idx_equipiers_decor ON equipiers (decor_id)',
        'CREATE INDEX idx_equipiers_utilisateur ON equipiers (utilisateur_id)',
    ] as $sql) {
        // MySQL ne connaît pas IF NOT EXISTS sur les index avant la 8.0.29 :
        // relancer l'installation ne doit pas échouer pour si peu.
        try {
            $pdo->exec($sql);
        } catch (PDOException) {
        }
    }
}
