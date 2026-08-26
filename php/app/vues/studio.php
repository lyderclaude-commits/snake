<?php $champs = array_values(array_filter($g['layers'], fn($l) => ($l['type'] ?? '') === 'text' && !empty($l['editable']))); ?>
<div class="contenu">
  <section class="entete" style="padding-bottom:14px">
    <h1><?= e($d['titre']) ?></h1>
    <?php if ($d['sous_titre']): ?><p><?= e($d['sous_titre']) ?></p><?php endif; ?>
  </section>

  <div class="studio">
    <div class="toile-boite">
      <canvas id="toile" width="640" height="640" aria-label="Aperçu de votre badge"></canvas>
      <p id="etat" class="aide" style="margin-top:10px"></p>
    </div>

    <div class="pile">
      <div class="carte">
        <div class="champ">
          <label for="photo">1 · Votre photo</label>
          <input id="photo" type="file" accept="image/*">
          <p class="aide">Elle reste sur votre téléphone : rien n’est envoyé au serveur.</p>
        </div>

        <div id="outils" hidden>
          <div class="champ">
            <label for="zoom">Zoom</label>
            <input id="zoom" type="range" min="0.5" max="4" step="0.01" value="1">
          </div>
          <div class="rangee">
            <button class="bouton fant petit" id="miroir" type="button">Miroir</button>
            <button class="bouton fant petit" id="recentrer" type="button">Recentrer</button>
          </div>
          <div class="champ" style="margin-top:14px">
            <label for="teinte">Teinte Wakabi</label>
            <input id="teinte" type="range" min="0" max="0.8" step="0.05" value="0">
          </div>
        </div>
      </div>

      <?php if ($champs): ?>
      <div class="carte">
        <h3 style="margin-bottom:12px">2 · Votre texte</h3>
        <?php foreach ($champs as $c): ?>
          <div class="champ">
            <label for="champ-<?= e($c['id']) ?>"><?= e($c['placeholder'] ?? 'Texte') ?></label>
            <input id="champ-<?= e($c['id']) ?>" type="text"
                   maxlength="<?= (int) ($c['maxLength'] ?? 42) ?>"
                   value="<?= e($c['value'] ?? '') ?>">
            <p class="aide" id="compte-<?= e($c['id']) ?>"><?= mb_strlen($c['value'] ?? '') ?>/<?= (int) ($c['maxLength'] ?? 42) ?></p>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="carte">
        <h3 style="margin-bottom:10px">3 · Partager</h3>
        <button class="bouton" id="telecharger" type="button" style="width:100%;justify-content:center">
          Télécharger mon badge
        </button>

        <div data-jeton hidden style="margin-top:14px">
          <p class="aide" style="margin:0">Code de votre badge, à présenter à l’entrée :</p>
          <p class="mono" id="jeton" style="font-size:1.3rem;font-weight:700;letter-spacing:.16em;margin:4px 0 0"></p>
        </div>

        <div id="bloc-partage" hidden style="margin-top:14px">
          <p class="aide">Votre navigateur ne permet pas l’enregistrement direct :
          <strong>appui long</strong> sur l’image, puis « Enregistrer ».</p>
          <img id="apercu-partage" alt="Votre badge" style="width:100%;border-radius:var(--r10)">
        </div>

        <div id="apres" hidden style="margin-top:14px">
          <a class="bouton fant" style="width:100%;justify-content:center"
             href="<?= e($g['share']['redirectUrl'] ?? url('')) ?>">
            <?= e($g['share']['redirectLabel'] ?? 'Découvrir sur Wakabi') ?>
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
window.WAKABI = {
  gabarit: <?= json_encode($g, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>,
  decorId: <?= json_encode($d['id']) ?>,
  slug: <?= json_encode($d['slug']) ?>,
  cadreUrl: <?= json_encode($d['cadre_url']) ?>,
  base: <?= json_encode(url('')) ?>,
  connecte: <?= $me ? 'true' : 'false' ?>
};
</script>
<script src="<?= e(url('public/studio.js')) ?>" defer></script>
