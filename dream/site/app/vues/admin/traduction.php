<?php
$titre = 'Traduction';
$cle = reglage('deepl_cle');
$gratuite = str_ends_with($cle, ':fx');
?>
<div class="entete">
  <div><h1>Traduction automatique</h1><p>DeepL traduit les articles du français vers l’anglais, mise en forme, images et liens compris. Chaque traduction reste marquée « à relire » jusqu’à ce que quelqu’un l’ait relue dans l’éditeur.</p></div>
</div>

<div class="grille g4" style="margin-bottom:20px">
  <div class="stat"><b><?= (int) $publies ?></b><span>Articles en ligne</span></div>
  <div class="stat o"><b data-n-a-traduire><?= (int) $a_traduire ?></b><span>Sans version anglaise</span></div>
  <a class="stat p" href="<?= url('admin/articles?statut=auto') ?>"><b><?= (int) $auto ?></b><span>Traductions automatiques à relire</span></a>
  <div class="stat v"><b><?= (int) $releves ?></b><span>Traductions relues ou écrites</span></div>
</div>

<form method="post" action="<?= url('admin/traduction') ?>" class="carte">
  <?= csrf_champ() ?>
  <h2>Le compte DeepL</h2>
  <p class="aide">Créez un compte « DeepL API Free » sur <b>deepl.com/pro-api</b> (gratuit jusqu’à 500 000 caractères par mois, soit 60 à 100 articles), puis copiez la clé depuis <i>Compte → Clé d’API pour DeepL</i>.</p>
  <div class="champs">
    <div class="champ"><label for="deepl_cle">Clé d’API</label>
      <input id="deepl_cle" name="deepl_cle" type="password" autocomplete="off" value="" placeholder="<?= $cle !== '' ? 'enregistrée' . ($gratuite ? ' (offre gratuite)' : ' (offre payante)') . ' — laissez vide pour la garder' : 'aucune clé enregistrée' ?>">
      <p class="aide">Jamais réaffichée. Une clé gratuite finit par <code>:fx</code>.</p>
      <?php if ($cle !== ''): ?><label class="case" style="margin-top:6px"><input type="checkbox" name="deepl_effacer" value="1"> Effacer la clé (couper DeepL)</label><?php endif; ?></div>
    <div class="champ"><label for="deepl_cible">Anglais visé</label>
      <select id="deepl_cible" name="deepl_cible"><?php foreach (DEEPL_CIBLES as $k => $l): ?><option value="<?= e($k) ?>"<?= (reglage('deepl_cible') ?: 'EN-GB') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select>
      <p class="aide">« Colour » ou « color » : l’anglais britannique se lit naturellement en Afrique de l’Ouest anglophone.</p></div>
  </div>
  <label class="case" style="margin:4px 0 16px"><input type="checkbox" name="deepl_auto" value="1"<?= reglage('deepl_auto') === '1' ? ' checked' : '' ?>> Traduire automatiquement chaque article à sa publication (éditeur et import WordPress)</label>
  <div class="rangee">
    <button class="bouton" type="submit">Enregistrer</button>
    <?php if ($cle !== ''): ?><button class="bouton fant" type="button" data-tester-deepl>Tester la connexion</button><span class="aide" data-etat-deepl role="status" style="margin:0"></span><?php endif; ?>
  </div>
</form>

<div class="carte" style="margin-top:20px" data-traduction-lot data-restants="<?= (int) $a_traduire ?>">
  <h2>Traduire tous les articles</h2>
  <p class="aide">Les articles en ligne sans version anglaise, du plus récent au plus ancien, trois par trois. Les versions anglaises existantes ne sont pas touchées. Laissez l’onglet ouvert jusqu’à la fin ; si le quota du mois s’épuise, la traduction s’arrête proprement et reprendra où elle s’est arrêtée.</p>
  <?php if (!deepl_actif()): ?>
    <div class="msg attention">Enregistrez d’abord une clé DeepL.</div>
  <?php elseif ($a_traduire === 0): ?>
    <div class="msg ok">Tous les articles en ligne ont leur version anglaise.</div>
  <?php else: ?>
    <button class="bouton" type="button" data-lancer-traduction>Traduire les <?= (int) $a_traduire ?> article(s)</button>
  <?php endif; ?>
  <div class="aide" data-etat-lot role="status" style="margin-top:12px"></div>
  <div class="progression" hidden data-barre-lot><i></i></div>
  <pre class="journal" data-journal-lot></pre>
</div>
