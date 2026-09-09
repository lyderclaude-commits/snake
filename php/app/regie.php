<?php
/**
 * La régie publicitaire : écrire à des gens, et savoir à qui.
 *
 * Un organisateur qui a rempli une salle a constitué quelque chose de
 * précieux — la liste des gens qui sont venus. Sans cet écran, cette liste
 * dort dans la base et il repart de zéro à chaque événement. C'est la
 * différence entre un générateur de badges et un outil de fidélisation.
 *
 * Trois décisions structurent tout le fichier :
 *
 *  1. **Toute campagne d'organisateur passe par l'équipe.** C'est ce que
 *     veut dire « régie » : une maison qui vend de l'espace publicitaire
 *     relit ce qui part sous son nom. Techniquement, c'est aussi la seule
 *     protection réelle du domaine d'envoi — un message signalé comme
 *     indésirable abîme la délivrabilité de TOUS les envois du guide, y
 *     compris les liens de confirmation d'adresse.
 *
 *  2. **La liste des destinataires est figée AVANT le premier envoi.** Un
 *     mutualisé coupe un script à trente secondes ; une boucle de deux
 *     mille messages finirait à la moitié, sans qu'on sache laquelle. Ici
 *     chaque destinataire est une ligne qui porte son propre état, et une
 *     reprise sait exactement où elle s'est arrêtée.
 *
 *  3. **Le désabonnement est en un clic, et il est global.** Ce n'est pas
 *     une politesse : c'est ce que réclame le RGPD, et c'est aussi ce qui
 *     évite qu'un lecteur agacé clique sur « signaler comme indésirable »
 *     — le seul geste dont on ne se relève pas.
 */

declare(strict_types=1);

/** Combien d'envois par passage. Au-delà, un mutualisé coupe. */
const REGIE_LOT = 25;

/**
 * Combien de fois on retente un envoi avant de le déclarer perdu.
 *
 * Un échec était DÉFINITIF : le relais tousse trois secondes, et vingt-cinq
 * personnes sortaient de la campagne sans que personne ne le sache. Trois
 * essais couvrent la coupure passagère ; au-delà, ce n'est plus un accident,
 * et s'acharner sur une adresse morte abîme la réputation du domaine.
 */
const REGIE_TENTATIVES = 3;

/**
 * L'en-tête qui décide de la remise chez Gmail, Yahoo et Microsoft.
 *
 * Le lien de désabonnement existait déjà dans le pied du message ; il ne
 * suffit plus. Depuis 2024 les trois demandent le désabonnement EN UN CLIC
 * (RFC 8058) : deux en-têtes, et une adresse qui accepte un POST sans
 * demander à personne de se connecter. Sans eux, au-delà de cinq mille
 * messages par jour, c'est un refus SMTP — pas un classement en
 * indésirables.
 */
function entetes_desabonnement(string $jeton): array
{
    $url = url_desabonnement($jeton);
    return [
        'List-Unsubscribe' => '<' . $url . '&clic=1>',
        'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        // Une lettre d'information n'est pas une réponse automatique : le
        // dire évite d'être rangé avec les accusés de réception.
        'Auto-Submitted' => null,
        'Precedence' => 'bulk',
        'List-Id' => 'Wakabi Boost <regie.' . (parse_url(base_url(), PHP_URL_HOST) ?: 'localhost') . '>',
    ];
}

/**
 * Les cibles, et qui a le droit de les viser.
 *
 * `equipe` : la base du guide, segmentée. `partenaire` : ses propres
 * invités, et une liste qu'il apporte. Un organisateur ne voit jamais la
 * base du guide — elle s'est constituée sur la promesse de nouvelles du
 * guide, et la louer la brûlerait en trois campagnes.
 */
const REGIE_CIBLES = [
    'mes-invites'   => ['Les invités de mes campagnes', 'partenaire'],
    'liste'         => ['Une liste de mon carnet', 'partenaire'],
    'tous'          => ['Tout le monde', 'equipe'],
    'organisateurs' => ['Les organisateurs', 'equipe'],
    'participants'  => ['Les participants', 'equipe'],
    'lome'          => ['Lomé', 'equipe'],
    'cotonou'       => ['Cotonou', 'equipe'],
    'abidjan'       => ['Abidjan', 'equipe'],
];

function regie_cibles_de(array $u): array
{
    $equipe = droit($u, 'valider');
    $out = [];
    foreach (REGIE_CIBLES as $cle => [$libelle, $pour]) {
        if ($equipe || $pour === 'partenaire') {
            $out[$cle] = $libelle;
        }
    }
    return $out;
}

const REGIE_STATUTS = [
    'brouillon'    => 'Brouillon',
    'en_relecture' => 'En relecture',
    'corrections'  => 'À corriger',
    'refuse'       => 'Refusée',
    'prete'        => 'Prête à partir',
    'envoi'        => 'En cours d’envoi',
    'envoye'       => 'Envoyée',
];

/* ------------------------------------------------------------------ */
/* Les campagnes                                                       */
/* ------------------------------------------------------------------ */

function campagne_email(string $id): ?array
{
    $s = db()->prepare('SELECT * FROM campagnes_email WHERE id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

function campagnes_email_de(string $auteur_id): array
{
    $s = db()->prepare('SELECT * FROM campagnes_email WHERE auteur_id = ? ORDER BY cree_le DESC');
    $s->execute([$auteur_id]);
    return $s->fetchAll();
}

function campagnes_email_toutes(?string $statut = null): array
{
    if ($statut !== null) {
        $s = db()->prepare('SELECT c.*, u.nom AS auteur_nom, u.email AS auteur_email
                            FROM campagnes_email c LEFT JOIN utilisateurs u ON u.id = c.auteur_id
                            WHERE c.statut = ? ORDER BY c.soumis_le ASC, c.cree_le DESC');
        $s->execute([$statut]);
        return $s->fetchAll();
    }
    return db()->query('SELECT c.*, u.nom AS auteur_nom, u.email AS auteur_email
                        FROM campagnes_email c LEFT JOIN utilisateurs u ON u.id = c.auteur_id
                        ORDER BY c.cree_le DESC')->fetchAll();
}

/**
 * Les cinq moments d'un événement.
 *
 * Le décalage est en heures avant l'événement — négatif pour l'après. Les
 * textes sont des points de départ : ce sont des campagnes ordinaires, et
 * tout s'y modifie ensuite.
 */
const RAPPELS_MODELE = [
    ['cle' => 'annonce', 'heures' => 24 * 14, 'titre' => 'Votre badge vous attend',
     'corps' => "C’est ouvert : créez votre badge en trente secondes, et montrez que vous y serez.",
     'libelle' => 'Créer mon badge'],
    ['cle' => 'semaine', 'heures' => 24 * 7, 'titre' => 'Plus qu’une semaine',
     'corps' => "Rendez-vous dans une semaine. Si ce n’est pas fait, votre badge vous attend toujours.",
     'libelle' => 'Voir mon badge'],
    ['cle' => 'veille', 'heures' => 26, 'titre' => 'C’est demain, voici votre badge',
     'corps' => "Présentez votre badge à l’accueil : on le scanne, et vous entrez.",
     'libelle' => 'Ouvrir mon badge'],
    ['cle' => 'portes', 'heures' => 2, 'titre' => 'On ouvre bientôt',
     'corps' => "Les portes ouvrent dans deux heures. Pensez à arriver tôt : après, c’est la file.",
     'libelle' => ''],
    ['cle' => 'merci', 'heures' => -15, 'titre' => 'Merci d’être venu',
     'corps' => "C’était une belle soirée. À très vite pour la prochaine.",
     'libelle' => 'Revoir le décor'],
];

/** L'heure d'un rappel, calculée depuis la date de l'événement. */
function rappel_quand(?string $evenement_le, int $heures): ?string
{
    if (!$evenement_le) {
        return null;
    }
    // Sans heure dans la colonne, on vise 20 h : un événement se tient le
    // soir, et un rappel « la veille à minuit » n'est pas un rappel.
    $base = strtotime(strlen($evenement_le) <= 10 ? $evenement_le . 'T20:00:00Z' : $evenement_le);
    return $base === false ? null : gmdate('Y-m-d\TH:i:s\Z', $base - $heures * 3600);
}

/** Les campagnes rattachées à un décor, dans l'ordre où elles partiront. */
function campagnes_du_decor(string $decor_id): array
{
    $s = db()->prepare('SELECT * FROM campagnes_email WHERE decor_id = ?
                        ORDER BY COALESCE(planifie_le, cree_le)');
    $s->execute([$decor_id]);
    return $s->fetchAll();
}

function campagnes_email_en_attente(): int
{
    return (int) db()->query("SELECT COUNT(*) FROM campagnes_email WHERE statut = 'en_relecture'")
        ->fetchColumn();
}

function campagne_email_creer(array $c): string
{
    $id = nouvel_id();
    $now = maintenant();
    db()->prepare('INSERT INTO campagnes_email
        (id, auteur_id, sujet, titre, corps, lien, lien_libelle, cible, liste, liste_id,
         canaux, planifie_le, decor_id, rappel, statut, cree_le, maj_le)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
      ->execute([
          $id, $c['auteur_id'], $c['sujet'], $c['titre'], $c['corps'],
          $c['lien'] ?: null, $c['lien_libelle'] ?: null,
          $c['cible'], $c['liste'] ?: null, $c['liste_id'] ?: null,
          $c['canaux'] ?? null, $c['planifie_le'] ?? null,
          $c['decor_id'] ?? null, $c['rappel'] ?? null,
          'brouillon', $now, $now,
      ]);
    return $id;
}

function campagne_email_maj(string $id, array $c): void
{
    db()->prepare('UPDATE campagnes_email SET sujet = ?, titre = ?, corps = ?, lien = ?,
                   lien_libelle = ?, cible = ?, liste = ?, liste_id = ?, canaux = ?,
                   planifie_le = ?, decor_id = ?, rappel = ?, maj_le = ? WHERE id = ?')
        ->execute([
            $c['sujet'], $c['titre'], $c['corps'], $c['lien'] ?: null,
            $c['lien_libelle'] ?: null, $c['cible'], $c['liste'] ?: null,
            $c['liste_id'] ?: null, $c['canaux'] ?? null, $c['planifie_le'] ?? null,
            $c['decor_id'] ?? null, $c['rappel'] ?? null, maintenant(), $id,
        ]);
}

/**
 * Ouvre une campagne dont l'heure programmée est venue.
 *
 * Pas de passage par `campagne_email_transition` : celle-ci vérifie un
 * RÔLE, et le cron n'en a pas. Il n'en a pas besoin non plus — la
 * relecture a déjà eu lieu, c'est ce que « prête » veut dire, et la seule
 * décision qui reste est celle de l'horloge.
 */
function campagne_ouvrir_planifiee(string $id): bool
{
    $s = db()->prepare("UPDATE campagnes_email SET statut = 'envoi', maj_le = ?
                        WHERE id = ? AND statut = 'prete'");
    $s->execute([maintenant(), $id]);
    return $s->rowCount() > 0;
}

function campagne_email_supprimer(string $id): void
{
    db()->prepare('DELETE FROM envois_email WHERE campagne_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM campagnes_email WHERE id = ?')->execute([$id]);
}

/**
 * Change l'état d'une campagne, en faisant respecter le circuit.
 *
 * La même machine à états que les décors, et pour la même raison : c'est
 * ici que la modération devient une règle plutôt qu'une consigne. Un
 * organisateur ne peut ni approuver ni envoyer, quoi qu'il poste.
 */
function campagne_email_transition(string $id, string $vers, array $acteur, ?string $motif = null): void
{
    $regles = [
        'partenaire' => [
            'brouillon'   => ['en_relecture'],
            'corrections' => ['en_relecture'],
            'refuse'      => ['brouillon'],
        ],
        'equipe' => [
            'brouillon'    => ['en_relecture', 'prete'],
            'en_relecture' => ['prete', 'corrections', 'refuse'],
            'corrections'  => ['prete', 'refuse'],
            'refuse'       => ['brouillon'],
            'prete'        => ['envoi', 'brouillon'],
            'envoi'        => ['envoye'],
        ],
    ];
    $c = campagne_email($id);
    if (!$c) {
        throw new RuntimeException('Campagne introuvable.');
    }
    $role = droit($acteur, 'valider') ? 'equipe' : 'partenaire';
    if (!in_array($vers, $regles[$role][$c['statut']] ?? [], true)) {
        throw new RuntimeException(sprintf(
            'Passage « %s → %s » non autorisé pour ce rôle.',
            REGIE_STATUTS[$c['statut']] ?? $c['statut'],
            REGIE_STATUTS[$vers] ?? $vers
        ));
    }
    if (in_array($vers, ['refuse', 'corrections'], true) && !trim((string) $motif)) {
        throw new RuntimeException('Un motif est obligatoire pour refuser ou demander des corrections.');
    }

    $now = maintenant();
    $sets = ['statut = ?', 'maj_le = ?'];
    $vals = [$vers, $now];
    if ($vers === 'en_relecture') {
        $sets[] = 'soumis_le = ?';
        $vals[] = $now;
    }
    if (in_array($vers, ['prete', 'corrections', 'refuse'], true)) {
        $sets[] = 'relu_le = ?';
        $sets[] = 'relu_par = ?';
        $sets[] = 'motif = ?';
        $vals[] = $now;
        $vals[] = $acteur['id'];
        $vals[] = trim((string) $motif) ?: null;
    }
    if ($vers === 'envoye') {
        $sets[] = 'envoye_le = ?';
        $vals[] = $now;
    }
    $vals[] = $id;
    db()->prepare('UPDATE campagnes_email SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
}

/* ------------------------------------------------------------------ */
/* Les destinataires                                                   */
/* ------------------------------------------------------------------ */

/** Une adresse a-t-elle demandé qu'on lui fiche la paix ? */
function desabonne(string $email): bool
{
    $s = db()->prepare('SELECT 1 FROM desabonnements WHERE email = ?');
    $s->execute([mb_strtolower(trim($email))]);
    return (bool) $s->fetchColumn();
}

function desabonner(string $email, string $motif = ''): void
{
    $email = mb_strtolower(trim($email));
    try {
        db()->prepare('INSERT INTO desabonnements (email, motif, cree_le) VALUES (?,?,?)')
            ->execute([$email, $motif ?: null, maintenant()]);
    } catch (PDOException) {
        // Déjà désabonné : c'est le résultat voulu, pas une erreur.
    }
}

/**
 * Résout une cible en adresses, sans doublon et sans désabonné.
 *
 * @return array<string, string> adresse => nom
 */
function regie_destinataires(array $campagne, array $auteur): array
{
    $cible = (string) $campagne['cible'];
    $lignes = [];

    if ($cible === 'liste') {
        /**
         * Une liste du carnet — et, à défaut, le collage d'autrefois.
         *
         * Le collage n'est plus la source normale : il est repris dans une
         * liste dès l'enregistrement de la campagne, et la migration v12 a
         * fait le même travail sur celles qui existaient déjà. La branche
         * reste pour une campagne dont la liste a été supprimée entre-temps
         * — mieux vaut écrire aux adresses qu'on a que refuser sèchement.
         */
        $liste_id = (string) ($campagne['liste_id'] ?? '');
        $liste = $liste_id !== '' ? carnet_liste($liste_id) : null;
        if ($liste && $liste['proprietaire_id'] === $auteur['id']) {
            $lignes = carnet_destinataires($liste_id);
        } else {
            $lignes = adresses_du_texte((string) ($campagne['liste'] ?? ''));
        }
    } elseif ($cible === 'mes-invites') {
        $s = db()->prepare("SELECT DISTINCT u.email, u.nom
                            FROM badges b
                            JOIN decors d ON d.id = b.decor_id
                            JOIN utilisateurs u ON u.id = b.utilisateur_id
                            WHERE d.auteur_id = ? AND u.email <> '' AND u.suspendu = 0");
        $s->execute([$auteur['id']]);
        foreach ($s->fetchAll() as $r) {
            $lignes[mb_strtolower((string) $r['email'])] = (string) $r['nom'];
        }
    } else {
        $sql = "SELECT email, nom FROM utilisateurs WHERE suspendu = 0 AND email <> ''";
        $args = [];
        if ($cible === 'organisateurs') {
            $sql .= " AND role = 'partenaire'";
        } elseif ($cible === 'participants') {
            $sql .= " AND role = 'participant'";
        } elseif (in_array($cible, ['lome', 'cotonou', 'abidjan'], true)) {
            $sql .= ' AND ville = ?';
            $args = [$cible];
        }
        $s = db()->prepare($sql);
        $s->execute($args);
        foreach ($s->fetchAll() as $r) {
            $lignes[mb_strtolower((string) $r['email'])] = (string) $r['nom'];
        }
    }

    // Les désabonnés sortent EN DERNIER, une fois la liste constituée :
    // ainsi le compte affiché est bien celui des gens qu'on écrira.
    foreach (array_keys($lignes) as $email) {
        if (desabonne($email)) {
            unset($lignes[$email]);
        }
    }
    return $lignes;
}

/** Combien de destinataires cette campagne toucherait, si elle partait maintenant. */
function regie_compter(array $campagne, array $auteur): int
{
    return count(regie_destinataires($campagne, $auteur));
}

/* ------------------------------------------------------------------ */
/* Les canaux d'un message                                             */
/* ------------------------------------------------------------------ */

/**
 * Les canaux cochés, tels qu'ils sont enregistrés.
 *
 * Une liste de cibles, chacune décrivant OÙ écrire — pas comment. Le
 * « comment » est le seul point qui change d'une plateforme à l'autre, et
 * il vit dans `canaux.php`.
 *
 * @return list<array{canal:string, canal_id?:string, destination_id?:string, direct?:bool, modele?:string}>
 */
function regie_canaux(array $campagne): array
{
    $lu = json_decode((string) ($campagne['canaux'] ?? ''), true);
    if (!is_array($lu)) {
        // Une campagne d'avant les canaux est une campagne e-mail : c'est
        // le seul chemin qui existait, et sa liste est déjà figée.
        return [['canal' => 'email']];
    }
    $out = [];
    foreach ($lu as $c) {
        if (is_array($c) && isset($c['canal']) && is_string($c['canal'])) {
            $out[] = $c;
        }
    }
    return $out ?: [['canal' => 'email']];
}

/** Le libellé d'une cible, pour l'écran et pour l'historique. */
function regie_canal_libelle(array $cible): string
{
    $genre = (string) ($cible['canal'] ?? '');
    if ($genre === 'email') {
        return 'E-mail';
    }
    if ($genre === 'push') {
        return 'Notifications navigateur';
    }
    $nom = CANAUX_GENRES[$genre]['nom'] ?? $genre;
    if (!empty($cible['destination_id']) && ($d = destination_par_id((string) $cible['destination_id']))) {
        return $nom . ' — ' . $d['nom'];
    }
    return $nom . ' — tête-à-tête';
}

/**
 * Ce qu'une cible touche : une ligne par envoi à préparer.
 *
 * @return list<array{cible:string, nom:string}>
 */
function regie_cibles_du_canal(array $cible, array $campagne, array $auteur): array
{
    $genre = (string) ($cible['canal'] ?? '');

    if ($genre === 'email') {
        $out = [];
        foreach (regie_destinataires($campagne, $auteur) as $email => $nom) {
            $out[] = ['cible' => (string) $email, 'nom' => (string) $nom];
        }
        return $out;
    }

    if ($genre === 'push') {
        /**
         * Une seule ligne, et non une par appareil.
         *
         * La diffusion push a sa propre mécanique — chiffrement par
         * abonnement, nettoyage des abonnements morts — et la recopier
         * ici la ferait diverger. La ligne déclenche `push_diffuser` ;
         * le segment visé est la cible de la campagne.
         */
        return [['cible' => (string) $campagne['cible'], 'nom' => 'Notifications navigateur']];
    }

    /* Une chaîne ou un groupe : une seule ligne, quel que soit le monde
       qui la lit. C'est ce qui fait qu'une publication ne coûte qu'un
       message de quota, et c'est exact — le message part une fois. */
    if (!empty($cible['destination_id'])) {
        $d = destination_par_id((string) $cible['destination_id']);
        return $d ? [['cible' => (string) $d['cible'], 'nom' => (string) $d['nom']]] : [];
    }

    /* Le tête-à-tête : une ligne par personne joignable. */
    $canal_id = (string) ($cible['canal_id'] ?? '');
    if ($canal_id === '') {
        return [];
    }
    $out = [];
    foreach (abonnes_canal($canal_id) as $a) {
        $out[] = ['cible' => (string) $a['cible'], 'nom' => (string) ($a['nom'] ?? '')];
    }
    return $out;
}

/**
 * La portée d'un message : par canal, en destinations et en personnes.
 *
 * Les deux nombres diffèrent et il faut les deux. Une chaîne de 4 210
 * abonnés est UNE destination ; quelqu'un qui est à la fois sur la chaîne
 * et abonné au bot recevra deux fois le même message. Annoncer la somme
 * des abonnés flatterait la portée d'un tiers.
 *
 * @return array{lignes: list<array{libelle:string, genre:string, n:int}>, destinations:int, payant:int}
 */
function regie_portee(array $campagne, array $auteur): array
{
    $lignes = [];
    $total = 0;
    $payant = 0;
    foreach (regie_canaux($campagne) as $cible) {
        $genre = (string) $cible['canal'];
        $n = count(regie_cibles_du_canal($cible, $campagne, $auteur));
        $lignes[] = ['libelle' => regie_canal_libelle($cible), 'genre' => $genre, 'n' => $n];
        $total += $n;
        if (!empty(CANAUX_GENRES[$genre]['payant'])) {
            $payant += $n;
        }
    }
    return ['lignes' => $lignes, 'destinations' => $total, 'payant' => $payant];
}

/**
 * Fige la liste : une ligne par destinataire, en attente.
 *
 * Appelé une seule fois, au passage en « prête ». Après quoi la liste ne
 * bouge plus : quelqu'un qui s'inscrit pendant l'envoi ne recevra pas une
 * campagne à moitié partie, et quelqu'un qui se désabonne sera écarté au
 * moment de l'envoi de sa ligne — pas avant, pas après.
 */
function regie_figer(string $campagne_id, array $auteur): int
{
    $c = campagne_email($campagne_id);
    if (!$c) {
        return 0;
    }
    db()->prepare('DELETE FROM envois_email WHERE campagne_id = ?')->execute([$campagne_id]);

    $ins = db()->prepare('INSERT INTO envois_email
                          (id, campagne_id, email, nom, jeton, statut, canal, cible, cree_le)
                          VALUES (?,?,?,?,?,?,?,?,?)');
    $n = 0;
    $now = maintenant();

    /**
     * Une ligne par envoi, tous canaux confondus.
     *
     * La file ne sait pas ce qu'est Telegram : elle porte un canal, une
     * cible, un état et un compteur de tentatives. C'est ce qui permet
     * d'ajouter une plateforme sans toucher aux lots, aux reprises ni au
     * quota — et de tout suivre sur un seul écran.
     */
    foreach (regie_canaux($c) as $cible_canal) {
        $genre = (string) $cible_canal['canal'];
        $canal_id = (string) ($cible_canal['canal_id'] ?? '');
        foreach (regie_cibles_du_canal($cible_canal, $c, $auteur) as $ligne) {
            $ins->execute([
                nouvel_id(), $campagne_id,
                // `email` reste la colonne du destinataire e-mail : le
                // désabonnement, lui, se juge sur une adresse et sur rien
                // d'autre. Une cible Telegram n'y met rien.
                $genre === 'email' ? $ligne['cible'] : '',
                $ligne['nom'] ?: null,
                bin2hex(random_bytes(16)), 'attente',
                $genre,
                $genre === 'email' ? null : ($canal_id . '|' . $ligne['cible']),
                $now,
            ]);
            $n++;
        }
    }
    db()->prepare('UPDATE campagnes_email SET destinataires = ?, envoyes = 0, echecs = 0, maj_le = ? WHERE id = ?')
        ->execute([$n, $now, $campagne_id]);
    return $n;
}

/* ------------------------------------------------------------------ */
/* L'envoi                                                             */
/* ------------------------------------------------------------------ */

/**
 * Envoie un lot, et rend ce qu'il reste à faire.
 *
 * @return array{envoyes: int, echecs: int, restants: int, fini: bool, message: string}
 */
function regie_envoyer_lot(string $campagne_id, int $lot = REGIE_LOT): array
{
    $c = campagne_email($campagne_id);
    if (!$c) {
        return ['envoyes' => 0, 'echecs' => 0, 'restants' => 0, 'fini' => true,
                'message' => 'Campagne introuvable.'];
    }
    /**
     * Le transport e-mail ne bloque plus que les lignes e-mail.
     *
     * Un message qui part sur Telegram n'a que faire du serveur SMTP, et
     * refuser tout le lot pour cette raison bloquait un canal qui marche
     * à cause d'un autre qu'on n'utilise pas.
     */
    $courriel_ok = courriel_branche();

    $s = db()->prepare("SELECT * FROM envois_email
                        WHERE campagne_id = ? AND statut = 'attente' AND tentatives < ?
                        ORDER BY cree_le LIMIT " . max(1, min(200, $lot)));
    $s->execute([$campagne_id, REGIE_TENTATIVES]);
    $paquet = $s->fetchAll();

    $maj = db()->prepare('UPDATE envois_email SET statut = ?, message = ?, tentatives = ?, envoye_le = ? WHERE id = ?');
    $envoyes = $echecs = $repris = 0;

    /**
     * Une seule conversation SMTP pour tout le lot.
     *
     * Et surtout : elle sait dire si un refus vise CE destinataire ou toute
     * la conversation. Le premier cas se note et on enchaîne ; le second
     * remet en attente ce qui reste, plutôt que de le perdre.
     */
    $session = new SessionCourriel();

    foreach ($paquet as $e) {
        $genre = (string) ($e['canal'] ?: 'email');
        // Quelqu'un a pu se désabonner depuis que la liste a été figée.
        // Le désabonnement porte sur une ADRESSE : il ne dit rien d'un
        // abonné Telegram, qui se retire, lui, par /stop.
        if ($genre === 'email' && desabonne((string) $e['email'])) {
            $maj->execute(['desabonne', null, (int) $e['tentatives'], maintenant(), $e['id']]);
            continue;
        }
        if ($genre === 'email' && $session->rompue()) {
            // Le transport est tombé : on ne touche pas au reste du lot,
            // il repartira au passage suivant, intact.
            break;
        }

        $tentatives = (int) $e['tentatives'] + 1;
        $r = regie_remettre($c, $e, $session, $courriel_ok);

        if ($r['ok']) {
            $maj->execute(['envoye', null, $tentatives, maintenant(), $e['id']]);
            $envoyes++;
            continue;
        }
        // À reprendre tant qu'il reste des essais ; sinon c'est un échec.
        $reprendre = !empty($r['reprendre']) && $tentatives < REGIE_TENTATIVES;
        $maj->execute([$reprendre ? 'attente' : 'echec', $r['message'], $tentatives, maintenant(), $e['id']]);
        $reprendre ? $repris++ : $echecs++;
    }

    $session->fermer();

    db()->prepare('UPDATE campagnes_email SET envoyes = envoyes + ?, echecs = echecs + ?, maj_le = ? WHERE id = ?')
        ->execute([$envoyes, $echecs, maintenant(), $campagne_id]);

    $restants = (int) (function () use ($campagne_id) {
        $q = db()->prepare("SELECT COUNT(*) FROM envois_email
                            WHERE campagne_id = ? AND statut = 'attente' AND tentatives < ?");
        $q->execute([$campagne_id, REGIE_TENTATIVES]);
        return $q->fetchColumn();
    })();

    if ($restants === 0 && $c['statut'] !== 'envoye') {
        db()->prepare("UPDATE campagnes_email SET statut = 'envoye', envoye_le = ?, maj_le = ? WHERE id = ?")
            ->execute([maintenant(), maintenant(), $campagne_id]);
    }

    return [
        'envoyes' => $envoyes, 'echecs' => $echecs, 'restants' => $restants,
        'fini' => $restants === 0,
        'repris' => $repris,
        'message' => sprintf(
            '%d parti(s), %d échec(s), %s%d restant(s).',
            $envoyes, $echecs, $repris ? $repris . ' à reprendre, ' : '', $restants
        ),
    ];
}

/**
 * Remet UNE ligne de la file, par le canal qu'elle porte.
 *
 * Le seul endroit où le produit sait qu'il existe plusieurs plateformes.
 * Tout ce qui précède — figer, lotir, reprendre, compter — les ignore.
 *
 * @return array{ok: bool, message: string, reprendre: bool}
 */
function regie_remettre(array $c, array $e, SessionCourriel $session, bool $courriel_ok): array
{
    $genre = (string) ($e['canal'] ?: 'email');

    if ($genre === 'email') {
        if (!$courriel_ok) {
            return ['ok' => false, 'reprendre' => true,
                    'message' => 'Le transport e-mail est éteint : réglez-le avant d’envoyer.'];
        }
        return courriel_mis_en_page(
            (string) $e['email'],
            (string) ($e['nom'] ?: ''),
            (string) $c['sujet'],
            (string) $c['titre'],
            regie_corps_pour($c, $e),
            (string) ($c['lien'] ?: ''),
            (string) ($c['lien_libelle'] ?: 'En savoir plus'),
            entetes_desabonnement((string) $e['jeton']),
            $session
        ) + ['reprendre' => false];
    }

    if ($genre === 'push') {
        /**
         * La diffusion push garde sa mécanique : une ligne de file, mais
         * un envoi à tous les appareils du segment. Recopier ici son
         * chiffrement et son nettoyage les ferait diverger.
         */
        $segment = (string) ($e['cible'] ?: 'mes-invites');
        $segment = str_contains($segment, '|') ? explode('|', $segment, 2)[1] : $segment;
        $abonnements = push_destinataires($segment, (string) ($c['auteur_id'] ?? ''));
        if (!$abonnements) {
            return ['ok' => false, 'reprendre' => false, 'message' => 'Aucun appareil abonné.'];
        }
        $r = push_diffuser($abonnements, [
            'titre' => (string) $c['titre'],
            'corps' => (string) $c['corps'],
            'lien' => (string) ($c['lien'] ?: ''),
        ]);
        return ['ok' => ($r['envoyes'] ?? 0) > 0, 'reprendre' => false,
                'message' => sprintf('%d appareil(s), %d échec(s).', $r['envoyes'] ?? 0, $r['echecs'] ?? 0)];
    }

    /* Telegram, WhatsApp : la cible porte « canal_id|cible ». */
    [$canal_id, $cible] = array_pad(explode('|', (string) $e['cible'], 2), 2, '');
    $canal = $canal_id !== '' ? canal_par_id($canal_id) : null;
    if (!$canal) {
        return ['ok' => false, 'reprendre' => false, 'message' => 'Ce canal a été débranché.'];
    }

    // Le rythme propre à la plateforme : Telegram compte en messages par
    // seconde, et refuse tout le reste du lot quand on le dépasse.
    $pause = CANAUX_RYTHME[$genre] ?? 0;
    if ($pause > 0) {
        usleep($pause);
    }

    return canal_remettre($canal, $cible, [
        'titre' => (string) $c['titre'],
        'corps' => (string) $c['corps'],
        'lien' => (string) ($c['lien'] ?: ''),
        'libelle' => (string) ($c['lien_libelle'] ?: 'Ouvrir'),
        'modele' => regie_modele_whatsapp($c),
    ]);
}

/** Le modèle WhatsApp choisi pour cette campagne, s'il y en a un. */
function regie_modele_whatsapp(array $campagne): string
{
    foreach (regie_canaux($campagne) as $cible) {
        if (($cible['canal'] ?? '') === 'whatsapp' && !empty($cible['modele'])) {
            return (string) $cible['modele'];
        }
    }
    return '';
}

/**
 * Le corps du message, avec le pied obligatoire.
 *
 * Le lien de désabonnement n'est pas une option qu'on ajoute si on y
 * pense : il est collé ici, dans la seule fonction par laquelle passe
 * chaque message de la régie. Un message marketing sans ce lien est
 * illégal en Europe et signalé partout ailleurs.
 */
function regie_corps_pour(array $campagne, array $envoi): string
{
    return rtrim((string) $campagne['corps'])
        . "\n\n---\n"
        . 'Vous recevez ce message parce que vous avez un compte ou un badge Wakabi Boost. '
        . 'Pour ne plus jamais en recevoir : ' . url_desabonnement((string) $envoi['jeton']);
}

function url_desabonnement(string $jeton): string
{
    return base_url() . '/index.php?p=desabonnement&j=' . rawurlencode($jeton);
}

function envoi_par_jeton(string $jeton): ?array
{
    $s = db()->prepare('SELECT * FROM envois_email WHERE jeton = ?');
    $s->execute([$jeton]);
    return $s->fetch() ?: null;
}

/* ------------------------------------------------------------------ */
/* Le quota                                                            */
/* ------------------------------------------------------------------ */

/** Les destinataires servis ce mois-ci par ce compte. */
function emails_du_mois(string $auteur_id): int
{
    $debut = gmdate('Y-m-01\T00:00:00\Z');
    $s = db()->prepare("SELECT COUNT(*) FROM envois_email e
                        JOIN campagnes_email c ON c.id = e.campagne_id
                        WHERE c.auteur_id = ? AND e.statut = 'envoye' AND e.envoye_le >= ?");
    $s->execute([$auteur_id, $debut]);
    return (int) $s->fetchColumn();
}

/**
 * Le quota d'envoi tient-il encore ?
 *
 * Opposé au moment de FIGER la liste, pas à chaque message : dire « il
 * vous reste 40 envois » à quelqu'un qui vient d'écrire à deux mille
 * personnes, message par message, serait une punition, pas une limite.
 *
 * @return array{ok: bool, max: int, utilises: int, reste: int, message: string}
 */
function quota_emails(array $u, int $demandes = 0): array
{
    $max = quota($u, 'emails_par_mois');
    $utilises = emails_du_mois((string) $u['id']);
    $reste = $max < 0 ? -1 : max(0, $max - $utilises);

    if ($max < 0) {
        return ['ok' => true, 'max' => -1, 'utilises' => $utilises, 'reste' => -1, 'message' => ''];
    }
    if ($max === 0) {
        return ['ok' => false, 'max' => 0, 'utilises' => $utilises, 'reste' => 0,
                'message' => 'Votre offre ne comprend pas la régie e-mail.'];
    }
    if ($demandes > $reste) {
        return ['ok' => false, 'max' => $max, 'utilises' => $utilises, 'reste' => $reste,
                'message' => sprintf(
                    'Cette campagne toucherait %d personnes, et il vous reste %d envois ce mois-ci '
                    . '(offre %s : %d par mois). Réduisez la cible, ou attendez le 1er du mois.',
                    $demandes, $reste, formule_libelle($u['formule'] ?? null), $max
                )];
    }
    return ['ok' => true, 'max' => $max, 'utilises' => $utilises, 'reste' => $reste, 'message' => ''];
}

/** Le compte des lignes encore en attente, toutes campagnes confondues. */
function regie_en_attente_denvoi(): int
{
    return (int) db()->query("SELECT COUNT(*) FROM envois_email WHERE statut = 'attente'")->fetchColumn();
}
