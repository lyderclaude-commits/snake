<?php
/**
 * Les rapports : ce qui est parti, et ce qui est arrivé.
 *
 * UNE RÈGLE COMMANDE TOUT CE FICHIER
 *
 * « Parti » et « reçu » ne sont pas le même chiffre, et chaque canal
 * s'arrête à un endroit différent de la route. Un SMTP qui répond 250 dit
 * qu'il ACCEPTE le message, pas qu'il l'a remis — le rebond peut venir une
 * heure plus tard, et sans boîte de retour relevée il ne nous revient
 * jamais. Un service de notifications qui répond 201 dit la même chose.
 * Telegram, lui, rend l'identifiant du message PUBLIÉ : c'est le seul
 * canal du produit dont « parti » vaut « remis ».
 *
 * Alors on n'écrit nulle part « reçu ». On écrit « parti », et à côté, en
 * toutes lettres, ce que « parti » prouve sur ce canal-là. Une colonne
 * vide vaut mieux qu'une colonne inventée : un rapport qu'on transmet à un
 * sponsor n'a qu'une chose à vendre, et c'est d'être exact.
 *
 * CE QU'UN RAPPORT NE MONTRE JAMAIS
 *
 * Les chiffres d'un autre. La portée est résolue une fois, dans
 * `rapport_portee()`, et un organisateur n'y obtient jamais qu'un décor
 * ou une campagne dont il est l'auteur. Les requêtes qui suivent ne
 * reçoivent plus d'identifiant venu de la requête HTTP : elles reçoivent
 * la portée déjà décidée.
 */

declare(strict_types=1);

/**
 * Les quatre canaux, dans leur ordre fixe, et ce que chacun prouve.
 *
 * `preuve` est la phrase affichée à côté du nombre de messages partis.
 * Elle n'est pas décorative : c'est elle qui empêche de lire « 7 979
 * partis » comme « 7 979 personnes prévenues ». `remise` ne vaut vrai que
 * lorsque la plateforme confirme la remise elle-même.
 */
const RAPPORT_CANAUX = [
    'email' => [
        'nom' => 'E-mail', 'puce' => 'mail', 'remise' => false,
        'preuve' => 'accepté par le serveur du destinataire',
        'lecture' => 'ouvertures',
    ],
    'push' => [
        'nom' => 'Notifications', 'puce' => 'web', 'remise' => false,
        'preuve' => 'accepté par le service de notifications',
        'lecture' => null,
    ],
    'telegram' => [
        'nom' => 'Telegram', 'puce' => 'telegram', 'remise' => true,
        'preuve' => 'publié dans la conversation',
        'lecture' => null,
    ],
    'whatsapp' => [
        'nom' => 'WhatsApp', 'puce' => 'whatsapp', 'remise' => false,
        'preuve' => 'accepté par Meta',
        'lecture' => null,
    ],
];

/** Les périodes toutes faites, dans l'ordre où on les propose. */
const RAPPORT_PERIODES = [
    'mois' => 'Ce mois-ci',
    'mois-dernier' => 'Le mois dernier',
    'annee' => 'Cette année',
    '12-mois' => '12 derniers mois',
    'libre' => 'Période libre',
];

/* ------------------------------------------------------------------ */
/* La période                                                          */
/* ------------------------------------------------------------------ */

/**
 * Les bornes d'une période, jamais renversées, jamais absurdes.
 *
 * La borne haute est EXCLUSIVE et tombe au lendemain à minuit : « du 1er
 * au 30 septembre » doit contenir ce qui s'est passé le 30 à 23 h 50, et
 * un `<= '2026-09-30'` comparé à un horodatage complet l'aurait perdu.
 * C'est le genre d'écart qu'on ne voit qu'en recomptant à la main.
 *
 * @return array{cle:string, du:string, au:string, debut:string, fin:string,
 *               libelle:string, jours:int, avant_debut:string, avant_fin:string}
 */
function rapport_periode(string $cle = '', string $du = '', string $au = ''): array
{
    $jour = static fn(string $s): string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) ? $s : '';
    $cle = isset(RAPPORT_PERIODES[$cle]) ? $cle : ($jour($du) !== '' ? 'libre' : 'mois');
    $aujourdhui = gmdate('Y-m-d');

    // Le premier du mois se calcule sur le PREMIER du mois courant, jamais
    // sur aujourd'hui : « -1 month » un 31 mars rend le 3 mars.
    $premier = gmdate('Y-m-01');
    [$du, $au] = match ($cle) {
        'mois-dernier' => [gmdate('Y-m-01', strtotime($premier . ' -1 month') ?: time()),
                           gmdate('Y-m-t', strtotime($premier . ' -1 month') ?: time())],
        'annee' => [gmdate('Y-01-01'), $aujourdhui],
        '12-mois' => [gmdate('Y-m-01', strtotime($premier . ' -11 month') ?: time()), $aujourdhui],
        'libre' => [$jour($du) ?: $premier, $jour($au) ?: $aujourdhui],
        default => [$premier, $aujourdhui],
    };
    // Deux dates à l'envers sont une faute de frappe, pas une demande :
    // on les remet dans l'ordre plutôt que de rendre un rapport vide.
    if ($du > $au) {
        [$du, $au] = [$au, $du];
    }

    $debut = $du . 'T00:00:00Z';
    $fin = gmdate('Y-m-d\T00:00:00\Z', (strtotime($au) ?: time()) + 86400);
    $jours = max(1, (int) round(((strtotime($fin) ?: 0) - (strtotime($debut) ?: 0)) / 86400));

    return [
        'cle' => $cle,
        'du' => $du,
        'au' => $au,
        'debut' => $debut,
        'fin' => $fin,
        'jours' => $jours,
        'libelle' => rapport_periode_libelle($cle, $du, $au),
        // La période d'avant, de même durée : c'est elle qui donne son sens
        // à un nombre. Mille badges est bon ou mauvais selon le mois passé.
        'avant_debut' => gmdate('Y-m-d\T00:00:00\Z', (strtotime($debut) ?: 0) - $jours * 86400),
        'avant_fin' => $debut,
    ];
}

function rapport_periode_libelle(string $cle, string $du, string $au): string
{
    $mois_entier = $du === gmdate('Y-m-01', strtotime($du) ?: time())
        && $au === gmdate('Y-m-t', strtotime($du) ?: time());
    if ($mois_entier) {
        return ucfirst(mois_fr($du));
    }
    if ($cle === 'annee') {
        return 'Année ' . gmdate('Y', strtotime($du) ?: time());
    }
    return 'Du ' . date_fr($du) . ' au ' . date_fr($au);
}

/* ------------------------------------------------------------------ */
/* La portée                                                           */
/* ------------------------------------------------------------------ */

/**
 * Ce que le rapport regarde, et surtout ce qu'il n'a pas le droit de regarder.
 *
 * Quatre portées : la plateforme entière (l'équipe seule), un organisateur
 * (l'équipe, ou lui-même), un décor, une campagne. Les deux dernières se
 * désignent par un identifiant qui vient de la requête — et c'est ici, et
 * nulle part ailleurs, qu'on vérifie que le demandeur en est bien
 * l'auteur. Une portée refusée retombe sur celle de son compte, sans
 * message d'erreur : on ne confirme pas à un curieux que l'identifiant
 * qu'il a essayé existe.
 *
 * @return array{cle:string, titre:string, detail:string, auteur_id:?string,
 *               decor:?array, campagne:?array, plateforme:bool}
 */
function rapport_portee(array $u, array $get): array
{
    $equipe = droit($u, 'decors_tous');
    $moi = (string) $u['id'];

    $campagne_id = trim((string) ($get['campagne'] ?? ''));
    if ($campagne_id !== '' && ($c = campagne_email($campagne_id))) {
        if ($equipe || (string) ($c['auteur_id'] ?? '') === $moi) {
            $d = ($c['decor_id'] ?? null) ? decor_par_id((string) $c['decor_id']) : null;
            return [
                'cle' => 'campagne',
                'titre' => (string) $c['titre'],
                'detail' => $d ? 'Campagne du décor ' . $d['titre'] : 'Campagne',
                'auteur_id' => (string) ($c['auteur_id'] ?? '') ?: null,
                'decor' => null,
                'campagne' => $c,
                'plateforme' => false,
            ];
        }
    }

    $decor = trim((string) ($get['decor'] ?? ''));
    if ($decor !== '') {
        $d = decor_par_slug($decor) ?? decor_par_id($decor);
        if ($d && ($equipe || (string) ($d['auteur_id'] ?? '') === $moi)) {
            return [
                'cle' => 'decor',
                'titre' => (string) $d['titre'],
                'detail' => ($d['evenement_le'] ?? null)
                    ? 'Événement du ' . date_fr((string) $d['evenement_le'])
                    : 'Décor sans date d’événement',
                'auteur_id' => (string) ($d['auteur_id'] ?? '') ?: null,
                'decor' => $d,
                'campagne' => null,
                'plateforme' => false,
            ];
        }
    }

    $organisateur = trim((string) ($get['organisateur'] ?? ''));
    if ($equipe && $organisateur !== '' && ($o = utilisateur_par_id($organisateur))) {
        return [
            'cle' => 'organisateur',
            'titre' => (string) ($o['organisation'] ?: $o['nom']),
            'detail' => 'Tous les décors et toutes les campagnes de ce compte',
            'auteur_id' => (string) $o['id'],
            'decor' => null,
            'campagne' => null,
            'plateforme' => false,
        ];
    }

    if ($equipe) {
        return [
            'cle' => 'plateforme',
            'titre' => 'Toute la plateforme',
            'detail' => 'Tous les décors, toutes les campagnes, tous les comptes',
            'auteur_id' => null,
            'decor' => null,
            'campagne' => null,
            'plateforme' => true,
        ];
    }

    /**
     * Un organisateur ne demande pas sa portée : c'est son compte.
     *
     * Il n'existe aucun chemin, ici, par lequel il obtiendrait autre chose
     * que `auteur_id = son propre identifiant` — les portées décor et
     * campagne au-dessus ont déjà vérifié qu'il en est l'auteur.
     */
    return [
        'cle' => 'organisateur',
        'titre' => (string) ($u['organisation'] ?: $u['nom']),
        'detail' => 'Vos décors et vos campagnes',
        'auteur_id' => $moi,
        'decor' => null,
        'campagne' => null,
        'plateforme' => false,
    ];
}

/**
 * Le filtre SQL d'une portée, côté envois.
 *
 * @return array{0:string, 1:list<string>}
 */
function rapport_ou_envois(array $portee): array
{
    return match ($portee['cle']) {
        'campagne' => [' AND e.campagne_id = ?', [(string) $portee['campagne']['id']]],
        'decor' => [' AND c.decor_id = ?', [(string) $portee['decor']['id']]],
        'organisateur' => [' AND c.auteur_id = ?', [(string) $portee['auteur_id']]],
        default => ['', []],
    };
}

/**
 * Le filtre SQL d'une portée, côté décors.
 *
 * La colonne visée change d'une table à l'autre — `decor_id` dans
 * `evenements` et `badges`, `id` dans `decors` — d'où le nom passé en
 * paramètre plutôt que quatre fonctions qui se ressembleraient.
 *
 * @return array{0:string, 1:list<string>}
 */
function rapport_ou_decors(array $portee, string $colonne): array
{
    return match ($portee['cle']) {
        'decor' => [" AND $colonne = ?", [(string) $portee['decor']['id']]],
        'organisateur' => [" AND $colonne IN (SELECT id FROM decors WHERE auteur_id = ?)",
                           [(string) $portee['auteur_id']]],
        // Une campagne n'a pas de vues ni de badges : la section d'activité
        // ne s'affiche pas pour elle, et ce filtre ne sert alors jamais.
        'campagne' => [' AND 1 = 0', []],
        default => ['', []],
    };
}

/* ------------------------------------------------------------------ */
/* Les envois, canal par canal                                         */
/* ------------------------------------------------------------------ */

/**
 * Le tableau central : une ligne par canal, une colonne par état.
 *
 * La date retenue est `cree_le`, le moment où la ligne a été FIGÉE, et non
 * `envoye_le`. Les deux ne diffèrent que de quelques minutes, et `cree_le`
 * a deux avantages décisifs : elle est toujours renseignée — une ligne
 * jamais partie en a une aussi — et elle rattache la ligne à la campagne
 * qui l'a engendrée. Un rapport de septembre contient donc tout ce que les
 * campagnes de septembre ont produit, y compris ce qui n'est pas parti.
 *
 * @return array{lignes:array<string,array<string,mixed>>, total:array<string,int>}
 */
function rapport_envois(array $bornes, array $portee): array
{
    [$ou, $args] = rapport_ou_envois($portee);
    $s = db()->prepare(
        'SELECT e.canal AS canal, e.statut AS statut, COUNT(*) AS n,
                SUM(CASE WHEN e.ouvert_le IS NULL THEN 0 ELSE 1 END) AS ouverts
         FROM envois_email e
         JOIN campagnes_email c ON c.id = e.campagne_id
         WHERE e.cree_le >= ? AND e.cree_le < ?' . $ou . '
         GROUP BY e.canal, e.statut'
    );
    $s->execute(array_merge([$bornes['debut'], $bornes['fin']], $args));

    $vide = ['programmes' => 0, 'envoyes' => 0, 'echecs' => 0, 'ecartes' => 0,
             'attente' => 0, 'ouverts' => 0];
    $lignes = [];
    foreach (RAPPORT_CANAUX as $cle => $def) {
        $lignes[$cle] = $vide + ['cle' => $cle] + $def;
    }
    $total = $vide;

    foreach ($s->fetchAll() as $r) {
        $canal = (string) ($r['canal'] ?: 'email');
        if (!isset($lignes[$canal])) {
            // Un canal inconnu — une version plus récente, une reprise de
            // sauvegarde — se range à part plutôt que de disparaître.
            // `plus` est la pastille neutre de la régie : sans classe, une
            // pastille est du texte blanc sur fond transparent.
            $lignes[$canal] = $vide + ['cle' => $canal, 'nom' => ucfirst($canal),
                'puce' => 'plus', 'remise' => false, 'preuve' => 'accepté par la plateforme',
                'lecture' => null];
        }
        $n = (int) $r['n'];
        $case = match ((string) $r['statut']) {
            'envoye' => 'envoyes',
            'echec' => 'echecs',
            'desabonne', 'archive' => 'ecartes',
            default => 'attente',
        };
        $lignes[$canal][$case] += $n;
        $lignes[$canal]['programmes'] += $n;
        $lignes[$canal]['ouverts'] += (int) $r['ouverts'];
        $total[$case] += $n;
        $total['programmes'] += $n;
        $total['ouverts'] += (int) $r['ouverts'];
    }

    // Les parts servent aux barres : calculées ici, une fois, plutôt que
    // dans l'écran ET dans le PDF, qui finiraient par ne plus dire pareil.
    $max = max(1, $total['programmes']);
    foreach ($lignes as $cle => $l) {
        $lignes[$cle]['part'] = $l['programmes'] / $max;
        $lignes[$cle]['taux_envoi'] = $l['programmes'] > 0 ? $l['envoyes'] / $l['programmes'] : 0.0;
        $lignes[$cle]['taux_echec'] = $l['programmes'] > 0 ? $l['echecs'] / $l['programmes'] : 0.0;
        $lignes[$cle]['taux_ouverture'] = $l['envoyes'] > 0 ? $l['ouverts'] / $l['envoyes'] : 0.0;
    }
    $total['taux_envoi'] = $total['programmes'] > 0 ? $total['envoyes'] / $total['programmes'] : 0.0;
    $total['taux_echec'] = $total['programmes'] > 0 ? $total['echecs'] / $total['programmes'] : 0.0;

    return ['lignes' => $lignes, 'total' => $total];
}

/**
 * Ce qui n'est pas arrivé, regroupé par motif.
 *
 * Le nombre seul ne dit pas quoi faire. Neuf adresses qui n'existent pas
 * se traitent en les archivant ; quatre boîtes pleines se relancent
 * demain. La distinction existe déjà dans la régie — `echec_mortel()` —
 * et on ne la refait pas : on la lit.
 *
 * @return list<array<string, mixed>>
 */
function rapport_echecs(array $bornes, array $portee, int $limite = 40): array
{
    [$ou, $args] = rapport_ou_envois($portee);
    $s = db()->prepare(
        "SELECT e.canal AS canal, e.message AS message, COUNT(*) AS n
         FROM envois_email e
         JOIN campagnes_email c ON c.id = e.campagne_id
         WHERE e.statut = 'echec' AND e.cree_le >= ? AND e.cree_le < ?" . $ou . '
         GROUP BY e.canal, e.message'
    );
    $s->execute(array_merge([$bornes['debut'], $bornes['fin']], $args));

    /**
     * Deux serveurs disent la même chose avec des mots différents.
     *
     * « 550 5.1.1 No such user here » et « 550 Unknown recipient » sont un
     * seul et même motif, et les compter séparément donnerait une liste de
     * quarante lignes à un chiffre. On regroupe donc sur le CODE quand il
     * y en a un, et sur le message entier sinon.
     */
    $groupes = [];
    foreach ($s->fetchAll() as $r) {
        $canal = (string) ($r['canal'] ?: 'email');
        $message = (string) ($r['message'] ?? '');
        $code = echec_code($message);
        $cle = $canal . '|' . ($code !== '' ? $code : mb_substr($message, 0, 60));
        if (!isset($groupes[$cle])) {
            $groupes[$cle] = [
                'canal' => $canal,
                'canal_nom' => RAPPORT_CANAUX[$canal]['nom'] ?? ucfirst($canal),
                'puce' => RAPPORT_CANAUX[$canal]['puce'] ?? 'plus',
                'code' => $code,
                'message' => $message,
                'n' => 0,
                'mortel' => echec_mortel($canal, $message),
                'reprenable' => echec_reprenable($canal, $message),
            ];
        }
        $groupes[$cle]['n'] += (int) $r['n'];
    }

    usort($groupes, fn(array $a, array $b): int => $b['n'] <=> $a['n']);
    foreach ($groupes as $i => $g) {
        $groupes[$i]['explication'] = echec_explication((string) $g['canal'], (string) $g['message']);
    }
    return array_slice(array_values($groupes), 0, $limite);
}

/**
 * Le motif d'un échec, dit en français.
 *
 * Le message brut du serveur est gardé et affiché — c'est lui qui fait foi
 * quand on appelle un hébergeur — mais « 550 5.1.1 » ne dit rien à
 * l'organisateur qui lit son rapport. Les codes traduits sont ceux qu'on
 * rencontre vraiment ; le reste retombe sur une phrase honnête.
 */
function echec_explication(string $canal, ?string $message): string
{
    $m = mb_strtolower((string) $message);
    if ($canal === 'telegram') {
        return match (true) {
            str_contains($m, 'blocked') => 'La personne a bloqué le bot',
            str_contains($m, 'chat not found') => 'Conversation introuvable',
            str_contains($m, 'kicked') => 'Le bot a été retiré du groupe',
            default => 'Refusé par Telegram',
        };
    }
    if ($canal === 'whatsapp') {
        return match (true) {
            str_contains($m, 'template') => 'Modèle refusé ou non approuvé par Meta',
            str_contains($m, 'not a whatsapp') => 'Ce numéro n’est pas sur WhatsApp',
            default => 'Refusé par Meta',
        };
    }
    if ($canal === 'push') {
        return match (true) {
            str_contains($m, '410') || str_contains($m, '404')
                => 'Abonnement expiré : navigateur effacé ou notifications refusées',
            default => 'Refusé par le service de notifications',
        };
    }
    return match (echec_code($message)) {
        '550', '551', '553' => 'L’adresse n’existe pas',
        '552' => 'Boîte pleine',
        '554' => 'Message refusé par le serveur destinataire',
        '421' => 'Serveur occupé : refus temporaire',
        '450', '451', '452' => 'Refus temporaire, à relancer',
        default => $message === null || $message === ''
            ? 'Refusé sans explication'
            : 'Refusé par le serveur destinataire',
    };
}

/* ------------------------------------------------------------------ */
/* L'activité : vues, badges, présences                                */
/* ------------------------------------------------------------------ */

/**
 * Ce que le public a fait pendant la période, et pendant celle d'avant.
 *
 * La variation n'est pas un ornement : elle est la seule chose qui
 * transforme un nombre en information. `null` quand la période précédente
 * était vide — « +∞ % » n'apprend rien à personne.
 *
 * @return array<string, array{titre:string, valeur:int, avant:int, variation:?int}>
 */
function rapport_activite(array $bornes, array $portee): array
{
    [$ou_ev, $a_ev] = rapport_ou_decors($portee, 'decor_id');

    $mesures = [
        'vues' => ['Vues de décors',
            "SELECT COUNT(*) AS n FROM evenements WHERE genre = 'vue'
             AND cree_le >= ? AND cree_le < ?" . $ou_ev, $a_ev],
        'badges' => ['Badges créés',
            'SELECT COUNT(*) AS n FROM badges WHERE cree_le >= ? AND cree_le < ?' . $ou_ev, $a_ev],
        'telechargements' => ['Badges téléchargés',
            "SELECT COUNT(*) AS n FROM evenements WHERE genre = 'telechargement'
             AND cree_le >= ? AND cree_le < ?" . $ou_ev, $a_ev],
        'presences' => ['Présences scannées',
            'SELECT COUNT(*) AS n FROM badges WHERE scanne_le >= ? AND scanne_le < ?' . $ou_ev, $a_ev],
    ];

    $out = [];
    foreach ($mesures as $cle => [$titre, $sql, $args]) {
        $ici = compter($sql, array_merge([$bornes['debut'], $bornes['fin']], $args));
        $la = compter($sql, array_merge([$bornes['avant_debut'], $bornes['avant_fin']], $args));
        $out[$cle] = [
            'titre' => $titre,
            'valeur' => $ici,
            'avant' => $la,
            'variation' => $la > 0 ? (int) round(($ici - $la) / $la * 100) : null,
        ];
    }
    return $out;
}

/**
 * L'entonnoir : on regarde, on fabrique, on emporte, on vient.
 *
 * Les quatre étapes sont celles du tableau de bord, mais bornées à la
 * période et à la portée. Le taux de passage est celui depuis l'étape
 * d'AVANT : c'est là que ça fuit, et un pourcentage du total ne le dirait
 * pas.
 *
 * @return list<array{nom:string, n:int, part:float, passage:?float}>
 */
function rapport_entonnoir(array $activite): array
{
    $etapes = [
        ['Vues de décors', $activite['vues']['valeur']],
        ['Badges créés', $activite['badges']['valeur']],
        ['Badges téléchargés', $activite['telechargements']['valeur']],
        ['Présences à l’entrée', $activite['presences']['valeur']],
    ];
    $tete = max(1, $etapes[0][1]);
    $out = [];
    foreach ($etapes as $i => [$nom, $n]) {
        $precedent = $i === 0 ? $n : $etapes[$i - 1][1];
        $out[] = [
            'nom' => $nom,
            'n' => $n,
            /**
             * La part est BORNÉE à 1, le pourcentage ne l'est pas.
             *
             * Une étape peut dépasser celle d'avant — des badges créés par
             * l'API, ou depuis un lien partagé qui n'a pas compté de vue —
             * et le chiffre doit le dire. Mais une barre à 301 % sort de sa
             * piste et passe par-dessus la colonne voisine.
             */
            'part' => min(1.0, $n / $tete),
            'passage' => $i === 0 ? null : ($precedent > 0 ? $n / $precedent : 0.0),
        ];
    }
    return $out;
}

/**
 * Les décors de la période, classés sur la présence réelle.
 *
 * Pas sur les vues : un décor très vu qui ne remplit pas la salle est
 * exactement celui qu'on croit bon et qui ne l'est pas. Les compteurs sont
 * ceux de la période, pas les totaux de toujours — sans quoi un décor de
 * l'an dernier resterait en tête d'un rapport de septembre.
 *
 * @return list<array<string, mixed>>
 */
function rapport_decors(array $bornes, array $portee, int $limite = 12): array
{
    if ($portee['cle'] === 'campagne') {
        return [];
    }
    [$ou, $args] = rapport_ou_decors($portee, 'd.id');
    $b = [$bornes['debut'], $bornes['fin']];

    $sql = 'SELECT d.id, d.titre, d.slug, d.statut, d.evenement_le,
                   (SELECT COUNT(*) FROM evenements e WHERE e.decor_id = d.id AND e.genre = \'vue\'
                    AND e.cree_le >= ? AND e.cree_le < ?) AS vues,
                   (SELECT COUNT(*) FROM badges b WHERE b.decor_id = d.id
                    AND b.cree_le >= ? AND b.cree_le < ?) AS badges,
                   (SELECT COUNT(*) FROM evenements e WHERE e.decor_id = d.id
                    AND e.genre = \'telechargement\' AND e.cree_le >= ? AND e.cree_le < ?) AS telechargements,
                   (SELECT COUNT(*) FROM badges b WHERE b.decor_id = d.id
                    AND b.scanne_le >= ? AND b.scanne_le < ?) AS presences
            FROM decors d
            WHERE 1 = 1' . $ou . '
            ORDER BY presences DESC, badges DESC, vues DESC';

    $s = db()->prepare($sql);
    $s->execute(array_merge($b, $b, $b, $b, $args));

    $out = [];
    foreach ($s->fetchAll() as $d) {
        // Un décor sans la moindre trace sur la période n'a rien à dire :
        // le lister en ferait une page de zéros.
        if ((int) $d['vues'] + (int) $d['badges'] + (int) $d['presences'] === 0) {
            continue;
        }
        $d['taux_presence'] = (int) $d['telechargements'] > 0
            ? (int) $d['presences'] / (int) $d['telechargements'] : null;
        $out[] = $d;
        if (count($out) >= $limite) {
            break;
        }
    }
    return $out;
}

/* ------------------------------------------------------------------ */
/* Les campagnes et les rappels                                        */
/* ------------------------------------------------------------------ */

/**
 * Les campagnes de la période, avec ce que chacune a produit.
 *
 * Les compteurs sont relus depuis la file plutôt que dans les colonnes de
 * la campagne : `envoyes` et `echecs` y sont incrémentés lot par lot, et
 * une reprise qui réussit ne décrémente pas le compteur d'échecs. La file,
 * elle, porte l'état ACTUEL de chaque ligne, qui est ce qu'un rapport doit
 * dire.
 *
 * @return list<array<string, mixed>>
 */
function rapport_campagnes(array $bornes, array $portee, int $limite = 60): array
{
    [$ou, $args] = match ($portee['cle']) {
        'campagne' => [' AND c.id = ?', [(string) $portee['campagne']['id']]],
        'decor' => [' AND c.decor_id = ?', [(string) $portee['decor']['id']]],
        'organisateur' => [' AND c.auteur_id = ?', [(string) $portee['auteur_id']]],
        default => ['', []],
    };

    /**
     * Les compteurs sont bornés à la PÉRIODE, comme le tableau par canal.
     *
     * Une campagne partie le 30 dont les reprises tombent le 1er du mois
     * suivant porterait sinon, sur sa ligne, plus de messages que le total
     * du rapport n'en compte — et personne ne pourrait recouper les deux.
     * Réconciliable vaut mieux que complet : le rapport du mois suivant
     * portera les lignes qui lui reviennent.
     */
    $b = [$bornes['debut'], $bornes['fin']];
    $s = db()->prepare(
        "SELECT c.id, c.titre, c.sujet, c.rappel, c.statut, c.decor_id, c.canaux,
                c.envoye_le, c.planifie_le, c.cree_le, d.titre AS decor_titre,
                (SELECT COUNT(*) FROM envois_email e WHERE e.campagne_id = c.id
                 AND e.cree_le >= ? AND e.cree_le < ?) AS programmes,
                (SELECT COUNT(*) FROM envois_email e WHERE e.campagne_id = c.id
                 AND e.statut = 'envoye' AND e.cree_le >= ? AND e.cree_le < ?) AS envoyes,
                (SELECT COUNT(*) FROM envois_email e WHERE e.campagne_id = c.id
                 AND e.statut = 'echec' AND e.cree_le >= ? AND e.cree_le < ?) AS echecs,
                (SELECT COUNT(*) FROM envois_email e WHERE e.campagne_id = c.id
                 AND e.ouvert_le IS NOT NULL AND e.cree_le >= ? AND e.cree_le < ?) AS ouverts
         FROM campagnes_email c
         LEFT JOIN decors d ON d.id = c.decor_id
         WHERE COALESCE(c.envoye_le, c.planifie_le, c.cree_le) >= ?
           AND COALESCE(c.envoye_le, c.planifie_le, c.cree_le) < ?" . $ou . '
         ORDER BY COALESCE(c.envoye_le, c.planifie_le, c.cree_le)'
    );
    $s->execute(array_merge($b, $b, $b, $b, $b, $args));

    $maintenant = maintenant();
    $out = [];
    foreach ($s->fetchAll() as $c) {
        /**
         * Un brouillon n'est pas un envoi.
         *
         * Une campagne dont la liste n'a jamais été figée n'a rien produit :
         * la lister donnerait des pages de zéros, et ferait croire à des
         * envois ratés. Restent celles qui ont une file — même vide de
         * départs — et celles qui attendent leur heure, qui sont, elles,
         * une information : c'est ce qui va partir.
         */
        $a_venir = (int) $c['programmes'] === 0
            && ($c['planifie_le'] ?? '') !== '' && (string) $c['planifie_le'] >= $maintenant;
        if ((int) $c['programmes'] === 0 && !$a_venir) {
            continue;
        }
        $c['a_venir'] = $a_venir;
        $c['quand'] = (string) ($c['envoye_le'] ?: ($c['planifie_le'] ?: $c['cree_le']));
        $c['moment'] = ($c['rappel'] ?? '') !== '' ? rappel_libelle((string) $c['rappel']) : '';
        $c['canaux_lus'] = rapport_canaux_dune_campagne($c);
        $out[] = $c;
        if (count($out) >= $limite) {
            break;
        }
    }
    return $out;
}

/**
 * Les canaux d'une campagne, avec le nombre de lignes figées sur chacun.
 *
 * On lit la FILE et non la case cochée : une campagne peut avoir été
 * enregistrée avec quatre canaux et n'en avoir figé que trois, faute
 * d'abonnés sur le quatrième. Le rapport doit dire ce qui est parti.
 *
 * @return list<array{cle:string, nom:string, puce:string, n:int}>
 */
function rapport_canaux_dune_campagne(array $campagne): array
{
    $s = db()->prepare('SELECT canal, COUNT(*) AS n FROM envois_email
                        WHERE campagne_id = ? GROUP BY canal');
    $s->execute([(string) $campagne['id']]);
    $out = [];
    foreach ($s->fetchAll() as $r) {
        $cle = (string) ($r['canal'] ?: 'email');
        $out[] = [
            'cle' => $cle,
            'nom' => RAPPORT_CANAUX[$cle]['nom'] ?? ucfirst($cle),
            'puce' => RAPPORT_CANAUX[$cle]['puce'] ?? 'plus',
            'n' => (int) $r['n'],
        ];
    }
    return $out;
}

/**
 * L'entrée, heure par heure — pour un décor, et pour lui seul.
 *
 * « Cent quatre-vingt-six personnes entre 19 h et 20 h » est le chiffre qui
 * dit combien de scanners il fallait à la porte, et c'est le seul que
 * l'organisateur ne peut reconstituer d'aucune autre façon. Agrégé sur
 * toute la plateforme il ne voudrait rien dire — deux événements de deux
 * villes à deux heures différentes — d'où la restriction à un décor.
 *
 * Les heures creuses ENTRE deux heures pleines sont gardées à zéro : un
 * trou dans une soirée est une information, et une liste qui saute de 19 h
 * à 23 h laisse croire à un flux continu.
 *
 * @return list<array{heure:string, n:int, part:float}>
 */
function rapport_entree_par_heure(array $bornes, array $portee): array
{
    if ($portee['cle'] !== 'decor') {
        return [];
    }
    $s = db()->prepare('SELECT SUBSTR(scanne_le, 12, 2) AS heure, COUNT(*) AS n
                        FROM badges
                        WHERE scanne_le >= ? AND scanne_le < ? AND decor_id = ?
                        GROUP BY SUBSTR(scanne_le, 12, 2) ORDER BY heure');
    $s->execute([$bornes['debut'], $bornes['fin'], (string) $portee['decor']['id']]);

    $par_heure = [];
    foreach ($s->fetchAll() as $r) {
        $par_heure[(int) $r['heure']] = (int) $r['n'];
    }
    if (!$par_heure) {
        return [];
    }
    $max = max($par_heure);
    $out = [];
    for ($h = (int) min(array_keys($par_heure)); $h <= (int) max(array_keys($par_heure)); $h++) {
        $n = $par_heure[$h] ?? 0;
        $out[] = ['heure' => sprintf('%02d h', $h), 'n' => $n, 'part' => $max > 0 ? $n / $max : 0.0];
    }
    return $out;
}

/**
 * Les liens courts de la portée, et ce qu'ils ont ramené.
 *
 * Attention au sens : `liens.clics` est un compteur CUMULÉ depuis la
 * création du lien, pas le nombre de clics de la période. Le dire est plus
 * honnête que de l'omettre, et moins coûteux qu'une ligne par clic — qui
 * serait la seule façon d'avoir le détail, et qu'on ajoutera le jour où la
 * question se posera vraiment.
 *
 * @return list<array<string, mixed>>
 */
function rapport_liens(array $portee): array
{
    [$ou, $args] = match ($portee['cle']) {
        'decor' => [' WHERE decor_id = ?', [(string) $portee['decor']['id']]],
        'organisateur' => [' WHERE auteur_id = ?', [(string) $portee['auteur_id']]],
        default => ['', []],
    };
    if ($portee['cle'] === 'campagne') {
        return [];
    }
    $s = db()->prepare('SELECT code, titre, cible, clics, dernier_clic, cree_le
                        FROM liens' . $ou . ' ORDER BY clics DESC');
    $s->execute($args);
    return array_slice($s->fetchAll(), 0, 10);
}

/**
 * Les comptes que l'équipe peut viser, pour le sélecteur de portée.
 *
 * Seuls ceux qui ont quelque chose à montrer : un compte sans décor ni
 * campagne ferait une ligne de plus dans une liste qu'on parcourt, et un
 * rapport vide au bout. L'ordre est celui du nom, parce qu'on y cherche
 * quelqu'un qu'on connaît.
 *
 * @return list<array{id:string, nom:string}>
 */
function organisateurs_pour_rapport(): array
{
    $lignes = db()->query(
        "SELECT u.id AS id, u.nom AS nom, u.organisation AS organisation
         FROM utilisateurs u
         WHERE u.role = 'partenaire'
           AND (EXISTS (SELECT 1 FROM decors d WHERE d.auteur_id = u.id)
             OR EXISTS (SELECT 1 FROM campagnes_email c WHERE c.auteur_id = u.id))
         ORDER BY COALESCE(NULLIF(u.organisation, ''), u.nom)"
    )->fetchAll();

    $out = [];
    foreach ($lignes as $l) {
        $out[] = ['id' => (string) $l['id'],
                  'nom' => (string) (($l['organisation'] ?? '') !== '' ? $l['organisation'] : $l['nom'])];
    }
    return $out;
}

/* ------------------------------------------------------------------ */
/* Le rapport entier                                                   */
/* ------------------------------------------------------------------ */

/**
 * Tout le rapport, en une fois.
 *
 * L'écran, le PDF et le CSV appellent CETTE fonction et rien d'autre.
 * Trois chemins qui recalculeraient chacun leurs totaux finiraient par ne
 * plus dire la même chose, et c'est le genre d'écart qu'on découvre quand
 * un client compare son PDF à son écran.
 *
 * @return array<string, mixed>
 */
function rapport(array $u, array $get): array
{
    $bornes = rapport_periode(
        (string) ($get['periode'] ?? ''),
        (string) ($get['du'] ?? ''),
        (string) ($get['au'] ?? '')
    );
    $portee = rapport_portee($u, $get);
    $activite = rapport_activite($bornes, $portee);
    $envois = rapport_envois($bornes, $portee);

    return [
        'periode' => $bornes,
        'portee' => $portee,
        'activite' => $activite,
        'entonnoir' => $portee['cle'] === 'campagne' ? [] : rapport_entonnoir($activite),
        'envois' => $envois,
        'echecs' => rapport_echecs($bornes, $portee),
        'decors' => $portee['cle'] === 'decor' ? [] : rapport_decors($bornes, $portee),
        'campagnes' => rapport_campagnes($bornes, $portee),
        'liens' => rapport_liens($portee),
        'entree' => rapport_entree_par_heure($bornes, $portee),
        'edite_le' => maintenant(),
        'edite_par' => (string) ($u['nom'] ?? ''),
    ];
}

/** Le nom du fichier, lisible dans un dossier de téléchargements. */
function rapport_nom_fichier(array $r, string $extension): string
{
    $portee = match ($r['portee']['cle']) {
        'decor' => 'decor-' . ($r['portee']['decor']['slug'] ?? ''),
        'campagne' => 'campagne',
        'organisateur' => 'compte',
        default => 'plateforme',
    };
    return 'rapport-' . $portee . '-' . $r['periode']['du'] . '-' . $r['periode']['au'] . '.' . $extension;
}

/* ------------------------------------------------------------------ */
/* L'export CSV                                                        */
/* ------------------------------------------------------------------ */

/**
 * Le même rapport, pour un tableur.
 *
 * Point-virgule et BOM : c'est ce qu'Excel attend en français, et sans eux
 * le comptable ouvre un fichier d'une seule colonne pleine de caractères
 * abîmés. Le CSV des factures fait déjà exactement ce choix.
 */
function rapport_csv(array $r): string
{
    $lignes = [];
    $lignes[] = ['Rapport', $r['portee']['titre']];
    $lignes[] = ['Période', $r['periode']['libelle']];
    $lignes[] = ['Du', $r['periode']['du']];
    $lignes[] = ['Au', $r['periode']['au']];
    $lignes[] = ['Édité le', date_fr($r['edite_le'])];
    $lignes[] = [];

    $lignes[] = ['Canal', 'Programmés', 'Partis', 'Échecs', 'Écartés', 'En attente',
                 'Ouvertures', 'Ce que « parti » prouve'];
    foreach ($r['envois']['lignes'] as $l) {
        if ((int) $l['programmes'] === 0) {
            continue;
        }
        $lignes[] = [$l['nom'], $l['programmes'], $l['envoyes'], $l['echecs'], $l['ecartes'],
                     $l['attente'], $l['lecture'] === null ? '' : $l['ouverts'], $l['preuve']];
    }
    $t = $r['envois']['total'];
    $lignes[] = ['Total', $t['programmes'], $t['envoyes'], $t['echecs'], $t['ecartes'],
                 $t['attente'], $t['ouverts'], ''];
    $lignes[] = [];

    $lignes[] = ['Activité', 'Période', 'Période précédente', 'Variation %'];
    foreach ($r['activite'] as $a) {
        $lignes[] = [$a['titre'], $a['valeur'], $a['avant'],
                     $a['variation'] === null ? '' : $a['variation']];
    }
    $lignes[] = [];

    if ($r['echecs']) {
        $lignes[] = ['Échecs — canal', 'Code', 'Motif', 'Nombre', 'Relançable', 'Message du serveur'];
        foreach ($r['echecs'] as $e) {
            $lignes[] = [$e['canal_nom'], $e['code'], $e['explication'], $e['n'],
                         $e['mortel'] ? 'non' : ($e['reprenable'] ? 'oui' : 'non'),
                         (string) $e['message']];
        }
        $lignes[] = [];
    }

    if ($r['campagnes']) {
        $lignes[] = ['Campagne', 'Moment', 'Date', 'Programmés', 'Partis', 'Échecs', 'Ouvertures'];
        foreach ($r['campagnes'] as $c) {
            $lignes[] = [$c['titre'], $c['moment'], date_fr((string) $c['quand']),
                         $c['programmes'], $c['envoyes'], $c['echecs'], $c['ouverts']];
        }
        $lignes[] = [];
    }

    if ($r['decors']) {
        $lignes[] = ['Décor', 'Vues', 'Badges', 'Téléchargés', 'Présents'];
        foreach ($r['decors'] as $d) {
            $lignes[] = [$d['titre'], $d['vues'], $d['badges'], $d['telechargements'], $d['presences']];
        }
    }

    $out = "\xEF\xBB\xBF";
    foreach ($lignes as $l) {
        $out .= implode(';', array_map(static function ($c): string {
            $v = (string) $c;
            return str_contains($v, ';') || str_contains($v, '"') || str_contains($v, "\n")
                ? '"' . str_replace('"', '""', $v) . '"' : $v;
        }, $l)) . "\r\n";
    }
    return $out;
}

/* ------------------------------------------------------------------ */
/* Le PDF                                                              */
/* ------------------------------------------------------------------ */

/** La couleur d'un canal dans le document, la même qu'à l'écran. */
const RAPPORT_COULEURS = [
    'email' => '#0F172A', 'push' => '#2563EB',
    'telegram' => '#229ED9', 'whatsapp' => '#25D366',
];

/**
 * Le rapport en PDF, sur le générateur écrit pour les factures.
 *
 * Aucune bibliothèque, aucune image : un graphique en barres est une suite
 * de pavés, et `pave()` existe depuis la facture. Le document tient en
 * autant de pages qu'il faut, numérotées — un rapport qu'on imprime et
 * qu'on agrafe doit se remettre dans l'ordre.
 */
function rapport_pdf(array $r): string
{
    $e = facturation_reglages();
    $pdf = new EcrivainPdf('Rapport ' . $r['portee']['titre'] . ' · ' . $r['periode']['libelle']);

    $g = 16.0;
    $d = PDF_L - 16.0;
    $bas = PDF_H - 22.0;

    /** Ouvre une page et rend le y de départ. */
    $ouvrir = static function () use ($pdf, $g, $d, $e, $r): float {
        $pdf->page();
        $y = 16.0;
        $pdf->texte($g, $y, (string) $e['fact_raison'], 9, true, '#475569');
        $pdf->texte($d, $y, $r['periode']['libelle'], 9, false, '#94A3B8', 'droite');
        $y += 2.5;
        $pdf->filet($g, $y, $d, $y, 0.3, '#E2E8F5');
        // Le pied de page s'écrit tout de suite : après `numeroter()`, une
        // écriture de plus ouvrirait une page vide à la fin du document.
        $pdf->texte($g, PDF_H - 12, rapport_nom_fichier($r, 'pdf') . ' · Wakabi Boost ' . VERSION,
            7.5, false, '#94A3B8');
        return $y + 8;
    };

    $y = $ouvrir();

    /* ---- le titre ---- */
    $pdf->texte($g, $y, 'Rapport', 10, true, '#94A3B8');
    $y += 8;
    $pdf->texte($g, $y, (string) $r['portee']['titre'], 19, true);
    $y += 6;
    $pdf->texte($g, $y, (string) $r['portee']['detail'], 9.5, false, '#475569');
    $y += 5;
    $pdf->texte($g, $y, 'Du ' . date_fr($r['periode']['du']) . ' au ' . date_fr($r['periode']['au'])
        . ' · ' . $r['periode']['jours'] . ' jours · édité le ' . date_fr($r['edite_le']),
        9, false, '#475569');
    $y += 11;

    /* ---- les tuiles ---- */
    $t = $r['envois']['total'];
    $n = static fn(int $x): string => number_format($x, 0, ',', ' ');
    $tuiles = $r['portee']['cle'] === 'campagne'
        ? [['Messages programmés', $n($t['programmes'])], ['Partis', $n($t['envoyes'])],
           ['Échecs', $n($t['echecs'])], ['Écartés', $n($t['ecartes'])],
           ['Ouvertures', $n($t['ouverts'])]]
        : [['Messages programmés', $n($t['programmes'])], ['Partis', $n($t['envoyes'])],
           ['Échecs', $n($t['echecs'])],
           ['Badges créés', $n($r['activite']['badges']['valeur'])],
           ['Présences scannées', $n($r['activite']['presences']['valeur'])]];
    $largeur = ($d - $g - 4 * 3) / 5;
    foreach ($tuiles as $i => [$titre, $valeur]) {
        $x = $g + $i * ($largeur + 3);
        $pdf->pave($x, $y, $largeur, 17, '#F1F5FC');
        $pdf->texte($x + 3, $y + 8, $valeur, 13, true);
        $pdf->texte($x + 3, $y + 13.5, $titre, 6.5, false, '#475569');
    }
    $y += 24;

    /* ---- les barres par canal ---- */
    $y = rapport_pdf_titre($pdf, $g, $y, 'Les envois, canal par canal');
    $piste = $d - $g - 46 - 24;
    if ((int) $t['programmes'] === 0) {
        // Un tableau vide surmonté d'un total à zéro ne dit rien ; la phrase,
        // si : rien n'est parti, et ce n'est pas une panne du rapport.
        $y = $pdf->paragraphe($g, $y, $d - $g,
            'Aucun message n’a été programmé sur cette période.', 9, 4.4, false, '#475569') + 6;
    }
    foreach ($r['envois']['lignes'] as $l) {
        if ((int) $l['programmes'] === 0) {
            continue;
        }
        $pdf->texte($g, $y + 3, (string) $l['nom'], 8.5, false);
        $pdf->pave($g + 46, $y, $piste, 4.2, '#F1F5FC');
        $pdf->pave($g + 46, $y, max(0.6, $piste * (float) $l['part']), 4.2,
            RAPPORT_COULEURS[$l['cle']] ?? '#2563EB');
        $pdf->texte($d, $y + 3, number_format((int) $l['programmes'], 0, ',', ' '), 8.5, true,
            '#0F172A', 'droite');
        $y += 7;
    }
    $y += 4;

    /* ---- le tableau ---- */
    $colonnes = (int) $t['programmes'] === 0 ? [] : [
        ['Canal', $g, 'gauche', 30],
        ['Programmés', $g + 46, 'droite', 0],
        ['Partis', $g + 66, 'droite', 0],
        ['Échecs', $g + 84, 'droite', 0],
        ['Écartés', $g + 102, 'droite', 0],
        ['Ouvertures', $g + 124, 'droite', 0],
    ];
    $y = $colonnes ? rapport_pdf_entete_tableau($pdf, $g, $d, $y, $colonnes) : $y;
    foreach ($colonnes ? $r['envois']['lignes'] : [] as $l) {
        if ((int) $l['programmes'] === 0) {
            continue;
        }
        $pdf->texte($g, $y, (string) $l['nom'], 8.5, true);
        foreach ([[$g + 46, $l['programmes']], [$g + 66, $l['envoyes']], [$g + 84, $l['echecs']],
                  [$g + 102, $l['ecartes']]] as [$x, $n]) {
            $pdf->texte($x, $y, number_format((int) $n, 0, ',', ' '), 8.5, false, '#0F172A', 'droite');
        }
        $pdf->texte($g + 124, $y, $l['lecture'] === null ? 'non mesuré'
            : number_format((int) $l['ouverts'], 0, ',', ' '),
            $l['lecture'] === null ? 7 : 8.5, false,
            $l['lecture'] === null ? '#94A3B8' : '#0F172A', 'droite');
        // Sous le nom, et non à droite des nombres : la phrase est longue,
        // et la marge droite est déjà prise par la colonne des ouvertures.
        $pdf->texte($g, $y + 3.6, '« parti » = ' . $l['preuve'], 6.5, false, '#94A3B8');
        $y += 7.6;
        $pdf->filet($g, $y + 1.4, $d, $y + 1.4, 0.15, '#E2E8F5');
        $y += 5;
    }
    if ($colonnes) {
        $pdf->texte($g, $y + 1, 'Total', 9, true);
        foreach ([[$g + 46, $t['programmes']], [$g + 66, $t['envoyes']], [$g + 84, $t['echecs']],
                  [$g + 102, $t['ecartes']], [$g + 124, $t['ouverts']]] as [$x, $n]) {
            $pdf->texte($x, $y + 1, number_format((int) $n, 0, ',', ' '), 9, true, '#0F172A', 'droite');
        }
        $y += 9;
    }

    $y = $colonnes ? $pdf->paragraphe($g, $y, $d - $g,
        'Aucun de ces nombres ne dit « reçu ». Un serveur qui accepte un message ne garantit pas '
        . 'qu’il le remettra, et seul Telegram confirme la publication. Les ouvertures sont un '
        . 'minimum : les images sont souvent bloquées, et une lecture sans image ne se compte pas.',
        8, 3.8, false, '#475569') + 4 : $y;

    /* ---- l'activité et l'entonnoir ---- */
    if ($r['entonnoir']) {
        if ($y > $bas - 60) {
            $y = $ouvrir();
        }
        $y = rapport_pdf_titre($pdf, $g, $y, 'De la vue à la porte');
        foreach ($r['entonnoir'] as $pas) {
            $pdf->texte($g, $y + 3, (string) $pas['nom'], 8.5, false);
            $pdf->pave($g + 46, $y, $piste, 4.2, '#F1F5FC');
            if ((int) $pas['n'] > 0) {
                $pdf->pave($g + 46, $y, max(0.6, $piste * (float) $pas['part']), 4.2, '#2563EB');
            }
            $pdf->texte($d, $y + 3, number_format((int) $pas['n'], 0, ',', ' ')
                . ($pas['passage'] === null ? '' : '   ' . round($pas['passage'] * 100) . ' %'),
                8.5, true, '#0F172A', 'droite');
            $y += 7;
        }
        $y += 6;
    }

    /* ---- l'entrée, heure par heure ---- */
    if ($r['entree']) {
        if ($y > $bas - 40) {
            $y = $ouvrir();
        }
        $y = rapport_pdf_titre($pdf, $g, $y, 'L’entrée, heure par heure');
        foreach ($r['entree'] as $h) {
            if ($y > $bas - 6) {
                $y = $ouvrir();
            }
            $pdf->texte($g, $y + 3, (string) $h['heure'], 8.5, false);
            $pdf->pave($g + 46, $y, $piste, 4.2, '#F1F5FC');
            if ((int) $h['n'] > 0) {
                $pdf->pave($g + 46, $y, max(0.6, $piste * (float) $h['part']), 4.2, '#0D9488');
            }
            $pdf->texte($d, $y + 3, number_format((int) $h['n'], 0, ',', ' '), 8.5, true,
                '#0F172A', 'droite');
            $y += 7;
        }
        $y += 6;
    }

    /* ---- les échecs ---- */
    if ($r['echecs']) {
        if ($y > $bas - 50) {
            $y = $ouvrir();
        }
        $y = rapport_pdf_titre($pdf, $g, $y,
            'Ce qui n’est pas arrivé (' . number_format($t['echecs'], 0, ',', ' ') . ')');
        $y = rapport_pdf_entete_tableau($pdf, $g, $d, $y, [
            ['Canal', $g, 'gauche', 0], ['Code', $g + 30, 'gauche', 0],
            ['Motif', $g + 46, 'gauche', 0], ['Nombre', $g + 132, 'droite', 0],
            ['Suite', $g + 136, 'gauche', 0],
        ]);
        foreach ($r['echecs'] as $ec) {
            if ($y > $bas - 6) {
                $y = $ouvrir();
            }
            $pdf->texte($g, $y, (string) $ec['canal_nom'], 8, false);
            $pdf->texte($g + 30, $y, (string) ($ec['code'] ?: 'sans code'), 8, false, '#475569');
            $pdf->texte($g + 46, $y, (string) $ec['explication'], 8, false);
            $pdf->texte($g + 132, $y, number_format((int) $ec['n'], 0, ',', ' '), 8, true,
                '#0F172A', 'droite');
            $pdf->texte($g + 136, $y, $ec['mortel'] ? 'à écarter'
                : ($ec['reprenable'] ? 'à relancer' : 'sans suite'), 8, false,
                $ec['mortel'] ? '#B91C1C' : '#475569');
            $y += 3.6;
            $pdf->filet($g, $y + 1.2, $d, $y + 1.2, 0.15, '#E2E8F5');
            $y += 4.6;
        }
        $y += 5;
    }

    /* ---- les campagnes ---- */
    if ($r['campagnes']) {
        if ($y > $bas - 50) {
            $y = $ouvrir();
        }
        $y = rapport_pdf_titre($pdf, $g, $y, $r['portee']['cle'] === 'decor'
            ? 'Les rappels de ce décor' : 'Les campagnes de la période');
        $y = rapport_pdf_entete_tableau($pdf, $g, $d, $y, [
            ['Campagne', $g, 'gauche', 0], ['Date', $g + 100, 'gauche', 0],
            ['Programmés', $g + 136, 'droite', 0], ['Partis', $g + 156, 'droite', 0],
            ['Échecs', $g + 172, 'droite', 0],
        ]);
        $montrees = array_slice($r['campagnes'], 0, 40);
        foreach ($montrees as $c) {
            if ($y > $bas - 6) {
                $y = $ouvrir();
            }
            $titre = ($c['moment'] !== '' ? $c['moment'] . ' · ' : '') . $c['titre'];
            $pdf->texte($g, $y, mb_strlen($titre) > 58 ? mb_substr($titre, 0, 57) . '…' : $titre, 8, false);
            $pdf->texte($g + 100, $y, date_fr((string) $c['quand']), 8, false, '#475569');
            foreach ([[$g + 136, $c['programmes']], [$g + 156, $c['envoyes']],
                      [$g + 172, $c['echecs']]] as [$x, $n]) {
                $pdf->texte($x, $y, number_format((int) $n, 0, ',', ' '), 8, false, '#0F172A', 'droite');
            }
            $y += 3.6;
            $pdf->filet($g, $y + 1.2, $d, $y + 1.2, 0.15, '#E2E8F5');
            $y += 4.6;
        }
        if (count($r['campagnes']) > count($montrees)) {
            $pdf->texte($g, $y, 'Et ' . (count($r['campagnes']) - count($montrees))
                . ' autres campagnes sur la période : le CSV les porte toutes.',
                8, false, '#94A3B8');
            $y += 6;
        }
        $y += 5;
    }

    /* ---- les décors ---- */
    if ($r['decors']) {
        if ($y > $bas - 50) {
            $y = $ouvrir();
        }
        $y = rapport_pdf_titre($pdf, $g, $y, 'Les décors, classés sur la présence');
        $y = rapport_pdf_entete_tableau($pdf, $g, $d, $y, [
            ['Décor', $g, 'gauche', 0], ['Vues', $g + 116, 'droite', 0],
            ['Badges', $g + 136, 'droite', 0], ['Téléchargés', $g + 158, 'droite', 0],
            ['Présents', $g + 178, 'droite', 0],
        ]);
        foreach ($r['decors'] as $dec) {
            if ($y > $bas - 6) {
                $y = $ouvrir();
            }
            $titre = (string) $dec['titre'];
            $pdf->texte($g, $y, mb_strlen($titre) > 54 ? mb_substr($titre, 0, 53) . '…' : $titre, 8, false);
            foreach ([[$g + 116, $dec['vues']], [$g + 136, $dec['badges']],
                      [$g + 158, $dec['telechargements']], [$g + 178, $dec['presences']]] as [$x, $n]) {
                $pdf->texte($x, $y, number_format((int) $n, 0, ',', ' '), 8, false, '#0F172A', 'droite');
            }
            $y += 3.6;
            $pdf->filet($g, $y + 1.2, $d, $y + 1.2, 0.15, '#E2E8F5');
            $y += 4.6;
        }
    }

    /* ---- la numérotation, une fois qu'on sait combien de pages ---- */
    $pdf->numeroter('Page %n / %t', $d, PDF_H - 12, 7.5, '#94A3B8');

    return $pdf->rendu();
}

/** Un titre de section, avec son filet. */
function rapport_pdf_titre(EcrivainPdf $pdf, float $g, float $y, string $titre): float
{
    $pdf->texte($g, $y, $titre, 11, true);
    $y += 2.5;
    $pdf->filet($g, $y, PDF_L - 16.0, $y, 0.3, '#0F172A');
    return $y + 7;
}

/**
 * L'en-tête d'un tableau : les libellés, puis un filet.
 *
 * @param list<array{0:string, 1:float, 2:string, 3:float}> $colonnes
 */
function rapport_pdf_entete_tableau(EcrivainPdf $pdf, float $g, float $d, float $y, array $colonnes): float
{
    foreach ($colonnes as [$titre, $x, $aligne]) {
        $pdf->texte($x, $y, mb_strtoupper($titre), 6.5, true, '#94A3B8', $aligne);
    }
    $y += 1.8;
    $pdf->filet($g, $y, $d, $y, 0.2, '#E2E8F5');
    return $y + 5;
}
