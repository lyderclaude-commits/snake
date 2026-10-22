<?php
/**
 * La définition de Live Y Dream pour le socle d'administration.
 *
 * Tout ce qui fait de l'administration générique celle de CE site : le
 * nom, la couleur, les langues, les contenus de l'accueil déclarés comme
 * collections, les réglages, le formulaire de contact, et les migrations
 * qui ont amené les données d'avant le socle dans sa table commune.
 */

declare(strict_types=1);

/** Les catégories du portfolio (filtres de la page Campagnes). */
const CATEGORIES_CAMPAGNE = [
    'digital' => 'Stratégie Digitale',
    'video' => 'Vidéo & Photo',
    'event' => 'Événementiel',
    'brand' => 'Identité Visuelle',
];

/** Les pictogrammes qu'on peut choisir pour un service (dessinés dans `icone()`). */
const ICONES_SERVICE = [
    'cible' => 'Cible (stratégie)', 'photos' => 'Photos (production)', 'design' => 'Crayon (graphisme)',
    'evenement' => 'Public (événementiel)', 'regie' => 'Mégaphone (publicité)', 'abonnement' => 'Étoile (offre)',
    'video' => 'Caméra (vidéo)', 'web' => 'Écran (web)', 'graphique' => 'Courbe (résultats)',
];

return [
    'nom' => 'Live Y Dream',
    'version' => '2.0.0',
    'base' => 'lyd.sqlite',
    'session' => 'lyd_session',
    'fuseau' => 'Africa/Lome',

    'marque' => [
        'logo' => '<span class="logo-lyd">LIVE<span>Y</span>DREAM</span>',
        'courriel_logo' => 'LIVE<span style="color:#C9A84C">Y</span>DREAM',
        'couleur' => '#2563EB',
        'nom_court' => 'LYD Admin',
        'css' => 'assets/css/admin-marque.css',
        'favicon' => 'assets/images/favicon.svg',
        'icone' => 'assets/images/icone-notification.png',
    ],

    'langues' => ['fr' => 'Français', 'en' => 'English'],
    'routes_en' => [
        '' => '', 'services' => 'services', 'campagnes' => 'campaigns', 'blog' => 'blog', 'a-propos' => 'about',
        'contact' => 'contact', 'mentions-legales' => 'legal-notice', 'confidentialite' => 'privacy',
    ],
    'traduire' => fn(string $s): string => t($s),

    'modules' => ['articles' => true, 'messages' => true, 'wordpress' => true, 'traduction' => true, 'push' => true],
    'wordpress' => 'https://liveydream.com/wp-json/wp/v2',
    'articles' => ['chemin' => 'blog', 'par_page' => 9, 'image_defaut' => 'assets/images/site/agency.jpg', 'auteur_defaut' => 'Équipe LYD'],

    // Le code du site, chargé après le socle.
    'fichiers' => ['app/langue.php', 'app/site.php', 'app/contenu.php', 'app/accueil.php', 'app/migrations.php'],
    'installer' => 'installer_accueil',

    'contact' => [
        'destinataire' => 'contact@liveydream.com',
        'champs' => [
            'nom' => ['libelle' => 'Nom', 'requis' => true, 'max' => 120],
            'entreprise' => ['libelle' => 'Entreprise', 'max' => 120],
            'email' => ['libelle' => 'E-mail', 'type' => 'email', 'requis' => true],
            'whatsapp' => ['libelle' => 'WhatsApp', 'type' => 'whatsapp', 'requis' => true, 'max' => 40],
            'besoin' => ['libelle' => 'Besoin', 'max' => 80, 'sujet' => true],
            'budget' => ['libelle' => 'Budget', 'max' => 80, 'liste' => true],
            'message' => ['libelle' => 'Message', 'type' => 'texte_long', 'max' => 5000],
        ],
        'erreur_requis' => 'Indiquez votre nom et votre numéro WhatsApp.',
        'erreur_limite' => 'Vous avez déjà envoyé plusieurs demandes. Pour aller plus vite, écrivez-nous sur WhatsApp.',
    ],

    /* ------------------------------------------------------------------ */
    /* Les contenus de l'accueil, du portfolio et de la page Services      */
    /* ------------------------------------------------------------------ */
    'collections' => [
        'campagnes' => [
            'titre' => 'Campagnes', 'element' => 'campagne', 'feminin' => true, 'aide_menu' => 'Le portfolio',
            'description' => 'Le portfolio de la page Campagnes. Les cinq premières apparaissent aussi sur l’accueil : les campagnes « à la une » d’abord, puis selon l’ordre ci-dessous.',
            'tri' => [['vedette', 'desc'], ['ordre', 'asc'], ['id', 'desc']],
            'visible' => ['libelle' => 'Visible sur le site', 'oui' => 'En ligne', 'non' => 'Masquée'],
            'sous_titre' => fn(array $c): string => (string) $c['client'],
            'colonnes' => [
                'Catégorie' => fn(array $c): string => e(CATEGORIES_CAMPAGNE[$c['categorie']] ?? $c['categorie']),
                'Année' => fn(array $c): string => e((string) $c['annee']),
            ],
            'pastilles' => fn(array $c): string => ((int) $c['vedette'] ? '<span class="pastille vedette">À la une</span>' : '')
                . (str_starts_with((string) $c['image'], 'http') ? '<span class="pastille nouveau" title="Image encore hébergée ailleurs">Image externe</span>' : ''),
            'vide' => 'Aucune campagne pour l’instant.',
            'champs' => [
                'titre' => ['libelle' => 'Titre', 'requis' => true, 'traduit' => true, 'placeholder' => 'Woodin Togo — Domination Digitale'],
                'client' => ['libelle' => 'Client', 'max' => 120],
                'annee' => ['libelle' => 'Année', 'max' => 20, 'placeholder' => '2024 ou 2022-2024'],
                'categorie' => ['libelle' => 'Catégorie', 'type' => 'choix', 'options' => CATEGORIES_CAMPAGNE, 'defaut' => 'digital'],
                'extrait' => ['libelle' => 'Résumé', 'type' => 'texte_long', 'max' => 300, 'lignes' => 2, 'traduit' => true],
                'description' => ['libelle' => 'Description complète', 'type' => 'texte_long', 'max' => 5000, 'lignes' => 7, 'traduit' => true,
                    'aide' => 'Affichée dans la fiche qui s’ouvre au clic sur la campagne.'],
                'resultats' => ['libelle' => 'Résultats obtenus', 'type' => 'repetition', 'lignes_max' => 4, 'section' => 'Résultats obtenus',
                    'aide' => 'Quatre chiffres au plus, chacun avec son libellé : « +340% » · « Engagement ». Le chiffre est le même dans les deux langues.',
                    'champs' => [
                        'valeur' => ['libelle' => 'Chiffre', 'max' => 40],
                        'libelle' => ['libelle' => 'Libellé', 'max' => 40, 'traduit' => true],
                    ]],
                'tags' => ['libelle' => 'Tags', 'type' => 'etiquettes', 'traduit' => true, 'section' => 'Résultats obtenus', 'placeholder' => 'Community Management, Contenu'],
                'vedette' => ['libelle' => 'À la une (accueil)', 'type' => 'case'],
                'image' => ['libelle' => 'L’image', 'type' => 'image', 'aide' => 'Format paysage, 1200 px de large au moins.'],
            ],
        ],
        'services' => [
            'titre' => 'Services', 'element' => 'service', 'aide_menu' => 'Accueil et page Services',
            'description' => 'Ce qui s’affiche dans « Ce que nous faisons » sur l’accueil, dans l’introduction et les sections détaillées de la page Services, et dans le pied de page. Dans l’ordre ci-dessous.',
            'sous_titre' => fn(array $s): string => '#' . $s['ancre'] . ' · ' . $s['numero'],
            'apercu' => fn(array $s): string => '<span class="logo-apercu" style="width:44px;height:44px">' . icone((string) $s['icone'], 22) . '</span>',
            'colonnes' => ['Page Services' => fn(array $s): string => (int) $s['detail'] ? 'Section détaillée' : '<span class="aide">Carte seule</span>'],
            'pastilles' => fn(array $s): string => (int) $s['vedette'] ? '<span class="pastille vedette">Mis en avant</span>' : '',
            'vide' => 'Aucun service.',
            'champs' => [
                'titre' => ['libelle' => 'Titre', 'requis' => true, 'max' => 120, 'traduit' => true, 'section' => 'La carte de l’accueil'],
                'resume' => ['libelle' => 'Texte de la carte', 'type' => 'texte_long', 'max' => 400, 'traduit' => true, 'section' => 'La carte de l’accueil'],
                'tags' => ['libelle' => 'Étiquettes', 'type' => 'etiquettes', 'traduit' => true, 'section' => 'La carte de l’accueil',
                    'aide' => 'Séparées par des virgules : Facebook, Instagram, TikTok.'],
                'bouton' => ['libelle' => 'Texte du bouton', 'max' => 60, 'traduit' => true, 'section' => 'La carte de l’accueil',
                    'aide' => 'Facultatif. « En savoir plus → » si vide ; sur la page Services, « Demander un devis ».'],
                'resume_court' => ['libelle' => 'Résumé dans l’introduction', 'max' => 200, 'traduit' => true, 'section' => 'La page Services'],
                'titre_detail' => ['libelle' => 'Titre de la section', 'type' => 'texte_long', 'max' => 160, 'lignes' => 2, 'traduit' => true, 'section' => 'La page Services',
                    'aide' => 'Une ligne par retour à la ligne ; <code>*mots*</code> en italique doré. Vide : le titre de la carte.'],
                'description' => ['libelle' => 'Accroche', 'type' => 'texte_long', 'max' => 800, 'traduit' => true, 'section' => 'La page Services'],
                'livrables' => ['libelle' => 'Ce qui est livré', 'type' => 'repetition', 'lignes_max' => 8, 'section' => 'La page Services',
                    'aide' => 'Les coches de la section : un intitulé et une phrase. Une ligne vide est ignorée.',
                    'champs' => [
                        'titre' => ['libelle' => 'Intitulé', 'max' => 120, 'traduit' => true],
                        'texte' => ['libelle' => 'Détail', 'max' => 300, 'traduit' => true],
                    ]],
                'prix_libelle' => ['libelle' => 'Libellé du prix', 'max' => 80, 'traduit' => true, 'section' => 'La page Services', 'placeholder' => 'À partir de'],
                'prix_valeur' => ['libelle' => 'Montant', 'max' => 80, 'traduit' => true, 'section' => 'La page Services', 'placeholder' => '75 000 FCFA/mois'],
                'etiquette' => ['libelle' => 'Étiquette sur l’image', 'max' => 60, 'traduit' => true, 'section' => 'La page Services'],
                'numero' => ['libelle' => 'Numéro affiché', 'max' => 4, 'section' => 'Repères', 'placeholder' => '01, ★…'],
                'ancre' => ['libelle' => 'Ancre', 'type' => 'slug', 'source' => 'titre', 'section' => 'Repères', 'placeholder' => 'déduite du titre',
                    'aide' => 'Le lien <code>/services#ancre</code>. La changer casse les liens déjà partagés.'],
                'icone' => ['libelle' => 'Pictogramme', 'type' => 'choix', 'options' => ICONES_SERVICE, 'defaut' => 'cible', 'section' => 'Repères'],
                'detail' => ['libelle' => 'Section détaillée sur la page Services', 'type' => 'case', 'defaut' => 1],
                'vedette' => ['libelle' => 'Carte mise en avant (fond noir)', 'type' => 'case'],
                'image' => ['libelle' => 'L’image', 'type' => 'image', 'aide' => 'Pour la section détaillée, format paysage.'],
            ],
        ],
        'temoignages' => [
            'titre' => 'Témoignages', 'element' => 'témoignage', 'aide_menu' => 'Ce que disent les clients',
            'description' => 'Les avis de clients de l’accueil, dans l’ordre ci-dessous.',
            'titre_champ' => 'entreprise',
            'sous_titre' => fn(array $t): string => tronquer((string) $t['texte'], 90),
            'colonnes' => ['Étoiles' => fn(array $t): string => str_repeat('★', (int) $t['note'])],
            'visible' => ['libelle' => 'Visible sur l’accueil'],
            'vide' => 'Aucun témoignage : la section disparaît de l’accueil.',
            'champs' => [
                'entreprise' => ['libelle' => 'Entreprise', 'requis' => true, 'max' => 120, 'placeholder' => 'Woodin Togo',
                    'aide' => 'Son initiale s’affiche dans la pastille.'],
                'note' => ['libelle' => 'Étoiles', 'type' => 'note'],
                'texte' => ['libelle' => 'Témoignage', 'type' => 'texte_long', 'requis' => true, 'max' => 1200, 'lignes' => 6, 'traduit' => true],
                'auteur' => ['libelle' => 'Fonction de la personne', 'max' => 120, 'traduit' => true, 'placeholder' => 'Directeur Marketing'],
            ],
        ],
        'clients' => [
            'titre' => 'Logos clients', 'element' => 'client', 'aide_menu' => 'Ils nous font confiance',
            'description' => 'Les clients de la bande de logos, sur l’accueil. Sans logo, le nom s’affiche en lettres capitales — comme dans la maquette. Les logos SVG ou PNG sur fond transparent rendent le mieux.',
            'apercu' => fn(array $c): string => '<span class="logo-apercu large">' . ($c['logo'] !== '' ? '<img src="' . e(image_url((string) $c['logo'])) . '" alt="">' : e((string) $c['nom'])) . '</span>',
            'sous_titre' => fn(array $c): string => (string) $c['lien'],
            'pastilles' => fn(array $c): string => $c['logo'] === '' ? '<span class="pastille brouillon">Nom seul</span>' : '',
            'visible' => ['libelle' => 'Visible sur l’accueil'],
            'vide' => 'Aucun client : la bande de logos disparaît de l’accueil.',
            'champs' => [
                'nom' => ['libelle' => 'Nom', 'requis' => true, 'max' => 80, 'placeholder' => 'ECOBANK',
                    'aide' => 'Il s’affiche quand il n’y a pas de logo, et sert de texte alternatif sinon.'],
                'lien' => ['libelle' => 'Lien (facultatif)', 'type' => 'lien', 'aide' => 'Le logo devient cliquable et ouvre le site du client.'],
                'logo' => ['libelle' => 'Logo', 'type' => 'image', 'aide' => 'Téléversez-le directement depuis la fenêtre de choix.'],
            ],
        ],
    ],

    /* ------------------------------------------------------------------ */
    /* Les réglages                                                        */
    /* ------------------------------------------------------------------ */
    'admin' => ['aide_reglages' => 'Coordonnées, accueil, SEO, e-mails'],
    'reglages' => [
        'agence' => [
            'titre' => 'L’agence',
            'description' => 'Ce qui s’affiche sur toutes les pages du site : coordonnées, réseaux, textes de l’accueil, référencement.',
            'sections' => [
                ['titre' => 'Coordonnées', 'aide' => 'Utilisées dans le pied de page, la page Contact et les données lues par Google.', 'champs' => [
                    'nom_site' => ['libelle' => 'Nom de l’agence'],
                    'telephone' => ['libelle' => 'Téléphone principal', 'defaut' => '+228 70 08 77 77'],
                    'telephone2' => ['libelle' => 'Second téléphone', 'defaut' => '+228 90 37 45 60'],
                    'whatsapp' => ['libelle' => 'Numéro WhatsApp', 'defaut' => '22870087777',
                        'aide' => 'Indicatif compris, chiffres seuls : 22870087777. Il alimente tous les boutons WhatsApp du site.'],
                    'email' => ['libelle' => 'E-mail principal', 'type' => 'email', 'defaut' => 'contact@liveydream.com'],
                    'email2' => ['libelle' => 'Second e-mail', 'type' => 'email', 'defaut' => 'customercare@liveydream.com'],
                    'adresse' => ['libelle' => 'Adresse', 'defaut' => '177 Rue HDN, Hédzranawoé'],
                    'adresse2' => ['libelle' => 'Ville, repère', 'traduit' => true, 'defaut' => 'Lomé, Togo (non loin de STEA)', 'defaut_en' => 'Lomé, Togo (near STEA)'],
                ]],
                ['titre' => 'Horaires', 'champs' => [
                    'horaires_semaine' => ['libelle' => 'Lundi — Vendredi', 'traduit' => true, 'defaut' => '8h30 — 18h00', 'defaut_en' => '8:30 am — 6:00 pm'],
                    'horaires_samedi' => ['libelle' => 'Samedi', 'traduit' => true, 'defaut' => '9h00 — 14h00', 'defaut_en' => '9:00 am — 2:00 pm'],
                    'horaires_dimanche' => ['libelle' => 'Dimanche', 'traduit' => true, 'defaut' => 'Fermé', 'defaut_en' => 'Closed'],
                ]],
                ['titre' => 'Réseaux sociaux', 'aide' => 'Vide : l’icône disparaît du pied de page.', 'champs' => [
                    'facebook' => ['libelle' => 'Facebook', 'type' => 'lien', 'defaut' => 'https://www.facebook.com/228LiveYDream'],
                    'instagram' => ['libelle' => 'Instagram', 'type' => 'lien', 'defaut' => 'https://instagram.com/liveydream'],
                    'linkedin' => ['libelle' => 'LinkedIn', 'type' => 'lien', 'defaut' => 'https://linkedin.com/company/liveydream'],
                    'tiktok' => ['libelle' => 'TikTok', 'type' => 'lien', 'defaut' => 'https://tiktok.com/@liveydream'],
                ]],
            ],
        ],
        'accueil' => [
            'titre' => 'Accueil',
            'description' => 'Le grand titre de l’accueil et les chiffres clés.',
            'sections' => [
                ['titre' => 'Le grand titre', 'aide' => 'Une ligne par retour à la ligne. <code>*mots*</code> s’affichent en couleur, <code>_mots_</code> en contour, <code>**mots**</code> en gras.', 'champs' => [
                    'hero_titre' => ['libelle' => 'Titre', 'type' => 'texte_long', 'traduit' => true, 'lignes' => 2,
                        'defaut' => 'VOS CLIENTS NE PEUVENT PAS VOUS IGNORER', 'defaut_en' => 'YOUR CLIENTS CAN\'T IGNORE YOU'],
                    'hero_sous_titre' => ['libelle' => 'Sous-titre', 'type' => 'texte_long', 'traduit' => true,
                        'defaut' => 'En Afrique, la visibilité est le vrai pouvoir. Nous transformons votre marque en une force incontournable.',
                        'defaut_en' => 'In Africa, visibility is real power. We turn your brand into a force that can\'t be ignored.'],
                ]],
                ['titre' => 'Les chiffres clés', 'aide' => 'Repris sur l’accueil et la page À propos. Écrivez « 200+ » : le nombre s’anime, le signe reste. Le chiffre est le même dans les deux langues, seul le libellé se traduit.', 'champs' => [
                    'stat1' => ['libelle' => 'Chiffre 1', 'defaut' => '10+'],
                    'stat1_libelle' => ['libelle' => 'Libellé 1', 'traduit' => true, 'defaut' => "Ans d'expertise", 'defaut_en' => 'Years of expertise'],
                    'stat2' => ['libelle' => 'Chiffre 2', 'defaut' => '200+'],
                    'stat2_libelle' => ['libelle' => 'Libellé 2', 'traduit' => true, 'defaut' => 'Marques accompagnées', 'defaut_en' => 'Brands supported'],
                    'stat3' => ['libelle' => 'Chiffre 3', 'defaut' => '4'],
                    'stat3_libelle' => ['libelle' => 'Libellé 3', 'traduit' => true, 'defaut' => 'Pays présents', 'defaut_en' => 'Countries'],
                    'stat4' => ['libelle' => 'Chiffre 4', 'defaut' => '15+'],
                    'stat4_libelle' => ['libelle' => 'Libellé 4', 'traduit' => true, 'defaut' => "Experts dans l'équipe", 'defaut_en' => 'Experts on the team'],
                ]],
            ],
        ],
        'referencement' => [
            'titre' => 'Référencement',
            'description' => 'Ce que Google lit du site.',
            'sections' => [
                ['titre' => 'Adresse du site', 'champs' => [
                    'site_url' => ['libelle' => 'Adresse publique', 'type' => 'lien',
                        'aide' => 'Par exemple <code>https://liveydream.com</code>. Elle sert aux liens de partage, au plan du site et à Google. Vide : déduite de la visite.'],
                ]],
                ['titre' => 'Description', 'champs' => [
                    'meta_description' => ['libelle' => 'Description par défaut', 'type' => 'texte_long', 'traduit' => true, 'max' => 400,
                        'aide' => 'Ce que Google affiche sous le nom du site. 150 caractères environ.',
                        'defaut' => "Depuis 2013, Live Y Dream est l'agence de communication de référence à Lomé, Togo. Marketing digital, création graphique, production vidéo, événementiel, community management.",
                        'defaut_en' => 'Since 2013, Live Y Dream has been the leading communication agency in Lomé, Togo. Digital marketing, graphic design, video production, events, community management.'],
                    'verif_google' => ['libelle' => 'Code de vérification Google Search Console', 'aide' => 'Le contenu de la balise <code>google-site-verification</code>.'],
                ]],
            ],
        ],
        'legal' => [
            'titre' => 'Pages légales',
            'description' => 'Vide : un texte type est affiché, construit avec vos coordonnées. Indiquez au moins l’hébergeur et le responsable de publication.',
            'sections' => [
                ['titre' => 'Mentions légales', 'aide' => 'HTML simple accepté (&lt;h2&gt;, &lt;p&gt;, &lt;a&gt;…).', 'champs' => [
                    'mentions_legales' => ['libelle' => 'Mentions légales', 'type' => 'html', 'traduit' => true, 'lignes' => 12],
                ]],
                ['titre' => 'Politique de confidentialité', 'champs' => [
                    'confidentialite' => ['libelle' => 'Politique de confidentialité', 'type' => 'html', 'traduit' => true, 'lignes' => 12],
                ]],
            ],
        ],
    ],

    /* ------------------------------------------------------------------ */
    /* Les données d'avant le socle                                        */
    /* ------------------------------------------------------------------ */
    'migrations' => [
        'lyd-contact-copies' => 'migration_lyd_contact_copies',
        'lyd-messages' => 'migration_lyd_messages',
        'lyd-elements' => 'migration_lyd_elements',
    ],
];
