<div class="contenu">
  <section class="entete">
    <div class="rangee" style="justify-content:space-between">
      <div>
        <h1>Mes campagnes</h1>
        <p><?= e($me['organisation'] ?: $me['nom']) ?></p>
      </div>
      <a class="bouton" href="<?= e(url('?p=nouveau')) ?>">Nouveau décor</a>
    </div>
  </section>

  <?php if (!empty($_GET['ok'])): ?><div class="msg ok" role="status"><?= e($_GET['ok']) ?></div><?php endif; ?>
  <?php if (!empty($_GET['err'])): ?><div class="msg err" role="alert"><?= e($_GET['err']) ?></div><?php endif; ?>

  <?php
  /**
   * Le rappel de confirmation, tant qu'elle manque.
   *
   * Placé ici et pas au moment de soumettre : découvrir la condition au
   * moment où l'on croit avoir fini, c'est l'apprendre trop tard. Il
   * n'apparaît que si l'on sait envoyer le lien — sinon il désignerait un
   * obstacle qui n'existe pas.
   */
  ?>
  <?php if (verification_exigee() && !email_verifie($me)): ?>
    <div class="msg err" style="margin-bottom:16px">
      <strong>Confirmez votre adresse pour pouvoir soumettre</strong>
      <p style="margin:.35em 0 .7em">Un lien est parti vers <?= e($me['email']) ?>. C’est par là que
      vous arrivera la décision de relecture — nous devons donc savoir que l’adresse existe.</p>
      <form method="post" action="<?= e(url('?p=renvoyer-verification')) ?>" style="margin:0">
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <button class="bouton petit" type="submit">M’envoyer un nouveau lien</button>
      </form>
    </div>
  <?php endif; ?>

  <?php
  /**
   * Les statistiques complètes sont une ligne d'offre.
   *
   * Découverte donne « stats de base » : les vues et les téléchargements —
   * ce qu'un générateur d'images quelconque sait déjà compter. La PRÉSENCE
   * réelle et le taux de conversion sont ce que Wakabi mesure et que
   * personne d'autre ne mesure ; c'est donc ce qui s'achète.
   */
  $stats_completes = capacite($me, 'stats');
  $emis = 0; $vus = 0; $presents = 0;
  foreach ($liste as $d) { $p = presence($d['id']); $emis += $p['emis']; $presents += $p['scannes']; $vus += (int) $d['vues']; }
  ?>
  <div class="grille g4" style="margin-bottom:18px">
    <div class="stat p"><b><?= count($liste) ?></b><span>campagnes</span></div>
    <div class="stat"><b><?= $vus ?></b><span>vues</span></div>
    <div class="stat o"><b><?= $emis ?></b><span>badges téléchargés</span></div>
    <?php if ($stats_completes): ?>
      <div class="stat v"><b><?= $presents ?></b><span>présences scannées</span></div>
    <?php else: ?>
      <div class="stat verrou">
        <b aria-hidden="true">✕</b>
        <span>présences scannées — offre <?= e(formule_libelle(offre_qui_debloque('stats'))) ?></span>
      </div>
    <?php endif; ?>
  </div>

  <?php
  /**
   * Ce que l'offre donne, et où l'on en est.
   *
   * Le chiffre est affiché AVANT de buter dessus : découvrir sa limite au
   * moment de soumettre une campagne préparée, c'est la découvrir trop tard.
   *
   * Les trois compteurs d'abord — ce sont eux qui s'épuisent et qui
   * bloquent —, puis les lignes incluses, puis celles qui ne le sont pas
   * avec l'offre qui les ouvre. Cacher ce qu'on n'a pas empêcherait de le
   * vendre ; le montrer barré, avec ce qu'il faut pour l'avoir, est plus
   * honnête et plus utile.
   */
  $bilan = bilan_offre($me);
  $offre = $bilan['offre'];
  $inclus = $manquant = [];
  foreach ($bilan['lignes'] as $cle => $l) {
      if ($l['nature'] === 'compteur') {
          continue;
      }
      if ($l['inclus']) {
          $inclus[$cle] = $l;
      } else {
          $manquant[$cle] = $l;
      }
  }
  ?>
  <div class="carte" style="margin-bottom:22px">
    <div class="rangee" style="justify-content:space-between;align-items:baseline">
      <h3 style="margin:0">Offre <?= e($offre['nom']) ?></h3>
      <a class="aide" href="<?= e(url('#tarifs')) ?>">Comparer les offres</a>
    </div>

    <div class="grille g2" style="margin-top:14px">
      <?php foreach (['campagnes', 'telechargements', 'liens_courts'] as $cle):
          $l = $bilan['lignes'][$cle];
          if (!$l['inclus']) {
              continue;
          } ?>
        <div class="marche">
          <div class="haut">
            <span><?= e($l['libelle']) ?><?= $cle === 'telechargements' ? ' ce mois' : '' ?></span>
            <b><?= (int) $l['consomme'] ?><?= $l['max'] < 0 ? '' : ' / ' . $l['max'] ?></b>
          </div>
          <div class="rail"><i style="width:<?= $l['max'] < 0 ? 6 : $l['part'] ?>%"></i></div>
          <span class="taux">
            <?php if ($l['max'] < 0): ?>
              Sans limite avec votre offre.
            <?php elseif ($l['reste'] === 0): ?>
              <strong>Épuisé.</strong> <?= e($l['aide']) ?>
            <?php else: ?>
              Il vous en reste <strong><?= (int) $l['reste'] ?></strong>. <?= e($l['aide']) ?>
            <?php endif; ?>
          </span>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($bilan['bonus'] > 0): ?>
      <p class="aide" style="margin:12px 0 0">Dont <strong><?= $bilan['bonus'] ?></strong>
      téléchargements accordés par l’équipe en plus de votre offre.</p>
    <?php endif; ?>

    <div class="grille g2" style="margin-top:18px;gap:18px">
      <div>
        <p class="pas" style="margin:0 0 8px">Compris dans votre offre</p>
        <?php if (!$inclus): ?>
          <?php
          /* Découverte n'ouvre aucune capacité : une colonne vide donnerait
             l'impression d'un écran cassé, alors que l'offre donne bien
             quelque chose — les compteurs juste au-dessus, et le Studio. */
          ?>
          <p class="aide" style="margin:0">Les compteurs ci-dessus, et le Studio complet :
          gabarits, cadres, recadrage, export haute définition et partage. C’est déjà de quoi
          faire une vraie campagne.</p>
        <?php endif; ?>
        <ul class="liste-offre">
          <?php foreach ($inclus as $l): ?>
            <li><span class="oui" aria-hidden="true">✓</span>
              <span><?= e($l['libelle']) ?>
                <?php if ($l['nature'] === 'service'): ?>
                  <span class="etiquette">l’équipe s’en charge</span>
                <?php endif; ?>
                <span class="aide" style="display:block"><?= e($l['aide']) ?></span>
              </span></li>
          <?php endforeach; ?>
        </ul>
      </div>

      <?php if ($manquant): ?>
        <div>
          <p class="pas" style="margin:0 0 8px">Pas dans votre offre</p>
          <ul class="liste-offre">
            <?php foreach ($manquant as $l): ?>
              <li><span class="non" aria-hidden="true">✕</span>
                <span class="hors"><?= e($l['libelle']) ?>
                  <span class="aide" style="display:block">
                    <?= e($l['aide']) ?>
                    <?php if ($l['debloque']): ?>
                      <strong>Avec l’offre <?= e(formule_libelle($l['debloque'])) ?>.</strong>
                    <?php endif; ?>
                  </span>
                </span></li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($stats_completes && $emis > 0 && $presents === 0): ?>
    <div class="msg info">
      <strong>C’est le QR qui fait la différence.</strong> <?= $emis ?> badge(s) téléchargé(s),
      0 personne réellement venue. Un générateur classique s’arrête au premier chiffre.
      Scannez les badges à l’entrée pour mesurer le second.
    </div>
  <?php endif; ?>

  <?php if (!$liste): ?>
    <div class="carte"><p style="margin:0;color:var(--text2)">Aucune campagne. Créez votre premier décor.</p></div>
  <?php else: foreach ($liste as $d):
      $p = presence($d['id']); $rapport = lire_prevol($d['id']); ?>
    <div class="carte" style="margin-bottom:14px">
      <div class="rangee" style="justify-content:space-between">
        <div>
          <b class="display" style="font-size:1.05rem"><?= e($d['titre']) ?></b>
          <span class="pastille <?= e($d['statut']) ?>" style="margin-left:8px"><?= e(statut_libelle($d['statut'])) ?></span>
        </div>
        <span class="aide">↓ <?= (int) $d['telechargements'] ?> · 👁 <?= (int) $d['vues'] ?><?php
          if ($stats_completes): ?> · ✓ <?= $p['scannes'] ?><?php
            if ($p['emis'] > 0): ?> (<?= (int) round($p['taux'] * 100) ?> %)<?php endif;
          endif; ?></span>
      </div>

      <?php if ($d['motif']): ?>
        <div class="msg <?= $d['statut'] === 'refuse' ? 'err' : 'info' ?>" style="margin:12px 0 0">
          <strong>Retour de relecture :</strong> <?= e($d['motif']) ?>
        </div>
      <?php endif; ?>

      <?php if ($rapport && !$rapport['passe']): ?>
        <div class="msg err" style="margin:12px 0 0">
          <strong>Le contrôle automatique a relevé des problèmes bloquants.</strong>
          <ul><?php foreach ($rapport['controles'] as $c): if ($c['etat'] === 'echec'): ?>
            <li><?= e($c['message']) ?></li>
          <?php endif; endforeach; ?></ul>
        </div>
      <?php endif; ?>

      <div class="rangee" style="margin-top:12px">
        <?php if (in_array($d['statut'], ['brouillon', 'corrections', 'refuse'], true)): ?>
          <a class="bouton fant petit" href="<?= e(url('?p=modifier&id=' . urlencode($d['id']))) ?>">Modifier</a>
        <?php endif; ?>
        <?php if (in_array($d['statut'], ['brouillon', 'corrections'], true)): ?>
          <form method="post" action="<?= e(url('?p=soumettre')) ?>">
            <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
            <input type="hidden" name="id" value="<?= e($d['id']) ?>">
            <button class="bouton petit" type="submit">Soumettre à la relecture</button>
          </form>
        <?php endif; ?>
        <?php if ($d['statut'] === 'publie'): ?>
          <a class="bouton fant petit" href="<?= e(url('?p=decor&slug=' . urlencode($d['slug']))) ?>">Voir en ligne</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>
