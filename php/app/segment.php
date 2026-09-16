<?php
/**
 * Les segments : du rapport à la campagne, sans liste figée.
 *
 * Le rapport dit « 486 badges créés, 372 emportés ». Il manquait le clic
 * qui écrit à ces 114 personnes-là. C'est tout l'objet de ce fichier, et
 * il tient en trois décisions.
 *
 *  1. **Un segment n'est pas une liste : c'est une règle.** Il n'a donc
 *     pas de table à lui, seulement une clause de plus sur les badges d'un
 *     décor, rejouée à chaque ouverture. Quelqu'un qui emporte son badge
 *     ce matin sort tout seul du segment « ne l'a jamais téléchargé » ce
 *     midi ; une liste enregistrée la veille lui aurait écrit pour rien.
 *
 *  2. **« Correspondre » et « être joignable » sont deux nombres.** Une
 *     personne qui fabrique un badge sans créer de compte et sans accepter
 *     les notifications compte dans les statistiques de l'organisateur et
 *     dans aucun de ses envois. Annoncer 114 destinataires puis n'en
 *     toucher que 96 ferait douter de tous les autres chiffres du produit :
 *     l'écran affiche donc les deux, et nomme l'écart.
 *
 *  3. **Un segment ne rouvre aucune porte que la régie a fermée.** Les
 *     désabonnés sortent, les adresses jamais confirmées sortent, le quota
 *     se décompte comme pour n'importe quelle campagne. Un segment change
 *     À QUI l'on écrit, jamais ce qu'on a le droit d'envoyer.
 *
 * CE QU'UN SEGMENT NE SAIT PAS COMPTER
 *
 * Telegram et WhatsApp écrivent à un salon, ou à des abonnés du bot que
 * rien ne rattache à un badge : aucune colonne ne relie « Ama Kodjo, badge
 * K6249XUED8 » à un `chat_id`. Un segment ne les compte donc pas, et le
 * dit. Inventer un nombre ici serait exactement la faute que la règle 2
 * cherche à éviter.
 */

declare(strict_types=1);

/**
 * Les segments, dans l'ordre où on les propose.
 *
 * `ou` est une clause SQL sur `badges b` — jamais une chaîne venue de la
 * requête : le choix de l'écran est une CLÉ de cette table, et une clé
 * inconnue retombe sur le premier segment.
 */
const SEGMENTS = [
    'non-emporte' => [
        'nom' => 'A créé un badge, ne l’a jamais téléchargé',
        'aide' => 'Le plus rentable : ils ont commencé, il manque un clic',
        'ou' => 'b.telecharge_le IS NULL',
    ],
    'emporte-absent' => [
        'nom' => 'A téléchargé son badge, n’est pas venu',
        'aide' => 'Pour le prochain événement, ou pour comprendre',
        'ou' => 'b.telecharge_le IS NOT NULL AND b.scanne_le IS NULL',
    ],
    'venu' => [
        'nom' => 'Est venu',
        'aide' => 'Les remerciements, le sondage, l’invitation suivante',
        'ou' => 'b.scanne_le IS NOT NULL',
    ],
    'sans-ouverture' => [
        'nom' => 'N’a pas ouvert les messages de ce décor',
        'aide' => 'Une relance, sur un autre canal que l’e-mail',
        'ou' => 'b.utilisateur_id IS NOT NULL
                 AND EXISTS (SELECT 1 FROM envois_email se
                             JOIN campagnes_email sc ON sc.id = se.campagne_id
                             JOIN utilisateurs su ON su.id = b.utilisateur_id
                             WHERE sc.decor_id = b.decor_id AND se.canal = \'email\'
                               AND se.statut = \'envoye\'
                               AND LOWER(se.email) = LOWER(su.email))
                 AND NOT EXISTS (SELECT 1 FROM envois_email se
                             JOIN campagnes_email sc ON sc.id = se.campagne_id
                             JOIN utilisateurs su ON su.id = b.utilisateur_id
                             WHERE sc.decor_id = b.decor_id AND se.canal = \'email\'
                               AND se.ouvert_le IS NOT NULL
                               AND LOWER(se.email) = LOWER(su.email))',
    ],
    'tous' => [
        'nom' => 'Tous les invités de ce décor',
        'aide' => 'Sans condition',
        'ou' => '1 = 1',
    ],
];

/** La clé demandée, ou la première de la table. */
function segment_cle(string $cle): string
{
    return isset(SEGMENTS[$cle]) ? $cle : array_key_first(SEGMENTS);
}

function segment_nom(string $cle): string
{
    return (string) (SEGMENTS[segment_cle($cle)]['nom']);
}

/**
 * Le décor visé, et le droit de le viser.
 *
 * Même discipline que `rapport_portee()` : un identifiant venu de la
 * requête ne donne le décor d'un autre à personne, et le refus est
 * silencieux — on ne confirme pas à un curieux que le slug qu'il a essayé
 * existe.
 */
function segment_decor(array $u, string $reference): ?array
{
    $reference = trim($reference);
    if ($reference === '') {
        return null;
    }
    $d = decor_par_slug($reference) ?? decor_par_id($reference);
    if (!$d) {
        return null;
    }
    if (droit($u, 'decors_tous') || (string) ($d['auteur_id'] ?? '') === (string) $u['id']) {
        return $d;
    }
    return null;
}

/**
 * Les conditions de joignabilité, écrites une seule fois.
 *
 * Elles servent au comptage, à la liste et à l'export : trois endroits qui
 * doivent dire le même nombre. Recopiées, elles auraient divergé au
 * premier ajustement.
 *
 * @return array{email:string, push:string}
 */
function segment_joignable_sql(): array
{
    $email = "u.id IS NOT NULL AND u.email <> '' AND u.suspendu = 0
              AND NOT EXISTS (SELECT 1 FROM desabonnements dz WHERE dz.email = LOWER(u.email))";
    if (verification_exigee()) {
        $email .= " AND u.email_verifie_le IS NOT NULL AND u.email_verifie_le <> ''";
    }

    /**
     * Les notifications se rattachent à un COMPTE, pas à un badge.
     *
     * Un abonnement pris sous un badge sans compte ne porte que le décor
     * (`push.decor_id`, v18) : il est joignable pour « les invités de ce
     * décor », mais rien ne dit s'il a emporté son badge ou s'il est venu.
     * Le ranger dans un segment serait une invention ; il reste hors du
     * compte, et l'écran le dit.
     */
    $push = 'b.utilisateur_id IS NOT NULL
             AND EXISTS (SELECT 1 FROM push pz WHERE pz.utilisateur_id = b.utilisateur_id)';

    return ['email' => $email, 'push' => $push];
}

/**
 * Ce que pèse un segment, par personne et par canal.
 *
 * On compte des PERSONNES, pas des lignes de badge : quelqu'un qui refait
 * son badge depuis un autre téléphone en a deux, et l'annoncer comme deux
 * destinataires serait faux dès l'écran. Une personne est son compte quand
 * elle en a un, et son badge sinon.
 *
 * @return array{correspondent:int, joignables:int, email:int, push:int, hors:int}
 */
function segment_compte(string $cle, string $decor_id): array
{
    $s = SEGMENTS[segment_cle($cle)];
    $j = segment_joignable_sql();

    $sql = 'SELECT COUNT(*) AS correspondent,
                   SUM(par_email) AS par_email,
                   SUM(par_push) AS par_push,
                   SUM(CASE WHEN par_email + par_push > 0 THEN 1 ELSE 0 END) AS joignables
            FROM (SELECT COALESCE(b.utilisateur_id, b.jeton) AS personne,
                         MAX(CASE WHEN ' . $j['email'] . ' THEN 1 ELSE 0 END) AS par_email,
                         MAX(CASE WHEN ' . $j['push'] . ' THEN 1 ELSE 0 END) AS par_push
                  FROM badges b
                  LEFT JOIN utilisateurs u ON u.id = b.utilisateur_id
                  WHERE b.decor_id = ? AND (' . $s['ou'] . ')
                  GROUP BY COALESCE(b.utilisateur_id, b.jeton)) q';

    $st = db()->prepare($sql);
    $st->execute([$decor_id]);
    $r = $st->fetch() ?: [];

    $n = (int) ($r['correspondent'] ?? 0);
    $joignables = (int) ($r['joignables'] ?? 0);
    return [
        'correspondent' => $n,
        'joignables' => $joignables,
        'email' => (int) ($r['par_email'] ?? 0),
        'push' => (int) ($r['par_push'] ?? 0),
        'hors' => max(0, $n - $joignables),
    ];
}

/** Les cinq segments d'un décor, chacun avec ses nombres du moment. */
function segments_du_decor(string $decor_id): array
{
    $out = [];
    foreach (SEGMENTS as $cle => $s) {
        $out[$cle] = $s + ['cle' => $cle] + segment_compte($cle, $decor_id);
    }
    return $out;
}

/**
 * Les adresses d'un segment, prêtes pour la régie.
 *
 * Dédoublonnées par adresse en minuscules — c'est ainsi qu'un
 * désabonnement est rangé, et « Ama@… » ne doit pas rouvrir une porte
 * fermée.
 *
 * @return array<string, string>  adresse => nom
 */
function segment_emails(string $cle, string $decor_id): array
{
    $s = SEGMENTS[segment_cle($cle)];
    $j = segment_joignable_sql();

    $st = db()->prepare(
        'SELECT DISTINCT LOWER(u.email) AS email, u.nom AS nom
         FROM badges b
         JOIN utilisateurs u ON u.id = b.utilisateur_id
         WHERE b.decor_id = ? AND (' . $s['ou'] . ') AND ' . $j['email']
    );
    $st->execute([$decor_id]);

    $out = [];
    foreach ($st->fetchAll() as $r) {
        $out[(string) $r['email']] = (string) ($r['nom'] ?? '');
    }
    return $out;
}

/** Les comptes d'un segment : c'est par eux que les notifications passent. */
function segment_utilisateurs(string $cle, string $decor_id): array
{
    $s = SEGMENTS[segment_cle($cle)];
    $st = db()->prepare(
        'SELECT DISTINCT b.utilisateur_id AS id FROM badges b
         WHERE b.decor_id = ? AND b.utilisateur_id IS NOT NULL AND (' . $s['ou'] . ')'
    );
    $st->execute([$decor_id]);
    return array_map('strval', $st->fetchAll(PDO::FETCH_COLUMN));
}

/**
 * Les abonnements aux notifications d'un segment.
 *
 * Par paquets de cinq cents : SQLite refuse au-delà de mille paramètres
 * liés, et un décor de deux mille invités en dépasserait deux fois.
 */
function segment_push(string $cle, string $decor_id): array
{
    $comptes = segment_utilisateurs($cle, $decor_id);
    if (!$comptes) {
        return [];
    }
    $out = [];
    foreach (array_chunk($comptes, 500) as $paquet) {
        $trous = implode(',', array_fill(0, count($paquet), '?'));
        $st = db()->prepare("SELECT * FROM push WHERE utilisateur_id IN ($trous)");
        $st->execute($paquet);
        foreach ($st->fetchAll() as $r) {
            $out[] = $r;
        }
    }
    return $out;
}

/**
 * La liste exportable : une ligne par personne, joignable ou non.
 *
 * Les non-joignables y figurent EXPRÈS. C'est même la moitié de l'intérêt
 * du fichier : un organisateur qui voit dix-huit lignes sans adresse sait
 * qu'il lui manque dix-huit personnes, et peut aller les chercher
 * autrement — au téléphone, ou à la porte.
 */
function segment_liste(string $cle, string $decor_id, int $limite = 5000): array
{
    $s = SEGMENTS[segment_cle($cle)];
    $j = segment_joignable_sql();

    $st = db()->prepare(
        'SELECT b.jeton AS jeton, b.cree_le AS cree_le, b.telecharge_le AS telecharge_le,
                b.scanne_le AS scanne_le, u.nom AS nom, u.email AS email,
                CASE WHEN ' . $j['email'] . ' THEN 1 ELSE 0 END AS par_email,
                CASE WHEN ' . $j['push'] . ' THEN 1 ELSE 0 END AS par_push
         FROM badges b
         LEFT JOIN utilisateurs u ON u.id = b.utilisateur_id
         WHERE b.decor_id = ? AND (' . $s['ou'] . ')
         ORDER BY b.cree_le DESC
         LIMIT ' . max(1, $limite)
    );
    $st->execute([$decor_id]);
    return $st->fetchAll();
}

/** Le même segment, pour un tableur. */
function segment_csv(array $decor, string $cle, array $lignes): string
{
    $out = [];
    $out[] = ['Décor', (string) $decor['titre']];
    $out[] = ['Segment', segment_nom($cle)];
    $out[] = ['Édité le', date_fr(maintenant())];
    $out[] = [];
    $out[] = ['Nom', 'Adresse', 'Joignable par e-mail', 'Joignable par notification',
              'Badge créé le', 'Badge emporté le', 'Entré le'];

    foreach ($lignes as $l) {
        $out[] = [
            (string) ($l['nom'] ?? ''),
            (string) ($l['email'] ?? ''),
            (int) $l['par_email'] === 1 ? 'oui' : 'non',
            (int) $l['par_push'] === 1 ? 'oui' : 'non',
            date_fr((string) $l['cree_le']),
            $l['telecharge_le'] ? date_fr((string) $l['telecharge_le']) : 'jamais',
            $l['scanne_le'] ? date_fr((string) $l['scanne_le']) : 'pas venu',
        ];
    }
    return csv_rendu($out);
}

/** Le nom du fichier, lisible dans un dossier de téléchargements. */
function segment_nom_fichier(array $decor, string $cle): string
{
    return 'segment-' . preg_replace('/[^a-z0-9-]+/', '-', strtolower((string) $decor['slug']))
        . '-' . segment_cle($cle) . '-' . gmdate('Y-m-d') . '.csv';
}
