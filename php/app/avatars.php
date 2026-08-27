<?php
/**
 * Portraits dessinés.
 *
 * Les témoignages de la vitrine sont des personnages, pas des clients
 * identifiables : leur coller une photographie ferait passer une fiction
 * pour une caution réelle. Ce sont donc des portraits dessinés, un par
 * personne, tracés ici en SVG — nets à toutes les tailles, sans une seule
 * requête réseau, et sans le visage de quelqu'un qui n'a rien signé.
 */

declare(strict_types=1);

/**
 * @param string $qui  clé du portrait : kofi, aicha, emmanuel, invitee
 */
function avatar(string $qui, int $taille = 44): string
{
    $p = portraits()[$qui] ?? portraits()['kofi'];

    return '<svg class="avatar" viewBox="0 0 100 100" width="' . $taille . '" height="' . $taille . '"'
         . ' role="img" aria-hidden="true" focusable="false">'
         . '<defs><clipPath id="rond-' . $qui . '"><circle cx="50" cy="50" r="50"/></clipPath></defs>'
         . '<g clip-path="url(#rond-' . $qui . ')">'
         . '<rect width="100" height="100" fill="' . $p['fond'] . '"/>'
         . $p['corps']
         . '</g></svg>';
}

/** Le tracé commun : épaules, cou, tête, oreilles. Les cheveux varient. */
function silhouette(string $peau, string $ombre, string $habit, string $col): string
{
    return
        // épaules et buste
        '<path d="M9 100c0-19 17.5-30 41-30s41 11 41 30Z" fill="' . $habit . '"/>'
        // encolure
        . '<path d="M38 72.5c3.5 5.5 20.5 5.5 24 0l-4-2.6c-2.5 3.4-13.5 3.4-16 0Z" fill="' . $col . '"/>'
        // cou
        . '<path d="M42 55h16v14c0 4.5-16 4.5-16 0Z" fill="' . $ombre . '"/>'
        // oreilles
        . '<ellipse cx="31.6" cy="45" rx="3.6" ry="4.6" fill="' . $peau . '"/>'
        . '<ellipse cx="68.4" cy="45" rx="3.6" ry="4.6" fill="' . $peau . '"/>'
        // visage
        . '<path d="M50 22c10.4 0 17.5 7.6 17.5 18.5S60.4 62 50 62 32.5 51.4 32.5 40.5 39.6 22 50 22Z" fill="' . $peau . '"/>';
}

/** Deux yeux et un sourire : le minimum pour qu'un visage regarde. */
function traits_visage(string $encre = '#1F2937'): string
{
    return '<ellipse cx="43" cy="42" rx="1.9" ry="2.3" fill="' . $encre . '"/>'
         . '<ellipse cx="57" cy="42" rx="1.9" ry="2.3" fill="' . $encre . '"/>'
         . '<path d="M45 50.5c2.6 2.2 7.4 2.2 10 0" fill="none" stroke="' . $encre
         . '" stroke-width="1.7" stroke-linecap="round" opacity=".75"/>';
}

function portraits(): array
{
    static $p = null;
    if ($p !== null) {
        return $p;
    }

    return $p = [

        // Kofi — coupe courte, barbe taillée, chemise bleue.
        'kofi' => [
            'fond' => '#DCE7FB',
            'corps' =>
                silhouette('#8A5A32', '#75491F', '#2563EB', '#1D4ED8')
                // cheveux ras
                . '<path d="M32.6 39c-.6-11.5 7.4-18.5 17.4-18.5S68 27.5 67.4 39c-2.4-6.2-8.4-8.6-17.4-8.6S35 32.8 32.6 39Z" fill="#241812"/>'
                // barbe taillée : une bande le long de la mâchoire, le sourire dégagé
                . '<path d="M33.4 41c-.5 3.4 0 6.8 1.4 10.1C37.4 57.6 43.2 62 50 62s12.6-4.4 15.2-10.9c1.4-3.3 1.9-6.7 1.4-10.1-.6 5.5-2.6 8.2-5.4 9.2-1.2 4.3-5.6 7-11.2 7s-10-2.7-11.2-7c-2.8-1-4.8-3.7-5.4-9.2Z" fill="#241812"/>'
                . traits_visage(),
        ],

        // Aïcha — foulard noué, boucles d'oreilles, veste orange.
        'aicha' => [
            'fond' => '#FDE8D3',
            'corps' =>
                silhouette('#7A4A28', '#653A1C', '#F97316', '#EA6A0C')
                // boucles d'oreilles
                . '<circle cx="31" cy="51" r="2.6" fill="#D97706"/><circle cx="69" cy="51" r="2.6" fill="#D97706"/>'
                // foulard noué : la coiffe, le pli du bandeau, le nœud sur le côté
                . '<path d="M30.5 43.5C29 30 38 20 50 20s21 10 19.5 23.5c-1.2-6.4-2.8-9.6-5.2-11.8-3.6 2.6-8.4 3.8-14.3 3.8s-10.7-1.2-14.3-3.8c-2.4 2.2-4 5.4-5.2 11.8Z" fill="#0D9488"/>'
                . '<path d="M35.4 30.6c3.8 2.8 8.8 4.1 14.6 4.1s10.8-1.3 14.6-4.1c1 .9 1.9 2 2.6 3.4-4.4 3.2-10.2 4.7-17.2 4.7s-12.8-1.5-17.2-4.7c.7-1.4 1.6-2.5 2.6-3.4Z" fill="#0F766E"/>'
                . '<path d="M65.5 22.5c4.8-2.2 9.6-.6 10.6 3.2.9 3.4-1.9 6.4-6.1 6.9 2.6 2.6 3.6 6 2.4 8.8-1.3 3-4.6 4.3-7.9 3.2 2.4-6.6 2.7-15.2 1-22.1Z" fill="#0F766E"/>'
                . traits_visage(),
        ],

        // Emmanuel — crâne rasé, lunettes, polo vert.
        'emmanuel' => [
            'fond' => '#D9F2EA',
            'corps' =>
                silhouette('#5E3620', '#4B2915', '#0D9488', '#0B7C72')
                . '<path d="M33 38c1.5-10.5 8.5-16 17-16s15.5 5.5 17 16c-3-5.5-9-8-17-8s-14 2.5-17 8Z" fill="#1A1310"/>'
                . traits_visage('#111827')
                // lunettes
                . '<g fill="none" stroke="#1F2937" stroke-width="1.6" opacity=".9">'
                . '<circle cx="43" cy="42" r="5.4"/><circle cx="57" cy="42" r="5.4"/>'
                . '<path d="M48.4 42h3.2"/><path d="M37.6 41.4 33 42.4"/><path d="M62.4 41.4 67 42.4"/></g>',
        ],

        // L'invitée du hero : c'est elle qu'on voit fabriquer son badge.
        'invitee' => [
            'fond' => '#EFF4FE',
            'corps' =>
                silhouette('#93603A', '#7C4B27', '#2563EB', '#1D4ED8')
                // tresses relevées
                . '<path d="M31 44c-1.5-14 8-23.5 19-23.5S70.5 30 69 44c-2-6-4.5-9-8-11-3 2.5-6.5 3.5-11 3.5s-8-1-11-3.5c-3.5 2-6 5-8 11Z" fill="#2B1B12"/>'
                . '<path d="M50 12c7 0 12 3.5 12 8.5 0 3-2 5-5 5.5-2-3-4-4.5-7-4.5s-5 1.5-7 4.5c-3-.5-5-2.5-5-5.5 0-5 5-8.5 12-8.5Z" fill="#2B1B12"/>'
                . '<circle cx="27" cy="52" r="4.2" fill="#2B1B12"/><circle cx="73" cy="52" r="4.2" fill="#2B1B12"/>'
                . traits_visage(),
        ],
    ];
}
