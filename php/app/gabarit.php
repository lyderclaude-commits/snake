<?php
/**
 * Le contrat de données, et les règles qui le rendent exécutable.
 *
 * Port fidèle de src/core/template.schema.ts et src/server/buildTemplate.ts.
 * Le gabarit produit ici est lu TEL QUEL par le renderer JavaScript côté
 * navigateur : c'est le même contrat des deux côtés, et c'est ce qui garantit
 * que l'aperçu et l'export coïncident.
 */

declare(strict_types=1);

const WAKABI_SIGNATURE = 'LE GUIDE DES BONS PLANS';

/**
 * Le garde-fou : un décor de partenaire ne peut renvoyer que vers un domaine
 * Wakabi. Sans lui, un décor deviendrait une passerelle vers n'importe quoi.
 */
const WAKABI_DOMAINES = ['wakabileguide.com', 'studio.wakabileguide.com'];

const STATUTS = ['brouillon', 'en_relecture', 'corrections', 'refuse', 'publie', 'expire', 'archive'];

/** Dispositions proposées au partenaire. Il ne place pas ses calques à la main. */
function dispositions(): array
{
    return [
        ['id' => 'bandeau',   'nom' => 'Bandeau bas',     'aide' => 'Carré · texte sur une bande en bas', 'ratio' => '1:1'],
        ['id' => 'angle',     'nom' => 'Coin & voile',    'aide' => 'Carré · texte en bas à gauche',      'ratio' => '1:1'],
        ['id' => 'story',     'nom' => 'Story verticale', 'aide' => '9:16 · pour le statut WhatsApp',     'ratio' => '9:16'],
        ['id' => 'instagram', 'nom' => 'Post Instagram',  'aide' => '4:5 · le format qui prend le plus de place dans le fil', 'ratio' => '4:5'],
        ['id' => 'facebook',  'nom' => 'Post Facebook',   'aide' => '1:1 · carré, jamais recadré par le fil',                 'ratio' => '1:1'],
        ['id' => 'tiktok',    'nom' => 'TikTok & Reels',  'aide' => '9:16 · tout remonté au-dessus des boutons de l’appli',   'ratio' => '9:16'],
        ['id' => 'vierge',    'nom' => 'Page blanche',    'aide' => 'tout au choix : format, fond, zone photo, formes',       'ratio' => 'libre'],
    ];
}

/**
 * Transitions autorisées, par acteur.
 *
 * C'est ici que la modération devient une règle et non une consigne : un
 * partenaire ne peut ni publier ni s'auto-approuver, quoi qu'il envoie.
 */
function transition_permise(string $de, string $vers, string $acteur): bool
{
    $regles = [
        'partenaire' => [
            'brouillon'   => ['en_relecture'],
            'corrections' => ['en_relecture'],
            'refuse'      => ['brouillon'],
        ],
        'equipe' => [
            'brouillon'    => ['en_relecture', 'publie', 'archive'],
            'en_relecture' => ['publie', 'corrections', 'refuse'],
            'corrections'  => ['publie', 'refuse'],
            'refuse'       => ['brouillon', 'archive'],
            'publie'       => ['expire', 'archive'],
            'expire'       => ['publie', 'archive'],
        ],
    ];
    return in_array($vers, $regles[$acteur][$de] ?? [], true);
}

function statut_libelle(string $s): string
{
    return [
        'brouillon' => 'Brouillon',
        'en_relecture' => 'En relecture',
        'corrections' => 'Corrections demandées',
        'refuse' => 'Refusé',
        'publie' => 'Publié',
        'expire' => 'Expiré',
        'archive' => 'Archivé',
    ][$s] ?? $s;
}

/* ---------------- dimensions et positions ---------------- */

/** Les quatre formats qu'une page blanche peut prendre. */
const FORMATS = [
    '1:1'  => 'Carré · 1080 × 1080',
    '4:5'  => 'Portrait · 1080 × 1350',
    '9:16' => 'Vertical plein écran · 1080 × 1920',
    '16:9' => 'Paysage · 1920 × 1080',
];

function format_canevas(string $format): array
{
    return match ($format) {
        '4:5'  => ['w' => 1080, 'h' => 1350, 'ratio' => '4:5'],
        '9:16' => ['w' => 1080, 'h' => 1920, 'ratio' => '9:16'],
        '16:9' => ['w' => 1920, 'h' => 1080, 'ratio' => '16:9'],
        default => ['w' => 1080, 'h' => 1080, 'ratio' => '1:1'],
    };
}

/**
 * Le canevas d'une disposition.
 *
 * Chaque gabarit nommé a son format d'origine — c'est ce qui le rend prêt à
 * publier. Mais un format explicite l'emporte toujours, parce qu'un CADRE
 * impose le sien : étirer une affiche 4:5 sur un canevas carré l'aplatit
 * d'un quart, et aucun réglage de fenêtre photo ne rattrape ça.
 *
 * Appelée sans format, la fonction rend le canevas d'origine : c'est ce que
 * veulent les appelants qui cherchent « le format de ce gabarit ».
 */
function canevas(string $disposition, string $format = ''): array
{
    if ($disposition === 'vierge' || isset(FORMATS[$format])) {
        return format_canevas($format);
    }
    return match ($disposition) {
        'story', 'tiktok' => ['w' => 1080, 'h' => 1920, 'ratio' => '9:16'],
        'instagram'       => ['w' => 1080, 'h' => 1350, 'ratio' => '4:5'],
        default           => ['w' => 1080, 'h' => 1080, 'ratio' => '1:1'],
    };
}

/** Le format d'origine d'une disposition, sous forme de clé de FORMATS. */
function format_natif(string $disposition): string
{
    return $disposition === 'vierge' ? '1:1' : canevas($disposition)['ratio'];
}

/**
 * L'apparence de départ d'une disposition.
 *
 * Ce sont des VALEURS DE DÉPART, pas des positions figées : le formulaire les
 * expose toutes, et l'équipe comme l'organisateur peuvent les déplacer. Ce
 * qu'ils ne peuvent pas faire, c'est retirer le QR, le filigrane ou
 * l'emplacement photo — `valider_gabarit()` s'y oppose.
 *
 * Les valeurs sont calées pour ne heurter NI le filigrane NI le QR avec sa
 * zone de silence, et pour les trois formats de réseau, pour rester hors des
 * zones que l'application recouvre de ses propres boutons.
 *
 * En bas d'un décor, la bande réellement libre va de x = 0,24 (fin du QR et
 * de sa zone de silence) à x = 0,74 (début du filigrane). Un bloc de texte
 * qui en sort passe SOUS l'un des deux, et le pré-vol le refuse.
 */
function apparence_par_defaut(string $disposition, string $format = ''): array
{
    /**
     * Le format d'origine de la disposition, sauf demande explicite.
     *
     * Écrire `'format' => '1:1'` ici revenait à rendre tous les gabarits
     * carrés dès que le canevas suivrait le format : story et TikTok se
     * seraient écrasés de moitié. Le format d'origine est la seule valeur
     * par défaut qui ne change rien pour personne.
     */
    $natif = format_natif($disposition);
    $effectif = isset(FORMATS[$format]) ? $format : $natif;

    $commun = [
        'texte_couleur' => 'brand.paper',
        'texte_align' => 'left',
        'qr_position' => 'bottom-left',
        'qr_taille' => 0.16,
        'filigrane_position' => 'bottom-right',
        'format' => $effectif,
        'fond' => 'brand.ink',
    ];

    /**
     * La fenêtre photo part de l'OUVERTURE du cadre de la disposition.
     *
     * À défaut, c'est le canevas entier — et là, la photo est cadrée sur
     * toute la surface alors que le dessin n'en laisse voir qu'une bande :
     * le visage de l'invité tombe où il veut. C'est le défaut qu'on corrige
     * ici, pour tout le monde d'un coup, sans que personne ait à toucher un
     * curseur. Les valeurs viennent de `public/cadres/fenetres.json`, relevé
     * sur les pixels des cadres au moment où ils sont fabriqués.
     *
     * `+=` et non `array_merge` : ici c'est bien la GAUCHE qu'on veut voir
     * l'emporter — l'ouverture relevée d'abord, le canevas entier seulement
     * s'il n'y a rien à relever.
     */
    $commun += fenetres_relevees()[cadre_prefere($disposition)] ?? [];
    $commun += [
        'photo_x' => 0.0, 'photo_y' => 0.0, 'photo_w' => 1.0, 'photo_h' => 1.0,
        'photo_forme' => 'rect',
    ];

    // `array_merge` et non `+` : avec l'union, ce sont les valeurs de GAUCHE
    // qui l'emportent, et le QR de TikTok serait retombé en bas à gauche,
    // c'est-à-dire sous la légende de l'application.
    $propre = match ($disposition) {
        'angle' => [
            'bloc_x' => 0.25, 'bloc_y' => 0.79, 'bloc_w' => 0.48,
            'accroche_taille' => 0.05, 'champ_taille' => 0.026,
        ],
        'story' => [
            'bloc_x' => 0.25, 'bloc_y' => 0.826, 'bloc_w' => 0.48,
            'accroche_taille' => 0.032, 'champ_taille' => 0.02,
        ],
        // Instagram : rien ne recouvre l'image dans le fil, le texte peut
        // descendre bas et rester large.
        'instagram' => [
            'bloc_x' => 0.25, 'bloc_y' => 0.80, 'bloc_w' => 0.49,
            'accroche_taille' => 0.05, 'champ_taille' => 0.027,
        ],
        // Facebook recadre les aperçus au carré : tout ce qui compte reste
        // dans le carré, et le texte prend toute la largeur utile.
        'facebook' => [
            'bloc_x' => 0.25, 'bloc_y' => 0.775, 'bloc_w' => 0.48,
            'accroche_taille' => 0.055, 'champ_taille' => 0.029,
        ],
        // TikTok pose sa légende sur le cinquième du bas et ses boutons sur
        // le bord droit : le texte remonte au-dessus, le QR passe en haut à
        // gauche, seul coin que l'application laisse tranquille.
        'tiktok' => [
            'bloc_x' => 0.09, 'bloc_y' => 0.655, 'bloc_w' => 0.62,
            'accroche_taille' => 0.036, 'champ_taille' => 0.021,
            'qr_position' => 'top-left', 'qr_taille' => 0.17,
            'filigrane_position' => 'bottom-center',
        ],
        /**
         * La page blanche : aucun cadre, une fenêtre photo en haut, le texte
         * dessous, et tout le bas laissé au QR et au filigrane.
         *
         * Les hauteurs sont CALCULÉES à partir du format choisi, elles ne
         * peuvent pas être écrites une fois pour toutes : le QR est dimensionné
         * sur la largeur, donc en paysage il mange un tiers de la hauteur là
         * où il n'en prend qu'un sixième en vertical. Des valeurs figées
         * posaient le texte sous le QR dès qu'on passait en 16:9.
         */
        'vierge' => page_blanche($effectif),
        default => [
            'bloc_x' => 0.25, 'bloc_y' => 0.795, 'bloc_w' => 0.48,
            'accroche_taille' => 0.058, 'champ_taille' => 0.03,
        ],
    };

    /**
     * Format changé : les repères d'origine ne valent plus.
     *
     * Les hauteurs ci-dessus sont calées au millième pour le format de leur
     * disposition. Sur un autre, elles posent le texte sous le QR — qui est
     * dimensionné sur la LARGEUR et occupe donc une part de hauteur très
     * différente selon le format. On les recalcule alors comme pour une page
     * blanche, en gardant ce qui tient à la disposition et non au format :
     * la place du QR et celle du filigrane.
     */
    if ($effectif !== $natif && $disposition !== 'vierge') {
        $propre = array_merge($propre, page_blanche($effectif));
    }

    return array_merge($commun, $propre);
}

/**
 * Retrouve la disposition d'un décor enregistré.
 *
 * Les décors créés avant que `layout` n'existe ne le portent pas : on les
 * reconnaît alors au format et à la mise en page. Approximatif par nature,
 * mais seulement pour ces décors-là, et une fois rouverts puis enregistrés
 * ils portent la vraie valeur.
 */
function disposition_devinee(array $g): string
{
    $connues = array_column(dispositions(), 'id');
    $enregistree = (string) ($g['layout'] ?? '');
    if (in_array($enregistree, $connues, true)) {
        return $enregistree;
    }

    $ratio = (string) ($g['canvas']['ratio'] ?? '1:1');
    $qr = (string) ($g['qr']['position'] ?? 'bottom-left');
    $claim = null;
    foreach ($g['layers'] ?? [] as $l) {
        if (($l['id'] ?? '') === 'claim') {
            $claim = $l;
        }
    }

    if ($ratio === '4:5') {
        return 'instagram';
    }
    if ($ratio === '9:16') {
        return $qr === 'top-left' ? 'tiktok' : 'story';
    }
    // Un carré sans `layout` date forcément d'avant les formats de réseau :
    // il ne peut être que l'un des deux carrés d'origine. « Bandeau » et
    // « Post Facebook » ont la même géométrie — les distinguer ici reviendrait
    // à tirer à pile ou face.
    return ($claim['uppercase'] ?? false) ? 'bandeau' : 'angle';
}

/**
 * Les cadres livrés avec l'application, avec leur format.
 *
 * Ils permettent d'essayer un gabarit sans passer par un graphiste : le
 * format annoncé vient de l'image elle-même, donc un cadre déposé plus tard
 * dans le dossier apparaît sans qu'on touche à ce code.
 */
function cadres_fournis(): array
{
    static $liste = null;
    if ($liste !== null) {
        return $liste;
    }

    $noms = [
        'jy-serai.png' => 'J’y serai',
        'bon-plan.png' => 'Bon plan',
        'story.png' => 'Story',
        'instagram.png' => 'Instagram',
        'facebook.png' => 'Facebook',
        'tiktok.png' => 'TikTok',
        '228-playground.png' => '228 Basket Playground',
        '228-playground-story.png' => '228 Basket Playground · story',
    ];

    $fenetres = fenetres_relevees();

    $liste = [];
    foreach (glob(RACINE . '/public/cadres/*.{png,webp}', GLOB_BRACE) ?: [] as $chemin) {
        $nom = basename($chemin);
        $taille = @getimagesize($chemin);
        if (!$taille) {
            continue;
        }
        $liste[$nom] = [
            'nom' => $noms[$nom] ?? pathinfo($nom, PATHINFO_FILENAME),
            'ratio' => ratio_lisible((int) $taille[0], (int) $taille[1]),
            'w' => (int) $taille[0],
            'h' => (int) $taille[1],
            'fenetre' => $fenetres[$nom] ?? null,
        ];
    }
    return $liste;
}

/**
 * L'ouverture de chaque cadre fourni, relevée à la fabrication.
 *
 * `public/cadres/fenetres.json` est écrit par `npm run frames`, en même
 * temps que les PNG et à partir des mêmes pixels : les deux ne peuvent pas
 * se contredire. Sans ce relevé, un décor bâti sur un cadre fourni cadre la
 * photo de l'invité sur le canevas ENTIER alors que le dessin ne laisse
 * voir qu'une bande — et le visage tombe où il veut.
 *
 * Fichier absent ou illisible : on retombe sur le canevas entier, c'est-à-
 * dire le comportement d'avant. Un manifeste manquant ne casse rien.
 */
function fenetres_relevees(): array
{
    static $lu = null;
    if ($lu !== null) {
        return $lu;
    }
    $lu = [];
    $chemin = RACINE . '/public/cadres/fenetres.json';
    $brut = is_readable($chemin) ? @file_get_contents($chemin) : false;
    $donnees = $brut === false ? null : json_decode($brut, true);
    if (!is_array($donnees)) {
        return $lu;
    }
    foreach ($donnees as $nom => $f) {
        if (!is_array($f) || !isset($f['x'], $f['y'], $f['w'], $f['h'])) {
            continue;
        }
        $lu[basename((string) $nom)] = [
            'photo_x' => max(0.0, min(0.9, (float) $f['x'])),
            'photo_y' => max(0.0, min(0.9, (float) $f['y'])),
            'photo_w' => max(0.08, min(1.0, (float) $f['w'])),
            'photo_h' => max(0.08, min(1.0, (float) $f['h'])),
            'photo_forme' => isset(APPARENCE_FORMES[$f['forme'] ?? '']) ? (string) $f['forme'] : 'rect',
        ];
    }
    return $lu;
}

/** Le cadre fourni qui va avec une disposition, quand on n'en téléverse pas. */
function cadre_prefere(string $disposition): string
{
    return ['instagram' => 'instagram.png', 'facebook' => 'facebook.png',
            'tiktok' => 'tiktok.png', 'story' => 'story.png'][$disposition] ?? 'jy-serai.png';
}

/**
 * Un cadre livré au bon format, pour l'aperçu d'un décor sans cadre.
 *
 * Sans lui, régler l'apparence avant d'avoir dessiné son cadre revient à
 * placer du texte sur du vide : on ne voit ni la bande, ni la zone laissée
 * libre pour la photo.
 */
function cadre_du_format(string $disposition): string
{
    $voulu = canevas($disposition)['ratio'];
    $prefere = cadre_prefere($disposition);

    $fournis = cadres_fournis();
    if (isset($fournis[$prefere])) {
        return url('public/cadres/' . $prefere);
    }
    foreach ($fournis as $nom => $c) {
        if ($c['ratio'] === $voulu) {
            return url('public/cadres/' . $nom);
        }
    }
    return '';
}

/** « 1080×1350 » devient « 4:5 » — sinon personne ne compare de tête. */
function ratio_lisible(int $w, int $h): string
{
    $r = $h > 0 ? $w / $h : 1;
    foreach (['1:1' => 1, '4:5' => 0.8, '9:16' => 0.5625, '16:9' => 1.7778] as $nom => $valeur) {
        if (abs($r - $valeur) < 0.02) {
            return $nom;
        }
    }
    return $w . '×' . $h;
}

/**
 * Les repères d'une page blanche, déduits de son format.
 *
 * On part du bas : le QR et sa marge occupent une hauteur qui dépend du
 * format, le bloc de texte se cale juste au-dessus, et la fenêtre photo
 * prend tout ce qui reste en haut.
 */
function page_blanche(string $format): array
{
    $c = format_canevas($format);
    $rapport = $c['w'] / $c['h'];

    // En paysage, un QR à 16 % de la largeur prendrait un tiers de la
    // hauteur : on le réduit plutôt que de sacrifier la photo.
    $taille_qr = $rapport > 1.3 ? 0.12 : 0.16;

    // Hauteurs normalisées : le renderer dimensionne le QR sur la LARGEUR.
    $hauteur_qr = $taille_qr * 1.16 * $rapport;
    $marge = 0.04 * min(1.0, $rapport);
    $plancher = 1 - $hauteur_qr - $marge - 0.02;

    $accroche = 0.06;
    $champ = 0.03;
    $hauteur_bloc = $accroche * 1.55 + 0.004 + $champ * 1.83;

    $bloc_y = max(0.2, round($plancher - $hauteur_bloc, 3));
    $photo_y = 0.05;
    $photo_h = max(0.25, round($bloc_y - 0.03 - $photo_y, 3));

    return [
        'bloc_x' => 0.08, 'bloc_y' => $bloc_y, 'bloc_w' => 0.84,
        'accroche_taille' => $accroche, 'champ_taille' => $champ,
        'photo_x' => 0.06, 'photo_y' => $photo_y, 'photo_w' => 0.88, 'photo_h' => $photo_h,
        'photo_forme' => 'arrondi',
        'qr_taille' => $taille_qr,
        'format' => $format,
    ];
}

/** Les valeurs qu'un formulaire a le droit de proposer. */
const APPARENCE_COULEURS = [
    'brand.paper' => 'Blanc',
    'brand.ink' => 'Encre (sur fond clair)',
    'brand.primary' => 'Bleu Wakabi',
    'brand.accent' => 'Orange',
    'brand.secondary' => 'Teal',
    'brand.kori' => 'Or (Koris)',
];
const APPARENCE_ALIGNEMENTS = ['left' => 'À gauche', 'center' => 'Centré', 'right' => 'À droite'];
const APPARENCE_QR = ['bottom-left' => 'En bas à gauche', 'top-left' => 'En haut à gauche', 'top-right' => 'En haut à droite'];
const APPARENCE_FILIGRANE = ['bottom-right' => 'En bas à droite', 'bottom-left' => 'En bas à gauche', 'bottom-center' => 'En bas au centre'];
const APPARENCE_FORMES = ['rect' => 'Rectangle', 'arrondi' => 'Coins arrondis', 'cercle' => 'Cercle'];

/**
 * Nettoie une apparence venue d'un formulaire.
 *
 * Tout ce qui n'est pas reconnu retombe sur la valeur de départ de la
 * disposition : un champ absent, mal orthographié ou hors bornes ne doit
 * jamais produire un décor cassé, seulement un décor par défaut.
 */
function apparence_propre(string $disposition, array $saisie): array
{
    // Le format d'abord : c'est de lui que dépendent toutes les hauteurs de
    // départ d'une page blanche.
    $format = (string) ($saisie['format'] ?? '');
    $d = apparence_par_defaut($disposition, isset(FORMATS[$format]) ? $format : '');

    $borne = function (string $cle, float $min, float $max) use ($saisie, $d): float {
        if (!isset($saisie[$cle]) || !is_numeric($saisie[$cle])) {
            return (float) $d[$cle];
        }
        return max($min, min($max, (float) $saisie[$cle]));
    };
    $parmi = function (string $cle, array $valeurs) use ($saisie, $d): string {
        $v = (string) ($saisie[$cle] ?? '');
        return isset($valeurs[$v]) ? $v : (string) $d[$cle];
    };

    return [
        'texte_couleur' => $parmi('texte_couleur', APPARENCE_COULEURS),
        'texte_align' => $parmi('texte_align', APPARENCE_ALIGNEMENTS),
        'format' => $parmi('format', FORMATS),
        'fond' => $parmi('fond', APPARENCE_COULEURS),
        // Bornes larges : l'ouverture d'un cadre peut être un médaillon
        // dans un coin. Les serrer reviendrait à refuser des cadres dont
        // la fenêtre est petite ou basse — et à rendre inexploitable la
        // détection automatique, qui rapporte ce que le fichier contient.
        'photo_x' => $borne('photo_x', 0, 0.9),
        'photo_y' => $borne('photo_y', 0, 0.9),
        'photo_w' => $borne('photo_w', 0.08, 1),
        'photo_h' => $borne('photo_h', 0.08, 1),
        'photo_forme' => $parmi('photo_forme', APPARENCE_FORMES),
        'bloc_x' => $borne('bloc_x', 0, 0.8),
        'bloc_y' => $borne('bloc_y', 0.02, 0.92),
        'bloc_w' => $borne('bloc_w', 0.15, 1),
        'accroche_taille' => $borne('accroche_taille', 0.02, 0.12),
        'champ_taille' => $borne('champ_taille', 0.014, 0.06),
        'qr_position' => $parmi('qr_position', APPARENCE_QR),
        'qr_taille' => $borne('qr_taille', 0.12, 0.28),
        'filigrane_position' => $parmi('filigrane_position', APPARENCE_FILIGRANE),
    ];
}

/**
 * Les deux rectangles de texte, déduits du bloc.
 *
 * L'accroche et le champ ne se règlent pas séparément : ils forment un bloc
 * qu'on déplace d'un seul geste. Deux réglages indépendants, et on obtient
 * surtout des décors où le prénom chevauche l'accroche.
 */
function rectangles_textes(array $a): array
{
    $h_accroche = $a['accroche_taille'] * 1.55;
    $h_champ = $a['champ_taille'] * 1.83;
    $y_champ = $a['bloc_y'] + $h_accroche + 0.004;

    // Le bloc ne sort pas du canevas, quelles que soient les tailles : on
    // rentre le débordement ici plutôt que de refuser la saisie plus tard
    // avec un « un élément déborde » que personne ne sait corriger.
    $debord = ($y_champ + $h_champ) - 1;
    if ($debord > 0) {
        $y_champ -= $debord;
    }
    $largeur = min($a['bloc_w'], 1 - $a['bloc_x']);

    return [
        'accroche' => ['x' => $a['bloc_x'], 'y' => $a['bloc_y'], 'w' => $largeur, 'h' => $h_accroche],
        'champ' => ['x' => $a['bloc_x'], 'y' => $y_champ, 'w' => $largeur, 'h' => $h_champ],
    ];
}

/* ---------------- fabrication ---------------- */

class GabaritInvalide extends RuntimeException
{
}

/**
 * Construit un gabarit valide à partir du formulaire partenaire, et le
 * valide. Une saisie qui viole une règle lève une exception portant le
 * message destiné au partenaire.
 */
function construire_gabarit(array $i): array
{
    $a = apparence_propre($i['disposition'], $i['apparence'] ?? []);
    $c = canevas($i['disposition'], $a['format']);
    $t = rectangles_textes($a);

    /**
     * La zone photo ne sort pas du canevas, et le masque suit la forme
     * demandée. `radius` est une fraction du plus petit côté de la zone :
     * 0,5 donnerait un ovale, 0,08 des coins arrondis discrets.
     */
    $photo = [
        'x' => $a['photo_x'],
        'y' => $a['photo_y'],
        'w' => min($a['photo_w'], 1 - $a['photo_x']),
        'h' => min($a['photo_h'], 1 - $a['photo_y']),
    ];
    $masque = match ($a['photo_forme']) {
        'cercle' => ['kind' => 'circle'],
        'arrondi' => ['kind' => 'rect', 'radius' => 0.08],
        default => ['kind' => 'rect', 'radius' => 0],
    };

    /**
     * L'accroche et le champ à remplir sont FACULTATIFS.
     *
     * Un décor peut n'avoir aucun texte : le cadre porte déjà tout ce qu'il
     * y a à dire, et l'invité n'a rien à saisir. Écrire un calque avec une
     * valeur vide reviendrait à réserver une zone de la mise en page pour
     * rien — et le pré-vol se plaindrait ensuite d'un texte introuvable.
     */
    $textes = [];
    if (trim((string) ($i['accroche'] ?? '')) !== '') {
        $textes[] = ['type' => 'text', 'id' => 'claim', 'value' => $i['accroche'],
             'editable' => false, 'placeholder' => '', 'maxLength' => 40,
             'uppercase' => in_array($i['disposition'], ['bandeau', 'facebook'], true),
             'rect' => $t['accroche'],
             'size' => $a['accroche_taille'], 'align' => $a['texte_align'],
             'color' => $a['texte_couleur'], 'font' => 'display', 'autoShrink' => true];
    }
    if (trim((string) ($i['champ_libelle'] ?? '')) !== '') {
        $textes[] = ['type' => 'text', 'id' => 'field', 'editable' => true,
             'placeholder' => $i['champ_libelle'], 'value' => $i['champ_valeur'],
             'maxLength' => 42, 'uppercase' => false,
             'rect' => $t['champ'],
             'size' => $a['champ_taille'], 'align' => $a['texte_align'],
             'color' => $a['texte_couleur'], 'font' => 'body', 'autoShrink' => true];
    }

    /**
     * Le cadre est un calque FACULTATIF.
     *
     * Une page blanche n'en a pas : son décor tient au fond, à la forme de
     * la fenêtre photo et au texte. Ajouter un calque image vide donnerait un
     * gabarit qui référence un fichier inexistant, et le rendu s'arrêterait
     * là où il devrait continuer.
     */
    $calques = [
        ['type' => 'photoSlot', 'id' => 'photo',
         'rect' => $photo,
         'fit' => 'cover', 'mask' => $masque,
         // 0,2 et non 0,5 : c'est ce qui permet de faire tenir une image
         // entière dans un décor qui n'a pas ses proportions.
         'minScale' => 0.2, 'maxScale' => 4, 'allowRotation' => false],
    ];
    if (($i['cadre_url'] ?? '') !== '') {
        $calques[] = ['type' => 'image', 'id' => 'frame', 'src' => $i['cadre_url'],
                      'rect' => ['x' => 0, 'y' => 0, 'w' => 1, 'h' => 1],
                      'opacity' => 1, 'blendMode' => 'normal'];
    }

    $gabarit = [
        'id' => nouvel_id(),
        'slug' => $i['slug'],
        'title' => $i['titre'],
        'subtitle' => $i['sous_titre'] ?: null,
        'city' => $i['ville'],
        'rubrique' => $i['rubrique'],
        'layout' => $i['disposition'],
        'status' => 'brouillon',
        'createdBy' => $i['cree_par'],
        'partnerId' => $i['partenaire_id'] ?? null,
        'expiresAt' => $i['expire_le'] ?: null,
        'moderation' => new stdClass(),
        'canvas' => ['ratio' => $c['ratio'], 'width' => $c['w'], 'height' => $c['h'], 'background' => $a['fond']],
        // Tous les champs sont écrits EXPLICITEMENT, y compris ceux que la
        // version TypeScript laissait remplir par zod. Le renderer les lit
        // sans les vérifier : un `mask` absent, et il ne dessine plus rien.
        // scripts/verifier-gabarit.ts compare cette structure au vrai schéma.
        'layers' => [
            ...$calques,
            ...$textes,
        ],
        // Le filigrane et le QR se déplacent, ne se retirent pas : ce sont
        // les deux informations qui font la différence avec une image.
        'watermark' => ['enabled' => true, 'position' => $a['filigrane_position'], 'opacity' => 0.9, 'variant' => 'wordmark'],
        'qr' => ['enabled' => true, 'position' => $a['qr_position'], 'size' => $a['qr_taille']],
        'export' => ['formats' => [$c['ratio']], 'maxPx' => 2048, 'quality' => 0.92, 'mimeType' => 'image/jpeg'],
        'filters' => ['none', 'wakabi-blue'],
        'share' => [
            'defaultCaption' => $i['legende'] ?? '',
            'hashtags' => ['Wakabi', 'JySerai'],
            'redirectUrl' => $i['redirection'],
            'redirectLabel' => $i['redirection_libelle'] ?: 'Découvrir sur Wakabi',
        ],
    ];

    valider_gabarit($gabarit);
    return $gabarit;
}

/**
 * Les invariants structurels.
 *
 * Chacun correspond à une manière concrète de produire un décor cassé ou
 * abusif. Ils sont vérifiés à l'écriture, jamais à la lecture : un gabarit
 * invalide en base ne se manifeste qu'en page introuvable.
 */
function valider_gabarit(array $g): void
{
    $couches = $g['layers'] ?? [];

    $slots = array_filter($couches, fn($l) => ($l['type'] ?? '') === 'photoSlot');
    if (count($slots) !== 1) {
        throw new GabaritInvalide('Un décor doit contenir exactement un emplacement photo.');
    }

    $r = reset($slots)['rect'] ?? null;
    if ($r && ($r['w'] < 0.24 || $r['h'] < 0.24)) {
        throw new GabaritInvalide(
            'L’emplacement photo doit occuper au moins un quart de la largeur et de la hauteur : '
            . 'en dessous, l’invité n’y tient plus.'
        );
    }

    $iPhoto = array_key_first($slots);
    $iCadre = null;
    foreach ($couches as $k => $l) {
        if (($l['type'] ?? '') === 'image' && ($l['id'] ?? '') === 'frame') {
            $iCadre = $k;
        }
    }
    if ($iCadre !== null && $iCadre < $iPhoto) {
        throw new GabaritInvalide('Le cadre doit être dessiné APRÈS la photo, sinon la photo le recouvre.');
    }

    // Le ratio est LU, pas deviné : trois formats hier, six aujourd'hui, et
    // un `9:16 sinon carré` codé en dur aurait accepté un 4:5 déclaré carré.
    $c = $g['canvas'] ?? [];
    [$rw, $rh] = array_map('floatval', explode(':', (string) ($c['ratio'] ?? '1:1')) + [1, 1]);
    $attendu = $rh > 0 ? $rw / $rh : 1;
    $reel = ($c['width'] ?? 1) / max(1, $c['height'] ?? 1);
    if (abs($reel - $attendu) > 0.01) {
        throw new GabaritInvalide('Les dimensions du canevas ne correspondent pas au format annoncé.');
    }

    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) ($g['slug'] ?? ''))) {
        throw new GabaritInvalide('L’adresse du décor ne peut contenir que des minuscules, des chiffres et des tirets.');
    }

    foreach ($couches as $l) {
        if (!isset($l['rect'])) {
            continue;
        }
        $r = $l['rect'];
        if ($r['x'] < 0 || $r['y'] < 0 || $r['x'] + $r['w'] > 1.0001 || $r['y'] + $r['h'] > 1.0001) {
            throw new GabaritInvalide('Un élément déborde du canevas.');
        }
    }

    // Le garde-fou de redirection — validé côté serveur, jamais côté formulaire.
    $redir = (string) ($g['share']['redirectUrl'] ?? '');
    if ($redir === '') {
        throw new GabaritInvalide('Indiquez la page vers laquelle renvoyer après téléchargement.');
    }
    $hote = parse_url($redir, PHP_URL_HOST);
    if (!$hote) {
        throw new GabaritInvalide('L’adresse de redirection n’est pas une URL valide.');
    }
    if (($g['createdBy'] ?? '') === 'partenaire') {
        $permis = false;
        foreach (WAKABI_DOMAINES as $d) {
            if ($hote === $d || str_ends_with($hote, '.' . $d)) {
                $permis = true;
                break;
            }
        }
        if (!$permis) {
            throw new GabaritInvalide(sprintf(
                'Un décor de partenaire doit rediriger vers un domaine Wakabi (%s), pas vers « %s ».',
                implode(', ', WAKABI_DOMAINES),
                $hote
            ));
        }
    }

    if (($g['status'] ?? '') === 'publie' && ($g['createdBy'] ?? '') === 'partenaire'
        && empty($g['moderation']['reluPar'])) {
        throw new GabaritInvalide('Un décor de partenaire ne peut pas être publié sans relecture Wakabi.');
    }
    if (in_array($g['status'] ?? '', ['refuse', 'corrections'], true) && empty($g['moderation']['motif'])) {
        throw new GabaritInvalide('Un refus ou une demande de correction exige un motif.');
    }
}
