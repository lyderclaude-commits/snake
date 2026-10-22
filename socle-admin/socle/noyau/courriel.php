<?php
/**
 * Les e-mails du site : la copie de chaque demande de contact.
 *
 * Chaque demande part TOUJOURS au destinataire que la définition du site
 * déclare (`contact.destinataire`) — pas dans un réglage qu'on pourrait
 * vider par mégarde. Les réglages ne peuvent qu'AJOUTER des copies.
 *
 * Deux transports. Par défaut `mail()`, l'envoi de l'hébergeur : rien à
 * régler, mais ces messages finissent souvent dans les indésirables. Si un
 * serveur SMTP est réglé (la boîte contact@ de l'hébergeur, par exemple),
 * on passe par lui : le message est alors signé par le vrai serveur du
 * domaine, et arrive.
 *
 * Le résultat de chaque envoi est noté sur la demande : l'administration
 * dit « envoyé » ou « échec », et pourquoi. Un e-mail perdu sans trace est
 * un client perdu sans trace.
 */

declare(strict_types=1);

/** Le destinataire fixe, puis les copies réglées, sans doublon. */
function courriel_destinataires(): array
{
    $l = CONTACT_EMAIL !== '' ? [CONTACT_EMAIL] : [];
    foreach (preg_split('/[\s,;]+/', reglage('contact_copies'), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $a) {
        $a = strtolower(trim($a));
        if (filter_var($a, FILTER_VALIDATE_EMAIL)) {
            $l[] = $a;
        }
    }
    return array_values(array_unique($l));
}

/**
 * L'adresse d'expédition. Elle doit appartenir au domaine qui envoie :
 * un message « de » gmail.com envoyé par un serveur d'hébergeur est rejeté.
 */
function courriel_expediteur(): string
{
    $r = trim(reglage('courriel_expediteur'));
    if (filter_var($r, FILTER_VALIDATE_EMAIL)) {
        return $r;
    }
    if (reglage('smtp_hote') !== '' && filter_var(reglage('smtp_utilisateur'), FILTER_VALIDATE_EMAIL)) {
        return reglage('smtp_utilisateur');
    }
    $hote = (string) preg_replace('/^www\./', '', (string) parse_url(base_url(), PHP_URL_HOST));
    return 'site@' . (str_contains($hote, '.') ? $hote : (substr((string) strrchr(CONTACT_EMAIL, '@'), 1) ?: 'localhost.localdomain'));
}

function courriel_entete(string $s): string
{
    $s = str_replace(["\r", "\n"], ' ', $s);
    return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
}

/**
 * Envoie un message texte + HTML.
 *
 * @param list<string> $a
 * @return array{ok: bool, detail: string}
 */
function courriel_envoyer(array $a, string $sujet, string $texte, string $html, string $repondre_a = ''): array
{
    $de = courriel_expediteur();
    $frontiere = 'socle-' . bin2hex(random_bytes(8));
    $domaine = substr(strrchr($de, '@') ?: '@localhost', 1);
    $entetes = [
        'Date: ' . date('r'),
        'From: ' . courriel_entete(reglage('nom_site')) . ' <' . $de . '>',
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domaine . '>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $frontiere . '"',
        'X-Mailer: Socle/' . SOCLE_VERSION,
    ];
    if ($repondre_a !== '' && filter_var($repondre_a, FILTER_VALIDATE_EMAIL)) {
        $entetes[] = 'Reply-To: ' . $repondre_a;
    }
    $corps = "--$frontiere\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($texte)) . "\r\n"
        . "--$frontiere\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html)) . "\r\n--$frontiere--\r\n";

    if (reglage('smtp_hote') !== '') {
        return smtp_envoyer($de, $a, array_merge(['To: ' . implode(', ', $a), 'Subject: ' . courriel_entete($sujet)], $entetes), $corps);
    }
    if (!function_exists('mail')) {
        return ['ok' => false, 'detail' => 'la fonction mail() est désactivée chez l’hébergeur : réglez un serveur SMTP'];
    }
    // `-f` fixe l'expéditeur d'enveloppe : sans lui, beaucoup d'hébergeurs signent « www-data@serveur ».
    $ok = @mail(implode(', ', $a), courriel_entete($sujet), $corps, implode("\r\n", $entetes), '-f' . $de);
    return $ok
        ? ['ok' => true, 'detail' => 'remis au serveur de l’hébergeur (mail)']
        : ['ok' => false, 'detail' => 'le serveur de l’hébergeur a refusé le message (mail) : réglez un serveur SMTP'];
}

/**
 * Un client SMTP minimal : SSL (465) ou STARTTLS (587), AUTH LOGIN.
 *
 * @param list<string> $a
 * @return array{ok: bool, detail: string}
 */
function smtp_envoyer(string $de, array $a, array $entetes, string $corps): array
{
    $hote = reglage('smtp_hote');
    $port = (int) (reglage('smtp_port') ?: 587);
    $chiffrement = reglage('smtp_chiffrement') ?: 'tls';
    $f = @stream_socket_client(($chiffrement === 'ssl' ? 'ssl://' : 'tcp://') . $hote . ':' . $port, $no, $err, 15);
    if (!$f) {
        return ['ok' => false, 'detail' => "connexion à $hote:$port impossible ($err)"];
    }
    stream_set_timeout($f, 20);
    $lire = static function () use ($f): array {
        $tout = '';
        while (($l = fgets($f, 1024)) !== false) {
            $tout .= $l;
            if (strlen($l) < 4 || $l[3] === ' ') {
                break;
            }
        }
        return [(int) substr($tout, 0, 3), trim($tout)];
    };
    // `$nom` remplace la commande dans les erreurs : ni mot de passe ni message n'y apparaissent.
    $dire = static function (string $c, array $attendus, string $nom = '') use ($f, $lire): void {
        fwrite($f, $c . "\r\n");
        [$code, $rep] = $lire();
        if (!in_array($code, $attendus, true)) {
            throw new RuntimeException('« ' . ($nom ?: $c) . ' » → ' . $rep);
        }
    };
    try {
        [$code, $rep] = $lire();
        if ($code !== 220) {
            throw new RuntimeException('accueil inattendu : ' . $rep);
        }
        $nom = (string) (parse_url(base_url(), PHP_URL_HOST) ?: 'localhost');
        $dire('EHLO ' . $nom, [250]);
        if ($chiffrement === 'tls') {
            $dire('STARTTLS', [220]);
            if (!@stream_socket_enable_crypto($f, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new RuntimeException('le chiffrement TLS a échoué');
            }
            $dire('EHLO ' . $nom, [250]);
        }
        if (reglage('smtp_utilisateur') !== '') {
            $dire('AUTH LOGIN', [334]);
            $dire(base64_encode(reglage('smtp_utilisateur')), [334], 'identifiant');
            $dire(base64_encode(reglage('smtp_mdp')), [235], 'mot de passe');
        }
        $dire('MAIL FROM:<' . $de . '>', [250]);
        foreach ($a as $dest) {
            $dire('RCPT TO:<' . $dest . '>', [250, 251]);
        }
        $dire('DATA', [354]);
        // Une ligne qui commence par un point se double : sinon le serveur y verrait la fin du message.
        $message = implode("\r\n", $entetes) . "\r\n\r\n" . preg_replace('/^\./m', '..', $corps);
        $dire($message . "\r\n.", [250], 'envoi du message');
        @fwrite($f, "QUIT\r\n");
        fclose($f);
        return ['ok' => true, 'detail' => "accepté par $hote"];
    } catch (RuntimeException $e) {
        @fclose($f);
        return ['ok' => false, 'detail' => 'SMTP ' . $hote . ' : ' . $e->getMessage()];
    }
}
