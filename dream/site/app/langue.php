<?php
/**
 * Le site en deux langues.
 *
 * Deux sortes de textes, deux mécanismes :
 *
 *  - Les textes ÉCRITS DANS LES PAGES (titres, boutons, paragraphes des
 *    pages Services ou À propos) restent en français dans les vues. Au
 *    moment de servir une page anglaise, chaque texte est cherché dans le
 *    dictionnaire `app/langues/en.php` et remplacé. Les vues n'ont donc
 *    qu'une version, et une phrase oubliée dans le dictionnaire s'affiche
 *    en français au lieu de casser la page.
 *
 *  - Les textes SAISIS dans l'administration (articles, campagnes,
 *    services, témoignages, réglages) ont leur champ anglais. Vide, il
 *    retombe sur le français.
 */

declare(strict_types=1);

function dictionnaire(string $l = 'en'): array
{
    static $d = [];
    if (!isset($d[$l])) {
        $f = RACINE . '/app/langues/' . $l . '.php';
        $brut = is_file($f) ? (array) require $f : [];
        $d[$l] = [];
        foreach ($brut as $fr => $tr) {
            $d[$l][texte_cle((string) $fr)] = (string) $tr;
        }
    }
    return $d[$l];
}

/** La clé d'un texte : sans entités, espaces resserrés. */
function texte_cle(string $s): string
{
    return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8')));
}

/** Traduit une phrase du code (avec `%s` éventuels) dans la langue de la page. */
function t(string $fr, string|int ...$valeurs): string
{
    $s = $fr;
    if (langue() !== 'fr') {
        $s = dictionnaire(langue())[texte_cle($fr)] ?? $fr;
    }
    return $valeurs ? vsprintf($s, $valeurs) : $s;
}

/**
 * Passe une page rendue en français au dictionnaire.
 *
 * Chaque texte entre deux balises, et chaque attribut lisible (alt,
 * placeholder, title, aria-label, content), est remplacé s'il figure au
 * dictionnaire — EN ENTIER, jamais par morceaux : « Contact » se traduit,
 * « Contactez-nous » ne devient pas « Contactez-us ». Les scripts et les
 * feuilles de style ne sont pas touchés.
 */
function traduire_html(string $html, string $l): string
{
    $dico = dictionnaire($l);
    if (!$dico) {
        return $html;
    }
    $morceaux = preg_split('~(<script\b.*?</script>|<style\b.*?</style>|<textarea\b.*?</textarea>)~is', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
    foreach ($morceaux as $i => $m) {
        if ($i % 2 === 1) {
            continue;
        }
        $m = (string) preg_replace_callback('/>([^<>]+)</u', static function (array $x) use ($dico): string {
            $cle = texte_cle($x[1]);
            if ($cle === '' || !isset($dico[$cle])) {
                return $x[0];
            }
            preg_match('/^(\s*).*?(\s*)$/su', $x[1], $bords);
            return '>' . $bords[1] . e($dico[$cle]) . $bords[2] . '<';
        }, $m);
        $m = (string) preg_replace_callback('/\b(alt|placeholder|title|aria-label|content|data-invite)="([^"]*)"/u', static function (array $x) use ($dico): string {
            $cle = texte_cle($x[2]);
            return isset($dico[$cle]) ? $x[1] . '="' . e($dico[$cle]) . '"' : $x[0];
        }, $m);
        $morceaux[$i] = $m;
    }
    return implode('', $morceaux);
}

const MOIS_EN = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

/** Une date dans la langue de la page : « 4 avril 2024 » ou « April 4, 2024 ». */
function date_lang(?string $d): string
{
    if (langue() !== 'en') {
        return date_fr($d);
    }
    $t = $d ? strtotime($d) : false;
    return $t === false ? '' : MOIS_EN[(int) date('n', $t) - 1] . ' ' . date('j, Y', $t);
}

/* ------------------------------------------------------------------ */
/* Choix de la langue                                                  */
/* ------------------------------------------------------------------ */

/**
 * Le visiteur a-t-il choisi l'anglais, ou son navigateur le préfère-t-il ?
 *
 * Seul l'accueil redirige, et seulement la première fois : un lien partagé
 * vers une page française reste français, et Google — qui n'envoie pas de
 * langue — voit toujours le site français à l'adresse française.
 */
function langue_preferee(): ?string
{
    $c = (string) ($_COOKIE['lyd_langue'] ?? '');
    if (isset(LANGUES[$c])) {
        return $c;
    }
    $a = strtolower((string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
    if ($a === '') {
        return null;
    }
    // La première langue citée décide : « en-US,en;q=0.9,fr;q=0.8 » → anglais.
    return str_starts_with($a, 'en') ? 'en' : null;
}

function langue_retenir(string $l): void
{
    if (!headers_sent() && ($_COOKIE['lyd_langue'] ?? '') !== $l) {
        setcookie('lyd_langue', $l, ['expires' => time() + 365 * 86400, 'path' => base_chemin() . '/', 'samesite' => 'Lax']);
    }
}

/** Les deux adresses d'une même page, pour le sélecteur et pour les balises hreflang. */
function adresses_alternatives(string $interne): array
{
    $l = [];
    foreach (array_keys(LANGUES) as $code) {
        $l[$code] = url_absolue($interne, $code);
    }
    return $l;
}
