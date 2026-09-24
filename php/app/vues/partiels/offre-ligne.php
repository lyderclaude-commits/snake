<?php
/**
 * Le bandeau de prix d'une page de présentation Boost.
 *
 * Il lit l'offre plutôt que de l'écrire : les offres se règlent depuis
 * l'administration, et trois pages de vitrine qui annonceraient un
 * chiffre en dur mentiraient dès la première modification — sans que
 * personne ne pense à les rouvrir.
 *
 * Attend $ligne (la clé de capacité ou de compteur), $quoi (le sujet de
 * la phrase) et $pluriel (l'accord du verbe — « les liens courts FONT
 * partie », et non « fait »).
 */
$_o = offre_porte($ligne);
$_p = $pluriel ?? false;
$_fait = $_p ? 'font' : 'fait';
$_est = $_p ? 'sont' : 'est';
$_fr = static fn(int $n): string => number_format($n, 0, ',', ' ');
?>
<?php if ($_o === null): ?>
  <div class="pricing-note" style="background:#FFF7ED;border:1px solid #FED7AA;border-radius:14px;padding:18px 22px;margin:0 0 28px">
    <strong><?= e(ucfirst($quoi)) ?> n’<?= e($_est) ?> compris dans aucune offre pour l’instant.</strong>
    Écrivez-nous : nous ouvrons la ligne au cas par cas en attendant.
  </div>
<?php elseif ((int) $_o['prix'] === 0): ?>
  <div class="pricing-note" style="background:#F0FDF4;border:1px solid #BBF7D0;border-radius:14px;padding:18px 22px;margin:0 0 28px">
    <strong><?= e(ucfirst($quoi)) ?> <?= e($_est) ?> compris dans l’offre <?= e((string) $_o['nom']) ?>, gratuite.</strong>
    Créez votre compte et commencez.
  </div>
<?php else: ?>
  <div class="pricing-note" style="background:#EFF6FF;border:1px solid #BFDBFE;border-radius:14px;padding:18px 22px;margin:0 0 28px">
    <strong><?= e(ucfirst($quoi)) ?> <?= e($_fait) ?> partie de l’offre <?= e((string) $_o['nom']) ?>.</strong>
    <?= e($_fr((int) ($_o['lancement'] ?: $_o['prix']))) ?> F CFA
    <?= (int) $_o['lancement'] > 0 && (int) $_o['lancement'] < (int) $_o['prix']
        ? 'le premier mois, puis ' . e($_fr((int) $_o['prix'])) . ' F CFA par mois'
        : 'par mois' ?>.
    <a href="<?= e(url('?p=accueil#tarifs')) ?>">Voir les offres</a>
  </div>
<?php endif; ?>
