<?php
/**
 * La définition du site exemple : le seul fichier à écrire pour brancher
 * le socle d'administration sur un nouveau site.
 *
 * Copiez-le, changez le nom, la couleur, les collections et les réglages :
 * l'administration se dessine d'après ce fichier. Toutes les clés sont
 * décrites dans le README du socle.
 */

declare(strict_types=1);

return [
    // Le nom par défaut du site (modifiable ensuite dans Réglages), et le fichier de la base.
    'nom' => 'Atelier Exemple',
    'base' => 'site.sqlite',
    'session' => 'exemple_session',
    'fuseau' => 'Africa/Lome',

    // L'apparence de l'administration : un logo (HTML ou rien : le nom du site), une couleur.
    'marque' => [
        'couleur' => '#0D9488',
        // 'logo' => '<img src="/assets/logo.svg" alt="Atelier Exemple">',
        // 'icone' => 'assets/icone-192.png',   // notifications et écran d'accueil du téléphone
    ],

    // Une seule langue ici. Pour un site bilingue : ['fr' => 'Français', 'en' => 'English'] et `routes_en`.
    'langues' => ['fr' => 'Français'],

    // Les modules du socle. `wordpress` et `traduction` (DeepL) n'ont de sens qu'avec un blog.
    'modules' => ['articles' => true, 'messages' => true, 'wordpress' => false, 'traduction' => false, 'push' => true],
    'articles' => ['chemin' => 'blog', 'par_page' => 9],

    'contact' => [
        // Chaque demande part TOUJOURS ici (les réglages peuvent ajouter des copies).
        'destinataire' => 'contact@exemple.com',
        'champs' => [
            'nom' => ['libelle' => 'Nom', 'requis' => true],
            'email' => ['libelle' => 'E-mail', 'type' => 'email', 'requis' => true],
            'telephone' => ['libelle' => 'Téléphone', 'type' => 'tel'],
            'sujet' => ['libelle' => 'Sujet', 'sujet' => true],
            'message' => ['libelle' => 'Message', 'type' => 'texte_long', 'requis' => true],
        ],
    ],

    // Les contenus du site, déclarés : le socle en tire la liste, la fiche, la recherche.
    'collections' => [
        'realisations' => [
            'titre' => 'Réalisations', 'element' => 'réalisation', 'feminin' => true, 'aide_menu' => 'Le portfolio',
            'description' => 'Les projets montrés sur l’accueil, dans l’ordre ci-dessous.',
            'sous_titre' => fn(array $r): string => (string) $r['client'],
            'pastilles' => fn(array $r): string => (int) $r['vedette'] ? '<span class="pastille vedette">À la une</span>' : '',
            'tri' => [['vedette', 'desc'], ['ordre', 'asc']],
            'champs' => [
                'titre' => ['libelle' => 'Titre', 'requis' => true],
                'client' => ['libelle' => 'Client'],
                'annee' => ['libelle' => 'Année', 'type' => 'nombre', 'defaut' => (int) date('Y')],
                'resume' => ['libelle' => 'Résumé', 'type' => 'texte_long', 'max' => 400],
                'chiffres' => ['libelle' => 'Chiffres clés', 'type' => 'repetition', 'lignes_max' => 3, 'section' => 'Les résultats',
                    'champs' => ['valeur' => ['libelle' => 'Chiffre'], 'libelle' => ['libelle' => 'Libellé']]],
                'lien' => ['libelle' => 'Lien vers le projet', 'type' => 'lien', 'section' => 'Les résultats'],
                'vedette' => ['libelle' => 'À la une', 'type' => 'case'],
                'image' => ['libelle' => 'L’image', 'type' => 'image', 'aide' => 'Format paysage.'],
            ],
        ],
        'faq' => [
            'titre' => 'Questions fréquentes', 'element' => 'question', 'feminin' => true, 'aide_menu' => 'La page Contact',
            'champs' => [
                'question' => ['libelle' => 'Question', 'requis' => true],
                'reponse' => ['libelle' => 'Réponse', 'type' => 'texte_long', 'requis' => true, 'lignes' => 4],
            ],
        ],
    ],

    // Les réglages, par onglets. Le socle ajoute de lui-même l'onglet « E-mails ».
    'reglages' => [
        'site' => [
            'titre' => 'Le site',
            'sections' => [
                ['titre' => 'Identité', 'champs' => [
                    'nom_site' => ['libelle' => 'Nom du site'],
                    'slogan' => ['libelle' => 'Slogan', 'defaut' => 'Des idées simples, bien faites.'],
                    'intro' => ['libelle' => 'Texte de l’accueil', 'type' => 'texte_long',
                        'defaut' => 'Nous concevons des sites, des marques et des campagnes pour les entreprises qui veulent être vues.'],
                ]],
                ['titre' => 'Coordonnées', 'champs' => [
                    'email' => ['libelle' => 'E-mail affiché', 'type' => 'email', 'defaut' => 'contact@exemple.com'],
                    'telephone' => ['libelle' => 'Téléphone', 'defaut' => '+228 00 00 00 00'],
                    'adresse' => ['libelle' => 'Adresse', 'defaut' => 'Lomé, Togo'],
                ]],
                ['titre' => 'Référencement', 'champs' => [
                    'site_url' => ['libelle' => 'Adresse publique', 'type' => 'lien', 'aide' => 'Vide : déduite de la visite.'],
                    'meta_description' => ['libelle' => 'Description pour Google', 'type' => 'texte_long', 'max' => 300],
                ]],
            ],
        ],
    ],

    // Le contenu de départ, posé une seule fois à la première visite.
    'installer' => function (): void {
        if (reglage('exemple_installe') === '1') {
            return;
        }
        if (elements_compter('realisations') === 0) {
            element_creer('realisations', ['titre' => 'Refonte d’une marque de jus', 'client' => 'Fruits du Sud', 'annee' => 2025,
                'resume' => 'Un nouveau logo, des étiquettes, et une campagne de lancement sur les réseaux.', 'vedette' => 1, 'image' => '', 'lien' => '',
                'chiffres' => [['valeur' => '+60 %', 'libelle' => 'de ventes'], ['valeur' => '3', 'libelle' => 'nouveaux marchés']]]);
            element_creer('realisations', ['titre' => 'Site d’une clinique', 'client' => 'Clinique Horizon', 'annee' => 2024,
                'resume' => 'Prise de rendez-vous en ligne et pages de chaque spécialité.', 'vedette' => 0, 'image' => '', 'lien' => '', 'chiffres' => []]);
        }
        if (elements_compter('faq') === 0) {
            element_creer('faq', ['question' => 'Quels sont vos délais ?', 'reponse' => 'Deux à six semaines selon le projet.']);
            element_creer('faq', ['question' => 'Travaillez-vous à distance ?', 'reponse' => 'Oui, avec des points réguliers en visio.']);
        }
        reglages_enregistrer(['exemple_installe' => '1']);
    },
];
