<?php
/**
 * Sert un cadre téléversé.
 *
 * Les fichiers vivent hors de la racine web : ils ne sont donc jamais servis
 * directement par le serveur, et un nom de fichier contrôlé ici est la seule
 * voie d'accès. Le motif exclut toute traversée de chemin.
 */
$nom = (string) ($_GET['f'] ?? '');
if (!preg_match('/^[0-9a-f-]{36}\.(png|webp)$/', $nom)) {
    http_response_code(404);
    exit('Introuvable');
}
$chemin = dossier_cadres() . '/' . $nom;
if (!is_file($chemin)) {
    http_response_code(404);
    exit('Introuvable');
}
$type = str_ends_with($nom, '.png') ? 'image/png' : 'image/webp';
header('Content-Type: ' . $type);
header('Content-Length: ' . filesize($chemin));
// Le nom contient un identifiant unique : le contenu ne change jamais.
header('Cache-Control: public, max-age=31536000, immutable');
readfile($chemin);
exit;
