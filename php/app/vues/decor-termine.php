<?php
/**
 * La page d'un décor dont la campagne est finie.
 *
 * Elle existe pour une seule raison : l'adresse a circulé. Quelqu'un
 * ouvre aujourd'hui un lien reçu il y a trois semaines, et ce qu'il
 * trouve doit lui dire ce qui s'est passé — pas « Introuvable », qui
 * laisse croire à une faute de frappe ou à un site cassé.
 *
 * On montre donc la vignette de la campagne : c'est elle qu'il a vue
 * passer, c'est elle qui lui dit qu'il est au bon endroit.
 */
$vignette = url_og($d);
?>
<div class="etroit" style="text-align:center">

  <img src="<?= e($vignette) ?>" alt="<?= e($d['titre']) ?>"
       width="1200" height="630"
       style="width:100%;height:auto;border-radius:var(--r14);
              box-shadow:var(--ombre1);opacity:.75">

  <p class="pastille archive" style="margin-top:18px">Campagne terminée</p>
  <h1 style="margin:10px 0 6px"><?= e($d['titre']) ?></h1>

  <?php if ($d['sous_titre']): ?>
    <p style="color:var(--text2);margin:0 0 4px"><?= e($d['sous_titre']) ?></p>
  <?php endif; ?>

  <p style="color:var(--text2);max-width:44ch;margin:14px auto 22px">
    Ce décor n’est plus en ligne : la campagne est terminée, et les badges
    ne peuvent plus être créés. Les badges déjà téléchargés, eux, restent
    valables.
  </p>

  <div class="rangee" style="justify-content:center;gap:10px">
    <a class="bouton" href="<?= e(url('?p=decors')) ?>">Les décors du moment</a>
    <a class="bouton fant" href="<?= e(url('')) ?>">Créer ma campagne</a>
  </div>
</div>
