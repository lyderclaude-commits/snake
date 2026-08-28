<?php
/**
 * Le pré-vol — sept contrôles automatiques avant la file de relecture.
 *
 * Ils s'exécutent CÔTÉ SERVEUR, avec GD : un partenaire ne peut donc pas les
 * contourner depuis son navigateur. Un décor qui échoue ne rejoint jamais la
 * file, ce qui permet de tenir l'engagement des 24 h — le relecteur ne voit
 * que des décors valides.
 *
 * Port de src/server/preflight.ts, sharp remplacé par GD.
 */

declare(strict_types=1);

/** Au-delà, la photo de l'invité serait invisible sous le cadre. */
const OPACITE_LIMITE = 0.85;
const POIDS_MAX = 800 * 1024;

/**
 * Retrouve le fichier réel derrière une URL de cadre.
 *
 * Deux formes coexistent, et il faut les deux : un cadre téléversé est servi
 * par `?p=cadre&f=<uuid>.png` — son nom est dans la CHAÎNE DE REQUÊTE, pas
 * dans le chemin — tandis qu'un cadre livré avec l'application est un fichier
 * ordinaire sous /public/cadres/. Ne lire que le chemin fait échouer toutes
 * les soumissions de partenaires avec « aucun cadre exploitable ».
 */
function chemin_cadre(?string $url): ?string
{
    if (!$url) {
        return null;
    }

    // 1. cadre téléversé : le nom vit dans le paramètre `f`
    $requete = parse_url($url, PHP_URL_QUERY);
    if ($requete) {
        parse_str($requete, $params);
        $nom = (string) ($params['f'] ?? '');
        if (preg_match('/^[0-9a-f-]{36}\.(png|webp)$/', $nom)) {
            $chemin = dossier_cadres() . '/' . $nom;
            return is_file($chemin) ? $chemin : null;
        }
    }

    // 2. cadre livré avec l'application
    $nom = basename(parse_url($url, PHP_URL_PATH) ?: $url);
    if ($nom === '' || str_contains($nom, '..')) {
        return null;
    }
    $livre = RACINE . '/public/cadres/' . $nom;
    return is_file($livre) ? $livre : null;
}

function charger_image(string $chemin): ?GdImage
{
    $info = @getimagesize($chemin);
    if (!$info) {
        return null;
    }
    return match ($info[2]) {
        IMAGETYPE_PNG => @imagecreatefrompng($chemin) ?: null,
        IMAGETYPE_WEBP => @imagecreatefromwebp($chemin) ?: null,
        default => null,
    };
}

/**
 * Part opaque d'une région du cadre.
 *
 * On lit le canal alpha : c'est lui qui dit si la photo passera au travers.
 * L'échantillonnage sur une grille de 64×64 suffit — on cherche un ordre de
 * grandeur, pas une mesure au pixel près, et un cadre fait 1080 px de côté.
 */
function part_opaque(GdImage $img, float $x, float $y, float $w, float $h): float
{
    $lw = imagesx($img);
    $lh = imagesy($img);
    $x0 = (int) round($x * $lw);
    $y0 = (int) round($y * $lh);
    $x1 = min($lw, (int) round(($x + $w) * $lw));
    $y1 = min($lh, (int) round(($y + $h) * $lh));
    if ($x1 <= $x0 || $y1 <= $y0) {
        return 0.0;
    }

    $pas_x = max(1, intdiv($x1 - $x0, 64));
    $pas_y = max(1, intdiv($y1 - $y0, 64));
    $total = 0;
    $opaques = 0;

    for ($py = $y0; $py < $y1; $py += $pas_y) {
        for ($px = $x0; $px < $x1; $px += $pas_x) {
            $c = imagecolorat($img, $px, $py);
            // GD : 0 = opaque, 127 = totalement transparent.
            $alpha = ($c >> 24) & 0x7F;
            $total++;
            if ($alpha < 32) {
                $opaques++;
            }
        }
    }
    return $total ? $opaques / $total : 0.0;
}

/** Deux rectangles normalisés se chevauchent-ils ? */
function se_chevauchent(array $a, array $b): bool
{
    return $a['x'] < $b['x'] + $b['w']
        && $a['x'] + $a['w'] > $b['x']
        && $a['y'] < $b['y'] + $b['h']
        && $a['y'] + $a['h'] > $b['y'];
}

/**
 * Lance les sept contrôles. Renvoie le rapport complet, pas seulement un
 * verdict : le relecteur doit voir POURQUOI un décor est passé.
 */
/**
 * Où le QR et le filigrane se posent réellement, d'après le gabarit.
 *
 * Ces deux zones étaient codées en dur en bas à gauche et en bas à droite.
 * Depuis que leur coin se règle, les figer revenait à contrôler la collision
 * ailleurs que là où elle se produit : un texte passait sous un QR déplacé
 * sans que rien ne le signale.
 *
 * Les marges reprennent celles du renderer : 4 % du plus petit côté, et une
 * zone de silence de 8 % autour du QR.
 */
function zones_reservees(array $gabarit): array
{
    $w = (float) ($gabarit['canvas']['width'] ?? 1080);
    $h = (float) ($gabarit['canvas']['height'] ?? 1080);
    $rapport = $h > 0 ? $w / $h : 1.0;

    // Le renderer prend sa marge sur le PLUS PETIT côté : en normalisé, elle
    // ne vaut donc pas la même chose sur les deux axes.
    $marge_px = 0.04 * min($w, $h);
    $marge_x = $marge_px / $w;
    $marge_y = $marge_px / $h;

    /**
     * Le QR est dimensionné sur la LARGEUR, zone de silence comprise.
     *
     * Sa hauteur normalisée dépend donc du format : un sixième de la hauteur
     * en carré, un tiers en paysage. Le supposer carré refusait des décors
     * 4:5 et 9:16 parfaitement valides.
     */
    $boite_x = (float) ($gabarit['qr']['size'] ?? 0.16) * 1.16;
    $boite_y = $boite_x * $rapport;
    $position_qr = (string) ($gabarit['qr']['position'] ?? 'bottom-left');
    $zone_qr = [
        'x' => $position_qr === 'top-right' ? 1 - $boite_x - $marge_x : $marge_x,
        'y' => $position_qr === 'bottom-left' ? 1 - $boite_y - $marge_y : $marge_y,
        'w' => $boite_x,
        'h' => $boite_y,
    ];

    // Le filigrane : 21 % de la largeur, et une hauteur d'environ 0,39 de sa
    // propre largeur — la même conversion s'applique.
    $largeur_filigrane = 0.21;
    $hauteur_filigrane = $largeur_filigrane * 0.42 * $rapport;
    $position_filigrane = (string) ($gabarit['watermark']['position'] ?? 'bottom-right');
    $x_filigrane = match ($position_filigrane) {
        'bottom-left' => $marge_x,
        'bottom-center' => (1 - $largeur_filigrane) / 2,
        default => 1 - $largeur_filigrane - $marge_x,
    };

    return [
        'qr' => $zone_qr,
        'filigrane' => [
            'x' => $x_filigrane,
            'y' => 1 - $hauteur_filigrane - $marge_y,
            'w' => $largeur_filigrane,
            'h' => $hauteur_filigrane,
        ],
    ];
}

function prevol(array $gabarit, ?string $cadre_url): array
{
    $controles = [];
    $ajouter = function (string $id, string $etat, string $message) use (&$controles): void {
        $controles[] = ['id' => $id, 'etat' => $etat, 'message' => $message];
    };

    /* 1 — le contrat */
    try {
        valider_gabarit($gabarit);
        $ajouter('schema', 'ok', 'Le gabarit respecte le contrat de données.');
    } catch (GabaritInvalide $e) {
        $ajouter('schema', 'echec', $e->getMessage());
        return ['passe' => false, 'controles' => $controles];
    }

    /**
     * Un décor peut n'avoir AUCUN cadre.
     *
     * C'est le cas de la page blanche : son décor tient au fond, à la forme
     * de la fenêtre photo et au texte. Les trois contrôles qui inspectent le
     * fichier n'ont alors rien à inspecter — mais les trois suivants, eux,
     * comptent toujours, et c'est là que la page blanche peut se tromper.
     */
    $sans_cadre = !array_filter(
        $gabarit['layers'] ?? [],
        fn($l) => ($l['type'] ?? '') === 'image' && ($l['id'] ?? '') === 'frame'
    );

    $img = null;
    if ($sans_cadre) {
        $ajouter('format', 'ok', 'Aucun cadre : le décor tient au fond, à la fenêtre photo et au texte.');
        $ajouter('poids', 'ok', 'Rien à charger en plus de la photo de l’invité.');
        $ajouter('photo-visible', 'ok', 'La photo est entièrement visible : aucun cadre ne la recouvre.');
    } else {
        $chemin = chemin_cadre($cadre_url);
        if (!$chemin || !is_file($chemin)) {
            $ajouter('format', 'echec', 'Aucun cadre exploitable n’est attaché à ce décor.');
            return ['passe' => false, 'controles' => $controles];
        }

        /* 2 — format du fichier */
        $info = @getimagesize($chemin);
        if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            $ajouter('format', 'echec', 'Le cadre doit être un PNG ou un WebP. Le SVG est refusé pour raison de sécurité.');
            return ['passe' => false, 'controles' => $controles];
        }
        $img = charger_image($chemin);
        if (!$img) {
            $ajouter('format', 'echec', 'Le fichier du cadre est illisible ou corrompu.');
            return ['passe' => false, 'controles' => $controles];
        }
        imagealphablending($img, false);
        imagesavealpha($img, true);
        /**
         * Le cadre et le canevas doivent avoir les mêmes proportions.
         *
         * Le renderer étire le cadre sur tout le canevas : une affiche 4:5
         * sur un décor carré perd un quart de sa hauteur, visages compris.
         * Le formulaire relève le format au téléversement, donc arriver ici
         * en désaccord veut dire qu'on l'a changé après — d'où l'alerte
         * plutôt que le refus : c'est peut-être voulu, mais ça se voit.
         */
        $ratio_cadre = ratio_lisible((int) $info[0], (int) $info[1]);
        $ratio_toile = (string) ($gabarit['canvas']['ratio'] ?? '');
        $type = $info[2] === IMAGETYPE_PNG ? 'PNG' : 'WebP';
        if ($ratio_toile !== '' && $ratio_cadre !== $ratio_toile) {
            $ajouter('format', 'alerte', sprintf(
                'Cadre %s %d × %d px (%s) sur un décor %s : il sera étiré. Alignez le format du décor sur celui du cadre.',
                $type, $info[0], $info[1], $ratio_cadre, $ratio_toile
            ));
        } else {
            $ajouter('format', 'ok', sprintf('Cadre %s, %d × %d px (%s).', $type, $info[0], $info[1], $ratio_cadre));
        }

        /* 3 — poids */
        $poids = filesize($chemin) ?: 0;
        if ($poids > POIDS_MAX) {
            $ajouter('poids', 'echec', sprintf(
                'Le cadre pèse %d Ko. Au-delà de %d Ko, il ne se charge pas en 3G.',
                (int) round($poids / 1024),
                (int) round(POIDS_MAX / 1024)
            ));
        } else {
            $ajouter('poids', 'ok', sprintf('%d Ko — soutenable en 3G.', (int) round($poids / 1024)));
        }

        /* 4 — la photo doit se voir */
        $slot = null;
        foreach ($gabarit['layers'] as $l) {
            if (($l['type'] ?? '') === 'photoSlot') {
                $slot = $l['rect'];
            }
        }
        $opaque = $slot ? part_opaque($img, $slot['x'], $slot['y'], $slot['w'], $slot['h']) : 1.0;
        if ($opaque > OPACITE_LIMITE) {
            $ajouter('photo-visible', 'echec', sprintf(
                'Le cadre recouvre %d %% de la zone photo : l’invité n’apparaîtra pas. Rendez le centre transparent.',
                (int) round($opaque * 100)
            ));
        } else {
            $ajouter('photo-visible', 'ok', sprintf('La photo apparaît sur %d %% de la zone.', (int) round((1 - $opaque) * 100)));
        }
    }

    /* 5 — collisions avec le filigrane et le QR */
    ['filigrane' => $zone_filigrane, 'qr' => $zone_qr] = zones_reservees($gabarit);

    $collisions = [];
    foreach ($gabarit['layers'] as $l) {
        if (($l['type'] ?? '') !== 'text' || !isset($l['rect'])) {
            continue;
        }
        if (se_chevauchent($l['rect'], $zone_filigrane)) {
            $collisions[] = 'le filigrane Wakabi';
        }
        if (($gabarit['qr']['enabled'] ?? true) && se_chevauchent($l['rect'], $zone_qr)) {
            $collisions[] = 'le QR Code';
        }
    }
    if ($collisions) {
        $ajouter('collision', 'echec', 'Un texte est posé sous ' . implode(' et ', array_unique($collisions)) . ' : il sera illisible.');
    } else {
        $ajouter('collision', 'ok', 'Aucun texte sous le filigrane ni sous le QR.');
    }

    /* 6 — les textes tiennent dans leur zone */
    $trop_longs = [];
    foreach ($gabarit['layers'] as $l) {
        if (($l['type'] ?? '') !== 'text') {
            continue;
        }
        $texte = (string) ($l['value'] ?? $l['placeholder'] ?? '');
        // Largeur moyenne d'un caractère ≈ 0,55 × la taille de police.
        $largeur_estimee = mb_strlen($texte) * ($l['size'] ?? 0.04) * 0.55;
        if ($largeur_estimee > ($l['rect']['w'] ?? 1) * 1.6) {
            $trop_longs[] = $texte;
        }
    }
    if ($trop_longs) {
        $ajouter('texte', 'alerte', 'Un texte devra être fortement réduit pour tenir : « ' . mb_substr($trop_longs[0], 0, 40) . ' ».');
    } else {
        $ajouter('texte', 'ok', 'Les textes tiennent dans le cadre.');
    }

    /* 7 — contraste */
    $sombres = [];
    foreach ($gabarit['layers'] as $l) {
        if (($l['type'] ?? '') === 'text' && in_array($l['color'] ?? '', ['brand.ink', 'brand.text'], true)) {
            $sombres[] = $l['id'];
        }
    }
    if ($sombres) {
        $ajouter('contraste', 'alerte', 'Un texte sombre est illisible sur une photo sombre. Préférez le blanc.');
    } else {
        $ajouter('contraste', 'ok', 'Les textes sont clairs, lisibles sur toute photo.');
    }

    if ($img) {
        imagedestroy($img);
    }

    $passe = true;
    foreach ($controles as $c) {
        if ($c['etat'] === 'echec') {
            $passe = false;
        }
    }
    return ['passe' => $passe, 'controles' => $controles];
}

function enregistrer_prevol(string $decor_id, array $rapport): void
{
    db()->prepare('DELETE FROM prevol WHERE decor_id = ?')->execute([$decor_id]);
    db()->prepare('INSERT INTO prevol (decor_id, passe, rapport, lance_le) VALUES (?,?,?,?)')
        ->execute([$decor_id, $rapport['passe'] ? 1 : 0, json_encode($rapport, JSON_UNESCAPED_UNICODE), maintenant()]);
}

function lire_prevol(string $decor_id): ?array
{
    $s = db()->prepare('SELECT * FROM prevol WHERE decor_id = ?');
    $s->execute([$decor_id]);
    $r = $s->fetch();
    return $r ? json_lire($r['rapport']) : null;
}
