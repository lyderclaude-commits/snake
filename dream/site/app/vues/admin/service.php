<?php
$nouveau = !$s || empty($s['id']);
$titre = $nouveau ? 'Nouveau service' : 'Modifier le service';
$fr = $s ? (is_array($s['fr']) ? $s['fr'] : (json_decode((string) $s['fr'], true) ?: [])) : [];
$en = $s ? (is_array($s['en']) ? $s['en'] : (json_decode((string) $s['en'], true) ?: [])) : [];
$image = (string) ($s['image'] ?? '');
/** Un champ en deux langues, côte à côte. */
$duo = function (string $k, string $label, string $type = 'text', string $aide = '') use ($fr, $en): string {
    $h = '<div class="duo-langues">';
    foreach (['fr' => $fr, 'en' => $en] as $l => $v) {
        $val = $v[$k] ?? '';
        if (is_array($val)) {
            $val = implode(', ', $val);
        }
        $id = 's-' . $l . '-' . $k;
        $h .= '<div class="champ"><label for="' . $id . '">' . e($label) . '<span class="drapeau">' . strtoupper($l) . '</span></label>';
        $h .= $type === 'textarea'
            ? '<textarea id="' . $id . '" name="' . $l . '[' . $k . ']" rows="3" lang="' . $l . '">' . e((string) $val) . '</textarea>'
            : '<input id="' . $id . '" name="' . $l . '[' . $k . ']" value="' . e((string) $val) . '" lang="' . $l . '">';
        $h .= '</div>';
    }
    return $h . '</div>' . ($aide !== '' ? '<p class="aide" style="margin:-8px 0 14px">' . $aide . '</p>' : '');
};
$livrables_fr = (array) ($fr['livrables'] ?? []);
$livrables_en = (array) ($en['livrables'] ?? []);
$n_livrables = max(4, count($livrables_fr) + 1, count($livrables_en));
?>
<a class="retour" href="<?= url('admin/services') ?>"><i>←</i>Les services</a>
<div class="entete"><div><h1><?= e($titre) ?></h1><p>Le français à gauche, l’anglais à droite. Un champ anglais vide reprend le français sur le site anglais.</p></div>
  <?php if (!$nouveau): ?><div class="actions"><a class="bouton fant" href="<?= url('services#' . $s['ancre']) ?>" target="_blank" rel="noopener">Voir sur le site ↗</a></div><?php endif; ?>
</div>
<?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

<form method="post" action="<?= url('admin/services/' . ($nouveau ? 'nouveau' : (int) $s['id'])) ?>" class="edition">
  <?= csrf_champ() ?>
  <div class="pile">
    <div class="carte">
      <h2>La carte de l’accueil</h2>
      <p class="aide">Une des cartes de « Ce que nous faisons ».</p>
      <?= $duo('titre', 'Titre', 'text') ?>
      <?= $duo('resume', 'Texte de la carte', 'textarea') ?>
      <?= $duo('tags', 'Étiquettes', 'text', 'Séparées par des virgules : Facebook, Instagram, TikTok.') ?>
      <?= $duo('bouton', 'Texte du bouton', 'text', 'Facultatif. « En savoir plus → » si vide ; sur la page Services, « Demander un devis ».') ?>
    </div>
    <div class="carte">
      <h2>La page Services</h2>
      <p class="aide">L’introduction liste tous les services ; ceux qui ont une section détaillée l’ont plus bas, avec leur image.</p>
      <?= $duo('resume_court', 'Résumé dans l’introduction', 'text') ?>
      <?= $duo('titre_detail', 'Titre de la section', 'textarea', 'Une ligne par retour à la ligne ; <code>*mots*</code> en italique doré. Vide : le titre de la carte.') ?>
      <?= $duo('description', 'Accroche', 'textarea') ?>
      <h3 style="margin:8px 0 6px">Ce qui est livré</h3>
      <p class="aide" style="margin:0 0 10px">Les coches de la section : un intitulé et une phrase. Une ligne vide est ignorée.</p>
      <?php for ($i = 0; $i < $n_livrables; $i++): ?>
        <div class="duo-langues">
          <?php foreach (['fr' => $livrables_fr, 'en' => $livrables_en] as $l => $lv): ?>
            <div class="livrable">
              <input name="<?= $l ?>[livrables][titre][]" value="<?= e((string) ($lv[$i]['titre'] ?? '')) ?>" placeholder="Intitulé (<?= strtoupper($l) ?>)" aria-label="Livrable <?= $i + 1 ?>, intitulé <?= strtoupper($l) ?>" lang="<?= $l ?>">
              <input name="<?= $l ?>[livrables][texte][]" value="<?= e((string) ($lv[$i]['texte'] ?? '')) ?>" placeholder="Détail (<?= strtoupper($l) ?>)" aria-label="Livrable <?= $i + 1 ?>, détail <?= strtoupper($l) ?>" lang="<?= $l ?>">
            </div>
          <?php endforeach; ?>
        </div>
      <?php endfor; ?>
      <h3 style="margin:14px 0 6px">Le prix</h3>
      <?= $duo('prix_libelle', 'Libellé', 'text', '« À partir de », « Sur devis selon projet »…') ?>
      <?= $duo('prix_valeur', 'Montant', 'text', '« 75 000 FCFA/mois », « Dès 100€ »…') ?>
      <?= $duo('etiquette', 'Étiquette sur l’image', 'text') ?>
    </div>
  </div>
  <aside class="edition-cote">
    <div class="carte">
      <h2>Publication</h2>
      <div class="pile" style="gap:10px;margin:12px 0 16px">
        <label class="case"><input type="checkbox" name="visible" value="1"<?= ($s['visible'] ?? 1) ? ' checked' : '' ?>> Visible sur le site</label>
        <label class="case"><input type="checkbox" name="detail" value="1"<?= ($s['detail'] ?? 1) ? ' checked' : '' ?>> Section détaillée sur la page Services</label>
        <label class="case"><input type="checkbox" name="vedette" value="1"<?= !empty($s['vedette']) ? ' checked' : '' ?>> Carte mise en avant (fond noir)</label>
      </div>
      <button class="bouton large" type="submit">Enregistrer</button>
    </div>
    <div class="carte">
      <h2>Repères</h2>
      <div class="champ"><label for="numero">Numéro affiché</label><input id="numero" name="numero" value="<?= e((string) ($s['numero'] ?? '')) ?>" maxlength="4" placeholder="01, ★…"></div>
      <div class="champ"><label for="ancre">Ancre</label><input id="ancre" name="ancre" value="<?= e((string) ($s['ancre'] ?? '')) ?>" placeholder="déduite du titre">
        <p class="aide">Le lien <code>/services#<?= e((string) ($s['ancre'] ?? 'ancre')) ?></code>. La changer casse les liens déjà partagés.</p></div>
      <div class="champ" style="margin:0"><label for="icone">Pictogramme</label>
        <select id="icone" name="icone"><?php foreach (ICONES_SERVICE as $k => $l): ?><option value="<?= e($k) ?>"<?= ($s['icone'] ?? 'cible') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
    </div>
    <div class="carte">
      <h2>L’image</h2>
      <p class="aide">Pour la section détaillée, format paysage.</p>
      <div class="couverture" data-couverture><?php if ($image !== ''): ?><img src="<?= e(image_url($image)) ?>" alt=""><?php else: ?>Aucune image<?php endif; ?></div>
      <input type="hidden" name="image" value="<?= e($image) ?>" data-couverture-champ>
      <div class="rangee" style="gap:8px">
        <button class="bouton fant petit" type="button" data-choisir-couverture>Choisir une image</button>
        <button class="bouton fant petit" type="button" data-retirer-couverture<?= $image === '' ? ' hidden' : '' ?>>Retirer</button>
      </div>
    </div>
  </aside>
</form>
<?php if (!$nouveau): ?>
<form method="post" action="<?= url('admin/services/' . (int) $s['id'] . '/supprimer') ?>" data-confirmer="Supprimer ce service ? Il disparaîtra de l’accueil et de la page Services." style="margin-top:28px">
  <?= csrf_champ() ?><button class="bouton danger fant" type="submit">Supprimer le service</button>
  <span class="aide" style="margin-left:10px">Pour le retirer un temps, décochez plutôt « Visible ».</span>
</form>
<?php endif; ?>
<?php require __DIR__ . '/_selecteur.php'; ?>
