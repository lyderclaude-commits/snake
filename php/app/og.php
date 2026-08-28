<?php
/**
 * La vignette qu'un lien de décor montre quand on le colle dans WhatsApp.
 *
 * Sans elle, un lien partagé arrive nu : un titre gris et rien d'autre. Or
 * tout le produit repose sur ce partage — c'est la boucle qui remplit la
 * salle. Une vignette qui montre le badge fait la moitié du travail avant
 * même le clic.
 *
 * L'image est composée avec GD, la même extension que le pré-vol : rien à
 * installer, et la composition tient en trois gestes — le décor agrandi et
 * flouté en fond, le décor net par-dessus, la marque dans un coin.
 *
 * AUCUN TEXTE n'y est dessiné, volontairement. GD écrit avec FreeType, qui
 * réclame une police TrueType ; le projet n'embarque que des WOFF2 pour le
 * navigateur, et en ajouter une au zip pour trois mots serait cher payé.
 * Le titre, de toute façon, voyage déjà dans `og:title` et s'affiche à côté
 * de la vignette.
 */

declare(strict_types=1);

/** 1200 × 630 : le format que Facebook, WhatsApp et LinkedIn attendent. */
const OG_LARGEUR = 1200;
const OG_HAUTEUR = 630;

/** Le dossier des vignettes déjà calculées. */
function dossier_og(): string
{
    $d = dossier_donnees() . '/og';
    if (!is_dir($d)) {
        @mkdir($d, 0775, true);
    }
    return $d;
}

/**
 * L'adresse publique de la vignette d'un décor.
 *
 * `v` porte la date de dernière modification : changer le cadre change
 * l'adresse, donc le cache de WhatsApp — qui, sans cela, garderait
 * l'ancienne image pendant des semaines.
 */
function url_og(?array $decor = null): string
{
    if (!$decor) {
        return base_url() . '/index.php?p=og';
    }
    return base_url() . '/index.php?p=og&slug=' . rawurlencode((string) $decor['slug'])
         . '&v=' . substr(md5((string) ($decor['maj_le'] ?? $decor['cree_le'] ?? '')), 0, 8);
}

/**
 * Fabrique (ou relit) la vignette d'un décor. Rend le chemin du fichier.
 *
 * Rendre `null` n'est pas un échec silencieux : l'appelant retombe alors
 * sur la vignette générique, qui vaut mieux qu'un lien nu.
 */
function fichier_og(?array $decor): ?string
{
    $cle = $decor
        ? 'd-' . preg_replace('/[^a-z0-9-]/', '', (string) $decor['slug'])
          . '-' . substr(md5((string) ($decor['maj_le'] ?? '')), 0, 8)
        : 'accueil';
    $chemin = dossier_og() . '/' . $cle . '.jpg';
    if (is_file($chemin) && filesize($chemin) > 0) {
        return $chemin;
    }

    /**
     * Les vignettes périmées du même décor s'en vont.
     *
     * L'empreinte de la date de modification est dans le nom du fichier :
     * modifier un décor vingt fois laisserait vingt images derrière lui,
     * dont dix-neuf que plus rien ne désigne.
     */
    if ($decor) {
        // Le motif est vérifié au caractère près : « soiree » est un préfixe
        // de « soiree-2 », et un glob trop large effacerait la vignette du
        // voisin — qu'il faudrait alors recalculer sans savoir pourquoi.
        $attendu = '/^' . preg_quote(substr(basename($chemin), 0, -12), '/') . '[0-9a-f]{8}\.jpg$/';
        foreach (glob(dossier_og() . '/d-*.jpg') ?: [] as $vieux) {
            if ($vieux !== $chemin && preg_match($attendu, basename($vieux))) {
                @unlink($vieux);
            }
        }
    }

    $vignette = $decor ? og_decor($decor) : og_generique();
    if (!$vignette) {
        return null;
    }
    $ok = @imagejpeg($vignette, $chemin, 82);
    imagedestroy($vignette);
    return $ok ? $chemin : null;
}

/* ------------------------------------------------------------------ */
/* La composition                                                      */
/* ------------------------------------------------------------------ */

/** L'encre de la charte, en composantes — GD ne connaît pas les jetons CSS. */
const OG_ENCRE = [15, 23, 42];

/**
 * Le décor tel qu'on le verra : cadre par-dessus une photo d'exemple.
 *
 * La photo passe par la FENÊTRE déclarée dans le gabarit — celle que le
 * cadre laisse ouverte. C'est ce qui distingue une vignette d'un aplat :
 * on y reconnaît un badge, pas un cadre vide sur fond gris.
 */
function og_badge(array $decor): ?GdImage
{
    $g = json_lire((string) $decor['gabarit']);
    $largeur = (int) ($g['canvas']['width'] ?? 1080);
    $hauteur = (int) ($g['canvas']['height'] ?? 1080);
    if ($largeur < 1 || $hauteur < 1) {
        return null;
    }

    $toile = imagecreatetruecolor($largeur, $hauteur);
    imagealphablending($toile, true);
    imagefilledrectangle($toile, 0, 0, $largeur, $hauteur, imagecolorallocate($toile, ...OG_ENCRE));

    // La fenêtre photo, en pixels. Par défaut : tout le canevas.
    $zone = ['x' => 0.0, 'y' => 0.0, 'w' => 1.0, 'h' => 1.0];
    foreach ($g['layers'] ?? [] as $l) {
        if (($l['type'] ?? '') === 'photoSlot' && isset($l['rect'])) {
            $zone = $l['rect'];
        }
    }
    $zx = (int) round($zone['x'] * $largeur);
    $zy = (int) round($zone['y'] * $hauteur);
    $zw = max(1, (int) round($zone['w'] * $largeur));
    $zh = max(1, (int) round($zone['h'] * $hauteur));

    $masque = ['kind' => 'rect', 'radius' => 0];
    foreach ($g['layers'] ?? [] as $l) {
        if (($l['type'] ?? '') === 'photoSlot' && isset($l['mask'])) {
            $masque = $l['mask'];
        }
    }

    $photo = charger_image(RACINE . '/public/apercu-photo.webp');
    if ($photo) {
        og_couvrir($toile, $photo, $zx, $zy, $zw, $zh);
        og_masquer($toile, $zx, $zy, $zw, $zh, $masque, imagecolorallocate($toile, ...OG_ENCRE));
        imagedestroy($photo);
    }

    $cadre = chemin_cadre((string) ($decor['cadre_url'] ?? '')) ;
    if ($cadre && ($img = charger_image($cadre))) {
        imagecopyresampled($toile, $img, 0, 0, 0, 0, $largeur, $hauteur, imagesx($img), imagesy($img));
        imagedestroy($img);
    }

    og_qr($toile, $g, $decor);
    return $toile;
}

/**
 * Le QR, à la place exacte que le gabarit lui donne.
 *
 * Sans lui, la vignette montre un badge dont la bande du bas est vide : le
 * QR et les textes sont dessinés par le renderer, pas gravés dans le cadre.
 * Un badge à moitié fini donne l'impression d'un décor bâclé, alors qu'il
 * ne manquait qu'un carré. Celui-ci n'est pas un décor : il mène vraiment
 * à la page du décor, comme celui d'un vrai badge mène à son jeton.
 *
 * Les calculs répètent ceux de `drawQr` dans `src/core/renderScene.ts` —
 * même marge, même zone de silence, même arrondi — pour que la vignette
 * ressemble au badge, et non à une approximation.
 */
function og_qr(GdImage $toile, array $g, array $decor): void
{
    $cfg = $g['qr'] ?? [];
    if (!($cfg['enabled'] ?? false)) {
        return;
    }
    $W = imagesx($toile);
    $H = imagesy($toile);
    $taille = (int) round($W * (float) ($cfg['size'] ?? 0.16));
    $silence = (int) round($taille * 0.08);
    $boite = $taille + $silence * 2;
    $marge = (int) round(min($W, $H) * 0.04);

    $x = ($cfg['position'] ?? 'bottom-left') === 'top-right' ? $W - $boite - $marge : $marge;
    $y = ($cfg['position'] ?? 'bottom-left') === 'bottom-left' ? $H - $boite - $marge : $marge;

    $blanc = imagecolorallocate($toile, 255, 255, 255);
    $sombre = imagecolorallocate($toile, ...OG_ENCRE);
    imagefilledrectangle($toile, $x, $y, $x + $boite, $y + $boite, $blanc);

    $m = Qr::matrix(base_url() . '/index.php?p=decor&slug=' . rawurlencode((string) $decor['slug']));
    $modules = count($m);
    if ($modules < 1) {
        return;
    }
    // Le pas est fractionnaire : arrondir chaque bord plutôt que le pas
    // évite d'accumuler l'erreur et de laisser une bande blanche à droite.
    for ($r = 0; $r < $modules; $r++) {
        for ($c = 0; $c < $modules; $c++) {
            if ($m[$r][$c] !== 1) {
                continue;
            }
            imagefilledrectangle(
                $toile,
                $x + $silence + (int) round($c * $taille / $modules),
                $y + $silence + (int) round($r * $taille / $modules),
                $x + $silence + (int) round(($c + 1) * $taille / $modules) - 1,
                $y + $silence + (int) round(($r + 1) * $taille / $modules) - 1,
                $sombre
            );
        }
    }
}

/**
 * Rend à la zone la forme que le gabarit lui donne : arrondie, ou ronde.
 *
 * GD n'a pas de découpe : on repeint donc ce qui DÉBORDE de la forme avec
 * la couleur de fond du décor. Le coût suit la surface repeinte, pas celle
 * de la photo — quatre petits coins pour un arrondi.
 *
 * Sans cette étape, une fenêtre ronde arriverait carrée dans la vignette,
 * alors qu'elle sera ronde dans le badge : le lien montrerait autre chose
 * que ce qu'on obtient, et c'est précisément ce qu'un aperçu ne doit pas
 * faire.
 */
function og_masquer(GdImage $toile, int $x, int $y, int $w, int $h, array $masque, int $fond): void
{
    $rond = ($masque['kind'] ?? 'rect') === 'circle';
    $rayon = $rond
        ? (int) round(min($w, $h) / 2)
        : (int) round((float) ($masque['radius'] ?? 0) * min($w, $h));
    if ($rayon < 1) {
        return;
    }

    if ($rond) {
        $cx = $x + $w / 2;
        $cy = $y + $h / 2;
        for ($j = $y; $j < $y + $h; $j++) {
            for ($i = $x; $i < $x + $w; $i++) {
                if (($i + 0.5 - $cx) ** 2 + ($j + 0.5 - $cy) ** 2 > $rayon ** 2) {
                    imagesetpixel($toile, $i, $j, $fond);
                }
            }
        }
        return;
    }

    // Coins arrondis : seuls les quatre carrés d'angle sont concernés.
    foreach ([[0, 0, 1, 1], [1, 0, -1, 1], [0, 1, 1, -1], [1, 1, -1, -1]] as [$cx0, $cy0, $sx, $sy]) {
        $ox = $x + $cx0 * ($w - 1);
        $oy = $y + $cy0 * ($h - 1);
        for ($d = 0; $d < $rayon; $d++) {
            for ($e = 0; $e < $rayon; $e++) {
                if (($rayon - $d - 0.5) ** 2 + ($rayon - $e - 0.5) ** 2 > $rayon ** 2) {
                    imagesetpixel($toile, (int) ($ox + $sx * $d), (int) ($oy + $sy * $e), $fond);
                }
            }
        }
    }
}

/** Dessine `$src` dans la zone en la recadrant comme « cover ». */
function og_couvrir(GdImage $dest, GdImage $src, int $x, int $y, int $w, int $h): void
{
    $sw = imagesx($src);
    $sh = imagesy($src);
    $echelle = max($w / $sw, $h / $sh);
    $pw = (int) round($w / $echelle);
    $ph = (int) round($h / $echelle);
    imagecopyresampled($dest, $src, $x, $y, (int) round(($sw - $pw) / 2), (int) round(($sh - $ph) / 2), $w, $h, $pw, $ph);
}

/**
 * La vignette : le badge net, sur lui-même agrandi et flouté.
 *
 * Un badge carré posé sur un rectangle 1200 × 630 laisse deux grandes
 * marges. Les remplir d'un aplat donnerait une image morte ; les remplir du
 * décor lui-même, flouté et assombri, donne une vignette qui a l'air
 * composée — et qui reste juste, puisque tout ce qu'on y voit vient du
 * décor.
 */
function og_decor(array $decor): ?GdImage
{
    $badge = og_badge($decor);
    if (!$badge) {
        return null;
    }
    $toile = og_fond($badge);

    // Le badge net, aussi grand que la hauteur le permet.
    $marge = 34;
    $bh = OG_HAUTEUR - 2 * $marge;
    $bw = (int) round($bh * imagesx($badge) / imagesy($badge));
    if ($bw > OG_LARGEUR - 2 * $marge) {
        $bw = OG_LARGEUR - 2 * $marge;
        $bh = (int) round($bw * imagesy($badge) / imagesx($badge));
    }
    $bx = (int) round((OG_LARGEUR - $bw) / 2);
    $by = (int) round((OG_HAUTEUR - $bh) / 2);

    // Un liseré clair détache le badge du fond : sans lui, un décor sombre
    // se fond dans son propre flou.
    imagefilledrectangle($toile, $bx - 2, $by - 2, $bx + $bw + 1, $by + $bh + 1,
        imagecolorallocate($toile, 255, 255, 255));
    imagecopyresampled($toile, $badge, $bx, $by, 0, 0, $bw, $bh, imagesx($badge), imagesy($badge));
    imagedestroy($badge);

    og_marque($toile);
    return $toile;
}

/** Le fond : la source, recadrée en 1200 × 630, floutée et assombrie. */
function og_fond(GdImage $source): GdImage
{
    $toile = imagecreatetruecolor(OG_LARGEUR, OG_HAUTEUR);
    imagealphablending($toile, true);
    imagefilledrectangle($toile, 0, 0, OG_LARGEUR, OG_HAUTEUR, imagecolorallocate($toile, ...OG_ENCRE));

    // Flouter en petit puis agrandir : le filtre de GD ne floute que d'un
    // pixel de rayon, donc trente passes sur une grande image coûteraient
    // cher pour un résultat encore net. Réduire d'abord fait le gros du
    // travail gratuitement.
    $pw = 120;
    $ph = (int) round(OG_HAUTEUR * $pw / OG_LARGEUR);
    $petit = imagecreatetruecolor($pw, $ph);
    og_couvrir($petit, $source, 0, 0, $pw, $ph);
    for ($i = 0; $i < 6; $i++) {
        imagefilter($petit, IMG_FILTER_GAUSSIAN_BLUR);
    }
    imagecopyresampled($toile, $petit, 0, 0, 0, 0, OG_LARGEUR, OG_HAUTEUR, $pw, $ph);
    imagedestroy($petit);

    // Assombrir : le badge doit rester le sujet.
    $voile = imagecreatetruecolor(OG_LARGEUR, OG_HAUTEUR);
    imagefilledrectangle($voile, 0, 0, OG_LARGEUR, OG_HAUTEUR, imagecolorallocate($voile, ...OG_ENCRE));
    imagecopymerge($toile, $voile, 0, 0, 0, 0, OG_LARGEUR, OG_HAUTEUR, 55);
    imagedestroy($voile);

    return $toile;
}

/** Le logo, en bas à droite, discret. */
function og_marque(GdImage $toile): void
{
    $chemin = RACINE . '/public/logo.png';
    if (!is_file($chemin) || !($logo = charger_image($chemin))) {
        return;
    }
    $lh = 46;
    $lw = (int) round($lh * imagesx($logo) / imagesy($logo));
    imagealphablending($toile, true);
    imagecopyresampled($toile, $logo, OG_LARGEUR - $lw - 30, OG_HAUTEUR - $lh - 26, 0, 0,
                       $lw, $lh, imagesx($logo), imagesy($logo));
    imagedestroy($logo);
}

/**
 * La vignette du site, pour l'accueil et tout ce qui n'est pas un décor.
 *
 * Bâtie sur le premier décor publié plutôt que sur un visuel figé : elle
 * suit ainsi les campagnes en cours, et une installation neuve n'affiche
 * pas la soirée de l'an dernier.
 */
function og_generique(): ?GdImage
{
    $publies = decors_publies();
    if ($publies) {
        $img = og_decor($publies[0]);
        if ($img) {
            return $img;
        }
    }
    $toile = imagecreatetruecolor(OG_LARGEUR, OG_HAUTEUR);
    imagefilledrectangle($toile, 0, 0, OG_LARGEUR, OG_HAUTEUR, imagecolorallocate($toile, ...OG_ENCRE));
    og_marque($toile);
    return $toile;
}
