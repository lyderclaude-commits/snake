<?php
/**
 * L'import des articles de l'ancien site WordPress (`/wp-json/wp/v2`).
 *
 * Il se fait PAR LOTS de dix articles : un mutualisé coupe un script au
 * bout de 30 à 60 secondes, et chaque article peut traîner une dizaine
 * d'images à télécharger. L'écran d'import enchaîne les lots tout seul.
 *
 * Il se rejoue sans dégât : un article déjà importé est mis à jour (par
 * son identifiant WordPress), sauf s'il a été retouché ici depuis — on ne
 * jette pas le travail de l'équipe pour une ligne changée là-bas.
 *
 * À faire AVANT de faire pointer le domaine vers ce site : une fois
 * liveydream.com servi par ce code, l'ancien WordPress n'y répond plus.
 */

declare(strict_types=1);

const WP_PAR_LOT = 10;

function wp_racine(): string
{
    return rtrim(trim(reglage('wp_racine')), '/');
}

/** L'hôte de l'ancien site — ses liens internes seront réécrits vers ce site-ci. */
function wp_hote(string $racine): string
{
    return strtolower((string) preg_replace('/^www\./', '', (string) parse_url($racine, PHP_URL_HOST)));
}

/**
 * @return array{ok: bool, code: int, donnees: mixed, total: int, pages: int, erreur: string}
 */
function wp_appel(string $url): array
{
    $r = http_get($url, 40, 30 * 1024 * 1024);
    $d = json_decode($r['corps'], true);
    $erreur = '';
    if (!$r['ok']) {
        $erreur = $r['erreur'] !== '' ? $r['erreur'] : 'HTTP ' . $r['code'];
        if (is_array($d) && isset($d['message'])) {
            $erreur .= ' — ' . texte_nu((string) $d['message']);
        }
    } elseif (!is_array($d)) {
        $erreur = 'la réponse n’est pas du JSON (l’adresse pointe-t-elle bien sur /wp-json/wp/v2 ?)';
    }
    return [
        'ok' => $erreur === '',
        'code' => $r['code'],
        'donnees' => is_array($d) ? $d : [],
        'total' => (int) ($r['entetes']['x-wp-total'] ?? 0),
        'pages' => (int) ($r['entetes']['x-wp-totalpages'] ?? 0),
        'erreur' => $erreur,
    ];
}

/** Combien d'articles attendent là-bas ? Sert de test de connexion. */
function wp_sonder(string $racine): array
{
    $r = wp_appel($racine . '/posts?per_page=1&_fields=id');
    return ['ok' => $r['ok'], 'total' => $r['total'], 'pages' => (int) ceil($r['total'] / WP_PAR_LOT), 'erreur' => $r['erreur']];
}

/**
 * Importe un lot. `$page` commence à 1.
 *
 * @return array{ok: bool, erreur: string, page: int, pages: int, total: int,
 *               crees: int, maj: int, gardes: int, images: int, images_echec: int, journal: list<string>}
 */
function wp_importer_lot(string $racine, int $page, bool $ecraser = false): array
{
    @set_time_limit(300);
    $res = ['ok' => false, 'erreur' => '', 'page' => $page, 'pages' => 0, 'total' => 0,
        'crees' => 0, 'maj' => 0, 'gardes' => 0, 'images' => 0, 'images_echec' => 0, 'journal' => []];

    $r = wp_appel($racine . '/posts?per_page=' . WP_PAR_LOT . '&page=' . $page . '&_embed=1&orderby=date&order=asc');
    // Une page au-delà de la dernière répond 400 : ce n'est pas une panne, c'est la fin.
    if (!$r['ok'] && $r['code'] === 400 && $page > 1) {
        $res['ok'] = true;
        $res['pages'] = $page - 1;
        return $res;
    }
    if (!$r['ok']) {
        $res['erreur'] = $r['erreur'];
        return $res;
    }
    $res['ok'] = true;
    $res['total'] = $r['total'];
    $res['pages'] = $r['pages'] ?: ($r['donnees'] ? $page : $page - 1);

    foreach ($r['donnees'] as $post) {
        if (!is_array($post) || !isset($post['id'])) {
            continue;
        }
        try {
            $bilan = wp_importer_article($racine, $post, $ecraser);
        } catch (Throwable $e) {
            $res['journal'][] = '✗ ' . wp_nu((string) ($post['title']['rendered'] ?? '#' . $post['id'])) . ' — ' . $e->getMessage();
            continue;
        }
        $res[$bilan['etat']]++;
        $res['images'] += $bilan['images'];
        $res['images_echec'] += $bilan['images_echec'];
        $res['journal'][] = match ($bilan['etat']) {
                'crees' => '+ ', 'maj' => '↻ ', default => '= ',
            } . $bilan['titre'] . ($bilan['images_echec'] ? " ({$bilan['images_echec']} image(s) non récupérée(s))" : '');
    }
    reglages_enregistrer(['wp_dernier_import' => maintenant()]);
    return $res;
}

/**
 * Un article WordPress → une ligne `articles`.
 *
 * @return array{etat: string, titre: string, images: int, images_echec: int}
 */
function wp_importer_article(string $racine, array $p, bool $ecraser): array
{
    $wp_id = (int) $p['id'];
    $titre = wp_nu((string) ($p['title']['rendered'] ?? '')) ?: 'Sans titre';
    $existant = un('SELECT * FROM articles WHERE wp_id = ?', [$wp_id]);

    if ($existant && !$ecraser && (int) $existant['retouche'] === 1) {
        return ['etat' => 'gardes', 'titre' => $titre, 'images' => 0, 'images_echec' => 0];
    }

    // On compte les images NOUVELLES : une même photo citée trois fois n'est copiée qu'une fois.
    $avant = (int) valeur('SELECT COUNT(*) FROM medias');
    $echecs = 0;
    $rapatrier = static function (string $u) use (&$echecs): ?string {
        $err = null;
        $local = media_depuis_url($u, $err);
        if ($local === null) {
            $echecs++;
        }
        return $local;
    };

    $hote = wp_hote($racine);
    $lien = static function (string $href) use ($hote): ?string {
        $h = strtolower((string) preg_replace('/^www\./', '', (string) parse_url($href, PHP_URL_HOST)));
        if ($h === '' || $h !== $hote) {
            return null;
        }
        $chemin = trim((string) parse_url($href, PHP_URL_PATH), '/');
        // Les liens vers l'administration ou les fichiers de WordPress ne mènent plus nulle part.
        if (str_starts_with($chemin, 'wp-')) {
            return null;
        }
        // Un lien vers un autre article de l'ancien site : le routeur saura le rediriger.
        return url($chemin === '' ? '' : $chemin . '/');
    };

    // Les images AVANT le tamis : il retire `srcset`, où WordPress range la grande version.
    $contenu = html_images_remplacer((string) ($p['content']['rendered'] ?? ''), $rapatrier, $lien);
    $contenu = contenu_stocker($contenu);

    // La couverture : l'image mise en avant, à sa taille d'origine.
    $image = '';
    $media = $p['_embedded']['wp:featuredmedia'][0] ?? null;
    $source = is_array($media) ? (string) ($media['source_url'] ?? '') : '';
    if ($source === '' && (int) ($p['featured_media'] ?? 0) > 0) {
        $m = wp_appel($racine . '/media/' . (int) $p['featured_media']);
        $source = $m['ok'] ? (string) ($m['donnees']['source_url'] ?? '') : '';
    }
    if ($source === '' && !empty($p['jetpack_featured_media_url'])) {
        $source = (string) $p['jetpack_featured_media_url'];
    }
    if ($source !== '') {
        $image = $rapatrier($source) ?? $source;
    }

    [$categorie, $tags] = wp_termes($racine, $p);

    $extrait = wp_nu((string) ($p['excerpt']['rendered'] ?? ''));
    $extrait = trim((string) preg_replace('/(\[(…|&hellip;|\.\.\.)\]|Lire la suite.*|Continuer la lecture.*|Read more.*)$/iu', '', $extrait));
    if ($extrait === '' && !empty($p['yoast_head_json']['description'])) {
        $extrait = (string) $p['yoast_head_json']['description'];
    }

    $auteur = (string) ($p['_embedded']['author'][0]['name'] ?? '');
    $publie_le = wp_date((string) ($p['date'] ?? '')) ?: maintenant();
    $wp_lien = (string) ($p['link'] ?? '');
    $wp_chemin = trim((string) parse_url($wp_lien, PHP_URL_PATH), '/');
    $slug_wp = rawurldecode((string) ($p['slug'] ?? '')) ?: slugifier($titre);

    $champs = [
        'titre' => $titre,
        'extrait' => tronquer($extrait, 400),
        'contenu' => $contenu,
        'image' => $image,
        'categorie' => $categorie,
        'tags' => json_encode($tags, JSON_UNESCAPED_UNICODE),
        'auteur' => $auteur !== '' ? $auteur : 'Équipe LYD',
        'statut' => 'publie',
        'publie_le' => $publie_le,
        'wp_lien' => $wp_lien,
        'wp_chemin' => $wp_chemin,
    ];
    $t = maintenant();
    if ($existant) {
        $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($champs)));
        q("UPDATE articles SET $sets, modifie_le = ?, importe_le = ?, retouche = 0 WHERE id = ?",
            [...array_values($champs), $t, $t, $existant['id']]);
        $etat = 'maj';
    } else {
        $champs['slug'] = slug_libre($slug_wp);
        $champs['wp_id'] = $wp_id;
        $cols = implode(', ', array_keys($champs));
        $trous = implode(', ', array_fill(0, count($champs), '?'));
        q("INSERT INTO articles ($cols, cree_le, modifie_le, importe_le) VALUES ($trous, ?, ?, ?)",
            [...array_values($champs), $t, $t, $t]);
        $etat = 'crees';
    }
    $images = (int) valeur('SELECT COUNT(*) FROM medias') - $avant;
    return ['etat' => $etat, 'titre' => $titre, 'images' => $images, 'images_echec' => $echecs];
}

/**
 * La catégorie principale et les étiquettes.
 *
 * `_embed` les fournit d'ordinaire dans `wp:term` ; certains extensions de
 * sécurité coupent `_embed`, d'où le repli sur `/categories` et `/tags`.
 *
 * @return array{0: string, 1: list<string>}
 */
function wp_termes(string $racine, array $p): array
{
    static $cache = [];
    $cats = [];
    $tags = [];
    foreach ((array) ($p['_embedded']['wp:term'] ?? []) as $groupe) {
        foreach ((array) $groupe as $t) {
            if (!is_array($t) || !isset($t['name'])) {
                continue;
            }
            if (($t['taxonomy'] ?? '') === 'category') {
                $cats[] = wp_nu((string) $t['name']);
            } elseif (($t['taxonomy'] ?? '') === 'post_tag') {
                $tags[] = wp_nu((string) $t['name']);
            }
        }
    }
    foreach (['categories' => &$cats, 'tags' => &$tags] as $tax => &$liste) {
        $ids = array_map('intval', (array) ($p[$tax] ?? []));
        if ($liste || !$ids) {
            continue;
        }
        $manque = array_diff($ids, array_keys($cache[$tax] ?? []));
        if ($manque) {
            $r = wp_appel($racine . '/' . $tax . '?per_page=100&include=' . implode(',', $manque) . '&_fields=id,name');
            foreach ($r['ok'] ? $r['donnees'] : [] as $t) {
                $cache[$tax][(int) $t['id']] = wp_nu((string) $t['name']);
            }
        }
        foreach ($ids as $i) {
            if (isset($cache[$tax][$i])) {
                $liste[] = $cache[$tax][$i];
            }
        }
    }
    unset($liste);
    // « Non classé » n'est pas une catégorie qu'on affiche à un visiteur.
    $cats = array_values(array_filter($cats, fn($c) => !in_array(mb_strtolower($c), ['non classé', 'uncategorized', 'non classe'], true)));
    return [$cats[0] ?? 'Actualités', array_values(array_unique($tags))];
}

/** Le texte nu d'un champ `rendered`. */
function wp_nu(string $html): string
{
    return texte_nu($html);
}

/** `2024-03-15T10:20:00` (heure du site WordPress) → notre format. */
function wp_date(string $brut): string
{
    $t = strtotime(str_replace('T', ' ', trim($brut)));
    return $t === false ? '' : maintenant($t);
}

/**
 * Une adresse de l'ancien site → l'article correspondant, s'il y en a un.
 *
 * Les liens partagés sur Facebook et WhatsApp, et ceux que Google a
 * retenus, pointent vers `/mon-article/` ou `/2024/03/mon-article/` :
 * on les reconnaît par leur chemin exact, puis par leur dernier segment.
 */
function wp_article_pour_chemin(string $chemin): ?array
{
    $chemin = trim($chemin, '/');
    if ($chemin === '') {
        return null;
    }
    $a = un('SELECT slug FROM articles WHERE wp_chemin = ? AND ' . sql_article_en_ligne(), [$chemin]);
    if ($a) {
        return $a;
    }
    $dernier = basename($chemin);
    return un('SELECT slug FROM articles WHERE slug = ? AND ' . sql_article_en_ligne(), [$dernier]);
}
