<?php
/**
 * Un éditeur de texte riche. Attend `$ed_nom` (le champ envoyé), `$ed_html`
 * (le contenu de départ), `$ed_invite` et `$ed_label`.
 */
?>
<div class="editeur" data-editeur>
  <div class="editeur-barre" role="toolbar" aria-label="Mise en forme">
    <button type="button" class="editeur-outil" data-cmd="formatBlock" data-val="H2" title="Intertitre">Titre</button>
    <button type="button" class="editeur-outil" data-cmd="formatBlock" data-val="H3" title="Sous-titre">Sous-titre</button>
    <button type="button" class="editeur-outil" data-cmd="formatBlock" data-val="P" title="Paragraphe">¶</button>
    <span class="sep"></span>
    <button type="button" class="editeur-outil" data-cmd="bold" title="Gras (Ctrl+B)"><b>G</b></button>
    <button type="button" class="editeur-outil" data-cmd="italic" title="Italique (Ctrl+I)"><i>I</i></button>
    <button type="button" class="editeur-outil" data-cmd="insertUnorderedList" title="Liste à puces">• Liste</button>
    <button type="button" class="editeur-outil" data-cmd="insertOrderedList" title="Liste numérotée">1. Liste</button>
    <button type="button" class="editeur-outil" data-cmd="formatBlock" data-val="BLOCKQUOTE" title="Citation">« »</button>
    <span class="sep"></span>
    <button type="button" class="editeur-outil" data-action="lien" title="Lien">Lien</button>
    <button type="button" class="editeur-outil" data-action="image" title="Image de la médiathèque">Image</button>
    <button type="button" class="editeur-outil" data-action="video" title="Vidéo YouTube ou Vimeo">Vidéo</button>
    <span class="sep"></span>
    <button type="button" class="editeur-outil" data-cmd="removeFormat" title="Effacer la mise en forme">Effacer</button>
    <button type="button" class="editeur-outil" data-action="source" title="Voir le HTML" aria-pressed="false">HTML</button>
  </div>
  <div class="editeur-zone" contenteditable="true" data-invite="<?= e($ed_invite) ?>" aria-label="<?= e($ed_label) ?>"><?= contenu_afficher($ed_html) ?></div>
  <textarea class="editeur-source" aria-label="HTML — <?= e($ed_label) ?>"></textarea>
  <input type="hidden" name="<?= e($ed_nom) ?>" value="" data-editeur-champ>
</div>
