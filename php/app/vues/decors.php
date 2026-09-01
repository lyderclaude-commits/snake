<div class="contenu">
  <section class="entete">
    <h1>Les décors</h1>
    <p>Choisissez-en un, ajoutez votre photo, partagez. Sans compte, en trente secondes.</p>
  </section>

  <?php if (!$liste): ?>
    <div class="carte"><p style="margin:0;color:var(--text2)">Aucun décor publié pour l’instant.</p></div>
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
  <?php endif; ?>
</div>
