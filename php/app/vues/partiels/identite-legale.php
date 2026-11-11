 <?php
/**
 * Qui édite ce site, et qui répond des données.
 *
 * Les deux pages légales doivent l'annoncer, et aucune des deux ne peut
 * l'inventer : la raison sociale, le numéro d'immatriculation et le siège
 * ne se devinent pas depuis le code. Ils vivent donc dans les réglages,
 * et ce partiel N'ÉCRIT QUE CE QUI EST RENSEIGNÉ (le siège porte désormais
 * un défaut honnête, Cotonou, dans `LEGAL_DEFAUTS`).
 *
 * C'est la différence qui compte : une page qui afficherait
 * « [à compléter] » en public serait pire qu'une page qui n'en parle pas,
 * et une page qui afficherait un numéro inventé serait pire que les deux.
 * Tant que l'équipe n'a pas rempli ces champs, le paragraphe se contente
 * du nom et de l'adresse de contact, ce qui est vrai.
 *
 * L'écran des réglages, lui, dit franchement ce qui manque : c'est là que
 * l'information est utile, parce que c'est là qu'on peut l'ajouter.
 *
 * L'ordre du paragraphe : QUI édite, D'OÙ il répond (l'adresse de
 * contact, e-mail d'abord), puis OÙ il est établi. Le siège ferme la
 * lecture, juste après l'e-mail — c'est le dernier détail que l'on
 * cherche avant d'écrire ou de payer.
 */
$_id = identite_legale();
?>
<p>
  Ce site est édité par <strong><?= e($_id['nom']) ?></strong><?php
  if ($_id['forme'] !== ''): ?>, <?= e($_id['forme']) ?><?php endif; ?>.
  <?php if ($_id['registre'] !== ''): ?>
    Immatriculation : <?= e($_id['registre']) ?>.
  <?php endif; ?>
  Pour toute question sur cette page comme sur le reste du service :
  <a href="mailto:<?= e($_id['courriel']) ?>"><?= e($_id['courriel']) ?></a><?php
  if ($_id['telephone'] !== ''): ?>, ou <?= e($_id['telephone']) ?><?php endif; ?>.
  <?php if ($_id['siege'] !== ''): ?>
    Le siège se trouve à <?= e($_id['siege']) ?>.
  <?php endif; ?>
</p>
