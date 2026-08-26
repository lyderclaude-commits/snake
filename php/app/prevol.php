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
    $ajouter('format', 'ok', sprintf('Cadre %s, %d × %d px.', $info[2] === IMAGETYPE_PNG ? 'PNG' : 'WebP', $info[0], $info[1]));

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

    /* 5 — collisions avec le filigrane et le QR */
    $zone_filigrane = ['x' => 0.74, 'y' => 0.86, 'w' => 0.24, 'h' => 0.12];
    $taille_qr = (float) ($gabarit['qr']['size'] ?? 0.16);
    $zone_qr = ['x' => 0.04, 'y' => 0.96 - $taille_qr, 'w' => $taille_qr + 0.03, 'h' => $taille_qr + 0.03];

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

    imagedestroy($img);

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
