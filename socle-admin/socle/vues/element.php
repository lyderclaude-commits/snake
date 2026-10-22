<?php
/**
 * La fiche d'un élément, dessinée d'après la déclaration de sa collection :
 * les champs dans des cartes (par `section`), les cases et les images sur
 * le côté, la version anglaise repliée en bas.
 */
require_once __DIR__ . '/_champs.php';
$nouveau = !$e || empty($e['id']);
$titre = $nouveau ? collection_mot($col, 'Nouveau', 'Nouvelle') . ' ' . $col['element'] : element_titre($e);
$valeur = fn(string $k) => $e[$k] ?? $col['champs'][$k]['defaut'];
$en = (array) ($e['_en'] ?? []);
$bilingue = isset(LANGUES['en']) && array_filter($col['champs'], fn($c) => $c['traduit']);

// Les cartes : la première sans titre, puis une par section, dans l'ordre de la déclaration.
$cartes = [];
$cote = [];
foreach ($col['champs'] as $k => $c) {
    if ($c['cote']) {
        $cote[$k] = $c;
    } else {
        $cartes[$c['section'] ?? ''][$k] = $c;
    }
}
/** Les champs d'une carte : les courts côte à côte, les longs sur toute la largeur. */
$dessiner = function (array $champs, bool $anglais) use ($valeur, $en, $e): string {
    $h = '';
    $rang = '';
    $vider = function () use (&$h, &$rang) {
        if ($rang !== '') {
            $h .= '<div class="champs">' . $rang . '</div>';
            $rang = '';
        }
    };
    foreach ($champs as $k => $c) {
        if ($anglais && !$c['traduit']) {
            continue;
        }
        $nom = ($anglais ? 'en' : 'c') . '[' . $k . ']';
        if ($c['type'] === 'repetition') {
            $vider();
            $h .= '<h3 style="margin:6px 0 4px">' . e($c['libelle']) . '</h3>' . ($c['aide'] !== '' && !$anglais ? '<p class="aide">' . $c['aide'] . '</p>' : '')
                . repetition_html($c, $nom, $anglais ? (array) ($en[$k] ?? []) : (array) $valeur($k), ['en' => $anglais, 'fr' => (array) ($e[$k] ?? []), 'id' => ($anglais ? 'en-' : '') . $k]);
            continue;
        }
        $v = $anglais ? ($en[$k] ?? '') : $valeur($k);
        // Les identifiants : `titre` pour le français, `en-titre` pour l'anglais.
        $o = $anglais ? ['en' => true, 'aide' => '', 'id' => 'en-' . $k, 'placeholder' => is_array($e[$k] ?? null) ? implode(', ', $e[$k]) : (string) ($e[$k] ?? '')] : ['id' => $k];
        if ($c['type'] === 'html' || $c['type'] === 'texte_long') {
            $o['placeholder'] = $anglais ? '' : $c['placeholder'];
        }
        $html = champ_html($c, $nom, $v, $o);
        if (in_array($c['type'], CHAMP_COURTS, true)) {
            $rang .= $html;
        } else {
            $vider();
            $h .= $html;
        }
    }
    $vider();
    return $h;
};
?>
<a class="retour" href="<?= url('admin/' . $col['nom']) ?>"><i>←</i><?= e($col['titre']) ?></a>
<div class="entete"><div><h1><?= e($titre) ?></h1><?php if ($col['description'] !== '' && $nouveau): ?><p><?= $col['description'] ?></p><?php endif; ?></div></div>
<?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

<form method="post" action="<?= url('admin/' . $col['nom'] . '/' . ($nouveau ? 'nouveau' : (int) $e['id'])) ?>" class="edition">
  <?= csrf_champ() ?>
  <div class="pile">
    <?php foreach ($cartes as $section => $champs): ?>
      <div class="carte">
        <?php if ($section !== ''): ?><h2><?= e($section) ?></h2><?php endif; ?>
        <?= $dessiner($champs, false) ?>
      </div>
    <?php endforeach; ?>
    <?php if ($bilingue): ?>
      <details class="carte version-en"<?= $en ? ' open' : '' ?>>
        <summary><span class="drapeau">EN</span> <b>Version anglaise</b>
          <span class="pastille <?= $en ? 'publie' : 'brouillon' ?>"><?= $en ? 'Traduite' : 'Pas encore traduite' ?></span></summary>
        <p class="aide">Ce qui s’affiche sur le site anglais. Un champ vide reprend le français.</p>
        <?= $dessiner(array_merge(...array_values($cartes ?: [[]])) + $cote, true) ?>
      </details>
    <?php endif; ?>
  </div>
  <aside class="edition-cote">
    <div class="carte">
      <h2>Publication</h2>
      <div class="pile" style="gap:10px;margin:12px 0 16px">
        <label class="case"><input type="checkbox" name="visible" value="1"<?= ($e['visible'] ?? 1) ? ' checked' : '' ?>> <?= e($col['visible']['libelle']) ?></label>
        <?php foreach ($cote as $k => $c): if ($c['type'] === 'image') continue; ?>
          <?= champ_html($c, 'c[' . $k . ']', $valeur($k), ['id' => $k]) ?>
        <?php endforeach; ?>
      </div>
      <button class="bouton large" type="submit">Enregistrer</button>
    </div>
    <?php foreach ($cote as $k => $c): if ($c['type'] !== 'image') continue; ?>
      <div class="carte">
        <h2><?= e($c['libelle']) ?></h2>
        <?php if ($c['aide'] !== ''): ?><p class="aide"><?= $c['aide'] ?></p><?php endif; ?>
        <?= champ_controle($c, 'c[' . $k . ']', $valeur($k)) ?>
      </div>
    <?php endforeach; ?>
  </aside>
</form>
<?php if (!$nouveau): ?>
<form method="post" action="<?= url('admin/' . $col['nom'] . '/' . (int) $e['id'] . '/supprimer') ?>" data-confirmer="Supprimer définitivement « <?= e(element_titre($e)) ?> » ?" style="margin-top:28px">
  <?= csrf_champ() ?><button class="bouton danger fant" type="submit">Supprimer</button>
</form>
<?php endif; ?>
<?php if (collection_champs_de_type($col['nom'], 'image')) require __DIR__ . '/_selecteur.php'; ?>
