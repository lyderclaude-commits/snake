<?php $titre = 'Articles'; ?>
<div class="entete">
  <div>
    <h1>Le blog</h1>
    <p><?= (int) valeur("SELECT COUNT(*) FROM articles WHERE statut = 'publie'") ?> article(s) en ligne sur <?= (int) valeur('SELECT COUNT(*) FROM articles') ?>. C’est la partie du site qu’un moteur de recherche lit le plus.</p>
  </div>
  <div class="actions">
    <?php if (est_admin($me)): ?><a class="bouton fant" href="<?= url('admin/import') ?>">Importer de WordPress</a><?php endif; ?>
    <a class="bouton" href="<?= url('admin/articles/nouveau') ?>">Écrire un article</a>
  </div>
</div>

<form class="chercher" method="get" action="<?= url('admin/articles') ?>">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Titre ou adresse" aria-label="Chercher un article">
  <select name="statut" aria-label="Filtrer">
    <option value="">Tous</option>
    <option value="publie"<?= $statut === 'publie' ? ' selected' : '' ?>>En ligne</option>
    <option value="brouillon"<?= $statut === 'brouillon' ? ' selected' : '' ?>>Brouillons</option>
    <option value="wp"<?= $statut === 'wp' ? ' selected' : '' ?>>Importés de WordPress</option>
  </select>
  <button class="bouton fant" type="submit">Filtrer</button>
</form>

<div class="tableau">
  <table>
    <thead><tr><th></th><th>Titre</th><th>Catégorie</th><th>État</th><th>Publié</th><th class="chiffre">Lectures</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($liste as $a):
        $programme = $a['statut'] === 'publie' && $a['publie_le'] > maintenant(); ?>
      <tr>
        <td style="width:72px"><img class="miniature" src="<?= e(article_image($a)) ?>" alt="" loading="lazy"></td>
        <td>
          <a class="titre-ligne" href="<?= url('admin/articles/' . $a['id']) ?>"><?= e($a['titre']) ?></a>
          <span class="sous"><?= e($a['slug']) ?></span>
        </td>
        <td><?= e($a['categorie'] ?: '—') ?></td>
        <td>
          <?php if ($programme): ?><span class="pastille programme">Programmé</span>
          <?php elseif ($a['statut'] === 'publie'): ?><span class="pastille publie">En ligne</span>
          <?php else: ?><span class="pastille brouillon">Brouillon</span><?php endif; ?>
          <?php if ($a['wp_id']): ?><span class="pastille wp" title="Importé de WordPress">WP</span><?php endif; ?>
        </td>
        <td class="aide" style="white-space:nowrap"><?= $a['publie_le'] ? e(date_fr($a['publie_le'])) : 'Pas encore' ?></td>
        <td class="chiffre"><?= (int) $a['lectures'] ?></td>
        <td class="actions">
          <?php if ($a['statut'] === 'publie' && !$programme): ?><a class="bouton fant petit" href="<?= url('blog/' . rawurlencode($a['slug'])) ?>" target="_blank" rel="noopener">Voir</a><?php endif; ?>
          <a class="bouton fant petit" href="<?= url('admin/articles/' . $a['id']) ?>">Modifier</a>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if (!$liste): ?>
      <tr><td colspan="7" class="aide" style="padding:28px;text-align:center">Aucun article<?= $q !== '' || $statut !== '' ? ' ne correspond à ce filtre' : '' ?>.</td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>
<?php if ($pages > 1): ?>
  <nav class="pages"><?php for ($i = 1; $i <= $pages; $i++): ?>
    <?php if ($i === $page): ?><span class="ici"><?= $i ?></span><?php else: ?><a href="<?= url('admin/articles?' . http_build_query(array_filter(['q' => $q, 'statut' => $statut, 'page' => $i]))) ?>"><?= $i ?></a><?php endif; ?>
  <?php endfor; ?></nav>
<?php endif; ?>
