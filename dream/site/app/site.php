<?php
/**
 * Les aides du site public : gabarit, liens de contact, icônes, référencement.
 */

declare(strict_types=1);

const BESOINS = ['Community Management', 'Production Vidéo & Photo', 'Conception Graphique & Identité',
    'Organisation Événementielle', 'Régie Publicitaire', 'Abonnement PME', 'Stratégie Digitale Complète', 'Autre'];
const BUDGETS = ['Moins de 100 000 FCFA', '100 000 — 300 000 FCFA', '300 000 — 1 000 000 FCFA',
    'Plus de 1 000 000 FCFA', 'À définir ensemble'];

/** Rend une page publique dans le gabarit du site. */
function vue_site(string $nom, array $donnees = [], int $code = 200): never
{
    http_response_code($code);
    $donnees['page'] ??= $nom;
    extract($donnees, EXTR_SKIP);
    $tete = '';
    ob_start();
    require RACINE . '/app/vues/site/' . $nom . '.php';
    $contenu = ob_get_clean();
    ob_start();
    require RACINE . '/app/vues/site/_gabarit.php';
    $page_html = (string) ob_get_clean();
    // La page anglaise : les textes écrits dans les vues passent au dictionnaire.
    echo langue() === 'fr' ? $page_html : traduire_html($page_html, langue());
    exit;
}

function wa_lien(string $texte = ''): string
{
    $n = preg_replace('/\D+/', '', reglage('whatsapp')) ?: '22870087777';
    return 'https://wa.me/' . $n . ($texte !== '' ? '?text=' . rawurlencode($texte) : '');
}

function tel_lien(string $numero): string
{
    return 'tel:' . preg_replace('/[^\d+]/', '', $numero);
}

/**
 * Un chiffre clé : « 200+ » s'affiche tel quel (lisible sans script, et
 * par les moteurs), puis s'anime de 0 à 200 quand il entre à l'écran.
 */
function compteur(string $valeur, string $classe): string
{
    $attr = '';
    if (preg_match('/^(\d+)(\D*)$/u', trim($valeur), $m)) {
        $attr = ' data-count="' . $m[1] . '"' . ($m[2] !== '' ? ' data-suffix="' . e($m[2]) . '"' : '');
    }
    return '<div class="' . e($classe) . '"' . $attr . '>' . e($valeur) . '</div>';
}

/**
 * Les pictogrammes du site, dessinés ici.
 *
 * Le site livré les chargeait depuis icons8.com : une requête vers un
 * tiers par icône, et des images qui disparaissent le jour où ce service
 * change ses adresses. Un trait de 1,5 px, comme les originaux.
 */
function icone(string $nom, int $taille = 40): string
{
    $traits = [
        'cible' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.2"/><path d="M21 3l-7.5 7.5M17 3h4v4"/>',
        'photos' => '<rect x="3" y="7" width="14" height="13" rx="1.5"/><path d="M7 4h13.5A.5.5 0 0 1 21 4.5V17"/><circle cx="8" cy="11.5" r="1.5"/><path d="M3 18l4.5-4.5 3 3 2-2L17 18"/>',
        'design' => '<path d="M4 20l4-1L19.5 7.5a2.1 2.1 0 0 0-3-3L5 16l-1 4z"/><path d="M14.5 6.5l3 3"/><path d="M4 4h6M4 4v6"/>',
        'evenement' => '<circle cx="12" cy="7" r="3"/><path d="M6 21v-2a6 6 0 0 1 12 0v2"/><circle cx="4.5" cy="10" r="2"/><circle cx="19.5" cy="10" r="2"/><path d="M1.5 19a3.5 3.5 0 0 1 4-3.4M22.5 19a3.5 3.5 0 0 0-4-3.4"/>',
        'regie' => '<path d="M3 10v4a1 1 0 0 0 1 1h2l5 4V5L6 9H4a1 1 0 0 0-1 1z"/><path d="M15 9a4 4 0 0 1 0 6M18 6a8 8 0 0 1 0 12"/>',
        'lieu' => '<path d="M12 21s-7-6.2-7-11.5A7 7 0 0 1 19 9.5C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'telephone' => '<path d="M5 3h3.5l1.5 4.5-2 1.5a12 12 0 0 0 7 7l1.5-2 4.5 1.5V19a2 2 0 0 1-2 2A17 17 0 0 1 3 5a2 2 0 0 1 2-2z"/>',
        'courriel' => '<rect x="3" y="5" width="18" height="14" rx="1.5"/><path d="M3.5 6l8.5 7 8.5-7"/>',
        'abonnement' => '<path d="M12 3l2.6 5.4 5.9.8-4.3 4.1 1 5.8L12 16.4 6.8 19.1l1-5.8L3.5 9.2l5.9-.8z"/>',
        'video' => '<rect x="3" y="6" width="13" height="12" rx="1.5"/><path d="M16 10l5-3v10l-5-3z"/>',
        'web' => '<rect x="3" y="4" width="18" height="13" rx="1.5"/><path d="M8 21h8M12 17v4M3 8h18"/>',
        'graphique' => '<path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 6-7"/><path d="M16 7h4v4"/>',
    ];
    return '<svg width="' . $taille . '" height="' . $taille . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
        . 'stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($traits[$nom] ?? '') . '</svg>';
}

/** Une adresse rendue par `url()` ou `image_url()`, en absolu (pour les balises de partage). */
function absolu(string $u): string
{
    if (preg_match('#^https?://#i', $u)) {
        return $u;
    }
    $b = base_chemin();
    if ($b !== '' && str_starts_with($u, $b . '/')) {
        $u = substr($u, strlen($b));
    }
    return url_absolue(explode('?', $u)[0]);
}

function jsonld(array $d): string
{
    return json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP);
}

function jsonld_organisation(): array
{
    return [
        '@type' => 'LocalBusiness',
        '@id' => url_absolue('') . '#organisation',
        'name' => reglage('nom_site'),
        'description' => langue() === 'en' ? 'Communication and digital marketing agency in Lomé, Togo' : 'Agence de communication et marketing digital à Lomé, Togo',
        'url' => url_absolue(''),
        'telephone' => preg_replace('/[^\d+]/', '', reglage('telephone')),
        'email' => reglage('email'),
        'image' => url_absolue('assets/images/site/background.jpg'),
        'address' => ['@type' => 'PostalAddress', 'streetAddress' => reglage('adresse'), 'addressLocality' => 'Lomé', 'addressCountry' => 'TG'],
        'foundingDate' => '2013',
        'sameAs' => array_values(array_filter([reglage('facebook'), reglage('instagram'), reglage('linkedin'), reglage('tiktok')])),
    ];
}

/**
 * Le plan du site pour les moteurs, dans les deux langues.
 *
 * Chaque page annonce sa jumelle (`xhtml:link hreflang`) : Google sert
 * alors la bonne langue à chacun au lieu de les compter comme deux pages
 * concurrentes. Un article non traduit n'a qu'une adresse, la française.
 */
function sitemap(): string
{
    $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
        . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
    $entree = static function (string $p, array $langues, string $lastmod = '') {
        $s = '';
        foreach ($langues as $l) {
            $s .= '  <url><loc>' . e(url_absolue($p, $l)) . '</loc>' . ($lastmod !== '' ? '<lastmod>' . e($lastmod) . '</lastmod>' : '');
            if (count($langues) > 1) {
                foreach ($langues as $autre) {
                    $s .= '<xhtml:link rel="alternate" hreflang="' . $autre . '" href="' . e(url_absolue($p, $autre)) . '"/>';
                }
            }
            $s .= "</url>\n";
        }
        return $s;
    };
    foreach (['', 'services', 'campagnes', 'blog', 'a-propos', 'contact'] as $p) {
        $x .= $entree($p, array_keys(LANGUES));
    }
    foreach (tous('SELECT slug, modifie_le, titre_en, contenu_en FROM articles WHERE ' . sql_article_en_ligne() . ' ORDER BY publie_le DESC') as $a) {
        $x .= $entree('blog/' . rawurlencode($a['slug']), article_traduit($a) ? array_keys(LANGUES) : ['fr'], substr((string) $a['modifie_le'], 0, 10));
    }
    return $x . "</urlset>\n";
}

/** Le titre d'un service : `*mots*` en italique doré, une ligne par retour à la ligne. */
function titre_service(string $s): string
{
    return nl2br((string) preg_replace('/\*(.+?)\*/u', '<em>$1</em>', e($s)), false);
}

/** Le texte saisi en clair dans un réglage (`**gras**`, `*or*`, `_contour_`), rendu en HTML sûr. */
function texte_marque(string $s): string
{
    $h = e($s);
    $h = (string) preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $h);
    $h = (string) preg_replace('/\*(.+?)\*/u', '<span class="gold">$1</span>', $h);
    $h = (string) preg_replace('/(?<![\w])_(.+?)_(?![\w])/u', '<span class="stroke">$1</span>', $h);
    return nl2br($h, false);
}
