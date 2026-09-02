<?php
/**
 * Le bouton d'abonnement aux notifications du navigateur.
 *
 * Un partiel, parce qu'il apparaît à deux endroits : dans le profil, où on
 * gère son compte, et sur la page d'un décor, où l'invité vient de faire
 * son badge — c'est là qu'il est le plus enclin à accepter. Le même bouton,
 * le même code, un seul endroit à corriger.
 *
 * Rien ne s'affiche si l'hébergement ne sait pas chiffrer : proposer un
 * bouton qui échouera n'apprend rien à personne.
 */
if (!push_disponible()) {
    return;
}
$_push_cle = '';
try {
    $_push_cle = vapid()['publique'];
} catch (Throwable) {
    return;
}
$_push_titre = $_push_titre ?? 'Les notifications';
?>
<div class="carte" style="margin-top:16px">
  <h3 style="margin:0 0 4px"><?= e($_push_titre) ?></h3>
  <p class="aide" style="margin:0 0 14px">Une alerte sur cet appareil quand une nouvelle campagne
  ouvre, ou quand une offre vous concerne. Elle arrive même site fermé, et se coupe d’un clic.</p>

  <button class="bouton" type="button" id="push-bouton" disabled>Recevoir les notifications</button>
  <p class="aide" id="push-etat" style="margin:10px 0 0">Vérification de ce navigateur…</p>

  <script type="application/json" id="push-contexte"><?= json_encode([
      'base' => rtrim(base_url(), '/') . '/',
      'csrf' => jeton_csrf(),
      'cle' => $_push_cle,
      // Un abonnement pris AVANT la connexion est anonyme. Le signaler
      // permet au script de le rattacher au compte, sans quoi « les
      // invités de mes campagnes » ne verrait jamais ces gens-là.
      'connecte' => (bool) utilisateur_courant(),
  ], JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
  <script src="<?= e(actif('public/push.js')) ?>" defer></script>
</div>
