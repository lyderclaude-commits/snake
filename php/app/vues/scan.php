<div class="contenu">
  <section class="entete">
    <h1>Contrôle d’entrée</h1>
    <p>Scannez le QR du badge (un lecteur se comporte comme un clavier) ou saisissez son code.</p>
  </section>

  <div class="grille g2" style="align-items:start">
    <div class="pile">
      <form method="post" class="carte">
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <label for="jeton">Code du badge</label>
        <div class="rangee" style="flex-wrap:nowrap;gap:8px">
          <input id="jeton" name="jeton" type="text" maxlength="10" autocomplete="off" autofocus
                 value="<?= e($prerempli) ?>" placeholder="A7K2M9XQ4P"
                 style="text-align:center;font-family:ui-monospace,monospace;font-size:1.35rem;font-weight:700;letter-spacing:.16em;text-transform:uppercase">
          <button class="bouton" type="submit">Valider</button>
        </div>
      </form>

      <?php if ($resultat): ?>
        <div class="msg <?= $resultat['ok'] ? 'ok' : 'err' ?>" role="status" style="margin:0">
          <strong style="font-size:1.05rem"><?= e($resultat['message']) ?></strong>
          <?php if (!empty($resultat['detail'])): ?>
            <p style="margin:.35em 0 0"><?= e($resultat['detail']) ?></p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="carte">
      <h3 style="margin-bottom:10px">Derniers passages</h3>
      <?php if (!$passages): ?>
        <p style="color:var(--text2);margin:0">Aucune entrée validée pour l’instant.</p>
      <?php else: foreach ($passages as $p): ?>
        <div class="rangee" style="justify-content:space-between;border-top:1px solid var(--border);padding:8px 0;font-size:.88rem">
          <span class="mono aide"><?= e(substr($p['scanne_le'], 11, 5)) ?></span>
          <span style="flex:1"><?= e($p['porteur'] ?: 'Badge anonyme') ?></span>
          <span class="aide"><?= e($p['decor']) ?></span>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>
