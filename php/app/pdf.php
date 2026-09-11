<?php
/**
 * Un écrivain de PDF, écrit à la main.
 *
 * Même raison que le ZIP et le QR : aucune bibliothèque externe n'entre
 * dans ce produit. Un `composer install` sur un mutualisé LWS, c'est un
 * dossier `vendor/` de trente mégaoctets à téléverser par FTP et une
 * version de PHP à surveiller. Le format PDF, lui, tient dans ce fichier.
 *
 * Ce qu'il sait faire, et rien de plus : des pages A4, du texte en
 * Helvetica, des filets, des aplats, et une image. C'est exactement ce
 * qu'il faut pour une facture, et c'est tout ce dont on a besoin.
 *
 * DEUX CHOIX QUI ÉVITENT LES ENNUIS
 *
 * 1. **Les polices ne sont pas incorporées.** Helvetica fait partie des
 *    quatorze polices que tout lecteur de PDF possède depuis 1993 : le
 *    fichier pèse quelques kilo-octets au lieu de trois cents, il s'ouvre
 *    partout, et aucune licence de fonte ne s'y invite. Le prix à payer
 *    est de ne pas retrouver la police du produit sur le papier — sur une
 *    facture, personne ne le remarque.
 *
 * 2. **Le texte est transcodé en Windows-1252.** C'est l'encodage des
 *    polices standard, et il couvre le français en entier, accents,
 *    apostrophes typographiques et œ compris. Un caractère hors de cette
 *    table est remplacé plutôt que d'écrire un octet que le lecteur
 *    afficherait de travers.
 *
 * Le repère est celui d'une feuille de papier — origine EN HAUT à gauche,
 * y qui descend, millimètres. Le PDF, lui, compte en points depuis le bas.
 * La conversion se fait ici, une fois, plutôt que dans chaque appel.
 */

declare(strict_types=1);

/** Un point PDF vaut 1/72 de pouce ; un millimètre, 72/25,4 points. */
const PDF_MM = 2.834645669;

/** A4, en millimètres. */
const PDF_L = 210.0;
const PDF_H = 297.0;

final class EcrivainPdf
{
    /** Les objets du document, dans l'ordre. L'objet n vaut $objets[n-1]. */
    private array $objets = [];

    /** Les numéros d'objet des pages, pour l'arbre des pages. */
    private array $pages = [];

    /** Le flux de la page en cours, en opérateurs PDF. */
    private string $flux = '';

    /** Les images déclarées : nom → ['objet' => int, 'l' => int, 'h' => int]. */
    private array $images = [];

    private ?string $titre = null;

    /**
     * La chasse des caractères d'Helvetica, en millièmes de cadratin.
     *
     * Sans elle, impossible d'aligner un montant à droite ou de centrer un
     * titre : le PDF ne mesure rien, il pose des glyphes là où on le lui
     * dit. Les valeurs viennent des métriques publiées par Adobe.
     */
    private const CHASSE = [
        'normale' => '278,278,355,556,556,889,667,191,333,333,389,584,278,333,278,278,'
                   . '556,556,556,556,556,556,556,556,556,556,278,278,584,584,584,556,'
                   . '1015,667,667,722,722,667,611,778,722,278,500,667,556,833,722,778,'
                   . '667,778,722,667,611,722,667,944,667,667,611,278,278,278,469,556,'
                   . '333,556,556,500,556,556,278,556,556,222,222,500,222,833,556,556,'
                   . '556,556,333,500,278,556,500,722,500,500,500,334,260,334,584',
        'grasse'  => '278,333,474,556,556,889,722,238,333,333,389,584,278,333,278,278,'
                   . '556,556,556,556,556,556,556,556,556,556,333,333,584,584,584,611,'
                   . '975,722,722,722,722,667,611,778,722,278,556,722,611,833,722,778,'
                   . '667,778,722,667,611,722,667,944,667,667,611,333,278,333,584,556,'
                   . '333,556,611,556,611,556,333,611,611,278,278,556,278,889,611,611,'
                   . '611,611,389,556,333,611,556,778,556,556,500,389,280,389,584',
    ];

    /**
     * Les caractères accentués empruntent la chasse de leur lettre de base.
     *
     * C'est ainsi que les polices sont dessinées : un accent ne pousse pas
     * la lettre suivante. Écrire deux cent cinquante-six valeurs de plus
     * n'apporterait donc rien qu'une occasion de se tromper.
     */
    private const HAUT_BASE = [
        192 => 'A', 193 => 'A', 194 => 'A', 195 => 'A', 196 => 'A', 197 => 'A', 199 => 'C',
        200 => 'E', 201 => 'E', 202 => 'E', 203 => 'E', 204 => 'I', 205 => 'I', 206 => 'I',
        207 => 'I', 209 => 'N', 210 => 'O', 211 => 'O', 212 => 'O', 213 => 'O', 214 => 'O',
        216 => 'O', 217 => 'U', 218 => 'U', 219 => 'U', 220 => 'U', 221 => 'Y',
        224 => 'a', 225 => 'a', 226 => 'a', 227 => 'a', 228 => 'a', 229 => 'a', 231 => 'c',
        232 => 'e', 233 => 'e', 234 => 'e', 235 => 'e', 236 => 'i', 237 => 'i', 238 => 'i',
        239 => 'i', 241 => 'n', 242 => 'o', 243 => 'o', 244 => 'o', 245 => 'o', 246 => 'o',
        248 => 'o', 249 => 'u', 250 => 'u', 251 => 'u', 252 => 'u', 253 => 'y', 255 => 'y',
    ];

    /** Ceux qui n'ont pas de lettre de base : chasse normale, chasse grasse. */
    private const HAUT_FIXE = [
        128 => [556, 556],   169 => [737, 737],   187 => [556, 556],
        133 => [1000, 1000], 171 => [556, 556],   198 => [1000, 1000],
        140 => [1000, 1000], 174 => [737, 737],   223 => [611, 611],
        145 => [222, 238],   176 => [400, 400],   230 => [889, 889],
        146 => [222, 238],   156 => [944, 944],   160 => [278, 278],
        147 => [333, 500],   148 => [333, 500],   149 => [350, 350],
        150 => [556, 556],   151 => [1000, 1000],
    ];

    public function __construct(?string $titre = null)
    {
        $this->titre = $titre;
    }

    /* ---------------------------------------------------------------- */
    /* Le texte                                                          */
    /* ---------------------------------------------------------------- */

    /**
     * Ce que Windows-1252 ne sait pas écrire, et qu'on écrit quand même.
     *
     * Le signe moins typographique (U+2212) n'y est pas : sans cette
     * table, un avoir de douze mille francs s'imprimerait « ?12 000 ».
     * Les autres sont des caractères qu'on emploie à l'écran et qui n'ont
     * pas d'équivalent sur le papier.
     */
    private const REMPLACES = [
        "\u{2212}" => '-', "\u{2010}" => '-', "\u{2011}" => '-',
        "\u{2007}" => ' ', "\u{202F}" => ' ', "\u{2009}" => ' ',
        "\u{2713}" => 'x', "\u{2717}" => 'x', "\u{00A0}" => ' ',
    ];

    /**
     * Le passage en Windows-1252, avec un repli qui ne casse rien.
     *
     * `mb_convert_encoding` est vérifié par l'installateur. S'il manque
     * malgré tout, on retombe sur un translittéré grossier : une facture
     * sans accents reste une facture, une facture illisible n'en est plus
     * une.
     */
    private static function cp1252(string $s): string
    {
        $s = strtr($s, self::REMPLACES);
        if (function_exists('mb_convert_encoding')) {
            $mb = @mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
            if (is_string($mb) && $mb !== '') {
                return $mb;
            }
        }
        return (string) preg_replace('/[^\x20-\x7E]/', '?', $s);
    }

    /** Une chaîne PDF : parenthèses et contre-obliques protégées. */
    private static function chaine(string $s): string
    {
        $s = self::cp1252($s);
        $out = '';
        for ($i = 0, $n = strlen($s); $i < $n; $i++) {
            $c = $s[$i];
            $o = ord($c);
            if ($c === '(' || $c === ')' || $c === '\\') {
                $out .= '\\' . $c;
            } elseif ($o < 32 || $o > 126) {
                $out .= sprintf('\\%03o', $o);
            } else {
                $out .= $c;
            }
        }
        return '(' . $out . ')';
    }

    /**
     * La largeur d'un texte, en millimètres.
     *
     * Publique : la mise en page de la facture s'en sert pour décider où
     * couper une ligne et où poser un filet.
     */
    public function largeur(string $texte, float $taille, bool $gras = false): float
    {
        $cle = $gras ? 'grasse' : 'normale';
        static $tables = [];
        if (!isset($tables[$cle])) {
            $tables[$cle] = array_map('intval', explode(',', self::CHASSE[$cle]));
        }
        $t = $tables[$cle];
        $s = self::cp1252($texte);
        $somme = 0;
        for ($i = 0, $n = strlen($s); $i < $n; $i++) {
            $o = ord($s[$i]);
            if ($o >= 32 && $o <= 126) {
                $somme += $t[$o - 32];
            } elseif (isset(self::HAUT_FIXE[$o])) {
                $somme += self::HAUT_FIXE[$o][$gras ? 1 : 0];
            } elseif (isset(self::HAUT_BASE[$o])) {
                $somme += $t[ord(self::HAUT_BASE[$o]) - 32];
            } else {
                $somme += 556;
            }
        }
        return $somme / 1000 * $taille / PDF_MM;
    }

    /* ---------------------------------------------------------------- */
    /* Le dessin                                                         */
    /* ---------------------------------------------------------------- */

    /** Ouvre une page. Toute page commence par celle-ci. */
    public function page(): void
    {
        if ($this->flux !== '') {
            $this->fermer_page();
        }
        $this->flux = '';
    }

    private static function pt(float $mm): string
    {
        return number_format($mm * PDF_MM, 2, '.', '');
    }

    /** Le y du papier vers le y du PDF : l'un descend, l'autre monte. */
    private static function ypt(float $mm): string
    {
        return number_format((PDF_H - $mm) * PDF_MM, 2, '.', '');
    }

    private static function couleur(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return '0 0 0';
        }
        return implode(' ', array_map(
            fn(string $p): string => number_format(hexdec($p) / 255, 3, '.', ''),
            [substr($hex, 0, 2), substr($hex, 2, 2), substr($hex, 4, 2)]
        ));
    }

    /**
     * Pose un texte, coin bas-gauche de la première lettre.
     *
     * `$aligne` vaut 'gauche', 'droite' ou 'centre' — et dans les deux
     * derniers cas `$x` désigne le bord droit ou le milieu, pas le départ.
     */
    public function texte(
        float $x,
        float $y,
        string $texte,
        float $taille = 10,
        bool $gras = false,
        string $couleur = '#0F172A',
        string $aligne = 'gauche'
    ): void {
        if ($texte === '') {
            return;
        }
        if ($aligne === 'droite') {
            $x -= $this->largeur($texte, $taille, $gras);
        } elseif ($aligne === 'centre') {
            $x -= $this->largeur($texte, $taille, $gras) / 2;
        }
        $this->flux .= 'BT ' . self::couleur($couleur) . ' rg /'
            . ($gras ? 'F2' : 'F1') . ' ' . number_format($taille, 2, '.', '') . ' Tf '
            . self::pt($x) . ' ' . self::ypt($y) . ' Td ' . self::chaine($texte) . " Tj ET\n";
    }

    /**
     * Un paragraphe coupé à la largeur donnée. Rend le y de la ligne suivante.
     *
     * La coupe se fait sur les espaces, en mesurant : un mot qui dépasse à
     * lui seul reste sur sa ligne plutôt que d'être tronqué, parce qu'une
     * référence de virement coupée en deux ne se recopie plus.
     */
    public function paragraphe(
        float $x,
        float $y,
        float $largeur,
        string $texte,
        float $taille = 9,
        float $interligne = 4.2,
        bool $gras = false,
        string $couleur = '#475569'
    ): float {
        $mots = preg_split('/\s+/u', trim($texte)) ?: [];
        $ligne = '';
        foreach ($mots as $mot) {
            $essai = $ligne === '' ? $mot : $ligne . ' ' . $mot;
            if ($ligne !== '' && $this->largeur($essai, $taille, $gras) > $largeur) {
                $this->texte($x, $y, $ligne, $taille, $gras, $couleur);
                $y += $interligne;
                $ligne = $mot;
                continue;
            }
            $ligne = $essai;
        }
        if ($ligne !== '') {
            $this->texte($x, $y, $ligne, $taille, $gras, $couleur);
            $y += $interligne;
        }
        return $y;
    }

    /** Un filet horizontal ou oblique. */
    public function filet(
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        float $epaisseur = 0.2,
        string $couleur = '#E2E8F5'
    ): void {
        $this->flux .= self::couleur($couleur) . ' RG '
            . number_format($epaisseur * PDF_MM, 2, '.', '') . ' w '
            . self::pt($x1) . ' ' . self::ypt($y1) . ' m '
            . self::pt($x2) . ' ' . self::ypt($y2) . " l S\n";
    }

    /** Un aplat rectangulaire — un fond de ligne, une bande de total. */
    public function pave(float $x, float $y, float $l, float $h, string $couleur = '#F1F5FC'): void
    {
        $this->flux .= self::couleur($couleur) . ' rg '
            . self::pt($x) . ' ' . self::ypt($y + $h) . ' '
            . self::pt($l) . ' ' . self::pt($h) . " re f\n";
    }

    /* ---------------------------------------------------------------- */
    /* Les images                                                        */
    /* ---------------------------------------------------------------- */

    /**
     * Déclare une image PNG, redimensionnée à la taille où on la pose.
     *
     * Un logo de neuf cent pixels de côté pèse deux mégaoctets une fois
     * étalé en octets bruts : sur un mutualisé, en fabriquer un par
     * facture épuiserait la mémoire avant la dixième. On le réduit donc à
     * la définition d'impression — trois cents points par pouce à la
     * taille réelle — et le résultat se garde en cache.
     *
     * La transparence passe par un masque : le PDF ne connaît pas le canal
     * alpha d'un PNG, il veut une image en niveaux de gris à part.
     */
    public function image_png(string $nom, string $chemin, float $largeur_mm): bool
    {
        if (isset($this->images[$nom])) {
            return true;
        }
        if (!is_file($chemin) || !function_exists('imagecreatefrompng')) {
            return false;
        }
        $cible = max(64, min(600, (int) round($largeur_mm / 25.4 * 300)));
        $cache = self::cache_image($chemin, $cible);
        $prepare = $cache !== null ? $cache : self::preparer_png($chemin, $cible);
        if ($prepare === null) {
            return false;
        }
        if ($cache === null) {
            self::poser_cache($chemin, $cible, $prepare);
        }

        [$l, $h, $rgb, $alpha] = $prepare;
        $masque = $this->ajouter(
            "<</Type/XObject/Subtype/Image/Width $l/Height $h"
            . '/ColorSpace/DeviceGray/BitsPerComponent 8/Filter/FlateDecode'
            . '/Length ' . strlen($alpha) . ">>\nstream\n" . $alpha . "\nendstream"
        );
        $objet = $this->ajouter(
            "<</Type/XObject/Subtype/Image/Width $l/Height $h"
            . '/ColorSpace/DeviceRGB/BitsPerComponent 8/Filter/FlateDecode'
            . "/SMask $masque 0 R/Length " . strlen($rgb) . ">>\nstream\n" . $rgb . "\nendstream"
        );
        $this->images[$nom] = ['objet' => $objet, 'l' => $l, 'h' => $h];
        return true;
    }

    /** Pose une image déjà déclarée. La hauteur suit les proportions. */
    public function image(string $nom, float $x, float $y, float $largeur_mm): float
    {
        if (!isset($this->images[$nom])) {
            return $y;
        }
        $i = $this->images[$nom];
        $hauteur = $largeur_mm * $i['h'] / max(1, $i['l']);
        $this->flux .= 'q ' . self::pt($largeur_mm) . ' 0 0 ' . self::pt($hauteur) . ' '
            . self::pt($x) . ' ' . self::ypt($y + $hauteur) . ' cm /IM'
            . $i['objet'] . " Do Q\n";
        return $y + $hauteur;
    }

    /** @return array{0:int,1:int,2:string,3:string}|null */
    private static function preparer_png(string $chemin, int $cible): ?array
    {
        $src = @imagecreatefrompng($chemin);
        if (!$src) {
            return null;
        }
        $l0 = imagesx($src);
        $h0 = imagesy($src);
        $l = min($cible, $l0);
        $h = max(1, (int) round($h0 * $l / max(1, $l0)));
        $im = imagecreatetruecolor($l, $h);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 255, 255, 255, 127));
        imagecopyresampled($im, $src, 0, 0, 0, 0, $l, $h, $l0, $h0);
        imagedestroy($src);

        $rgb = '';
        $alpha = '';
        for ($y = 0; $y < $h; $y++) {
            for ($x = 0; $x < $l; $x++) {
                $c = imagecolorat($im, $x, $y);
                $rgb .= chr(($c >> 16) & 255) . chr(($c >> 8) & 255) . chr($c & 255);
                // GD compte l'opacité à l'envers, et sur 7 bits : 0 opaque, 127 transparent.
                $alpha .= chr(255 - (int) round((($c >> 24) & 0x7F) * 255 / 127));
            }
        }
        imagedestroy($im);
        return [$l, $h, gzcompress($rgb, 6), gzcompress($alpha, 6)];
    }

    private static function chemin_cache(string $source, int $cible): ?string
    {
        if (!function_exists('dossier_donnees')) {
            return null;
        }
        $dossier = dossier_donnees() . '/pdf';
        if (!is_dir($dossier) && !@mkdir($dossier, 0775, true) && !is_dir($dossier)) {
            return null;
        }
        return $dossier . '/' . substr(sha1($source . '|' . $cible . '|' . (string) @filemtime($source)), 0, 24) . '.bin';
    }

    /** @return array{0:int,1:int,2:string,3:string}|null */
    private static function cache_image(string $source, int $cible): ?array
    {
        $c = self::chemin_cache($source, $cible);
        if ($c === null || !is_file($c)) {
            return null;
        }
        $lu = @unserialize((string) file_get_contents($c), ['allowed_classes' => false]);
        return is_array($lu) && count($lu) === 4 ? $lu : null;
    }

    private static function poser_cache(string $source, int $cible, array $prepare): void
    {
        $c = self::chemin_cache($source, $cible);
        if ($c !== null) {
            @file_put_contents($c, serialize($prepare));
        }
    }

    /* ---------------------------------------------------------------- */
    /* L'assemblage                                                      */
    /* ---------------------------------------------------------------- */

    private function ajouter(string $contenu): int
    {
        $this->objets[] = $contenu;
        return count($this->objets);
    }

    private function fermer_page(): void
    {
        $flux = gzcompress($this->flux, 6);
        $contenu = $this->ajouter(
            '<</Filter/FlateDecode/Length ' . strlen($flux) . ">>\nstream\n" . $flux . "\nendstream"
        );
        $this->pages[] = ['contenu' => $contenu, 'images' => array_values($this->images)];
        $this->flux = '';
    }

    /**
     * Le document entier, prêt à écrire ou à envoyer.
     *
     * La table des références croisées veut le décalage EXACT de chaque
     * objet depuis le début du fichier : c'est elle qui permet à un lecteur
     * d'ouvrir la dernière page d'un document de mille sans lire les neuf
     * cent quatre-vingt-dix-neuf premières. Un octet d'écart et le fichier
     * est refusé.
     */
    public function rendu(): string
    {
        if ($this->flux !== '') {
            $this->fermer_page();
        }

        $police = $this->ajouter('<</Type/Font/Subtype/Type1/BaseFont/Helvetica/Encoding/WinAnsiEncoding>>');
        $grasse = $this->ajouter('<</Type/Font/Subtype/Type1/BaseFont/Helvetica-Bold/Encoding/WinAnsiEncoding>>');

        // L'arbre des pages se réserve son numéro avant les pages elles-mêmes :
        // chacune doit pouvoir le désigner comme parent.
        $arbre = $this->ajouter('');
        $numeros = [];
        foreach ($this->pages as $p) {
            $xo = '';
            foreach ($p['images'] as $i) {
                $xo .= '/IM' . $i['objet'] . ' ' . $i['objet'] . ' 0 R';
            }
            $numeros[] = $this->ajouter(
                '<</Type/Page/Parent ' . $arbre . ' 0 R'
                . '/MediaBox[0 0 ' . self::pt(PDF_L) . ' ' . self::pt(PDF_H) . ']'
                . '/Resources<</Font<</F1 ' . $police . ' 0 R/F2 ' . $grasse . ' 0 R>>'
                . ($xo !== '' ? '/XObject<<' . $xo . '>>' : '') . '>>'
                . '/Contents ' . $p['contenu'] . ' 0 R>>'
            );
        }
        $this->objets[$arbre - 1] = '<</Type/Pages/Count ' . count($numeros) . '/Kids['
            . implode(' ', array_map(fn(int $n): string => $n . ' 0 R', $numeros)) . ']>>';

        $catalogue = $this->ajouter('<</Type/Catalog/Pages ' . $arbre . ' 0 R>>');
        $info = $this->ajouter(
            '<</Producer ' . self::chaine('Wakabi Boost')
            . ($this->titre !== null ? '/Title ' . self::chaine($this->titre) : '')
            . '/CreationDate ' . self::chaine('D:' . gmdate('YmdHis') . "Z00'00") . '>>'
        );

        $out = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $decalages = [];
        foreach ($this->objets as $i => $contenu) {
            $decalages[] = strlen($out);
            $out .= ($i + 1) . " 0 obj\n" . $contenu . "\nendobj\n";
        }

        $xref = strlen($out);
        $n = count($this->objets) + 1;
        $out .= "xref\n0 $n\n0000000000 65535 f \n";
        foreach ($decalages as $d) {
            $out .= sprintf("%010d 00000 n \n", $d);
        }
        $out .= "trailer\n<</Size $n/Root $catalogue 0 R/Info $info 0 R>>\n"
              . "startxref\n$xref\n%%EOF\n";
        return $out;
    }
}
