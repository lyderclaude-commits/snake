<?php
/**
 * Articles et campagnes : lecture pour le site, écriture pour l'administration.
 */

declare(strict_types=1);

const CATEGORIES_CAMPAGNE = [
    'digital' => 'Stratégie Digitale',
    'video' => 'Vidéo & Photo',
    'event' => 'Événementiel',
    'brand' => 'Identité Visuelle',
];

const ARTICLES_PAR_PAGE = 9;

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

/** L'image d'un article, avec repli sur la première image du texte. */
function article_image(array $a): string
{
    if ($a['image'] !== '') {
        return image_url($a['image']);
    }
    if (preg_match('#<img[^>]+src="([^"]+)"#i', (string) $a['contenu'], $m)) {
        return image_url(html_entity_decode($m[1], ENT_QUOTES, 'UTF-8'));
    }
    return actif('assets/images/site/agency.jpg');
}

function article_extrait(array $a, int $n = 220): string
{
    $extrait = function_exists('article_l') ? article_l($a, 'extrait') : (string) $a['extrait'];
    $contenu = function_exists('article_l') ? article_l($a, 'contenu') : (string) $a['contenu'];
    return tronquer($extrait !== '' ? $extrait : texte_nu($contenu), $n);
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
/* Campagnes                                                           */
/* ------------------------------------------------------------------ */

function campagnes_publiees(int $limite = 0): array
{
    return array_map('campagne_decoder', tous('SELECT * FROM campagnes WHERE publie = 1 ORDER BY vedette DESC, ordre, id DESC'
        . ($limite ? ' LIMIT ' . $limite : '')));
}

function campagne_decoder(array $c): array
{
    $c['resultats'] = json_liste($c['resultats']);
    $c['tags'] = json_liste($c['tags']);
    return $c;
}

function categorie_campagne(string $c): string
{
    return CATEGORIES_CAMPAGNE[$c] ?? $c;
}

/** Les campagnes livrées avec le site : son portfolio d'origine. */
function campagnes_demonstration(): array
{
    return [
        ['Woodin Togo — Domination Digitale', 'Woodin', 'digital', '2023', 'Refonte complète de la stratégie digitale. +340% d\'engagement en 3 mois.', 'Mandatés pour refondre entièrement la présence digitale de Woodin Togo, nous avons conçu et exécuté une stratégie de contenu complète sur Instagram, Facebook et TikTok. Shooting produit mensuel, calendrier éditorial thématique calé sur les temps forts culturels togolais, gestion quotidienne de la communauté.', 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=1200&q=80', 1, ['+340%', 'Engagement', '50k+', 'Followers', '×4', 'Portée organique', '★★★★★', 'Satisfaction'], ['Community Management', 'Stratégie Digitale', 'Contenu']],
        ['Battle Arena — Compétition Internationale', 'Live Y Dream', 'event', '2019-2024', 'Concept événementiel devenu la référence nationale de la danse urbaine au Togo.', 'Concept événementiel créé et produit par Live Y Dream. Une compétition de danse urbaine devenue la référence nationale, attirant des danseurs de toute l\'Afrique de l\'Ouest. Production complète : scénographie, communication, billetterie, couverture audiovisuelle, partenariats.', 'https://images.unsplash.com/photo-1508700115892-45ecd05ae2ad?w=1200&q=80', 1, ['1000+', 'Participants', '5', 'Éditions', '15k+', 'Spectateurs cumulés', 'N°1', 'Au Togo'], ['Événementiel', 'Production', 'Communication']],
        ['Groupe KIK — Campagne Publicitaire 360°', 'Groupe KIK', 'video', '2022', 'Série de spots publicitaires pour les différentes marques du groupe, diffusés sur tous les canaux.', 'Production d\'une série de spots publicitaires pour les différentes marques du Groupe KIK. Direction artistique, casting, tournage en studio et en extérieur, post-production et déclinaison pour tous les canaux : TV, réseaux sociaux, affichage.', 'https://images.unsplash.com/photo-1492691527719-9d1e07e534b4?w=1200&q=80', 0, ['8', 'Spots produits', '4', 'Marques couvertes', '100%', 'Délais respectés', '★★★★★', 'Satisfaction'], ['Production Vidéo', 'Publicité', 'Multi-canal']],
        ['Krystal Optique — Nouvelle Identité', 'Krystal Optique', 'brand', '2023', 'Refonte complète de l\'identité visuelle. Logo, charte, supports et réseaux sociaux.', 'Refonte complète de l\'identité visuelle de Krystal Optique. Nouveau logo, charte graphique complète, tous les supports de communication, signalétique boutique et habillage réseaux sociaux. Un repositionnement premium qui a transformé la perception de la marque.', 'https://images.unsplash.com/photo-1574258495973-f010dfbb5371?w=1200&q=80', 0, ['Logo', 'Redesigné', 'Charte', 'Complète', 'RS', 'Habillés', '+40%', 'Notoriété'], ['Identité Visuelle', 'Logo', 'Branding']],
        ['Kramel — Croissance Community Management', 'Kramel', 'digital', '2022-2023', '+280% de croissance de la communauté et augmentation directe des ventes en ligne.', 'Gestion complète des réseaux sociaux de Kramel avec une stratégie de contenu orientée conversion. Shooting produits récurrents, animations, stories interactives, publicités Facebook ciblées et campagnes saisonnières. Résultats mesurables et traçables.', 'https://images.unsplash.com/photo-1551024601-bec78aea704b?w=1200&q=80', 0, ['+280%', 'Croissance', '+150%', 'Ventes online', '2×', 'Trafic boutique', 'Top 3', 'Lomé'], ['Community Management', 'Facebook Ads', 'Contenu']],
        ['Lomé By Night — Concept Événementiel', 'Live Y Dream', 'event', '2018-2019', 'Un concept qui a réuni plus de 1000 personnes pour explorer Lomé sous un nouveau jour.', 'Concept créé par Live Y Dream pour explorer les facettes nocturnes de Lomé. Événement qui a réuni plus de 1 000 personnes autour de la découverte culturelle et gastronomique de la capitale togolaise. Communication virale, vente de billets en ligne, couverture média.', 'https://images.unsplash.com/photo-1540575467063-178a50c2df87?w=1200&q=80', 0, ['1000+', 'Personnes', '2', 'Éditions', '100%', 'Sold Out', 'Viral', 'Sur les RS'], ['Concept', 'Événementiel', 'Lomé']],
    ];
}

/** L'anglais des campagnes livrées, rangé par titre français. */
function campagnes_demonstration_en(): array
{
    return [
        'Woodin Togo — Domination Digitale' => ['titre' => 'Woodin Togo — Digital Domination', 'extrait' => 'A complete overhaul of the digital strategy. +340% engagement in 3 months.',
            'description' => 'Hired to completely rebuild Woodin Togo\'s digital presence, we designed and executed a full content strategy across Instagram, Facebook and TikTok: monthly product shoots, a themed editorial calendar built around key Togolese cultural moments, and daily community management.',
            'resultats' => ['+340%', 'Engagement', '50k+', 'Followers', '×4', 'Organic reach', '★★★★★', 'Satisfaction'], 'tags' => ['Community Management', 'Digital Strategy', 'Content']],
        'Battle Arena — Compétition Internationale' => ['titre' => 'Battle Arena — International Competition', 'extrait' => 'An event concept that became the national benchmark for urban dance in Togo.',
            'description' => 'An event concept created and produced by Live Y Dream. An urban dance competition that became the national benchmark, drawing dancers from all over West Africa. Full production: stage design, communication, ticketing, audiovisual coverage, partnerships.',
            'resultats' => ['1000+', 'Participants', '5', 'Editions', '15k+', 'Total spectators', 'No.1', 'In Togo'], 'tags' => ['Events', 'Production', 'Communication']],
        'Groupe KIK — Campagne Publicitaire 360°' => ['titre' => 'Groupe KIK — 360° Advertising Campaign', 'extrait' => 'A series of commercials for the group\'s brands, broadcast across every channel.',
            'description' => 'Production of a series of commercials for the Groupe KIK brands. Art direction, casting, studio and outdoor shooting, post-production and adaptation for every channel: TV, social media, billboards.',
            'resultats' => ['8', 'Commercials produced', '4', 'Brands covered', '100%', 'Deadlines met', '★★★★★', 'Satisfaction'], 'tags' => ['Video Production', 'Advertising', 'Multi-channel']],
        'Krystal Optique — Nouvelle Identité' => ['titre' => 'Krystal Optique — New Identity', 'extrait' => 'A complete visual identity overhaul. Logo, guidelines, materials and social media.',
            'description' => 'A complete overhaul of Krystal Optique\'s visual identity: new logo, full brand guidelines, all communication materials, store signage and social media branding. A premium repositioning that transformed how the brand is perceived.',
            'resultats' => ['Logo', 'Redesigned', 'Guidelines', 'Complete', 'Social', 'Rebranded', '+40%', 'Awareness'], 'tags' => ['Visual Identity', 'Logo', 'Branding']],
        'Kramel — Croissance Community Management' => ['titre' => 'Kramel — Community Management Growth', 'extrait' => '+280% community growth and a direct increase in online sales.',
            'description' => 'Full social media management for Kramel with a conversion-driven content strategy: recurring product shoots, giveaways, interactive stories, targeted Facebook ads and seasonal campaigns. Measurable, trackable results.',
            'resultats' => ['+280%', 'Growth', '+150%', 'Online sales', '2×', 'Store traffic', 'Top 3', 'Lomé'], 'tags' => ['Community Management', 'Facebook Ads', 'Content']],
        'Lomé By Night — Concept Événementiel' => ['titre' => 'Lomé By Night — Event Concept', 'extrait' => 'A concept that brought together over 1,000 people to discover Lomé in a new light.',
            'description' => 'A concept created by Live Y Dream to explore Lomé\'s nightlife. The event brought more than 1,000 people together around the cultural and culinary discovery of the Togolese capital. Viral communication, online ticketing, media coverage.',
            'resultats' => ['1000+', 'People', '2', 'Editions', '100%', 'Sold out', 'Viral', 'On social media'], 'tags' => ['Concept', 'Events', 'Lomé']],
    ];
}

/** Donne leur anglais aux campagnes livrées qui n'en ont pas encore (bases créées avant le bilinguisme). */
function completer_campagnes_en(): void
{
    foreach (campagnes_demonstration_en() as $titre => $en) {
        q("UPDATE campagnes SET en = ? WHERE titre = ? AND en IN ('', '{}')", [json_encode($en, JSON_UNESCAPED_UNICODE), $titre]);
    }
}

function installer_campagnes_demonstration(): void
{
    if ((int) valeur('SELECT COUNT(*) FROM campagnes') > 0) {
        return;
    }
    $st = db()->prepare('INSERT INTO campagnes (titre, client, categorie, annee, extrait, description, image, vedette, resultats, tags, publie, ordre, cree_le, modifie_le)
        VALUES (?,?,?,?,?,?,?,?,?,?,1,?,?,?)');
    foreach (campagnes_demonstration() as $i => $c) {
        $st->execute([$c[0], $c[1], $c[2], $c[3], $c[4], $c[5], $c[6], $c[7],
            json_encode($c[8], JSON_UNESCAPED_UNICODE), json_encode($c[9], JSON_UNESCAPED_UNICODE), $i, maintenant(), maintenant()]);
    }
    completer_campagnes_en();
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
    foreach (tous("SELECT id, image FROM campagnes WHERE image LIKE 'http%'") as $r) {
        $l[] = ['table' => 'campagnes', 'id' => (int) $r['id'], 'champ' => 'image', 'url' => $r['image']];
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
        if ($img['champ'] === 'image') {
            q("UPDATE {$img['table']} SET image = ? WHERE id = ?", [$local, $img['id']]);
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
