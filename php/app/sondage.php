<?php
/**
 * Le sondage du lendemain : trois questions, et pas dix.
 *
 * Le rappel « Merci d'être venu » part déjà tout seul, quinze heures après
 * l'événement. Trois questions au bout de son lien, et l'organisateur a la
 * note de sa soirée pendant qu'elle est encore fraîche — au lieu de la
 * deviner l'an prochain, au moment de revendre.
 *
 * QUATRE DÉCISIONS
 *
 *  1. **Le jeton de l'envoi fait la clé.** Il existe déjà : il sert au
 *     désabonnement et au pixel d'ouverture. Il désigne une personne et
 *     une seule, sans qu'elle ait de compte à créer. `envoi_id` est UNIQUE
 *     dans la table : une réponse par jeton, la première comptée. Un lien
 *     transféré à toute la famille ne vote pas six fois.
 *
 *  2. **Anonyme à l'affichage, pas en base.** L'organisateur voit les
 *     notes sans les noms — c'est la condition pour qu'on réponde
 *     franchement. La base, elle, sait relier une note à « venu » ou « pas
 *     venu » : c'est ce qui rend la mesure utile l'an prochain, et c'est
 *     ce que `venu` recopie à la réponse.
 *
 *  3. **Trois questions, jamais dix.** Une note, une intention, un mot
 *     libre. Un formulaire de dix questions au lendemain d'une soirée ne
 *     se remplit pas, et fait baisser le taux de réponse de tous les
 *     suivants.
 *
 *  4. **Le lien du sondage ne part que sur l'e-mail.** C'est le seul canal
 *     dont chaque ligne s'adresse à UNE personne et porte son propre
 *     jeton. Une publication dans un salon Telegram porterait un lien
 *     unique, partagé : le premier arrivé répondrait pour tout le monde,
 *     et la décision 1 ne serait plus vraie.
 */

declare(strict_types=1);

/** Les cinq notes, du haut vers le bas, comme on les affiche. */
const SONDAGE_NOTES = [
    5 => 'Excellente',
    4 => 'Bonne',
    3 => 'Correcte',
    2 => 'Décevante',
    1 => 'Mauvaise',
];

/** Combien de mots libres on montre à l'écran. Le PDF les porte tous. */
const SONDAGE_MOTS = 40;

function url_sondage(string $jeton): string
{
    return base_url() . '/index.php?p=sondage&j=' . rawurlencode($jeton);
}

/**
 * Ce message porte-t-il les trois questions ?
 *
 * Deux façons de le dire : la campagne l'a demandé explicitement, ou c'est
 * le rappel du lendemain d'un décor dont le sondage est ouvert. La seconde
 * est celle qui compte : elle laisse l'organisateur ouvrir son sondage
 * APRÈS avoir posé ses rappels, ce qui est l'ordre dans lequel on y pense.
 */
function sondage_actif(array $campagne, ?array $decor = null): bool
{
    if ((int) ($campagne['sondage'] ?? 0) === 1) {
        return true;
    }
    if ((string) ($campagne['rappel'] ?? '') !== 'merci') {
        return false;
    }
    $decor ??= ($campagne['decor_id'] ?? null) ? decor_par_id((string) $campagne['decor_id']) : null;
    return $decor !== null && (int) ($decor['sondage'] ?? 0) === 1;
}

/**
 * Le contexte d'une réponse, ouvert par le jeton de l'envoi.
 *
 * Tout est vérifié ici, et nulle part ailleurs : que le jeton existe, que
 * la campagne porte bien le sondage, que le décor l'a ouvert. Un jeton
 * inconnu ne dit pas qu'il est inconnu — il rend `null`, et la page
 * affiche la même chose que pour un sondage fermé.
 *
 * @return array{envoi:array, campagne:array, decor:array, deja:?array}|null
 */
function sondage_contexte(string $jeton): ?array
{
    $jeton = trim($jeton);
    if ($jeton === '') {
        return null;
    }
    $e = envoi_par_jeton($jeton);
    if (!$e) {
        return null;
    }
    $c = campagne_email((string) $e['campagne_id']);
    if (!$c) {
        return null;
    }
    $d = ($c['decor_id'] ?? null) ? decor_par_id((string) $c['decor_id']) : null;
    if (!$d || !sondage_actif($c, $d)) {
        return null;
    }

    $s = db()->prepare('SELECT * FROM reponses_sondage WHERE envoi_id = ?');
    $s->execute([(string) $e['id']]);

    return ['envoi' => $e, 'campagne' => $c, 'decor' => $d, 'deja' => $s->fetch() ?: null];
}

/**
 * Enregistre une réponse. La première gagne.
 *
 * L'unicité est tenue par la CONTRAINTE de la table, pas par le SELECT qui
 * précède : deux onglets ouverts sur le même lien arriveraient sinon dans
 * l'intervalle. On insère, et on lit l'échec comme « quelqu'un a répondu
 * avant » — ce qui est exactement ce qu'il veut dire.
 *
 * @return array{ok:bool, message:string}
 */
function sondage_repondre(array $ctx, array $reponse): array
{
    $note = (int) ($reponse['note'] ?? 0);
    $revient = (string) ($reponse['revient'] ?? '');
    $mot = trim((string) ($reponse['mot'] ?? ''));

    if (!isset(SONDAGE_NOTES[$note])) {
        return ['ok' => false, 'message' => 'Choisissez une note, de 1 à 5.'];
    }
    if (!in_array($revient, ['oui', 'non', ''], true)) {
        $revient = '';
    }

    /**
     * « Est-il venu ? » se lit MAINTENANT, et se recopie.
     *
     * Le badge peut être supprimé, le décor archivé : la question « est-ce
     * que ceux qui sont venus ont mieux noté ? » doit rester répondable
     * l'an prochain, quand on prépare l'édition suivante.
     */
    $venu = compter(
        'SELECT COUNT(*) AS n FROM badges b
         JOIN utilisateurs u ON u.id = b.utilisateur_id
         WHERE b.decor_id = ? AND b.scanne_le IS NOT NULL AND LOWER(u.email) = LOWER(?)',
        [(string) $ctx['decor']['id'], (string) $ctx['envoi']['email']]
    ) > 0 ? 1 : 0;

    try {
        db()->prepare('INSERT INTO reponses_sondage
            (id, envoi_id, campagne_id, decor_id, note, revient, mot, venu, cree_le)
            VALUES (?,?,?,?,?,?,?,?,?)')
            ->execute([
                nouvel_id(), (string) $ctx['envoi']['id'], (string) $ctx['campagne']['id'],
                (string) $ctx['decor']['id'], $note, $revient ?: null,
                mb_substr($mot, 0, 600) ?: null, $venu, maintenant(),
            ]);
    } catch (PDOException) {
        return ['ok' => false, 'message' => 'Une réponse a déjà été donnée avec ce lien.'];
    }
    return ['ok' => true, 'message' => 'Merci : votre réponse est enregistrée.'];
}

/**
 * Ce que les gens en ont pensé, sur la portée du rapport.
 *
 * Le dénominateur du taux de réponse est le nombre de messages de sondage
 * réellement PARTIS : c'est la seule population à qui la question a été
 * posée. Prendre les destinataires programmés donnerait un taux qui baisse
 * quand le serveur du destinataire tousse, ce qui n'apprend rien sur la
 * soirée.
 *
 * @return array{reponses:int, partis:int, taux:?float, moyenne:?float,
 *                revient:?float, distribution:array<int,int>, mots:list<array>}
 */
function rapport_sondage(array $portee): array
{
    [$ou, $args] = match ($portee['cle']) {
        'decor' => [' AND r.decor_id = ?', [(string) $portee['decor']['id']]],
        'campagne' => [' AND r.campagne_id = ?', [(string) $portee['campagne']['id']]],
        'organisateur' => [' AND r.decor_id IN (SELECT id FROM decors WHERE auteur_id = ?)',
                           [(string) $portee['auteur_id']]],
        default => ['', []],
    };

    $vide = ['reponses' => 0, 'partis' => 0, 'taux' => null, 'moyenne' => null,
             'revient' => null, 'distribution' => [], 'mots' => []];

    $s = db()->prepare('SELECT COUNT(*) AS n, AVG(note) AS moyenne,
                               SUM(CASE WHEN revient = \'oui\' THEN 1 ELSE 0 END) AS oui,
                               SUM(CASE WHEN revient IS NULL OR revient = \'\' THEN 0 ELSE 1 END) AS dits
                        FROM reponses_sondage r WHERE 1 = 1' . $ou);
    $s->execute($args);
    $t = $s->fetch() ?: [];
    $n = (int) ($t['n'] ?? 0);
    if ($n === 0) {
        return $vide;
    }

    /* Les messages de sondage partis : le dénominateur du taux. */
    [$ou_c, $args_c] = match ($portee['cle']) {
        'decor' => [' AND c.decor_id = ?', [(string) $portee['decor']['id']]],
        'campagne' => [' AND c.id = ?', [(string) $portee['campagne']['id']]],
        'organisateur' => [' AND c.auteur_id = ?', [(string) $portee['auteur_id']]],
        default => ['', []],
    };
    $partis = compter(
        "SELECT COUNT(*) AS n FROM envois_email e
         JOIN campagnes_email c ON c.id = e.campagne_id
         JOIN decors d ON d.id = c.decor_id
         WHERE e.canal = 'email' AND e.statut = 'envoye'
           AND (c.sondage = 1 OR (c.rappel = 'merci' AND d.sondage = 1))" . $ou_c,
        $args_c
    );

    $d = db()->prepare('SELECT note, COUNT(*) AS n FROM reponses_sondage r
                        WHERE 1 = 1' . $ou . ' GROUP BY note');
    $d->execute($args);
    $distribution = [];
    foreach (array_keys(SONDAGE_NOTES) as $note) {
        $distribution[$note] = 0;
    }
    foreach ($d->fetchAll() as $l) {
        $distribution[(int) $l['note']] = (int) $l['n'];
    }

    /**
     * Les mots, en entier et sans nom.
     *
     * C'est ce qui se lit en premier — bien avant la moyenne. On les rend
     * tous au PDF ; l'écran en montre quarante, parce qu'au-delà personne
     * ne fait défiler.
     */
    $m = db()->prepare("SELECT mot, note, venu, cree_le FROM reponses_sondage r
                        WHERE mot IS NOT NULL AND mot <> ''" . $ou . '
                        ORDER BY cree_le DESC');
    $m->execute($args);

    return [
        'reponses' => $n,
        'partis' => $partis,
        'taux' => $partis > 0 ? min(1.0, $n / $partis) : null,
        'moyenne' => $t['moyenne'] !== null ? round((float) $t['moyenne'], 1) : null,
        'revient' => (int) ($t['dits'] ?? 0) > 0
            ? (int) $t['oui'] / (int) $t['dits'] : null,
        'distribution' => $distribution,
        'mots' => $m->fetchAll(),
    ];
}
