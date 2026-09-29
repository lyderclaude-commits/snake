<?php
/**
 * Le bouton d'abonnement aux notifications du navigateur, en carte.
 *
 * Un partiel, parce qu'il apparaît à deux endroits : dans le profil, où on
 * gère son compte, et sur la page d'un décor, où l'invité vient de faire
 * son badge — c'est là qu'il est le plus enclin à accepter. Le même bouton,
 * le même code, un seul endroit à corriger.
 *
 * Il n'est plus seul : la barre du compte et la page d'accueil proposent
 * la même chose, autrement. Les trois partagent un contexte unique et un
 * script unique, et se repeignent ensemble — on s'abonne ici, l'entrée du
 * menu bascule sans recharger.
 *
 * Rien ne s'affiche si l'hébergement ne sait pas chiffrer : proposer un
 * bouton qui échouera n'apprend rien à personne.
 */
$_push_contexte = push_contexte_html((string) ($_push_decor ?? ''));
if (push_cle_publique() === null) {
    return;
}
$_push_titre = $_push_titre ?? 'Les notifications';
?>
<div class="carte" style="margin-top:16px" data-push="carte">
  <h3 style="margin:0 0 4px"><?= e($_push_titre) ?></h3>
  <p class="aide" style="margin:0 0 14px">Une alerte sur cet appareil quand une nouvelle campagne
  ouvre, ou quand une offre vous concerne. Elle arrive même site fermé, et se coupe d’un clic.</p>

  <button class="bouton" type="button" id="push-bouton" data-push-bouton disabled>Recevoir les notifications</button>
  <p class="aide" id="push-etat" data-push-etat style="margin:10px 0 0">Vérification de ce navigateur…</p>

  <?= $_push_contexte ?>
</div>
