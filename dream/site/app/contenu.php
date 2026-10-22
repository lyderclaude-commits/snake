<?php
/**
 * Les campagnes du portfolio : lecture pour le site, et le portfolio
 * d'origine posé à l'installation. Elles se règlent dans l'administration
 * (collection `campagnes`, déclarée dans `app/definition.php`).
 */

declare(strict_types=1);

/**
 * Les campagnes en ligne, dans la langue de la page, triées (« à la une »
 * d'abord), prêtes pour les vues : `resultats` à plat, comme le script de
 * la fiche les attend (« +340% », « Engagement », …).
 */
function campagnes_publiees(int $limite = 0): array
{
    return array_map('campagne_publique', elements('campagnes', ['limite' => $limite]));
}

function campagne_publique(array $c): array
{
    $plat = [];
    foreach ((array) $c['resultats'] as $r) {
        $plat[] = (string) ($r['valeur'] ?? '');
        $plat[] = (string) ($r['libelle'] ?? '');
    }
    $c['resultats'] = $plat;
    return $c;
}

function categorie_campagne(string $c): string
{
    return CATEGORIES_CAMPAGNE[$c] ?? $c;
}

/* ------------------------------------------------------------------ */
/* Le portfolio d'origine                                              */
/* ------------------------------------------------------------------ */

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

/** Pose le portfolio d'origine dans la collection, avec son anglais. */
function installer_campagnes_demonstration(): void
{
    $en = campagnes_demonstration_en();
    foreach (campagnes_demonstration() as $i => $c) {
        $paires = array_chunk($c[8], 2);
        $paires_en = array_chunk($en[$c[0]]['resultats'] ?? [], 2);
        element_creer('campagnes', [
            'titre' => $c[0], 'client' => $c[1], 'categorie' => $c[2], 'annee' => $c[3], 'extrait' => $c[4], 'description' => $c[5],
            'image' => $c[6], 'vedette' => $c[7], 'resultats' => array_map(fn($p) => ['valeur' => $p[0], 'libelle' => $p[1] ?? ''], $paires), 'tags' => $c[9],
        ], [
            'titre' => $en[$c[0]]['titre'] ?? '', 'extrait' => $en[$c[0]]['extrait'] ?? '', 'description' => $en[$c[0]]['description'] ?? '',
            'tags' => $en[$c[0]]['tags'] ?? [], 'resultats' => array_map(fn($p) => ['libelle' => $p[1] ?? ''], $paires_en),
        ], true, $i);
    }
}
