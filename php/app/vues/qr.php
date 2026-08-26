<?php
$etat = !$b ? 'inconnu' : ($b['scanne_le'] ? 'utilise' : 'valide');
$staff = $me && $me['role'] === 'equipe';
?>
<div class="etroit" style="text-align:center">
  <div class="carte">
    <p style="font-size:2.4rem;line-height:1;margin:0">
      <?= $etat === 'valide' ? '🎟️' : ($etat === 'utilise' ? '↩️' : '⚠️') ?>
    </p>
    <h1 style="font-size:1.4rem;margin:.5em 0 0">
      <?= ['inconnu' => 'Badge introuvable', 'utilise' => 'Badge déjà utilisé', 'valide' => 'Badge valide'][$etat] ?>
    </h1>

    <p class="mono" style="font-size:1.25rem;font-weight:700;letter-spacing:.18em;margin:.7em 0 0">
      <?= e(strtoupper(substr($jeton, 0, 10))) ?>
    </p>

    <?php if ($b): ?>
      <p class="aide" style="margin:.2em 0 0">
        <?= e($b['decor_titre']) ?><?= $b['porteur'] ? ' · ' . e($b['porteur']) : '' ?>
      </p>
    <?php endif; ?>

    <p style="color:var(--text2);margin:1em auto 0;max-width:38ch">
      <?= [
        'inconnu' => 'Ce code ne correspond à aucun badge émis. Vérifiez les 10 caractères, ou recréez votre visuel.',
        'utilise' => 'Ce badge a déjà servi à une entrée. Un badge ne vaut qu’un passage.',
        'valide' => 'Présentez ce code à l’entrée. C’est le passage à l’entrée qui crédite vos Koris, pas le téléchargement.',
      ][$etat] ?>
    </p>

    <?php if ($b && $b['scanne_le']): ?>
      <p class="aide" style="margin-top:.5em">Entrée validée le <?= e(substr($b['scanne_le'], 0, 10)) ?>
      à <?= e(substr($b['scanne_le'], 11, 5)) ?></p>
    <?php endif; ?>

    <?php if ($staff && $etat === 'valide'): ?>
      <a class="bouton" style="margin-top:20px" href="<?= e(url('?p=scan&code=' . urlencode(strtoupper($jeton)))) ?>">
        Valider l’entrée
      </a>
    <?php else: ?>
      <a class="bouton fant" style="margin-top:20px"
         href="<?= e($b ? url('?p=decor&slug=' . urlencode($b['decor_slug'])) : url('?p=decors')) ?>">
        <?= $b ? 'Voir ce décor' : 'Voir les décors' ?>
      </a>
    <?php endif; ?>
  </div>

  <?php if (!$staff): ?>
    <p class="aide" style="margin-top:16px">Vous tenez l’entrée ?
      <a href="<?= e(url('?p=connexion')) ?>">Connectez-vous</a> pour valider les passages.</p>
  <?php endif; ?>
</div>
