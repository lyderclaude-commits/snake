<?php
/**
 * Les notifications push de l'administration : « Nouvelle demande de… ».
 *
 * Le protocole est celui que l'administration Wakabi utilise déjà
 * (php/app/push.php), repris ici fonction pour fonction — VAPID (RFC 8292)
 * pour dire au service de push « c'est bien ce serveur », et le chiffrement
 * aes128gcm (RFC 8291) pour que ni Google ni Mozilla ne lisent le contenu.
 * Sans Composer : tout tient dans OpenSSL.
 *
 * Chaque membre de l'équipe abonne SES appareils depuis son profil. Une
 * demande de contact part à tous les appareils abonnés.
 */

declare(strict_types=1);

function b64u(string $brut): string
{
    return rtrim(strtr(base64_encode($brut), '+/', '-_'), '=');
}

function b64u_lire(string $texte): string
{
    $t = strtr($texte, '-_', '+/');
    return (string) base64_decode($t . str_repeat('=', (4 - strlen($t) % 4) % 4), true);
}

/**
 * La paire VAPID, créée une fois et gardée dans les réglages.
 *
 * Elle ne doit JAMAIS changer : la clé publique est scellée dans chaque
 * abonnement, une nouvelle paire les invaliderait tous sans un message.
 * On relit après avoir écrit, pour que deux premières demandes simultanées
 * repartent avec la même paire.
 *
 * @return array{publique: string, pem: string}
 */
function vapid(): array
{
    $pem = (string) valeur("SELECT valeur FROM reglages WHERE cle = 'vapid_pem'");
    if ($pem === '') {
        $cle = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if (!$cle) {
            throw new RuntimeException('OpenSSL ne sait pas créer de clé P-256 sur cet hébergement.');
        }
        openssl_pkey_export($cle, $neuf);
        q("INSERT OR IGNORE INTO reglages (cle, valeur) VALUES ('vapid_pem', ?)", [$neuf]);
        $pem = (string) valeur("SELECT valeur FROM reglages WHERE cle = 'vapid_pem'");
    }
    $d = openssl_pkey_get_details(openssl_pkey_get_private($pem));
    if (!isset($d['ec']['x'], $d['ec']['y'])) {
        throw new RuntimeException('La clé VAPID enregistrée est illisible.');
    }
    return [
        'publique' => b64u("\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT)),
        'pem' => $pem,
    ];
}

/** Ce que l'hébergement doit offrir, nommément — affiché tel quel dans le profil. */
function push_pre_requis(): array
{
    return [
        'OpenSSL' => extension_loaded('openssl'),
        'hash_hkdf()' => function_exists('hash_hkdf'),
        'openssl_pkey_derive()' => function_exists('openssl_pkey_derive'),
        'cURL' => function_exists('curl_init'),
    ];
}

function push_disponible(): bool
{
    return !in_array(false, push_pre_requis(), true);
}

/** Le JWT signé ES256 ; `aud` est l'ORIGINE du service de push, pas l'adresse complète. */
function vapid_jeton(string $endpoint, array $cles, string $sujet): string
{
    $p = parse_url($endpoint);
    $entete = b64u(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $corps = b64u(json_encode([
        'aud' => ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '') . (isset($p['port']) ? ':' . $p['port'] : ''),
        'exp' => time() + 12 * 3600,
        'sub' => $sujet,
    ]));
    $aSigner = $entete . '.' . $corps;
    if (!openssl_sign($aSigner, $der, openssl_pkey_get_private($cles['pem']), OPENSSL_ALGO_SHA256)) {
        throw new RuntimeException('La signature du jeton VAPID a échoué.');
    }
    return $aSigner . '.' . b64u(der_vers_brut($der));
}

/** La signature d'OpenSSL est en DER ; JOSE la veut en r‖s brut, 32 octets chacun. */
function der_vers_brut(string $der): string
{
    $i = 0;
    if (($der[$i++] ?? '') !== "\x30") {
        throw new RuntimeException('Signature DER inattendue.');
    }
    $long = ord($der[$i++]);
    if ($long > 0x80) {
        $i += $long - 0x80;
    }
    $lire = function () use ($der, &$i): string {
        if (($der[$i++] ?? '') !== "\x02") {
            throw new RuntimeException('Entier DER attendu dans la signature.');
        }
        $n = ord($der[$i++]);
        $v = substr($der, $i, $n);
        $i += $n;
        return str_pad(ltrim($v, "\0"), 32, "\0", STR_PAD_LEFT);
    };
    return $lire() . $lire();
}

/**
 * Chiffre un message POUR un abonnement (RFC 8291, aes128gcm).
 *
 * `$eph` et `$sel` ne servent qu'à la vérification ; en production ils sont
 * tirés au hasard à chaque envoi.
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
        $eph = ['pem' => $pem, 'publique' => "\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT)];
    }
    $sel ??= random_bytes(16);
    $partage = openssl_pkey_derive(cle_publique_pem($clientPub), openssl_pkey_get_private($eph['pem']), 32);
    if ($partage === false) {
        throw new RuntimeException('Le calcul ECDH a échoué.');
    }
    $prk = hash_hkdf('sha256', $partage, 32, "WebPush: info\0" . $clientPub . $eph['publique'], $authSecret);
    $cek = hash_hkdf('sha256', $prk, 16, "Content-Encoding: aes128gcm\0", $sel);
    $nonce = hash_hkdf('sha256', $prk, 12, "Content-Encoding: nonce\0", $sel);
    $chiffre = openssl_encrypt($message . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
    if ($chiffre === false) {
        throw new RuntimeException('Le chiffrement AES-GCM a échoué.');
    }
    return ['corps' => $sel . pack('N', 4096) . chr(strlen($eph['publique'])) . $eph['publique'] . $chiffre . $tag];
}

/** Un point P-256 brut, emballé en clé publique pour OpenSSL. */
function cle_publique_pem(string $point): OpenSSLAsymmetricKey
{
    $prefixe = "\x30\x59\x30\x13\x06\x07\x2a\x86\x48\xce\x3d\x02\x01\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07\x03\x42\x00";
    $cle = openssl_pkey_get_public("-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($prefixe . $point), 64, "\n") . "-----END PUBLIC KEY-----\n");
    if (!$cle) {
        throw new RuntimeException('Clé publique d’abonnement illisible.');
    }
    return $cle;
}

/** Le `sub` du jeton : une adresse où joindre l'exploitant. */
function push_sujet(): string
{
    return 'mailto:' . (string) config('contact_email', CONTACT_EMAIL);
}

/**
 * Envoie une notification à UN abonnement.
 *
 * @return array{ok: bool, code: int, mort: bool, message: string}
 *         `mort` : ce navigateur ne répondra plus jamais (404/410), l'abonnement se supprime.
 */
function push_envoyer(array $abonnement, array $message): array
{
    if (!push_disponible()) {
        return ['ok' => false, 'code' => 0, 'mort' => false, 'message' => 'fonctions de chiffrement absentes de l’hébergement'];
    }
    try {
        $cles = vapid();
        $chiffre = push_chiffrer(json_encode($message, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (string) $abonnement['p256dh'], (string) $abonnement['auth']);
        $jeton = vapid_jeton((string) $abonnement['endpoint'], $cles, push_sujet());
    } catch (Throwable $e) {
        return ['ok' => false, 'code' => 0, 'mort' => false, 'message' => $e->getMessage()];
    }
    $ch = curl_init((string) $abonnement['endpoint']);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $chiffre['corps'],
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'TTL: 86400',
            // Une demande de client attend : « high » réveille le téléphone tout de suite.
            'Urgency: high',
            'Authorization: vapid t=' . $jeton . ', k=' . $cles['publique'],
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $reponse = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return ['ok' => $code >= 200 && $code < 300, 'code' => $code, 'mort' => in_array($code, [404, 410], true),
        'message' => $err !== '' ? $err : mb_substr((string) $reponse, 0, 200)];
}

/** Envoie à une liste d'abonnements ; nettoie les morts, note le résultat. */
function push_diffuser(array $abonnements, array $message): array
{
    $bilan = ['ok' => 0, 'echecs' => 0, 'morts' => 0, 'details' => []];
    foreach ($abonnements as $ab) {
        $r = push_envoyer($ab, $message);
        if ($r['mort']) {
            q('DELETE FROM push_abonnements WHERE id = ?', [$ab['id']]);
            $bilan['morts']++;
            continue;
        }
        q('UPDATE push_abonnements SET dernier_envoi = ?, dernier_resultat = ? WHERE id = ?',
            [maintenant(), $r['ok'] ? 'reçu par le service (' . $r['code'] . ')' : 'échec : ' . ($r['code'] ?: '') . ' ' . $r['message'], $ab['id']]);
        $r['ok'] ? $bilan['ok']++ : $bilan['echecs']++;
        if (!$r['ok']) {
            $bilan['details'][] = trim(($r['code'] ?: '') . ' ' . $r['message']);
        }
    }
    return $bilan;
}

/** La notification d'une nouvelle demande, à tous les appareils de l'équipe encore actifs. */
function push_nouveau_message(array $m): array
{
    $abonnements = tous('SELECT p.* FROM push_abonnements p JOIN utilisateurs u ON u.id = p.utilisateur_id WHERE u.actif = 1');
    if (!$abonnements) {
        return ['ok' => 0, 'echecs' => 0, 'morts' => 0, 'details' => []];
    }
    $corps = trim(($m['besoin'] !== '' ? $m['besoin'] . ' · ' : '') . ($m['entreprise'] !== '' ? $m['entreprise'] . ' · ' : '')
        . ($m['message'] !== '' ? tronquer((string) $m['message'], 110) : $m['whatsapp']));
    return push_diffuser($abonnements, [
        'titre' => 'Nouvelle demande : ' . $m['nom'] . (($m['langue'] ?? 'fr') === 'en' ? ' (EN)' : ''),
        'corps' => $corps,
        'lien' => url_langue('admin/messages/' . (int) $m['id'], 'fr'),
        'tag' => 'message-' . (int) $m['id'],
        'icone' => url_langue('assets/images/icone-notification.png', 'fr'),
    ]);
}

/** Un résumé lisible du navigateur, pour reconnaître ses appareils dans la liste. */
function appareil_depuis_ua(string $ua): string
{
    $n = match (true) {
        str_contains($ua, 'Edg/') => 'Edge', str_contains($ua, 'OPR/') => 'Opera', str_contains($ua, 'Firefox/') => 'Firefox',
        str_contains($ua, 'Chrome/') => 'Chrome', str_contains($ua, 'Safari/') => 'Safari', default => 'Navigateur',
    };
    $s = match (true) {
        str_contains($ua, 'Android') => 'Android', str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
        str_contains($ua, 'Windows') => 'Windows', str_contains($ua, 'Mac OS') => 'macOS', str_contains($ua, 'Linux') => 'Linux', default => '',
    };
    return trim($n . ($s !== '' ? ' · ' . $s : ''));
}
