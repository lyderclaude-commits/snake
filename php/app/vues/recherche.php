<?php
/**
 * Les résultats de la barre de commande.
 *
 * Une page à elle, et non un volet qui tombe sous le champ : une page a
 * une adresse, donc elle se partage, se met en favori et revient avec le
 * bouton « précédent ». Un volet flottant disparaît au premier clic hors
 * de lui, c'est-à-dire au moment où l'on cherche à lire ce qu'il montre.
 */
$onglet = '';
$a_traiter = 0;
$nouveau = [];
$total = array_sum(array_map(fn(array $f): int => count($f['lignes']), $familles));
?>
<div class="contenu bord">
  <div class="bord-barre">
    <div class="bord-qui">
      <h1>Rechercher</h1>
      <p><?= $cherche === ''
        ? 'Un décor, un compte, un article, un lien.'
        : ($total ? $total . ' résultat' . ($total > 1 ? 's' : '') . ' pour « ' . e($cherche) . ' »'
                  : 'Rien pour « ' . e($cherche) . ' »') ?></p>
    </div>
    <form class="bord-chercher" method="get" action="<?= e(url('?p=recherche')) ?>" role="search">
      <input type="hidden" name="p" value="recherche">
      <label for="bord-q" class="sr-only">Chercher un décor, un compte, un article, un lien</label>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
           aria-hidden="true"><circle cx="11" cy="11" r="7"></circle><line x1="16.5" y1="16.5" x2="21" y2="21"></line></svg>
      <input id="bord-q" type="search" name="q" value="<?= e($cherche) ?>" autofocus
             placeholder="Chercher un décor, un compte, un article, un lien…"
             autocomplete="off" spellcheck="false">
      <kbd aria-hidden="true">/</kbd>
    </form>
    <a class="bouton fant" href="<?= e(url('?p=admin')) ?>">Retour au tableau de bord</a>
  </div>

  <?php if ($cherche !== '' && mb_strlen($cherche) < 2): ?>
    <div class="carte"><p class="aide" style="margin:0">Deux lettres au moins : une seule
    rendrait la moitié du produit.</p></div>
  <?php elseif ($cherche !== '' && !$familles): ?>
    <div class="carte">
      <h3 style="margin:0 0 6px">Rien ne porte ce nom</h3>
      <p class="aide" style="margin:0">La recherche porte sur le titre et l’adresse d’un décor,
      le nom et l’adresse d’un compte, le titre d’un article, le code d’un lien court.
      Elle ne cherche que dans ce que votre rôle vous ouvre.</p>
    </div>
  <?php endif; ?>

  <?php foreach ($familles as $f): ?>
    <div class="bord-tete" style="margin-top:20px">
      <h2><?= e($f['famille']) ?></h2>
      <a class="aide" href="<?= e(url($f['tout'])) ?>">Tout voir</a>
    </div>
    <div class="carte bord-file bord-resultats">
      <?php foreach ($f['lignes'] as $l): ?>
        <div class="bord-ligne">
          <span class="bord-titre"><?= e($l['titre']) ?></span>
          <span class="bord-auteur"><?= e($l['note']) ?></span>
          <a class="bouton fant petit" href="<?= e(url($l['lien'])) ?>">Ouvrir</a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
</div>
