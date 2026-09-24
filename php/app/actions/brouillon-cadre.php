<?php
/**
 * Sert un cadre déposé sans compte, à celui qui l'a déposé.
 *
 * Le Studio s'ouvre désormais aux visiteurs, et l'aperçu doit montrer le
 * cadre qu'on vient de choisir : sans cela, on compose à l'aveugle et l'on
 * découvre son décor après avoir créé un compte. Le fichier ne peut pas
 * pour autant rejoindre `donnees/cadres/`, qui sert les décors publiés :
 * il n'appartient encore à personne.
 *
 * D'où cette porte, étroite par construction. Le nom est tiré au sort sur
 * seize octets, mais « impossible à deviner » n'est pas une autorisation :
 * c'est le jeton du cookie qui dit à qui le fichier est.
 */

declare(strict_types=1);

$nom = (string) ($_GET['f'] ?? '');

if (!brouillon_fichier_permis($nom)) {
    http_response_code(404);
    exit('Introuvable');
}

$chemin = dossier_brouillons() . '/' . $nom;
if (!is_file($chemin)) {
    http_response_code(404);
    exit('Introuvable');
}

header('Content-Type: ' . (str_ends_with($nom, '.png') ? 'image/png' : 'image/webp'));
header('Content-Length: ' . filesize($chemin));
/**
 * Jamais de cache partagé.
 *
 * Le fichier est servi selon un cookie : un intermédiaire qui le garderait
 * le rendrait au visiteur suivant. `private` le réserve au navigateur qui
 * l'a demandé, et la durée reste courte parce que le fichier lui-même ne
 * vit que quarante-huit heures.
 */
header('Cache-Control: private, max-age=600');
readfile($chemin);
exit;
