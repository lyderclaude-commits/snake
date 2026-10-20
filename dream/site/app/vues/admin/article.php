<?php
$nouveau = !$a || empty($a['id']);
$titre = $nouveau ? 'Écrire un article' : 'Modifier « ' . tronquer((string) $a['titre'], 40) . ' »';
$classe_main = 'contenu';
$tags_txt = implode(', ', json_liste($a['tags'] ?? '[]'));
$date = !empty($a['publie_le']) ? date('Y-m-d\TH:i', strtotime((string) $a['publie_le'])) : '';
$image = (string) ($a['image'] ?? '');
?>
<a class="retour" href="<?= url('admin/articles') ?>"><i>←</i>Le blog</a>
<div class="entete">
  <div>
    <h1><?= $nouveau ? 'Écrire un article' : 'Modifier l’article' ?></h1>
    <p>Écrivez comme dans un traitement de texte : ce que vous voyez est ce que la page publiera.
      <?php if (!empty($a['wp_id'])): ?><br><span class="pastille wp">Importé de WordPress</span> Une fois modifié ici, un nouvel import ne l’écrasera plus.<?php endif; ?></p>
  </div>
  <?php if (!$nouveau && ($a['statut'] ?? '') === 'publie'): ?>
    <div class="actions"><a class="bouton fant" href="<?= url('blog/' . rawurlencode((string) $a['slug'])) ?>" target="_blank" rel="noopener">Voir en ligne ↗</a></div>
  <?php elseif (!$nouveau): ?>
    <div class="actions"><a class="bouton fant" href="<?= url('blog/' . rawurlencode((string) $a['slug'])) ?>" target="_blank" rel="noopener">Aperçu ↗</a></div>
  <?php endif; ?>
</div>
<?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

<form method="post" action="<?= url('admin/articles/' . ($nouveau ? 'nouveau' : (int) $a['id'])) ?>" class="edition" id="form-article" data-editeur-form>
  <?= csrf_champ() ?>
  <div class="pile">
    <div class="carte">
      <div class="champ"><label for="titre">Titre <span class="obligatoire">*</span></label>
        <input id="titre" name="titre" class="titre-saisie" required maxlength="240" value="<?= e((string) ($a['titre'] ?? '')) ?>" placeholder="Pourquoi votre PME a besoin d’un community manager"></div>
      <div class="champ"><label for="slug">Adresse</label>
        <input id="slug" name="slug" value="<?= e((string) ($a['slug'] ?? '')) ?>" placeholder="laissez vide : elle se déduit du titre">
        <p class="aide"><?= e(url_absolue('blog/')) ?><b id="slug-apercu"><?= e((string) ($a['slug'] ?? 'votre-titre')) ?></b></p></div>
      <div class="champ" style="margin:0"><label for="extrait">Chapô <span class="aide" style="display:inline">(facultatif)</span></label>
        <textarea id="extrait" name="extrait" rows="2" maxlength="600" placeholder="Deux phrases qui donnent envie de lire la suite."><?= e((string) ($a['extrait'] ?? '')) ?></textarea>
        <p class="aide">C’est ce que montrent Google, Facebook et WhatsApp. Vide, il est tiré du début du texte.</p></div>
    </div>

    <div>
      <label>Le texte</label>
      <?php $ed_nom = 'contenu'; $ed_html = (string) ($a['contenu'] ?? ''); $ed_label = 'Texte de l’article';
            $ed_invite = 'Racontez : le constat, ce que vous conseillez, un exemple concret.'; require __DIR__ . '/_editeur.php'; ?>
      <p class="aide">Collez depuis Word ou Google Docs : la mise en forme est nettoyée à l’enregistrement. Les scripts et styles ne passent pas.</p>
    </div>

    <?php $traduit = trim((string) ($a['titre_en'] ?? '')) !== '' && trim((string) ($a['contenu_en'] ?? '')) !== ''; ?>
    <details class="carte version-en"<?= $traduit || trim((string) ($a['titre_en'] ?? '')) !== '' ? ' open' : '' ?>>
      <summary><span class="drapeau">EN</span> <b>Version anglaise</b>
        <span class="pastille <?= $traduit ? 'publie' : 'brouillon' ?>"><?= $traduit ? 'Traduit' : 'Pas encore traduit' ?></span></summary>
      <p class="aide">Remplissez au moins le titre et le texte : la page anglaise (<code>/en/blog/…</code>) affiche alors cette version. Sinon, le site anglais montre l’article en français, avec la mention « disponible uniquement en français ».</p>
      <div class="champ"><label for="titre_en">Title</label><input id="titre_en" name="titre_en" maxlength="240" value="<?= e((string) ($a['titre_en'] ?? '')) ?>" lang="en"></div>
      <div class="champ"><label for="extrait_en">Excerpt <span class="aide" style="display:inline">(facultatif)</span></label><textarea id="extrait_en" name="extrait_en" rows="2" maxlength="600" lang="en"><?= e((string) ($a['extrait_en'] ?? '')) ?></textarea></div>
      <label>Text</label>
      <?php $ed_nom = 'contenu_en'; $ed_html = (string) ($a['contenu_en'] ?? ''); $ed_label = 'Texte anglais de l’article';
            $ed_invite = 'The English version of the article.'; require __DIR__ . '/_editeur.php'; ?>
    </details>
  </div>

  <aside class="edition-cote">
    <div class="carte">
      <h2>Publication</h2>
      <p class="aide">Un brouillon ne se voit pas sur le site.</p>
      <div class="champ"><label for="statut">État</label>
        <select id="statut" name="statut">
          <option value="brouillon"<?= ($a['statut'] ?? 'brouillon') !== 'publie' ? ' selected' : '' ?>>Brouillon</option>
          <option value="publie"<?= ($a['statut'] ?? '') === 'publie' ? ' selected' : '' ?>>En ligne</option>
        </select></div>
      <div class="champ"><label for="publie_le">Date de publication</label>
        <input id="publie_le" name="publie_le" type="datetime-local" value="<?= e($date) ?>">
        <p class="aide">Vide : maintenant. Dans le futur : l’article paraîtra à cette date.</p></div>
      <div class="pile" style="gap:8px">
        <button class="bouton large" type="submit">Enregistrer</button>
      </div>
    </div>

    <div class="carte">
      <h2>La couverture</h2>
      <p class="aide">Affichée en tête d’article et dans les partages.</p>
      <div class="couverture" data-couverture><?php if ($image !== ''): ?><img src="<?= e(image_url($image)) ?>" alt=""><?php else: ?>Aucune image<?php endif; ?></div>
      <input type="hidden" name="image" value="<?= e($image) ?>" data-couverture-champ>
      <div class="rangee" style="gap:8px">
        <button class="bouton fant petit" type="button" data-choisir-couverture>Choisir une image</button>
        <button class="bouton fant petit" type="button" data-retirer-couverture<?= $image === '' ? ' hidden' : '' ?>>Retirer</button>
      </div>
    </div>

    <div class="carte">
      <h2>Classement</h2>
      <div class="champ"><label for="categorie">Catégorie</label>
        <input id="categorie" name="categorie" list="liste-categories" value="<?= e((string) ($a['categorie'] ?? '')) ?>" placeholder="Stratégie, Conseils…">
        <datalist id="liste-categories"><?php foreach ($categories as $c): ?><option value="<?= e($c) ?>"><?php endforeach; ?></datalist></div>
      <div class="champ"><label for="tags">Tags</label>
        <input id="tags" name="tags" value="<?= e($tags_txt) ?>" placeholder="Community Management, Togo">
        <p class="aide">Séparés par des virgules.</p></div>
      <div class="champ" style="margin:0"><label for="auteur">Auteur</label>
        <input id="auteur" name="auteur" value="<?= e((string) ($a['auteur'] ?? $me['nom'])) ?>"></div>
    </div>
  </aside>
</form>

<?php if (!$nouveau): ?>
<form method="post" action="<?= url('admin/articles/' . (int) $a['id'] . '/supprimer') ?>" data-confirmer="Supprimer définitivement cet article ?" style="margin-top:28px">
  <?= csrf_champ() ?>
  <button class="bouton danger fant" type="submit">Supprimer l’article</button>
  <?php if (!empty($a['wp_id'])): ?><span class="aide" style="margin-left:10px">Un nouvel import WordPress le recréerait.</span><?php endif; ?>
</form>
<?php endif; ?>
<?php require __DIR__ . '/_selecteur.php'; ?>
