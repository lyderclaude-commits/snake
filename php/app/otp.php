<?php
/**
 * La double authentification, pour les comptes de l'équipe.
 *
 * Un compte d'équipe ouvre le catalogue entier, les comptes clients et les
 * réglages. Un mot de passe réutilisé ailleurs, une fuite chez un autre
 * service, et c'est toute l'installation qui change de mains. Le second
 * facteur coûte six chiffres à la connexion et rend cette fuite inutile.
 *
 * Écrite à la main, comme le reste : TOTP (RFC 6238), c'est-à-dire un
 * HMAC-SHA1 du numéro de tranche de trente secondes, dont on garde six
 * chiffres. Une trentaine de lignes utiles, et aucune dépendance à
 * installer sur un mutualisé.
 *
 * Elle est réservée aux comptes INTERNES. La proposer à un organisateur
 * qui gère une soirée par trimestre, c'est fabriquer des comptes bloqués
 * un samedi soir parce que le téléphone a changé — et la seule issue
 * serait l'équipe, qu'on cherchait justement à ne pas déranger.
 */

declare(strict_types=1);

/** La tranche de temps, en secondes. Trente : la valeur que toutes les applications attendent. */
const OTP_PAS = 30;

/** Combien de tranches d'écart on tolère, avant et après. */
const OTP_TOLERANCE = 1;

/**
 * L'alphabet base32 (RFC 4648).
 *
 * C'est celui que lisent Google Authenticator, Aegis, FreeOTP et les
 * autres : le secret doit s'écrire ainsi pour être recopiable à la main
 * quand la caméra ne veut pas lire le QR.
 */
const OTP_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

/** Un secret neuf : vingt octets, la longueur recommandée pour SHA-1. */
function otp_secret_neuf(): string
{
    return otp_base32_encoder(random_bytes(20));
}

function otp_base32_encoder(string $octets): string
{
    $bits = '';
    foreach (str_split($octets) as $o) {
        $bits .= str_pad(decbin(ord($o)), 8, '0', STR_PAD_LEFT);
    }
    $sortie = '';
    foreach (str_split($bits, 5) as $morceau) {
        $sortie .= OTP_ALPHABET[bindec(str_pad($morceau, 5, '0', STR_PAD_RIGHT))];
    }
    return $sortie;
}

function otp_base32_decoder(string $texte): string
{
    $texte = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $texte) ?? '');
    $bits = '';
    foreach (str_split($texte) as $c) {
        $i = strpos(OTP_ALPHABET, $c);
        if ($i === false) {
            continue;
        }
        $bits .= str_pad(decbin($i), 5, '0', STR_PAD_LEFT);
    }
    $octets = '';
    foreach (str_split($bits, 8) as $morceau) {
        if (strlen($morceau) === 8) {
            $octets .= chr(bindec($morceau));
        }
    }
    return $octets;
}

/**
 * Le code attendu pour une tranche donnée.
 *
 * La « troncature dynamique » du RFC : les quatre derniers bits du HMAC
 * désignent l'endroit où lire quatre octets, dont on garde 31 bits. Ce
 * détour existe pour que le code ne dépende pas d'une portion fixe du
 * condensat — c'est écrit ainsi dans la norme, et toutes les applications
 * le font pareil.
 */
function otp_code(string $secret32, ?int $tranche = null): string
{
    $tranche ??= (int) floor(time() / OTP_PAS);
    $binaire = hash_hmac('sha1', pack('J', $tranche), otp_base32_decoder($secret32), true);
    $decalage = ord($binaire[19]) & 0x0F;
    $nombre = ((ord($binaire[$decalage]) & 0x7F) << 24)
        | (ord($binaire[$decalage + 1]) << 16)
        | (ord($binaire[$decalage + 2]) << 8)
        | ord($binaire[$decalage + 3]);
    return str_pad((string) ($nombre % 1000000), 6, '0', STR_PAD_LEFT);
}

/**
 * Ce code est-il valable maintenant ?
 *
 * On accepte une tranche avant et une après : l'horloge d'un téléphone
 * dérive, et refuser un code juste parce que le serveur a huit secondes
 * d'avance produit des blocages que personne ne sait diagnostiquer.
 * La comparaison est à temps constant, comme pour un mot de passe.
 */
function otp_verifier(string $secret32, string $saisi): bool
{
    $saisi = preg_replace('/\D/', '', $saisi) ?? '';
    if (strlen($saisi) !== 6 || $secret32 === '') {
        return false;
    }
    $maintenant = (int) floor(time() / OTP_PAS);
    for ($d = -OTP_TOLERANCE; $d <= OTP_TOLERANCE; $d++) {
        if (hash_equals(otp_code($secret32, $maintenant + $d), $saisi)) {
            return true;
        }
    }
    return false;
}

/**
 * L'adresse `otpauth://` que lit l'appareil photo.
 *
 * L'émetteur ET le nom du compte y figurent : quelqu'un qui gère trois
 * installations doit pouvoir distinguer les trois lignes dans son
 * application, sinon il saisit le code de la mauvaise.
 */
function otp_uri(array $u, string $secret32): string
{
    $hote = parse_url(base_url(), PHP_URL_HOST) ?: 'wakabi';
    $etiquette = rawurlencode('Wakabi Boost (' . $hote . ')') . ':' . rawurlencode((string) $u['email']);
    return 'otpauth://totp/' . $etiquette
        . '?secret=' . $secret32
        . '&issuer=' . rawurlencode('Wakabi Boost')
        . '&algorithm=SHA1&digits=6&period=' . OTP_PAS;
}

/** Ce compte a-t-il la double authentification en service ? */
function otp_actif(?array $u): bool
{
    return $u !== null && (int) ($u['otp_actif'] ?? 0) === 1 && ($u['otp_secret'] ?? '') !== '';
}

/** Qui peut l'activer : les comptes de la maison, et eux seuls. */
function otp_proposable(?array $u): bool
{
    return interne($u);
}
