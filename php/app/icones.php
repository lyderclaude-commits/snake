<?php
/**
 * Jeu d'icônes — SVG en ligne, dessinées sur une grille de 24 px.
 *
 * En ligne et non en fichiers : une icône par requête sur un mutualisé, sur
 * une connexion 3G, coûte plus cher que les 300 octets qu'elle pèse. Et pas
 * d'émojis : leur rendu change d'un téléphone à l'autre, et ils ne prennent
 * pas la couleur de la marque.
 */

declare(strict_types=1);

function icone(string $nom): string
{
    $traits = 'fill="none" stroke="currentColor" stroke-width="1.7" '
            . 'stroke-linecap="round" stroke-linejoin="round"';

    $chemins = [
        // Bulle de conversation — la messagerie
        'message' => '<path d="M20.5 11.6a7.9 7.9 0 0 1-8.5 7.9 8.6 8.6 0 0 1-3-.6L4 20.5l1.6-4.7a7.7 7.7 0 0 1-1.1-4.2 7.9 7.9 0 0 1 8.5-7.9 8 8 0 0 1 7.5 7.9Z"/>',

        // Cloche — les notifications poussées
        'cloche' => '<path d="M18 8.5a6 6 0 1 0-12 0c0 6-2 7.5-2 7.5h16s-2-1.5-2-7.5Z"/>'
                  . '<path d="M13.7 20a2 2 0 0 1-3.4 0"/>',

        // Avion de papier — la diffusion
        'avion' => '<path d="M21 3 10.5 13.5"/><path d="M21 3 14.5 21l-4-7.5L3 9.5Z"/>',

        // Maillon — les liens courts
        'lien' => '<path d="M10 13.5a4 4 0 0 0 5.7.3l3-3a4 4 0 0 0-5.7-5.7l-1.7 1.7"/>'
                . '<path d="M14 10.5a4 4 0 0 0-5.7-.3l-3 3a4 4 0 0 0 5.7 5.7l1.7-1.7"/>',

        // Coche — ce que l'offre apporte
        'coche' => '<path d="M4.5 12.5 9.5 17.5 19.5 6.5"/>',

        // Croix — ce qu'elle n'apporte pas
        'croix' => '<path d="M6.5 6.5 17.5 17.5"/><path d="M17.5 6.5 6.5 17.5"/>',

        // Chevron — un groupe de liens qui se déplie
        'chevron' => '<path d="M6.5 9.5 12 15l5.5-5.5"/>',

        // Trois traits — le menu replié du mobile
        'menu' => '<path d="M4 7h16"/><path d="M4 12h16"/><path d="M4 17h16"/>',

        // Cadre et portrait — le Studio
        'studio' => '<rect x="3.2" y="3.2" width="17.6" height="17.6" rx="3"/>'
                  . '<circle cx="9" cy="9.5" r="1.9"/>'
                  . '<path d="M3.6 17.2 8.4 12.6a2 2 0 0 1 2.7-.1l5.1 4.7"/>'
                  . '<path d="M14.6 14.2l1.7-1.6a2 2 0 0 1 2.7 0l1.4 1.3"/>',
    ];

    $d = $chemins[$nom] ?? '';
    return $d === ''
        ? ''
        : '<svg viewBox="0 0 24 24" ' . $traits . ' aria-hidden="true" focusable="false">' . $d . '</svg>';
}

/**
 * Le logo Wakabi.
 *
 * C'est le fichier officiel de la marque, servi tel quel : rien n'est
 * redessiné ici. `public/logo.png` d'abord, `public/logo.svg` ensuite —
 * remplacer l'un ou l'autre suffit à changer le logo partout, sans toucher
 * une ligne de code. S'il manque, on retombe sur le nom écrit plutôt que
 * sur une approximation du dessin.
 */
function logo_fichier(): ?array
{
    foreach (['logo.png' => 'image/png', 'logo.svg' => 'image/svg+xml'] as $nom => $type) {
        if (is_file(RACINE . '/public/' . $nom)) {
            return ['url' => url('public/' . $nom), 'type' => $type];
        }
    }
    return null;
}

function logo_wakabi(string $classe = 'logo'): string
{
    $f = logo_fichier();
    return $f === null
        ? '<span class="' . e($classe) . '-texte">WAKABI</span>'
        : '<img class="' . e($classe) . '" src="' . e($f['url']) . '" alt="Wakabi Boost">';
}

/**
 * Les réseaux du guide, dessinés ici plutôt que chargés d'ailleurs.
 *
 * Le pied de page du site principal va chercher quatre PNG sur un
 * sous-domaine WordPress. Les recopier ici lierait ce paquet à un serveur
 * qu'il ne contrôle pas : le jour où ce sous-domaine bouge, quatre carrés
 * vides apparaissent dans le pied de page de toutes les installations.
 * Un tracé vectoriel ne pèse rien, ne se charge pas, et suit la couleur
 * du texte.
 */
const RESEAUX_WAKABI = [
    'facebook'  => ['Facebook',  'https://www.facebook.com/wakabileguide'],
    'instagram' => ['Instagram', 'https://www.instagram.com/wakabileguide'],
    'tiktok'    => ['TikTok',    'https://www.tiktok.com/@wakabileguide'],
    'youtube'   => ['YouTube',   'https://www.youtube.com/@WakabiLeGuideDesBonsCoins'],
];

function icone_reseau(string $nom): string
{
    $d = match ($nom) {
        'facebook' => 'M14 8.5h2V6h-2c-1.9 0-3.5 1.6-3.5 3.5V11H8.5v2.5h2V20H13v-6.5h2.2l.3-2.5H13V9.5c0-.55.45-1 1-1z',
        'instagram' => 'M8.5 3.5h7A5 5 0 0 1 20.5 8.5v7a5 5 0 0 1-5 5h-7a5 5 0 0 1-5-5v-7a5 5 0 0 1 5-5zm0 2a3 3 0 0 0-3 3v7a3 3 0 0 0 3 3h7a3 3 0 0 0 3-3v-7a3 3 0 0 0-3-3h-7zM12 7.75A4.25 4.25 0 1 1 7.75 12 4.25 4.25 0 0 1 12 7.75zm0 2A2.25 2.25 0 1 0 14.25 12 2.25 2.25 0 0 0 12 9.75zM16.8 6.6a1.1 1.1 0 1 1-1.1 1.1 1.1 1.1 0 0 1 1.1-1.1z',
        'tiktok' => 'M14.5 3h2.2a4.6 4.6 0 0 0 3.8 3.9v2.2a6.9 6.9 0 0 1-3.8-1.3v6.4a5.2 5.2 0 1 1-5.2-5.2c.24 0 .47.02.7.05v2.3a2.9 2.9 0 1 0 2.3 2.85z',
        'youtube' => 'M21.3 8.1a2.4 2.4 0 0 0-1.7-1.7C18.1 6 12 6 12 6s-6.1 0-7.6.4A2.4 2.4 0 0 0 2.7 8.1 25 25 0 0 0 2.3 12a25 25 0 0 0 .4 3.9 2.4 2.4 0 0 0 1.7 1.7C5.9 18 12 18 12 18s6.1 0 7.6-.4a2.4 2.4 0 0 0 1.7-1.7 25 25 0 0 0 .4-3.9 25 25 0 0 0-.4-3.9zM10.2 14.4V9.6l4.1 2.4z',
        default => '',
    };
    return $d === ''
        ? ''
        : '<svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false">'
          . '<path fill="currentColor" d="' . $d . '"/></svg>';
}
