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
    require RACINE . '/app/vues/site/_gabarit.php';
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
        'description' => 'Agence de communication et marketing digital à Lomé, Togo',
        'url' => url_absolue(''),
        'telephone' => preg_replace('/[^\d+]/', '', reglage('telephone')),
        'email' => reglage('email'),
        'image' => url_absolue('assets/images/site/background.jpg'),
        'address' => ['@type' => 'PostalAddress', 'streetAddress' => reglage('adresse'), 'addressLocality' => 'Lomé', 'addressCountry' => 'TG'],
        'foundingDate' => '2013',
        'sameAs' => array_values(array_filter([reglage('facebook'), reglage('instagram'), reglage('linkedin'), reglage('tiktok')])),
    ];
}

/** Le plan du site pour les moteurs. */
function sitemap(): string
{
    $x = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach (['', 'services', 'campagnes', 'blog', 'a-propos', 'contact'] as $p) {
        $x .= '  <url><loc>' . e(url_absolue($p)) . "</loc></url>\n";
    }
    foreach (tous('SELECT slug, modifie_le FROM articles WHERE ' . sql_article_en_ligne() . ' ORDER BY publie_le DESC') as $a) {
        $x .= '  <url><loc>' . e(url_absolue('blog/' . rawurlencode($a['slug']))) . '</loc><lastmod>'
            . e(substr((string) $a['modifie_le'], 0, 10)) . "</lastmod></url>\n";
    }
    return $x . "</urlset>\n";
}

/** Une demande de contact : enregistrée, puis signalée par e-mail. */
function contact_recevoir(array $v): ?string
{
    if (trim((string) ($v['site_web'] ?? '')) !== '') {
        return null; // le champ piège : un robot l'a rempli, on fait semblant d'accepter
    }
    $d = [];
    foreach (['nom' => 120, 'entreprise' => 120, 'email' => 160, 'whatsapp' => 40, 'besoin' => 80, 'budget' => 80, 'message' => 5000] as $k => $max) {
        $d[$k] = mb_substr(trim((string) ($v[$k] ?? '')), 0, $max);
    }
    if ($d['nom'] === '' || $d['whatsapp'] === '') {
        return 'Indiquez votre nom et votre numéro WhatsApp.';
    }
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
        return 'Cette adresse e-mail ne semble pas valide.';
    }
    $cle = 'contact:' . ip_client();
    if (tentatives_recentes($cle, 3600) >= 5) {
        return 'Vous avez déjà envoyé plusieurs demandes. Pour aller plus vite, écrivez-nous sur WhatsApp.';
    }
    tentative_noter($cle);
    q('INSERT INTO messages (nom, entreprise, email, whatsapp, besoin, budget, message, ip, cree_le) VALUES (?,?,?,?,?,?,?,?,?)',
        [$d['nom'], $d['entreprise'], $d['email'], $d['whatsapp'], $d['besoin'], $d['budget'], $d['message'], ip_client(), maintenant()]);
    contact_notifier($d, (int) db()->lastInsertId());
    return null;
}

function contact_notifier(array $d, int $id): void
{
    $a = trim(reglage('contact_destinataire'));
    if ($a === '' || !filter_var($a, FILTER_VALIDATE_EMAIL) || !function_exists('mail')) {
        return;
    }
    $hote = (string) parse_url(base_url(), PHP_URL_HOST);
    $sujet = '=?UTF-8?B?' . base64_encode('Nouvelle demande : ' . $d['nom'] . ($d['besoin'] ? ' — ' . $d['besoin'] : '')) . '?=';
    $corps = "Nouvelle demande reçue sur le site.\n\n"
        . "Nom : {$d['nom']}\nEntreprise : {$d['entreprise']}\nE-mail : {$d['email']}\nWhatsApp : {$d['whatsapp']}\n"
        . "Besoin : {$d['besoin']}\nBudget : {$d['budget']}\n\n{$d['message']}\n\n"
        . 'Voir dans l’administration : ' . url_absolue('admin/messages/' . $id) . "\n";
    $entetes = [
        'From: ' . reglage('nom_site') . ' <ne-pas-repondre@' . ($hote ?: 'localhost') . '>',
        'Reply-To: ' . str_replace(["\r", "\n"], '', $d['email']),
        'Content-Type: text/plain; charset=UTF-8',
        'MIME-Version: 1.0',
    ];
    @mail($a, $sujet, $corps, implode("\r\n", $entetes));
}
