<?php
/**
 * Le tableau de bord, onglet « À faire ».
 *
 * L'écran d'avant rangeait sept blocs, dont cinq d'analyse. Ce qu'on vient
 * y chercher vingt fois par jour — la file de relecture, et un lien vers un
 * écran — était séparé par une grille de chiffres puis noyé dans douze
 * raccourcis de même taille. Et la page faisait 1 760 px : le tableau du
 * bas ne se voyait jamais.
 *
 * Rien n'a été supprimé : tout ce qui comptait est passé dans l'onglet
 * « Les chiffres ». Le tableau de bord cesse d'être un rapport — le
 * rapport existe déjà, avec sa période et son PDF.
 */
$fr = fn(int $n) => number_format($n, 0, ',', ' ');
$onglet = 'faire';
?>
<div class="contenu bord">
<?php require __DIR__ . '/partiels/bord-barre.php'; ?>

<!-- ---------- ce qui attend une décision ---------- -->
<?php
/**
 * Une LISTE D'OBJETS, et non une rangée de compteurs.
 *
 * « 2 décors à relire » oblige à ouvrir un autre écran pour savoir
 * lesquels, et ne dit jamais que l'un attend depuis six jours. Ici le
 * titre, l'auteur, l'âge et le bouton tiennent sur une ligne.
 */
?>
<section class="carte bord-file">
  <div class="bord-tete">
    <h2>À traiter</h2>
    <span class="aide">du plus vieux au plus récent</span>
  </div>

  <?php if (!$a_faire): ?>
    <p class="aide bord-vide" role="status">Rien n’attend de décision. Aucune relecture en file.</p>
  <?php else: ?>
    <?php foreach ($a_faire as $l): ?>
      <div class="bord-ligne">
        <span class="bord-genre <?= e($l['ton']) ?>"><?= e($l['genre']) ?></span>
        <span class="bord-titre"><?= e($l['titre']) ?></span>
        <span class="bord-auteur"><?= e($l['qui']) ?></span>
        <span class="bord-age<?= $l['depuis'] !== '' && bord_urgent($l['depuis']) ? ' vieux' : '' ?>">
          <?= e($l['depuis'] !== '' ? bord_depuis($l['depuis']) : '') ?>
        </span>
        <a class="bouton petit<?= $l['depuis'] !== '' && bord_urgent($l['depuis']) ? '' : ' fant' ?>"
           href="<?= e(url($l['lien'])) ?>"><?= e($l['action']) ?></a>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <?php if ($sans_urgence): ?>
    <p class="bord-calme">Sans urgence :
      <?php foreach ($sans_urgence as $i => [$ou, $n, $quoi]): ?>
        <?= $i ? ' · ' : '' ?><a href="<?= e(url($ou)) ?>"><?= (int) $n ?> <?= e($quoi) ?></a>
      <?php endforeach; ?>
    </p>
  <?php endif; ?>
</section>

<!-- ---------- reprendre ---------- -->
<?php
/**
 * Les quatre dernières choses que VOUS avez touchées.
 *
 * Lues dans le journal, qui les enregistrait déjà sans que personne les
 * relise. C'est ce qui manque quand on referme l'ordinateur au milieu
 * d'une relecture : rouvrir le produit ne dit pas où l'on en était.
 */
?>
<?php if ($reprendre): ?>
  <div class="bord-tete">
    <h2>Reprendre</h2>
    <span class="aide">ce que vous avez touché en dernier</span>
  </div>
  <div class="grille g4 bord-reprendre">
    <?php foreach ($reprendre as $r): ?>
      <a class="carte plate" href="<?= e(url($r['lien'])) ?>">
        <span class="bord-genre <?= e($r['ton']) ?>"><?= e($r['genre']) ?></span>
        <b><?= e($r['titre']) ?></b>
        <span class="aide"><?= e($r['quoi']) ?> · <?= e(bord_depuis($r['quand'])) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<!-- ---------- où aller ---------- -->
<?php
/**
 * Des pastilles chiffrées, et seulement ce que le rôle ouvre.
 *
 * Douze raccourcis de même taille rangés en trois colonnes prenaient un
 * demi-écran pour un geste qu'on répète vingt fois par jour. Le chiffre
 * remplace la phrase d'explication : « Comptes 340 » se lit plus vite que
 * « Comptes / Rôles, offres, suspensions », et tient sur une ligne.
 */
?>
<div class="bord-tete">
  <h2>Où aller</h2>
  <span class="aide">seulement ce que votre rôle ouvre</span>
</div>
<div class="bord-pastilles">
  <?php foreach ($ou_aller as [$ou, $nom, $n, $_d]): ?>
    <a href="<?= e(url($ou)) ?>"><?= e($nom) ?><?php
      if ($n !== null): ?> <span><?= $fr((int) $n) ?></span><?php endif; ?></a>
  <?php endforeach; ?>
</div>

<!-- ---------- la semaine, en une ligne ---------- -->
<?php
/* Le chiffre du haut de l'ancien écran, réduit à une bande cliquable :
   il dit si quelque chose a bougé, et mène au détail. Cinq cartes de
   statistiques au milieu d'un écran d'action, c'était le détail qui
   coupait l'action en deux. */
?>
<a class="carte plate bord-semaine" href="<?= e(url('?p=admin&vue=chiffres')) ?>">
  <span class="bord-semaine-haut">
    <span class="bord-semaine-titre">7 derniers jours</span>
    <span class="bord-semaine-tout">Tout voir →</span>
  </span>
  <span class="bord-mesures">
  <?php foreach ($semaine as $k): ?>
    <?php
    $v = $k['variation'];
    $classe = $v === null ? 'plat' : ($v > 0 ? 'haut' : ($v < 0 ? 'bas' : 'plat'));
    $texte = $v === null ? 'nouveau' : ($v > 0 ? '+' . $v . ' %' : $v . ' %');
    ?>
    <span class="bord-mesure">
      <b><?= $fr((int) $k['valeur']) ?></b>
      <span><?= e($k['titre']) ?></span>
      <span class="delta <?= $classe ?>"><?= e($texte) ?></span>
    </span>
  <?php endforeach; ?>
  </span>
</a>
</div>
