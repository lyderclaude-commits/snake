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
