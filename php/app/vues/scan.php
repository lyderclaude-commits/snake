<div class="contenu">
  <section class="entete">
    <h1>Contrôle d’entrée</h1>
    <p>Visez le QR avec la caméra du téléphone. À défaut : une douchette se comporte
    comme un clavier, et le code se saisit à la main.</p>
  </section>

  <div class="grille g2" style="align-items:start">
    <div class="pile">
      <?php
      /**
       * La caméra d'abord, la saisie ensuite.
       *
       * Devant une file, taper dix caractères par personne ne tient pas. Le
       * geste devient : approcher le téléphone, entendre le bip, passer au
       * suivant. Le formulaire reste dessous, et marche sans JavaScript —
       * la caméra est un raccourci, pas une dépendance.
       */
      ?>
      <div class="carte">
        <div class="rangee" style="justify-content:space-between;align-items:baseline;gap:12px">
          <div>
            <p class="pas" style="margin:0">Lecture au vol</p>
            <p class="aide" style="margin:2px 0 0">La caméra reste ouverte d’un badge à l’autre.</p>
          </div>
          <button class="bouton" type="button" id="camera">Scanner avec la caméra</button>
        </div>

        <div id="camera-boite" hidden style="margin-top:14px">
          <div style="position:relative;border-radius:12px;overflow:hidden;background:#000">
            <video id="camera-video" muted playsinline
                   style="width:100%;max-height:340px;object-fit:cover;display:block"></video>
            <!-- La mire : un cadre pour viser, sans rien recouvrir. -->
            <div aria-hidden="true" style="position:absolute;inset:18% 26%;border:3px solid rgba(255,255,255,.9);
                 border-radius:14px;box-shadow:0 0 0 9999px rgba(15,23,42,.35)"></div>
          </div>
          <p class="aide" id="camera-etat" role="status" style="margin:10px 0 0">Caméra éteinte.</p>
          <div id="camera-verdict" role="status" hidden style="margin:10px 0 0"></div>
        </div>
      </div>

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
        <div class="msg <?= $resultat['ok'] ? 'ok' : 'err' ?>" id="resultat" role="status" style="margin:0">
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
      <?php endif; ?>
      <!-- Rempli par le serveur, puis rafraîchi par la caméra sans recharger. -->
      <div id="passages">
      <?php foreach ($passages as $p): ?>
        <div class="rangee" style="justify-content:space-between;border-top:1px solid var(--border);padding:8px 0;font-size:.88rem">
          <span class="mono aide"><?= e(substr($p['scanne_le'], 11, 5)) ?></span>
          <span style="flex:1"><?= e($p['porteur'] ?: 'Badge anonyme') ?></span>
          <span class="aide"><?= e($p['decor']) ?></span>
        </div>
      <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<script>window.WAKABI_SCAN = {
  base: <?= json_encode(url(''), JSON_UNESCAPED_SLASHES) ?>,
  csrf: <?= json_encode(jeton_csrf()) ?>
};</script>
<script src="<?= e(url('public/scanner.js')) ?>" defer></script>
