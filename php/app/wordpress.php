<?php
/**
 * Les articles du guide, tirés de son WordPress.
 *
 * Le blog de wakabileguide.com allait les chercher DEPUIS LE NAVIGATEUR :
 * la page arrivait vide, un script appelait `wp-json/wp/v2/posts?_embed`,
 * puis fabriquait les cartes. Ça marche à l'oeil, et ça coûte trois
 * choses qui comptent ici.
 *
 * D'abord le référencement : un moteur de recherche reçoit une page vide.
 * Le blog du guide est donc aujourd'hui INVISIBLE dans les résultats —
 * pour un guide dont tout le métier est de se faire trouver, c'est le
 * contraire du but. Ensuite la 3G : chaque visiteur paie l'aller-retour
 * vers WordPress, plus la charge `_embed` qui traîne les médias et les
 * catégories. Enfin la fusion elle-même : le blog de Boost est rendu par
 * le serveur, avec ses articles et sa relecture ; pour ranger les deux
 * sources dans une seule liste triée par date, il faut qu'elles se
 * rencontrent quelque part, et ce quelque part est ici.
 *
 * On lit donc côté serveur, on garde en cache, et l'on rend du HTML déjà
 * écrit. Si WordPress ne répond pas, le cache périmé sert quand même :
 * un blog qui affiche les articles d'hier vaut infiniment mieux qu'un
 * blog vide, et c'est exactement ce que le visiteur attend.
 */

declare(strict_types=1);

/** Là où vit l'API tant que personne n'a dit le contraire. */
const WP_RACINE_DEFAUT = 'https://admin.wakabileguide.com/wp-json/wp/v2';

/** Combien de temps un cache est frais. Dix minutes : un blog n'est pas une horloge. */
const WP_FRAICHEUR = 600;

/**
 * La taille d'un lot, et combien on en demande au plus.
 *
 * Vingt articles par appel plutôt que cent : la page 1 du blog en montre
 * neuf, et `_embed` traîne derrière chaque article ses médias et ses
 * catégories. Demander cent objets pour en afficher neuf, c'est faire
 * payer à WordPress — et à la connexion — dix fois ce qu'on utilise.
 *
 * Dix lots au plus, soit deux cents articles du guide atteignables. Au
 * delà, la page profonde du blog ne montre plus que les articles d'ici :
 * c'est une limite écrite, pas une surprise, et elle borne le temps qu'une
 * seule page peut passer à attendre WordPress.
 */
const WP_PAR_LOT = 20;
const WP_LOTS_MAX = 10;

/**
 * Après une panne, on laisse WordPress tranquille cinq minutes.
 *
 * Sans ce répit, un hébergement sans sortie réseau — cas banal sur un
 * mutualisé — ferait attendre le délai de connexion À CHAQUE affichage du
 * blog, pour toujours échouer pareil. Le blog resterait lisible, mais lent
 * à en être inutilisable, et rien ne dirait pourquoi.
 */
const WP_REPIT = 300;

/**
 * La racine de l'API.
 *
 * Trois sources, dans cet ordre : ce que l'équipe a réglé en ligne, ce que
 * `config.php` impose, puis le défaut. Le réglage en ligne passe devant
 * parce que c'est le seul qui se corrige sans FTP le jour où l'adresse de
 * WordPress change — et ce jour-là, le blog est à moitié vide.
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
 * Ces articles-ci font exception, sur décision explicite : leurs images
 * vivent dans la médiathèque du guide et les rapatrier demanderait un
 * import que personne n'a demandé. L'exception est BORNÉE à l'hôte de
 * l'API — pas « n'importe quelle adresse », l'hôte du guide et lui seul.
 * La différence est tout le sujet : on fait confiance à son propre
 * serveur, pas au web entier.
 */
function wp_hote(): string
{
    return strtolower((string) parse_url(wp_racine(), PHP_URL_HOST));
}

/** Cette adresse d'image vient-elle bien du guide ? */
function wp_image_permise(string $url): bool
{
    $u = parse_url(trim($url));
    if (!$u || !in_array(strtolower($u['scheme'] ?? ''), ['http', 'https'], true)) {
        return false;
    }
    $h = strtolower($u['host'] ?? '');
    $ref = wp_hote();
    return $ref !== '' && ($h === $ref || str_ends_with($h, '.' . $ref));
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
 * Un GET JSON, avec repli quand cURL manque.
 *
 * Même forme que `canal_http()`, qui ne sait que poster. Sur un
 * mutualisé, `curl` est parfois désactivé ; le repli par flux fait la
 * même chose en moins bavard sur les erreurs, d'où l'ordre.
 *
 * Les délais sont courts VOLONTAIREMENT : cette requête est sur le chemin
 * d'une page publique. Six secondes d'attente, c'est déjà un visiteur
 * perdu ; mieux vaut servir le cache périmé.
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
     * combien de pages ils font.
     *
     * Sans le premier, la pagination du blog fusionné serait fausse : on
     * ne saurait pas combien d'articles du guide viennent s'ajouter aux
     * nôtres, donc pas combien de pages annoncer. Sans le second, on
     * demanderait des lots après le dernier, pour rien.
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
 * Le cache d'une requête, et le repli sur du périmé.
 *
 * Trois états, et le troisième est celui qui compte : frais (on sert),
 * périmé mais WordPress répond (on rafraîchit), périmé et WordPress muet
 * (ON SERT LE PÉRIMÉ). Le dernier cas est le seul qu'on ne voit jamais en
 * recette et le seul qui arrive vraiment un dimanche soir.
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
 * du site fusionné.
 *
 * On traduit donc : les titres deviennent `##`, les listes `-`, les
 * citations `>`, les liens `[texte](url)`, les images `![légende](url)`.
 * Ce qu'on ne reconnaît pas devient du texte nu. Les balises finales sont
 * toutes écrites par `texte_riche()`, comme pour un article d'ici.
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
 * quelle, `strtotime()` la lit dans le fuseau du serveur, et un article du
 * guide se range une heure trop tôt ou trop tard au milieu des nôtres. On
 * lui rend son `Z`, et les deux sources se trient alors par simple
 * comparaison de chaînes — même forme, même ordre.
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
 * Un article WordPress rangé comme un article d'ici.
 *
 * Même forme que la table `articles`, pour que la liste du blog et la
 * page d'article n'aient pas à savoir d'où vient ce qu'elles affichent.
 * `source` est le seul champ en plus, et il ne sert qu'à fabriquer la
 * bonne adresse.
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
        /* L'adresse de l'article chez WordPress. On ne s'en sert pas pour
           le lien canonique — après la fusion, la page qui fait foi est
           celle-ci — mais la garder coûte un champ et évite de refaire
           l'appel le jour où la rédaction voudra y retourner. */
        'lien_source' => (string) ($p['link'] ?? ''),
    ];
}

/**
 * L'adresse d'un article, selon d'où il vient.
 *
 * Deux paramètres distincts, `a` pour les nôtres et `g` pour ceux du
 * guide, plutôt qu'un seul : rien n'empêche un article d'ici et un article
 * de WordPress de porter le même slug, et le jour où cela arrive, l'un des
 * deux disparaîtrait derrière l'autre sans un mot. Deux portes, aucune
 * collision possible.
 */
function url_article(array $a): string
{
    $p = ($a['source'] ?? '') === 'guide' ? 'g' : 'a';
    return '?p=blog&' . $p . '=' . rawurlencode((string) $a['slug']);
}

/**
 * Les articles du guide, jusqu'au rang demandé.
 *
 * On demande des lots jusqu'à en avoir assez pour la page affichée, et pas
 * un de plus : la page 1 en montre neuf, elle ne fait donc qu'un appel.
 * Chaque lot a son cache, si bien qu'aller plus loin dans le blog ne
 * redemande que ce qui manque.
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

        $r = wp_cache($cle, static fn(): array => wp_get($url));
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

/**
 * Un article du guide par son slug.
 *
 * WordPress sait chercher par slug : `?slug=…` rend une LISTE d'un
 * élément. On passe par là plutôt que par l'identifiant numérique, pour
 * que l'adresse d'un article du guide ait la même forme que celle d'un
 * article d'ici.
 */
function wp_article(string $slug): ?array
{
    $slug = trim($slug);
    if (!wp_actif() || $slug === '' || !preg_match('/^[\w%-]{1,200}$/u', $slug)) {
        return null;
    }
    $url = wp_racine() . '/posts?' . http_build_query(['slug' => $slug, '_embed' => '1']);
    $r = wp_cache('post-' . substr(sha1(wp_racine() . '|' . $slug), 0, 16),
                  static fn(): array => wp_get($url));
    $l = (array) ($r['corps'] ?? []);
    return isset($l[0]) && is_array($l[0]) ? wp_normaliser($l[0]) : null;
}

/**
 * Les deux blogs en une seule liste, triée par date.
 *
 * C'est ici que la fusion a lieu, et elle tient en trois gestes : prendre
 * de chaque source les `rang` articles les plus récents, les trier
 * ensemble, découper la tranche demandée. Le premier geste est ce qui rend
 * le découpage EXACT : le k-ième article de la liste fusionnée est
 * forcément dans les k premiers de l'une des deux sources.
 *
 * @return array{liste: list<array>, total: int, pages: int, frais: bool}
 */
function blog_fusionne(int $page, int $par_page, string $cherche = ''): array
{
    $page = max(1, $page);
    $par_page = max(1, $par_page);
    $rang = $page * $par_page;

    $guide = wp_jusqua($rang, $cherche);
    $miens = articles_publies_jusqua($rang, $cherche);

    $tout = array_merge($miens, $guide['articles']);
    usort($tout, static fn(array $x, array $y): int
        => strcmp((string) ($y['publie_le'] ?? ''), (string) ($x['publie_le'] ?? '')));

    $total = compter_articles_publies_cherches($cherche) + $guide['total'];
    return [
        'liste' => array_slice($tout, ($page - 1) * $par_page, $par_page),
        'total' => $total,
        'pages' => max(1, (int) ceil($total / $par_page)),
        'frais' => $guide['frais'],
    ];
}
