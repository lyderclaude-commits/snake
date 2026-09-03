<?php
/**
 * L'API REST de l'offre Mouvement.
 *
 * Elle figurait au comparatif des offres depuis le début, vendue 30 000 F
 * par mois, et n'existait pas : il ne restait qu'une colonne `cle_api` qui
 * dormait dans la table des comptes. Une ligne vendue et absente est un
 * problème commercial avant d'être un problème technique.
 *
 * Ce qu'elle fait, et ce qu'elle ne fait pas :
 *
 *  - Elle **lit** ce que l'organisateur voit déjà sur son tableau de bord —
 *    ses campagnes, ses badges, ses présences, ses liens — pour qu'il puisse
 *    le verser dans son propre tableur, son CRM ou son écran de régie.
 *  - Elle **crée des liens courts**, seule écriture ouverte, parce que c'est
 *    la seule qui se scripte utilement et qu'elle est réversible.
 *  - Elle ne publie pas de décor et ne touche à aucun compte. Une API qui
 *    publie sans relecture ferait tomber le circuit de validation, qui est
 *    la garantie qu'on donne à tous les autres.
 *
 * L'accès est une clé par compte, révocable depuis le profil. Elle vaut mot
 * de passe : elle n'est affichée en entier qu'une fois, à sa création.
 */

declare(strict_types=1);

/** La version servie. Elle figure dans l'adresse : une v2 pourra coexister. */
const API_VERSION = 'v1';

/** Le plafond d'appels, par clé et par quart d'heure. */
const API_APPELS_MAX = 300;

/* ------------------------------------------------------------------ */
/* La clé                                                              */
/* ------------------------------------------------------------------ */

/**
 * Fabrique une clé et la pose sur le compte. Rend la clé EN CLAIR.
 *
 * Elle est gardée telle quelle en base, et non hachée comme un mot de
 * passe : il faut pouvoir la retrouver à chaque appel sans connaître le
 * compte à l'avance. C'est le compromis habituel des clés d'API, et c'est
 * pourquoi elle est longue, aléatoire, et révocable d'un clic.
 */
function api_cle_creer(string $utilisateur_id): string
{
    $cle = 'wkb_' . bin2hex(random_bytes(24));
    db()->prepare('UPDATE utilisateurs SET cle_api = ? WHERE id = ?')->execute([$cle, $utilisateur_id]);
    return $cle;
}

function api_cle_revoquer(string $utilisateur_id): void
{
    db()->prepare('UPDATE utilisateurs SET cle_api = NULL WHERE id = ?')->execute([$utilisateur_id]);
}

/** De quoi montrer qu'une clé existe sans la réafficher : `wkb_3f9a…c210`. */
function api_cle_masquee(?string $cle): string
{
    $cle = (string) $cle;
    return strlen($cle) < 16 ? '' : substr($cle, 0, 8) . '…' . substr($cle, -4);
}

/**
 * La clé présentée par l'appelant.
 *
 * `Authorization: Bearer …` d'abord, comme partout. Mais un hébergement
 * mutualisé en CGI **retire** cet en-tête sauf si `CGIPassAuth` est actif,
 * et l'appelant n'a alors aucun moyen de comprendre pourquoi il est
 * refusé. `X-Api-Cle` est le repli documenté, et il marche partout.
 */
function api_cle_presentee(): string
{
    $entetes = function_exists('getallheaders') ? array_change_key_case(getallheaders(), CASE_LOWER) : [];
    $auth = (string) ($entetes['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (preg_match('/^Bearer\s+(\S+)$/i', trim($auth), $m)) {
        return $m[1];
    }
    return trim((string) ($entetes['x-api-cle'] ?? $_SERVER['HTTP_X_API_CLE'] ?? ''));
}

/* ------------------------------------------------------------------ */
/* Le point d'entrée                                                   */
/* ------------------------------------------------------------------ */

/** Une erreur, dans la forme que l'appelant peut lire et afficher. */
function api_erreur(string $message, int $code, string $genre = 'erreur'): never
{
    json_repondre(['ok' => false, 'genre' => $genre, 'message' => $message], $code);
}

/**
 * Vérifie la clé, l'offre et le débit, puis rend le compte appelant.
 *
 * L'ordre compte : une clé inconnue et une clé valable sur une offre qui
 * n'ouvre pas l'API doivent recevoir deux messages DIFFÉRENTS. Sans quoi
 * un client qui a payé Mouvement passe une journée à croire que sa clé est
 * fausse alors que son offre vient d'être rétrogradée.
 */
function api_appelant(): array
{
    $cle = api_cle_presentee();
    if ($cle === '') {
        api_erreur('Clé absente. Envoyez « Authorization: Bearer <clé> » '
            . 'ou, si votre hébergement retire cet en-tête, « X-Api-Cle: <clé> ».', 401, 'cle_absente');
    }

    $s = db()->prepare('SELECT * FROM utilisateurs WHERE cle_api = ?');
    $s->execute([$cle]);
    $u = $s->fetch() ?: null;
    if (!$u) {
        api_erreur('Cette clé n’est reconnue par aucun compte.', 401, 'cle_inconnue');
    }
    if ((int) $u['suspendu']) {
        api_erreur('Ce compte est suspendu.', 403, 'compte_suspendu');
    }
    /**
     * Le droit AVANT l'offre, et pas seulement à l'écran.
     *
     * Sans cette ligne, une clé fabriquée du temps où l'écran ne demandait
     * rien continuerait de fonctionner pour un rôle qui n'y a plus droit.
     * Un contrôle posé uniquement sur le formulaire n'est pas un contrôle :
     * c'est une politesse.
     */
    if (!droit($u, 'api')) {
        api_erreur('Ce rôle n’ouvre pas l’API. Elle s’adresse aux organisateurs '
            . 'et à l’équipe.', 403, 'role_insuffisant');
    }
    if (!capacite($u, 'api')) {
        api_erreur('L’offre ' . formule_libelle($u['formule']) . ' n’ouvre pas l’API. '
            . 'Elle est comprise dans l’offre ' . formule_libelle(offre_qui_debloque('api')) . '.',
            403, 'offre_insuffisante');
    }

    // Le même compteur que la connexion, sur une clé différente : un script
    // en boucle ne doit pas pouvoir occuper le serveur à lui seul.
    $debit = 'api|' . substr(hash('sha256', $cle), 0, 24);
    if (debit_appels_depasse($debit)) {
        api_erreur('Trop d’appels. Le plafond est de ' . API_APPELS_MAX . ' par quart d’heure.',
            429, 'debit_depasse');
    }
    debit_noter($debit);

    return $u;
}

/** Le compteur d'appels — même table que la limitation de connexion. */
function debit_appels_depasse(string $cle): bool
{
    $depuis = gmdate('Y-m-d\TH:i:s\Z', time() - 15 * 60);
    $s = db()->prepare('SELECT COUNT(*) FROM tentatives WHERE cle = ? AND cree_le > ?');
    $s->execute([$cle, $depuis]);
    return (int) $s->fetchColumn() >= API_APPELS_MAX;
}

/* ------------------------------------------------------------------ */
/* Les ressources                                                      */
/* ------------------------------------------------------------------ */

/**
 * Un décor, tel que l'API le montre.
 *
 * Volontairement plat et stable : ni le gabarit, ni les identifiants
 * internes des autres tables. Ce qu'on publie ici, on devra le maintenir.
 */
function api_campagne(array $d): array
{
    $p = presence((string) $d['id']);
    return [
        'slug' => $d['slug'],
        'titre' => $d['titre'],
        'statut' => $d['statut'],
        'ville' => $d['ville'] ?: null,
        'publie_le' => $d['publie_le'] ?: null,
        'maj_le' => $d['maj_le'],
        'adresse' => base_url() . '/index.php?p=decor&slug=' . rawurlencode((string) $d['slug']),
        'chiffres' => [
            'vues' => (int) ($d['vues'] ?? 0),
            'telechargements' => (int) ($d['telechargements'] ?? 0),
            'badges_emis' => $p['emis'],
            'presences' => $p['scannes'],
            'taux_presence' => round($p['taux'], 4),
        ],
    ];
}

/** Le décor visé par `?campagne=slug`, s'il appartient bien à l'appelant. */
function api_campagne_de(array $u, string $slug): array
{
    $d = decor_par_slug($slug);
    if (!$d || $d['auteur_id'] !== $u['id']) {
        api_erreur('Aucune campagne « ' . $slug . ' » sur ce compte.', 404, 'introuvable');
    }
    return $d;
}

/** Les bornes de pagination, communes à toutes les listes. */
function api_pagination(): array
{
    $par_page = max(1, min(200, (int) ($_GET['par_page'] ?? 50)));
    $page = max(1, (int) ($_GET['page'] ?? 1));
    return [$par_page, ($page - 1) * $par_page, $page];
}

/**
 * Sert une ressource. Ne rend jamais : chaque branche répond et sort.
 *
 * Les ressources sont au pluriel et sans verbe : c'est la seule convention
 * qu'un développeur n'a pas à lire pour deviner.
 */
function api_servir(): never
{
    $u = api_appelant();
    $r = trim((string) ($_GET['r'] ?? ''), '/');
    $methode = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    /* ---- moi : le compte, son offre, ses compteurs ---- */
    if ($r === 'moi' && $methode === 'GET') {
        $bilan = bilan_offre($u);
        json_repondre([
            'ok' => true,
            'compte' => [
                'nom' => $u['nom'],
                'organisation' => $u['organisation'] ?: null,
                'email' => $u['email'],
                'ville' => $u['ville'] ?: null,
                'offre' => $u['formule'],
                'offre_libelle' => formule_libelle($u['formule']),
                'echeance_le' => echeance_de($u),
                'jours_restants' => jours_restants($u),
            ],
            'compteurs' => array_map(
                fn(array $l) => ['consomme' => $l['consomme'], 'maximum' => $l['max']],
                array_filter($bilan['lignes'], fn(array $l) => $l['nature'] === 'compteur')
            ),
        ]);
    }

    /* ---- campagnes ---- */
    if ($r === 'campagnes' && $methode === 'GET') {
        json_repondre([
            'ok' => true,
            'campagnes' => array_map('api_campagne', decors_de((string) $u['id'])),
        ]);
    }
    if (str_starts_with($r, 'campagnes/') && $methode === 'GET') {
        json_repondre([
            'ok' => true,
            'campagne' => api_campagne(api_campagne_de($u, substr($r, 10))),
        ]);
    }

    /* ---- badges émis, et présences ---- */
    if (($r === 'badges' || $r === 'presences') && $methode === 'GET') {
        $d = api_campagne_de($u, (string) ($_GET['campagne'] ?? ''));
        [$par_page, $decalage, $page] = api_pagination();
        $ou = $r === 'presences' ? 'AND b.scanne_le IS NOT NULL' : '';
        $s = db()->prepare("SELECT b.jeton, b.cree_le, b.scanne_le, u.nom AS invite
            FROM badges b LEFT JOIN utilisateurs u ON u.id = b.utilisateur_id
            WHERE b.decor_id = ? $ou ORDER BY b.cree_le DESC LIMIT $par_page OFFSET $decalage");
        $s->execute([$d['id']]);
        $c = db()->prepare("SELECT COUNT(*) FROM badges b WHERE b.decor_id = ? $ou");
        $c->execute([$d['id']]);

        json_repondre([
            'ok' => true,
            'campagne' => $d['slug'],
            'page' => $page,
            'total' => (int) $c->fetchColumn(),
            ($r === 'presences' ? 'presences' : 'badges') => array_map(fn(array $b) => [
                // Le jeton est TRONQUÉ : entier, il permettrait de valider
                // une entrée à la place de l'invité. Il sert ici à
                // rapprocher deux exports, pas à ouvrir une porte.
                'reference' => substr((string) $b['jeton'], 0, 8),
                'invite' => $b['invite'] ?: null,
                'emis_le' => $b['cree_le'],
                'scanne_le' => $b['scanne_le'] ?: null,
            ], $s->fetchAll()),
        ]);
    }

    /* ---- liens courts : lire, et créer ---- */
    if ($r === 'liens' && $methode === 'GET') {
        json_repondre([
            'ok' => true,
            'liens' => array_map(fn(array $l) => [
                'code' => $l['code'],
                'adresse' => lien_court_url((string) $l['code']),
                'cible' => $l['cible'],
                'titre' => $l['titre'] ?: null,
                'clics' => (int) $l['clics'],
                'cree_le' => $l['cree_le'],
            ], liens_de((string) $u['id'])),
        ]);
    }
    if ($r === 'liens' && $methode === 'POST') {
        $corps = api_corps();
        $cible = trim((string) ($corps['cible'] ?? ''));
        $titre = trim((string) ($corps['titre'] ?? ''));
        if (!filter_var($cible, FILTER_VALIDATE_URL)) {
            api_erreur('« cible » doit être une adresse complète, avec http:// ou https://.', 422, 'cible_invalide');
        }
        $max = quota($u, 'liens_courts');
        if ($max >= 0 && count(liens_de((string) $u['id'])) >= $max) {
            api_erreur('Le quota de liens courts de votre offre est atteint.', 409, 'quota_atteint');
        }
        // Le même garde-fou qu'à l'écran : un partenaire ne redirige que
        // vers les domaines Wakabi. L'API n'est pas une porte dérobée.
        if (!droit($u, 'valider') && !redirection_autorisee($cible)) {
            api_erreur('La cible doit être un domaine Wakabi.', 422, 'domaine_refuse');
        }
        $code = creer_lien((string) $u['id'], $cible, $titre);
        json_repondre(['ok' => true, 'code' => $code, 'adresse' => lien_court_url($code)], 201);
    }

    api_erreur('Ressource inconnue : « ' . $r . " ». Voir la documentation à "
        . base_url() . '/index.php?p=api-doc', 404, 'ressource_inconnue');
}

/** Le corps JSON d'une requête, ou le formulaire si l'appelant préfère. */
function api_corps(): array
{
    $brut = file_get_contents('php://input') ?: '';
    if (trim($brut) === '') {
        return $_POST;
    }
    $j = json_decode($brut, true);
    return is_array($j) ? $j : $_POST;
}
