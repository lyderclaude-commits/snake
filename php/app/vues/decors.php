<div class="contenu">
  <section class="entete">
    <h1>Les décors</h1>
    <p>Choisissez-en un, ajoutez votre photo, partagez. Sans compte, en trente secondes.</p>
  </section>

  <?php
  /* La recherche porte sur le titre, le sous-titre et la ville : on se
     souvient rarement d'un titre exact, mais très bien de « Lomé » ou
     d'un mot lu sur le badge. Le même formulaire que le blog, pour que
     les deux listes du site se cherchent pareil. */
  ?>
  <form method="get" action="<?= e(url('?p=decors')) ?>" class="rangee chercher chercher-comptes">
    <?= permaliens_champ_page('decors') ?>
    <input type="search" name="q" value="<?= e($cherche) ?>" placeholder="Chercher un décor"
           aria-label="Chercher un décor">
    <button class="bouton fant petit" type="submit">Chercher</button>
    <?php if ($cherche !== ''): ?>
      <a class="bouton fant petit" href="<?= e(url('?p=decors')) ?>">Tout voir</a>
    <?php endif; ?>
  </form>

  <?php if ($cherche !== ''): ?>
    <p class="aide" style="margin:0 0 16px"><?= (int) $total ?>
      décor<?= $total > 1 ? 's' : '' ?> pour « <?= e($cherche) ?> ».</p>
  <?php endif; ?>

  <?php if (!$liste): ?>
    <div class="carte">
      <p style="margin:0;color:var(--text2)">
        <?php if ($cherche !== ''): ?>
          Aucun décor ne correspond à « <?= e($cherche) ?> ».
          <a href="<?= e(url('?p=decors')) ?>">Voir tout le catalogue</a>.
        <?php else: ?>
          Aucun décor publié pour l’instant.
        <?php endif; ?>
      </p>
    </div>
  <?php else: ?>
    <div class="grille g3">
      <?php foreach ($liste as $d): ?>
        <a class="vignette" href="<?= e(url('?p=decor&slug=' . urlencode($d['slug']))) ?>">
          <?php $_im = image_reduite($d['cadre_url'] ?: url('public/cadres/bon-plan.png'), 320); ?>
          <img src="<?= e($_im['src']) ?>"
               <?= $_im['srcset'] ? 'srcset="' . e($_im['srcset']) . '" sizes="(max-width:700px) 92vw, 320px"' : '' ?>
               <?= $_im['largeur'] ? 'width="' . $_im['largeur'] . '" height="' . $_im['hauteur'] . '"' : '' ?>
               alt="" loading="lazy" decoding="async">
          <div class="bas">
            <b><?= e($d['titre']) ?></b>
            <span><?= e($d['sous_titre'] ?: ucfirst($d['ville'])) ?> · <?= (int) $d['telechargements'] ?> badges</span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>

    <?php
    /* La pagination par page entière, pas par défilement infini : une page
       numérotée a une adresse, elle se partage et un moteur sait la
       parcourir. Mêmes libellés que le blog. */
    ?>
    <?php if ($pages > 1): ?>
      <nav class="rangee" style="margin-top:22px;gap:10px;justify-content:center" aria-label="Pages">
        <?php if ($page_n > 1): ?>
          <a class="bouton fant petit" href="<?= e(url('?p=decors&n=' . ($page_n - 1) . ($cherche ? '&q=' . urlencode($cherche) : ''))) ?>">← Plus récents</a>
        <?php endif; ?>
        <span class="aide">Page <?= (int) $page_n ?> sur <?= (int) $pages ?></span>
        <?php if ($page_n < $pages): ?>
          <a class="bouton fant petit" href="<?= e(url('?p=decors&n=' . ($page_n + 1) . ($cherche ? '&q=' . urlencode($cherche) : ''))) ?>">Plus anciens →</a>
        <?php endif; ?>
      </nav>
    <?php endif; ?>
  <?php endif; ?>
</div>
