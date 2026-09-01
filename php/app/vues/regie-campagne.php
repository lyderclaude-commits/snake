<?php
/** Une campagne : ce qu'elle dit, à qui, et où elle en est. */
$erreur = $erreur ?? null;
$message = $message ?? null;
$modifiable = in_array($c['statut'], ['brouillon', 'corrections', 'refuse'], true);
$soumettable = in_array($c['statut'], ['brouillon', 'corrections'], true);
$bouton = function (string $quoi, string $libelle, string $classe = 'bouton', bool $motif = false) use ($c) {
    ob_start(); ?>
    <form method="post" action="<?= e(url('?p=regie-action')) ?>" style="display:inline">
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
      <input type="hidden" name="id" value="<?= e($c['id']) ?>">
      <input type="hidden" name="quoi" value="<?= e($quoi) ?>">
      <?php if ($motif): ?><input type="hidden" name="motif" value=""><?php endif; ?>
      <button class="<?= e($classe) ?>" type="submit"><?= e($libelle) ?></button>
    </form>
    <?php return ob_get_clean();
};
?>
<div class="contenu etroit-large">
  <p class="fil"><a href="<?= e(url('?p=regie')) ?>">← La régie</a></p>

  <?php if ($message): ?><div class="msg ok" role="status"><?= e($message) ?></div><?php endif; ?>
  <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

  <section class="entete">
    <div class="rangee" style="justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap">
      <div>
        <h1 style="margin-bottom:6px"><?= e($c['sujet']) ?></h1>
        <p>
          <span class="pastille <?= e($c['statut']) ?>"><?= e(REGIE_STATUTS[$c['statut']] ?? $c['statut']) ?></span>
          · <?= e(REGIE_CIBLES[$c['cible']][0] ?? $c['cible']) ?>
          · <strong><?= (int) $vise ?></strong> destinataire(s)
          <?php if ($equipe && $auteur): ?> · <?= e($auteur['nom']) ?><?php endif; ?>
        </p>
      </div>
      <?php if ($modifiable): ?>
        <a class="bouton fant" href="<?= e(url('?p=regie-ecrire&id=' . urlencode($c['id']))) ?>">Modifier</a>
      <?php endif; ?>
    </div>
  </section>

  <?php if ($c['motif']): ?>
    <div class="msg <?= $c['statut'] === 'refuse' ? 'err' : '' ?>" style="margin-bottom:16px">
      <strong><?= $c['statut'] === 'refuse' ? 'Refusée' : ($c['statut'] === 'corrections' ? 'À corriger' : 'Note de la régie') ?></strong>
      <p style="margin:.35em 0 0"><?= e($c['motif']) ?></p>
    </div>
  <?php endif; ?>

  <!-- ---------- l'aperçu ---------- -->
  <div class="carte">
    <h3 style="margin:0 0 4px">Ce que la personne recevra</h3>
    <p class="aide" style="margin:0 0 16px">Le pied de désabonnement est ajouté à l’envoi ;
    il n’est pas dans cet aperçu mais il partira.</p>

    <div class="apercu-courriel">
      <div class="entete-courriel">WAKABI BOOST</div>
      <div class="corps-courriel">
        <h4><?= e($c['titre']) ?></h4>
        <?php foreach (preg_split('/\n{2,}/', trim((string) $c['corps'])) ?: [] as $p): ?>
          <p><?= nl2br(e($p)) ?></p>
        <?php endforeach; ?>
        <?php if ($c['lien']): ?>
          <p><span class="faux-bouton"><?= e($c['lien_libelle'] ?: 'Ouvrir') ?></span></p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ---------- l'avancement ---------- -->
  <?php if (in_array($c['statut'], ['prete', 'envoi', 'envoye'], true)): ?>
    <div class="carte" style="margin-top:16px">
      <h3 style="margin:0 0 14px">L’envoi</h3>
      <?php
      $total = max(1, (int) $c['destinataires']);
      $faits = (int) $c['envoyes'] + (int) $c['echecs'];
      ?>
      <div class="marche">
        <div class="haut">
          <span>Messages partis</span>
          <b><?= (int) $c['envoyes'] ?> / <?= (int) $c['destinataires'] ?></b>
        </div>
        <div class="rail"><i style="width:<?= min(100, (int) round($faits / $total * 100)) ?>%"></i></div>
        <span class="taux">
          <?php if ($c['statut'] === 'envoye'): ?>
            Terminé<?= $c['envoye_le'] ? ' le ' . e(gmdate('d/m/Y à H:i', strtotime((string) $c['envoye_le']))) . ' UTC' : '' ?>.
            <?= (int) $c['echecs'] ?> échec(s).
          <?php else: ?>
            Par lots de <?= REGIE_LOT ?>. <?= (int) $c['echecs'] ?> échec(s).
          <?php endif; ?>
        </span>
      </div>
    </div>
  <?php endif; ?>

  <!-- ---------- ce qu'on peut faire ---------- -->
  <div class="carte" style="margin-top:16px">
    <h3 style="margin:0 0 14px">Ce que vous pouvez faire</h3>

    <div class="rangee" style="gap:10px;flex-wrap:wrap">
      <?php if ($soumettable): ?>
        <?= $bouton('soumettre', $equipe ? 'Préparer l’envoi' : 'Soumettre à la régie') ?>
      <?php endif; ?>

      <?php if ($equipe && $c['statut'] === 'en_relecture'): ?>
        <?= $bouton('approuver', 'Approuver') ?>
      <?php endif; ?>

      <?php if ($equipe && in_array($c['statut'], ['prete', 'envoi'], true)): ?>
        <?= $bouton('envoyer', $c['statut'] === 'prete' ? 'Envoyer le premier lot' : 'Envoyer le lot suivant') ?>
      <?php endif; ?>

      <?php if ($modifiable || ($equipe && $c['statut'] !== 'envoi')): ?>
        <?= $bouton('supprimer', 'Supprimer', 'bouton danger') ?>
      <?php endif; ?>
    </div>

    <?php if ($equipe && in_array($c['statut'], ['en_relecture', 'prete'], true)): ?>
      <form method="post" action="<?= e(url('?p=regie-action')) ?>" style="margin-top:18px;border-top:1px solid var(--border);padding-top:16px">
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <input type="hidden" name="id" value="<?= e($c['id']) ?>">
        <div class="champ">
          <label for="r-motif">Motif — obligatoire pour renvoyer ou refuser</label>
          <textarea id="r-motif" name="motif" rows="2"
                    placeholder="Le ton est trop insistant ; retirez la mention du prix."></textarea>
        </div>
        <div class="rangee" style="gap:10px">
          <button class="bouton fant" type="submit" name="quoi" value="corrections">Renvoyer à l’auteur</button>
          <button class="bouton danger" type="submit" name="quoi" value="refuser">Refuser</button>
        </div>
      </form>
    <?php endif; ?>

    <?php if (!$equipe && $c['statut'] === 'en_relecture'): ?>
      <p class="aide" style="margin:14px 0 0">En attente de la régie. Vous serez prévenu par
      notification et par e-mail dès qu’une décision est prise.</p>
    <?php endif; ?>

    <?php if (!$equipe && in_array($c['statut'], ['prete', 'envoi'], true)): ?>
      <p class="aide" style="margin:14px 0 0">Approuvée. L’envoi est déclenché par l’équipe,
      par lots, pour ne pas saturer le serveur d’envoi.</p>
    <?php endif; ?>
  </div>

  <?php if (!$equipe): ?>
    <p class="aide" style="margin-top:14px">
      Il vous reste <strong><?= $quota['max'] < 0 ? 'un nombre illimité d’' : (int) $quota['reste'] . ' ' ?>envois</strong>
      ce mois-ci<?= $quota['max'] < 0 ? '' : ' sur ' . (int) $quota['max'] ?>.
    </p>
  <?php endif; ?>
</div>
