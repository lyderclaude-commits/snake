<?php
/** Un article, en pleine largeur de lecture. */
$brouillon = $a['statut'] !== 'publie';
?>
<div class="contenu lecture">
  <?php if ($brouillon): ?>
    <div class="msg err" style="margin-bottom:16px">
      <strong>Pas encore en ligne<?= $a['statut'] === 'en_relecture' ? ' — chez la rédaction' : '' ?>.</strong>
      Cette page n’est visible que par vous et par la rédaction. Personne d’autre ne peut
      l’atteindre, même avec l’adresse.
    </div>
  <?php endif; ?>

  <p class="fil"><a href="<?= e(url('?p=blog')) ?>">← Le blog</a></p>

  <article>
    <h1><?= e($a['titre']) ?></h1>
    <p class="signature">
      <?= e($a['auteur_nom'] ?: 'La rédaction Wakabi') ?>
      <?php if ($a['publie_le']): ?>
        · <time datetime="<?= e((string) $a['publie_le']) ?>"><?= e(gmdate('d/m/Y', strtotime((string) $a['publie_le']))) ?></time>
      <?php endif; ?>
      · <?= texte_minutes((string) $a['corps']) ?> min de lecture
    </p>

    <?php if ($a['chapo']): ?>
      <p class="chapo-article"><?= e($a['chapo']) ?></p>
    <?php endif; ?>

    <?php if ($a['couverture']):
        $_im = image_reduite($a['couverture'], 960); ?>
      <img class="couverture" src="<?= e($_im['src']) ?>"
           <?= $_im['srcset'] ? 'srcset="' . e($_im['srcset']) . '" sizes="(max-width:820px) 92vw, 760px"' : '' ?>
           <?= $_im['largeur'] ? 'width="' . $_im['largeur'] . '" height="' . $_im['hauteur'] . '"' : '' ?>
           alt="" decoding="async">
    <?php endif; ?>

    <?php
    /* Le corps est du TEXTE : `texte_riche()` écrit toutes les balises
       elle-même, après avoir tout échappé. Rien de la saisie n'arrive ici
       sous forme de HTML — c'est pour cela que ce `<?=` ne passe pas par
       `e()`, et c'est le seul endroit du site dans ce cas. */
    ?>
    <div class="corps-article"><?= texte_riche((string) $a['corps']) ?></div>
  </article>

  <div class="carte" style="margin-top:28px">
    <h3 style="margin:0 0 4px">Votre prochain événement</h3>
    <p class="aide" style="margin:0 0 14px">Un décor, un badge, un QR à l’entrée. Vos invités
    font l’affiche eux-mêmes, et vous savez qui est venu.</p>
    <div class="rangee" style="gap:10px">
      <a class="bouton" href="<?= e(url('?p=decors')) ?>">Voir les décors</a>
      <a class="bouton fant" href="<?= e(url('?p=inscription')) ?>">Créer une campagne</a>
    </div>
  </div>

  <?php if ($autres): ?>
    <section style="margin-top:34px">
      <h2 style="font-size:1.15rem;margin:0 0 14px">À lire aussi</h2>
      <div class="grille g3">
        <?php foreach ($autres as $x): ?>
          <a class="vignette article" href="<?= e(url('?p=blog&a=' . urlencode($x['slug']))) ?>">
            <div class="bas">
              <b><?= e($x['titre']) ?></b>
              <span class="chapo"><?= e($x['chapo'] ?: texte_extrait((string) $x['corps'], 100)) ?></span>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    </section>
  <?php endif; ?>
</div>
