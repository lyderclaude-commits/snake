<?php
/**
 * La traduction automatique des articles, par DeepL.
 *
 * DeepL traduit le HTML tel quel (`tag_handling=html`) : la mise en forme,
 * les images et les liens d'un article ressortent intacts, seul le texte
 * change de langue. Le résultat est rangé dans les champs anglais de
 * l'article et marqué « traduction automatique » jusqu'à ce que quelqu'un
 * l'ait relu dans l'éditeur.
 *
 * La clé vit dans les réglages, jamais dans le code ni dans une page : une
 * clé lue par un tiers, c'est le quota du mois dépensé par quelqu'un d'autre.
 */

declare(strict_types=1);

const DEEPL_CIBLES = ['EN-GB' => 'Anglais britannique', 'EN-US' => 'Anglais américain'];

/** Une requête DeepL ne doit pas dépasser 128 Ko ; on reste large. */
const DEEPL_TAILLE_MAX = 90_000;

/** Combien d'articles un lot traduit : chacun peut peser plusieurs requêtes. */
const TRADUCTION_PAR_LOT = 3;

function deepl_cle(): string
{
    return trim(reglage('deepl_cle'));
}

function deepl_actif(): bool
{
    return deepl_cle() !== '';
}

/**
 * L'adresse de l'API : les clés gratuites finissent par `:fx` et ont leur
 * propre serveur. `config.php` peut imposer une autre adresse (la recette s'en sert).
 */
function deepl_url(string $chemin): string
{
    $base = (string) config('deepl_url', str_ends_with(deepl_cle(), ':fx') ? 'https://api-free.deepl.com' : 'https://api.deepl.com');
    return rtrim($base, '/') . '/v2/' . $chemin;
}

/**
 * @return array{ok: bool, code: int, donnees: array, erreur: string}
 */
function deepl_appel(string $chemin, ?array $corps = null): array
{
    if (!deepl_actif()) {
        return ['ok' => false, 'code' => 0, 'donnees' => [], 'erreur' => 'Aucune clé DeepL n’est réglée.'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'code' => 0, 'donnees' => [], 'erreur' => 'L’extension cURL manque à l’hébergement.'];
    }
    $ch = curl_init(deepl_url($chemin));
    $entetes = ['Authorization: DeepL-Auth-Key ' . deepl_cle(), 'User-Agent: Socle/' . SOCLE_VERSION];
    $options = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 8];
    if ($corps !== null) {
        $entetes[] = 'Content-Type: application/json';
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = json_encode($corps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $options[CURLOPT_HTTPHEADER] = $entetes;
    curl_setopt_array($ch, $options);
    $brut = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    $d = is_string($brut) ? json_decode($brut, true) : null;
    if ($code >= 200 && $code < 300 && is_array($d)) {
        return ['ok' => true, 'code' => $code, 'donnees' => $d, 'erreur' => ''];
    }
    // Les codes que DeepL documente, dits dans des mots qu'on peut transmettre tels quels.
    $erreur = match (true) {
        $err !== '' => 'DeepL ne répond pas (' . $err . ').',
        $code === 403 => 'DeepL refuse la clé : vérifiez-la dans votre compte DeepL (Compte → Clé d’API).',
        $code === 456 => 'Le quota DeepL du mois est épuisé. Il se renouvelle à la date anniversaire de l’abonnement.',
        $code === 429 => 'DeepL reçoit trop de demandes à la fois : réessayez dans une minute.',
        $code === 413 => 'Texte trop long pour une seule demande DeepL.',
        $code >= 500 => 'DeepL est momentanément indisponible (' . $code . ') : réessayez plus tard.',
        default => 'DeepL a répondu ' . $code . (is_array($d) && isset($d['message']) ? ' : ' . $d['message'] : '') . '.',
    };
    return ['ok' => false, 'code' => $code, 'donnees' => is_array($d) ? $d : [], 'erreur' => $erreur];
}

/** Ce qui a été consommé ce mois-ci. @return array{ok: bool, utilises: int, limite: int, erreur: string} */
function deepl_usage(): array
{
    $r = deepl_appel('usage');
    return ['ok' => $r['ok'], 'utilises' => (int) ($r['donnees']['character_count'] ?? 0),
        'limite' => (int) ($r['donnees']['character_limit'] ?? 0), 'erreur' => $r['erreur']];
}

/**
 * Traduit une liste de textes, du français vers l'anglais réglé.
 *
 * Les textes trop longs sont coupés entre deux blocs HTML, envoyés en
 * plusieurs morceaux, puis recollés : un article de 200 Ko passe comme un
 * article de 2 Ko. Rend les traductions dans le même ordre, ou une erreur.
 *
 * @param list<string> $textes
 * @return array{ok: bool, textes: list<string>, caracteres: int, erreur: string}
 */
function deepl_traduire(array $textes, bool $html): array
{
    // Chaque texte devient un ou plusieurs morceaux ; on retient d'où vient chacun.
    $morceaux = [];
    $origine = [];
    foreach (array_values($textes) as $i => $t) {
        foreach (deepl_decouper((string) $t) as $m) {
            $morceaux[] = $m;
            $origine[] = $i;
        }
    }
    $traduits = [];
    $caracteres = 0;
    $lot = [];
    $poids = 0;
    $envoyer = static function (array $lot) use ($html, &$traduits, &$caracteres): ?string {
        if (!$lot) {
            return null;
        }
        $corps = ['text' => $lot, 'source_lang' => 'FR', 'target_lang' => reglage('deepl_cible') ?: 'EN-GB'];
        if ($html) {
            $corps['tag_handling'] = 'html';
        }
        $r = deepl_appel('translate', $corps);
        if (!$r['ok']) {
            return $r['erreur'];
        }
        foreach ((array) ($r['donnees']['translations'] ?? []) as $t) {
            $traduits[] = (string) ($t['text'] ?? '');
        }
        $caracteres += array_sum(array_map('mb_strlen', $lot));
        return null;
    };
    foreach ($morceaux as $m) {
        // 50 textes au plus par requête, et un poids raisonnable.
        if ($lot && ($poids + strlen($m) > DEEPL_TAILLE_MAX || count($lot) >= 50)) {
            if ($e = $envoyer($lot)) {
                return ['ok' => false, 'textes' => [], 'caracteres' => $caracteres, 'erreur' => $e];
            }
            $lot = [];
            $poids = 0;
        }
        $lot[] = $m;
        $poids += strlen($m);
    }
    if ($e = $envoyer($lot)) {
        return ['ok' => false, 'textes' => [], 'caracteres' => $caracteres, 'erreur' => $e];
    }
    if (count($traduits) !== count($morceaux)) {
        return ['ok' => false, 'textes' => [], 'caracteres' => $caracteres, 'erreur' => 'DeepL a rendu une réponse incomplète.'];
    }
    $sortie = array_fill(0, count($textes), '');
    foreach ($traduits as $k => $t) {
        $sortie[$origine[$k]] .= $t;
    }
    return ['ok' => true, 'textes' => $sortie, 'caracteres' => $caracteres, 'erreur' => ''];
}

/** Coupe un long HTML après des fins de blocs, en morceaux de taille raisonnable. @return list<string> */
function deepl_decouper(string $t): array
{
    if (strlen($t) <= DEEPL_TAILLE_MAX) {
        return [$t];
    }
    $blocs = preg_split('#(?<=</p>|</h2>|</h3>|</h4>|</ul>|</ol>|</figure>|</blockquote>|</table>)#i', $t) ?: [$t];
    $sortie = [];
    $courant = '';
    foreach ($blocs as $b) {
        if ($courant !== '' && strlen($courant) + strlen($b) > DEEPL_TAILLE_MAX) {
            $sortie[] = $courant;
            $courant = '';
        }
        $courant .= $b;
    }
    if ($courant !== '') {
        $sortie[] = $courant;
    }
    return $sortie;
}

/**
 * La version anglaise d'un article, sans l'enregistrer.
 *
 * @return array{ok: bool, titre_en?: string, extrait_en?: string, contenu_en?: string, caracteres: int, erreur: string}
 */
function traduction_article(array $a): array
{
    $titre = deepl_traduire([(string) $a['titre'], (string) $a['extrait']], false);
    if (!$titre['ok']) {
        return ['ok' => false, 'caracteres' => 0, 'erreur' => $titre['erreur']];
    }
    $contenu = trim((string) $a['contenu']) === '' ? ['ok' => true, 'textes' => [''], 'caracteres' => 0]
        : deepl_traduire([(string) $a['contenu']], true);
    if (!$contenu['ok']) {
        return ['ok' => false, 'caracteres' => $titre['caracteres'], 'erreur' => $contenu['erreur']];
    }
    return [
        'ok' => true,
        'titre_en' => mb_substr(trim($titre['textes'][0]), 0, 240),
        'extrait_en' => mb_substr(trim($titre['textes'][1]), 0, 600),
        // Repassé au tamis : on ne publie jamais un HTML qu'on n'a pas nettoyé, même venu de DeepL.
        'contenu_en' => contenu_stocker($contenu['textes'][0]),
        'caracteres' => $titre['caracteres'] + $contenu['caracteres'],
        'erreur' => '',
    ];
}

/** Traduit et enregistre la version anglaise d'un article, marquée « automatique ». */
function traduire_article(int $id): array
{
    $a = un('SELECT * FROM articles WHERE id = ?', [$id]);
    if (!$a) {
        return ['ok' => false, 'caracteres' => 0, 'erreur' => 'Article introuvable.'];
    }
    $t = traduction_article($a);
    if ($t['ok']) {
        q('UPDATE articles SET titre_en = ?, extrait_en = ?, contenu_en = ?, traduction_auto = 1, traduit_le = ? WHERE id = ?',
            [$t['titre_en'], $t['extrait_en'], $t['contenu_en'], maintenant(), $id]);
    }
    return $t;
}

/** Les articles publiés qui n'ont pas encore d'anglais. */
function articles_a_traduire(): int
{
    return (int) valeur("SELECT COUNT(*) FROM articles WHERE statut = 'publie' AND (trim(titre_en) = '' OR trim(contenu_en) = '')");
}

/**
 * Traduit le lot suivant d'articles publiés sans anglais, du plus récent au plus ancien.
 *
 * @return array{ok: bool, faits: int, restants: int, caracteres: int, journal: list<string>, erreur: string}
 */
function traduire_lot(): array
{
    @set_time_limit(300);
    $liste = tous("SELECT id, titre FROM articles WHERE statut = 'publie' AND (trim(titre_en) = '' OR trim(contenu_en) = '')
        ORDER BY publie_le DESC LIMIT " . TRADUCTION_PAR_LOT);
    $res = ['ok' => true, 'faits' => 0, 'restants' => 0, 'caracteres' => 0, 'journal' => [], 'erreur' => ''];
    foreach ($liste as $a) {
        $t = traduire_article((int) $a['id']);
        $res['caracteres'] += $t['caracteres'];
        if (!$t['ok']) {
            // Clé refusée ou quota épuisé : inutile d'insister sur les articles suivants.
            $res['ok'] = false;
            $res['erreur'] = $t['erreur'];
            $res['journal'][] = '✗ ' . $a['titre'] . ' — ' . $t['erreur'];
            break;
        }
        $res['faits']++;
        $res['journal'][] = '→ ' . $a['titre'] . '  ⟶  ' . $t['titre_en'];
    }
    $res['restants'] = articles_a_traduire();
    return $res;
}

/**
 * La traduction à la publication, si elle est activée : un article publié
 * sans anglais en reçoit un. Une panne de DeepL ne bloque jamais la
 * publication — l'article paraît en français, et le lot le rattrapera.
 */
function traduire_si_auto(int $id): ?array
{
    if (reglage('deepl_auto') !== '1' || !deepl_actif()) {
        return null;
    }
    $a = un('SELECT statut, titre_en, contenu_en FROM articles WHERE id = ?', [$id]);
    if (!$a || $a['statut'] !== 'publie' || (trim((string) $a['titre_en']) !== '' && trim((string) $a['contenu_en']) !== '')) {
        return null;
    }
    return traduire_article($id);
}
