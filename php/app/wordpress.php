<?php
/**
 * Les articles du guide, et comment ils arrivent ici.
 *
 * Le blog de wakabileguide.com allait les chercher DEPUIS LE NAVIGATEUR :
 * la page arrivait vide, un script appelait `wp-json/wp/v2/posts?_embed`,
 * puis fabriquait les cartes. Le référencement en souffrait d'abord : un
 * moteur de recherche recevait une page vide. Puis la lecture côté serveur
 * a réglé cela, en affichant au fil de l'eau les articles de WordPress à
 * côté des nôtres.
 *
 * C'est fini, et dans l'autre sens. À l'affichage, le blog ne lit PLUS
 * WordPress : tout ce qui paraît ici est un article de la table `articles`,
 * avec son texte à nous, ses images hébergées ici, ses dates propres. Ce
 * qui vient du guide est IMPORTÉ — à la demande, depuis l'écran du blog de
 * l'administration — et une fois importé, il ne se distingue d'un article
 * écrit ici d'aucune façon visible pour un lecteur.
 *
 * Ce qui reste dans ce fichier est le matériel de l'import : le client de
 * l'API (`wp_get`), la traduction du HTML de WordPress vers le texte du
 * projet (`wp_html_vers_texte`), et le rapatriement des images. Un article
 * non importé n'apparaît nulle part côté public : sa seule existence pour
 * le site est la liste du panneau d'import.
 *
 * Si WordPress ne répond pas pendant un import, on le dit à l'écran ; le
 * site, lui, ne dépend plus de lui pour rien.
 */

declare(strict_types=1);

/** Là où vit l'API tant que personne n'a dit le contraire. */
const WP_RACINE_DEFAUT = 'https://admin.wakabileguide.com/wp-json/wp/v2';

/** Combien de temps un cache est frais. Dix minutes : un blog n'est pas une horloge. */
const WP_FRAICHEUR = 600;

/**
 * La taille d'un lot, et combien on en demande au plus.
 *
 * Vingt articles par appel : `_embed` traîne derrière chaque article ses
 * médias et ses catégories, et demander cent objets à la fois ferait
 * payer à WordPress — et à la connexion — dix fois ce qu'on utilise.
 *
 * Dix lots au plus, soit deux cents articles proposés à l'import. Au
 * delà, le panneau coupe : c'est une limite écrite, pas une surprise, et
 * elle borne le temps qu'un écran d'administration peut passer à
 * attendre WordPress.
 */
const WP_PAR_LOT = 20;
const WP_LOTS_MAX = 10;

/** Le poids au delà duquel une image du guide est jugée trop lourde pour venir ici. */
const WP_IMAGE_MAX = 6 * 1024 * 1024;

/** Combien d'articles un clic d'import traite au plus. */
const WP_IMPORT_LOT_MAX = 25;

/**
 * Après une panne, on laisse WordPress tranquille cinq minutes.
 *
 * Sans ce répit, un hébergement sans sortie réseau — cas banal sur un
 * mutualisé — ferait attendre le délai de connexion À CHAQUE lecture,
 * pour toujours échouer pareil. Sur l'écran d'import, le message le dit ;
 * côté public, personne n'attend plus rien de WordPress.
 */
const WP_REPIT = 300;

/**
 * La racine de l'API.
 *
 * Trois sources, dans cet ordre : ce que l'équipe a réglé en ligne, ce que
 * `config.php` impose, puis le défaut. Le réglage en ligne passe devant
 * parce que c'est le seul qui se corrige sans FTP le jour où l'adresse de
 * WordPress change.
 *
 * Vide, c'est un arrêt volontaire : plus aucun appel ne part.
 */
function wp_racine(): string
{
    static $vu = null;
    static $version = -1;
    if ($vu !== null && $version === reglages_version()) {
        return $vu;
    }
    $lu = reglages_bdd(['wp_racine'])['wp_racine'] ?? null;
    if ($lu === null) {
        $lu = reglage('wp_racine', WP_RACINE_DEFAUT);
    }
    $version = reglages_version();
    return $vu = rtrim(trim((string) $lu), '/');
}

/** La source du guide est-elle branchée ? */
function wp_actif(): bool
{
    return wp_racine() !== '';
}

/**
 * L'hôte dont on accepte les images.
 *
 * Le projet ne rend d'ordinaire que les images qu'il héberge, et pour de
 * bonnes raisons écrites dans `texte.php` : une adresse extérieure, c'est
 * un mouchard posé sur la page de quelqu'un d'autre et une image qui
 * meurt le jour où le site d'origine ferme.
 *
 * L'exception est BORNÉE aux adresses du guide : l'hôte de l'API, le
 * domaine qui le porte, et leurs sous-domaines. Pas « n'importe quelle
 * adresse » — l'hôte du guide et lui seul. La différence est tout le
 * sujet : on fait confiance à son propre serveur, pas au web entier.
 *
 * Pourquoi l'élargissement : la médiathèque du guide sert des images
 * depuis l'API (`admin.wakabileguide.com`) depuis l'apex
 * (`wakabileguide.com`) selon le billet. N'accepter que l'hôte exact de
 * l'API laissait la moitié du catalogue en images refusées, et c'est le
 * même éditeur qui les a posées — le domaine de base est la frontière, pas
 * le sous-domaine.
 */
function wp_hote(): string
{
    return strtolower((string) parse_url(wp_racine(), PHP_URL_HOST));
}

/** Le domaine de base de l'API : les deux derniers étiquettes de son hôte. */
function wp_domaine(): string
{
    $t = explode('.', wp_hote());
    $n = count($t);
    return $n >= 2 ? $t[$n - 2] . '.' . $t[$n - 1] : wp_hote();
}

/** Cette adresse d'image vient-elle bien du guide ? */
function wp_image_permise(string $url): bool
{
    $u = parse_url(trim($url));
    if (!$u || !in_array(strtolower($u['scheme'] ?? ''), ['http', 'https'], true)) {
        return false;
    }
    $h = strtolower(preg_replace('/\.$/', '', (string) ($u['host'] ?? '')));
    if ($h === '') {
        return false;
    }
    $ref = wp_hote();
    $dom = wp_domaine();
    if ($ref === '') {
        return false;
    }
    $sous_ref = str_ends_with($h, '.' . $ref);
    $sous_dom = $dom === $ref ? false : (str_ends_with($h, '.' . $dom) || $h === $dom);
    return $h === $ref || $sous_ref || $sous_dom;
}

function dossier_cache(): string
{
    $d = dossier_donnees() . '/cache';
    if (!is_dir($d)) {
        @mkdir($d, 0775, true);
    }
    return $d;
}

/** WordPress vient-il d'échouer ? Alors on ne le rappelle pas tout de suite. */
function wp_en_panne(): bool
{
    $f = dossier_cache() . '/wp-panne';
    return is_file($f) && (time() - (int) @filemtime($f)) < WP_REPIT;
}

function wp_panne_noter(bool $echec): void
{
    $f = dossier_cache() . '/wp-panne';
    if ($echec) {
        @file_put_contents($f, (string) time());
    } elseif (is_file($f)) {
        @unlink($f);
    }
}

/**
 * Un GET, avec repli quand cURL manque.
 *
 * Sur un mutualisé, `curl` est parfois désactivé ; le repli par flux fait
 * la même chose en moins bavard sur les erreurs, d'où l'ordre. Les
 * en-têtes de réponse l'accompagnent : l'import lit `X-WP-Total` comme
 * la liste du blog le faisait.
 *
 * @return array{ok: bool, code: int, corps: mixed, pages: int, total: int}
 */
function wp_get(string $url, int $delai = 6): array
{
    $brut = null;
    $code = 0;
    $entetes = [];

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => $delai,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_USERAGENT => 'Wakabi/' . VERSION,
        ]);
        $rep = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $taille = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        if (is_string($rep)) {
            $entetes = explode("\r\n", substr($rep, 0, $taille));
            $brut = substr($rep, $taille);
        }
    } else {
        $ctx = stream_context_create(['http' => [
            'method' => 'GET',
            'timeout' => $delai,
            'ignore_errors' => true,
            'header' => 'User-Agent: Wakabi/' . VERSION,
        ]]);
        $brut = @file_get_contents($url, false, $ctx);
        $entetes = $http_response_header ?? [];
        foreach ($entetes as $l) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $l, $m)) {
                $code = (int) $m[1];
            }
        }
    }

    /**
     * `X-WP-Total` dit combien d'articles répondent, `X-WP-TotalPages`
     * combien de pages ils font. Sans le premier, le panneau d'import
     * ne saurait pas combien d'articles proposer au total.
     */
    $pages = 0;
    $total = 0;
    foreach ($entetes as $l) {
        if (stripos($l, 'x-wp-totalpages:') === 0) {
            $pages = (int) trim(substr($l, 16));
        } elseif (stripos($l, 'x-wp-total:') === 0) {
            $total = (int) trim(substr($l, 11));
        }
    }

    $corps = is_string($brut) ? json_decode($brut, true) : null;
    return [
        'ok' => $code >= 200 && $code < 300 && is_array($corps),
        'code' => $code,
        'corps' => is_array($corps) ? $corps : [],
        'pages' => $pages,
        'total' => $total,
    ];
}

/**
 * Un GET qui ne parle pas JSON : le corps binaire d'une image.
 *
 * Même client que `wp_get`, mais la réponse est gardée entière — c'est
 * l'image qu'on vient rapporter, pas un document à décoder. Le délai est
 * bien plus long que la lecture live, ET VOLONTAIREMENT : une image de
 * la médiathèque du guide peut peser des mégaoctets, et cette requête
 * n'est plus sur le chemin d'aucune page publique — elle part au clic
 * d'un bouton tenu par l'équipe, qui voit le chargement patienter.
 *
 * @return array{ok: bool, code: int, corps: ?string, type: string}
 */
function wp_telecharge(string $url, int $delai = 20): array
{
    $brut = null;
    $code = 0;
    $type = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $delai,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_USERAGENT => 'Wakabi/' . VERSION,
            CURLOPT_MAXFILESIZE => WP_IMAGE_MAX,
        ]);
        $brut = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'method' => 'GET',
            'timeout' => $delai,
            'ignore_errors' => true,
            'header' => 'User-Agent: Wakabi/' . VERSION,
        ]]);
        $brut = @file_get_contents($url, false, $ctx, 0, WP_IMAGE_MAX);
        foreach ($http_response_header ?? [] as $l) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $l, $m)) {
                $code = (int) $m[1];
            } elseif (stripos($l, 'content-type:') === 0) {
                $type = trim(substr($l, 13));
            }
        }
    }

    return [
        'ok' => $code >= 200 && $code < 300 && is_string($brut) && $brut !== '',
        'code' => $code,
        'corps' => is_string($brut) ? $brut : null,
        'type' => $type,
    ];
}

/**
 * Le cache d'une requête, et le repli sur du périmé.
 *
 * Trois états, et le troisième est celui qui compte : frais (on sert),
 * périmé mais WordPress répond (on rafraîchit), périmé et WordPress muet
 * (ON SERT LE PÉRIMÉ). Pour l'écran d'import, le périmé vaut mieux qu'un
 * panneau vide : la liste des articles à importer est une liste, pas une
 * écriture.
 */
function wp_cache(string $cle, callable $chercher): array
{
    $f = dossier_cache() . '/wp-' . preg_replace('/[^a-z0-9_-]/i', '', $cle) . '.json';
    $age = is_file($f) ? time() - (int) @filemtime($f) : PHP_INT_MAX;

    if ($age < WP_FRAICHEUR) {
        $vu = json_decode((string) @file_get_contents($f), true);
        if (is_array($vu)) {
            return $vu + ['frais' => true];
        }
    }

    $vieux = is_file($f) ? json_decode((string) @file_get_contents($f), true) : null;

    /* En panne : on ne rappelle pas, on sert ce qu'on a. */
    if (wp_en_panne()) {
        return is_array($vieux)
            ? $vieux + ['frais' => false]
            : ['ok' => false, 'corps' => [], 'pages' => 0, 'total' => 0, 'frais' => false];
    }

    $neuf = $chercher();
    wp_panne_noter(!$neuf['ok']);
    if ($neuf['ok']) {
        @file_put_contents($f, json_encode($neuf, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $neuf + ['frais' => true];
    }

    return is_array($vieux)
        ? $vieux + ['frais' => false]
        : ['ok' => false, 'corps' => [], 'pages' => 0, 'total' => 0, 'frais' => false];
}

/* ------------------------------------------------------------------ */
/* Du HTML de WordPress vers le texte du projet                        */
/* ------------------------------------------------------------------ */

/**
 * Le corps d'un article WordPress, retourné en marques du projet.
 *
 * Et non en HTML gardé tel quel — c'est le point entier de cette
 * fonction. `texte.php` l'écrit en toutes lettres : le corps d'un article
 * est du TEXTE, échappé d'abord, et AUCUNE balise ne vient de la saisie.
 * Coller ici le `content.rendered` de WordPress ferait exactement ce que
 * cette règle interdit, et la faille serait d'autant plus vicieuse qu'elle
 * passerait par un serveur de confiance : il suffirait d'un compte
 * d'auteur WordPress compromis pour poser un script sur toutes les pages
 * du site.
 *
 * On traduit donc : les titres deviennent `##`, les listes `-`, les
 * citations `>`, les liens `[texte](url)`, les images `![légende](url)`.
 * Ce qu'on ne reconnaît pas devient du texte nu. Les balises finales sont
 * toutes écrites par `texte_riche()`, comme pour un article d'ici. Les
 * images ainsi citées restent aux adresses du guide : c'est
 * `wp_rapatrier_images()` qui les ramène à l'import.
 */
function wp_html_vers_texte(string $html): string
{
    $s = (string) preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html);

    /**
     * Les retours à la ligne du SOURCE deviennent des espaces.
     *
     * Dans un document HTML, un retour à la ligne est une espace : c'est
     * `<br>` qui coupe, et lui seul. Les garder faisait couper une phrase
     * en deux au milieu — « Voici exactement<br>comment nous avons rempli
     * la salle » — parce que la suite du traitement travaille en lignes.
     * Les vrais sauts sont réintroduits plus bas, par les règles de bloc.
     */
    $s = (string) preg_replace('/\s*\R\s*/u', ' ', $s);

    /* Les images d'abord : elles portent une adresse qu'il faut garder
       entière, et les remplacements suivants mangeraient leurs balises. */
    $s = (string) preg_replace_callback(
        '#<figure\b[^>]*>(.*?)</figure>#is',
        static function (array $m): string {
            $legende = '';
            if (preg_match('#<figcaption\b[^>]*>(.*?)</figcaption>#is', $m[1], $c)) {
                $legende = trim(html_entity_decode(strip_tags($c[1]), ENT_QUOTES, 'UTF-8'));
            }
            if (!preg_match('#<img\b[^>]*\bsrc="([^"]+)"#i', $m[1], $i)) {
                return "\n\n";
            }
            return "\n\n![" . str_replace([']', '['], '', $legende) . '](' . trim($i[1]) . ")\n\n";
        },
        $s
    );
    $s = (string) preg_replace_callback(
        '#<img\b[^>]*\bsrc="([^"]+)"[^>]*>#i',
        static function (array $m): string {
            $alt = preg_match('#\balt="([^"]*)"#i', $m[0], $a) ? trim($a[1]) : '';
            return "\n\n![" . str_replace([']', '['], '', $alt) . '](' . trim($m[1]) . ")\n\n";
        },
        $s
    );

    /* Les liens, avant que les balises ne tombent. */
    $s = (string) preg_replace_callback(
        '#<a\b[^>]*\bhref="([^"]+)"[^>]*>(.*?)</a>#is',
        static function (array $m): string {
            $texte = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES, 'UTF-8'));
            $url = trim(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
            if ($texte === '') {
                return '';
            }
            /* Une adresse qui n'est pas http(s) ne devient pas un lien :
               `javascript:` et `data:` n'ont rien à faire dans un article. */
            $schema = strtolower((string) parse_url($url, PHP_URL_SCHEME));
            return in_array($schema, ['http', 'https'], true)
                ? '[' . str_replace([']', '['], '', $texte) . '](' . $url . ')'
                : $texte;
        },
        $s
    );

    /* Les blocs. L'ordre compte : du plus précis au plus général. */
    $blocs = [
        '#<h[12]\b[^>]*>(.*?)</h[12]>#is' => "\n\n## \\1\n\n",
        '#<h[3-6]\b[^>]*>(.*?)</h[3-6]>#is' => "\n\n### \\1\n\n",
        '#<blockquote\b[^>]*>(.*?)</blockquote>#is' => "\n\n> \\1\n\n",
        '#<li\b[^>]*>(.*?)</li>#is' => "\n- \\1",
        '#<(strong|b)\b[^>]*>(.*?)</\1>#is' => '**\2**',
        '#<(em|i)\b[^>]*>(.*?)</\1>#is' => '*\2*',
        '#<br\s*/?>#i' => "\n",
        '#</p>#i' => "\n\n",
    ];
    foreach ($blocs as $motif => $vers) {
        $s = (string) preg_replace($motif, $vers, $s);
    }

    $s = strip_tags($s);
    $s = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    /* Trois lignes vides ou plus n'ajoutent rien qu'une deuxième n'ait
       déjà dit, et laisseraient des trous dans l'article. */
    $s = (string) preg_replace("/[ \t]+\n/", "\n", $s);
    $s = (string) preg_replace("/\n{3,}/", "\n\n", $s);
    return trim($s);
}

/** Le texte nu d'un champ `rendered` — pour un titre ou un chapô. */
function wp_nu(string $html): string
{
    $t = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim((string) preg_replace('/\s+/u', ' ', $t));
}

/**
 * Une date de WordPress, écrite comme les nôtres.
 *
 * `date_gmt` arrive sans fuseau : « 2026-09-20T14:32:11 ». Laissée telle
 * quelle, `strtotime()` la lit dans le fuseau du serveur, et un article
 * importé se range une heure trop tôt ou trop tard au milieu des nôtres.
 * On lui rend son `Z`, et tout se trie par simple comparaison de chaînes
 * — même forme, même ordre.
 */
function wp_date(string $brut): string
{
    $brut = trim($brut);
    if ($brut === '') {
        return '';
    }
    $t = strtotime(str_ends_with($brut, 'Z') ? $brut : $brut . 'Z');
    return $t === false ? '' : maintenant($t);
}

/**
 * Un article de l'API WordPress, rangé comme un article d'ici.
 *
 * Même forme que la table `articles`, pour que le panneau d'import et
 * l'importeur eux-mêmes n'aient qu'une seule manière de lire un article
 * du guide. `source` et `id` en `'wp-…'` n'existent qu'ICI, pour la liste
 * : ce qui est écrit dans la table a un vrai identifiant de la maison, et
 * n'est plus jamais appelé « guide ».
 */
function wp_normaliser(array $p): array
{
    $couv = (string) ($p['featured_image_url'] ?? '');
    if ($couv === '') {
        $couv = (string) ($p['_embedded']['wp:featuredmedia'][0]['source_url'] ?? '');
    }
    $chapo = wp_nu((string) ($p['excerpt']['rendered'] ?? ''));
    $corps = wp_html_vers_texte((string) ($p['content']['rendered'] ?? ''));
    $date = wp_date((string) ($p['date_gmt'] ?? $p['date'] ?? ''));
    $maj = wp_date((string) ($p['modified_gmt'] ?? $p['modified'] ?? ''));

    return [
        'source' => 'guide',
        'id' => 'wp-' . (int) ($p['id'] ?? 0),
        'idwp' => (int) ($p['id'] ?? 0),
        'slug' => (string) ($p['slug'] ?? ''),
        'titre' => wp_nu((string) ($p['title']['rendered'] ?? '')),
        'chapo' => $chapo !== '' ? $chapo : texte_extrait($corps),
        'corps' => $corps,
        'couverture' => wp_image_permise($couv) ? $couv : '',
        'rubrique' => wp_nu((string) ($p['_embedded']['wp:term'][0][0]['name'] ?? '')),
        'auteur_id' => null,
        'auteur_nom' => wp_nu((string) ($p['_embedded']['author'][0]['name'] ?? '')),
        'statut' => 'publie',
        'publie_le' => $date,
        'cree_le' => $date,
        'maj_le' => $maj !== '' ? $maj : $date,
        'vues' => 0,
        'decor_id' => null,
        /* L'adresse de l'article chez le guide : la référence qui marque
           l'import dans `importe_de` — c'est par elle qu'on sait qu'un
           article est déjà entré, sans relire tout le blog au deuxième
           clic. Et elle laisse un retour vers l'original, pour la relecture. */
        'lien_source' => (string) ($p['link'] ?? ''),
    ];
}

/**
 * L'adresse d'un article.
 *
 * `?p=blog&a=…` pour tout le monde maintenant : un article importé EST un
 * article de la maison. La clé `source` n'a d'existence que dans la
 * liste du panneau d'import, et elle ne sert plus à choisir une porte.
 */
function url_article(array $a): string
{
    $p = ($a['source'] ?? '') === 'guide' ? 'g' : 'a';
    return '?p=blog&' . $p . '=' . rawurlencode((string) $a['slug']);
}

/**
 * Les articles du guide, jusqu'au rang demandé.
 *
 * On demande des lots jusqu'à en avoir assez, et pas un de plus : chaque
 * lot a son cache, si bien que relancer le panneau ne redemande que ce
 * qui manque. N'est plus qu'ICI que WordPress est appelé — la liste du
 * panneau d'import et son état de connexion.
 *
 * @return array{articles: list<array>, total: int, frais: bool}
 */
function wp_jusqua(int $rang, string $cherche = ''): array
{
    if (!wp_actif() || $rang < 1) {
        return ['articles' => [], 'total' => 0, 'frais' => true];
    }

    $articles = [];
    $total = 0;
    $frais = true;
    $lots = min(WP_LOTS_MAX, (int) ceil($rang / WP_PAR_LOT));

    for ($lot = 1; $lot <= $lots; $lot++) {
        $params = [
            'per_page' => WP_PAR_LOT,
            'page' => $lot,
            '_embed' => '1',
            'orderby' => 'date',
            'order' => 'desc',
        ];
        if ($cherche !== '') {
            $params['search'] = $cherche;
        }
        $url = wp_racine() . '/posts?' . http_build_query($params);
        $cle = 'posts-' . $lot . '-' . substr(sha1(wp_racine() . '|' . $cherche), 0, 12);

        /* Vingt secondes, pas six : plus rien de public ne passe par ici,
           et le premier appel apres une cache morte peut dépasser largement
           six secondes sur un WordPress charge. Le cache périmé reste là
           pour le pire cas. */
        $r = wp_cache($cle, static fn(): array => wp_get($url, 20));
        $frais = $frais && (bool) ($r['frais'] ?? false);
        if ((int) ($r['total'] ?? 0) > 0) {
            $total = (int) $r['total'];
        }

        $recus = 0;
        foreach ((array) ($r['corps'] ?? []) as $p) {
            if (is_array($p) && ($p['slug'] ?? '') !== '') {
                $articles[] = wp_normaliser($p);
                $recus++;
            }
        }
        /* Lot incomplet : il n'y a plus rien derrière. Demander la page
           suivante rendrait un 400 de WordPress, pas une liste vide. */
        if ($recus < WP_PAR_LOT) {
            break;
        }
    }

    return [
        'articles' => array_slice($articles, 0, $rang),
        'total' => max($total, count($articles)),
        'frais' => $frais,
    ];
}

/* ------------------------------------------------------------------ */
/* L'import : télécharger, héberger, écrire ici                        */
/* ------------------------------------------------------------------ */

/**
 * Une image du guide, rapportée et hébergée chez nous.
 *
 * Le test qui décide est `getimagesizefromstring()` : une extension est
 * une déclaration, un contenu d'image est une preuve. Le fichier reçoit
 * alors un identifiant de la maison, la même recompression qu'un
 * téléversement dans l'éditeur — une photo de 4000 px s'affiche en 900,
 * autant ne plus la transférer entière à chaque lecture — puis le fanion
 * `.opt` de la convention des cadres.
 *
 * Â tout échec — hors hôte, trop lourde, pas une image, téléchargement
 * mort — '' : l'appelant garde alors l'adresse du guide, et rien ne
 * casse au pire.
 *
 * @return string l'adresse locale du média, ou '' si rien n'a été rapporté
 */
function wp_importer_media(string $url): string
{
    $url = trim($url);
    if ($url === '' || !wp_image_permise($url)) {
        return '';
    }

    $r = wp_telecharge($url);
    if (!$r['ok'] || strlen((string) $r['corps']) > WP_IMAGE_MAX) {
        return '';
    }
    $info = @getimagesizefromstring($r['corps']);
    $ext = match ($info[2] ?? 0) {
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG => 'png',
        IMAGETYPE_WEBP => 'webp',
        default => null,
    };
    if (!$ext) {
        return '';
    }

    $dossier = dossier_medias();
    $nom = nouvel_id() . '.' . $ext;
    if (@file_put_contents($dossier . '/' . $nom, (string) $r['corps']) === false) {
        return '';
    }
    /* Un fichier qu'on ne peut pas recomprimer reste en l'état : une
       image un peu lourde vaut infiniment mieux qu'un média perdu. */
    $nom = compresser_cadre($dossier, $nom)['nom'];
    @touch($dossier . '/' . $nom . '.opt');
    return url('?p=media&f=' . $nom);
}

/**
 * Les images d'un corps converti, ramenées chez nous une à une.
 *
 * `wp_html_vers_texte()` a laissé `![alt](https://…)` aux adresses du
 * guide : on les passe une à une à `wp_importer_media()`, et l'adresse
 * locale remplace la sienne dans la marque. Une adresse déjà de la
 * maison — reconnue par `cle_image()` — n'est pas retouchée ; une image
 * qui refuse de venir reste au guide, et `texte.php` la rend encore —
 * la dérogation de lecture perdure, à défaut du media lui-même.
 *
 * Chaque adresse ne part qu'UNE FOIS, par dédoublonnage : un article
 * WordPress cite volontiers trois fois la même image (couverture répétée
 * en corps), et un article de huit images à quatre exemplaires
 * coûterait autant de téléchargements pour rien.
 *
 * @return string le même corps, aux adresses locales quand il s'a pu
 */
function wp_rapatrier_images(string $corps): string
{
    $faites = [];
    return (string) preg_replace_callback(
        '/!\[([^\]]*)\]\(([^)\s]+)\)/',
        static function (array $m) use (&$faites): string {
            $url = trim($m[2]);
            if ($url === '' || cle_image($url) !== null || !wp_image_permise($url)) {
                return $m[0];
            }
            if (!array_key_exists($url, $faites)) {
                $faites[$url] = wp_importer_media($url);
            }
            return $faites[$url] !== ''
                ? '![' . $m[1] . '](' . $faites[$url] . ')'
                : $m[0];
        },
        $corps
    );
}

/**
 * Un article du guide est-il déjà entré chez nous ?
 *
 * La référence est le lien d'origine, écrit dans `importe_de` à chaque
 * import. Un article du guide sans lien n'a pas de référence fiable :
 * `null` dit « je ne sais pas », et l'importeur préfère alors une
 * écriture plutôt qu'une perte — un doublon se voit, on le supprime ;
 * un article jamais arrivé, personne ne le cherche.
 */
function article_deja_importe(string $reference): ?array
{
    $reference = trim($reference);
    if ($reference === '') {
        return null;
    }
    $s = db()->prepare('SELECT * FROM articles WHERE importe_de = ? LIMIT 1');
    $s->execute([$reference]);
    $r = $s->fetch();
    return $r ?: null;
}

/**
 * Un article du guide, écrit dans la table, en état de paraître.
 *
 * PAS par `article_creer()` — qui force le statut brouillon, et à raison
 * : le circuit de relecture est fait pour un auteur inconnu de soi. Ici
 * l'article a DÉJÀ été relu : c'est celui du guide, publié là-bas par la
 * rédaction qui porte son nom. Il entre donc publié, avec ses dates à
 * lui datant d'avant son arrivée — un article de septembre se rangerait
 * mal derrière les notres juste à cause du jour de son import.
 *
 * `importe_de` porte le lien d'origine : c'est l'inscription au registre
 * des imports, celle qui rend un deuxième clic inoffensif.
 *
 * @param array $d un article normalisé par `wp_normaliser()`
 * @param string $auteur_id le compte qui a cliqué sur « Importer »
 * @return array{ok: bool, id: ?string, deja: bool, erreur: string}
 */
function wp_importer_article(array $d, string $auteur_id): array
{
    $ref = trim((string) ($d['lien_source'] ?? ''));

    $deja = article_deja_importe($ref);
    if ($deja) {
        return ['ok' => true, 'id' => (string) $deja['id'], 'deja' => true, 'erreur' => ''];
    }

    $titre = trim((string) ($d['titre'] ?? ''));
    if ($titre === '') {
        return ['ok' => false, 'id' => null, 'deja' => false, 'erreur' => 'L’article n’a pas de titre.'];
    }

    /* Le slug du guide passe DE PREMIER : les adresses déjà partagées de
       ses articles pointant vers `?p=blog&g=…` avec CE slug, les garder
       ici rend la redirection de secours immédiate et juste. */
    $base_slug = trim((string) ($d['slug'] ?? ''));
    $slug = slug_article_libre($base_slug !== '' ? $base_slug : $titre);

    /* Les images du corps sont rapatriées AVANT l'écriture : si le
       téléchargement échoue, une écriture n'a jamais commencé — un
       article moitié aux adresses du guide moitié introuvable serait le
       pire des deux mondes. À l'échec total, les adresses du guide
       restent, et le corps reste lisible tant qu'il dure. */
    $corps = wp_rapatrier_images((string) ($d['corps'] ?? ''));
    if (mb_strlen(trim($corps)) < 40) {
        return ['ok' => false, 'id' => null, 'deja' => false, 'erreur' => 'Le corps de l’article est vide ou trop court.'];
    }

    $couverture = wp_importer_media((string) ($d['couverture'] ?? ''));
    $publie_le = (string) ($d['publie_le'] ?? '') ?: null;
    $now = $publie_le !== null ? (string) $d['publie_le'] : maintenant();
    $maj_le = (string) ($d['maj_le'] ?? '') !== '' ? (string) $d['maj_le'] : $now;
    $auteur_nom = trim((string) ($d['auteur_nom'] ?? ''));

    $id = nouvel_id();
    db()->prepare('INSERT INTO articles
        (id, slug, titre, chapo, corps, couverture, decor_id, statut, auteur_id, auteur_nom,
         publie_le, importe_de, cree_le, maj_le)
        VALUES (?,?,?,?,?,?,NULL,\'publie\',?,?,?,?,?,?)')
        ->execute([
            $id, $slug, $titre,
            trim((string) ($d['chapo'] ?? '')) !== '' ? (string) $d['chapo'] : texte_extrait($corps),
            $corps, $couverture !== '' ? $couverture : null,
            $auteur_id, $auteur_nom !== '' ? $auteur_nom : 'La rédaction Wakabi',
            $publie_le, $ref !== '' ? $ref : ('wp:' . (int) ($d['idwp'] ?? 0)),
            $now, $maj_le,
        ]);

    return ['ok' => true, 'id' => $id, 'deja' => false, 'erreur' => ''];
}

/* ------------------------------------------------------------------ */
/* La liste publique                                                   */
/* ------------------------------------------------------------------ */

/**
 * La liste du blog — les articles d'ici, et rien que les articles d'ici.
 *
 * Le nom raconte une histoire : le temps où deux sources se mélangeaient
 * à la volée. Aujourd'hui une seule table fournit la liste, triée par
 * ses dates, et les articles importés du guide s'y tiennent à leur date
 * PUBLIÉE — au rang auquel ils appartenaient depuis toujours.
 *
 * La forme de retour ne change pas — `liste`, `total`, `pages` et
 * `frais` — parce que la vue du blog et ses appelants n'ont pas à savoir
 * qu'il n'y a plus qu'une seule source. `frais` est vrai par définition :
 * il n'y a plus de cache susceptible d'être périmé.
 *
 * @return array{liste: list<array>, total: int, pages: int, frais: bool}
 */
function blog_fusionne(int $page, int $par_page, string $cherche = ''): array
{
    $page = max(1, $page);
    $par_page = max(1, $par_page);

    $tout = articles_publies_jusqua($page * $par_page, $cherche);
    $total = compter_articles_publies_cherches($cherche);

    return [
        'liste' => array_slice($tout, ($page - 1) * $par_page, $par_page),
        'total' => $total,
        'pages' => max(1, (int) ceil($total / $par_page)),
        'frais' => true,
    ];
}
