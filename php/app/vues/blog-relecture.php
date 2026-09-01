<?php
/** La file de relecture du blog. Le même écran que celle des décors. */
$erreur = $erreur ?? null;
$message = $message ?? null;
?>
<div class="contenu">
  <section class="entete">
    <h1>Relecture du blog</h1>
    <p><?= count($liste) ?> article(s) proposés par des organisateurs, le plus ancien d’abord.</p>
  </section>

  <?php if ($message): ?><div class="msg ok" role="status"><?= e($message) ?></div><?php endif; ?>
  <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

  <?php if (!$liste): ?>
    <div class="msg ok" role="status">La file est vide : rien n’attend de décision.</div>
  <?php else: ?>
    <?php foreach ($liste as $a): ?>
      <div class="carte" style="margin-bottom:16px">
        <div class="rangee" style="justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap">
          <div>
            <h3 style="margin:0 0 4px"><?= e($a['titre']) ?></h3>
            <p class="aide" style="margin:0">
              Proposé par <strong><?= e($a['propose_par'] ?: $a['auteur_nom'] ?: 'inconnu') ?></strong>
              <?php if ($a['soumis_le']): ?>
                le <?= e(gmdate('d/m/Y à H:i', strtotime((string) $a['soumis_le']))) ?> UTC
              <?php endif; ?>
              · <?= texte_minutes((string) $a['corps']) ?> min de lecture
            </p>
          </div>
          <a class="bouton fant petit" href="<?= e(url('?p=blog&a=' . urlencode($a['slug']))) ?>" target="_blank" rel="noopener">
            Lire en entier
          </a>
        </div>

        <?php if ($a['chapo']): ?>
          <p style="margin:14px 0 0"><?= e($a['chapo']) ?></p>
        <?php endif; ?>
        <p class="aide" style="margin:10px 0 0"><?= e(texte_extrait((string) $a['corps'], 300)) ?></p>

        <form method="post" action="<?= e(url('?p=blog-action')) ?>"
              style="margin-top:16px;border-top:1px solid var(--border);padding-top:16px">
          <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
          <input type="hidden" name="id" value="<?= e($a['id']) ?>">
          <div class="champ">
            <label for="m-<?= e($a['id']) ?>">Motif — obligatoire pour renvoyer ou refuser</label>
            <textarea id="m-<?= e($a['id']) ?>" name="motif" rows="2"
                      placeholder="Ajoutez les chiffres réels : sans eux, l’article ne prouve rien."></textarea>
          </div>
          <div class="rangee" style="gap:10px;flex-wrap:wrap">
            <button class="bouton" type="submit" name="quoi" value="publier">Publier</button>
            <button class="bouton fant" type="submit" name="quoi" value="corrections">Renvoyer à l’auteur</button>
            <button class="bouton danger" type="submit" name="quoi" value="refuser">Refuser</button>
          </div>
        </form>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
