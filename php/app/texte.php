<?php
/**
 * Le texte d'un article, mis en forme sans confier le HTML à personne.
 *
 * Un champ « corps » où l'on collerait du HTML serait une faille béante :
 * quiconque publie un article pourrait poser un `<script>` sur une page
 * lue par tout le monde. Un éditeur riche règle cela avec une liste
 * blanche de balises — c'est-à-dire avec un analyseur HTML complet, qu'il
 * faudrait écrire ici, sans bibliothèque, et maintenir.
 *
 * On prend l'autre chemin, plus court et plus sûr : le corps est du TEXTE.
 * On l'échappe d'abord — entièrement, sans exception — et seulement ensuite
 * on reconnaît quelques marques d'écriture pour poser les balises
 * nous-mêmes. Aucune balise ne peut donc venir de la saisie : elles sont
 * toutes écrites par ce fichier.
 *
 * Les marques reconnues, volontairement peu nombreuses :
 *
 *     ## Un intertitre
 *     Un paragraphe ordinaire, séparé du suivant par une ligne vide.
 *     - un point de liste
 *     > une citation
 *     **gras** et *italique*
 *     [le texte du lien](https://exemple.tg)
 *
 * C'est la syntaxe que les gens connaissent de WhatsApp et de Markdown.
 * Ce qui n'est pas reconnu s'affiche tel quel, ce qui est le comportement
 * le moins surprenant.
 */

declare(strict_types=1);

/** Les marques en ligne : gras, italique, code, liens. */
function texte_en_ligne(string $echappe): string
{
    // `**gras**` avant `*italique*`, sinon le second mangerait le premier.
    $t = preg_replace('/\*\*(?=\S)(.+?)(?<=\S)\*\*/s', '<strong>$1</strong>', $echappe) ?? $echappe;
    $t = preg_replace('/(?<![\*\w])\*(?=\S)(.+?)(?<=\S)\*(?![\*\w])/s', '<em>$1</em>', $t) ?? $t;
    $t = preg_replace('/`([^`]+)`/', '<code>$1</code>', $t) ?? $t;

    /**
     * Un lien : le libellé au choix, l'adresse sous contrôle.
     *
     * Le motif n'accepte que `http` et `https`. Sans cela, un
     * `[cliquez](javascript:…)` deviendrait un lien exécutable — c'est
     * l'injection la plus banale qui soit dans un champ de ce genre. Les
     * liens sortants portent `rel="noopener nofollow"` : le premier ferme
     * l'accès à notre onglet, le second dit aux moteurs qu'on ne se porte
     * pas garant.
     */
    return preg_replace_callback(
        '/\[([^\]]{1,120})\]\((https?:&#0*39;?[^\s)]+|https?:\/\/[^\s)]+)\)/',
        function (array $m): string {
            $url = html_entity_decode($m[2], ENT_QUOTES, 'UTF-8');
            if (!preg_match('~^https?://~i', $url)) {
                return $m[0];
            }
            /**
             * On compare les HÔTES, pas les préfixes d'adresse. Sur un site
             * servi depuis `boost.exemple.com`, une adresse pointant vers
             * `boost.exemple.com.ailleurs.net` commence par la nôtre sans
             * être la nôtre : le test par préfixe la déclarait interne, et
             * elle s'ouvrait dans notre onglet, sans `noopener`.
             *
             * `nofollow` s'ajoute ici et pas ailleurs : ces liens sont
             * écrits dans un article, donc par une personne, et le site ne
             * se porte pas garant de ce qu'elle cite.
             */
            return '<a href="' . e($url) . '"'
                 . sortie_externe($url, 'nofollow')
                 . '>' . $m[1] . '</a>';
        },
        $t
    ) ?? $t;
}

/**
 * Le corps complet, en HTML.
 *
 * L'échappement vient EN PREMIER, sur la chaîne entière. Tout ce qui suit
 * travaille sur du texte déjà inoffensif : c'est ce qui rend la fonction
 * sûre par construction plutôt que par vigilance.
 */
function texte_riche(string $brut): string
{
    $lignes = preg_split('/\r\n|\r|\n/', e(trim($brut))) ?: [];
    $html = [];
    $liste = false;

    $fermer_liste = function () use (&$liste, &$html): void {
        if ($liste) {
            $html[] = '</ul>';
            $liste = false;
        }
    };

    $paragraphe = [];
    $vider = function () use (&$paragraphe, &$html): void {
        if ($paragraphe) {
            $html[] = '<p>' . texte_en_ligne(implode('<br>', $paragraphe)) . '</p>';
            $paragraphe = [];
        }
    };

    foreach ($lignes as $ligne) {
        $l = trim($ligne);

        if ($l === '') {
            $vider();
            $fermer_liste();
            continue;
        }
        if (preg_match('/^###\s+(.*)$/', $l, $m)) {
            $vider();
            $fermer_liste();
            $html[] = '<h4>' . texte_en_ligne($m[1]) . '</h4>';
            continue;
        }
        if (preg_match('/^##\s+(.*)$/', $l, $m)) {
            $vider();
            $fermer_liste();
            // `h3` et non `h2` : le titre de l'article est le `h1`, et la
            // page a déjà un `h2`. Sauter un niveau désoriente un lecteur
            // d'écran, qui navigue justement par les titres.
            $html[] = '<h3>' . texte_en_ligne($m[1]) . '</h3>';
            continue;
        }
        if (preg_match('/^&gt;\s?(.*)$/', $l, $m)) {
            $vider();
            $fermer_liste();
            $html[] = '<blockquote>' . texte_en_ligne($m[1]) . '</blockquote>';
            continue;
        }
        /**
         * Une image est un BLOC, jamais une marque en ligne.
         *
         * Une image de 900 px au milieu d'une phrase n'a aucun sens ; et
         * la traiter comme du gras obligerait à décider quoi faire du
         * texte qui l'entoure. Elle occupe sa ligne, seule.
         */
        if (preg_match('/^!\[([^\]]*)\]\(([^)\s]+)\)$/', $l, $m)) {
            $vider();
            $fermer_liste();
            $img = image_article($m[2], $m[1]);
            if ($img !== '') {
                $html[] = $img;
            }
            continue;
        }
        if (preg_match('/^[-*]\s+(.*)$/', $l, $m)) {
            $vider();
            if (!$liste) {
                $html[] = '<ul>';
                $liste = true;
            }
            $html[] = '<li>' . texte_en_ligne($m[1]) . '</li>';
            continue;
        }
        $fermer_liste();
        $paragraphe[] = $l;
    }
    $vider();
    $fermer_liste();

    return implode("\n", $html);
}

/**
 * Une image d'article, et la règle qui décide de la servir ou non.
 *
 * **Seules les images téléversées ici sont rendues.** Une adresse
 * extérieure serait trois problèmes en un : un mouchard posé sur la page
 * de quelqu'un d'autre, une image qui disparaît le jour où le site
 * d'origine ferme, et un contenu mixte qui fait crier le navigateur sur
 * une page en HTTPS. Une adresse non reconnue n'est pas une erreur : la
 * ligne disparaît, et l'auteur le voit dans son aperçu.
 *
 * La légende n'est pas un ornement : c'est le texte que lit quelqu'un qui
 * ne voit pas l'image — parce qu'il est aveugle, ou parce que le réseau a
 * lâché. Vide, l'image est déclarée décorative plutôt que d'être annoncée
 * par son nom de fichier, qui n'apprend rien.
 */
/** La largeur utile de la colonne d'un article, en pixels. Voir `.corps-article`. */
const COLONNE_ARTICLE = 760;

function image_article(string $url, string $legende = ''): string
{
    $url = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
    if (!function_exists('image_reduite') || cle_image($url) === null) {
        return '';
    }
    /**
     * La largeur voulue par l'auteur décide de DEUX choses.
     *
     * De la place que la figure prend dans la colonne, bien sûr ; mais
     * aussi du fichier téléchargé. Une image posée à 40 % s'affiche en
     * 300 px de large : lui envoyer la vignette de 960 serait payer trois
     * fois le transfert pour rien, sur des connexions qui se comptent.
     */
    $pourcent = largeur_de_url($url);
    $affichee = max(160, (int) round(COLONNE_ARTICLE * $pourcent / 100));

    $im = image_reduite($url, $affichee);
    $dim = $im['largeur']
        ? ' width="' . (int) $im['largeur'] . '" height="' . (int) $im['hauteur'] . '"'
        : '';
    $srcset = $im['srcset']
        ? ' srcset="' . e($im['srcset']) . '" sizes="(max-width:820px) 92vw, ' . $affichee . 'px"'
        : '';

    $balise = '<img src="' . e($im['src']) . '"' . $srcset . $dim
            . ' alt="' . e($legende) . '" loading="lazy" decoding="async">';

    // Sous 100 %, la figure est CENTRÉE : une image de 40 % collée au bord
    // gauche ressemble à un défaut de mise en page, pas à une intention.
    $style = $pourcent < 100 ? ' style="width:' . $pourcent . '%"' : '';

    return $legende === ''
        ? '<figure class="image-article"' . $style . '>' . $balise . '</figure>'
        : '<figure class="image-article"' . $style . '>' . $balise
          . '<figcaption>' . texte_en_ligne(e($legende)) . '</figcaption></figure>';
}

/**
 * Un résumé, quand l'auteur n'a pas écrit de chapô.
 *
 * On coupe sur un mot entier : une phrase tronquée au milieu d'un mot
 * ressemble à un bug, pas à un extrait.
 */
function texte_extrait(string $brut, int $max = 180): string
{
    /**
     * Les balises de BLOC deviennent des espaces ; les balises en ligne, rien.
     *
     * Sans espace, la fin d'un titre se colle au début du paragraphe
     * suivant. Mais en mettre partout coupe aussi `<strong>à part</strong>,`
     * et rend « à part , » — une espace avant la virgule, que l'œil
     * attrape aussitôt. La distinction n'est donc pas cosmétique : c'est
     * la différence entre deux mots collés et une ponctuation fautive.
     */
    $plat = strip_tags(preg_replace(
        '~<(/?(?:p|h[1-6]|li|ul|ol|blockquote|br|div|figure|figcaption)\b)~i',
        ' <$1',
        texte_riche($brut)
    ) ?? '');
    $plat = html_entity_decode($plat, ENT_QUOTES, 'UTF-8');
    $plat = trim(preg_replace('/\s+/', ' ', $plat) ?? '');
    if (mb_strlen($plat) <= $max) {
        return $plat;
    }
    $coupe = mb_substr($plat, 0, $max);
    $espace = mb_strrpos($coupe, ' ');
    return rtrim($espace !== false ? mb_substr($coupe, 0, $espace) : $coupe, " ,;:.") . '…';
}

/** Le temps de lecture, à 200 mots la minute. Une info, pas une promesse. */
function texte_minutes(string $brut): int
{
    return max(1, (int) round(str_word_count(strip_tags($brut), 0, 'àâäéèêëïîôöùûüçÀÂÄÉÈÊËÏÎÔÖÙÛÜÇ') / 200));
}
