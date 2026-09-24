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
];

/**
 * Les pages que Boost expose SANS compte.
 *
 * Elles complètent `PAGES_CONTENU` pour décider de la barre : un membre
 * connecté y reste devant la vitrine. Tout le reste, c'est du travail.
 *
 * `nouveau` y figure et c'est voulu : le Studio s'ouvre sans compte. Un
 * visiteur y compose son décor devant la barre de vitrine ; un
 * organisateur qui l'ouvre depuis son tableau de bord est au travail, et
 * la règle le lui sert alors dans l'autre barre.
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
        ]],
        ['?p=decors',      'Les décors'],
        ['?p=partenaires', 'Partenaires'],
        ['?p=villes',      'Nos Villes'],
        ['?p=blog',        'Blog'],
        ['?p=a-propos',    'À Propos'],
    ];
}

/**
 * La première offre qui ouvre une ligne, telle qu'elle est AUJOURD'HUI.
 *
 * Les pages de présentation de Boost annoncent un prix. L'écrire à la
 * main les ferait mentir le jour où quelqu'un change une offre depuis
 * l'administration — et personne ne penserait à rouvrir trois pages de
 * vitrine pour corriger un chiffre.
 *
 * Deux sortes de lignes, et c'est la raison de cette fonction plutôt que
 * d'un simple appel à `offre_qui_debloque()` : une CAPACITÉ s'ouvre ou
 * non, un COMPTEUR grandit. « La régie » se débloque ; « cent liens
 * courts » ne se débloque pas, il se compte, et `offre_qui_debloque()`
 * répond donc `null` pour lui — à raison.
 */
function offre_porte(string $ligne): ?array
{
    $compteurs = ['campagnes', 'telechargements', 'liens_courts', 'emails_par_mois'];
    if (in_array($ligne, $compteurs, true)) {
        foreach (formules_actives() as $cle => $f) {
            if ((int) ($f[$ligne] ?? 0) !== 0) {
                return ['cle' => $cle] + $f;
            }
        }
        return null;
    }
    $cle = offre_qui_debloque($ligne);
    return $cle === null ? null : ['cle' => $cle] + formules()[$cle];
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
