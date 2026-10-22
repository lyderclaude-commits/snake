<?php
/**
 * Le HTML d'un article, passé au tamis.
 *
 * Deux sources écrivent du HTML ici : l'éditeur de l'administration et
 * l'import WordPress. Ni l'une ni l'autre n'est dangereuse en temps normal,
 * mais un compte d'auteur WordPress compromis suffirait à poser un script
 * sur chaque page du site. On garde donc une liste blanche : des balises
 * de texte, des liens et des images, rien qui s'exécute.
 *
 * Ce qui n'est pas reconnu est « déballé » : la balise tombe, son texte
 * reste. Les `div` et `span` de Gutenberg disparaissent ainsi sans
 * emporter le contenu avec eux.
 */

declare(strict_types=1);

const HTML_BALISES = [
    'p' => [], 'br' => [], 'hr' => [],
    'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
    'strong' => [], 'b' => [], 'em' => [], 'i' => [], 'u' => [], 's' => [], 'mark' => [],
    'sup' => [], 'sub' => [], 'small' => [], 'blockquote' => [], 'cite' => [], 'q' => [],
    'ul' => [], 'ol' => ['start'], 'li' => [],
    'a' => ['href', 'title', 'target'],
    'img' => ['src', 'alt', 'width', 'height', 'title'],
    'figure' => [], 'figcaption' => [],
    'table' => [], 'thead' => [], 'tbody' => [], 'tfoot' => [], 'tr' => [], 'caption' => [],
    'th' => ['colspan', 'rowspan'], 'td' => ['colspan', 'rowspan'],
    'pre' => [], 'code' => [],
    'iframe' => ['src', 'width', 'height', 'title', 'allowfullscreen'],
];

/** Ces balises partent avec tout ce qu'elles contiennent. */
const HTML_INTERDITES = ['script', 'style', 'noscript', 'template', 'form', 'input', 'button', 'select',
    'textarea', 'object', 'embed', 'applet', 'svg', 'math', 'link', 'meta', 'base', 'frame', 'frameset', 'head', 'title'];

/** Les seules vidéos qu'on laisse s'intégrer. */
const HTML_IFRAMES = ['www.youtube.com', 'youtube.com', 'www.youtube-nocookie.com', 'player.vimeo.com',
    'www.facebook.com', 'www.google.com', 'maps.google.com', 'www.instagram.com'];

function html_nettoyer(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }
    $doc = new DOMDocument('1.0', 'UTF-8');
    $prec = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><!DOCTYPE html><html><body><div id="racine">' . $html . '</div></body></html>',
        LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($prec);

    $racine = $doc->getElementById('racine');
    if (!$racine) {
        return '';
    }
    html_tamiser($racine);

    $sortie = '';
    foreach (iterator_to_array($racine->childNodes) as $n) {
        $sortie .= $doc->saveHTML($n);
    }
    // Les paragraphes vides que laissent l'éditeur et Gutenberg.
    $sortie = (string) preg_replace('#<p>(\s|&nbsp;|<br>)*</p>#u', '', $sortie);
    return trim($sortie);
}

function html_tamiser(DOMNode $noeud): void
{
    foreach (iterator_to_array($noeud->childNodes) as $enfant) {
        if ($enfant instanceof DOMComment || $enfant instanceof DOMProcessingInstruction) {
            $noeud->removeChild($enfant);
            continue;
        }
        if (!$enfant instanceof DOMElement) {
            continue;
        }
        $nom = strtolower($enfant->nodeName);
        if (in_array($nom, HTML_INTERDITES, true)) {
            $noeud->removeChild($enfant);
            continue;
        }
        // Le titre de page appartient au gabarit : un h1 du contenu devient h2.
        if ($nom === 'h1') {
            $enfant = html_renommer($enfant, 'h2');
            $nom = 'h2';
        }
        if (!array_key_exists($nom, HTML_BALISES)) {
            html_tamiser($enfant);
            html_deballer($enfant);
            continue;
        }
        html_attributs($enfant, $nom);
        if ($enfant->parentNode === null) {
            continue; // retiré par html_attributs (iframe ou image refusée)
        }
        html_tamiser($enfant);
    }
}

function html_attributs(DOMElement $el, string $nom): void
{
    $permis = HTML_BALISES[$nom];
    foreach (iterator_to_array($el->attributes) as $attr) {
        if (!in_array(strtolower($attr->name), $permis, true)) {
            $el->removeAttribute($attr->name);
        }
    }
    if ($nom === 'a') {
        $href = trim($el->getAttribute('href'));
        if ($href !== '' && !html_adresse_permise($href, true)) {
            $el->removeAttribute('href');
        }
        if ($el->getAttribute('target') !== '') {
            $el->setAttribute('target', '_blank');
            $el->setAttribute('rel', 'noopener');
        }
    } elseif ($nom === 'img') {
        if (!html_adresse_permise(trim($el->getAttribute('src')), false)) {
            $el->parentNode?->removeChild($el);
            return;
        }
        $el->setAttribute('loading', 'lazy');
    } elseif ($nom === 'iframe') {
        $src = trim($el->getAttribute('src'));
        $hote = strtolower((string) parse_url($src, PHP_URL_HOST));
        if (!str_starts_with($src, 'https://') || !in_array($hote, HTML_IFRAMES, true)) {
            $el->parentNode?->removeChild($el);
            return;
        }
        $el->setAttribute('loading', 'lazy');
    }
    foreach (['width', 'height', 'colspan', 'rowspan', 'start'] as $num) {
        if ($el->hasAttribute($num) && !ctype_digit($el->getAttribute($num))) {
            $el->removeAttribute($num);
        }
    }
}

/** http(s), un chemin relatif, une ancre — et pour les liens, mailto et tel. */
function html_adresse_permise(string $u, bool $lien): bool
{
    if ($u === '') {
        return false;
    }
    if (preg_match('#^(/|\#|\.\./|\./)#', $u) || !preg_match('#^[a-z][a-z0-9+.-]*:#i', $u)) {
        return !str_contains($u, '\\');
    }
    $schema = strtolower((string) parse_url($u, PHP_URL_SCHEME));
    return in_array($schema, $lien ? ['http', 'https', 'mailto', 'tel'] : ['http', 'https'], true);
}

function html_deballer(DOMElement $el): void
{
    $parent = $el->parentNode;
    if (!$parent) {
        return;
    }
    // Un bloc déballé garde sa coupure : sans elle, deux paragraphes de
    // Gutenberg enveloppés de <div> se colleraient en une seule ligne.
    $bloc = in_array(strtolower($el->nodeName), ['div', 'section', 'article', 'header', 'footer', 'main', 'aside', 'center'], true);
    while ($el->firstChild) {
        $parent->insertBefore($el->firstChild, $el);
    }
    if ($bloc && $el->nextSibling && !($el->nextSibling instanceof DOMElement)) {
        $parent->insertBefore($el->ownerDocument->createTextNode(' '), $el);
    }
    $parent->removeChild($el);
}

function html_renommer(DOMElement $el, string $nom): DOMElement
{
    $neuf = $el->ownerDocument->createElement($nom);
    while ($el->firstChild) {
        $neuf->appendChild($el->firstChild);
    }
    $el->parentNode->replaceChild($neuf, $el);
    return $neuf;
}

/**
 * Applique une fonction à chaque adresse d'image (et aux liens qui pointent
 * vers une image, ceux des galeries WordPress). Sert à l'import, pour
 * remplacer les images distantes par leurs copies locales.
 *
 * @param callable(string): ?string $remplacer reçoit l'adresse, rend la nouvelle ou null
 */
function html_images_remplacer(string $html, callable $remplacer, ?callable $lien = null): string
{
    if (trim($html) === '') {
        return $html;
    }
    $doc = new DOMDocument('1.0', 'UTF-8');
    $prec = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><!DOCTYPE html><html><body><div id="racine">' . $html . '</div></body></html>', LIBXML_NONET);
    libxml_clear_errors();
    libxml_use_internal_errors($prec);
    $racine = $doc->getElementById('racine');
    if (!$racine) {
        return $html;
    }
    foreach (iterator_to_array($racine->getElementsByTagName('img')) as $img) {
        /** @var DOMElement $img */
        $src = html_meilleure_source($img);
        $neuf = $src !== '' ? $remplacer($src) : null;
        if ($neuf !== null) {
            $img->setAttribute('src', $neuf);
        }
        // Les dimensions annoncées sont celles de la vignette : avec la grande
        // version, elles la réduiraient à 300 px.
        if ($img->getAttribute('srcset') !== '') {
            $img->removeAttribute('width');
            $img->removeAttribute('height');
        }
        $img->removeAttribute('srcset');
        $img->removeAttribute('sizes');
        $img->removeAttribute('data-src');
    }
    foreach (iterator_to_array($racine->getElementsByTagName('a')) as $a) {
        /** @var DOMElement $a */
        $href = trim($a->getAttribute('href'));
        if ($href === '') {
            continue;
        }
        if (preg_match('#\.(jpe?g|png|gif|webp)(\?.*)?$#i', $href)) {
            $neuf = $remplacer($href);
        } else {
            $neuf = $lien ? $lien($href) : null;
        }
        if ($neuf !== null) {
            $a->setAttribute('href', $neuf);
        }
    }
    $sortie = '';
    foreach (iterator_to_array($racine->childNodes) as $n) {
        $sortie .= $doc->saveHTML($n);
    }
    return trim($sortie);
}

/**
 * La plus grande version d'une image WordPress.
 *
 * `src` porte souvent la vignette de 300 px (`photo-300x200.jpg`) ; la
 * vraie taille est dans `srcset`, ou s'obtient en retirant le suffixe.
 * On prend la plus large annoncée par `srcset`, sinon `src` tel quel.
 */
function html_meilleure_source(DOMElement $img): string
{
    $src = trim($img->getAttribute('data-src') ?: $img->getAttribute('src'));
    $srcset = trim($img->getAttribute('srcset'));
    $meilleure = $src;
    $largeur = 0;
    if ($srcset !== '') {
        foreach (explode(',', $srcset) as $cand) {
            $parts = preg_split('/\s+/', trim($cand));
            if (!$parts || $parts[0] === '') {
                continue;
            }
            $w = isset($parts[1]) ? (int) $parts[1] : 0;
            if ($w > $largeur) {
                $largeur = $w;
                $meilleure = $parts[0];
            }
        }
    }
    return html_entity_decode($meilleure, ENT_QUOTES, 'UTF-8');
}
