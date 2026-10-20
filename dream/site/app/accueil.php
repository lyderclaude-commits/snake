<?php
/**
 * Les contenus de l'accueil et de la page Services qui se règlent dans
 * l'administration : les services, les témoignages, les logos clients.
 *
 * Les textes d'un service ou d'un témoignage vivent dans deux colonnes JSON,
 * `fr` et `en` : un champ anglais laissé vide retombe sur le français, si
 * bien qu'une traduction incomplète ne laisse jamais de trou sur le site.
 */

declare(strict_types=1);

/** Les pictogrammes qu'on peut choisir pour un service (dessinés dans `icone()`). */
const ICONES_SERVICE = [
    'cible' => 'Cible (stratégie)', 'photos' => 'Photos (production)', 'design' => 'Crayon (graphisme)',
    'evenement' => 'Public (événementiel)', 'regie' => 'Mégaphone (publicité)', 'abonnement' => 'Étoile (offre)',
    'video' => 'Caméra (vidéo)', 'web' => 'Écran (web)', 'graphique' => 'Courbe (résultats)',
];

/** Un champ traduit d'une ligne `fr`/`en` : l'anglais s'il existe, sinon le français. */
function texte_l(array $ligne, string $cle): mixed
{
    $fr = is_array($ligne['fr'] ?? null) ? $ligne['fr'] : (json_decode((string) ($ligne['fr'] ?? '{}'), true) ?: []);
    if (langue() === 'en') {
        $en = is_array($ligne['en'] ?? null) ? $ligne['en'] : (json_decode((string) ($ligne['en'] ?? '{}'), true) ?: []);
        $v = $en[$cle] ?? null;
        if ($v !== null && $v !== '' && $v !== []) {
            return $v;
        }
    }
    return $fr[$cle] ?? (in_array($cle, ['tags', 'livrables'], true) ? [] : '');
}

function json_texte(array $v): string
{
    return json_encode($v, JSON_UNESCAPED_UNICODE);
}

/* ------------------------------------------------------------------ */
/* Lecture pour le site                                                */
/* ------------------------------------------------------------------ */

function services_visibles(): array
{
    return tous('SELECT * FROM services WHERE visible = 1 ORDER BY ordre, id');
}

function temoignages_visibles(): array
{
    return tous('SELECT * FROM temoignages WHERE visible = 1 ORDER BY ordre, id');
}

function clients_visibles(): array
{
    return tous('SELECT * FROM clients WHERE visible = 1 ORDER BY ordre, id');
}

/* ------------------------------------------------------------------ */
/* Les contenus livrés avec le site                                    */
/* ------------------------------------------------------------------ */

/**
 * Pose les services, témoignages et logos d'origine, une seule fois.
 *
 * Un drapeau dans les réglages, et non « la table est vide » : une équipe qui
 * supprime tous ses témoignages ne doit pas les voir revenir le lendemain.
 */
function installer_accueil(): void
{
    if (reglage('accueil_installe') === '1') {
        return;
    }
    $t = maintenant();
    db()->beginTransaction();
    if ((int) valeur('SELECT COUNT(*) FROM services') === 0) {
        $st = db()->prepare('INSERT INTO services (ancre, numero, icone, image, vedette, detail, fr, en, ordre, modifie_le) VALUES (?,?,?,?,?,?,?,?,?,?)');
        foreach (services_demonstration() as $i => $s) {
            $st->execute([$s['ancre'], $s['numero'], $s['icone'], $s['image'], $s['vedette'], $s['detail'], json_texte($s['fr']), json_texte($s['en']), $i, $t]);
        }
    }
    if ((int) valeur('SELECT COUNT(*) FROM temoignages') === 0) {
        $st = db()->prepare('INSERT INTO temoignages (entreprise, note, fr, en, ordre, modifie_le) VALUES (?,?,?,?,?,?)');
        foreach (temoignages_demonstration() as $i => $x) {
            $st->execute([$x[0], 5, json_texte(['texte' => $x[1], 'auteur' => $x[2]]), json_texte(['texte' => $x[3], 'auteur' => $x[4]]), $i, $t]);
        }
    }
    if ((int) valeur('SELECT COUNT(*) FROM clients') === 0) {
        $st = db()->prepare('INSERT INTO clients (nom, logo, ordre, modifie_le) VALUES (?,?,?,?)');
        foreach ([['UNICEF', 'assets/images/clients/unicef-1.svg'], ['UBA', ''], ['ECOBANK', 'assets/images/clients/Ecobank.svg'],
            ['WOODIN', ''], ['GIZ', ''], ['MSC', 'assets/images/clients/MSC.svg'], ['GROUPE KIK', ''], ['KRYSTAL', ''],
            ['KRAMEL', ''], ['MANTA HUB', ''], ['ABISCREAM', ''], ['LDM', '']] as $i => [$nom, $logo]) {
            $st->execute([$nom, $logo, $i, $t]);
        }
    }
    db()->commit();
    installer_campagnes_demonstration();
    completer_campagnes_en();
    reglages_enregistrer(['accueil_installe' => '1']);
}

function services_demonstration(): array
{
    $l = fn(array ...$p) => array_map(fn($x) => ['titre' => $x[0], 'texte' => $x[1]], $p);
    return [
        ['ancre' => 'community', 'numero' => '01', 'icone' => 'cible', 'image' => 'assets/images/site/strategie.jpg', 'vedette' => 0, 'detail' => 1,
            'fr' => ['titre' => 'Stratégie & Community Management', 'titre_detail' => 'Stratégie & *Community Management*',
                'resume' => 'Vos réseaux sociaux transformés en machines à attirer des clients. Contenus, calendriers, interactions, on gère tout.',
                'resume_court' => 'Stratégie, création et gestion de vos réseaux sociaux.', 'tags' => ['Facebook', 'Instagram', 'TikTok', 'LinkedIn'],
                'description' => 'Vos réseaux sociaux transformés en machines à attirer des clients. Chaque publication, chaque interaction est pensée pour convertir.',
                'livrables' => $l(['Stratégie de contenu mensuelle', 'Calendrier éditorial complet, thématiques, angles de communication'],
                    ['Création de contenus quotidiens', 'Posts, stories, reels, carrousels, créés par nos experts certifiés'],
                    ['Modération & engagement', 'Réponse aux commentaires et messages, gestion de la communauté'],
                    ['Reporting mensuel', 'Rapport détaillé avec KPIs, analyses et recommandations']),
                'prix_libelle' => 'À partir de', 'prix_valeur' => '75 000 FCFA/mois', 'bouton' => 'Demander un devis', 'etiquette' => 'Community Management'],
            'en' => ['titre' => 'Strategy & Community Management', 'titre_detail' => 'Strategy & *Community Management*',
                'resume' => 'Your social networks turned into client-attracting machines. Content, calendars, interactions: we handle it all.',
                'resume_court' => 'Strategy, creation and management of your social networks.', 'tags' => ['Facebook', 'Instagram', 'TikTok', 'LinkedIn'],
                'description' => 'Your social networks turned into client-attracting machines. Every post, every interaction is designed to convert.',
                'livrables' => $l(['Monthly content strategy', 'Full editorial calendar, themes, communication angles'],
                    ['Daily content creation', 'Posts, stories, reels and carousels, created by our certified experts'],
                    ['Moderation & engagement', 'Replies to comments and messages, community management'],
                    ['Monthly reporting', 'Detailed report with KPIs, analysis and recommendations']),
                'prix_libelle' => 'Starting from', 'prix_valeur' => '75,000 FCFA/month', 'bouton' => 'Request a quote', 'etiquette' => 'Community Management']],
        ['ancre' => 'video', 'numero' => '02', 'icone' => 'photos', 'image' => 'assets/images/site/production.jpg', 'vedette' => 0, 'detail' => 1,
            'fr' => ['titre' => 'Production Vidéo & Photo', 'titre_detail' => 'Production *Vidéo & Photo*',
                'resume' => 'Des images qui arrêtent le scroll et des vidéos qui font vendre. Studio équipé, équipe créative, direction artistique.',
                'resume_court' => 'Studio broadcast, direction artistique, post-production.', 'tags' => ['Publicités', 'Corporate', 'Shooting'],
                'description' => 'Des images qui arrêtent le scroll et des vidéos qui font vendre. Studio équipé broadcast, équipe créative, direction artistique maîtrisée.',
                'livrables' => $l(['Spots publicitaires TV & réseaux', 'Production complète : script, tournage, montage, étalonnage'],
                    ['Shooting produit & corporate', 'Photos professionnelles pour vos supports de communication'],
                    ['Reels & contenus courts', 'Formats optimisés pour Instagram, TikTok et Facebook'],
                    ['Motion design', 'Animations et vidéos infographiques pour vos présentations']),
                'prix_libelle' => 'Sur devis selon projet', 'prix_valeur' => 'Dès 100€', 'bouton' => 'Obtenir un devis', 'etiquette' => 'Studio Broadcast'],
            'en' => ['titre' => 'Video & Photo Production', 'titre_detail' => '*Video & Photo* Production',
                'resume' => 'Images that stop the scroll and videos that sell. Equipped studio, creative team, art direction.',
                'resume_court' => 'Broadcast studio, art direction, post-production.', 'tags' => ['Ads', 'Corporate', 'Photo shoots'],
                'description' => 'Images that stop the scroll and videos that sell. Broadcast-grade studio, creative team, expert art direction.',
                'livrables' => $l(['TV & social media commercials', 'Full production: script, shooting, editing, color grading'],
                    ['Product & corporate photo shoots', 'Professional photos for all your communication materials'],
                    ['Reels & short-form content', 'Formats optimized for Instagram, TikTok and Facebook'],
                    ['Motion design', 'Animations and infographic videos for your presentations']),
                'prix_libelle' => 'Quote based on project', 'prix_valeur' => 'From €100', 'bouton' => 'Get a quote', 'etiquette' => 'Broadcast Studio']],
        ['ancre' => 'graphisme', 'numero' => '03', 'icone' => 'design', 'image' => 'assets/images/site/conception.jpg', 'vedette' => 0, 'detail' => 1,
            'fr' => ['titre' => 'Conception Graphique & Identité', 'titre_detail' => "Conception Graphique\n& *Identité Visuelle*",
                'resume' => 'Un logo reconnaissable, une identité qui inspire confiance et fait la différence avec vos concurrents.',
                'resume_court' => 'Identité visuelle, logo, charte graphique, packaging.', 'tags' => ['Logo', 'Charte', 'Packaging'],
                'description' => "Un logo que vos clients reconnaissent au premier coup d'œil, une identité qui inspire confiance et fait la différence avec vos concurrents.",
                'livrables' => $l(['Création de logo', '3 propositions créatives, 2 tours de modifications inclus'],
                    ['Charte graphique complète', "Couleurs, typographies, règles d'usage, exemples d'application"],
                    ['Supports imprimés', 'Cartes de visite, flyers, affiches, roll-up, packaging'],
                    ['Habillage digital', 'Templates réseaux sociaux, bannières web, présentations']),
                'prix_libelle' => 'Logo complet à partir de', 'prix_valeur' => '50€', 'bouton' => 'Demander un devis', 'etiquette' => 'Identité Visuelle'],
            'en' => ['titre' => 'Graphic Design & Branding', 'titre_detail' => "Graphic Design\n& *Visual Identity*",
                'resume' => 'A memorable logo, an identity that builds trust and sets you apart from your competitors.',
                'resume_court' => 'Visual identity, logo, brand guidelines, packaging.', 'tags' => ['Logo', 'Brand guidelines', 'Packaging'],
                'description' => 'A logo your clients recognize at first glance, an identity that builds trust and sets you apart from your competitors.',
                'livrables' => $l(['Logo design', '3 creative proposals, 2 rounds of revisions included'],
                    ['Complete brand guidelines', 'Colors, typography, usage rules, application examples'],
                    ['Print materials', 'Business cards, flyers, posters, roll-up banners, packaging'],
                    ['Digital branding', 'Social media templates, web banners, presentations']),
                'prix_libelle' => 'Complete logo from', 'prix_valeur' => '€50', 'bouton' => 'Request a quote', 'etiquette' => 'Visual Identity']],
        ['ancre' => 'evenement', 'numero' => '04', 'icone' => 'evenement', 'image' => 'assets/images/site/organisation.jpg', 'vedette' => 0, 'detail' => 1,
            'fr' => ['titre' => 'Organisation Événementielle', 'titre_detail' => "Organisation\n*Événementielle*",
                'resume' => 'Vos événements transformés en expériences mémorables. De la conception au jour J, on gère tout.',
                'resume_court' => 'Organisation et communication de vos événements.', 'tags' => ['Lancement', 'Corporate', 'Activation'],
                'description' => 'Vos événements transformés en expériences mémorables. De la conception au jour J, nous gérons tout pour que vous soyez présent à 100%.',
                'livrables' => $l(['Conception scénographique', 'Direction artistique, décoration, ambiance visuelle complète'],
                    ['Communication événement', 'Campagne digitale, invitations, affiches, relations presse'],
                    ['Coordination jour J', 'Équipe dédiée sur place, logistique, gestion des imprévus'],
                    ['Couverture audiovisuelle', 'Photo et vidéo live, montage post-événement, restitution']),
                'prix_libelle' => 'Sur devis selon envergure', 'prix_valeur' => 'Dès 300€', 'bouton' => 'Obtenir un devis', 'etiquette' => 'Activation de Marque'],
            'en' => ['titre' => 'Event Management', 'titre_detail' => "Event\n*Management*",
                'resume' => 'Your events turned into memorable experiences. From concept to the big day, we handle everything.',
                'resume_court' => 'Organization and promotion of your events.', 'tags' => ['Launch', 'Corporate', 'Activation'],
                'description' => 'Your events turned into memorable experiences. From concept to the big day, we handle everything so you can be 100% present.',
                'livrables' => $l(['Stage design', 'Art direction, decoration, complete visual atmosphere'],
                    ['Event communication', 'Digital campaign, invitations, posters, press relations'],
                    ['On-the-day coordination', 'Dedicated on-site team, logistics, handling the unexpected'],
                    ['Audiovisual coverage', 'Live photo and video, post-event editing, highlights']),
                'prix_libelle' => 'Quote based on scale', 'prix_valeur' => 'From €300', 'bouton' => 'Get a quote', 'etiquette' => 'Brand Activation']],
        ['ancre' => 'regie', 'numero' => '05', 'icone' => 'regie', 'image' => 'assets/images/site/regie.jpg', 'vedette' => 0, 'detail' => 1,
            'fr' => ['titre' => 'Régie Publicitaire & Affichage', 'titre_detail' => "Régie Publicitaire\n& *Affichage*",
                'resume' => 'Panneaux, affichage digital, pub Facebook/Google, chaque FCFA investi maximisé.',
                'resume_court' => 'Affichage, Facebook Ads, Google Ads, billboards.', 'tags' => ['Billboards', 'Facebook Ads', 'Google Ads'],
                'description' => 'Panneaux, affichage digital, pub Facebook/Google, nous gérons vos investissements publicitaires pour maximiser chaque FCFA dépensé.',
                'livrables' => $l(['Affichage extérieur & billboards', 'Sélection des emplacements, création des visuels, gestion des droits'],
                    ['Facebook & Instagram Ads', 'Ciblage précis, création des publicités, optimisation quotidienne'],
                    ['Google Ads & SEA', 'Campagnes de recherche et display, mots-clés optimisés'],
                    ['Suivi & optimisation', 'Reporting détaillé, ajustements en temps réel, recommandations']),
                'prix_libelle' => 'Gestion à partir de', 'prix_valeur' => '100€/mois', 'bouton' => 'Demander un devis', 'etiquette' => 'Régie Publicitaire'],
            'en' => ['titre' => 'Advertising & Billboards', 'titre_detail' => "Advertising\n& *Billboards*",
                'resume' => 'Billboards, digital displays, Facebook/Google ads: every FCFA invested, maximized.',
                'resume_court' => 'Billboards, Facebook Ads, Google Ads, outdoor.', 'tags' => ['Billboards', 'Facebook Ads', 'Google Ads'],
                'description' => 'Billboards, digital displays, Facebook/Google ads: we manage your advertising investments to get the most out of every FCFA spent.',
                'livrables' => $l(['Outdoor advertising & billboards', 'Location selection, visual creation, rights management'],
                    ['Facebook & Instagram Ads', 'Precise targeting, ad creation, daily optimization'],
                    ['Google Ads & SEA', 'Search and display campaigns, optimized keywords'],
                    ['Tracking & optimization', 'Detailed reporting, real-time adjustments, recommendations']),
                'prix_libelle' => 'Management from', 'prix_valeur' => '€100/month', 'bouton' => 'Request a quote', 'etiquette' => 'Advertising']],
        ['ancre' => 'abonnement', 'numero' => '★', 'icone' => 'abonnement', 'image' => '', 'vedette' => 1, 'detail' => 0,
            'fr' => ['titre' => 'Abonnement Communication PME', 'titre_detail' => '', 'resume' => 'La solution tout-en-un pour les PME. Visibilité garantie, résultats mesurés, sans se prendre la tête.',
                'resume_court' => 'Tout-en-un à partir de 200€/mois.', 'tags' => ['Dès 200€/mois'], 'description' => '', 'livrables' => [],
                'prix_libelle' => '', 'prix_valeur' => '', 'bouton' => "Découvrir l'offre", 'etiquette' => ''],
            'en' => ['titre' => 'SME Communication Plan', 'titre_detail' => '', 'resume' => 'The all-in-one solution for SMEs. Guaranteed visibility, measured results, hassle-free.',
                'resume_court' => 'All-in-one from €200/month.', 'tags' => ['From €200/month'], 'description' => '', 'livrables' => [],
                'prix_libelle' => '', 'prix_valeur' => '', 'bouton' => 'Discover the plan', 'etiquette' => '']],
    ];
}

function temoignages_demonstration(): array
{
    return [
        ['Woodin Togo', "Live Y Dream nous accompagne depuis 3 ans. Ils ont su s'immerger dans notre culture d'entreprise pour nous offrir une identité visuelle incomparable. Leur réactivité est indéniablement leur plus grand atout.", 'Directeur Marketing',
            'Live Y Dream has been working with us for 3 years. They immersed themselves in our company culture to give us an unrivaled visual identity. Their responsiveness is undoubtedly their greatest strength.', 'Marketing Director'],
        ['Groupe KIK', 'Les missions confiées à Live Y Dream ont été réussies. Planification rigoureuse, sérieux et créativité ont apporté notre satisfaction. Je les recommande sans aucune hésitation à qui veut développer sa visibilité.', 'Responsable Communication',
            'Every project we entrusted to Live Y Dream was a success. Rigorous planning, professionalism and creativity left us fully satisfied. I recommend them without hesitation to anyone looking to grow their visibility.', 'Head of Communications'],
        ['Manta Hub', 'Live Y Dream apporte de bonnes réponses en termes de visibilité. Nos interlocuteurs font preuve d\'une excellente capacité d\'écoute tant pour la nature de nos besoins que pour la mise en œuvre des actions.', 'Directrice Générale',
            'Live Y Dream delivers the right answers when it comes to visibility. Our contacts listen remarkably well, both to understand our needs and to carry out the actions.', 'Managing Director'],
        ['Abiscream', 'Une agence créative, réactive et sympathique qui sait mettre la satisfaction client au cœur de ses préoccupations. À travers les projets réalisés, ils ont su répondre à toutes nos demandes, quelle que soit leur complexité.', 'Responsable',
            'A creative, responsive and friendly agency that puts client satisfaction at the heart of everything it does. Across every project, they met all our requests, however complex.', 'Manager'],
    ];
}
