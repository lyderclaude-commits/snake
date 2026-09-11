<?php
/**
 * La facturation, vue par l'équipe.
 *
 * Un écran qu'on ouvre le 1er du mois pour répondre à une seule question :
 * QUI DOIS-JE RELANCER ? D'où l'ordre — les échus d'abord, les échéances
 * proches ensuite, les tranquilles en bas — plutôt que l'ordre
 * alphabétique, qui obligerait à lire toutes les lignes pour trouver
 * celles qui comptent.
 */
$message = $message ?? null;
$erreur = $erreur ?? null;
$mois = gmdate('Y-m');

$compteurs = ['' => count($lignes)];
foreach ($lignes as $l) {
    $cle = $l['etat']['cle'];
    $compteurs[$cle] = ($compteurs[$cle] ?? 0) + 1;
}
$filtres = [
    '' => 'Tous', 'echu' => 'Échus', 'retard' => 'En retard', 'proche' => 'Échéance proche',
    'actif' => 'Actifs', 'sans_echeance' => 'Sans échéance', 'gratuit' => 'Sans abonnement',
];
$visibles = $filtre === ''
    ? $lignes
    : array_values(array_filter($lignes, fn(array $l): bool => $l['etat']['cle'] === $filtre));

/** Le formulaire de règlement, replié sous une facture. */
$regler = static function (array $f): string {
    ob_start(); ?>
    <details class="sup">
      <summary class="bouton petit">Régler…</summary>
      <div class="sup-p mot">
        <form method="post" action="<?= e(url('?p=facturation')) ?>">
          <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
          <input type="hidden" name="quoi" value="regler">
          <input type="hidden" name="facture" value="<?= e((string) $f['id']) ?>">
          <div class="champ" style="margin:0 0 8px">
            <label>Mode</label>
            <select name="mode">
              <?php foreach (FACTURE_MODES as $cle => $lib): ?>
                <option value="<?= e($cle) ?>"><?= e($lib) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="champ" style="margin:0 0 10px">
            <label>Référence</label>
            <input name="reference" type="text" placeholder="MM-8841203">
          </div>
          <button class="bouton petit" type="submit">Marquer réglée</button>
        </form>
      </div>
    </details>
    <?php return ob_get_clean();
};
?>
<div class="contenu">
  <section class="entete">
    <div class="rangee" style="justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap">
      <div>
        <h1>Facturation</h1>
        <p>Les abonnements en cours, ce qui arrive à échéance, et les factures émises.</p>
      </div>
      <div class="rangee" style="gap:8px">
        <a class="bouton fant" href="<?= e(url('?p=facturation&export=csv&mois=' . $mois)) ?>">Exporter le mois</a>
        <form method="post" action="<?= e(url('?p=facturation')) ?>">
          <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
          <input type="hidden" name="quoi" value="relancer">
          <button class="bouton" type="submit">Relancer les échéances</button>
        </form>
      </div>
    </div>
  </section>

  <?php if ($message): ?><div class="msg ok" role="status"><?= e($message) ?></div><?php endif; ?>
  <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

  <?php if (!$courriel): ?>
    <div class="msg err" style="margin-bottom:16px">
      <strong>Le transport e-mail est éteint.</strong>
      <p style="margin:.35em 0 0">Vous pouvez émettre des factures, mais aucune ne partira :
      <a href="<?= e(url('?p=reglages')) ?>">réglez le serveur d’envoi</a>.</p>
    </div>
  <?php endif; ?>

  <div class="grille g4" style="margin-bottom:18px">
    <div class="stat v">
      <b><?= e(montant_fr((int) $bilan['encaisse'])) ?></b>
      <span>Encaissé ce mois, avoirs déduits</span>
    </div>
    <div class="stat p">
      <b><?= e(montant_fr((int) $bilan['recurrent'])) ?></b>
      <span>Récurrent, par mois</span>
    </div>
    <div class="stat o">
      <b><?= (int) $bilan['proches'] ?></b>
      <span>À relancer sous 7 jours<?= $bilan['proches_montant'] ? ' · ' . e(montant_fr((int) $bilan['proches_montant'])) : '' ?></span>
    </div>
    <div class="stat">
      <b style="color:<?= $bilan['retards'] ? '#B91C1C' : 'inherit' ?>"><?= (int) $bilan['retards'] ?></b>
      <span>En retard ou échus<?= $bilan['attendu'] ? ' · ' . e(montant_fr((int) $bilan['attendu'])) . ' à régler' : '' ?></span>
    </div>
  </div>

  <?php
  /* ---------------- émettre une facture ---------------- */
  if ($facturer):
    $prix = (int) (FORMULES[$facturer['formule']]['prix'] ?? 0);
    $demande = (string) ($facturer['offre_demandee'] ?? '');
  ?>
    <div class="carte" style="margin-bottom:18px;border-color:var(--primary)">
      <div class="rangee" style="justify-content:space-between;align-items:baseline;flex-wrap:wrap">
        <h3 style="margin:0">Facturer <?= e((string) ($facturer['organisation'] ?: $facturer['nom'])) ?></h3>
        <a class="bouton fant petit" href="<?= e(url('?p=facturation')) ?>">Annuler</a>
      </div>
      <p class="aide" style="margin:6px 0 14px">
        Offre <strong><?= e(formule_libelle($facturer['formule'])) ?></strong>
        <?php if ($facturer['echeance_le']): ?>
          · échéance actuelle le <?= e(date_fr((string) $facturer['echeance_le'])) ?>
        <?php else: ?>
          · aucune échéance posée
        <?php endif; ?>
      </p>

      <?php if ($demande && isset(FORMULES[$demande])): ?>
        <div class="msg ok" style="margin:0 0 14px">
          <strong>Changement d’offre demandé.</strong>
          <p style="margin:.35em 0 0">Ce client demande à passer en
          <strong><?= e(formule_libelle($demande)) ?></strong>
          (<?= e(montant_fr((int) (FORMULES[$demande]['prix'] ?? 0))) ?> par mois).
          Émettre la facture appliquera le changement : c’est le moment prévu pour cela.</p>
        </div>
      <?php endif; ?>

      <form method="post" action="<?= e(url('?p=facturation')) ?>">
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <input type="hidden" name="quoi" value="emettre">
        <input type="hidden" name="id" value="<?= e((string) $facturer['id']) ?>">

        <div class="grille g3">
          <div class="champ">
            <label for="f-debut">Début de la période</label>
            <input id="f-debut" name="debut" type="date" value="<?= e(gmdate('Y-m-d')) ?>">
            <p class="aide">Par défaut aujourd’hui. Reculez-la si le client paie en retard
            une période déjà commencée.</p>
          </div>
          <div class="champ">
            <label for="f-jours">Durée, en jours</label>
            <input id="f-jours" name="jours" type="number" min="1" max="730" value="<?= ABONNEMENT_JOURS ?>">
          </div>
          <div class="champ">
            <label for="f-montant">Montant TTC</label>
            <input id="f-montant" name="montant" type="number" min="0" step="500"
                   value="<?= (int) ($demande && isset(FORMULES[$demande]) ? FORMULES[$demande]['prix'] : $prix) ?>">
          </div>
        </div>

        <div class="grille g3">
          <div class="champ">
            <label for="f-statut">État</label>
            <select id="f-statut" name="statut">
              <option value="reglee">Réglée, encaissée</option>
              <option value="a_regler">À régler, envoyée avant paiement</option>
            </select>
          </div>
          <div class="champ">
            <label for="f-mode">Mode de règlement</label>
            <select id="f-mode" name="mode">
              <?php foreach (FACTURE_MODES as $cle => $lib): ?>
                <option value="<?= e($cle) ?>"><?= e($lib) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="champ">
            <label for="f-reference">Référence du versement</label>
            <input id="f-reference" name="reference" type="text" placeholder="MM-8841203">
          </div>
        </div>

        <div class="champ">
          <label for="f-note">Note sur le document <span class="aide">(facultative)</span></label>
          <input id="f-note" name="note" type="text" maxlength="200"
                 placeholder="Réglé en deux fois, second versement le 20.">
        </div>

        <label class="coche" style="display:flex;gap:9px;align-items:flex-start;margin:4px 0 14px">
          <input type="checkbox" name="envoyer" value="1" <?= $courriel ? 'checked' : 'disabled' ?>>
          <span>Envoyer la facture par e-mail à <strong><?= e((string) $facturer['email']) ?></strong>,
          le PDF en pièce jointe.
          <?php if (!$courriel): ?><br><span class="aide">Impossible : le transport est éteint.</span><?php endif; ?></span>
        </label>

        <button class="bouton" type="submit">Émettre la facture</button>
        <p class="aide" style="margin:10px 0 0">Le document est figé à l’émission : changer le tarif
        de l’offre demain ne réécrira pas cette facture.</p>
      </form>
    </div>
  <?php endif; ?>

  <!-- ---------------- les abonnements ---------------- -->
  <div class="rangee" style="gap:8px;margin-bottom:12px;flex-wrap:wrap">
    <?php foreach ($filtres as $cle => $lib): ?>
      <?php $n = $cle === '' ? count($lignes) : ($compteurs[$cle] ?? 0); ?>
      <?php if ($cle === '' || $n > 0): ?>
        <a class="bouton <?= $filtre === $cle ? '' : 'fant' ?> petit"
           href="<?= e(url('?p=facturation' . ($cle === '' ? '' : '&etat=' . $cle))) ?>"><?= e($lib) ?> <b><?= $n ?></b></a>
      <?php endif; ?>
    <?php endforeach; ?>
  </div>

  <?php if (!$visibles): ?>
    <div class="carte"><p class="aide" style="margin:0">Aucun compte dans cet état.</p></div>
  <?php else: ?>
    <div class="tableau">
      <table>
        <thead>
          <tr><th>Organisateur</th><th>Offre</th><th>Période en cours</th><th>Reste</th>
          <th class="chiffre">Dernier document</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($visibles as $l):
            $c = $l['compte'];
            $etat = $l['etat'];
            $f = $l['derniere']; ?>
            <tr>
              <td>
                <a href="<?= e(url('?p=organisateur&id=' . urlencode((string) $c['id']))) ?>">
                  <strong><?= e((string) $c['nom']) ?></strong></a>
                <span class="aide" style="display:block">
                  <?= e((string) ($c['organisation'] ?: 'Sans structure')) ?>
                  <?php if ($c['ville']): ?> · <?= e(ucfirst((string) $c['ville'])) ?><?php endif; ?>
                </span>
              </td>
              <td>
                <span class="pastille formule"><?= e(formule_libelle($c['formule'])) ?></span>
                <?php if (($c['offre_demandee'] ?? '') !== ''): ?>
                  <span class="pastille corrections" style="margin-left:4px">demande
                  <?= e(formule_libelle((string) $c['offre_demandee'])) ?></span>
                <?php endif; ?>
              </td>
              <td class="mono" style="white-space:nowrap;font-size:.86rem">
                <?php if ($c['echeance_le']): ?>
                  <?php if ($l['depuis']): ?><?= e(date_fr((string) $l['depuis'])) ?> → <?php
                  else: ?><span class="aide">jusqu’au </span><?php endif; ?>
                  <?= e(date_fr((string) $c['echeance_le'])) ?>
                <?php else: ?>
                  <span class="aide">jamais facturé</span>
                <?php endif; ?>
              </td>
              <td><span class="pastille <?= e($etat['ton']) ?>"><?= e($etat['libelle']) ?></span></td>
              <td class="chiffre">
                <?php if ($f): ?>
                  <span class="mono"><?= e(montant_fr((int) $f['montant'])) ?></span>
                  <span class="aide" style="display:block"><?= e((string) $f['numero']) ?>
                  · <?= e(FACTURE_STATUTS[$f['statut'] ?? 'reglee'] ?? '') ?></span>
                <?php else: ?><span class="aide">aucun</span><?php endif; ?>
              </td>
              <td style="text-align:right;white-space:nowrap">
                <?php if ($etat['cle'] === 'gratuit'): ?>
                  <a class="bouton fant petit"
                     href="<?= e(url('?p=organisateur&id=' . urlencode((string) $c['id']))) ?>">Poser une offre</a>
                <?php else: ?>
                  <a class="bouton <?= in_array($etat['cle'], ['echu', 'retard', 'proche', 'sans_echeance'], true) ? '' : 'fant' ?> petit"
                     href="<?= e(url('?p=facturation&facturer=' . urlencode((string) $c['id']))) ?>">Facturer</a>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <!-- ---------------- les factures du mois ---------------- -->
  <div class="carte" style="margin-top:18px">
    <div class="rangee" style="justify-content:space-between;align-items:baseline;flex-wrap:wrap">
      <h3 style="margin:0">Les documents de <?= e(mois_fr(maintenant())) ?></h3>
      <span class="aide"><?= count($factures) ?> document(s)</span>
    </div>
    <?php if (!$factures): ?>
      <p class="aide" style="margin:8px 0 0">Rien d’émis ce mois-ci.</p>
    <?php else: ?>
      <div class="tableau" style="margin-top:12px">
        <table>
          <thead><tr><th>Numéro</th><th>Client</th><th>Période</th>
          <th class="chiffre">Montant</th><th>État</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($factures as $f): ?>
            <tr<?= $f['statut'] === 'annulee' ? ' class="pale"' : '' ?>>
              <td class="mono"><?= e((string) $f['numero']) ?>
                <?php if ($f['avoir_de']): ?><span class="aide" style="display:block">avoir</span><?php endif; ?>
              </td>
              <td><?= e((string) ($f['client_org'] ?: $f['client_nom'])) ?></td>
              <td class="mono" style="font-size:.84rem;white-space:nowrap"><?= e(date_fr((string) $f['debut_le'])) ?>
                → <?= e(date_fr((string) $f['fin_le'])) ?></td>
              <td class="chiffre mono"><?= e(montant_fr((int) $f['montant'])) ?></td>
              <td>
                <span class="pastille <?= $f['statut'] === 'reglee' ? 'publie' : ($f['statut'] === 'annulee' ? 'brouillon' : 'prete') ?>">
                  <?= e(FACTURE_STATUTS[$f['statut'] ?? 'reglee'] ?? '') ?></span>
                <?php if ($f['envoyee_le']): ?>
                  <span class="aide" style="display:block">envoyée le <?= e(date_fr((string) $f['envoyee_le'])) ?></span>
                <?php endif; ?>
              </td>
              <td style="text-align:right;white-space:nowrap">
                <a class="bouton fant petit" href="<?= e(url('?p=facture&id=' . urlencode((string) $f['id']))) ?>">Voir</a>
                <a class="bouton fant petit" href="<?= e(url('?p=facture&id=' . urlencode((string) $f['id']) . '&pdf=1')) ?>">PDF</a>
                <?php if ($f['statut'] === 'a_regler'): ?><?= $regler($f) ?><?php endif; ?>
                <?php if ($f['statut'] !== 'annulee' && !$f['avoir_de']): ?>
                  <form method="post" action="<?= e(url('?p=facturation')) ?>" style="display:inline">
                    <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
                    <input type="hidden" name="quoi" value="envoyer">
                    <input type="hidden" name="facture" value="<?= e((string) $f['id']) ?>">
                    <button class="bouton fant petit" type="submit">Envoyer</button>
                  </form>
                  <details class="sup">
                    <summary class="bouton fant petit">Annuler…</summary>
                    <div class="sup-p mot">
                      <p>Une facture ne se supprime pas : elle s’annule par un avoir, qui porte son
                      propre numéro et reste à côté d’elle.</p>
                      <form method="post" action="<?= e(url('?p=facturation')) ?>">
                        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
                        <input type="hidden" name="quoi" value="avoir">
                        <input type="hidden" name="facture" value="<?= e((string) $f['id']) ?>">
                        <div class="champ" style="margin:0 0 10px">
                          <label>Motif</label>
                          <input name="motif" type="text" placeholder="Erreur de montant">
                        </div>
                        <button class="bouton danger petit" type="submit">Émettre l’avoir</button>
                      </form>
                    </div>
                  </details>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="carte" style="margin-top:16px">
    <h3 style="margin:0 0 4px">L’identité qui figure sur vos factures</h3>
    <p class="aide" style="margin:0 0 12px">
      <?= e((string) $reglages['fact_raison']) ?><?php
      if ($reglages['fact_rccm']): ?> · RCCM <?= e((string) $reglages['fact_rccm']) ?><?php endif;
      if ($reglages['fact_nif']): ?> · NIF <?= e((string) $reglages['fact_nif']) ?><?php endif; ?>
      · TVA <?= (int) $reglages['fact_tva'] > 0
        ? e(rtrim(rtrim(number_format((int) $reglages['fact_tva'] / 100, 2, ',', ' '), '0'), ',')) . ' %'
        : 'non applicable' ?>.
      <?php if (!$reglages['fact_rccm'] || !$reglages['fact_nif']): ?>
        <br><strong>Il manque le RCCM ou le NIF :</strong> un comptable refuse une facture qui n’en
        porte pas.
      <?php endif; ?>
    </p>
    <a class="bouton fant" href="<?= e(url('?p=reglages-facturation')) ?>">Modifier l’identité</a>
  </div>
</div>
