<?php
/**
 * La frontière entre la vitrine et l'atelier.
 *
 * Le site fusionné porte deux barres, et non une seule qui change de
 * contenu. C'est délibéré : ce sont deux métiers.
 *
 * La barre de VITRINE ne bouge jamais. Connecté ou non, on y voit les
 * mêmes sept entrées, parce qu'un site public dont le menu se réarrange
 * selon qui regarde oblige chacun à réapprendre où sont les choses. Seul
 * le volet du compte change : il propose de se connecter, ou il porte le
 * nom de la personne et de quoi ressortir.
 *
 * La barre d'ADMINISTRATION, elle, se déduit des droits et ne paraît que
 * sur les écrans de travail. Elle a sa propre logique, dans layout.php,
 * et elle n'a pas bougé d'un caractère : ce qui marchait continue.
 *
 * Deux listes suffisent à tout régler, et elles règlent deux questions
 * différentes qu'il ne faut pas confondre :
 *
 *   PAGES_CONTENU — les pages de contenu du site fusionné : leur vue,
 *                   leur titre, leur phrase de partage. C'est aussi
 *                   elle qui dit quelle FEUILLE DE STYLE servir : y
 *                   figurer, c'est porter le dessin du guide.
 *   PAGES_VITRINE — ce que Boost expose sans compte. Hors de ces deux
 *                   listes, un membre connecté est au travail.
 */

declare(strict_types=1);

/**
 * L'adresse de l'application mobile, écrite UNE fois.
 *
 * Elle vivait en clair dans le pied de page ; le header et les pages du
 * guide la demandent à leur tour. Quatre copies d'une adresse de magasin,
 * c'est trois qu'on oubliera le jour d'un changement d'identifiant.
 */
const APPLICATION_URL = 'https://play.google.com/store/apps/details?id=com.wakabi.wakabimobile';

/**
 * Ce que chaque page de contenu porte : sa vue, son titre, sa phrase.
 *
 * Rangé ici et non dans le routeur, parce que ces trois-là vont ensemble
 * et se relisent d'un coup d'oeil. Le titre et la description partent
 * dans les balises de partage : un lien collé dans WhatsApp montre cette
 * phrase-là, et c'est par ce lien que tout circule.
 */
const PAGES_CONTENU = [
    'application' => ['guide-application', 'L’Application Wakabi',
        'Ce qu’il faut savoir sur l’expérience, les fonctionnalités et la technologie derrière Wakabi.'],
    'partenaires' => ['guide-partenaires', 'Devenir partenaire',
        'Zéro commission, visibilité maximale et des statistiques en temps réel pour votre établissement.'],
    'villes'      => ['guide-villes', 'Nos villes',
        'Cotonou, Lomé, Abidjan, et la suite : le plan d’expansion de Wakabi en Afrique francophone.'],
    'a-propos'    => ['guide-a-propos', 'À propos',
        'Qui nous sommes, ce que nous construisons, et pourquoi nous le construisons ici.'],
    'contact'     => ['guide-contact', 'Contact',
        'Une question, un partenariat, une ville à ouvrir : écrivez-nous.'],
    'boost-push'  => ['guide-push', 'Push',
        'WhatsApp, Telegram et les notifications du navigateur, depuis un seul écran.'],
    'boost-regie' => ['guide-regie', 'Régie',
        'Chaque badge laisse une adresse. La régie en fait une audience, et elle reste la vôtre.'],
    'boost-liens' => ['guide-liens', 'Liens courts',
        'Une adresse courte à mettre sur une affiche, et le nombre de personnes qui l’ont suivie.'],
    /**
     * Les deux pages légales, ici et non plus chez le guide.
     *
     * Elles vivaient sur wakabileguide.com/cgu.html et /confidentialite.html,
     * les deux dernières sorties du pied de page : on cliquait « CGU », on
     * changeait de site, on arrivait sous un autre header, et il fallait
     * revenir à la main. C'est la coupure que la fusion supprime partout
     * ailleurs, et ces deux liens-là s'ouvrent précisément au moment où
     * l'on hésite à créer un compte ou à payer.
     *
     * Elles figurent dans cette table comme les autres, et en héritent
     * tout : le dessin du guide, la barre de vitrine, le pied complet, et
     * leur ligne dans le plan du site.
     */
    /**
     * Les tarifs, page à part entière et non ancre de l'accueil.
     *
     * Treize boutons du produit menaient à `?p=accueil#tarifs`, c'est-à-dire
     * à quatre mille pixels du haut d'une page que le visiteur n'avait pas
     * demandée. Une ancre ne se partage pas, ne se met pas en favori, et un
     * moteur ne la distingue pas de l'accueil.
     */
    'tarifs'          => ['guide-tarifs', 'Tarifs',
        'Une offre gratuite à vie, et trois qui grandissent avec vos salles. Sans engagement.'],
    'cgu'             => ['guide-cgu', 'Conditions d’utilisation',
        'Ce que vous pouvez attendre du service, ce qu’il attend de vous, et ce qui se passe quand ça ne va pas.'],
    'confidentialite' => ['guide-confidentialite', 'Politique de confidentialité',
        'Ce que nous collectons, pourquoi, combien de temps, et comment tout emporter ou tout effacer vous-même.'],
];

/**
 * Les pages que Boost expose SANS compte.
 *
 * Elles complètent `PAGES_CONTENU` pour décider de la barre : un membre
 * connecté y reste devant la vitrine. Tout le reste, c'est du travail.
 *
 * `nouveau` n'y figure PAS, et c'est voulu : le Studio s'ouvre sans
 * compte, mais il ne se LIT pas, il s'utilise. Un visiteur anonyme y
 * arrive quand même devant la barre de vitrine — la première règle,
 * `$moi === null`, s'en charge, parce qu'il n'a rien d'autre à voir. Un
 * organisateur qui l'ouvre depuis son tableau de bord, lui, est au
 * travail : il garde sa barre et ses raccourcis.
 */
const PAGES_VITRINE = [
    'accueil', 'decors', 'decor', 'blog', 'article', 'creer', 'lien-court',
    'connexion', 'inscription', 'oubli', 'reinitialiser', 'verification',
    'offre-requise', 'introuvable', 'desabonnement', 'qr',
];

/**
 * Cette page porte-t-elle le dessin du guide ?
 *
 * La question se pose à `PAGES_CONTENU` plutôt qu'à une seconde liste :
 * deux listes qui doivent s'accorder finissent toujours par diverger, et
 * celle-là se serait vengée en servant la mauvaise feuille — donc une
 * page au dessin cassé, sans message ni piste.
 */
function page_du_guide(string $page): bool
{
    return isset(PAGES_CONTENU[$page]);
}

/**
 * Cette page montre-t-elle la barre de vitrine ?
 *
 * Non connecté : toujours — de toute façon, rien d'autre ne s'ouvre.
 * Connecté : seulement sur le site public, c'est-à-dire les pages de
 * contenu et celles que Boost expose sans compte.
 */
function barre_vitrine(?array $moi, string $page): bool
{
    return $moi === null
        || page_du_guide($page)
        || in_array($page, PAGES_VITRINE, true);
}

/**
 * Le menu de la vitrine, identique pour tout le monde.
 *
 * Écrit une fois ici plutôt que dans le gabarit : le pied de page et le
 * plan du site y puiseront, et trois listes qui divergent finissent
 * toujours par proposer une page qui n'existe plus.
 *
 * Chaque entrée : [route, libellé] ; un groupe : ['groupe', libellé,
 * [sous-entrées avec leur ligne d'explication]].
 */
function menu_vitrine(): array
{
    return [
        ['?p=application', 'L’Application'],
        ['groupe', 'Boost', [
            ['?p=boost-push',  'Push',         'WhatsApp, Telegram, notifications'],
            ['?p=boost-regie', 'Régie',        'Campagnes e-mail'],
            ['?p=boost-liens', 'Liens courts', 'Adresses traçables'],
            ['?p=tarifs',      'Tarifs',       'Les quatre offres, comparées'],
        ]],
        ['?p=decors',      'Les décors'],
        ['?p=partenaires', 'Partenaires'],
        ['?p=villes',      'Nos Villes'],
        ['?p=blog',        'Blog'],
        ['?p=a-propos',    'À Propos'],
    ];
}

/* ------------------------------------------------------------------ */
/* Les offres, calculées UNE fois pour les deux pages qui les montrent  */
/* ------------------------------------------------------------------ */

/**
 * Ce que chaque offre vendue donne, prêt à afficher.
 *
 * Ce calcul vivait en entier dans `accueil.php`, en trois fermetures de
 * cent quarante lignes. Il en faut maintenant un second exemplaire sur la
 * page des tarifs, et recopier cent quarante lignes de règles de vente,
 * c'est se donner rendez-vous dans six mois avec deux pages qui annoncent
 * deux choses différentes.
 *
 * Il rend des DONNÉES, pas du balisage, et c'est la raison de sa forme :
 * l'accueil est habillé par `wakabi.css`, la page des tarifs par
 * `guide.css`, et leurs cartes n'ont pas une classe en commun. Une
 * fonction qui rendrait du HTML aurait obligé l'une des deux à porter le
 * dessin de l'autre.
 *
 * Les prix et les lignes viennent de `formules()`, jamais d'une liste
 * écrite à la main : une promesse et son application ne peuvent pas
 * diverger quand elles n'ont qu'une source.
 *
 * @return list<array{cle:string,nom:string,tag:string,cta:string,phare:bool,
 *                    prix:int,lancement:int,prefixe:?string,
 *                    lignes:list<array{0:string,1:int}>}>
 */
function offres_vitrine(): array
{
    /** Ce qu'une offre donne, ligne à ligne, telle qu'on la vend. */
    $lignes_offre = static function (string $cle): array {
        $f = formules()[$cle] ?? formules()['decouverte'] ?? FORMULES['decouverte'];
        $out = [];
        $out[] = [$f['campagnes'] < 0 ? 'Campagnes illimitées'
                  : $f['campagnes'] . ' campagne' . ($f['campagnes'] > 1 ? 's' : '') . ' active'
                    . ($f['campagnes'] > 1 ? 's' : ''), 1];
        $out[] = [$f['telechargements'] < 0 ? 'Téléchargements illimités'
                  : number_format($f['telechargements'], 0, ',', ' ') . ' téléchargements / mois', 1];
        if ($f['liens_courts'] !== 0) {
            $out[] = [$f['liens_courts'] < 0 ? 'Liens courts illimités'
                      : $f['liens_courts'] . ' liens courts wkb.link', 1];
        }
        /* Un compteur qui vaut zéro ne se montre pas : « 0 e-mail par mois »
           se lit comme une privation, alors que l'offre n'a simplement
           jamais promis cette ligne. */
        if ($f['emails_par_mois'] !== 0) {
            $out[] = [$f['emails_par_mois'] < 0 ? 'E-mails marketing illimités'
                      : number_format($f['emails_par_mois'], 0, ',', ' ') . ' e-mails marketing / mois', 1];
        }
        /* Le Studio est dans toutes les offres, y compris la gratuite :
           c'est le produit lui-même, et le dire ici évite qu'on croie
           l'offre d'essai amputée. */
        $out[] = ['Studio complet', 1];
        if ($f['stats'] !== 'completes') {
            $out[] = ['Statistiques de base', 1];
        }

        $barrees = 0;
        foreach (OFFRE_LIGNES as $k => [$libelle, $nature, ]) {
            if ($nature === 'compteur') {
                continue;
            }
            $ouvert = $k === 'stats' ? $f['stats'] === 'completes' : (bool) $f[$k];
            if ($ouvert) {
                $out[] = [$libelle, 1];
                continue;
            }
            /* Deux lignes barrées suffisent à situer une offre d'entrée.
               Les dix y tiendraient, mais la carte deviendrait un catalogue
               de ce qu'on n'a pas. */
            if ($cle === 'decouverte' && $barrees < 2) {
                $out[] = [$libelle, 0];
                $barrees++;
            }
        }
        return $out;
    };

    /** Ce que l'offre précédente donnait déjà : inutile de le relister. */
    $nouveautes = static function (string $cle, ?string $avant) use ($lignes_offre): array {
        if (!$avant) {
            return $lignes_offre($cle);
        }
        $deja = [];
        foreach ($lignes_offre($avant) as [$t, $ok]) {
            if ($ok) {
                $deja[$t] = true;
            }
        }
        return array_values(array_filter($lignes_offre($cle), fn($l) => !isset($deja[$l[0]])));
    };

    /**
     * La liste des offres se DÉDUIT, elle ne s'écrit pas.
     *
     * Les quatre clés étaient codées en dur avec leur accroche et leur
     * bouton : ajouter une offre depuis l'administration aurait donné une
     * offre invisible, et en supprimer une aurait cassé la page.
     */
    $vendues = formules_actives();
    $cles = array_keys($vendues);
    $out = [];
    foreach ($cles as $i => $cle) {
        $f = $vendues[$cle];
        /**
         * La PREMIÈRE offre payante liste tout ce qu'elle donne.
         *
         * « Tout Découverte, plus : » ferait paraître maigre l'offre
         * d'entrée, puisque Découverte ne donne presque rien. Le raccourci
         * ne vaut qu'entre deux offres qui se paient toutes les deux.
         */
        $prec = $i > 0 ? $cles[$i - 1] : null;
        $avant = $prec !== null && (int) $f['prix'] > 0
              && (int) $vendues[$prec]['prix'] > 0 ? $prec : null;
        $out[] = [
            'cle' => $cle,
            'nom' => (string) $f['nom'],
            'tag' => (string) ($f['tag'] ?? ''),
            'cta' => (string) ($f['cta'] ?? '') ?: ('Choisir ' . $f['nom']),
            'phare' => (bool) ($f['phare'] ?? false),
            'prix' => (int) $f['prix'],
            'lancement' => (int) $f['lancement'],
            'prefixe' => $avant ? 'Tout ' . $vendues[$avant]['nom'] . ', plus :' : null,
            'lignes' => $nouveautes($cle, $avant),
        ];
    }
    return $out;
}

/**
 * Les trois icônes du déroulant « Boost ».
 *
 * Dessinées ici plutôt que chargées : le site d'origine allait chercher
 * vingt-huit icônes chez un tiers, une requête chacune, sur une connexion
 * où le mégaoctet se compte. Un tracé suit la couleur du texte, ne se
 * charge pas, et ne dépend d'aucun serveur que nous ne tenons pas.
 */
function icone_vitrine(string $route): string
{
    $d = match (true) {
        str_contains($route, 'push') =>
            '<path d="M18 8.5a6 6 0 1 0-12 0c0 6-2 7.5-2 7.5h16s-2-1.5-2-7.5"></path>'
            . '<path d="M13.7 20a2 2 0 0 1-3.4 0"></path>',
        str_contains($route, 'regie') =>
            '<rect x="3" y="5" width="18" height="14" rx="2.5"></rect>'
            . '<polyline points="3.6 6.5 12 13 20.4 6.5"></polyline>',
        default =>
            '<path d="M10 13.5a4 4 0 0 0 5.7.4l3-3a4 4 0 0 0-5.7-5.7l-1.7 1.7"></path>'
            . '<path d="M14 10.5a4 4 0 0 0-5.7-.4l-3 3a4 4 0 0 0 5.7 5.7l1.7-1.7"></path>',
    };
    return '<svg viewBox="0 0 24 24" fill="none" stroke="#475569" stroke-width="1.9" '
        . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" '
        . 'style="width:18px;height:18px">' . $d . '</svg>';
}

/**
 * Les initiales d'un nom, pour la pastille du compte.
 *
 * Deux lettres au plus, prises sur les deux premiers mots. Un nom d'un
 * seul mot donne une seule lettre plutôt que deux tirées du même mot :
 * « KO » pour Kossi ressemble à un code, « K » se lit comme une initiale.
 */
function initiales(string $nom): string
{
    $mots = preg_split('/\s+/u', trim($nom), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (!$mots) {
        return '?';
    }
    $p = mb_strtoupper(mb_substr($mots[0], 0, 1));
    return count($mots) > 1 ? $p . mb_strtoupper(mb_substr($mots[1], 0, 1)) : $p;
}

/* ------------------------------------------------------------------ */
/* L'identité légale, pour les deux pages de conditions                */
/* ------------------------------------------------------------------ */

/**
 * Ce que seule l'équipe peut renseigner, et le reste qu'on sait déjà.
 *
 * Les CGU et la politique de confidentialité doivent dire QUI édite le
 * site et qui répond des données. Le code ne peut ni deviner une raison
 * sociale, ni un numéro d'immatriculation, ni un siège, et surtout ne
 * doit pas les inventer : un numéro faux sur une page de conditions est
 * un faux, pas un défaut d'affichage. Ces quatre champs vivent donc dans
 * les réglages, vides au départ, et les pages n'écrivent que ce qui est
 * là.
 *
 * `legal_maj` est la date de la dernière révision des TEXTES, et non la
 * date du jour. `date('d/m/Y')` aurait été plus simple et aurait menti :
 * une page de conditions qui se déclare à jour chaque matin est une page
 * dont personne ne peut vérifier ce qui a changé, ni depuis quand.
 */
const LEGAL_DEFAUTS = [
    'legal_forme'    => '',
    'legal_registre' => '',
    'legal_siege'    => '',
    'legal_courriel' => 'contact@wakabileguide.com',
    'legal_maj'      => '2026-10-01',
];

function identite_legale(): array
{
    static $cache = null;
    static $version = -1;
    if ($cache !== null && $version === reglages_version()) {
        return $cache;
    }

    $lus = reglages_bdd(array_keys(LEGAL_DEFAUTS));
    $r = [];
    foreach (LEGAL_DEFAUTS as $cle => $defaut) {
        $v = trim((string) ($lus[$cle] ?? ''));
        $r[substr($cle, 6)] = $v !== '' ? $v : $defaut;
    }

    /* Le nom, la ville et le téléphone sont DÉJÀ des réglages : ceux du
       référencement. Les redemander ici en aurait fait deux copies à
       tenir d'accord, et c'est toujours la seconde qu'on oublie. */
    $r['nom'] = seo_reglage('seo_organisation') ?: 'Wakabi';
    $r['ville'] = seo_reglage('seo_ville');
    $r['telephone'] = seo_reglage('seo_telephone');

    /* Un siège vide se rabat sur la ville du référencement : « dont le
       siège est à Lomé » est moins précis qu'une adresse, et bien plus
       utile que rien. */
    if ($r['siege'] === '' && $r['ville'] !== '') {
        $r['siege'] = $r['ville'];
    }

    $version = reglages_version();
    return $cache = $r;
}

/** La date de révision des textes légaux, telle qu'on l'affiche. */
function legal_date(): string
{
    $d = identite_legale()['maj'];
    $t = strtotime($d) ?: time();
    // Écrite en clair plutôt qu'en chiffres : « 1er octobre 2026 » ne se
    // lit pas à l'envers, « 01/10/2026 » si.
    $mois = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet',
             'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $j = (int) date('j', $t);
    return ($j === 1 ? '1er' : (string) $j) . ' ' . $mois[(int) date('n', $t)]
        . ' ' . date('Y', $t);
}

/**
 * Les mêmes valeurs, BRUTES, pour le formulaire des réglages.
 *
 * `identite_legale()` comble les vides : un siège non renseigné prend la
 * ville du référencement, et une adresse de contact absente prend celle
 * de la maison. C'est ce qu'il faut pour une page publique, et c'est
 * trompeur dans un champ de saisie : on croirait avoir renseigné quelque
 * chose qu'on n'a pas touché, et le jour où la ville change, le siège
 * changerait avec elle sans qu'on l'ait voulu.
 */
function identite_legale_brute(): array
{
    $lus = reglages_bdd(array_keys(LEGAL_DEFAUTS));
    $r = [];
    foreach (LEGAL_DEFAUTS as $cle => $defaut) {
        $r[substr($cle, 6)] = trim((string) ($lus[$cle] ?? ''));
    }
    // Deux exceptions, et seulement parce que le défaut est vrai sans
    // réglage : l'adresse de la maison et la date d'écriture des textes.
    $r['courriel'] = $r['courriel'] ?: LEGAL_DEFAUTS['legal_courriel'];
    $r['maj'] = $r['maj'] ?: LEGAL_DEFAUTS['legal_maj'];
    return $r;
}
