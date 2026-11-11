<?php
/**
 * Une carte d'événement, pour les vues grille.
 *
 * La carte mène à la billetterie quand les billets sont ouverts ; sinon
 * elle reste un bloc muet — un événement sans billetterie n'a encore
 * rien à vendre, et une carte morte vaut mieux qu'un lien mort.
 *
 * Variables attendues :
 *   $ev            une entrée de `api_evenements()`
 *   $ev_etat       badge facultatif sur le visuel ('En vente', 'Bientôt')
 *   $ev_avec_date  ajoute le jour au-dessus du lieu, pour la billetterie
 *   $ev_compte     nombre de catégories de billets, affiché s'il vaut plus de zéro
 */
$achetable = $ev['billets'];
$ev_etat = $ev_etat ?? '';
$ev_avec_date = $ev_avec_date ?? false;
$ev_compte = $ev_compte ?? 0;
?>
<<?= $achetable ? 'a class="evt-carte" href="' . e(url('?p=tickets&e=' . rawurlencode($ev['id']))) . '"' : 'div class="evt-carte"' ?>>
  <div class="evt-visuel <?= api_visuel($ev['titre']) ?>">
    <?php if ($ev['image'] !== ''): ?>
      <img src="<?= e($ev['image']) ?>" alt="" loading="lazy" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover">
    <?php else: ?>
      <span class="evt-initiale"><?= e(api_initiale($ev['titre'])) ?></span>
    <?php endif; ?>
    <?php if ($ev['date'] !== ''): ?>
      <span class="evt-date"><b><?= e(api_date_jour($ev['date'])) ?></b><span><?= e(api_date_mois($ev['date'])) ?></span></span>
    <?php endif; ?>
    <?php if ($ev_etat !== ''): ?>
      <span class="vente-etat <?= $ev_etat === 'En vente' ? 'etat-en-vente' : 'etat-bientot' ?>"><?= e($ev_etat) ?></span>
    <?php endif; ?>
  </div>
  <div class="evt-corps">
    <h3><?= e($ev['titre']) ?></h3>
    <?php $_ou = trim(($ev['lieu'] !== '' ? $ev['lieu'] . (api_ville_label($ev['ville']) !== '' ? ', ' : '') : '') . api_ville_label($ev['ville'])); ?>
    <?php if ($_ou !== '' || ($ev_avec_date && $ev['date'] !== '')): ?>
      <p class="evt-ligne"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11z"></path><circle cx="12" cy="10" r="2.6"></circle></svg><?= e($_ou) ?><?= $ev_avec_date && $ev['date'] !== '' ? ' · ' . e(api_date_jour($ev['date']) . ' ' . mb_strtolower(api_date_mois($ev['date']))) : '' ?></p>
    <?php endif; ?>
    <div class="evt-pied">
      <?php $prix = api_prix($ev['prix_min']); ?>
      <?php if ($prix !== ''): ?><span class="badge badge-green">Dès <?= e($prix) ?></span><?php endif; ?>
      <?php $dans = api_dans_combien($ev['date']); ?>
      <?php if ($dans !== null): ?><span class="evt-jours"><?= e($dans) ?></span>
      <?php elseif ($ev_compte > 0): ?><span class="evt-jours"><?= (int) $ev_compte ?> catégorie<?= $ev_compte > 1 ? 's' : '' ?></span><?php endif; ?>
    </div>
  </div>
</<?= $achetable ? 'a' : 'div' ?>>
