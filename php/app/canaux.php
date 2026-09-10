<?php
/**
 * Les canaux de diffusion : Telegram, WhatsApp, et ce qui vient après.
 *
 * La régie savait écrire par un seul chemin, le courrier. Un organisateur
 * de Lomé, lui, parle à ses invités là où ils sont — et ce n'est pas leur
 * boîte aux lettres. Ce fichier ajoute les canaux SANS recopier la file
 * d'envoi : lots, reprises, quota et désabonnement restent partagés, et
 * seul le dernier mètre change.
 *
 * Deux plateformes, deux mondes — et il faut le dire clairement parce que
 * la promesse commerciale en dépend :
 *
 *   TELEGRAM fait exactement ce qu'on attend. Un bot administrateur d'une
 *   chaîne ou d'un groupe y publie sans limite d'abonnés, gratuitement, et
 *   peut écrire en tête-à-tête à qui lui a parlé une fois.
 *
 *   WHATSAPP ne le fait pas. L'API Cloud n'écrit qu'en tête-à-tête, à des
 *   numéros qui ont donné leur accord, avec un modèle approuvé par Meta, et
 *   chaque message est facturé. Sa « Groups API » plafonne à huit
 *   participants et les Chaînes n'ont aucune API : « diffuser dans un
 *   groupe WhatsApp » n'existe pas, et le promettre serait mentir.
 *
 * Aucune dépendance : le protocole des deux tient dans une requête HTTP.
 */

declare(strict_types=1);

/** Les canaux qu'on sait brancher, et ce que chacun sait faire. */
const CANAUX_GENRES = [
    'telegram' => [
        'nom' => 'Telegram',
        'sigle' => 'TG',
        'diffusion' => true,      // chaînes et groupes
        'direct' => true,         // tête-à-tête après un /start
        'payant' => false,
        'aide' => 'Un bot publie dans vos chaînes et vos groupes, et écrit à qui lui a parlé. '
                . 'Gratuit, immédiat, sans limite d’abonnés.',
    ],
    'whatsapp' => [
        'nom' => 'WhatsApp Business',
        'sigle' => 'WA',
        'diffusion' => false,
        'direct' => true,
        'payant' => true,
        'aide' => 'Tête-à-tête seulement, à des numéros qui ont donné leur accord, avec un modèle '
                . 'approuvé par Meta. Chaque message est facturé. Ni chaîne, ni groupe.',
    ],
];

/** Ce qu'une destination peut être. */
const CANAUX_DESTINATIONS = [
    'chaine' => 'Chaîne',
    'groupe' => 'Groupe',
    'direct' => 'Tête-à-tête',
];

/**
 * Le rythme d'envoi, par plateforme, en microsecondes.
 *
 * Telegram accepte une trentaine de messages par seconde au global, mais
 * seulement une vingtaine par MINUTE dans un même groupe. On tient le
 * rythme le plus prudent des deux : une campagne qui part en 429 doit être
 * rejouée entière, ce qui coûte bien plus cher que d'attendre.
 */
const CANAUX_RYTHME = [
    'telegram' => 60000,   // ~16 messages/s, sous la limite globale
    'whatsapp' => 100000,  // ~10/s, large sous les seuils Meta
];

/* ------------------------------------------------------------------ */
/* Le dépôt                                                            */
/* ------------------------------------------------------------------ */

function canal_par_id(string $id): ?array
{
    $s = db()->prepare('SELECT * FROM canaux WHERE id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

/** Les canaux d'un compte, avec leurs destinations et leurs abonnés. */
function canaux_de(string $proprietaire_id): array
{
    $s = db()->prepare('SELECT * FROM canaux WHERE proprietaire_id = ? ORDER BY cree_le');
    $s->execute([$proprietaire_id]);
    $canaux = $s->fetchAll();
    if (!$canaux) {
        return [];
    }

    /**
     * Une requête pour toutes les destinations, pas une par canal.
     *
     * Trois canaux feraient six allers-retours pour afficher un écran qui
     * n'en demande qu'un — la même leçon que le carnet d'adresses.
     */
    $ids = array_column($canaux, 'id');
    $trous = implode(',', array_fill(0, count($ids), '?'));

    $d = db()->prepare("SELECT * FROM destinations WHERE canal_id IN ($trous) AND actif = 1 ORDER BY nom");
    $d->execute($ids);
    $parCanal = [];
    foreach ($d->fetchAll() as $ligne) {
        $parCanal[$ligne['canal_id']][] = $ligne;
    }

    $a = db()->prepare("SELECT canal_id, COUNT(*) n FROM abonnes_canal
                        WHERE canal_id IN ($trous) AND actif = 1 GROUP BY canal_id");
    $a->execute($ids);
    $abonnes = [];
    foreach ($a->fetchAll() as $ligne) {
        $abonnes[$ligne['canal_id']] = (int) $ligne['n'];
    }

    foreach ($canaux as $i => $c) {
        $canaux[$i]['destinations'] = $parCanal[$c['id']] ?? [];
        $canaux[$i]['abonnes'] = $abonnes[$c['id']] ?? 0;
    }
    return $canaux;
}

function canal_creer(string $proprietaire_id, string $genre, string $nom, string $jeton, string $reference = ''): string
{
    $id = nouvel_id();
    db()->prepare('INSERT INTO canaux (id, proprietaire_id, genre, nom, jeton, reference, statut, cree_le, maj_le)
                   VALUES (?,?,?,?,?,?,?,?,?)')
        ->execute([$id, $proprietaire_id, $genre, $nom, $jeton, $reference ?: null,
                   'a_verifier', maintenant(), maintenant()]);
    return $id;
}

function canal_maj(string $id, array $champs): void
{
    $champs['maj_le'] = maintenant();
    $sets = implode(', ', array_map(fn($c) => "$c = ?", array_keys($champs)));
    $v = array_values($champs);
    $v[] = $id;
    db()->prepare("UPDATE canaux SET $sets WHERE id = ?")->execute($v);
}

function canal_supprimer(string $id): void
{
    db()->prepare('DELETE FROM destinations WHERE canal_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM abonnes_canal WHERE canal_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM canaux WHERE id = ?')->execute([$id]);
}

/** Pose une destination, ou met à jour celle qui porte déjà cette cible. */
function destination_poser(string $canal_id, string $genre, string $nom, string $cible, int $abonnes = 0): void
{
    $s = db()->prepare('SELECT id FROM destinations WHERE canal_id = ? AND cible = ?');
    $s->execute([$canal_id, $cible]);
    $existe = $s->fetchColumn();
    if ($existe) {
        db()->prepare('UPDATE destinations SET genre = ?, nom = ?, abonnes = ?, actif = 1, maj_le = ? WHERE id = ?')
            ->execute([$genre, $nom, $abonnes, maintenant(), $existe]);
        return;
    }
    db()->prepare('INSERT INTO destinations (id, canal_id, genre, nom, cible, abonnes, actif, cree_le, maj_le)
                   VALUES (?,?,?,?,?,?,1,?,?)')
        ->execute([nouvel_id(), $canal_id, $genre, $nom, $cible, $abonnes, maintenant(), maintenant()]);
}

function destination_par_id(string $id): ?array
{
    $s = db()->prepare('SELECT * FROM destinations WHERE id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

function destination_retirer(string $id): void
{
    db()->prepare('DELETE FROM destinations WHERE id = ?')->execute([$id]);
}

/**
 * Note qu'une personne est joignable en tête-à-tête sur ce canal.
 *
 * `accord_le` n'est pas décoratif : c'est la preuve qu'on présentera si
 * Meta la demande, et la date à partir de laquelle on a le droit d'écrire.
 */
function abonne_canal_poser(string $canal_id, string $cible, string $nom = '', string $source = 'direct'): void
{
    try {
        db()->prepare('INSERT INTO abonnes_canal (id, canal_id, cible, nom, source, accord_le, actif, cree_le)
                       VALUES (?,?,?,?,?,?,1,?)')
            ->execute([nouvel_id(), $canal_id, $cible, $nom ?: null, $source, maintenant(), maintenant()]);
    } catch (PDOException) {
        // Déjà abonné : on le réveille plutôt que d'en faire un doublon.
        db()->prepare('UPDATE abonnes_canal SET actif = 1, nom = COALESCE(?, nom) WHERE canal_id = ? AND cible = ?')
            ->execute([$nom ?: null, $canal_id, $cible]);
    }
}

function abonne_canal_retirer(string $canal_id, string $cible): void
{
    db()->prepare('UPDATE abonnes_canal SET actif = 0 WHERE canal_id = ? AND cible = ?')
        ->execute([$canal_id, $cible]);
}

/** Les cibles joignables en tête-à-tête, pour figer une liste d'envoi. */
function abonnes_canal(string $canal_id): array
{
    $s = db()->prepare('SELECT cible, nom FROM abonnes_canal WHERE canal_id = ? AND actif = 1 ORDER BY cree_le');
    $s->execute([$canal_id]);
    return $s->fetchAll();
}

/* ------------------------------------------------------------------ */
/* Le fil HTTP, commun aux deux plateformes                            */
/* ------------------------------------------------------------------ */

/**
 * Un appel JSON, avec repli quand cURL manque.
 *
 * Sur un mutualisé, `curl` est parfois désactivé. Le repli par flux fait
 * la même chose en moins bavard sur les erreurs — d'où l'ordre.
 *
 * @return array{ok: bool, code: int, corps: array, message: string}
 */
function canal_http(string $url, array $charge, array $entetes = [], int $delai = 12): array
{
    $json = json_encode($charge, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $entetes = array_merge(['Content-Type: application/json'], $entetes);

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $json,
            CURLOPT_HTTPHEADER => $entetes,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $delai,
            CURLOPT_CONNECTTIMEOUT => 6,
        ]);
        $corps = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($corps === false) {
            return ['ok' => false, 'code' => 0, 'corps' => [],
                    'message' => $err ?: 'Le serveur n’a pas répondu.'];
        }
    } else {
        $ctx = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => implode("\r\n", $entetes),
            'content' => $json,
            'timeout' => $delai,
            'ignore_errors' => true,
        ]]);
        $corps = @file_get_contents($url, false, $ctx);
        $code = 0;
        foreach ($http_response_header ?? [] as $l) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $l, $m)) {
                $code = (int) $m[1];
            }
        }
        if ($corps === false) {
            return ['ok' => false, 'code' => 0, 'corps' => [],
                    'message' => 'Le serveur n’a pas répondu, et cURL n’est pas disponible sur cet hébergement.'];
        }
    }

    $lu = json_decode((string) $corps, true);
    return [
        'ok' => $code >= 200 && $code < 300,
        'code' => $code,
        'corps' => is_array($lu) ? $lu : [],
        'message' => is_array($lu) ? '' : (string) $corps,
    ];
}

/* ------------------------------------------------------------------ */
/* Telegram                                                            */
/* ------------------------------------------------------------------ */

/**
 * La racine d'une plateforme.
 *
 * Écrite ici, et surchargeable par l'environnement — c'est la couture par
 * laquelle le vérifieur dresse un faux Telegram et un faux Meta. Sans
 * elle, la seule façon d'éprouver ce qu'on envoie serait d'écrire pour de
 * bon à des gens, ce qui n'est pas une façon de tester.
 */
function canal_racine(string $genre): string
{
    $defauts = [
        'telegram' => 'https://api.telegram.org',
        'whatsapp' => 'https://graph.facebook.com',
    ];
    $env = getenv('WAKABI_' . strtoupper($genre) . '_API');
    return is_string($env) && $env !== '' ? rtrim($env, '/') : ($defauts[$genre] ?? '');
}

/** L'adresse d'une méthode du Bot API. Le jeton est DANS le chemin. */
function telegram_url(string $jeton, string $methode): string
{
    return canal_racine('telegram') . '/bot' . rawurlencode($jeton) . '/' . $methode;
}

/**
 * @return array{ok: bool, corps: array, message: string}
 */
function telegram_appel(string $jeton, string $methode, array $charge = []): array
{
    $r = canal_http(telegram_url($jeton, $methode), $charge);
    $corps = $r['corps'];
    if (!empty($corps['ok'])) {
        return ['ok' => true, 'corps' => $corps['result'] ?? [], 'message' => ''];
    }
    /**
     * Telegram répond en clair quand il refuse : on relaie SA phrase.
     *
     * « Bad Request: chat not found » dit à l'organisateur ce qu'aucun
     * « échec » ne lui dira — que le bot n'est pas dans la chaîne.
     */
    $dit = (string) ($corps['description'] ?? $r['message'] ?: 'Telegram n’a pas répondu.');
    return ['ok' => false, 'corps' => $corps, 'message' => telegram_en_clair($dit)];
}

/** Les refus les plus fréquents, traduits en ce qu'il faut faire. */
function telegram_en_clair(string $dit): string
{
    return match (true) {
        str_contains($dit, 'chat not found') =>
            'Chaîne ou groupe introuvable : ajoutez le bot comme administrateur, puis réessayez. '
            . 'Pour une chaîne publique, l’identifiant s’écrit @nom_de_la_chaine.',
        str_contains($dit, 'bot was blocked') =>
            'Cette personne a bloqué le bot.',
        str_contains($dit, 'not enough rights') || str_contains($dit, 'need administrator') =>
            'Le bot est dans la chaîne mais n’a pas le droit d’y publier : passez-le administrateur.',
        str_contains($dit, 'Unauthorized') =>
            'Jeton refusé. Recopiez celui que @BotFather vous a donné, en entier.',
        str_contains($dit, 'Too Many Requests') =>
            'Telegram demande de ralentir. Le lot suivant reprendra où celui-ci s’arrête.',
        default => $dit,
    };
}

/**
 * Le bot est-il vivant, et comment s'appelle-t-il ?
 *
 * @return array{ok: bool, nom: string, message: string}
 */
function telegram_verifier(string $jeton): array
{
    $r = telegram_appel($jeton, 'getMe');
    if (!$r['ok']) {
        return ['ok' => false, 'nom' => '', 'message' => $r['message']];
    }
    $u = (string) ($r['corps']['username'] ?? '');
    return ['ok' => true, 'nom' => $u !== '' ? '@' . $u : 'bot', 'message' => ''];
}

/**
 * Reconnaît une destination à partir de ce que l'organisateur a collé.
 *
 * On accepte @nom, un lien t.me/nom, ou un identifiant numérique — parce
 * que c'est ce qu'on a sous la main, et qu'exiger la bonne forme est une
 * façon de faire échouer les gens sur un détail.
 */
function telegram_cible_propre(string $saisie): string
{
    $s = trim($saisie);
    if ($s === '') {
        return '';
    }
    if (preg_match('#(?:t\.me/|telegram\.me/)(?:s/)?([A-Za-z0-9_]{4,})#', $s, $m)) {
        return '@' . $m[1];
    }
    if (preg_match('/^-?\d{5,}$/', $s)) {
        return $s;
    }
    $s = ltrim($s, '@');
    return preg_match('/^[A-Za-z0-9_]{4,}$/', $s) ? '@' . $s : '';
}

/**
 * Interroge une chaîne ou un groupe : son nom, son genre, ses abonnés.
 *
 * @return array{ok: bool, nom: string, genre: string, abonnes: int, message: string}
 */
function telegram_destination(string $jeton, string $cible): array
{
    $r = telegram_appel($jeton, 'getChat', ['chat_id' => $cible]);
    if (!$r['ok']) {
        return ['ok' => false, 'nom' => '', 'genre' => '', 'abonnes' => 0, 'message' => $r['message']];
    }
    $c = $r['corps'];
    $type = (string) ($c['type'] ?? '');
    $genre = $type === 'channel' ? 'chaine' : ($type === 'private' ? 'direct' : 'groupe');
    $nom = (string) ($c['title'] ?? ($c['username'] ?? $cible));

    $n = telegram_appel($jeton, 'getChatMemberCount', ['chat_id' => $cible]);
    $abonnes = $n['ok'] ? (int) ($n['corps'] ?: 0) : 0;

    return ['ok' => true, 'nom' => $nom, 'genre' => $genre, 'abonnes' => $abonnes, 'message' => ''];
}

/**
 * Relève les personnes qui ont écrit au bot depuis la dernière fois.
 *
 * C'est ainsi qu'on obtient le droit de leur écrire : un `/start`, et rien
 * d'autre. Telegram garde ces messages 24 h, d'où le passage régulier par
 * le cron plutôt qu'un relevé à la demande.
 *
 * @return array{ok: bool, nouveaux: int, message: string}
 */
function telegram_relever_abonnes(array $canal): array
{
    $jeton = (string) $canal['jeton'];
    // `offset` : on repart après le dernier message vu, sinon Telegram
    // renvoie éternellement les mêmes et l'on recompte les mêmes personnes.
    $cle_offset = 'tg_offset_' . $canal['id'];
    $depuis = (int) (reglages_bdd([$cle_offset])[$cle_offset] ?? 0);
    $r = telegram_appel($jeton, 'getUpdates', [
        'offset' => $depuis > 0 ? $depuis : null,
        'limit' => 100,
        'timeout' => 0,
        'allowed_updates' => ['message', 'my_chat_member'],
    ]);
    if (!$r['ok']) {
        return ['ok' => false, 'nouveaux' => 0, 'message' => $r['message']];
    }

    $nouveaux = 0;
    $dernier = $depuis;
    foreach ($r['corps'] as $maj) {
        $dernier = max($dernier, (int) ($maj['update_id'] ?? 0) + 1);
        $chat = $maj['message']['chat'] ?? null;
        if (!is_array($chat) || ($chat['type'] ?? '') !== 'private') {
            continue;
        }
        $texte = trim((string) ($maj['message']['text'] ?? ''));
        $cible = (string) ($chat['id'] ?? '');
        if ($cible === '') {
            continue;
        }
        $nom = trim(((string) ($chat['first_name'] ?? '')) . ' ' . ((string) ($chat['last_name'] ?? '')));

        // « /stop » vaut désabonnement : le demander et ne pas l'obtenir
        // est le plus sûr moyen de se faire signaler.
        if (str_starts_with($texte, '/stop')) {
            abonne_canal_retirer((string) $canal['id'], $cible);
            telegram_appel($jeton, 'sendMessage', [
                'chat_id' => $cible,
                'text' => 'C’est noté : vous ne recevrez plus de messages. Écrivez /start pour revenir.',
            ]);
            continue;
        }
        abonne_canal_poser((string) $canal['id'], $cible, $nom, 'telegram');
        $nouveaux++;
        if (str_starts_with($texte, '/start')) {
            telegram_appel($jeton, 'sendMessage', [
                'chat_id' => $cible,
                'text' => 'Bienvenue ! Vous recevrez ici les rappels de nos événements. '
                        . 'Écrivez /stop à tout moment pour arrêter.',
            ]);
        }
    }
    if ($dernier > $depuis) {
        reglages_bdd_poser([$cle_offset => (string) $dernier]);
    }
    return ['ok' => true, 'nouveaux' => $nouveaux, 'message' => ''];
}

/* ------------------------------------------------------------------ */
/* WhatsApp                                                            */
/* ------------------------------------------------------------------ */

/**
 * L'API Cloud, version figée.
 *
 * Meta fait évoluer ses versions tous les trois mois et retire les
 * anciennes au bout de deux ans. La version est donc écrite ici, en un
 * seul endroit, plutôt que dispersée dans les adresses.
 */
const WHATSAPP_VERSION = 'v21.0';

function whatsapp_url(string $numero_id, string $chemin = 'messages'): string
{
    return canal_racine('whatsapp') . '/' . WHATSAPP_VERSION . '/' . rawurlencode($numero_id) . '/' . $chemin;
}

/**
 * @return array{ok: bool, corps: array, message: string}
 */
function whatsapp_appel(array $canal, array $charge, string $chemin = 'messages'): array
{
    $r = canal_http(
        whatsapp_url((string) $canal['reference'], $chemin),
        $charge,
        ['Authorization: Bearer ' . (string) $canal['jeton']]
    );
    if ($r['ok']) {
        return ['ok' => true, 'corps' => $r['corps'], 'message' => ''];
    }
    $dit = (string) ($r['corps']['error']['message'] ?? $r['message'] ?: 'Meta n’a pas répondu.');
    return ['ok' => false, 'corps' => $r['corps'], 'message' => whatsapp_en_clair($dit)];
}

function whatsapp_en_clair(string $dit): string
{
    return match (true) {
        str_contains($dit, 'access token') || str_contains($dit, 'OAuth') =>
            'Jeton refusé ou expiré. Reprenez-en un dans le tableau de bord Meta : '
            . 'un jeton temporaire ne vit que vingt-quatre heures.',
        str_contains($dit, 'template') && str_contains($dit, 'not exist') =>
            'Ce modèle n’existe pas dans votre compte Meta, ou il n’est pas encore approuvé.',
        str_contains($dit, 're-engagement') || str_contains($dit, '24 hour') =>
            'Hors de la fenêtre de vingt-quatre heures : seul un modèle approuvé peut être envoyé.',
        default => $dit,
    };
}

/** Le numéro tel que Meta l'attend : chiffres seulement, indicatif compris. */
function whatsapp_numero_propre(string $saisie): string
{
    $n = preg_replace('/\D+/', '', $saisie) ?? '';
    return strlen($n) >= 8 && strlen($n) <= 15 ? $n : '';
}

/**
 * Le numéro d'envoi répond-il ?
 *
 * @return array{ok: bool, nom: string, message: string}
 */
function whatsapp_verifier(array $canal): array
{
    $r = canal_http(
        canal_racine('whatsapp') . '/' . WHATSAPP_VERSION . '/' . rawurlencode((string) $canal['reference'])
        . '?fields=display_phone_number,verified_name',
        [],
        ['Authorization: Bearer ' . (string) $canal['jeton']]
    );
    if (!$r['ok']) {
        $dit = (string) ($r['corps']['error']['message'] ?? $r['message'] ?: 'Meta n’a pas répondu.');
        return ['ok' => false, 'nom' => '', 'message' => whatsapp_en_clair($dit)];
    }
    $nom = (string) ($r['corps']['verified_name'] ?? '');
    $num = (string) ($r['corps']['display_phone_number'] ?? '');
    return ['ok' => true, 'nom' => trim($nom . ' ' . $num) ?: 'numéro vérifié', 'message' => ''];
}

/* ------------------------------------------------------------------ */
/* Le transport : le dernier mètre, et lui seul                        */
/* ------------------------------------------------------------------ */

/**
 * Remet UN message sur un canal.
 *
 * Rend la même forme que la session courriel — `ok`, `message`,
 * `reprendre` — pour que la boucle d'envoi ne sache rien de la plateforme.
 * C'est ce qui permet d'ajouter un canal sans toucher aux lots, aux
 * reprises, au quota ni au désabonnement.
 *
 * @return array{ok: bool, message: string, reprendre: bool}
 */
function canal_remettre(array $canal, string $cible, array $message): array
{
    return match ((string) $canal['genre']) {
        'telegram' => telegram_remettre($canal, $cible, $message),
        'whatsapp' => whatsapp_remettre($canal, $cible, $message),
        default => ['ok' => false, 'message' => 'Canal inconnu.', 'reprendre' => false],
    };
}

/** Le texte d'un message, mis en forme pour Telegram. */
function telegram_texte(array $message): string
{
    $t = trim((string) ($message['titre'] ?? ''));
    $c = trim((string) ($message['corps'] ?? ''));
    // `HTML` plutôt que Markdown : le Markdown de Telegram casse dès qu'un
    // texte contient un tiret bas ou une astérisque, ce qu'un organisateur
    // écrit sans y penser.
    $h = fn(string $s): string => htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');
    return ($t !== '' ? '<b>' . $h($t) . '</b>' . "\n\n" : '') . $h($c);
}

function telegram_remettre(array $canal, string $cible, array $message): array
{
    $charge = [
        'chat_id' => $cible,
        'text' => telegram_texte($message),
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => false,
    ];
    $lien = trim((string) ($message['lien'] ?? ''));
    if ($lien !== '' && str_starts_with($lien, 'http')) {
        // Un bouton plutôt qu'une URL nue : c'est plus grand sous le pouce,
        // et Telegram le garde visible même quand le texte est replié.
        $charge['reply_markup'] = ['inline_keyboard' => [[[
            'text' => (string) ($message['libelle'] ?? 'Ouvrir'),
            'url' => $lien,
        ]]]];
    }

    $r = telegram_appel((string) $canal['jeton'], 'sendMessage', $charge);
    if ($r['ok']) {
        return ['ok' => true, 'message' => 'Remis à Telegram.', 'reprendre' => false];
    }
    return ['ok' => false, 'message' => $r['message'],
            'reprendre' => !telegram_definitif((string) $r['message'])];
}

/**
 * Ce qui se retente, et ce qui ne se retente pas.
 *
 * Un bot bloqué ou une chaîne introuvable ne s'arrangeront pas tout
 * seuls ; une limite de débit, si. S'acharner sur le premier cas fait
 * perdre trois essais et n'apporte rien.
 *
 * Une fonction plutôt qu'un test en ligne : l'écran des échecs pose la
 * même question, des jours plus tard, à partir du message conservé. Deux
 * listes de mots-clés finiraient par diverger, et l'écran proposerait de
 * relancer ce que la file, elle, refuserait de reprendre.
 */
function telegram_definitif(string $message): bool
{
    return str_contains($message, 'bloqué')
        || str_contains($message, 'introuvable')
        || str_contains($message, 'administrateur');
}

/**
 * WhatsApp : un modèle approuvé, et ses variables.
 *
 * Hors de la fenêtre de vingt-quatre heures — c'est-à-dire toujours, pour
 * un rappel d'événement — seul un modèle passe. Le texte libre écrit dans
 * la régie n'est donc PAS envoyé tel quel : il remplit les variables du
 * modèle que Meta a approuvé, dans l'ordre.
 */
function whatsapp_remettre(array $canal, string $cible, array $message): array
{
    $modele = trim((string) ($message['modele'] ?? ''));
    if ($modele === '') {
        return ['ok' => false, 'reprendre' => false,
                'message' => 'Aucun modèle WhatsApp choisi : Meta refuse le texte libre hors des '
                           . 'vingt-quatre heures qui suivent un message du destinataire.'];
    }

    $variables = [];
    foreach ([$message['titre'] ?? '', $message['corps'] ?? '', $message['lien'] ?? ''] as $v) {
        $v = trim((string) $v);
        if ($v !== '') {
            // Meta refuse les sauts de ligne dans une variable de modèle.
            $variables[] = ['type' => 'text', 'text' => preg_replace('/\s+/u', ' ', $v)];
        }
    }

    $charge = [
        'messaging_product' => 'whatsapp',
        'to' => $cible,
        'type' => 'template',
        'template' => [
            'name' => $modele,
            'language' => ['code' => (string) ($message['langue'] ?? 'fr')],
        ],
    ];
    if ($variables) {
        $charge['template']['components'] = [['type' => 'body', 'parameters' => $variables]];
    }

    $r = whatsapp_appel($canal, $charge);
    if ($r['ok']) {
        return ['ok' => true, 'message' => 'Remis à WhatsApp.', 'reprendre' => false];
    }
    return ['ok' => false, 'message' => $r['message'],
            'reprendre' => !whatsapp_definitif((string) $r['message'])];
}

/** Le pendant WhatsApp : un modèle refusé ou un jeton mort ne s'arrangent pas. */
function whatsapp_definitif(string $message): bool
{
    return str_contains($message, 'modèle') || str_contains($message, 'Jeton refusé');
}

/* ------------------------------------------------------------------ */
/* Les quotas                                                          */
/* ------------------------------------------------------------------ */

/** Combien de messages sont partis ce mois-ci sur ce genre de canal. */
function envois_canal_du_mois(string $auteur_id, string $genre): int
{
    $debut = gmdate('Y-m-01\T00:00:00\Z');
    $s = db()->prepare("SELECT COUNT(*) FROM envois_email e
                        JOIN campagnes_email c ON c.id = e.campagne_id
                        WHERE c.auteur_id = ? AND e.canal = ? AND e.statut = 'envoye' AND e.envoye_le >= ?");
    $s->execute([$auteur_id, $genre, $debut]);
    return (int) $s->fetchColumn();
}

/**
 * Le quota d'un canal tient-il encore ?
 *
 * Même forme et même moment que le quota e-mail — opposé quand on FIGE la
 * liste, pas à chaque message. Telegram étant gratuit, sa limite protège
 * l'installation, pas la facture : elle est large.
 *
 * @return array{ok: bool, max: int, utilises: int, reste: int, message: string}
 */
function quota_canal(array $u, string $genre, int $demandes = 0): array
{
    $cle = $genre . '_par_mois';
    $max = quota($u, $cle);
    $utilises = envois_canal_du_mois((string) $u['id'], $genre);
    $reste = $max < 0 ? -1 : max(0, $max - $utilises);
    $nom = CANAUX_GENRES[$genre]['nom'] ?? $genre;

    if ($max < 0) {
        return ['ok' => true, 'max' => -1, 'utilises' => $utilises, 'reste' => -1, 'message' => ''];
    }
    if ($max === 0) {
        return ['ok' => false, 'max' => 0, 'utilises' => $utilises, 'reste' => 0,
                'message' => 'Votre offre ne comprend pas ' . $nom . '.'];
    }
    if ($demandes > $reste) {
        return ['ok' => false, 'max' => $max, 'utilises' => $utilises, 'reste' => $reste,
                'message' => sprintf(
                    'Ce message toucherait %d destinations sur %s, et il vous en reste %d ce mois-ci '
                    . '(offre %s : %d par mois).',
                    $demandes, $nom, $reste, formule_libelle($u['formule'] ?? null), $max
                )];
    }
    return ['ok' => true, 'max' => $max, 'utilises' => $utilises, 'reste' => $reste, 'message' => ''];
}
