<?php
/**
 * Qui édite ce site, et qui répond des données.
 *
 * Les deux pages légales doivent l'annoncer, et aucune des deux ne peut
 * l'inventer : la raison sociale, le numéro d'immatriculation et le siège
 * ne se devinent pas depuis le code. Ils vivent donc dans les réglages,
 * et ce partiel N'ÉCRIT QUE CE QUI EST RENSEIGNÉ.
 *
 * C'est la différence qui compte : une page qui afficherait
 * « [à compléter] » en public serait pire qu'une page qui n'en parle pas,
 * et une page qui afficherait un numéro inventé serait pire que les deux.
 * Tant que l'équipe n'a pas rempli ces champs, le paragraphe se contente
 * du nom et de l'adresse de contact, ce qui est vrai.
 *
 * L'écran des réglages, lui, dit franchement ce qui manque : c'est là que
 * l'information est utile, parce que c'est là qu'on peut l'ajouter.
 */
$_id = identite_legale();
?>
<p>
  Ce site est édité par <strong><?= e($_id['nom']) ?></strong><?php
  if ($_id['forme'] !== ''): ?>, <?= e($_id['forme']) ?><?php endif; ?><?php
  if ($_id['siege'] !== ''): ?>, dont le siège est à <?= e($_id['siege']) ?><?php endif; ?>.
  <?php if ($_id['registre'] !== ''): ?>
    Immatriculation : <?= e($_id['registre']) ?>.
  <?php endif; ?>
  Pour toute question sur cette page comme sur le reste du service :
  <a href="mailto:<?= e($_id['courriel']) ?>"><?= e($_id['courriel']) ?></a><?php
  if ($_id['telephone'] !== ''): ?>, ou <?= e($_id['telephone']) ?><?php endif; ?>.
</p>
