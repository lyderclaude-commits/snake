<?php
/**
 * Le dessin d'un champ déclaré : le même pour la fiche d'un élément et
 * pour les réglages. `$nom` est le nom HTML (`c[titre]`, `en[titre]`,
 * `hero_titre`), `$valeur` la valeur rangée.
 */

declare(strict_types=1);

if (!function_exists('champ_html')) {
    function champ_id(string $nom): string
    {
        return 'f-' . trim((string) preg_replace('/[^a-z0-9]+/i', '-', $nom), '-');
    }

    /** Le contrôle seul (sans libellé). */
    function champ_controle(array $c, string $nom, mixed $valeur, array $o = []): string
    {
        $id = e((string) ($o['id'] ?? champ_id($nom)));
        $lang = !empty($o['en']) ? ' lang="en"' : '';
        $ph = (string) ($o['placeholder'] ?? $c['placeholder']);
        $ph = $ph !== '' ? ' placeholder="' . e($ph) . '"' : '';
        $req = !empty($c['requis']) && empty($o['en']) ? ' required' : '';
        $max = in_array($c['type'], ['texte', 'texte_long', 'email', 'lien'], true) && $c['max'] > 0 ? ' maxlength="' . $c['max'] . '"' : '';
        $v = is_scalar($valeur) ? (string) $valeur : '';
        switch ($c['type']) {
            case 'texte_long':
            case 'html':
                return '<textarea id="' . $id . '" name="' . e($nom) . '" rows="' . $c['lignes'] . '"' . $max . $req . $ph . $lang . '>' . e($v) . '</textarea>';
            case 'nombre':
                return '<input id="' . $id . '" name="' . e($nom) . '" type="number"' . ($c['min'] !== null ? ' min="' . $c['min'] . '"' : '') . ' value="' . e($v) . '"' . $req . $ph . '>';
            case 'email':
                return '<input id="' . $id . '" name="' . e($nom) . '" type="email" value="' . e($v) . '"' . $max . $req . $ph . '>';
            case 'lien':
                return '<input id="' . $id . '" name="' . e($nom) . '" type="url" value="' . e($v) . '"' . $max . $req . ($ph ?: ' placeholder="https://…"') . '>';
            case 'choix':
            case 'note':
                $h = '<select id="' . $id . '" name="' . e($nom) . '"' . $req . '>';
                foreach ($c['options'] as $k => $l) {
                    $h .= '<option value="' . e((string) $k) . '"' . ((string) $k === $v ? ' selected' : '') . '>' . e((string) $l) . '</option>';
                }
                return $h . '</select>';
            case 'etiquettes':
                $v = is_array($valeur) ? implode(', ', $valeur) : $v;
                return '<input id="' . $id . '" name="' . e($nom) . '" value="' . e($v) . '"' . $ph . $lang . '>';
            case 'case':
                return '<label class="case"><input type="checkbox" id="' . $id . '" name="' . e($nom) . '" value="1"' . ($valeur ? ' checked' : '') . '> ' . e($c['libelle']) . '</label>';
            case 'image':
                $img = $v !== '' ? '<img src="' . e(image_url($v)) . '" alt="">' : e((string) ($o['vide'] ?? 'Aucune image'));
                return '<div data-image><div class="' . e((string) ($o['cadre'] ?? 'couverture')) . '" data-couverture data-vide="' . e((string) ($o['vide'] ?? 'Aucune image')) . '">' . $img . '</div>'
                    . '<input type="hidden" name="' . e($nom) . '" value="' . e($v) . '" data-couverture-champ>'
                    . '<div class="rangee" style="gap:8px"><button class="bouton fant petit" type="button" data-choisir-couverture>Choisir une image</button>'
                    . '<button class="bouton fant petit" type="button" data-retirer-couverture' . ($v === '' ? ' hidden' : '') . '>Retirer</button></div>'
                    . (str_starts_with($v, 'http') ? '<p class="aide">Cette image est encore hébergée ailleurs. <a href="' . url('admin/import') . '">La rapatrier</a>.</p>' : '')
                    . '</div>';
        }
        return '<input id="' . $id . '" name="' . e($nom) . '" value="' . e($v) . '"' . $max . $req . $ph . $lang . '>';
    }

    /** Le champ complet : libellé, contrôle, aide. */
    function champ_html(array $c, string $nom, mixed $valeur, array $o = []): string
    {
        if ($c['type'] === 'case') {
            return '<div class="champ">' . champ_controle($c, $nom, $valeur, $o) . ($c['aide'] !== '' ? '<p class="aide">' . $c['aide'] . '</p>' : '') . '</div>';
        }
        $libelle = (string) ($o['libelle'] ?? $c['libelle']);
        $h = '<div class="champ"><label for="' . e((string) ($o['id'] ?? champ_id($nom))) . '">' . e($libelle)
            . (!empty($c['requis']) && empty($o['en']) ? ' <span class="obligatoire">*</span>' : '') . '</label>'
            . champ_controle($c, $nom, $valeur, $o);
        $aide = (string) ($o['aide'] ?? $c['aide']);
        if ($c['type'] === 'etiquettes' && $aide === '') {
            $aide = 'Séparés par des virgules.';
        }
        return $h . ($aide !== '' ? '<p class="aide">' . $aide . '</p>' : '') . '</div>';
    }

    /**
     * Une répétition : les lignes enregistrées, puis deux lignes vides pour
     * en ajouter (sans dépasser le maximum). En anglais, une ligne par ligne
     * française, avec le français en indication.
     */
    function repetition_html(array $c, string $nom, array $lignes, array $o = []): string
    {
        $en = !empty($o['en']);
        $fr = (array) ($o['fr'] ?? []);
        $sous = $en ? array_filter($c['sous'], fn($s) => $s['traduit']) : $c['sous'];
        $n = $en ? count($fr) : min($c['lignes_max'], count($lignes) + 2);
        if ($en && $n === 0) {
            return '<p class="aide">Remplissez d’abord les lignes en français.</p>';
        }
        $h = '<div class="repetition" style="--colonnes:' . count($sous) . '">';
        for ($i = 0; $i < $n; $i++) {
            $h .= '<div class="repetition-ligne">';
            foreach ($sous as $sk => $sc) {
                $ph = $en ? (string) ($fr[$i][$sk] ?? '') : $sc['placeholder'];
                $etiquette = $sc['libelle'] . ($en ? '' : ' ' . ($i + 1));
                if ($en && !$sc['traduit']) {
                    continue;
                }
                $h .= champ_html(array_merge($sc, ['requis' => false]), $nom . '[' . $i . '][' . $sk . ']', $lignes[$i][$sk] ?? '',
                    ['en' => $en, 'placeholder' => $ph, 'libelle' => $etiquette, 'aide' => '', 'id' => ($o['id'] ?? champ_id($nom)) . '-' . $i . '-' . $sk]);
            }
            $h .= '</div>';
        }
        return $h . '</div>';
    }
}
