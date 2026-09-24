<?php
/**
 * D'où part votre décor ?
 *
 * L'étape « Le cadre » du Studio mélangeait trois choses : choisir un
 * modèle, téléverser son propre fichier, ou partir d'un cadre fourni. Deux
 * métiers se disputaient le même panneau. Quelqu'un dont le graphiste a
 * rendu un PNG n'a que faire d'une galerie de modèles ; quelqu'un qui part
 * de rien n'a pas de fichier à déposer et perd du temps à se demander
 * lequel on lui réclame.
 *
 * Un écran, deux portes, et le Studio s'ouvre déjà dans le bon mode. Les
 * cadres fournis par Wakabi restent des deux côtés : ce ne sont pas des
 * fichiers apportés du dehors, et s'en priver appauvrirait les deux
 * chemins sans rien protéger.
 *
 * Publique : on compose d'abord, on se présente au moment de publier.
 */

declare(strict_types=1);

vue('creer', [
    'titre' => 'Créer un décor · ' . seo_reglage('seo_nom_site'),
    'description' => 'Déposez le décor que vous avez déjà, ou composez-le dans le Studio. '
        . 'Sans compte pour commencer. Lomé, Cotonou, Abidjan.',
    'moi' => utilisateur_courant(),
]);
