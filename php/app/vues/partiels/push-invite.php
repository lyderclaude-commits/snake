<?php
/**
 * L'invitation à s'abonner, sur la page d'accueil.
 *
 * Elle n'apparaît PAS au chargement, et ce n'est pas une coquetterie : un
 * site qui réclame une permission avant qu'on ait rien vu se fait refuser,
 * et un refus est définitif du point de vue du navigateur — on ne
 * redemandera jamais. Elle attend donc que le visiteur ait lu un moment,
 * ou soit descendu à mi-page.
 *
 * Elle ne demande rien elle-même non plus : la fenêtre du navigateur ne
 * s'ouvre qu'après un clic sur son bouton. « Plus tard » la fait taire un
 * mois, la croix une semaine, un abonnement la fait taire pour de bon.
 *
 * En bas à GAUCHE, parce que le bas à droite est déjà occupé par le retour
 * en tête de page. Sur téléphone elle prend la largeur, et pousse ce
 * bouton au-dessus d'elle plutôt que de le recouvrir.
 */
if (push_cle_publique() === null) {
    return;
}
push_contexte_attendu(true);
?>
<?php
/* Une `aside` nommée, et non un `role="dialog"` : elle ne prend pas le
   clavier et ne bloque pas la page derrière elle. Annoncer une boîte de
   dialogue à qui ne peut pas en sortir au clavier serait un mensonge. */
?>
<aside class="push-invite" id="push-invite" data-push="invite" hidden
       aria-labelledby="push-invite-t">
  <button class="push-invite-x" type="button" data-push-fermer aria-label="Fermer"><?= icone('croix') ?></button>
  <div class="push-invite-h">
    <span class="push-invite-ico">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
           stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
           style="width:22px;height:22px"><path d="M18 8.5a6 6 0 1 0-12 0c0 6-2 7.5-2 7.5h16s-2-1.5-2-7.5"></path>
        <path d="M13.7 20a2 2 0 0 1-3.4 0"></path></svg>
    </span>
    <div>
      <b id="push-invite-t">Ne ratez plus une news</b>
      <p data-push-aide>Une alerte quand un décor paraît ou qu’une campagne ouvre,
      même site fermé. Un clic pour arrêter.</p>
    </div>
  </div>
  <div class="push-invite-b">
    <button class="bouton" type="button" data-push-bouton>Recevoir les notifications</button>
    <button class="bouton fant" type="button" data-push-plus-tard>Plus tard</button>
  </div>
</aside>
