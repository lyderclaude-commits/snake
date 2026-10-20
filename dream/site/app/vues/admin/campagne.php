<?php
$nouveau = !$c || empty($c['id']);
$titre = $nouveau ? 'Nouvelle campagne' : 'Modifier la campagne';
$r = $c['resultats'] ?? [];
$image = (string) ($c['image'] ?? '');
// `en` arrive en JSON depuis la base, ou en tableau quand un formulaire refusé est réaffiché.
$en = is_array($c['en'] ?? null) ? $c['en'] : (json_decode((string) ($c['en'] ?? '{}'), true) ?: []);
$en_libelles = $en['libelles'] ?? array_values(array_filter((array) ($en['resultats'] ?? []), fn($k) => $k % 2 === 1, ARRAY_FILTER_USE_KEY));
$en_tags = is_array($en['tags'] ?? null) ? implode(', ', $en['tags']) : (string) ($en['tags'] ?? '');
?>
<a class="retour" href="<?= url('admin/campagnes') ?>"><i>←</i>Les campagnes</a>
<div class="entete"><div><h1><?= e($titre) ?></h1><p>Une réalisation du portfolio : ce qui a été fait, pour qui, et les chiffres qui le prouvent.</p></div></div>
<?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

<form method="post" action="<?= url('admin/campagnes/' . ($nouveau ? 'nouveau' : (int) $c['id'])) ?>" class="edition">
  <?= csrf_champ() ?>
  <div class="pile">
    <div class="carte">
      <div class="champ"><label for="titre">Titre <span class="obligatoire">*</span></label><input id="titre" name="titre" class="titre-saisie" required value="<?= e((string) ($c['titre'] ?? '')) ?>" placeholder="Woodin Togo — Domination Digitale"></div>
      <div class="champs">
        <div class="champ"><label for="client">Client</label><input id="client" name="client" value="<?= e((string) ($c['client'] ?? '')) ?>"></div>
        <div class="champ"><label for="annee">Année</label><input id="annee" name="annee" value="<?= e((string) ($c['annee'] ?? '')) ?>" placeholder="2024 ou 2022-2024"></div>
        <div class="champ"><label for="categorie">Catégorie</label>
          <select id="categorie" name="categorie"><?php foreach (CATEGORIES_CAMPAGNE as $k => $l): ?><option value="<?= e($k) ?>"<?= ($c['categorie'] ?? '') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></div>
      </div>
      <div class="champ"><label for="extrait">Résumé</label><textarea id="extrait" name="extrait" rows="2" maxlength="300"><?= e((string) ($c['extrait'] ?? '')) ?></textarea></div>
      <div class="champ" style="margin:0"><label for="description">Description complète</label><textarea id="description" name="description" rows="7"><?= e((string) ($c['description'] ?? '')) ?></textarea>
        <p class="aide">Affichée dans la fiche qui s’ouvre au clic sur la campagne.</p></div>
    </div>
    <div class="carte">
      <h2>Résultats obtenus</h2>
      <p class="aide">Quatre chiffres au plus, chacun avec son libellé : « +340% » · « Engagement ».</p>
      <?php for ($i = 0; $i < 4; $i++): ?>
        <div class="champs">
          <div class="champ"><label for="r<?= $i ?>a">Chiffre <?= $i + 1 ?></label><input id="r<?= $i ?>a" name="resultats[]" value="<?= e((string) ($r[$i * 2] ?? '')) ?>" maxlength="40"></div>
          <div class="champ"><label for="r<?= $i ?>b">Libellé</label><input id="r<?= $i ?>b" name="resultats[]" value="<?= e((string) ($r[$i * 2 + 1] ?? '')) ?>" maxlength="40"></div>
        </div>
      <?php endfor; ?>
      <div class="champ" style="margin:0"><label for="tags">Tags</label><input id="tags" name="tags" value="<?= e(implode(', ', $c['tags'] ?? [])) ?>" placeholder="Community Management, Contenu"><p class="aide">Séparés par des virgules.</p></div>
    </div>
    <details class="carte version-en"<?= ($en['titre'] ?? '') !== '' ? ' open' : '' ?>>
      <summary><span class="drapeau">EN</span> <b>Version anglaise</b>
        <span class="pastille <?= ($en['titre'] ?? '') !== '' ? 'publie' : 'brouillon' ?>"><?= ($en['titre'] ?? '') !== '' ? 'Traduite' : 'Pas encore traduite' ?></span></summary>
      <p class="aide">Ce qui s’affiche sur <code>/en/campaigns</code>. Un champ vide reprend le français.</p>
      <div class="champ"><label for="en-titre">Title</label><input id="en-titre" name="en[titre]" value="<?= e((string) ($en['titre'] ?? '')) ?>" lang="en"></div>
      <div class="champ"><label for="en-extrait">Summary</label><textarea id="en-extrait" name="en[extrait]" rows="2" maxlength="300" lang="en"><?= e((string) ($en['extrait'] ?? '')) ?></textarea></div>
      <div class="champ"><label for="en-description">Full description</label><textarea id="en-description" name="en[description]" rows="6" lang="en"><?= e((string) ($en['description'] ?? '')) ?></textarea></div>
      <p class="aide" style="margin:0 0 8px">Les libellés des résultats — les chiffres restent ceux du français.</p>
      <div class="champs">
        <?php for ($i = 0; $i < 4; $i++): ?>
          <div class="champ"><label for="en-l<?= $i ?>">Libellé <?= $i + 1 ?><?= ($r[$i * 2] ?? '') !== '' ? ' (' . e((string) $r[$i * 2]) . ')' : '' ?></label>
            <input id="en-l<?= $i ?>" name="en[libelles][]" value="<?= e((string) ($en_libelles[$i] ?? '')) ?>" maxlength="40" placeholder="<?= e((string) ($r[$i * 2 + 1] ?? '')) ?>" lang="en"></div>
        <?php endfor; ?>
      </div>
      <div class="champ" style="margin:0"><label for="en-tags">Tags</label><input id="en-tags" name="en[tags]" value="<?= e($en_tags) ?>" lang="en"></div>
    </details>
  </div>
  <aside class="edition-cote">
    <div class="carte">
      <h2>Publication</h2>
      <div class="pile" style="gap:10px;margin:12px 0 16px">
        <label class="case"><input type="checkbox" name="publie" value="1"<?= ($c['publie'] ?? 1) ? ' checked' : '' ?>> Visible sur le site</label>
        <label class="case"><input type="checkbox" name="vedette" value="1"<?= !empty($c['vedette']) ? ' checked' : '' ?>> À la une (accueil)</label>
      </div>
      <button class="bouton large" type="submit">Enregistrer</button>
    </div>
    <div class="carte">
      <h2>L’image</h2>
      <p class="aide">Format paysage, 1200 px de large au moins.</p>
      <div class="couverture" data-couverture><?php if ($image !== ''): ?><img src="<?= e(image_url($image)) ?>" alt=""><?php else: ?>Aucune image<?php endif; ?></div>
      <input type="hidden" name="image" value="<?= e($image) ?>" data-couverture-champ>
      <div class="rangee" style="gap:8px">
        <button class="bouton fant petit" type="button" data-choisir-couverture>Choisir une image</button>
        <button class="bouton fant petit" type="button" data-retirer-couverture<?= $image === '' ? ' hidden' : '' ?>>Retirer</button>
      </div>
      <?php if (str_starts_with($image, 'http')): ?><p class="aide">Cette image est encore hébergée ailleurs. <a href="<?= url('admin/import') ?>">La rapatrier</a>.</p><?php endif; ?>
    </div>
  </aside>
</form>
<?php if (!$nouveau): ?>
<form method="post" action="<?= url('admin/campagnes/' . (int) $c['id'] . '/supprimer') ?>" data-confirmer="Supprimer définitivement cette campagne ?" style="margin-top:28px">
  <?= csrf_champ() ?><button class="bouton danger fant" type="submit">Supprimer la campagne</button>
</form>
<?php endif; ?>
<?php require __DIR__ . '/_selecteur.php'; ?>
