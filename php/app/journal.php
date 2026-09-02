<?php
/**
 * Qui a fait quoi, et quand.
 *
 * Avec un seul administrateur, ce fichier ne sert à rien. Avec un
 * coordinateur, un éditeur et un scanner qui arbitrent chacun de leur
 * côté, c'est la première question qu'on se pose le jour où un décor a
 * disparu — et sans trace écrite, la réponse est « on ne sait pas », ce
 * qui abîme la confiance dans l'équipe entière.
 *
 * Trois décisions le rendent utile plutôt que décoratif :
 *
 *  - **Le nom de l'acteur est recopié**, pas seulement son identifiant. Un
 *    compte supprimé ne doit pas effacer ce qu'il a fait ; c'est même à ce
 *    moment-là qu'on a le plus besoin de le savoir.
 *  - **Le titre de l'objet aussi.** « Décor 4f2a-… supprimé » n'apprend
 *    rien ; « Soirée Akwaba supprimée » se comprend d'un coup d'œil.
 *  - **On journalise les DÉCISIONS, pas les lectures.** Un journal qui
 *    note chaque page vue devient illisible en une semaine, et l'on n'y
 *    retrouve plus jamais la ligne qui compte.
 */

declare(strict_types=1);

/**
 * Les actions suivies, et leur libellé.
 *
 * La table sert de deux façons : elle traduit le code en français à
 * l'écran, et elle documente ce qui est réellement suivi — une action
 * absente d'ici n'est journalisée nulle part.
 */
const JOURNAL_ACTIONS = [
    'decor.publie'        => 'a publié un décor',
    'decor.refuse'        => 'a refusé un décor',
    'decor.corrections'   => 'a renvoyé un décor en corrections',
    'decor.archive'       => 'a archivé un décor',
    'decor.supprime'      => 'a supprimé un décor',
    'article.publie'      => 'a publié un article',
    'article.refuse'      => 'a refusé un article',
    'article.corrections' => 'a renvoyé un article à son auteur',
    'article.supprime'    => 'a supprimé un article',
    'campagne.envoyee'    => 'a envoyé une campagne e-mail',
    'compte.cree'         => 'a créé un compte',
    'compte.role'         => 'a changé le rôle ou l’offre d’un compte',
    'compte.suspendu'     => 'a suspendu ou réactivé un compte',
    'compte.supprime'     => 'a supprimé un compte',
    'abonnement.paye'     => 'a enregistré un paiement',
    'abonnement.echu'     => 'abonnement arrivé à terme',
    'sauvegarde.restauree' => 'a restauré une sauvegarde',
    'reglages.modifies'   => 'a modifié les réglages',
];

/** Combien de temps on garde le journal. Au-delà, il ne sert plus qu'à peser. */
const JOURNAL_JOURS = 365;

/**
 * Écrit une ligne. Ne lève jamais.
 *
 * Un journal qui fait tomber l'action qu'il journalise serait pire que pas
 * de journal du tout : on perdrait la publication ET la trace. En cas de
 * problème — table absente sur une installation à moitié migrée, disque
 * plein — on laisse passer en silence.
 */
function journal_ecrire(
    ?array $acteur,
    string $action,
    ?string $objet_type = null,
    ?string $objet_id = null,
    ?string $objet_titre = null,
    ?string $detail = null
): void {
    try {
        db()->prepare('INSERT INTO journal
            (id, acteur_id, acteur_nom, acteur_role, action, objet_type, objet_id,
             objet_titre, detail, cree_le) VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([
                nouvel_id(),
                $acteur['id'] ?? null,
                $acteur['nom'] ?? 'Automatique',
                $acteur['role'] ?? null,
                $action,
                $objet_type,
                $objet_id,
                $objet_titre !== null ? mb_substr($objet_titre, 0, 180) : null,
                $detail !== null ? mb_substr($detail, 0, 500) : null,
                maintenant(),
            ]);
    } catch (Throwable) {
        // Voir le commentaire ci-dessus : on ne fait jamais tomber l'action.
    }
}

/** Le libellé français d'une action, ou son code si on ne le connaît pas. */
function journal_libelle(string $action): string
{
    return JOURNAL_ACTIONS[$action] ?? $action;
}

/**
 * Une page du journal, filtrable par acteur et par action.
 *
 * Les deux filtres qui servent vraiment : « qu'a fait cette personne ? »
 * quand on a un doute, et « qui a supprimé des décors ? » quand il en
 * manque un.
 */
function journal_lire(int $page = 1, string $acteur = '', string $action = '', int $par_page = 50): array
{
    [$where, $args] = journal_filtre($acteur, $action);
    $decalage = max(0, ($page - 1) * $par_page);
    $s = db()->prepare("SELECT * FROM journal $where ORDER BY cree_le DESC
                        LIMIT $par_page OFFSET $decalage");
    $s->execute($args);
    return $s->fetchAll();
}

function journal_combien(string $acteur = '', string $action = ''): int
{
    [$where, $args] = journal_filtre($acteur, $action);
    $s = db()->prepare("SELECT COUNT(*) FROM journal $where");
    $s->execute($args);
    return (int) $s->fetchColumn();
}

/** Le `WHERE`, écrit une fois pour la page et pour le compte. */
function journal_filtre(string $acteur, string $action): array
{
    $ou = [];
    $args = [];
    if (trim($acteur) !== '') {
        $ou[] = 'acteur_id = ?';
        $args[] = trim($acteur);
    }
    if ($action !== '' && isset(JOURNAL_ACTIONS[$action])) {
        $ou[] = 'action = ?';
        $args[] = $action;
    }
    return [$ou ? 'WHERE ' . implode(' AND ', $ou) : '', $args];
}

/** Les personnes qui apparaissent au journal — pour proposer un filtre utile. */
function journal_acteurs(): array
{
    return db()->query('SELECT acteur_id, acteur_nom, COUNT(*) AS n FROM journal
                        WHERE acteur_id IS NOT NULL
                        GROUP BY acteur_id, acteur_nom ORDER BY n DESC LIMIT 40')->fetchAll();
}

/** Efface ce qui a plus d'un an. Appelé par le passage quotidien. */
function journal_elaguer(int $jours = JOURNAL_JOURS): int
{
    $s = db()->prepare('DELETE FROM journal WHERE cree_le < ?');
    $s->execute([maintenant(time() - max(1, $jours) * 86400)]);
    return $s->rowCount();
}
