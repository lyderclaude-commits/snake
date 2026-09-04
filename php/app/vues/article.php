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

  <?php
  /**
   * Le décor dont parle l'article, s'il en cite un.
   *
   * Il REMPLACE l'invitation générique plutôt que de s'y ajouter : deux
   * appels à l'action l'un sous l'autre se neutralisent, et le lecteur qui
   * vient de lire le récit d'une soirée n'a pas besoin qu'on lui propose
   * « voir les décors » — il veut celui-là.
   */
  ?>
  <?php if ($decor): ?>
    <aside class="decor-lie">
      <a class="decor-lie-image" href="<?= e(url('?p=decor&slug=' . urlencode((string) $decor['slug']))) ?>">
        <?php $_dl = image_reduite($decor['cadre_url'] ?: url('public/cadres/bon-plan.png'), 400); ?>
        <img src="<?= e($_dl['src']) ?>"
             <?= $_dl['srcset'] ? 'srcset="' . e($_dl['srcset']) . '" sizes="(max-width:660px) 92vw, 260px"' : '' ?>
             <?= $_dl['largeur'] ? 'width="' . $_dl['largeur'] . '" height="' . $_dl['hauteur'] . '"' : '' ?>
             alt="" loading="lazy" decoding="async">
      </a>
      <div class="decor-lie-texte">
        <p class="decor-lie-sur">Le décor de cet article</p>
        <h3><?= e((string) $decor['titre']) ?></h3>
        <p class="aide"><?= e((string) ($decor['sous_titre'] ?: ucfirst((string) $decor['ville']))) ?><?php
          if ((int) $decor['telechargements']): ?> · <?= (int) $decor['telechargements'] ?> badges déjà faits<?php endif; ?></p>
        <div class="rangee" style="gap:10px;margin-top:12px;flex-wrap:wrap">
          <a class="bouton" href="<?= e(url('?p=decor&slug=' . urlencode((string) $decor['slug']))) ?>">Faire mon badge</a>
          <a class="bouton fant" href="<?= e(url('?p=decors')) ?>">Les autres décors</a>
        </div>
      </div>
    </aside>
  <?php else: ?>
    <div class="carte" style="margin-top:28px">
      <h3 style="margin:0 0 4px">Votre prochain événement</h3>
      <p class="aide" style="margin:0 0 14px">Un décor, un badge, un QR à l’entrée. Vos invités
      font l’affiche eux-mêmes, et vous savez qui est venu.</p>
      <div class="rangee" style="gap:10px">
        <a class="bouton" href="<?= e(url('?p=decors')) ?>">Voir les décors</a>
        <a class="bouton fant" href="<?= e(url('?p=inscription')) ?>">Créer une campagne</a>
      </div>
    </div>
  <?php endif; ?>

  <?php
  /**
   * Le partage, en liens simples et sans mouchard.
   *
   * Aucun script de Facebook ni de X n'est chargé : les boutons officiels
   * pistent chaque lecteur d'une page où ils figurent, qu'on clique ou
   * non, et ils ralentissent l'affichage sur une connexion de Lomé. Une
   * adresse `https://` fait exactement le même travail.
   *
   * WhatsApp d'abord, et ce n'est pas un détail : c'est là que se partage
   * réellement une soirée à Lomé, Cotonou ou Abidjan. Le bouton « Partager »
   * du téléphone, lui, n'apparaît que si le navigateur sait le faire — il
   * ouvre le menu du système, qui connaît les applications installées
   * mieux que nous.
   */
  $_part_u = rawurlencode($lien_article);
  $_part_t = rawurlencode((string) $a['titre']);
  ?>
  <?php if (!$brouillon): ?>
    <section class="partage" aria-labelledby="partage-titre">
      <h2 id="partage-titre">Partager cet article</h2>
      <div class="partage-liens">
        <a class="partage-lien wa" target="_blank" rel="noopener"
           href="https://wa.me/?text=<?= $_part_t ?>%20<?= $_part_u ?>">WhatsApp</a>
        <a class="partage-lien fb" target="_blank" rel="noopener"
           href="https://www.facebook.com/sharer/sharer.php?u=<?= $_part_u ?>">Facebook</a>
        <a class="partage-lien x" target="_blank" rel="noopener"
           href="https://twitter.com/intent/tweet?url=<?= $_part_u ?>&amp;text=<?= $_part_t ?>">X</a>
        <a class="partage-lien tg" target="_blank" rel="noopener"
           href="https://t.me/share/url?url=<?= $_part_u ?>&amp;text=<?= $_part_t ?>">Telegram</a>
        <a class="partage-lien ml"
           href="mailto:?subject=<?= $_part_t ?>&amp;body=<?= $_part_u ?>">E-mail</a>
        <button class="partage-lien copie" type="button" id="partage-copie"
                data-lien="<?= e($lien_article) ?>">Copier le lien</button>
        <button class="partage-lien natif" type="button" id="partage-natif" hidden>Partager…</button>
      </div>
    </section>

    <script>
    /**
     * Deux gestes, et rien de plus.
     *
     * « Copier » dit ce qu'il a fait — un bouton qui ne réagit pas laisse
     * croire qu'il n'a pas marché, et l'on clique trois fois. « Partager… »
     * n'apparaît que si le navigateur sait ouvrir le menu du système : le
     * proposer ailleurs afficherait un bouton mort.
     */
    (function () {
      var b = document.getElementById('partage-copie');
      if (b && navigator.clipboard) {
        b.addEventListener('click', function () {
          navigator.clipboard.writeText(b.dataset.lien).then(function () {
            var avant = b.textContent;
            b.textContent = 'Lien copié';
            b.classList.add('fait');
            setTimeout(function () { b.textContent = avant; b.classList.remove('fait'); }, 2200);
          });
        });
      } else if (b) {
        b.hidden = true;
      }
      var n = document.getElementById('partage-natif');
      if (n && navigator.share) {
        n.hidden = false;
        n.addEventListener('click', function () {
          navigator.share({
            title: <?= json_encode((string) $a['titre'], JSON_UNESCAPED_UNICODE) ?>,
            url: <?= json_encode($lien_article, JSON_UNESCAPED_UNICODE) ?>,
          }).catch(function () { /* annulé par l'utilisateur : sans intérêt */ });
        });
      }
    })();
    </script>
  <?php endif; ?>

  <?php if ($autres): ?>
    <section style="margin-top:34px">
      <h2 style="font-size:1.15rem;margin:0 0 14px">À lire aussi</h2>
      <div class="grille g3">
        <?php foreach ($autres as $x): ?>
          <a class="vignette article" href="<?= e(url('?p=blog&a=' . urlencode($x['slug']))) ?>">
            <?php $_ia = image_reduite(illustration_article($x), 320); ?>
            <img src="<?= e($_ia['src']) ?>"
                 <?= $_ia['srcset'] ? 'srcset="' . e($_ia['srcset']) . '" sizes="(max-width:700px) 92vw, 240px"' : '' ?>
                 <?= $_ia['largeur'] ? 'width="' . $_ia['largeur'] . '" height="' . $_ia['hauteur'] . '"' : '' ?>
                 alt="" loading="lazy" decoding="async">
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
