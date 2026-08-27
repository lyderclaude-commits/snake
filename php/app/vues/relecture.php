<div class="contenu">
  <section class="entete">
    <h1>Relecture</h1>
    <p>Ce que la machine ne peut pas juger : les droits, le ton, la pertinence.</p>
  </section>

  <?php if (!empty($_GET['ok'])): ?><div class="msg ok" role="status"><?= e($_GET['ok']) ?></div><?php endif; ?>
  <?php if (!empty($_GET['err'])): ?><div class="msg err" role="alert"><?= e($_GET['err']) ?></div><?php endif; ?>

  <?php if (!$file): ?>
    <div class="carte" style="text-align:center">
      <h3>La file est vide</h3>
      <p style="color:var(--text2);margin:.4em 0 0">Aucun décor n’attend de relecture.</p>
    </div>
  <?php else: foreach ($file as $d): $r = $rapports[$d['id']] ?? null; ?>
    <div class="carte" style="margin-bottom:16px">
      <div class="rangee" style="justify-content:space-between">
        <div>
          <b class="display" style="font-size:1.1rem"><?= e($d['titre']) ?></b>
          <p class="aide" style="margin:2px 0 0">
            <?= e($d['auteur_nom'] ?: 'Équipe Wakabi') ?> · soumis le <?= e(substr((string) $d['soumis_le'], 0, 10)) ?>
          </p>
        </div>
        <?php if ($d['cadre_url']): ?>
          <img src="<?= e($d['cadre_url']) ?>" alt="" style="width:72px;height:72px;object-fit:contain;background:var(--bg2);border-radius:var(--r10)">
        <?php endif; ?>
      </div>

      <?php if ($r): ?>
        <h3 style="margin:16px 0 8px">Contrôle automatique</h3>
        <ul style="margin:0;padding-left:1.1em;font-size:.9rem">
          <?php foreach ($r['controles'] as $c): ?>
            <li style="color:<?= $c['etat'] === 'echec' ? 'var(--rouge)' : ($c['etat'] === 'alerte' ? 'var(--gold)' : 'var(--text2)') ?>">
              <?= $c['etat'] === 'ok' ? '✓' : ($c['etat'] === 'alerte' ? '!' : '✗') ?>
              <?= e($c['message']) ?>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>

      <div class="rangee" style="margin-top:16px;align-items:flex-start">
        <form method="post" action="<?= e(url('?p=decider')) ?>">
          <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
          <input type="hidden" name="id" value="<?= e($d['id']) ?>">
          <input type="hidden" name="vers" value="publie">
          <button class="bouton" type="submit">Approuver</button>
        </form>

        <form method="post" action="<?= e(url('?p=decider')) ?>" style="flex:1;min-width:260px">
          <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
          <input type="hidden" name="id" value="<?= e($d['id']) ?>">
          <input name="motif" type="text" required placeholder="Motif, obligatoire pour refuser ou corriger">
          <div class="rangee" style="margin-top:8px">
            <button class="bouton fant petit" type="submit" name="vers" value="corrections">Demander des corrections</button>
            <button class="bouton danger petit" type="submit" name="vers" value="refuse">Refuser</button>
          </div>
        </form>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>
