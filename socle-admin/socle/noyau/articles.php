<?php
/**
 * Les articles du blog : lecture pour le site, rangement pour l'administration.
 */

declare(strict_types=1);

define('ARTICLES_PAR_PAGE', (int) def('articles.par_page', 9));

/* ------------------------------------------------------------------ */
/* Articles                                                            */
/* ------------------------------------------------------------------ */

/** La condition « en ligne » : publié, et pas daté dans le futur. */
function sql_article_en_ligne(string $t = ''): string
{
    $p = $t ? $t . '.' : '';
    return "{$p}statut = 'publie' AND {$p}publie_le <= '" . maintenant() . "'";
}

/**
 * Les articles en ligne, filtrés et paginés.
 *
 * @return array{articles: array, total: int, pages: int}
 */
function articles_publies(int $page = 1, string $categorie = '', string $tag = '', string $recherche = '', int $par = ARTICLES_PAR_PAGE): array
{
    $where = [sql_article_en_ligne()];
    $p = [];
    if ($categorie !== '') {
        $where[] = 'categorie = ?';
        $p[] = $categorie;
    }
    if ($tag !== '') {
        // Les tags sont une liste JSON : on cherche la valeur exacte, guillemets compris.
        $where[] = 'tags LIKE ?';
        $p[] = '%' . json_encode($tag, JSON_UNESCAPED_UNICODE) . '%';
    }
    if ($recherche !== '') {
        $where[] = '(titre LIKE ? OR extrait LIKE ? OR contenu LIKE ?)';
        array_push($p, "%$recherche%", "%$recherche%", "%$recherche%");
    }
    $w = implode(' AND ', $where);
    $total = (int) valeur("SELECT COUNT(*) FROM articles WHERE $w", $p);
    $pages = max(1, (int) ceil($total / $par));
    $page = min(max(1, $page), $pages);
    $articles = tous("SELECT * FROM articles WHERE $w ORDER BY publie_le DESC, id DESC LIMIT $par OFFSET " . (($page - 1) * $par), $p);
    return ['articles' => $articles, 'total' => $total, 'pages' => $pages, 'page' => $page];
}

function article_par_slug(string $slug, bool $brouillon = false): ?array
{
    return un('SELECT * FROM articles WHERE slug = ?' . ($brouillon ? '' : ' AND ' . sql_article_en_ligne()), [$slug]);
}

function categories_articles(): array
{
    return tous('SELECT categorie, COUNT(*) n FROM articles WHERE ' . sql_article_en_ligne() .
        " AND categorie <> '' GROUP BY categorie ORDER BY n DESC, categorie");
}

function tags_articles(int $max = 30): array
{
    $compte = [];
    foreach (tous('SELECT tags FROM articles WHERE ' . sql_article_en_ligne()) as $r) {
        foreach (json_liste($r['tags']) as $t) {
            $compte[$t] = ($compte[$t] ?? 0) + 1;
        }
    }
    arsort($compte);
    return array_slice(array_keys($compte), 0, $max);
}

function slug_libre(string $slug, ?int $sauf = null): string
{
    $base = slugifier($slug);
    $s = $base;
    for ($i = 2; valeur('SELECT id FROM articles WHERE slug = ? AND id IS NOT ?', [$s, $sauf]) !== null; $i++) {
        $s = $base . '-' . $i;
    }
    return $s;
}

/** Les robots, aperçus de liens et outils de mesure : ils ne lisent pas, ils ne comptent pas. */
function est_robot(): bool
{
    $ua = strtolower((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
    return $ua === '' || (bool) preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegram|discord|curl|wget|python|headless(?!chrome)|lighthouse|pingdom/', $ua);
}

/**
 * Une lecture, signalée par la page elle-même une fois affichée
 * (`POST blog/{slug}/vue`, envoyé par `navigator.sendBeacon`).
 *
 * Compter au chargement côté serveur comptait les robots, les aperçus de
 * liens de WhatsApp, et ne comptait PAS l'équipe connectée — si bien qu'en
 * vérifiant le site soi-même, le compteur ne bougeait jamais. La page
 * envoie un signal ; le navigateur n'en envoie qu'un par onglet et par article.
 */
function article_lecture(string $slug): never
{
    $a = article_par_slug($slug);
    if ($a && !est_robot()) {
        q('UPDATE articles SET lectures = lectures + 1 WHERE id = ?', [$a['id']]);
    }
    http_response_code(204);
    exit;
}

/** L'image d'un article, avec repli sur la première image du texte. */
function article_image(array $a): string
{
    if ($a['image'] !== '') {
        return image_url($a['image']);
    }
    if (preg_match('#<img[^>]+src="([^"]+)"#i', (string) $a['contenu'], $m)) {
        return image_url(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
    }
    $d = (string) def('articles.image_defaut', '');
    return $d !== '' ? actif($d) : '';
}

function article_extrait(array $a, int $n = 220): string
{
    $extrait = article_l($a, 'extrait');
    return tronquer($extrait !== '' ? $extrait : texte_nu(article_l($a, 'contenu')), $n);
}

/** L'article a-t-il sa version anglaise ? (un titre et un texte au moins) */
function article_traduit(array $a): bool
{
    return trim((string) ($a['titre_en'] ?? '')) !== '' && trim((string) ($a['contenu_en'] ?? '')) !== '';
}

/** Un champ d'article dans la langue de la page, avec repli sur le français. */
function article_l(array $a, string $champ): string
{
    if (langue() === 'en' && article_traduit($a)) {
        $v = (string) ($a[$champ . '_en'] ?? '');
        if (trim($v) !== '') {
            return $v;
        }
    }
    return (string) ($a[$champ] ?? '');
}

/**
 * Le texte d'un article tel qu'on le range : nettoyé, et ses images locales
 * écrites `uploads/…` sans le chemin du site. Le site peut ainsi changer de
 * dossier (ou de domaine) sans qu'aucune image d'article ne casse.
 */
function contenu_stocker(string $html): string
{
    $html = html_nettoyer($html);
    $b = preg_quote(base_url() . '/', '#') . '|' . preg_quote(base_chemin() . '/', '#');
    return (string) preg_replace('#(src|href)="(?:' . $b . ')(uploads/|assets/)#', '$1="$2', $html);
}

/** …et tel qu'on l'affiche. */
function contenu_afficher(string $html): string
{
    return (string) preg_replace('#(src|href)="(uploads/|assets/)#', '$1="' . base_chemin() . '/$2', $html);
}

/* ------------------------------------------------------------------ */
/* Images encore extérieures                                           */
/* ------------------------------------------------------------------ */

/**
 * Les adresses d'images qui ne sont pas encore chez nous.
 *
 * @return list<array{table: string, id: int, champ: string, url: string}>
 */
function images_exterieures(): array
{
    $l = [];
    foreach (tous("SELECT id, image FROM articles WHERE image LIKE 'http%'") as $r) {
        $l[] = ['table' => 'articles', 'id' => (int) $r['id'], 'champ' => 'image', 'url' => $r['image']];
    }
    // Les champs image des collections (une campagne illustrée par Unsplash, un logo pris sur un autre site).
    foreach (tous("SELECT id, collection, donnees FROM elements WHERE donnees LIKE '%http%'") as $r) {
        $d = json_decode($r['donnees'], true) ?: [];
        foreach (collection_champs_de_type($r['collection'], 'image') as $k) {
            if (is_string($d[$k] ?? null) && preg_match('#^https?://#i', $d[$k])) {
                $l[] = ['table' => 'elements', 'id' => (int) $r['id'], 'champ' => $k, 'url' => $d[$k]];
            }
        }
    }
    foreach (tous("SELECT id, contenu FROM articles WHERE contenu LIKE '%src=\"http%'") as $r) {
        if (preg_match_all('#<img[^>]+src="(https?://[^"]+)"#i', $r['contenu'], $m)) {
            foreach (array_unique($m[1]) as $u) {
                $l[] = ['table' => 'articles', 'id' => (int) $r['id'], 'champ' => 'contenu', 'url' => html_entity_decode($u, ENT_QUOTES, 'UTF-8')];
            }
        }
    }
    return $l;
}

/**
 * Rapatrie jusqu'à `$max` images extérieures. Rend ce qui a été fait.
 *
 * @return array{faites: int, echecs: list<string>, restantes: int}
 */
function rapatrier_images(int $max = 8): array
{
    $faites = 0;
    $echecs = [];
    $echecs_vus = (array) json_decode(reglage('rapatriement_echecs') ?: '[]', true);
    foreach (images_exterieures() as $img) {
        if ($faites + count($echecs) >= $max) {
            break;
        }
        if (in_array($img['url'], $echecs_vus, true)) {
            continue;
        }
        $err = null;
        $local = media_depuis_url($img['url'], $err);
        if ($local === null) {
            $echecs[] = $img['url'] . ' — ' . $err;
            $echecs_vus[] = $img['url'];
            continue;
        }
        if ($img['table'] === 'elements') {
            element_modifier_champ($img['id'], $img['champ'], $local);
        } elseif ($img['champ'] === 'image') {
            q('UPDATE articles SET image = ? WHERE id = ?', [$local, $img['id']]);
        } else {
            $c = (string) valeur('SELECT contenu FROM articles WHERE id = ?', [$img['id']]);
            $c = html_images_remplacer($c, fn(string $u) => $u === $img['url'] ? $local : null);
            q('UPDATE articles SET contenu = ? WHERE id = ?', [$c, $img['id']]);
        }
        $faites++;
    }
    reglages_enregistrer(['rapatriement_echecs' => json_encode(array_values(array_unique($echecs_vus)))]);
    $restantes = count(array_filter(images_exterieures(), fn($i) => !in_array($i['url'], $echecs_vus, true)));
    return ['faites' => $faites, 'echecs' => $echecs, 'restantes' => $restantes];
}
