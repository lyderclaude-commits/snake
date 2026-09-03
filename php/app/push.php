<?php
/**
 * Les notifications push du navigateur.
 *
 * Une notification qui arrive quand le site est FERMÉ, c'est la seule façon
 * de reparler à quelqu'un qui a téléchargé un badge il y a trois semaines.
 * Ni compte à créer, ni application à installer : le navigateur suffit.
 *
 * Le protocole tient en trois pièces, et elles sont écrites ici — sans
 * Composer, comme le reste :
 *
 *  1. **VAPID** (RFC 8292) : un jeton JWT signé ES256 qui dit au service de
 *     push « c'est bien ce serveur ». Une paire de clés, générée une fois.
 *  2. **Le chiffrement** (RFC 8291) : le contenu est chiffré POUR le
 *     navigateur destinataire. Ni Google ni Mozilla ne peuvent le lire —
 *     ils ne transportent qu'une enveloppe close. C'est la partie sérieuse.
 *  3. **L'envoi** : un POST vers l'adresse que le navigateur a donnée.
 *
 * `scripts/verifier-push.ts` compare octet pour octet le chiffrement de ce
 * fichier à une implémentation Node indépendante, sur des clés et un sel
 * fixés. Deux implémentations d'un même RFC qui tombent d'accord, c'est la
 * seule preuve raisonnable qu'on peut se donner sans un vrai navigateur.
 */

declare(strict_types=1);

/** base64 « URL », sans remplissage : le format de tout ce protocole. */
function b64u(string $brut): string
{
    return rtrim(strtr(base64_encode($brut), '+/', '-_'), '=');
}

function b64u_lire(string $texte): string
{
    $t = strtr($texte, '-_', '+/');
    return (string) base64_decode($t . str_repeat('=', (4 - strlen($t) % 4) % 4), true);
}

/* ------------------------------------------------------------------ */
/* Les clés du serveur                                                 */
/* ------------------------------------------------------------------ */

/**
 * La paire VAPID, créée à la première demande et gardée dans les réglages.
 *
 * Elle ne doit JAMAIS changer une fois des abonnements enregistrés : la clé
 * publique est scellée dans chaque abonnement par le navigateur, et une
 * nouvelle paire les invaliderait tous d'un coup, sans message d'erreur —
 * les envois partiraient et personne ne recevrait rien.
 *
 * @return array{publique: string, privee: string, pem: string}
 */
function vapid(): array
{
    $pem = (string) (reglages_bdd(['vapid_pem'])['vapid_pem'] ?? '');

    if ($pem === '') {
        $cle = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if (!$cle) {
            throw new RuntimeException('OpenSSL ne sait pas créer de clé P-256 sur cet hébergement.');
        }
        openssl_pkey_export($cle, $neuf);
        reglages_bdd_poser(['vapid_pem' => $neuf]);
        /**
         * On RELIT après avoir écrit, et c'est le point de ce détour.
         *
         * Deux visiteurs peuvent arriver en même temps sur la toute
         * première page qui demande une clé. Les deux en fabriqueraient
         * une, et le second écraserait le premier — invalidant en silence
         * l'abonnement que le premier vient peut-être de prendre. En
         * relisant, les deux repartent avec la MÊME paire : celle qui est
         * réellement enregistrée.
         */
        $pem = (string) (reglages_bdd(['vapid_pem'])['vapid_pem'] ?? $neuf);
    }

    /**
     * Les deux autres valeurs sont DÉDUITES du PEM, jamais stockées.
     *
     * Les garder à part ouvrait la porte à un trio incohérent — une clé
     * publique d'une paire, une privée d'une autre — et les envois
     * seraient refusés par le service de push sans qu'aucun message
     * d'erreur ne dise pourquoi. Ici, l'incohérence est impossible.
     */
    $cle = openssl_pkey_get_private($pem);
    $d = $cle ? openssl_pkey_get_details($cle) : null;
    if (!isset($d['ec']['x'], $d['ec']['y'], $d['ec']['d'])) {
        throw new RuntimeException('La clé VAPID enregistrée est illisible.');
    }

    // Le point public non compressé : 0x04 puis X et Y sur 32 octets chacun.
    return [
        'publique' => b64u("\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT)
                                   . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT)),
        'privee' => b64u(str_pad($d['ec']['d'], 32, "\0", STR_PAD_LEFT)),
        'pem' => $pem,
    ];
}

/**
 * Le push est-il utilisable sur cet hébergement ?
 *
 * `curl` en fait partie : sans lui, `curl_init()` n'est pas une erreur
 * qu'on rattrape, c'est une erreur fatale qui rend une page blanche au
 * milieu d'un envoi. Mieux vaut le dire avant.
 */
function push_disponible(): bool
{
    return extension_loaded('openssl') && function_exists('hash_hkdf')
        && function_exists('openssl_pkey_derive') && function_exists('curl_init');
}

/**
 * Ce qui manque, nommément — pour un écran de diagnostic.
 *
 * « Ça ne marche pas » est le pire message d'erreur qui soit. Celui qui
 * lit celui-ci doit pouvoir le transmettre à son hébergeur tel quel.
 *
 * @return array<string, bool>
 */
function push_pre_requis(): array
{
    return [
        'OpenSSL' => extension_loaded('openssl'),
        'hash_hkdf()' => function_exists('hash_hkdf'),
        'openssl_pkey_derive()' => function_exists('openssl_pkey_derive'),
        'cURL' => function_exists('curl_init'),
        'HTTPS' => str_starts_with(base_url(), 'https://'),
    ];
}

/* ------------------------------------------------------------------ */
/* Le jeton VAPID                                                      */
/* ------------------------------------------------------------------ */

/**
 * Le JWT que le service de push exige, signé ES256.
 *
 * `aud` est l'ORIGINE du service (https://fcm.googleapis.com), pas
 * l'adresse complète de l'abonnement : un jeton signé pour la mauvaise
 * audience est refusé, et le message d'erreur ne le dit pas toujours.
 */
function vapid_jeton(string $endpoint, array $cles, string $sujet): string
{
    $p = parse_url($endpoint);
    $audience = ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '');

    $entete = b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $corps = b64u(json_encode([
        'aud' => $audience,
        // 12 heures : au-delà, plusieurs services refusent le jeton.
        'exp' => time() + 12 * 3600,
        'sub' => $sujet,
    ]));
    $aSigner = $entete . '.' . $corps;

    $cle = openssl_pkey_get_private($cles['pem']);
    if (!$cle || !openssl_sign($aSigner, $der, $cle, OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('La signature du jeton VAPID a échoué.');
    }
    return $aSigner . '.' . b64u(der_vers_brut($der));
}

/**
 * La signature d'OpenSSL est en DER ; JOSE la veut en r‖s brut.
 *
 * OpenSSL rend une séquence ASN.1 dont les entiers portent parfois un octet
 * nul de tête (pour rester positifs) et parfois non. Recopier les 64
 * derniers octets « en général » produit une signature juste la plupart du
 * temps — et l'échec, quand il vient, est indéchiffrable.
 */
function der_vers_brut(string $der): string
{
    $i = 0;
    if (($der[$i++] ?? '') !== "\x30") {
        throw new RuntimeException('Signature DER inattendue.');
    }
    $long = ord($der[$i++]);
    if ($long > 0x80) {
        $i += $long - 0x80;   // longueur sur plusieurs octets
    }

    $lire = function () use ($der, &$i): string {
        if (($der[$i++] ?? '') !== "\x02") {
            throw new RuntimeException('Entier DER attendu dans la signature.');
        }
        $n = ord($der[$i++]);
        $v = substr($der, $i, $n);
        $i += $n;
        // Retirer le zéro de tête, puis compléter à 32 octets.
        return str_pad(ltrim($v, "\0"), 32, "\0", STR_PAD_LEFT);
    };
    return $lire() . $lire();
}

/* ------------------------------------------------------------------ */
/* Le chiffrement du contenu (RFC 8291, aes128gcm)                     */
/* ------------------------------------------------------------------ */

/**
 * Chiffre un message POUR un abonnement donné.
 *
 * Le navigateur a fourni deux secrets à l'inscription : sa clé publique
 * `p256dh` et un secret d'authentification `auth`. On crée une paire
 * éphémère, on en dérive un secret partagé par ECDH, et tout le reste en
 * découle. Le service de push ne voit qu'un bloc opaque.
 *
 * `$eph` et `$sel` ne sont passés que par la vérification, qui doit pouvoir
 * rejouer exactement le même calcul qu'une autre implémentation. En
 * production ils sont tirés au hasard — réutiliser un sel affaiblirait le
 * chiffrement.
 */
function push_chiffrer(string $message, string $p256dh, string $auth, ?array $eph = null, ?string $sel = null): array
{
    $clientPub = b64u_lire($p256dh);
    $authSecret = b64u_lire($auth);
    if (strlen($clientPub) !== 65 || $clientPub[0] !== "\x04") {
        throw new RuntimeException('Clé publique d’abonnement inattendue.');
    }

    if ($eph === null) {
        $k = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        openssl_pkey_export($k, $pem);
        $d = openssl_pkey_get_details($k);
        $eph = [
            'pem' => $pem,
            'publique' => "\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT)
                                 . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT),
        ];
    }
    $sel ??= random_bytes(16);

    // ECDH : le secret partagé, que seuls les deux bouts peuvent calculer.
    $partage = openssl_pkey_derive(cle_publique_pem($clientPub), openssl_pkey_get_private($eph['pem']), 32);
    if ($partage === false) {
        throw new RuntimeException('Le calcul ECDH a échoué.');
    }

    // RFC 8291 §3.4 : le contexte lie les deux clés publiques au secret.
    $info = "WebPush: info\0" . $clientPub . $eph['publique'];
    $prk = hash_hkdf('sha256', $partage, 32, $info, $authSecret);

    $cek = hash_hkdf('sha256', $prk, 16, "Content-Encoding: aes128gcm\0", $sel);
    $nonce = hash_hkdf('sha256', $prk, 12, "Content-Encoding: nonce\0", $sel);

    // 0x02 marque la fin du contenu : c'est le délimiteur du dernier bloc.
    $clair = $message . "\x02";
    $chiffre = openssl_encrypt($clair, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    if ($chiffre === false) {
        throw new RuntimeException('Le chiffrement AES-GCM a échoué.');
    }

    // L'en-tête aes128gcm : sel, taille d'enregistrement, longueur de la
    // clé, la clé éphémère, puis le bloc chiffré.
    $corps = $sel . pack('N', 4096) . chr(strlen($eph['publique'])) . $eph['publique'] . $chiffre . $tag;
    return ['corps' => $corps, 'sel' => $sel, 'publique' => $eph['publique']];
}

/**
 * Un point P-256 brut, emballé en clé publique PEM pour OpenSSL.
 *
 * `openssl_pkey_derive` veut une ressource de clé ; on lui construit donc
 * la structure ASN.1 minimale autour des 65 octets du point. Les octets de
 * tête sont l'identifiant de l'algorithme et celui de la courbe — ils ne
 * changent jamais pour P-256.
 */
function cle_publique_pem(string $point): OpenSSLAsymmetricKey
{
    $prefixe = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"
             . "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00";
    $pem = "-----BEGIN PUBLIC KEY-----\n"
         . chunk_split(base64_encode($prefixe . $point), 64, "\n")
         . "-----END PUBLIC KEY-----\n";
    $cle = openssl_pkey_get_public($pem);
    if (!$cle) {
        throw new RuntimeException('Clé publique d’abonnement illisible.');
    }
    return $cle;
}

/* ------------------------------------------------------------------ */
/* L'envoi                                                             */
/* ------------------------------------------------------------------ */

/**
 * Envoie une notification à UN abonnement.
 *
 * @return array{ok: bool, code: int, mort: bool, message: string}
 *         `mort` distingue « ce navigateur ne répondra plus jamais » —
 *         désinstallé, notifications refusées — de « ça n'a pas marché
 *         cette fois ». Le premier cas se nettoie, le second se réessaie.
 */
function push_envoyer(array $abonnement, array $message): array
{
    if (!push_disponible()) {
        return ['ok' => false, 'code' => 0, 'mort' => false,
                'message' => 'Cet hébergement n’a pas les fonctions de chiffrement nécessaires.'];
    }

    try {
        $cles = vapid();
        $charge = json_encode($message, JSON_UNESCAPED_UNICODE);
        $chiffre = push_chiffrer($charge, (string) $abonnement['p256dh'], (string) $abonnement['auth']);
        $jeton = vapid_jeton((string) $abonnement['endpoint'], $cles, push_sujet());
    } catch (Throwable $e) {
        return ['ok' => false, 'code' => 0, 'mort' => false, 'message' => $e->getMessage()];
    }

    $entetes = [
        'Content-Type: application/octet-stream',
        'Content-Encoding: aes128gcm',
        'Content-Length: ' . strlen($chiffre['corps']),
        // 4 semaines : si le téléphone est éteint, le service garde le
        // message et le remet à l'allumage.
        'TTL: 2419200',
        'Urgency: normal',
        'Authorization: vapid t=' . $jeton . ', k=' . $cles['publique'],
    ];

    $ch = curl_init((string) $abonnement['endpoint']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $chiffre['corps'],
        CURLOPT_HTTPHEADER => $entetes,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $reponse = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    // 404 et 410 : l'abonnement n'existe plus. C'est normal et fréquent —
    // on vide son navigateur, on refuse les notifications, on change de
    // machine. Les garder ferait grossir la table de morts.
    return [
        'ok' => $code >= 200 && $code < 300,
        'code' => $code,
        'mort' => in_array($code, [404, 410], true),
        'message' => $err ?: (string) $reponse,
    ];
}

/**
 * Le `sub` du jeton VAPID : une façon de joindre l'exploitant du service.
 *
 * Les services de push le demandent pour pouvoir prévenir en cas de
 * problème. L'adresse expéditrice du transport e-mail fait très bien
 * l'affaire ; à défaut, l'adresse du site.
 */
function push_sujet(): string
{
    $de = reglages_courriel()['courriel_expediteur'] ?? '';
    return $de !== '' ? 'mailto:' . $de : base_url();
}

/* ------------------------------------------------------------------ */
/* Les abonnements                                                     */
/* ------------------------------------------------------------------ */

/**
 * L'empreinte de l'adresse, parce que l'adresse ne s'indexe pas.
 *
 * Un `endpoint` de Mozilla dépasse les 250 caractères ; la limite d'un
 * index utf8mb4 sur les MySQL qu'on trouve en hébergement mutualisé est de
 * 191. On indexe donc une empreinte de longueur fixe, et c'est par elle
 * qu'on retrouve une ligne — l'adresse complète reste stockée telle quelle
 * puisque c'est elle qu'on appellera.
 */
function push_empreinte(string $endpoint): string
{
    return hash('sha256', $endpoint);
}

function push_abonner(?string $utilisateur_id, string $endpoint, string $p256dh, string $auth, string $agent): void
{
    // L'adresse est unique : se réabonner depuis le même navigateur met à
    // jour, sinon la table doublerait à chaque visite.
    $s = db()->prepare('SELECT id FROM push WHERE empreinte = ?');
    $s->execute([push_empreinte($endpoint)]);
    $existe = $s->fetchColumn();

    if ($existe) {
        db()->prepare('UPDATE push SET utilisateur_id = ?, p256dh = ?, auth = ?, agent = ?, vu_le = ? WHERE id = ?')
            ->execute([$utilisateur_id, $p256dh, $auth, $agent, maintenant(), $existe]);
        return;
    }
    db()->prepare('INSERT INTO push (id, empreinte, utilisateur_id, endpoint, p256dh, auth, agent, cree_le, vu_le)
                   VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([nouvel_id(), push_empreinte($endpoint), $utilisateur_id, $endpoint,
                   $p256dh, $auth, $agent, maintenant(), maintenant()]);
}

function push_desabonner(string $endpoint): void
{
    db()->prepare('DELETE FROM push WHERE empreinte = ?')->execute([push_empreinte($endpoint)]);
}

function push_abonnement_de(string $endpoint): ?array
{
    $s = db()->prepare('SELECT * FROM push WHERE empreinte = ?');
    $s->execute([push_empreinte($endpoint)]);
    return $s->fetch() ?: null;
}

/**
 * À qui l'on peut écrire.
 *
 * Les segments sont volontairement peu nombreux et lisibles : « tout le
 * monde », « les organisateurs », « une ville », et — pour un
 * organisateur — « les gens qui ont fait un badge chez moi ». C'est le seul
 * segment qui ait une valeur commerciale, et le seul qu'un organisateur ait
 * le droit de viser.
 */
function push_destinataires(string $segment, ?string $auteur_id = null): array
{
    $sql = 'SELECT p.* FROM push p LEFT JOIN utilisateurs u ON u.id = p.utilisateur_id WHERE 1=1';
    $args = [];

    switch ($segment) {
        case 'mes-invites':
            $sql = "SELECT DISTINCT p.* FROM push p
                    JOIN badges b ON b.utilisateur_id = p.utilisateur_id
                    JOIN decors d ON d.id = b.decor_id
                    WHERE d.auteur_id = ?";
            $args = [$auteur_id];
            break;
        case 'organisateurs':
            $sql .= " AND u.role = 'partenaire'";
            break;
        case 'lome':
        case 'cotonou':
        case 'abidjan':
            $sql .= ' AND u.ville = ?';
            $args = [$segment];
            break;
        // 'tous' : aucune condition de plus.
    }

    $s = db()->prepare($sql);
    $s->execute($args);
    return $s->fetchAll();
}

const PUSH_SEGMENTS = [
    'tous' => 'Tout le monde',
    'organisateurs' => 'Les organisateurs',
    'lome' => 'Lomé',
    'cotonou' => 'Cotonou',
    'abidjan' => 'Abidjan',
];

/**
 * Envoie à un segment entier, et nettoie au passage.
 *
 * Les MOTIFS d'échec sont gardés, dédoublonnés. Un compteur qui dit
 * « 12 échecs » et rien d'autre ne permet ni de corriger ni même de
 * savoir s'il faut s'inquiéter : douze abonnements périmés et douze
 * refus d'authentification demandent deux gestes opposés.
 *
 * `personnes` compte les COMPTES atteints, pas les abonnements : quelqu'un
 * qui a autorisé les notifications sur son téléphone et sur son ordinateur
 * en a deux. Un seul chiffre gonflerait la portée d'un tiers sans que
 * personne ne s'en aperçoive — et c'est le chiffre qu'on cite ensuite en
 * réunion.
 *
 * @return array{envoyes: int, echecs: int, nettoyes: int, personnes: int,
 *               motifs: array<string, int>}
 */
function push_diffuser(array $abonnements, array $message): array
{
    $envoyes = $echecs = $nettoyes = 0;
    $motifs = [];
    $atteints = [];
    foreach ($abonnements as $a) {
        $r = push_envoyer($a, $message);
        if ($r['ok']) {
            $envoyes++;
            // Un abonnement sans compte — quelqu'un qui s'est abonné sans
            // se connecter — compte pour une personne à lui seul : son
            // empreinte fait office d'identité.
            $atteints[(string) ($a['utilisateur_id'] ?: 'anonyme:' . $a['id'])] = true;
            continue;
        }
        $echecs++;
        $cle = ($r['code'] ? 'HTTP ' . $r['code'] . ' — ' : '')
             . trim(mb_substr((string) $r['message'], 0, 160));
        $motifs[$cle] = ($motifs[$cle] ?? 0) + 1;
        if ($r['mort']) {
            push_desabonner((string) $a['endpoint']);
            $nettoyes++;
        }
    }
    return ['envoyes' => $envoyes, 'echecs' => $echecs, 'nettoyes' => $nettoyes,
            'personnes' => count($atteints), 'motifs' => $motifs];
}

/* ------------------------------------------------------------------ */
/* L'historique                                                        */
/* ------------------------------------------------------------------ */

/** Combien de diffusions par page. Au-delà, on cherche plutôt qu'on ne lit. */
const DIFFUSIONS_PAR_PAGE = 25;

/**
 * Garde la trace d'une diffusion.
 *
 * Écrite APRÈS l'envoi et avec ce que l'envoi a réellement produit, jamais
 * avec ce qu'on espérait : une ligne posée avant, « en cours », resterait
 * en cours pour toujours le jour où le script est coupé — et un historique
 * qui ment est pire que pas d'historique.
 *
 * Ne lève jamais : perdre la trace est regrettable, perdre l'envoi parce
 * que la trace a échoué serait absurde.
 */
function diffusion_enregistrer(?string $auteur_id, array $saisie, int $vises, array $rapport): void
{
    try {
        db()->prepare('INSERT INTO diffusions
            (id, auteur_id, segment, titre, corps, lien, abonnements, remises, personnes,
             echecs, nettoyes, motifs, cree_le)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')
            ->execute([
                nouvel_id(), $auteur_id, (string) $saisie['segment'],
                (string) $saisie['titre'], ($saisie['corps'] ?? '') ?: null,
                ($saisie['lien'] ?? '') ?: null,
                $vises, (int) $rapport['envoyes'], (int) ($rapport['personnes'] ?? 0),
                (int) $rapport['echecs'], (int) $rapport['nettoyes'],
                $rapport['motifs'] ? json_encode($rapport['motifs'], JSON_UNESCAPED_UNICODE) : null,
                maintenant(),
            ]);
    } catch (Throwable) {
        // Table absente sur une installation à moitié migrée, disque plein :
        // on laisse passer. L'envoi, lui, a bien eu lieu.
    }
}

/**
 * L'historique, le sien ou celui de tout le monde.
 *
 * Un organisateur ne voit QUE ses envois : les siens partent vers ses
 * invités, ceux du guide vers la base du guide, et lui montrer les seconds
 * lui apprendrait la taille et le rythme d'une audience qu'il n'a pas.
 */
function diffusions_lire(?string $auteur_id, int $page = 1): array
{
    $decalage = max(0, ($page - 1) * DIFFUSIONS_PAR_PAGE);
    $ou = $auteur_id === null ? '' : ' WHERE d.auteur_id = ?';
    $s = db()->prepare('SELECT d.*, u.nom AS auteur_nom FROM diffusions d
                        LEFT JOIN utilisateurs u ON u.id = d.auteur_id' . $ou
                       . ' ORDER BY d.cree_le DESC LIMIT ' . DIFFUSIONS_PAR_PAGE
                       . ' OFFSET ' . $decalage);
    $s->execute($auteur_id === null ? [] : [$auteur_id]);
    return $s->fetchAll();
}

function diffusions_combien(?string $auteur_id): int
{
    if ($auteur_id === null) {
        return (int) db()->query('SELECT COUNT(*) FROM diffusions')->fetchColumn();
    }
    $s = db()->prepare('SELECT COUNT(*) FROM diffusions WHERE auteur_id = ?');
    $s->execute([$auteur_id]);
    return (int) $s->fetchColumn();
}

/** Les personnes touchées par ce compte ce mois-ci — de quoi doser le rythme. */
function diffusions_du_mois(?string $auteur_id): array
{
    $debut = gmdate('Y-m-01\T00:00:00\Z');
    $ou = $auteur_id === null ? '' : ' AND auteur_id = ?';
    $s = db()->prepare('SELECT COUNT(*) AS envois, COALESCE(SUM(personnes), 0) AS personnes
                        FROM diffusions WHERE cree_le >= ?' . $ou);
    $s->execute($auteur_id === null ? [$debut] : [$debut, $auteur_id]);
    $r = $s->fetch() ?: [];
    return ['envois' => (int) ($r['envois'] ?? 0), 'personnes' => (int) ($r['personnes'] ?? 0)];
}

/** Les abonnements d'un compte — pour s'envoyer un essai à soi-même. */
function push_abonnements_de(string $utilisateur_id): array
{
    $s = db()->prepare('SELECT * FROM push WHERE utilisateur_id = ? ORDER BY vu_le DESC');
    $s->execute([$utilisateur_id]);
    return $s->fetchAll();
}
