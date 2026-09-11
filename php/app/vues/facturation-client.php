<?php
/**
 * La facturation, vue par l'organisateur.
 *
 * Il ouvre cette page pour UNE question : jusqu'à quand ai-je payé ? Tout
 * le reste en découle — ce qu'il consomme à côté de ce qu'il paie, ses
 * documents, et de quoi reprendre la main sans écrire à personne.
 *
 * La frise plutôt qu'un compte à rebours : « 2 jours » seul angoisse sans
 * rien dire de ce qui a été réglé. La barre montre la période entière,
 * donc ce qu'il a acheté, et la part qu'il en a consommée.
 */
$message = $message ?? null;
$erreur = $erreur ?? null;
$suivi = abonnement_suivi($me);
$demande = (string) ($me['offre_demandee'] ?? '');
$prix = (int) (FORMULES[$me['formule'] ?? '']['prix'] ?? 0);

/** La part consommée de la période payée, entre 0 et 1. */
$part = 0.0;
if ($me['abonne_depuis'] && $me['echeance_le']) {
    $a = strtotime((string) $me['abonne_depuis']) ?: 0;
    $b = strtotime((string) $me['echeance_le']) ?: 0;
    $part = $b > $a ? max(0.0, min(1.0, (time() - $a) / ($b - $a))) : 1.0;
}

/** Une jauge de consommation, comme au tableau de bord. */
$jauge = static function (string $libelle, array $q, string $unite): string {
    ob_start(); ?>
    <div class="marche" style="margin:0">
      <div class="haut">
        <span><?= e($libelle) ?></span>
        <b><?= (int) $q['utilises'] ?><?= $q['max'] < 0 ? '' : ' / ' . (int) $q['max'] ?></b>
      </div>
      <div class="rail"><i style="width:<?= $q['max'] < 0
        ? 6 : min(100, (int) round($q['utilises'] / max(1, $q['max']) * 100)) ?>%"></i></div>
      <span class="taux"><?= $q['max'] < 0 ? 'Sans limite avec votre offre.' : e($unite) ?></span>
    </div>
    <?php return ob_get_clean();
};
?>
<div class="contenu">
  <section class="entete">
    <h1>Facturation</h1>
    <p>Votre abonnement et vos documents. Tout ce qui est ici vous concerne, vous seul.</p>
  </section>

  <?php if ($message): ?><div class="msg ok" role="status"><?= e($message) ?></div><?php endif; ?>
  <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

  <!-- ---------------- l'abonnement ---------------- -->
  <div class="carte" style="margin-bottom:16px">
    <div class="rangee" style="justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap">
      <div>
        <h3 style="margin:0">Offre <?= e(formule_libelle($me['formule'] ?? null)) ?></h3>
        <p class="aide" style="margin:4px 0 0">
          <?php if ($suivi): ?>
            <?= e(montant_fr($prix, 'F CFA')) ?> par mois.
          <?php else: ?>
            Gratuite, et sans date de fin.
          <?php endif; ?>
        </p>
      </div>
      <span class="pastille <?= e($etat['ton']) ?>"><?= e($etat['libelle']) ?></span>
    </div>

    <?php if ($suivi && $me['echeance_le']): ?>
      <?php
      /**
       * La frise ne se dessine QUE si l'on connaît le début.
       *
       * Une barre vide à côté de « 29 jours » se lit « rien de consommé »,
       * ce qui serait faux : on ne sait simplement pas depuis quand. Les
       * comptes abonnés avant la v19 sont dans ce cas tant qu'ils n'ont
       * pas été facturés une fois.
       */
      ?>
      <?php if ($me['abonne_depuis']): ?>
        <div class="rail" style="height:9px;background:var(--bg2);border-radius:999px;overflow:hidden;margin:16px 0 6px">
          <i style="display:block;height:100%;border-radius:999px;width:<?= (int) round($part * 100) ?>%;
                    background:<?= $etat['cle'] === 'actif' ? 'var(--teal)'
                      : ($etat['cle'] === 'proche' ? 'var(--gold)' : 'var(--rouge)') ?>"></i>
        </div>
        <div class="rangee" style="justify-content:space-between;font-size:.8rem;color:var(--text2)">
          <span><?= e(date_fr((string) $me['abonne_depuis'])) ?></span>
          <span><?= e(date_fr((string) $me['echeance_le'])) ?></span>
        </div>
      <?php else: ?>
        <p style="margin:16px 0 0;font-size:1.05rem;font-weight:700">
          Payée jusqu’au <?= e(date_fr((string) $me['echeance_le'])) ?></p>
      <?php endif; ?>

      <p class="aide" style="margin:12px 0 0">
        <?php if (($etat['jours'] ?? 0) < 0): ?>
          Votre abonnement est arrivé à terme. Le compte garde tout, badges, campagnes et liens, mais
          repasse aux limites de l’offre gratuite <?= ABONNEMENT_GRACE ?> jours après l’échéance.
        <?php else: ?>
          Un rappel vous parvient une semaine avant l’échéance, puis la veille. Passé l’échéance, le
          compte garde tout et repasse aux limites de l’offre gratuite au bout de
          <?= ABONNEMENT_GRACE ?> jours.
        <?php endif; ?>
      </p>
    <?php elseif ($suivi): ?>
      <p class="aide" style="margin:14px 0 0">Aucune date de fin n’est posée sur votre compte.
      Écrivez-nous si vous pensez que c’est une erreur.</p>
    <?php endif; ?>

    <?php if ($demande && isset(FORMULES[$demande])): ?>
      <div class="msg ok" style="margin:14px 0 0">
        <strong>Changement demandé.</strong>
        <p style="margin:.35em 0 0">Vous passerez en <strong><?= e(formule_libelle($demande)) ?></strong>
        à votre prochaine échéance. D’ici là, rien ne change et rien ne vous est facturé en plus.</p>
      </div>
    <?php endif; ?>
  </div>

  <!-- ---------------- ce que vous consommez ---------------- -->
  <?php if ($suivi): ?>
    <div class="grille g3" style="margin-bottom:16px">
      <div class="carte"><?= $jauge('E-mails ce mois', $quotas['emails'], 'Le compteur repart le 1er du mois.') ?></div>
      <div class="carte"><?= $jauge('Badges ce mois', $quotas['badges'], 'Chaque badge téléchargé compte pour un.') ?></div>
      <div class="stat v" style="display:flex;flex-direction:column;justify-content:center">
        <b><?= e(montant_fr((int) $regle)) ?></b>
        <span>Réglé depuis l’ouverture du compte</span>
      </div>
    </div>
  <?php endif; ?>

  <!-- ---------------- les documents ---------------- -->
  <div class="carte" style="margin-bottom:16px">
    <h3 style="margin:0 0 4px">Mes factures</h3>
    <?php if (!$factures): ?>
      <p class="aide" style="margin:6px 0 0">Aucune facture pour l’instant. Elles apparaîtront ici dès
      votre premier règlement, et vous parviendront aussi par e-mail.</p>
    <?php else: ?>
      <p class="aide" style="margin:0 0 12px">Le PDF se télécharge, s’imprime et se transmet à votre
      comptabilité : il porte toutes les mentions qu’elle demande.</p>
      <div class="tableau">
        <table>
          <thead><tr><th>Numéro</th><th>Période</th><th class="chiffre">Montant</th>
          <th>État</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($factures as $f): ?>
              <tr<?= $f['statut'] === 'annulee' ? ' class="pale"' : '' ?>>
                <td class="mono"><?= e((string) $f['numero']) ?>
                  <?php if ($f['avoir_de']): ?><span class="aide" style="display:block">avoir</span><?php endif; ?>
                </td>
                <td class="mono" style="font-size:.84rem;white-space:nowrap"><?= e(date_fr((string) $f['debut_le'])) ?>
                  → <?= e(date_fr((string) $f['fin_le'])) ?></td>
                <td class="chiffre mono"><?= e(montant_fr((int) $f['montant'])) ?></td>
                <td><span class="pastille <?= $f['statut'] === 'reglee' ? 'publie'
                  : ($f['statut'] === 'annulee' ? 'brouillon' : 'prete') ?>">
                  <?= e(FACTURE_STATUTS[$f['statut'] ?? 'reglee'] ?? '') ?></span></td>
                <td style="text-align:right;white-space:nowrap">
                  <a class="bouton fant petit" href="<?= e(url('?p=facture&id=' . urlencode((string) $f['id']))) ?>">Voir</a>
                  <a class="bouton petit" href="<?= e(url('?p=facture&id=' . urlencode((string) $f['id']) . '&pdf=1')) ?>">PDF</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <!-- ---------------- renouveler, ou changer ---------------- -->
  <div class="carte">
    <h3 style="margin:0 0 4px">Renouveler, ou changer d’offre</h3>
    <p class="aide" style="margin:5px 0 16px">Le règlement se fait avec l’équipe :
    Mobile Money, virement ou espèces. <strong>Rien n’est prélevé automatiquement</strong> : ces boutons préviennent,
    ils ne paient pas.</p>

    <div class="grille g3" style="margin-bottom:16px">
      <?php foreach (FORMULES as $cle => $of): ?>
        <?php if ((int) $of['prix'] === 0) { continue; } ?>
        <?php $sienne = $cle === ($me['formule'] ?? ''); ?>
        <div class="carte" style="box-shadow:none;<?= $sienne ? 'border-color:var(--primary);background:var(--primary-wash)' : '' ?>">
          <div class="rangee" style="justify-content:space-between;align-items:baseline">
            <strong><?= e((string) $of['nom']) ?></strong>
            <?php if ($sienne): ?><span class="pastille formule">votre offre</span><?php endif; ?>
          </div>
          <p style="margin:6px 0 4px;font-size:1.15rem;font-weight:800;letter-spacing:-.02em">
            <?= e(montant_fr((int) $of['prix'])) ?>
            <span class="aide" style="font-size:.72rem;font-weight:600">F CFA / mois</span></p>
          <p class="aide" style="margin:0">
            <?= (int) $of['campagnes'] < 0 ? 'Campagnes sans limite' : (int) $of['campagnes'] . ' campagnes' ?>,
            <?= (int) $of['telechargements'] < 0 ? 'badges sans limite' : number_format((int) $of['telechargements'], 0, ',', ' ') . ' badges' ?><?php
            if ($of['regie']): ?>, régie et canaux<?php endif; ?>.
          </p>
          <?php if (!$sienne && $cle !== $demande): ?>
            <form method="post" action="<?= e(url('?p=facturation')) ?>" style="margin-top:10px">
              <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
              <input type="hidden" name="quoi" value="changer">
              <input type="hidden" name="formule" value="<?= e((string) $cle) ?>">
              <button class="bouton fant petit" type="submit">Demander cette offre</button>
            </form>
          <?php elseif ($cle === $demande): ?>
            <p class="aide" style="margin:10px 0 0"><strong>Demandée</strong> : effective à votre
            prochaine échéance.</p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($suivi): ?>
      <form method="post" action="<?= e(url('?p=facturation')) ?>">
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <input type="hidden" name="quoi" value="renouveler">
        <button class="bouton" type="submit">Renouveler <?= e(formule_libelle($me['formule'] ?? null)) ?>
          · <?= e(montant_fr($prix, 'F CFA')) ?></button>
      </form>
      <p class="aide" style="margin:10px 0 0">Un changement d’offre prend effet à la
      <strong>prochaine échéance</strong>, jamais au milieu d’une période déjà payée : vous ne perdez
      aucun jour de ce que vous avez réglé.</p>
    <?php else: ?>
      <p class="aide" style="margin:0">Vous êtes sur l’offre gratuite. Demandez une offre ci-dessus :
      l’équipe vous recontacte pour le règlement.</p>
    <?php endif; ?>
  </div>
</div>
