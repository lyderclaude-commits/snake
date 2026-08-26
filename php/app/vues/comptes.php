<div class="contenu">
  <section class="entete"><h1>Comptes</h1><p><?= count($liste) ?> inscrits.</p></section>

  <?php if (!empty($_GET['ok'])): ?><div class="msg ok" role="status"><?= e($_GET['ok']) ?></div><?php endif; ?>
  <?php if (!empty($_GET['err'])): ?><div class="msg err" role="alert"><?= e($_GET['err']) ?></div><?php endif; ?>

  <div class="tableau">
    <table>
      <thead><tr><th>Compte</th><th>Structure</th><th>Rôle</th><th>État</th></tr></thead>
      <tbody>
      <?php foreach ($liste as $c): ?>
        <tr>
          <td><b><?= e($c['nom']) ?></b><br><span class="aide"><?= e($c['email']) ?></span></td>
          <td><?= e($c['organisation'] ?: '—') ?><br><span class="aide"><?= e($c['ville'] ?: '') ?></span></td>
          <td>
            <?php if ($c['id'] === $me['id']): ?>
              <span class="aide">Vous — non modifiable</span>
            <?php else: ?>
              <form method="post" action="<?= e(url('?p=role')) ?>" class="rangee" style="gap:6px">
                <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
                <input type="hidden" name="id" value="<?= e($c['id']) ?>">
                <select name="role" style="width:auto">
                  <?php foreach (['participant' => 'Participant', 'partenaire' => 'Partenaire', 'equipe' => 'Équipe'] as $k => $v): ?>
                    <option value="<?= e($k) ?>" <?= $c['role'] === $k ? 'selected' : '' ?>><?= e($v) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="bouton fant petit" type="submit">OK</button>
              </form>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($c['id'] !== $me['id']): ?>
              <form method="post" action="<?= e(url('?p=suspendre')) ?>">
                <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
                <input type="hidden" name="id" value="<?= e($c['id']) ?>">
                <button class="bouton <?= $c['suspendu'] ? 'fant' : 'danger' ?> petit" type="submit">
                  <?= $c['suspendu'] ? 'Réactiver' : 'Suspendre' ?>
                </button>
              </form>
            <?php else: ?><span class="pastille publie">Actif</span><?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <p class="aide" style="margin-top:12px">Suspendre coupe immédiatement les sessions ouvertes.
  Un administrateur ne peut ni se rétrograder ni se suspendre lui-même — sans quoi
  l’installation pourrait se retrouver sans administrateur.</p>
</div>
