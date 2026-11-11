<?php
/**
 * Une rangée d'événement, pour les vues liste.
 *
 * La même rangée que l'agenda, partout où les événements se lisent en
 * liste : date en médaillon, titre, lieu, catégorie, prix, et le lien
 * vers la billetterie.
 *
 * TOUS LES ÉVÉNEMENTS N'ONT PAS DE BILLET À VENDRE. Un concert gratuit,
 * une exposition, une soirée à l'entrée libre : l'API les rend avec
 * `billets` à faux, et la rangée menait quand même vers la billetterie,
 * où le visiteur ne trouvait rien le concernant. Elle suit maintenant la
 * règle que la carte applique déjà : un lien vers la billetterie quand
 * il y a quelque chose à y acheter, un bloc muet sinon. Une rangée morte
 * vaut mieux qu'un lien mort.
 *
 * Variable attendue : $ev, une entrée de `api_evenements()`.
 */
$achetable = $ev['billets'];
?>
<<?= $achetable ? 'a class="agenda-ligne" href="' . e(url('?p=tickets&e=' . rawurlencode($ev['id']))) . '"' : 'div class="agenda-ligne"' ?>>
  <div class="agenda-date"><b><?= e(api_date_jour($ev['date']) ?: '·') ?></b><span><?= e(api_date_mois($ev['date'])) ?></span></div>
  <div class="agenda-corps">
    <h3><?= e($ev['titre']) ?></h3>
    <p><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s-7-5.5-7-11a7 7 0 0 1 14 0c0 5.5-7 11-7 11z"></path><circle cx="12" cy="10" r="2.6"></circle></svg><?= e(trim(($ev['lieu'] !== '' ? $ev['lieu'] . (api_ville_label($ev['ville']) !== '' ? ' · ' : '') : '') . api_ville_label($ev['ville']))) ?></p>
  </div>
  <?php if ($ev['categorie'] !== ''): ?><span class="tag"><?= e($ev['categorie']) ?></span><?php endif; ?>
  <?php $prix = api_prix($ev['prix_min']); ?>
  <?php if ($prix !== ''): ?><span class="agenda-prix"><?= e($prix) ?></span><?php endif; ?>
  <?php if ($achetable): ?><span class="btn btn-outline btn-sm">Détails</span><?php endif; ?>
</<?= $achetable ? 'a' : 'div' ?>>
