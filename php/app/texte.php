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
            $interne = str_starts_with($url, base_url());
            return '<a href="' . e($url) . '"'
                 . ($interne ? '' : ' target="_blank" rel="noopener nofollow"')
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
 * Un résumé, quand l'auteur n'a pas écrit de chapô.
 *
 * On coupe sur un mot entier : une phrase tronquée au milieu d'un mot
 * ressemble à un bug, pas à un extrait.
 */
function texte_extrait(string $brut, int $max = 180): string
{
    // Les balises deviennent des ESPACES, pas rien : sans cela, la fin d'un
    // titre se collerait au début du paragraphe suivant.
    $plat = strip_tags(str_replace('<', ' <', texte_riche($brut)));
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
