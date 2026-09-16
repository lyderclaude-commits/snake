<?php
/**
 * « À qui j'écris », à l'écran.
 *
 * Deux colonnes, et une seule question par colonne. À gauche : quelle
 * règle. À droite : ce qu'elle donne, et par quel canal. Le nombre de
 * gauche est celui des gens qui CORRESPONDENT ; celui de droite, ceux
 * qu'on peut JOINDRE. Les confondre serait la seule vraie façon de
 * décevoir ici : un segment qui annonce 114 destinataires puis n'en touche
 * que 96 ferait douter de tous les autres chiffres du produit.
 */
$nb = static fn(int $n): string => number_format($n, 0, ',', ' ');

/** Une adresse de cet écran, en gardant le décor. */
$lien = static function (array $change) use ($decor): string {
    $q = ['p' => 'segments', 'decor' => (string) $decor['slug']];
    foreach ($change as $k => $v) {
        $q[$k] = (string) $v;
    }
    return url('?' . http_build_query($q));
};
?>
<div class="contenu">

  <section class="entete">
    <div class="rangee" style="justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap">
      <div>
        <h1>À qui j’écris</h1>
        <p>
          <strong><?= e((string) $decor['titre']) ?></strong> ·
          les segments se recalculent à chaque ouverture, jamais figés d’avance
        </p>
      </div>
      <a class="bouton fant"
         href="<?= e(url('?p=rapports&decor=' . rawurlencode((string) $decor['slug']))) ?>">Retour au rapport</a>
    </div>
  </section>

  <div class="grille-seg">

    <!-- ------------------- la règle ------------------- -->
    <section class="carte">
      <h2>Le segment</h2>
      <p class="aide" style="margin:4px 0 14px">
        Un seul à la fois. Les nombres sont ceux de maintenant.
      </p>

      <div class="seg-liste">
        <?php foreach ($segments as $s): ?>
          <a class="seg<?= $s['cle'] === $cle ? ' on' : '' ?>"
             href="<?= e($lien(['s' => (string) $s['cle']])) ?>"
             <?= $s['cle'] === $cle ? 'aria-current="true"' : '' ?>>
            <span class="seg-nom"><?= e((string) $s['nom']) ?></span>
            <span class="seg-aide"><?= e((string) $s['aide']) ?></span>
            <span class="seg-n"><b><?= e($nb((int) $s['correspondent'])) ?></b>
              <small><?= e($nb((int) $s['joignables'])) ?> joignables</small></span>
          </a>
        <?php endforeach; ?>
      </div>
    </section>

    <!-- ------------------- ce que ça donne ------------------- -->
    <section class="carte">
      <h2>Ce que ça donne</h2>

      <?php if ((int) $ici['correspondent'] === 0): ?>
        <p class="aide" style="margin-top:12px">
          Personne ne correspond à cette règle sur ce décor. Ce n’est pas une panne :
          c’est une bonne nouvelle pour « n’a jamais téléchargé », et une mauvaise
          pour « est venu ».
        </p>
      <?php else: ?>

        <p class="seg-phrase">
          <strong><?= e($nb((int) $ici['correspondent'])) ?></strong>
          personne<?= (int) $ici['correspondent'] > 1 ? 's' : '' ?> correspond<?= (int) $ici['correspondent'] > 1 ? 'ent' : '' ?>.
          <strong><?= e($nb((int) $ici['joignables'])) ?></strong>
          <?= (int) $ici['joignables'] > 1 ? 'sont joignables' : 'est joignable' ?>,
          et pas toutes par le même canal.
        </p>

        <div class="seg-canaux">
          <div class="seg-canal">
            <span class="puce mail">E-mail</span>
            <b><?= e($nb((int) $ici['email'])) ?></b>
          </div>
          <div class="seg-canal">
            <span class="puce web">Notifications</span>
            <b><?= e($nb((int) $ici['push'])) ?></b>
          </div>
        </div>

        <?php if ((int) $ici['hors'] > 0): ?>
          <div class="msg info" style="margin-top:14px">
            <strong><?= e($nb((int) $ici['hors'])) ?> personne<?= (int) $ici['hors'] > 1 ? 's ne sont' : ' n’est' ?>
            joignable<?= (int) $ici['hors'] > 1 ? 's' : '' ?> par aucun canal.</strong>
            Elles ont fabriqué un badge sans créer de compte et sans accepter les
            notifications. Elles comptent dans vos statistiques, pas dans vos envois.
          </div>
        <?php endif; ?>

        <div class="rangee" style="gap:8px;margin-top:16px;flex-wrap:wrap">
          <?php if ($peut_ecrire && (int) $ici['joignables'] > 0): ?>
            <a class="bouton"
               href="<?= e(url('?p=regie-ecrire&segment=' . rawurlencode((string) $cle)
                    . '&decor=' . rawurlencode((string) $decor['slug']))) ?>">
              Écrire à ces <?= e($nb((int) $ici['joignables'])) ?> personnes</a>
          <?php elseif ((int) $ici['joignables'] > 0): ?>
            <span class="aide">La régie arrive avec l’offre Croissance : sans elle,
            l’export reste à votre disposition.</span>
          <?php endif; ?>
          <a class="bouton fant"
             href="<?= e($lien(['s' => (string) $cle, 'export' => 'csv'])) ?>">Exporter la liste</a>
        </div>

        <p class="aide" style="margin-top:10px">
          Désabonnés écartés, adresses non confirmées écartées, quota décompté :
          un segment ne rouvre aucune porte que la régie a fermée.
        </p>

      <?php endif; ?>

      <?php
      /**
       * Cette phrase est hors du « s’il y a du monde », exprès.
       *
       * Elle n’explique pas un résultat : elle explique le modèle. Un
       * segment vide est justement le moment où l’on se demande « et mon
       * canal Telegram, alors ? » — la réponse doit être là aussi.
       */
      ?>
      <p class="aide" style="margin-top:14px">
        Telegram et WhatsApp n’apparaissent pas ici : ils écrivent à un salon, ou à
        des abonnés du bot que rien ne rattache à un badge. Un segment ne peut donc
        pas les compter, et préfère le dire.
      </p>
    </section>
  </div>

  <!-- ------------------- un aperçu de la liste ------------------- -->
  <?php if ($apercu): ?>
    <section class="carte" style="margin-top:18px">
      <h2>Les huit derniers</h2>
      <p class="aide" style="margin:4px 0 0">
        De quoi vérifier que la règle vise bien ce qu’on croit. L’export porte la liste entière.
      </p>
      <div class="tableau" style="margin-top:12px">
        <table>
          <thead>
            <tr>
              <th>Personne</th>
              <th>Joignable</th>
              <th>Badge créé</th>
              <th>Emporté</th>
              <th>Entré</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($apercu as $l): ?>
              <tr>
                <td>
                  <?= e(((string) ($l['nom'] ?? '')) ?: 'Sans compte') ?>
                  <?php if (($l['email'] ?? '') !== ''): ?>
                    <span class="rap-preuve"><?= e((string) $l['email']) ?></span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ((int) $l['par_email'] === 1): ?><span class="puce mail">E-mail</span><?php endif; ?>
                  <?php if ((int) $l['par_push'] === 1): ?><span class="puce web">Push</span><?php endif; ?>
                  <?php if ((int) $l['par_email'] === 0 && (int) $l['par_push'] === 0): ?>
                    <span class="rap-vide">par aucun canal</span>
                  <?php endif; ?>
                </td>
                <td><?= e(date_fr((string) $l['cree_le'])) ?></td>
                <td><?= $l['telecharge_le'] ? e(date_fr((string) $l['telecharge_le']))
                    : '<span class="rap-vide">jamais</span>' ?></td>
                <td><?= $l['scanne_le'] ? e(date_fr((string) $l['scanne_le']))
                    : '<span class="rap-vide">pas venu</span>' ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>
  <?php endif; ?>

</div>
