<?php
/**
 * Les collections : les contenus d'un site qui ne sont ni des articles ni
 * des réglages — des services, des témoignages, des logos, des réalisations,
 * une équipe, des questions fréquentes…
 *
 * Une collection se DÉCLARE dans la définition du site (`collections`) : un
 * nom, des champs typés. Le socle en tire tout seul la liste ordonnable,
 * la fiche d'édition, la version anglaise, la recherche, le rapatriement
 * des images — sans une ligne de SQL ni de formulaire à écrire.
 *
 * Tous les éléments vivent dans une seule table, `elements` : les valeurs
 * françaises (et celles qui ne se traduisent pas) dans `donnees`, les
 * traductions dans `en`. Un champ anglais vide retombe sur le français, si
 * bien qu'une traduction incomplète ne laisse jamais de trou sur le site.
 */

declare(strict_types=1);

const CHAMP_TYPES = ['texte', 'texte_long', 'html', 'nombre', 'case', 'choix', 'note', 'image', 'lien', 'email',
    'etiquettes', 'repetition', 'slug'];

/** Les types qui tiennent sur une demi-ligne dans la fiche. */
const CHAMP_COURTS = ['texte', 'nombre', 'choix', 'note', 'lien', 'email', 'slug'];

/** Un champ déclaré, complété de ses valeurs par défaut. */
function champ_normaliser(string $k, array $c): array
{
    $type = in_array($c['type'] ?? 'texte', CHAMP_TYPES, true) ? (string) ($c['type'] ?? 'texte') : 'texte';
    $n = [
        'cle' => $k,
        'type' => $type,
        'libelle' => (string) ($c['libelle'] ?? ucfirst(str_replace('_', ' ', $k))),
        'aide' => (string) ($c['aide'] ?? ''),
        'requis' => !empty($c['requis']),
        'traduit' => !empty($c['traduit']),
        'unique' => !empty($c['unique']) || $type === 'slug',
        'max' => (int) ($c['max'] ?? match ($type) { 'texte_long' => 2000, 'html' => 100000, 'lien' => 300, default => 200 }),
        'defaut' => $c['defaut'] ?? match ($type) { 'case' => 0, 'nombre' => 0, 'note' => 5, 'etiquettes', 'repetition' => [], default => '' },
        'options' => (array) ($c['options'] ?? []),
        'section' => isset($c['section']) ? (string) $c['section'] : null,
        'cote' => (bool) ($c['cote'] ?? in_array($type, ['case', 'image'], true)),
        'placeholder' => (string) ($c['placeholder'] ?? ''),
        'lignes' => (int) ($c['lignes'] ?? ($type === 'html' ? 10 : 3)),
        'min' => isset($c['min']) ? (int) $c['min'] : null,
        'source' => (string) ($c['source'] ?? ''),
        'lignes_max' => (int) ($c['lignes_max'] ?? 12),
        'sous' => [],
    ];
    if ($type === 'note') {
        $n['options'] = [5 => '★★★★★', 4 => '★★★★', 3 => '★★★', 2 => '★★', 1 => '★'];
    }
    if ($type === 'repetition') {
        foreach ((array) ($c['champs'] ?? []) as $sk => $sc) {
            $n['sous'][$sk] = champ_normaliser((string) $sk, (array) $sc);
        }
        // Une ligne est traduite dès qu'un de ses sous-champs l'est.
        $n['traduit'] = (bool) array_filter($n['sous'], fn($s) => $s['traduit']);
    }
    return $n;
}

/**
 * Les collections déclarées, complétées.
 *
 * @return array<string, array>
 */
function collections(): array
{
    static $l = null;
    if ($l !== null) {
        return $l;
    }
    $l = [];
    foreach ((array) def('collections', []) as $nom => $c) {
        $champs = [];
        foreach ((array) ($c['champs'] ?? []) as $k => $ch) {
            $champs[$k] = champ_normaliser((string) $k, (array) $ch);
        }
        $premier = array_key_first($champs) ?? 'titre';
        $l[$nom] = [
            'nom' => $nom,
            'titre' => (string) ($c['titre'] ?? ucfirst($nom)),
            // « service », « campagne » : pour « Nouveau service », « Campagne enregistrée ».
            'element' => (string) ($c['element'] ?? 'élément'),
            'feminin' => !empty($c['feminin']),
            'description' => (string) ($c['description'] ?? ''),
            'aide_menu' => (string) ($c['aide_menu'] ?? ''),
            'groupe' => (string) ($c['groupe'] ?? 'Contenus'),
            'titre_champ' => (string) ($c['titre_champ'] ?? $premier),
            'champs' => $champs,
            'tri' => (array) ($c['tri'] ?? [['ordre', 'asc'], ['id', 'asc']]),
            'visible' => (array) ($c['visible'] ?? []) + ['libelle' => 'Visible sur le site', 'oui' => 'Visible', 'non' => 'Masqué'],
            'sous_titre' => $c['sous_titre'] ?? null,
            'apercu' => $c['apercu'] ?? null,
            'colonnes' => (array) ($c['colonnes'] ?? []),
            'pastilles' => $c['pastilles'] ?? null,
            'valider' => $c['valider'] ?? null,
            'vide' => (string) ($c['vide'] ?? 'Rien pour l’instant.'),
            'editeur' => (bool) ($c['editeur'] ?? true),
        ];
    }
    return $l;
}

function collection(string $nom): ?array
{
    return collections()[$nom] ?? null;
}

/** Les clés des champs d'un type donné (les images à rapatrier, par exemple). */
function collection_champs_de_type(string $nom, string $type): array
{
    $c = collection($nom);
    return $c ? array_keys(array_filter($c['champs'], fn($ch) => $ch['type'] === $type)) : [];
}

/** « Nouveau service », « Nouvelle campagne ». */
function collection_mot(array $col, string $masculin, string $feminin): string
{
    return $col['feminin'] ? $feminin : $masculin;
}

/* ------------------------------------------------------------------ */
/* Lecture                                                             */
/* ------------------------------------------------------------------ */

/** Une ligne de la table, décodée : les champs à plat, et la traduction à côté (`_en`). */
function element_decoder(array $r): array
{
    $col = collection((string) $r['collection']);
    $d = json_decode((string) $r['donnees'], true) ?: [];
    $e = [];
    foreach ($col['champs'] ?? [] as $k => $c) {
        $e[$k] = $d[$k] ?? $c['defaut'];
    }
    $e += $d;
    $e['id'] = (int) $r['id'];
    $e['collection'] = (string) $r['collection'];
    $e['ordre'] = (int) $r['ordre'];
    $e['visible'] = (int) $r['visible'];
    $e['cree_le'] = $r['cree_le'];
    $e['modifie_le'] = $r['modifie_le'];
    $e['_en'] = json_decode((string) $r['en'], true) ?: [];
    return $e;
}

/** Une valeur est-elle remplie ? (`0` l'est, `''` et `[]` non) */
function rempli(mixed $v): bool
{
    return $v !== null && $v !== '' && $v !== [];
}

/**
 * Un élément dans une langue : chaque champ traduit prend sa valeur
 * anglaise si elle existe. Les lignes d'une répétition se traduisent une
 * à une, sous-champ par sous-champ — un chiffre (« +340% ») reste celui du
 * français, seul son libellé change.
 */
function element_localiser(array $e, ?string $l = null): array
{
    $l ??= langue();
    $col = collection($e['collection']);
    if ($l === 'fr' || !$col) {
        return $e;
    }
    $en = (array) ($e['_en'] ?? []);
    foreach ($col['champs'] as $k => $c) {
        if (!$c['traduit'] || !rempli($en[$k] ?? null)) {
            continue;
        }
        if ($c['type'] === 'repetition') {
            $lignes = (array) $e[$k];
            foreach ($lignes as $i => $ligne) {
                foreach ((array) ($en[$k][$i] ?? []) as $sk => $v) {
                    if (($c['sous'][$sk]['traduit'] ?? false) && rempli($v)) {
                        $lignes[$i][$sk] = $v;
                    }
                }
            }
            $e[$k] = $lignes;
        } else {
            $e[$k] = $en[$k];
        }
    }
    return $e;
}

/** L'élément a-t-il sa version anglaise ? (son champ titre est traduit) */
function element_traduit(array $e): bool
{
    $col = collection($e['collection']);
    return $col !== null && rempli($e['_en'][$col['titre_champ']] ?? null);
}

/** Le titre d'un élément, pour les listes et les messages. */
function element_titre(array $e): string
{
    $col = collection($e['collection']);
    $v = $col ? ($e[$col['titre_champ']] ?? '') : '';
    return is_scalar($v) && trim((string) $v) !== '' ? (string) $v : '#' . $e['id'];
}

/** Trie une liste selon la déclaration (`[['vedette', 'desc'], ['ordre', 'asc']]`). */
function elements_trier(array $l, array $tri): array
{
    usort($l, function (array $a, array $b) use ($tri): int {
        foreach ($tri as [$k, $sens]) {
            $x = $a[$k] ?? null;
            $y = $b[$k] ?? null;
            $c = is_numeric($x) && is_numeric($y) ? $x <=> $y : strcmp((string) (is_scalar($x) ? $x : ''), (string) (is_scalar($y) ? $y : ''));
            if ($c !== 0) {
                return $sens === 'desc' ? -$c : $c;
            }
        }
        return 0;
    });
    return $l;
}

/**
 * Les éléments d'une collection, pour le site : visibles, triés, dans la
 * langue de la page.
 *
 * @param array{tous?: bool, limite?: int, langue?: string} $o
 */
function elements(string $nom, array $o = []): array
{
    $col = collection($nom);
    if (!$col) {
        return [];
    }
    $sql = 'SELECT * FROM elements WHERE collection = ?' . (empty($o['tous']) ? ' AND visible = 1' : '');
    $l = elements_trier(array_map('element_decoder', tous($sql, [$nom])), $col['tri']);
    if (!empty($o['limite'])) {
        $l = array_slice($l, 0, (int) $o['limite']);
    }
    $langue = $o['langue'] ?? langue();
    return array_map(fn($e) => element_localiser($e, $langue), $l);
}

function element(int $id, ?string $collection = null): ?array
{
    $r = un('SELECT * FROM elements WHERE id = ?' . ($collection !== null ? ' AND collection = ?' : ''),
        $collection !== null ? [$id, $collection] : [$id]);
    return $r && collection($r['collection']) ? element_decoder($r) : null;
}

function elements_compter(string $nom, bool $visibles = false): int
{
    return (int) valeur('SELECT COUNT(*) FROM elements WHERE collection = ?' . ($visibles ? ' AND visible = 1' : ''), [$nom]);
}

/* ------------------------------------------------------------------ */
/* Écriture                                                            */
/* ------------------------------------------------------------------ */

function json_net(array $v): string
{
    // Barres obliques non échappées : `uploads/2025/…` se retrouve tel quel par LIKE.
    return json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Crée un élément à partir de valeurs déjà propres — pour les contenus
 * qu'un site pose à son installation.
 */
function element_creer(string $nom, array $donnees, array $en = [], bool $visible = true, ?int $ordre = null, ?int $id = null): int
{
    $t = maintenant();
    $ordre ??= (int) valeur('SELECT COALESCE(MAX(ordre), 0) + 1 FROM elements WHERE collection = ?', [$nom]);
    q('INSERT INTO elements (' . ($id !== null ? 'id, ' : '') . 'collection, donnees, en, ordre, visible, cree_le, modifie_le) VALUES ('
        . ($id !== null ? '?, ' : '') . '?, ?, ?, ?, ?, ?, ?)',
        array_merge($id !== null ? [$id] : [], [$nom, json_net($donnees), json_net(array_filter($en, 'rempli')), $ordre, $visible ? 1 : 0, $t, $t]));
    return (int) db()->lastInsertId();
}

/** Change une seule valeur (une image rapatriée, par exemple). */
function element_modifier_champ(int $id, string $champ, mixed $valeur): void
{
    $d = json_decode((string) valeur('SELECT donnees FROM elements WHERE id = ?', [$id]), true) ?: [];
    $d[$champ] = $valeur;
    q('UPDATE elements SET donnees = ? WHERE id = ?', [json_net($d), $id]);
}

function image_saisie(string $v): string
{
    $v = trim($v);
    return $v !== '' && preg_match('#^(https?://|uploads/|assets/)#', $v) ? $v : '';
}

function lien_saisie(string $v): string
{
    $v = trim($v);
    return preg_match('#^https?://#i', $v) ? mb_substr($v, 0, 300) : '';
}

/** Une valeur saisie, rendue propre selon le type de son champ. */
function champ_saisir(array $c, mixed $brut, array $fr = []): mixed
{
    $texte = fn($v) => mb_substr(trim(str_replace("\r", '', is_scalar($v) ? (string) $v : '')), 0, $c['max']);
    switch ($c['type']) {
        case 'texte_long':
        case 'texte':
            return $texte($brut);
        case 'html':
            return contenu_stocker(is_string($brut) ? $brut : '');
        case 'email':
            $v = $texte($brut);
            return filter_var($v, FILTER_VALIDATE_EMAIL) ? $v : '';
        case 'lien':
            return lien_saisie(is_string($brut) ? $brut : '');
        case 'image':
            return image_saisie(is_string($brut) ? $brut : '');
        case 'case':
            return $brut ? 1 : 0;
        case 'nombre':
            $n = (int) (is_scalar($brut) ? $brut : 0);
            if ($c['min'] !== null) {
                $n = max($c['min'], $n);
            }
            return $n;
        case 'note':
            return max(1, min(5, (int) (is_scalar($brut) ? $brut : 5)));
        case 'choix':
            $v = is_scalar($brut) ? (string) $brut : '';
            return array_key_exists($v, $c['options']) ? $v : (string) ($c['defaut'] !== '' ? $c['defaut'] : (array_key_first($c['options']) ?? ''));
        case 'etiquettes':
            return liste_depuis_saisie(is_string($brut) ? $brut : implode(',', (array) $brut));
        case 'slug':
            $v = $texte($brut);
            return slugifier($v !== '' ? $v : (string) ($fr[$c['source']] ?? ''), '');
        case 'repetition':
            $lignes = [];
            foreach (is_array($brut) ? $brut : [] as $ligne) {
                $propre = [];
                foreach ($c['sous'] as $sk => $sc) {
                    $propre[$sk] = champ_saisir($sc, $ligne[$sk] ?? '');
                }
                if (array_filter($propre, fn($v) => rempli($v) && $v !== 0)) {
                    $lignes[] = $propre;
                }
            }
            return array_slice($lignes, 0, $c['lignes_max']);
    }
    return $texte($brut);
}

/**
 * La saisie d'une fiche, rangée : `[donnees, en]`. Seuls les champs
 * traduits ont une version anglaise ; une répétition anglaise garde
 * l'alignement de ses lignes sur le français.
 *
 * @return array{0: array, 1: array}
 */
function element_saisir(array $col, array $saisie_fr, array $saisie_en): array
{
    $fr = [];
    foreach ($col['champs'] as $k => $c) {
        if ($c['type'] !== 'slug') {
            $fr[$k] = champ_saisir($c, $saisie_fr[$k] ?? ($c['type'] === 'case' ? 0 : ''), $fr);
        }
    }
    // Les adresses (slug) en dernier : elles se déduisent des autres champs.
    foreach ($col['champs'] as $k => $c) {
        if ($c['type'] === 'slug') {
            $fr[$k] = champ_saisir($c, $saisie_fr[$k] ?? '', $fr);
        }
    }
    $en = [];
    if (isset(LANGUES['en'])) {
        foreach ($col['champs'] as $k => $c) {
            if (!$c['traduit']) {
                continue;
            }
            if ($c['type'] === 'repetition') {
                $lignes = [];
                foreach ((array) $fr[$k] as $i => $ligne_fr) {
                    $ligne = [];
                    foreach ($c['sous'] as $sk => $sc) {
                        if ($sc['traduit']) {
                            $v = champ_saisir($sc, $saisie_en[$k][$i][$sk] ?? '');
                            if (rempli($v)) {
                                $ligne[$sk] = $v;
                            }
                        }
                    }
                    $lignes[] = $ligne;
                }
                while ($lignes && end($lignes) === []) {
                    array_pop($lignes);
                }
                $en[$k] = $lignes;
            } else {
                $en[$k] = champ_saisir($c, $saisie_en[$k] ?? '');
            }
        }
        $en = array_filter($en, 'rempli');
    }
    return [$fr, $en];
}

/** Ce qui empêche d'enregistrer, ou null. */
function element_verifier(array $col, array $fr, array $en, ?int $id): ?string
{
    foreach ($col['champs'] as $k => $c) {
        if ($c['requis'] && !rempli($fr[$k] ?? null)) {
            return 'Le champ « ' . $c['libelle'] . ' » est obligatoire.';
        }
    }
    foreach ($col['champs'] as $k => $c) {
        if (!$c['unique'] || !rempli($fr[$k] ?? null)) {
            continue;
        }
        foreach (tous('SELECT id, donnees FROM elements WHERE collection = ? AND id IS NOT ?', [$col['nom'], $id]) as $r) {
            $d = json_decode($r['donnees'], true) ?: [];
            if (($d[$k] ?? null) === $fr[$k]) {
                return 'Un autre élément utilise déjà « ' . $fr[$k] . ' » comme ' . mb_strtolower($c['libelle']) . '.';
            }
        }
    }
    if (is_callable($col['valider'])) {
        $err = ($col['valider'])($fr, $en, $id);
        if (is_string($err) && $err !== '') {
            return $err;
        }
    }
    return null;
}

/**
 * Enregistre une fiche saisie dans l'administration.
 *
 * @return array{0: ?string, 1: int, 2: array} l'erreur, l'identifiant, l'élément tel que saisi
 */
function element_enregistrer(array $col, ?array $existant, array $post): array
{
    [$fr, $en] = element_saisir($col, (array) ($post['c'] ?? []), (array) ($post['en'] ?? []));
    $visible = isset($post['visible']) ? 1 : 0;
    $id = $existant ? (int) $existant['id'] : null;
    $saisi = $fr + ['id' => $id, 'collection' => $col['nom'], 'visible' => $visible, 'ordre' => $existant['ordre'] ?? 0, '_en' => $en];
    $erreur = element_verifier($col, $fr, $en, $id);
    if ($erreur !== null) {
        return [$erreur, (int) $id, $saisi];
    }
    if ($existant) {
        // Les données inconnues de la déclaration (un champ retiré depuis) sont gardées, pas effacées.
        $avant = json_decode((string) valeur('SELECT donnees FROM elements WHERE id = ?', [$id]), true) ?: [];
        q('UPDATE elements SET donnees = ?, en = ?, visible = ?, modifie_le = ? WHERE id = ?',
            [json_net($fr + $avant), json_net($en), $visible, maintenant(), $id]);
        return [null, (int) $id, $saisi];
    }
    return [null, element_creer($col['nom'], $fr, $en, (bool) $visible), $saisi];
}
