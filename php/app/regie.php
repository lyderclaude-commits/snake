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
 * Les cibles, et qui a le droit de les viser.
 *
 * `equipe` : la base du guide, segmentée. `partenaire` : ses propres
 * invités, et une liste qu'il apporte. Un organisateur ne voit jamais la
 * base du guide — elle s'est constituée sur la promesse de nouvelles du
 * guide, et la louer la brûlerait en trois campagnes.
 */
const REGIE_CIBLES = [
    'mes-invites'   => ['Les invités de mes campagnes', 'partenaire'],
    'liste'         => ['Une liste que j’apporte', 'partenaire'],
    'tous'          => ['Tout le monde', 'equipe'],
    'organisateurs' => ['Les organisateurs', 'equipe'],
    'participants'  => ['Les participants', 'equipe'],
    'lome'          => ['Lomé', 'equipe'],
    'cotonou'       => ['Cotonou', 'equipe'],
    'abidjan'       => ['Abidjan', 'equipe'],
];

function regie_cibles_de(array $u): array
{
    $equipe = ($u['role'] ?? '') === 'equipe';
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
        (id, auteur_id, sujet, titre, corps, lien, lien_libelle, cible, liste, statut, cree_le, maj_le)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')
      ->execute([
          $id, $c['auteur_id'], $c['sujet'], $c['titre'], $c['corps'],
          $c['lien'] ?: null, $c['lien_libelle'] ?: null,
          $c['cible'], $c['liste'] ?: null, 'brouillon', $now, $now,
      ]);
    return $id;
}

function campagne_email_maj(string $id, array $c): void
{
    db()->prepare('UPDATE campagnes_email SET sujet = ?, titre = ?, corps = ?, lien = ?,
                   lien_libelle = ?, cible = ?, liste = ?, maj_le = ? WHERE id = ?')
        ->execute([
            $c['sujet'], $c['titre'], $c['corps'], $c['lien'] ?: null,
            $c['lien_libelle'] ?: null, $c['cible'], $c['liste'] ?: null, maintenant(), $id,
        ]);
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
    $role = ($acteur['role'] ?? '') === 'equipe' ? 'equipe' : 'partenaire';
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
         * Une liste apportée : une adresse par ligne, « Nom <adresse> »
         * accepté. On ne refuse pas une ligne mal formée, on la saute —
         * refuser tout le collage à cause d'une virgule oubliée ferait
         * recommencer une saisie de deux cents lignes.
         */
        foreach (preg_split('/[\r\n,;]+/', (string) $campagne['liste']) ?: [] as $ligne) {
            $ligne = trim($ligne);
            if ($ligne === '') {
                continue;
            }
            $nom = '';
            if (preg_match('/^(.*?)<([^>]+)>$/', $ligne, $m)) {
                $nom = trim($m[1], " \t\"'");
                $ligne = trim($m[2]);
            }
            if (filter_var($ligne, FILTER_VALIDATE_EMAIL)) {
                $lignes[mb_strtolower($ligne)] = $nom;
            }
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

    $ins = db()->prepare('INSERT INTO envois_email (id, campagne_id, email, nom, jeton, statut, cree_le)
                          VALUES (?,?,?,?,?,?,?)');
    $n = 0;
    $now = maintenant();
    foreach (regie_destinataires($c, $auteur) as $email => $nom) {
        $ins->execute([nouvel_id(), $campagne_id, $email, $nom ?: null, bin2hex(random_bytes(16)), 'attente', $now]);
        $n++;
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
    if (!courriel_branche()) {
        return ['envoyes' => 0, 'echecs' => 0, 'restants' => 0, 'fini' => false,
                'message' => 'Le transport e-mail est éteint : réglez-le avant d’envoyer.'];
    }

    $s = db()->prepare("SELECT * FROM envois_email WHERE campagne_id = ? AND statut = 'attente'
                        ORDER BY cree_le LIMIT " . max(1, min(200, $lot)));
    $s->execute([$campagne_id]);
    $paquet = $s->fetchAll();

    $maj = db()->prepare('UPDATE envois_email SET statut = ?, message = ?, envoye_le = ? WHERE id = ?');
    $envoyes = $echecs = 0;

    foreach ($paquet as $e) {
        // Quelqu'un a pu se désabonner depuis que la liste a été figée.
        if (desabonne((string) $e['email'])) {
            $maj->execute(['desabonne', null, maintenant(), $e['id']]);
            continue;
        }
        $r = courriel_mis_en_page(
            (string) $e['email'],
            (string) ($e['nom'] ?: ''),
            (string) $c['sujet'],
            (string) $c['titre'],
            regie_corps_pour($c, $e),
            (string) ($c['lien'] ?: ''),
            (string) ($c['lien_libelle'] ?: 'En savoir plus')
        );
        $maj->execute([$r['ok'] ? 'envoye' : 'echec', $r['ok'] ? null : $r['message'], maintenant(), $e['id']]);
        $r['ok'] ? $envoyes++ : $echecs++;
    }

    db()->prepare('UPDATE campagnes_email SET envoyes = envoyes + ?, echecs = echecs + ?, maj_le = ? WHERE id = ?')
        ->execute([$envoyes, $echecs, maintenant(), $campagne_id]);

    $restants = (int) (function () use ($campagne_id) {
        $q = db()->prepare("SELECT COUNT(*) FROM envois_email WHERE campagne_id = ? AND statut = 'attente'");
        $q->execute([$campagne_id]);
        return $q->fetchColumn();
    })();

    if ($restants === 0 && $c['statut'] !== 'envoye') {
        db()->prepare("UPDATE campagnes_email SET statut = 'envoye', envoye_le = ?, maj_le = ? WHERE id = ?")
            ->execute([maintenant(), maintenant(), $campagne_id]);
    }

    return [
        'envoyes' => $envoyes, 'echecs' => $echecs, 'restants' => $restants,
        'fini' => $restants === 0,
        'message' => sprintf('%d parti(s), %d échec(s), %d restant(s).', $envoyes, $echecs, $restants),
    ];
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
