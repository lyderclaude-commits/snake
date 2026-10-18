<?php /** La fenêtre de choix d'image, partagée par les articles et les campagnes. */ ?>
<dialog class="fenetre" id="selecteur-medias">
  <div class="fenetre-tete">
    <h2>Choisir une image</h2>
    <label class="bouton petit" style="margin:0;cursor:pointer;color:#fff">Téléverser…<input type="file" accept="image/jpeg,image/png,image/webp,image/gif" hidden data-televerser></label>
    <button class="bouton fant petit" type="button" data-fermer>Fermer</button>
  </div>
  <div class="fenetre-corps">
    <p class="aide" data-etat style="margin:0 0 12px">Chargement…</p>
    <div class="medias" data-grille></div>
  </div>
</dialog>
