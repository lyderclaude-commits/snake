<?php $titre = 'Modifier ' . $c['nom']; ?>
<a class="retour" href="<?= url('admin/clients') ?>"><i>←</i>Logos clients</a>
<div class="entete"><div><h1><?= e($c['nom']) ?></h1><p>Le nom sert de texte de remplacement au logo, et s’affiche seul quand il n’y a pas de logo.</p></div></div>
<form method="post" action="<?= url('admin/clients/' . (int) $c['id']) ?>" class="pile" style="max-width:640px">
  <?= csrf_champ() ?>
  <div class="carte">
    <div class="champ"><label for="nom">Nom</label><input id="nom" name="nom" required value="<?= e($c['nom']) ?>"></div>
    <div class="champ"><label for="lien">Lien (facultatif)</label><input id="lien" name="lien" type="url" value="<?= e($c['lien']) ?>" placeholder="https://…"><p class="aide">Le logo devient cliquable et ouvre le site du client.</p></div>
    <div class="champ">
      <label>Logo</label>
      <div class="rangee">
        <div class="logo-apercu" data-couverture><?= $c['logo'] !== '' ? '<img src="' . e(image_url($c['logo'])) . '" alt="">' : '—' ?></div>
        <input type="hidden" name="logo" value="<?= e($c['logo']) ?>" data-couverture-champ>
        <button class="bouton fant petit" type="button" data-choisir-couverture>Choisir dans la médiathèque</button>
        <button class="bouton fant petit" type="button" data-retirer-couverture<?= $c['logo'] === '' ? ' hidden' : '' ?>>Retirer</button>
      </div>
    </div>
    <label class="case" style="margin:6px 0 16px"><input type="checkbox" name="visible" value="1"<?= (int) $c['visible'] ? ' checked' : '' ?>> Visible sur l’accueil</label>
    <button class="bouton" type="submit">Enregistrer</button>
  </div>
</form>
<form method="post" action="<?= url('admin/clients/' . (int) $c['id'] . '/supprimer') ?>" data-confirmer="Retirer <?= e($c['nom']) ?> de la bande de logos ?" style="margin-top:28px">
  <?= csrf_champ() ?><button class="bouton danger fant" type="submit">Retirer ce client</button>
</form>
<?php require __DIR__ . '/_selecteur.php'; ?>
