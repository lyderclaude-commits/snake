<h1>Contact</h1>
<div class="deux">
  <div>
    <?php if ($envoye): ?>
      <p class="succes" id="envoye">Merci ! Votre message est bien arrivé, nous vous répondons rapidement.</p>
    <?php else: ?>
      <?php if ($erreur): ?><p class="erreur"><?= e($erreur) ?></p><?php endif; ?>
      <form method="post" action="<?= url('contact') ?>" class="formulaire">
        <?= csrf_champ() ?>
        <?php foreach (contact_champs() as $k => $c): ?>
          <label for="c-<?= e($k) ?>"><?= e($c['libelle']) ?><?= $c['requis'] ? ' *' : '' ?></label>
          <?php if ($c['type'] === 'texte_long'): ?>
            <textarea id="c-<?= e($k) ?>" name="<?= e($k) ?>" rows="5"<?= $c['requis'] ? ' required' : '' ?>><?= e((string) ($v[$k] ?? '')) ?></textarea>
          <?php else: ?>
            <input id="c-<?= e($k) ?>" name="<?= e($k) ?>" type="<?= $c['type'] === 'email' ? 'email' : ($c['type'] === 'tel' || $c['type'] === 'whatsapp' ? 'tel' : 'text') ?>" value="<?= e((string) ($v[$k] ?? '')) ?>"<?= $c['requis'] ? ' required' : '' ?>>
          <?php endif; ?>
        <?php endforeach; ?>
        <?php /* Le champ piège : invisible pour un humain, rempli par les robots. */ ?>
        <div class="piege" aria-hidden="true"><label>Site web <input name="site_web" tabindex="-1" autocomplete="off"></label></div>
        <button class="bouton" type="submit">Envoyer</button>
      </form>
    <?php endif; ?>
  </div>
  <aside>
    <h2>Questions fréquentes</h2>
    <?php foreach ($faq as $q): ?>
      <details><summary><?= e((string) $q['question']) ?></summary><p><?= nl2br(e((string) $q['reponse'])) ?></p></details>
    <?php endforeach; ?>
  </aside>
</div>
