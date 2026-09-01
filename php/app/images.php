<?php
/**
 * Les images, servies au poids qu'elles doivent faire.
 *
 * Le catalogue affiche une douzaine de décors dans des vignettes de 300 px
 * de large. Sans ce fichier, chacune télécharge le cadre ENTIER — un PNG de
 * 1080 px et 140 Ko — pour l'afficher en timbre-poste. Une page du
 * catalogue coûtait ainsi près d'un mégaoctet, sur des connexions où le
 * mégaoctet se paie et se compte.
 *
 * Trois gestes, dans cet ordre d'importance :
 *
 *  1. **Redimensionner.** C'est de loin le plus gros gain : une image
 *     affichée en 300 px n'a aucune raison d'en faire 1080.
 *  2. **Changer de format.** WebP pèse deux à quatre fois moins qu'un PNG
 *     à qualité équivalente, transparence comprise. Il est lu par tout ce
 *     qui a moins de huit ans.
 *  3. **Recompresser à l'arrivée.** Un cadre téléversé depuis Canva sort
 *     souvent en PNG 24 bits de 3 Mo là où 200 Ko suffisent. On le réduit
 *     UNE fois, à l'envoi, plutôt qu'à chaque affichage.
 *
 * Tout est fabriqué à la demande puis gardé sur disque : la première
 * visite paie, les suivantes non. Rien n'est jamais recalculé tant que le
 * fichier source n'a pas changé — sa date de modification entre dans la
 * clé du cache.
 */

declare(strict_types=1);

/**
 * Les largeurs fabriquées. Volontairement peu nombreuses.
 *
 * Chaque largeur en plus est un fichier de plus à écrire et à garder. 320
 * couvre le mobile en une colonne, 640 le même écran en densité double et
 * les tablettes, 960 le grand écran. Au-delà, on sert l'original.
 */
const VIGNETTE_LARGEURS = [320, 640, 960];

/** La qualité WebP. 82 : le point où l'œil ne voit plus la différence. */
const VIGNETTE_QUALITE = 82;

/** Le plus grand côté d'un cadre gardé à l'envoi. Au-delà, c'est du gâchis. */
const CADRE_COTE_MAX = 1600;

function dossier_vignettes(): string
{
    $d = dossier_donnees() . '/vignettes';
    if (!is_dir($d)) {
        @mkdir($d, 0775, true);
    }
    return $d;
}

/** GD sait-il écrire du WebP ici ? Sur un mutualisé, ce n'est pas acquis. */
function webp_disponible(): bool
{
    return function_exists('imagewebp') && (gd_info()['WebP Support'] ?? false);
}

/**
 * Le nom que la route `?p=vignette` sait retraduire en chemin.
 *
 * Trois origines, trois préfixes : `c:` un cadre téléversé, `p:` un cadre
 * livré avec l'application, `m:` un média (couverture d'article). Un
 * préfixe plutôt qu'un chemin, parce qu'un nom de fichier venu de la
 * requête ne désigne jamais un chemin — ici pas plus qu'ailleurs.
 */
function cle_image(?string $url): ?string
{
    $url = (string) $url;
    if (preg_match('/[?&]p=media&f=([0-9a-f-]{36}\.(?:png|webp|jpg))(?:$|&)/', $url, $m)) {
        return 'm:' . $m[1];
    }
    if (preg_match('/[?&]f=([0-9a-f-]{36}\.(?:png|webp))(?:$|&)/', $url, $m)) {
        return 'c:' . $m[1];
    }
    if (preg_match('~public/cadres/([a-z0-9-]+\.(?:png|webp|jpg))$~i', $url, $m)) {
        return 'p:' . $m[1];
    }
    return null;
}

/** Et l'inverse, avec les mêmes motifs — c'est ce qui rend la route sûre. */
function image_de_la_cle(string $cle): ?string
{
    $dossier = match (substr($cle, 0, 2)) {
        'm:' => dossier_medias(),
        'c:' => dossier_cadres(),
        'p:' => RACINE . '/public/cadres',
        default => null,
    };
    if ($dossier === null || !preg_match('/^..([0-9a-z-]+\.(?:png|webp|jpg))$/i', $cle, $m)) {
        return null;
    }
    $c = $dossier . '/' . $m[1];
    return is_file($c) ? $c : null;
}

/**
 * Le fichier derrière une URL, quelle que soit son origine.
 *
 * `chemin_cadre()` sait déjà lire les deux formes d'un cadre ; on ajoute
 * ici les médias, et on garde une seule porte d'entrée pour l'appelant.
 */
function chemin_image(?string $url): ?string
{
    $url = (string) $url;
    if (preg_match('/[?&]p=media&f=([0-9a-f-]{36}\.(?:png|webp|jpg))(?:$|&)/', $url, $m)) {
        $c = dossier_medias() . '/' . $m[1];
        return is_file($c) ? $c : null;
    }
    return chemin_cadre($url);
}

/**
 * Ce qu'il faut mettre dans un `<img>` : la source, les tailles, et les
 * dimensions.
 *
 * Rend `src`, `srcset` et les dimensions. Les dimensions ne sont pas un
 * ornement : sans elles, la page saute au fur et à mesure que les images
 * arrivent, et quelqu'un qui lisait la deuxième ligne se retrouve à la
 * cinquième. Elles viennent du fichier, pas d'une supposition.
 *
 * @return array{src: string, srcset: string, largeur: int, hauteur: int}
 */
function image_reduite(?string $url, int $affichee = 320): array
{
    $defaut = ['src' => (string) $url, 'srcset' => '', 'largeur' => 0, 'hauteur' => 0];
    $cle = cle_image($url);
    $chemin = chemin_image($url);
    if (!$cle || !$chemin || !webp_disponible()) {
        // Pas de vignette possible : on sert l'original, en donnant au moins
        // ses dimensions si on sait les lire.
        if ($chemin && ($t = @getimagesize($chemin))) {
            $defaut['largeur'] = (int) $t[0];
            $defaut['hauteur'] = (int) $t[1];
        }
        return $defaut;
    }

    $taille = @getimagesize($chemin) ?: [0, 0];
    $source_l = max(1, (int) $taille[0]);
    $source_h = max(1, (int) $taille[1]);

    // On ne fabrique jamais plus grand que l'original : agrandir coûte des
    // octets pour une image plus floue.
    $largeurs = array_values(array_filter(
        VIGNETTE_LARGEURS,
        fn(int $l) => $l <= $source_l
    ));
    if (!$largeurs) {
        $largeurs = [$source_l];
    }

    $morceaux = [];
    foreach ($largeurs as $l) {
        $morceaux[] = url('?p=vignette&f=' . rawurlencode($cle) . '&l=' . $l) . ' ' . $l . 'w';
    }

    // `src` vise la largeur d'affichage : c'est ce que téléchargent les
    // navigateurs qui ignorent `srcset`, et le repli si `sizes` manque.
    $proche = $largeurs[0];
    foreach ($largeurs as $l) {
        if ($l >= $affichee) {
            $proche = $l;
            break;
        }
        $proche = $l;
    }

    return [
        'src' => url('?p=vignette&f=' . rawurlencode($cle) . '&l=' . $proche),
        'srcset' => implode(', ', $morceaux),
        'largeur' => $proche,
        'hauteur' => (int) round($proche * $source_h / $source_l),
    ];
}

/**
 * Fabrique (ou retrouve) la vignette d'un fichier, et rend son chemin.
 *
 * La date de modification de la source entre dans le nom du cache : un
 * cadre remplacé produit un nom différent, donc une vignette refaite, sans
 * qu'on ait à vider quoi que ce soit à la main.
 */
function vignette(string $source, int $largeur): ?string
{
    if (!webp_disponible() || !is_file($source)) {
        return null;
    }
    $largeur = max(16, min(2048, $largeur));
    $cle = substr(sha1($source . '|' . filemtime($source) . '|' . $largeur . '|' . VIGNETTE_QUALITE), 0, 20);
    $cible = dossier_vignettes() . '/' . $cle . '.webp';
    if (is_file($cible)) {
        return $cible;
    }

    $image = image_ouvrir($source);
    if (!$image) {
        return null;
    }
    $l = imagesx($image);
    $h = imagesy($image);
    if ($l <= $largeur) {
        // Déjà assez petite : on la recompresse quand même en WebP, c'est
        // le gain de format, gratuit.
        $reduite = $image;
    } else {
        $reduite = imagescale($image, $largeur, (int) round($h * $largeur / $l), IMG_BICUBIC);
        if (!$reduite) {
            imagedestroy($image);
            return null;
        }
    }

    // La transparence d'un cadre est TOUT ce qui fait un cadre : la perdre
    // donnerait un rectangle opaque par-dessus la photo de l'invité.
    imagealphablending($reduite, false);
    imagesavealpha($reduite, true);

    $ok = imagewebp($reduite, $cible, VIGNETTE_QUALITE);
    if ($reduite !== $image) {
        imagedestroy($reduite);
    }
    imagedestroy($image);

    return $ok && is_file($cible) ? $cible : null;
}

/** Ouvre une image quel que soit son format, ou rend `null`. */
function image_ouvrir(string $chemin): ?GdImage
{
    $t = @getimagesize($chemin);
    $im = match ($t[2] ?? 0) {
        IMAGETYPE_PNG => @imagecreatefrompng($chemin),
        IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($chemin) : false,
        IMAGETYPE_JPEG => @imagecreatefromjpeg($chemin),
        default => false,
    };
    if (!$im) {
        return null;
    }
    // Un PNG à palette casse `imagescale` en dents de scie : on repasse en
    // vraies couleurs avant tout redimensionnement.
    if (function_exists('imagepalettetotruecolor') && !imageistruecolor($im)) {
        imagepalettetotruecolor($im);
    }
    return $im;
}

/**
 * Recompresse un cadre à l'arrivée, et rend le nom réellement écrit.
 *
 * Appelé au téléversement, une fois. Deux choses s'y jouent :
 *
 *  - le cadre est ramené à `CADRE_COTE_MAX` : au-delà, l'export d'un badge
 *    n'y gagne rien, et l'invité paie le transfert ;
 *  - le PNG devient WebP SI le WebP est plus petit. La condition n'est pas
 *    de la prudence de façade : sur un cadre très plat — deux aplats et un
 *    trait — le PNG gagne parfois, et garder le plus lourd « parce que
 *    c'est le format moderne » serait absurde.
 *
 * En cas de pépin, le fichier d'origine est laissé tel quel : une image un
 * peu lourde vaut infiniment mieux qu'un cadre perdu.
 *
 * @return array{nom: string, avant: int, apres: int}
 */
function compresser_cadre(string $dossier, string $nom): array
{
    $chemin = $dossier . '/' . $nom;
    $avant = (int) @filesize($chemin);
    $image = image_ouvrir($chemin);
    if (!$image) {
        return ['nom' => $nom, 'avant' => $avant, 'apres' => $avant];
    }

    $l = imagesx($image);
    $h = imagesy($image);
    $cote = max($l, $h);
    if ($cote > CADRE_COTE_MAX) {
        $ratio = CADRE_COTE_MAX / $cote;
        $petit = imagescale($image, (int) round($l * $ratio), (int) round($h * $ratio), IMG_BICUBIC);
        if ($petit) {
            imagedestroy($image);
            $image = $petit;
        }
    }
    imagealphablending($image, false);
    imagesavealpha($image, true);

    $base = pathinfo($nom, PATHINFO_FILENAME);
    $essais = [];

    // Le PNG réécrit par GD est déjà plus petit que celui de bien des
    // outils : les métadonnées et les blocs de couleur inutiles sautent.
    $tmpPng = $dossier . '/.' . $base . '.essai.png';
    if (@imagepng($image, $tmpPng, 9)) {
        $essais['png'] = $tmpPng;
    }
    if (webp_disponible()) {
        $tmpWebp = $dossier . '/.' . $base . '.essai.webp';
        if (@imagewebp($image, $tmpWebp, VIGNETTE_QUALITE)) {
            $essais['webp'] = $tmpWebp;
        }
    }
    imagedestroy($image);

    if (!$essais) {
        return ['nom' => $nom, 'avant' => $avant, 'apres' => $avant];
    }

    // Le plus léger gagne — y compris l'original, s'il était déjà bon.
    $gagnant = null;
    $poids = $avant;
    foreach ($essais as $ext => $fichier) {
        $t = (int) filesize($fichier);
        if ($t > 0 && $t < $poids) {
            $poids = $t;
            $gagnant = [$ext, $fichier];
        }
    }

    if ($gagnant === null) {
        foreach ($essais as $f) {
            @unlink($f);
        }
        return ['nom' => $nom, 'avant' => $avant, 'apres' => $avant];
    }

    [$ext, $fichier] = $gagnant;
    $final = $base . '.' . $ext;
    @rename($fichier, $dossier . '/' . $final);
    foreach ($essais as $f) {
        @unlink($f);
    }
    if ($final !== $nom) {
        @unlink($chemin);
    }
    return ['nom' => $final, 'avant' => $avant, 'apres' => (int) @filesize($dossier . '/' . $final)];
}

/** Un poids lisible par un humain. */
function poids(int $octets): string
{
    return $octets >= 1024 * 1024
        ? number_format($octets / 1024 / 1024, 1, ',', ' ') . ' Mo'
        : max(1, (int) round($octets / 1024)) . ' Ko';
}

/**
 * Allège les cadres DÉJÀ en ligne, par petits lots.
 *
 * Les vignettes règlent le catalogue, mais pas le Studio : là, c'est le
 * cadre entier qui est chargé, parce que c'est lui qu'on dessine. Un
 * décor mis en ligne avant cette version porte donc encore son PNG de
 * 3 Mo, et l'invité le paie à chaque badge.
 *
 * Par LOTS parce qu'un mutualisé coupe un script à 30 secondes : traiter
 * cent cadres d'un coup finirait en page blanche à mi-parcours, avec la
 * moitié du travail faite et aucun moyen de savoir laquelle. On en fait
 * quelques-uns, on dit où on en est, et l'écran propose de continuer.
 *
 * @return array{traites: int, allegees: int, avant: int, apres: int, restants: int}
 */
function alleger_cadres(int $lot = 12): array
{
    $bilan = ['traites' => 0, 'allegees' => 0, 'avant' => 0, 'apres' => 0, 'restants' => 0];

    $s = db()->query("SELECT id, cadre_url FROM decors WHERE cadre_url LIKE '%p=cadre%'");
    $aFaire = [];
    foreach ($s->fetchAll() as $d) {
        if (!preg_match('/[?&]f=([0-9a-f-]{36}\.(?:png|webp))(?:$|&)/', (string) $d['cadre_url'], $m)) {
            continue;
        }
        $chemin = dossier_cadres() . '/' . $m[1];
        if (!is_file($chemin)) {
            continue;
        }
        /**
         * Le marqueur : un fichier vide à côté de celui qu'on a traité.
         *
         * Sans lui, chaque passage retraiterait les mêmes cadres — les
         * recompresser une seconde fois ne gagne rien et dégrade l'image
         * un peu plus à chaque tour. On ne peut pas s'appuyer sur le poids
         * pour deviner : un cadre déjà optimal ne change pas de taille, et
         * serait retraité indéfiniment.
         */
        if (is_file($chemin . '.opt')) {
            continue;
        }
        $aFaire[] = [$d['id'], $m[1], $chemin];
    }

    $bilan['restants'] = max(0, count($aFaire) - $lot);
    foreach (array_slice($aFaire, 0, max(1, $lot)) as [$id, $nom, $chemin]) {
        $r = compresser_cadre(dossier_cadres(), $nom);
        $bilan['traites']++;
        $bilan['avant'] += $r['avant'];
        $bilan['apres'] += $r['apres'];
        if ($r['apres'] < $r['avant']) {
            $bilan['allegees']++;
        }
        if ($r['nom'] !== $nom) {
            // L'extension a changé : l'adresse enregistrée doit suivre,
            // sinon le décor pointe vers un fichier qui n'existe plus.
            db()->prepare('UPDATE decors SET cadre_url = ?, maj_le = ? WHERE id = ?')
                ->execute([url('?p=cadre&f=' . $r['nom']), maintenant(), $id]);
        }
        @touch(dossier_cadres() . '/' . $r['nom'] . '.opt');
    }

    return $bilan;
}
