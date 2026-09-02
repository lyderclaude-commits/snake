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
 * Le cadrage écrit dans une adresse : `&c=x-y-l-h`.
 *
 * Les quatre nombres sont des MILLIÈMES de l'image d'origine, jamais des
 * pixels. C'est ce qui permet de recadrer une deuxième fois sans partir du
 * premier découpage : l'original ne bouge pas, le cadrage n'est qu'une
 * façon de le regarder. Recadrer serré puis vouloir revenir en arrière est
 * exactement ce qu'on fait dans la vraie vie ; avec des pixels cuits dans
 * le fichier, ce retour serait impossible.
 *
 * @return array{0:int,1:int,2:int,3:int}|null
 */
function cadrage_de_url(?string $url): ?array
{
    if (!preg_match('/[?&]c=(\d{1,4})-(\d{1,4})-(\d{1,4})-(\d{1,4})(?:$|&)/', (string) $url, $m)) {
        return null;
    }
    return cadrage_valide([(int) $m[1], (int) $m[2], (int) $m[3], (int) $m[4]]);
}

/** Un cadrage qui sort de l'image, ou trop petit pour être vu, n'en est pas un. */
function cadrage_valide(array $c): ?array
{
    [$x, $y, $l, $h] = array_map('intval', array_pad(array_slice($c, 0, 4), 4, 0));
    if ($l < 50 || $h < 50 || $x < 0 || $y < 0 || $x + $l > 1000 || $y + $h > 1000) {
        return null;
    }
    // Le cadrage plein n'en est pas un : il ne mérite ni fichier ni cache.
    return [$x, $y, $l, $h] === [0, 0, 1000, 1000] ? null : [$x, $y, $l, $h];
}

/** La largeur d'affichage écrite dans une adresse : `&t=NN`, en pourcents. */
function largeur_de_url(?string $url): int
{
    if (!preg_match('/[?&]t=(\d{1,3})(?:$|&)/', (string) $url, $m)) {
        return 100;
    }
    return max(20, min(100, (int) $m[1]));
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
        // Le cadrage entre dans la CLÉ : deux façons de regarder le même
        // fichier sont deux images, avec deux caches et deux dimensions.
        $c = cadrage_de_url($url);
        return 'm:' . $m[1] . ($c ? '!' . implode('-', $c) : '');
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
    $cadre = null;
    if (preg_match('/^(.*)!(\d{1,4})-(\d{1,4})-(\d{1,4})-(\d{1,4})$/', $cle, $m)) {
        $cle = $m[1];
        $cadre = cadrage_valide([(int) $m[2], (int) $m[3], (int) $m[4], (int) $m[5]]);
    }
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
    if (!is_file($c)) {
        return null;
    }
    return $cadre ? (image_cadree($c, $cadre) ?? $c) : $c;
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
        if (!is_file($c)) {
            return null;
        }
        // Le fichier RÉELLEMENT affiché : recadré, il n'a plus les mêmes
        // dimensions, et c'est de ces dimensions-là que la page a besoin
        // pour réserver la place — sinon elle saute au chargement.
        $cadre = cadrage_de_url($url);
        return $cadre ? (image_cadree($c, $cadre) ?? $c) : $c;
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
 * Découpe une image selon un cadrage, et rend le fichier obtenu.
 *
 * Le résultat est écrit UNE fois puis gardé, comme une vignette : la clé
 * porte la date de la source et les quatre nombres du cadrage, si bien
 * qu'un même découpage ne se recalcule jamais et qu'un fichier remplacé
 * se redécoupe tout seul.
 *
 * L'original n'est pas touché. C'est ce qui permet de rouvrir l'article
 * six mois plus tard et de RÉÉLARGIR le cadrage : recadrer n'a rien
 * détruit.
 */
function image_cadree(string $source, array $cadrage): ?string
{
    $c = cadrage_valide($cadrage);
    if ($c === null || !is_file($source)) {
        return null;
    }
    $ext = webp_disponible() ? 'webp' : 'png';
    $cle = substr(sha1($source . '|' . filemtime($source) . '|' . implode('-', $c)
                       . '|' . VIGNETTE_QUALITE), 0, 20);
    $cible = dossier_vignettes() . '/c' . $cle . '.' . $ext;
    if (is_file($cible)) {
        return $cible;
    }

    $image = image_ouvrir($source);
    if (!$image) {
        return null;
    }
    $l = imagesx($image);
    $h = imagesy($image);
    // Les millièmes deviennent des pixels ici, et nulle part ailleurs.
    $x = max(0, min($l - 1, (int) round($c[0] * $l / 1000)));
    $y = max(0, min($h - 1, (int) round($c[1] * $h / 1000)));
    $largeur = max(1, min($l - $x, (int) round($c[2] * $l / 1000)));
    $hauteur = max(1, min($h - $y, (int) round($c[3] * $h / 1000)));

    $coupe = imagecrop($image, ['x' => $x, 'y' => $y, 'width' => $largeur, 'height' => $hauteur]);
    imagedestroy($image);
    if (!$coupe) {
        return null;
    }
    // Comme partout ailleurs : la transparence d'un PNG survit au découpage.
    imagealphablending($coupe, false);
    imagesavealpha($coupe, true);
    $ok = $ext === 'webp'
        ? @imagewebp($coupe, $cible, VIGNETTE_QUALITE)
        : @imagepng($coupe, $cible, 9);
    imagedestroy($coupe);

    return $ok && is_file($cible) ? $cible : null;
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
 * Les médias cités par un texte : couverture, images d'article.
 *
 * @return string[] les noms de fichiers, sans doublon
 */
function medias_cites(string $texte): array
{
    preg_match_all('/[?&]f=([0-9a-f-]{36}\.(?:png|webp|jpg))/', $texte, $m);
    return array_values(array_unique($m[1] ?? []));
}

/**
 * Recompresse par lots les images des ARTICLES déjà en ligne.
 *
 * Même geste que pour les cadres, et pour la même raison : une couverture
 * téléversée avant que la compression n'existe pèse encore ses trois
 * mégaoctets, et personne n'ira la repasser à la main.
 *
 * Une différence, et elle compte : le nom du fichier peut changer
 * d'extension en cours de route. L'adresse est alors réécrite PARTOUT où
 * elle figure — la couverture, mais aussi le corps de l'article, où elle
 * est enfouie dans une marque. L'oublier laisserait des images mortes au
 * milieu du texte, et c'est le genre de dégât qu'on ne voit qu'en
 * relisant les vieux articles.
 */
function alleger_medias(int $lot = 12): array
{
    $bilan = ['traites' => 0, 'allegees' => 0, 'avant' => 0, 'apres' => 0, 'restants' => 0];

    $aFaire = [];
    foreach (db()->query('SELECT couverture, corps FROM articles')->fetchAll() as $a) {
        foreach (medias_cites((string) $a['couverture'] . "\n" . (string) $a['corps']) as $nom) {
            $chemin = dossier_medias() . '/' . $nom;
            // Le même marqueur que pour les cadres : recompresser deux fois
            // ne gagne rien et abîme un peu plus l'image à chaque tour.
            if (is_file($chemin) && !is_file($chemin . '.opt')) {
                $aFaire[$nom] = true;
            }
        }
    }
    $noms = array_keys($aFaire);
    $bilan['restants'] = max(0, count($noms) - $lot);

    foreach (array_slice($noms, 0, max(1, $lot)) as $nom) {
        $r = compresser_cadre(dossier_medias(), $nom);
        $bilan['traites']++;
        $bilan['avant'] += $r['avant'];
        $bilan['apres'] += $r['apres'];
        if ($r['apres'] < $r['avant']) {
            $bilan['allegees']++;
        }
        if ($r['nom'] !== $nom) {
            $s = db()->prepare('UPDATE articles SET couverture = REPLACE(couverture, ?, ?),
                                corps = REPLACE(corps, ?, ?)
                                WHERE couverture LIKE ? OR corps LIKE ?');
            $s->execute([$nom, $r['nom'], $nom, $r['nom'], '%' . $nom . '%', '%' . $nom . '%']);
        }
        @touch(dossier_medias() . '/' . $r['nom'] . '.opt');
    }

    return $bilan;
}

/**
 * Les deux passes, sous un seul bouton.
 *
 * Les cadres d'abord — ce sont les plus lourds — puis les images des
 * articles avec ce qui reste du lot. Un mutualisé coupe un script à trente
 * secondes : le lot est la seule chose qui garantisse qu'on rende la main.
 */
function alleger_images(int $lot = 12): array
{
    $b = alleger_cadres($lot);
    $m = alleger_medias(max(1, $lot - $b['traites']));
    foreach (['traites', 'allegees', 'avant', 'apres', 'restants'] as $c) {
        $b[$c] += $m[$c];
    }
    return $b;
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
