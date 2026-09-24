<?php
/**
 * Les icônes des pages du guide, dessinées plutôt que chargées.
 *
 * Le site d'origine allait chercher vingt-huit icônes chez `icons8.com`,
 * une requête chacune, plus six drapeaux en PNG couleur. Sur une
 * connexion 3G de Lomé ou de Cotonou, c'est trente-quatre allers-retours
 * vers un tiers avant que la page ait l'air finie — et le jour où ce
 * tiers change ses adresses ou ferme, trente-quatre carrés vides.
 *
 * Le projet avait déjà tranché cette question pour le pied de page :
 * `icones.php` redessine les quatre logos sociaux au lieu de les prendre
 * sur un sous-domaine WordPress. Ces pages-ci suivent la même règle.
 *
 * Les tracés suivent la couleur du texte ; les drapeaux, non, parce
 * qu'un drapeau monochrome n'est plus un drapeau.
 */

declare(strict_types=1);

/**
 * Les tracés, rangés par le nom que portait l'icône d'origine.
 *
 * Garder ces noms-là — `map-marker`, `push-notifications` — plutôt que de
 * les rebaptiser permet de retrouver, six mois plus tard, ce que la page
 * montrait avant la fusion.
 */
const TRACES_GUIDE = [
    'search'        => '<circle cx="11" cy="11" r="7"></circle><line x1="16.5" y1="16.5" x2="21" y2="21"></line>',
    'calendar'      => '<rect x="3" y="5" width="18" height="16" rx="2.5"></rect><line x1="3" y1="10" x2="21" y2="10"></line><line x1="8" y1="3" x2="8" y2="7"></line><line x1="16" y1="3" x2="16" y2="7"></line>',
    'map-marker'    => '<path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11z"></path><circle cx="12" cy="10" r="2.6"></circle>',
    'push-notifications' => '<path d="M18 8.5a6 6 0 1 0-12 0c0 6-2 7.5-2 7.5h16s-2-1.5-2-7.5"></path><path d="M13.7 20a2 2 0 0 1-3.4 0"></path>',
    'ticket'        => '<path d="M3 9.5V7a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2.5a2.5 2.5 0 0 0 0 5V17a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2.5a2.5 2.5 0 0 0 0-5z"></path><line x1="14" y1="5" x2="14" y2="19" stroke-dasharray="2 3"></line>',
    'crowd'         => '<circle cx="8" cy="8" r="2.8"></circle><circle cx="16" cy="8" r="2.8"></circle><path d="M3 19c0-2.6 2.2-4.2 5-4.2s5 1.6 5 4.2"></path><path d="M13.5 15.2c.8-.3 1.6-.4 2.5-.4 2.8 0 5 1.6 5 4.2"></path>',
    'compass'       => '<circle cx="12" cy="12" r="9"></circle><polygon points="15.5 8.5 13.5 13.5 8.5 15.5 10.5 10.5"></polygon>',
    'user'          => '<circle cx="12" cy="8" r="3.6"></circle><path d="M4.8 20c0-3.5 3.2-5.6 7.2-5.6s7.2 2.1 7.2 5.6"></path>',
    /* L'icône d'origine s'appelait « bard » ; elle illustrait « Assistance :
       une assistante à plein temps ». Le nom d'un catalogue d'icônes n'est
       pas toujours celui de ce qu'on montre. */
    'assistance'    => '<path d="M4 13.5v-1.5a8 8 0 0 1 16 0v1.5"></path><rect x="2.6" y="13" width="4" height="6" rx="1.8"></rect><rect x="17.4" y="13" width="4" height="6" rx="1.8"></rect><path d="M19.4 19v.8a2.6 2.6 0 0 1-2.6 2.6H13"></path>',
    'telephone'     => '<rect x="6" y="2.5" width="12" height="19" rx="2.5"></rect><line x1="10.5" y1="18.5" x2="13.5" y2="18.5"></line>',
    'boutique'      => '<path d="M4 9.5V19a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9.5"></path><path d="M3 9.5l1.6-5A2 2 0 0 1 6.5 3h11a2 2 0 0 1 1.9 1.5L21 9.5a3 3 0 0 1-6 0 3 3 0 0 1-6 0 3 3 0 0 1-6 0z"></path>',
    'confiance'     => '<path d="M12 21s7.5-4 7.5-9.5V5.8L12 3 4.5 5.8v5.7C4.5 17 12 21 12 21z"></path><polyline points="9 11.8 11.3 14.1 15.4 10"></polyline>',
    'creativite'    => '<path d="M9.5 18.5h5"></path><path d="M10 21.5h4"></path><path d="M12 2.5a6.5 6.5 0 0 0-3.7 11.8c.5.4.8 1 .8 1.6v.6h5.8v-.6c0-.6.3-1.2.8-1.6A6.5 6.5 0 0 0 12 2.5z"></path>',
    'equipe'        => '<circle cx="9" cy="7.5" r="3"></circle><path d="M3 19c0-2.8 2.4-4.5 6-4.5s6 1.7 6 4.5"></path><path d="M16.5 5.2a3 3 0 0 1 0 5.6"></path><path d="M17.5 14.9c2.1.5 3.5 1.9 3.5 4.1"></path>',
    'croissance'    => '<line x1="3" y1="21" x2="21" y2="21"></line><rect x="4.5" y="12" width="3.6" height="6"></rect><rect x="10.2" y="8" width="3.6" height="10"></rect><rect x="15.9" y="4" width="3.6" height="14"></rect>',
    'pourcentage'   => '<line x1="6" y1="18" x2="18" y2="6"></line><circle cx="7.6" cy="7.6" r="2.4"></circle><circle cx="16.4" cy="16.4" r="2.4"></circle>',
    'visibilite'    => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"></path><circle cx="12" cy="12" r="3"></circle>',
    'analyse'       => '<path d="M3 3v18h18"></path><polyline points="6.5 15 10.5 10.5 14 13.5 19 7"></polyline>',
    'hotel'         => '<path d="M3 21V6.5l9-3.5 9 3.5V21"></path><rect x="9.5" y="13.5" width="5" height="7.5"></rect><line x1="7" y1="9.5" x2="7" y2="9.6"></line><line x1="12" y1="9.5" x2="12" y2="9.6"></line><line x1="17" y1="9.5" x2="17" y2="9.6"></line>',
    'cocktail'      => '<path d="M4 5h16l-8 8z"></path><line x1="12" y1="13" x2="12" y2="20"></line><line x1="8.5" y1="20" x2="15.5" y2="20"></line>',
    'monde'         => '<circle cx="12" cy="12" r="9"></circle><ellipse cx="12" cy="12" rx="3.8" ry="9"></ellipse><line x1="3.2" y1="9" x2="20.8" y2="9"></line><line x1="3.2" y1="15" x2="20.8" y2="15"></line>',
    'recompense'    => '<circle cx="12" cy="9" r="5.5"></circle><polyline points="8.5 13.5 7 21.5 12 19 17 21.5 15.5 13.5"></polyline>',
];

/**
 * Une icône du guide, par son nom d'origine.
 *
 * Un nom inconnu rend une chaîne vide plutôt qu'une icône de secours : un
 * carré générique au milieu d'une page soignée se remarque plus qu'un
 * trou, et un trou se corrige.
 */
function icone_guide(string $nom, int $taille = 26): string
{
    $d = TRACES_GUIDE[$nom] ?? '';
    if ($d === '') {
        return '';
    }
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" '
        . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" '
        . 'style="width:' . $taille . 'px;height:' . $taille . 'px">' . $d . '</svg>';
}

/**
 * Les drapeaux des pays où le guide s'installe.
 *
 * Dessinés en aplats plutôt que chargés en PNG couleur. Ils gardent leurs
 * couleurs — un drapeau qui suivrait la couleur du texte ne dirait plus
 * rien — et portent un `title` : à côté, la page écrit déjà le nom du
 * pays, mais l'image doit se nommer pour qui ne la voit pas.
 */
const DRAPEAUX = [
    'benin'   => ['Bénin',         '<rect width="12" height="24" fill="#008751"/><rect x="12" width="24" height="12" fill="#FCD116"/><rect x="12" y="12" width="24" height="12" fill="#E8112D"/>'],
    /* Cinq bandes égales — vert, jaune, vert, jaune, vert — et un carré
       rouge au guindant, haut de trois bandes, avec l'étoile blanche. */
    'togo'    => ['Togo',          '<rect width="36" height="4.8" y="0" fill="#006A4E"/><rect width="36" height="4.8" y="4.8" fill="#FFCE00"/><rect width="36" height="4.8" y="9.6" fill="#006A4E"/><rect width="36" height="4.8" y="14.4" fill="#FFCE00"/><rect width="36" height="4.8" y="19.2" fill="#006A4E"/><rect width="14.4" height="14.4" fill="#D21034"/><polygon points="7.2,2.6 8.6,6.4 12.6,6.4 9.4,8.9 10.6,12.7 7.2,10.4 3.8,12.7 5,8.9 1.8,6.4 5.8,6.4" fill="#fff"/>'],
    'ci'      => ['Côte d’Ivoire', '<rect width="12" height="24" fill="#F77F00"/><rect x="12" width="12" height="24" fill="#fff"/><rect x="24" width="12" height="24" fill="#009E60"/>'],
    'senegal' => ['Sénégal',       '<rect width="12" height="24" fill="#00853F"/><rect x="12" width="12" height="24" fill="#FDEF42"/><rect x="24" width="12" height="24" fill="#E31B23"/><polygon points="18,7.5 19.4,11.6 23.7,11.6 20.2,14.1 21.6,18.2 18,15.7 14.4,18.2 15.8,14.1 12.3,11.6 16.6,11.6" fill="#00853F"/>'],
    'cameroun'=> ['Cameroun',      '<rect width="12" height="24" fill="#007A5E"/><rect x="12" width="12" height="24" fill="#CE1126"/><rect x="24" width="12" height="24" fill="#FCD116"/><polygon points="18,8 19.2,11.6 23,11.6 19.9,13.8 21.1,17.4 18,15.2 14.9,17.4 16.1,13.8 13,11.6 16.8,11.6" fill="#FCD116"/>'],
    'gabon'   => ['Gabon',         '<rect width="36" height="8" fill="#009E60"/><rect y="8" width="36" height="8" fill="#FCD116"/><rect y="16" width="36" height="8" fill="#3A75C4"/>'],
];

function drapeau(string $pays, int $largeur = 42): string
{
    if (!isset(DRAPEAUX[$pays])) {
        return '';
    }
    [$nom, $d] = DRAPEAUX[$pays];
    $h = (int) round($largeur * 24 / 36);
    return '<svg viewBox="0 0 36 24" role="img" aria-label="Drapeau ' . e($nom) . '" '
        . 'style="width:' . $largeur . 'px;height:' . $h . 'px;border-radius:3px;'
        . 'box-shadow:0 1px 3px rgba(15,23,42,.18);display:block">'
        . '<title>' . e($nom) . '</title>' . $d . '</svg>';
}
