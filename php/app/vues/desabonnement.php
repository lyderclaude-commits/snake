<?php
/** Le désabonnement : une page, un bouton, et c'est fini. */
?>
<div class="etroit">
  <?php if ($fait): ?>
    <div class="carte" style="text-align:center">
      <h1 style="margin:0 0 10px;font-size:1.5rem">C’est fait</h1>
      <p style="margin:0 0 6px"><strong><?= e($adresse) ?></strong> ne recevra plus aucun
      message marketing de Wakabi Boost.</p>
      <p class="aide" style="margin:0 0 18px">Ni de nous, ni d’un organisateur. Les messages de
      service — confirmation d’adresse, décision sur un décor — continuent : ce sont des
      réponses à vos propres actions, pas de la publicité.</p>
      <a class="bouton fant" href="<?= e(url('')) ?>">Aller sur Wakabi Boost</a>
    </div>
  <?php elseif (!$envoi): ?>
    <div class="carte" style="text-align:center">
      <h1 style="margin:0 0 10px;font-size:1.5rem">Ce lien n’est plus valable</h1>
      <p class="aide" style="margin:0 0 18px">Il a peut-être déjà servi. Si vous recevez encore
      des messages, utilisez le lien du dernier reçu — c’est toujours le plus récent qui marche.</p>
      <a class="bouton fant" href="<?= e(url('')) ?>">Aller sur Wakabi Boost</a>
    </div>
  <?php else: ?>
    <div class="carte">
      <h1 style="margin:0 0 10px;font-size:1.5rem">Ne plus recevoir de messages</h1>
      <p style="margin:0 0 6px">Vous êtes sur le point de retirer <strong><?= e($adresse) ?></strong>
      de toutes les campagnes marketing.</p>
      <p class="aide" style="margin:0 0 18px">C’est immédiat et définitif. Les messages de service
      — confirmation d’adresse, décision sur un décor — ne sont pas concernés.</p>

      <form method="post" action="<?= e(url('?p=desabonnement&j=' . urlencode($jeton))) ?>">
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <div class="champ">
          <label for="d-motif">Si vous voulez nous dire pourquoi <span style="font-weight:400">(facultatif)</span></label>
          <input id="d-motif" name="motif" type="text" maxlength="160" placeholder="Trop de messages">
        </div>
        <button class="bouton danger" type="submit" style="width:100%;justify-content:center">
          Confirmer le désabonnement
        </button>
      </form>
    </div>
  <?php endif; ?>
</div>
