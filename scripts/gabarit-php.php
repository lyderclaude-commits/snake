<?php
/** Sort un gabarit produit par le PHP, en JSON — utilisé par verifier-gabarit.ts. */
require __DIR__ . '/../php/app/bootstrap.php';
require __DIR__ . '/../php/app/gabarit.php';
echo json_encode(construire_gabarit([
    'slug' => 'decor-de-controle',
    'titre' => 'Décor de contrôle',
    'sous_titre' => 'Vérification du contrat',
    'ville' => 'lome',
    'rubrique' => 'evenements',
    'disposition' => $argv[1] ?? 'bandeau',
    // La page blanche se passe de cadre : c'est justement la variante que le
    // schéma doit accepter, calque image en moins.
    'cadre_url' => ($argv[1] ?? '') === 'vierge' ? '' : 'https://wakabileguide.com/frames/jy-serai.png',
    'accroche' => 'J’Y SERAI',
    'champ_libelle' => 'Ton prénom',
    'champ_valeur' => 'Kossi',
    'redirection' => 'https://wakabileguide.com/p/test',
    'redirection_libelle' => '',
    'legende' => '',
    'expire_le' => '',
    'cree_par' => 'equipe',
]), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
