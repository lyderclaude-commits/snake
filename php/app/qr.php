<?php
/**
 * Encodeur QR — mode octet, correction M.
 *
 * Pourquoi l'écrire plutôt que d'installer une bibliothèque : la version PHP
 * doit se déployer en décompressant un zip. Pas de Composer, pas de dépendance
 * à télécharger sur un mutualisé. Le QR est la seule brique qui l'exigeait.
 *
 * Vérifié module par module contre la bibliothèque `qrcode` de Node, sur des
 * charges utiles de 1 à 200 caractères et les versions 1 à 10 — voir
 * scripts/verifier-qr.ts.
 */

declare(strict_types=1);

final class Qr
{
    /** Nombre de mots de code de données, par version, pour le niveau M. */
    private const DATA_CODEWORDS_M = [
        1 => 16, 2 => 28, 3 => 44, 4 => 64, 5 => 86,
        6 => 108, 7 => 124, 8 => 154, 9 => 182, 10 => 216,
    ];

    /** Mots de correction PAR BLOC, niveau M. */
    private const EC_PER_BLOCK_M = [
        1 => 10, 2 => 16, 3 => 26, 4 => 18, 5 => 24,
        6 => 16, 7 => 18, 8 => 22, 9 => 22, 10 => 26,
    ];

    /** [groupe1 : nb blocs, groupe2 : nb blocs] — niveau M. */
    private const BLOCKS_M = [
        1 => [1, 0], 2 => [1, 0], 3 => [1, 0], 4 => [2, 0], 5 => [2, 0],
        6 => [4, 0], 7 => [4, 0], 8 => [2, 2], 9 => [3, 2], 10 => [4, 1],
    ];

    /** Centres des motifs d'alignement, par version. */
    private const ALIGN = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46],
        10 => [6, 28, 50],
    ];

    private static ?array $exp = null;
    private static ?array $log = null;

    /* ---------------- corps de Galois GF(256) ---------------- */

    private static function initGf(): void
    {
        if (self::$exp !== null) {
            return;
        }
        $exp = array_fill(0, 512, 0);
        $log = array_fill(0, 256, 0);
        $x = 1;
        for ($i = 0; $i < 255; $i++) {
            $exp[$i] = $x;
            $log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D; // polynôme primitif du QR
            }
        }
        for ($i = 255; $i < 512; $i++) {
            $exp[$i] = $exp[$i - 255];
        }
        self::$exp = $exp;
        self::$log = $log;
    }

    private static function gfMul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        return self::$exp[self::$log[$a] + self::$log[$b]];
    }

    /** Polynôme générateur de degré $degree. */
    private static function generator(int $degree): array
    {
        $poly = [1];
        for ($i = 0; $i < $degree; $i++) {
            $next = array_fill(0, count($poly) + 1, 0);
            foreach ($poly as $j => $coef) {
                $next[$j] ^= self::gfMul($coef, 1);
                $next[$j + 1] ^= self::gfMul($coef, self::$exp[$i]);
            }
            // La ligne ci-dessus multiplie par (x + α^i) : le terme constant
            // reste, le terme de degré supérieur reçoit α^i.
            $poly = $next;
        }
        return $poly;
    }

    /** Mots de correction d'un bloc de données. */
    private static function ecc(array $data, int $ecLen): array
    {
        $gen = self::generator($ecLen);
        $rem = array_merge($data, array_fill(0, $ecLen, 0));

        for ($i = 0; $i < count($data); $i++) {
            $coef = $rem[$i];
            if ($coef === 0) {
                continue;
            }
            foreach ($gen as $j => $g) {
                $rem[$i + $j] ^= self::gfMul($g, $coef);
            }
        }
        return array_slice($rem, count($data));
    }

    /* ---------------- encodage ---------------- */

    private static function pickVersion(int $len): int
    {
        foreach (self::DATA_CODEWORDS_M as $v => $cap) {
            // 4 bits de mode + 8 ou 16 bits de longueur + les données
            $countBits = $v <= 9 ? 8 : 16;
            $needed = (int) ceil((4 + $countBits + $len * 8) / 8);
            if ($needed <= $cap) {
                return $v;
            }
        }
        throw new RuntimeException('Charge utile trop longue pour un QR (max ~200 caractères).');
    }

    private static function bitsToCodewords(string $bits, int $capacity): array
    {
        // Terminateur : jusqu'à 4 zéros, sans dépasser la capacité.
        $bits .= str_repeat('0', min(4, $capacity * 8 - strlen($bits)));
        // Alignement sur l'octet.
        if (strlen($bits) % 8 !== 0) {
            $bits .= str_repeat('0', 8 - strlen($bits) % 8);
        }

        $words = [];
        for ($i = 0; $i < strlen($bits); $i += 8) {
            $words[] = bindec(substr($bits, $i, 8));
        }
        // Remplissage alterné imposé par la norme.
        $pad = [0xEC, 0x11];
        $k = 0;
        while (count($words) < $capacity) {
            $words[] = $pad[$k++ % 2];
        }
        return $words;
    }

    /** Découpe en blocs, calcule la correction, puis entrelace. */
    private static function interleave(array $words, int $version): array
    {
        [$g1, $g2] = self::BLOCKS_M[$version];
        $total = $g1 + $g2;
        $ecLen = self::EC_PER_BLOCK_M[$version];

        $base = intdiv(count($words), $total);
        $blocks = [];
        $eccs = [];
        $offset = 0;

        for ($i = 0; $i < $total; $i++) {
            // Les blocs du second groupe portent un mot de plus.
            $size = $i < $g1 ? $base : $base + 1;
            $block = array_slice($words, $offset, $size);
            $offset += $size;
            $blocks[] = $block;
            $eccs[] = self::ecc($block, $ecLen);
        }

        $out = [];
        $max = max(array_map('count', $blocks));
        for ($i = 0; $i < $max; $i++) {
            foreach ($blocks as $b) {
                if (isset($b[$i])) {
                    $out[] = $b[$i];
                }
            }
        }
        for ($i = 0; $i < $ecLen; $i++) {
            foreach ($eccs as $e) {
                $out[] = $e[$i];
            }
        }
        return $out;
    }

    /* ---------------- matrice ---------------- */

    private static function blankMatrix(int $size): array
    {
        return array_fill(0, $size, array_fill(0, $size, null));
    }

    private static function placeFinder(array &$m, array &$res, int $r, int $c): void
    {
        for ($i = -1; $i <= 7; $i++) {
            for ($j = -1; $j <= 7; $j++) {
                $y = $r + $i;
                $x = $c + $j;
                if ($y < 0 || $x < 0 || $y >= count($m) || $x >= count($m)) {
                    continue;
                }
                $inBorder = $i >= 0 && $i <= 6 && $j >= 0 && $j <= 6;
                $ring = $i === 0 || $i === 6 || $j === 0 || $j === 6;
                $core = $i >= 2 && $i <= 4 && $j >= 2 && $j <= 4;
                $m[$y][$x] = $inBorder && ($ring || $core) ? 1 : 0;
                $res[$y][$x] = true;
            }
        }
    }

    private static function buildMatrix(array $codewords, int $version, int $mask): array
    {
        $size = 17 + $version * 4;
        $m = self::blankMatrix($size);
        $res = array_fill(0, $size, array_fill(0, $size, false)); // zones réservées

        self::placeFinder($m, $res, 0, 0);
        self::placeFinder($m, $res, 0, $size - 7);
        self::placeFinder($m, $res, $size - 7, 0);

        // Motifs de synchronisation
        for ($i = 8; $i < $size - 8; $i++) {
            $bit = $i % 2 === 0 ? 1 : 0;
            $m[6][$i] = $bit;
            $res[6][$i] = true;
            $m[$i][6] = $bit;
            $res[$i][6] = true;
        }

        // Motifs d'alignement
        $centers = self::ALIGN[$version];
        foreach ($centers as $cy) {
            foreach ($centers as $cx) {
                // Pas sous les motifs de détection.
                if (($cy <= 8 && $cx <= 8)
                    || ($cy <= 8 && $cx >= $size - 9)
                    || ($cy >= $size - 9 && $cx <= 8)) {
                    continue;
                }
                for ($i = -2; $i <= 2; $i++) {
                    for ($j = -2; $j <= 2; $j++) {
                        $m[$cy + $i][$cx + $j] = (abs($i) === 2 || abs($j) === 2 || ($i === 0 && $j === 0)) ? 1 : 0;
                        $res[$cy + $i][$cx + $j] = true;
                    }
                }
            }
        }

        // Module toujours noir + réservation des zones d'information de format
        $m[$size - 8][8] = 1;
        $res[$size - 8][8] = true;
        for ($i = 0; $i <= 8; $i++) {
            if (!$res[8][$i]) { $res[8][$i] = true; $m[8][$i] = 0; }
            if (!$res[$i][8]) { $res[$i][8] = true; $m[$i][8] = 0; }
        }
        for ($i = 0; $i < 8; $i++) {
            if (!$res[8][$size - 1 - $i]) { $res[8][$size - 1 - $i] = true; $m[8][$size - 1 - $i] = 0; }
            if (!$res[$size - 1 - $i][8]) { $res[$size - 1 - $i][8] = true; $m[$size - 1 - $i][8] = 0; }
        }

        // Information de version (à partir de la version 7)
        if ($version >= 7) {
            $bits = self::versionBits($version);
            for ($i = 0; $i < 18; $i++) {
                $bit = ($bits >> $i) & 1;
                $r = intdiv($i, 3);
                $c = $size - 11 + ($i % 3);
                $m[$r][$c] = $bit;
                $res[$r][$c] = true;
                $m[$c][$r] = $bit;
                $res[$c][$r] = true;
            }
        }

        // Données, en zigzag depuis le coin bas-droit
        $bits = '';
        foreach ($codewords as $w) {
            $bits .= str_pad(decbin($w), 8, '0', STR_PAD_LEFT);
        }
        $p = 0;
        $up = true;
        for ($col = $size - 1; $col > 0; $col -= 2) {
            if ($col === 6) {
                $col--; // la colonne de synchronisation est sautée
            }
            for ($k = 0; $k < $size; $k++) {
                $row = $up ? $size - 1 - $k : $k;
                foreach ([0, 1] as $d) {
                    $c = $col - $d;
                    if ($res[$row][$c]) {
                        continue;
                    }
                    $bit = $p < strlen($bits) ? (int) $bits[$p] : 0;
                    $p++;
                    $m[$row][$c] = $bit ^ self::maskBit($mask, $row, $c);
                }
            }
            $up = !$up;
        }

        // Information de format, une fois le masque connu.
        //
        // Les deux copies se lisent en sens INVERSE l'une de l'autre : le bras
        // vertical de la première porte les bits de poids faible en descendant,
        // le bras horizontal les bits de poids fort en allant vers la gauche.
        // Les inverser produit un QR d'apparence correcte, que rien ne décode.
        $fmt = self::formatBits($mask);

        // Copie 1 — bras vertical (colonne 8) : bits 0 à 8
        for ($i = 0; $i <= 5; $i++) {
            $m[$i][8] = ($fmt >> $i) & 1;
        }
        $m[7][8] = ($fmt >> 6) & 1;
        $m[8][8] = ($fmt >> 7) & 1;

        // Copie 1 — bras horizontal (ligne 8) : bits 8 à 14, vers la gauche
        $m[8][7] = ($fmt >> 8) & 1;
        for ($i = 9; $i <= 14; $i++) {
            $m[8][14 - $i] = ($fmt >> $i) & 1;
        }

        // Copie 2 — sous le motif haut-gauche : bits 14 à 8
        for ($i = 0; $i <= 6; $i++) {
            $m[$size - 1 - $i][8] = ($fmt >> (14 - $i)) & 1;
        }
        // Copie 2 — à droite du motif haut-droit : bits 7 à 0
        for ($i = 0; $i <= 7; $i++) {
            $m[8][$size - 8 + $i] = ($fmt >> (7 - $i)) & 1;
        }

        return $m;
    }

    private static function maskBit(int $mask, int $r, int $c): int
    {
        return match ($mask) {
            0 => ($r + $c) % 2 === 0 ? 1 : 0,
            1 => $r % 2 === 0 ? 1 : 0,
            2 => $c % 3 === 0 ? 1 : 0,
            3 => ($r + $c) % 3 === 0 ? 1 : 0,
            4 => (intdiv($r, 2) + intdiv($c, 3)) % 2 === 0 ? 1 : 0,
            5 => (($r * $c) % 2 + ($r * $c) % 3) === 0 ? 1 : 0,
            6 => ((($r * $c) % 2 + ($r * $c) % 3) % 2) === 0 ? 1 : 0,
            7 => ((($r + $c) % 2 + ($r * $c) % 3) % 2) === 0 ? 1 : 0,
            default => 0,
        };
    }

    private static function formatBits(int $mask): int
    {
        // Niveau M = 00, suivi des 3 bits de masque.
        $data = (0b00 << 3) | $mask;
        $rem = $data << 10;
        for ($i = 14; $i >= 10; $i--) {
            if (($rem >> $i) & 1) {
                $rem ^= 0b10100110111 << ($i - 10);
            }
        }
        return (($data << 10) | $rem) ^ 0b101010000010010;
    }

    private static function versionBits(int $version): int
    {
        $rem = $version << 12;
        for ($i = 17; $i >= 12; $i--) {
            if (($rem >> $i) & 1) {
                $rem ^= 0b1111100100101 << ($i - 12);
            }
        }
        return ($version << 12) | $rem;
    }

    /* ---------------- pénalités et choix du masque ---------------- */

    private static function penalty(array $m): int
    {
        $size = count($m);
        $score = 0;

        // Règle 1 — séries de 5 modules identiques ou plus
        foreach ([true, false] as $horizontal) {
            for ($a = 0; $a < $size; $a++) {
                $run = 1;
                for ($b = 1; $b < $size; $b++) {
                    $cur = $horizontal ? $m[$a][$b] : $m[$b][$a];
                    $prev = $horizontal ? $m[$a][$b - 1] : $m[$b - 1][$a];
                    if ($cur === $prev) {
                        $run++;
                    } else {
                        if ($run >= 5) {
                            $score += 3 + ($run - 5);
                        }
                        $run = 1;
                    }
                }
                if ($run >= 5) {
                    $score += 3 + ($run - 5);
                }
            }
        }

        // Règle 2 — blocs 2×2 uniformes
        for ($r = 0; $r < $size - 1; $r++) {
            for ($c = 0; $c < $size - 1; $c++) {
                $v = $m[$r][$c];
                if ($v === $m[$r][$c + 1] && $v === $m[$r + 1][$c] && $v === $m[$r + 1][$c + 1]) {
                    $score += 3;
                }
            }
        }

        // Règle 3 — motif 1:1:3:1:1 assimilable à un motif de détection
        $a = [1, 0, 1, 1, 1, 0, 1, 0, 0, 0, 0];
        $b = [0, 0, 0, 0, 1, 0, 1, 1, 1, 0, 1];
        foreach ([true, false] as $horizontal) {
            for ($i = 0; $i < $size; $i++) {
                for ($j = 0; $j <= $size - 11; $j++) {
                    $okA = true;
                    $okB = true;
                    for ($k = 0; $k < 11; $k++) {
                        $v = $horizontal ? $m[$i][$j + $k] : $m[$j + $k][$i];
                        if ($v !== $a[$k]) { $okA = false; }
                        if ($v !== $b[$k]) { $okB = false; }
                    }
                    if ($okA) { $score += 40; }
                    if ($okB) { $score += 40; }
                }
            }
        }

        // Règle 4 — déséquilibre entre modules clairs et sombres
        $dark = 0;
        foreach ($m as $row) {
            $dark += array_sum($row);
        }
        $ratio = $dark * 100 / ($size * $size);
        $score += (int) (abs(intdiv((int) $ratio, 5) * 5 - 50) / 5) * 10;

        return $score;
    }

    /* ---------------- API publique ---------------- */

    /** Matrice de modules : 1 = sombre, 0 = clair. */
    public static function matrix(string $texte, ?int $forcerMasque = null): array
    {
        self::initGf();

        $len = strlen($texte);
        $version = self::pickVersion($len);
        $countBits = $version <= 9 ? 8 : 16;

        $bits = '0100'; // mode octet
        $bits .= str_pad(decbin($len), $countBits, '0', STR_PAD_LEFT);
        for ($i = 0; $i < $len; $i++) {
            $bits .= str_pad(decbin(ord($texte[$i])), 8, '0', STR_PAD_LEFT);
        }

        $words = self::bitsToCodewords($bits, self::DATA_CODEWORDS_M[$version]);
        $final = self::interleave($words, $version);

        if ($forcerMasque !== null) {
            return self::buildMatrix($final, $version, $forcerMasque);
        }

        $best = null;
        $bestScore = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; $mask++) {
            $m = self::buildMatrix($final, $version, $mask);
            $s = self::penalty($m);
            if ($s < $bestScore) {
                $bestScore = $s;
                $best = $m;
            }
        }
        return $best;
    }

    /** PNG en data URI, prêt à être dessiné dans un canevas. */
    public static function dataUri(string $texte, int $taille = 512): string
    {
        $m = self::matrix($texte);
        $modules = count($m);
        $echelle = max(1, intdiv($taille, $modules));
        $px = $modules * $echelle;

        $img = imagecreatetruecolor($px, $px);
        $blanc = imagecolorallocate($img, 255, 255, 255);
        $sombre = imagecolorallocate($img, 15, 23, 42); // brand.ink
        imagefilledrectangle($img, 0, 0, $px, $px, $blanc);

        for ($r = 0; $r < $modules; $r++) {
            for ($c = 0; $c < $modules; $c++) {
                if ($m[$r][$c] === 1) {
                    imagefilledrectangle(
                        $img,
                        $c * $echelle,
                        $r * $echelle,
                        ($c + 1) * $echelle - 1,
                        ($r + 1) * $echelle - 1,
                        $sombre
                    );
                }
            }
        }

        ob_start();
        imagepng($img, null, 9);
        $donnees = ob_get_clean();
        imagedestroy($img);

        return 'data:image/png;base64,' . base64_encode($donnees);
    }
}
