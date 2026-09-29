<?php
/**
 * L'abonnement aux notifications, dans le volet du compte.
 *
 * Il se tient sous « Connexion » et « Créer un compte », et c'est
 * délibéré : ces deux portes demandent un formulaire, un mot de passe, une
 * adresse. Celle-ci ne demande rien. Quelqu'un qui n'a pas envie de créer
 * un compte a quand même envie d'être prévenu, et il n'avait jusqu'ici
 * aucun endroit où le dire depuis la vitrine.
 *
 * Il part CACHÉ, et le script le révèle. Un navigateur qui ne sait pas
 * recevoir de notifications, ou qui a déjà refusé pour ce site, ne verra
 * donc jamais l'entrée — plutôt qu'un bouton mort dans un menu. Et si le
 * script ne charge pas du tout, il n'y a rien non plus : c'est la même
 * chose que ce qu'il aurait fait.
 */
if (push_cle_publique() === null) {
    return;
}
push_contexte_attendu(true);
?>
<button class="wk-push" type="button" data-push="menu" data-push-bouton hidden>
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
       stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"
       style="width:18px;height:18px"><path d="M18 8.5a6 6 0 1 0-12 0c0 6-2 7.5-2 7.5h16s-2-1.5-2-7.5"></path>
    <path d="M13.7 20a2 2 0 0 1-3.4 0"></path></svg>
  <span><b data-push-titre>Recevoir les notifications</b>
  <small data-push-aide>Les sorties et les campagnes, dès qu’elles ouvrent.</small></span>
</button>
