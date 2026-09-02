<?php
/**
 * La fiche d'un compte, vue de l'équipe.
 *
 * Le même bilan que celui de l'organisateur — même fonction, mêmes chiffres
 * — plus ce qu'un commercial demande toujours : depuis quand, combien, et
 * sur quelle limite ça bute. Puis les leviers : l'offre, la soupape de
 * téléchargements, la suspension, une note interne.
 */
$c = $fiche['compte'];
$b = $fiche['bilan'];
$t = $fiche['totaux'];
$moi = $c['id'] === $me['id'];
?>
<div class="contenu">
  <section class="entete">
    <div class="rangee" style="justify-content:space-between;align-items:baseline;gap:12px">
      <div>
        <h1><?= e($c['nom']) ?></h1>
        <p><?= e($c['email']) ?>
          <?php if ($c['organisation']): ?> · <?= e($c['organisation']) ?><?php endif; ?>
          · inscrit le <?= e(gmdate('d/m/Y', strtotime((string) $c['cree_le']))) ?>
        </p>
      </div>
      <a class="bouton fant" href="<?= e(url('?p=comptes')) ?>">Tous les comptes</a>
    </div>
  </section>

  <?php if (!empty($_GET['ok'])): ?><div class="msg ok" role="status"><?= e($_GET['ok']) ?></div><?php endif; ?>
  <?php if (!empty($_GET['err'])): ?><div class="msg err" role="alert"><?= e($_GET['err']) ?></div><?php endif; ?>

  <div class="rangee" style="gap:8px;margin-bottom:16px">
    <span class="pastille <?= $c['suspendu'] ? 'refuse' : 'publie' ?>">
      <?= $c['suspendu'] ? 'Suspendu' : 'Actif' ?>
    </span>
    <span class="pastille"><?= e(role_libelle($c['role'])) ?></span>
    <span class="pastille"><?= e(formule_libelle($c['formule'] ?? null)) ?></span>
    <span class="pastille <?= empty($c['email_verifie_le']) ? 'corrections' : 'publie' ?>">
      <?= empty($c['email_verifie_le']) ? 'Adresse non confirmée' : 'Adresse confirmée' ?>
    </span>
  </div>

  <div class="grille g4" style="margin-bottom:18px">
    <div class="stat p"><b><?= $t['campagnes'] ?></b><span>campagnes</span></div>
    <div class="stat"><b><?= $t['vues'] ?></b><span>vues</span></div>
    <div class="stat o"><b><?= $t['badges'] ?></b><span>badges émis</span></div>
    <div class="stat v"><b><?= $t['presences'] ?></b>
      <span>présences · <?= (int) round($t['taux'] * 100) ?> % de conversion</span></div>
  </div>

  <!-- ---------- la consommation, face à l'offre ---------- -->
  <div class="carte" style="margin-bottom:16px">
    <h3 style="margin:0 0 4px">Consommation de l’offre <?= e($b['offre']['nom']) ?></h3>
    <p class="aide" style="margin:0 0 16px">Ce que ce compte a pris sur ce qu’il a payé.
    Les téléchargements se remettent à zéro le 1er de chaque mois.</p>

    <div class="grille g2">
      <?php foreach (['campagnes', 'telechargements', 'liens_courts'] as $cle):
          $l = $b['lignes'][$cle]; ?>
        <div class="marche">
          <div class="haut">
            <span><?= e($l['libelle']) ?></span>
            <b><?= (int) $l['consomme'] ?><?= $l['max'] < 0 ? '' : ' / ' . $l['max'] ?></b>
          </div>
          <div class="rail"><i style="width:<?= $l['max'] < 0 ? 6 : $l['part'] ?>%"></i></div>
          <span class="taux">
            <?php if (!$l['inclus']): ?>
              Pas dans cette offre.
            <?php elseif ($l['max'] < 0): ?>
              Sans limite.
            <?php elseif ($l['reste'] === 0): ?>
              <strong>Plein.</strong> Ce compte est bloqué jusqu’au 1er du mois prochain.
            <?php else: ?>
              Reste <?= (int) $l['reste'] ?>.
            <?php endif; ?>
          </span>
        </div>
      <?php endforeach; ?>
    </div>

    <?php
    /**
     * Ce que l'offre ouvre et ce qu'elle ferme, en clair.
     *
     * L'équipe doit pouvoir répondre « non, ce n'est pas une panne, c'est
     * son offre » sans avoir à relire la grille tarifaire.
     */
    ?>
    <div class="grille g2" style="margin-top:18px;gap:18px">
      <?php foreach ([[true, 'Ouvert'], [false, 'Fermé']] as [$veut, $titre]): ?>
        <div>
          <p class="pas" style="margin:0 0 8px"><?= $titre ?></p>
          <ul class="liste-offre">
            <?php foreach ($b['lignes'] as $l):
                if ($l['nature'] === 'compteur' || $l['inclus'] !== $veut) {
                    continue;
                } ?>
              <li>
                <span class="<?= $veut ? 'oui' : 'non' ?>" aria-hidden="true"><?= $veut ? '✓' : '✕' ?></span>
                <span class="<?= $veut ? '' : 'hors' ?>"><?= e($l['libelle']) ?>
                  <?php if ($veut && $l['nature'] === 'service'): ?>
                    <span class="etiquette">à rendre par l’équipe</span>
                  <?php endif; ?>
                </span>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ---------- l'abonnement ---------- -->
  <?php if (abonnement_suivi($c)): ?>
    <?php
    $reste = jours_restants($c);
    $factures = factures_de((string) $c['id']);
    ?>
    <div class="carte" style="margin-bottom:16px">
      <div class="rangee" style="justify-content:space-between;align-items:baseline">
        <h3 style="margin:0">Abonnement</h3>
        <span class="pastille <?= $reste === null ? 'brouillon' : ($reste < 0 ? 'refuse' : ($reste <= 7 ? 'corrections' : 'publie')) ?>">
          <?php if ($reste === null): ?>Aucune échéance
          <?php elseif ($reste < -ABONNEMENT_GRACE): ?>Échu
          <?php elseif ($reste < 0): ?>En retard de <?= abs($reste) ?> j
          <?php else: ?><?= $reste ?> jour<?= $reste > 1 ? 's' : '' ?><?php endif; ?>
        </span>
      </div>

      <p class="aide" style="margin:8px 0 14px">
        <?php if ($reste === null): ?>
          Offre <?= e(formule_libelle($c['formule'])) ?> sans date de fin. Enregistrez un
          paiement pour lui en donner une — sinon rien ne relancera ce compte.
        <?php elseif ($reste < 0): ?>
          Échue le <?= e(date_fr((string) $c['echeance_le'])) ?>. Le compte repasse en
          Découverte <?= ABONNEMENT_GRACE ?> jours après l’échéance.
        <?php else: ?>
          Payée jusqu’au <strong><?= e(date_fr((string) $c['echeance_le'])) ?></strong>.
          Un rappel part 7 jours avant, puis la veille.
        <?php endif; ?>
      </p>

      <form method="post" action="<?= e(url('?p=paiement')) ?>" class="rangee" style="gap:10px;flex-wrap:wrap;align-items:flex-end">
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <input type="hidden" name="id" value="<?= e($c['id']) ?>">
        <div class="champ" style="margin:0">
          <label for="p-montant">Montant reçu (F)</label>
          <input id="p-montant" name="montant" type="number" min="0" step="500"
                 style="width:130px" value="<?= (int) (FORMULES[$c['formule']]['prix'] ?? 0) ?>">
        </div>
        <div class="champ" style="margin:0">
          <label for="p-jours">Pour combien de jours</label>
          <input id="p-jours" name="jours" type="number" min="1" max="730"
                 style="width:110px" value="<?= ABONNEMENT_JOURS ?>">
        </div>
        <button class="bouton" type="submit">Enregistrer le paiement</button>
      </form>

      <?php if ($factures): ?>
        <div class="tableau" style="margin-top:16px">
          <table>
            <thead><tr><th>Facture</th><th>Période</th><th class="chiffre">Montant</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($factures as $f): ?>
              <tr>
                <td class="mono"><?= e((string) $f['numero']) ?>
                  <br><span class="aide"><?= e(formule_libelle((string) $f['formule'])) ?></span></td>
                <td><?= e(date_fr((string) $f['debut_le'])) ?> → <?= e(date_fr((string) $f['fin_le'])) ?></td>
                <td class="mono chiffre"><?= number_format((int) $f['montant'], 0, ',', ' ') ?> F</td>
                <td><a class="bouton fant petit" href="<?= e(url('?p=facture&id=' . rawurlencode((string) $f['id']))) ?>">Voir</a></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if (otp_actif($c) && droit($me, 'comptes_internes') && !$moi): ?>
    <div class="carte" style="margin-bottom:16px">
      <h3 style="margin:0 0 4px">Double authentification</h3>
      <p class="aide" style="margin:0 0 12px">En service sur ce compte. Si la personne a perdu
      son téléphone, la lever est la seule issue — elle la remettra ensuite depuis son profil.</p>
      <form method="post" action="<?= e(url('?p=otp-lever')) ?>">
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <input type="hidden" name="id" value="<?= e($c['id']) ?>">
        <button class="bouton danger petit" type="submit">Lever la double authentification</button>
      </form>
    </div>
  <?php endif; ?>

  <!-- ---------- les leviers ---------- -->
  <div class="carte" style="margin-bottom:16px">
    <h3 style="margin:0 0 14px">Gérer ce compte</h3>

    <?php if ($moi): ?>
      <p class="aide" style="margin:0">C’est votre propre compte : ni le rôle, ni la suspension
      ne s’y changent. Sans cette règle, l’installation pourrait se retrouver sans
      administrateur.</p>
    <?php else: ?>
      <form method="post" action="<?= e(url('?p=role')) ?>" class="rangee" style="gap:10px;align-items:end;flex-wrap:wrap">
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <input type="hidden" name="id" value="<?= e($c['id']) ?>">
        <input type="hidden" name="retour" value="fiche">
        <div class="champ" style="margin:0">
          <label for="f-role">Rôle</label>
          <select id="f-role" name="role" style="width:auto">
            <?php foreach (ROLES as $r): ?>
              <option value="<?= e($r) ?>" <?= $c['role'] === $r ? 'selected' : '' ?>><?= e(role_libelle($r)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="champ" style="margin:0">
          <label for="f-formule">Offre</label>
          <select id="f-formule" name="formule" style="width:auto">
            <?php foreach (FORMULES as $cle => $f): ?>
              <option value="<?= e($cle) ?>" <?= ($c['formule'] ?? 'decouverte') === $cle ? 'selected' : '' ?>>
                <?= e($f['nom']) ?> — <?= $f['prix'] ? number_format($f['prix'], 0, ',', ' ') . ' FCFA' : 'gratuit' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <button class="bouton" type="submit">Appliquer</button>
      </form>
      <p class="aide" style="margin:8px 0 18px">Le changement prend effet immédiatement : filigrane,
      Koris, redirection et statistiques suivent l’offre, y compris sur les campagnes déjà en ligne.</p>

      <form method="post" action="<?= e(url('?p=bonus')) ?>" class="rangee" style="gap:10px;align-items:end;flex-wrap:wrap">
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <input type="hidden" name="id" value="<?= e($c['id']) ?>">
        <div class="champ" style="margin:0">
          <label for="f-bonus">Téléchargements accordés en plus</label>
          <input id="f-bonus" name="bonus" type="number" min="0" max="100000" style="width:160px"
                 value="<?= (int) ($c['bonus_telechargements'] ?? 0) ?>">
        </div>
        <button class="bouton fant" type="submit">Accorder</button>
      </form>
      <p class="aide" style="margin:8px 0 18px">La soupape : elle s’ajoute au quota de l’offre,
      sans la changer. De quoi passer un pic d’un soir sans faire monter quelqu’un d’une offre
      qu’il ne veut pas.</p>

      <form method="post" action="<?= e(url('?p=suspendre')) ?>" style="margin:0 0 18px">
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <input type="hidden" name="id" value="<?= e($c['id']) ?>">
        <input type="hidden" name="retour" value="fiche">
        <button class="bouton <?= $c['suspendu'] ? 'fant' : 'danger' ?>" type="submit">
          <?= $c['suspendu'] ? 'Réactiver ce compte' : 'Suspendre ce compte' ?>
        </button>
        <span class="aide" style="margin-left:10px">Suspendre coupe immédiatement les sessions ouvertes.</span>
      </form>
    <?php endif; ?>

    <form method="post" action="<?= e(url('?p=note-compte')) ?>">
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
      <input type="hidden" name="id" value="<?= e($c['id']) ?>">
      <div class="champ">
        <label for="f-note">Note interne <span style="font-weight:400">(invisible pour l’organisateur)</span></label>
        <textarea id="f-note" name="note" rows="3"
                  placeholder="Payé jusqu’en décembre. Article sponsorisé publié le 12/09."><?= e((string) ($c['note_equipe'] ?? '')) ?></textarea>
      </div>
      <button class="bouton fant petit" type="submit">Enregistrer la note</button>
    </form>
  </div>

  <!-- ---------- ses campagnes ---------- -->
  <div class="carte" style="margin-bottom:16px">
    <h3 style="margin:0 0 10px">Ses campagnes</h3>
    <?php if (!$fiche['decors']): ?>
      <p class="aide" style="margin:0">Aucune campagne créée.</p>
    <?php else: foreach ($fiche['decors'] as $d): $p = presence((string) $d['id']); ?>
      <div class="rangee" style="justify-content:space-between;border-top:1px solid var(--border);padding:10px 0;gap:12px">
        <div>
          <b><?= e($d['titre']) ?></b>
          <span class="pastille <?= e($d['statut']) ?>" style="margin-left:8px"><?= e(statut_libelle($d['statut'])) ?></span>
          <p class="aide" style="margin:3px 0 0"><?= e($d['ville']) ?> ·
          créée le <?= e(gmdate('d/m/Y', strtotime((string) $d['cree_le']))) ?></p>
        </div>
        <span class="aide" style="white-space:nowrap">↓ <?= (int) $d['telechargements'] ?>
          · 👁 <?= (int) $d['vues'] ?> · ✓ <?= $p['scannes'] ?></span>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <!-- ---------- ses liens ---------- -->
  <?php if ($fiche['liens']): ?>
    <div class="carte">
      <div class="rangee" style="justify-content:space-between;align-items:baseline">
        <h3 style="margin:0">Ses liens courts</h3>
        <span class="aide"><?= (int) $t['clics'] ?> clics au total</span>
      </div>
      <?php foreach ($fiche['liens'] as $l): ?>
        <div class="rangee" style="justify-content:space-between;border-top:1px solid var(--border);padding:9px 0;gap:12px">
          <span class="mono" style="overflow-wrap:anywhere"><?= e((string) $l['code']) ?> → <?= e((string) $l['cible']) ?></span>
          <span class="aide" style="white-space:nowrap"><?= (int) $l['clics'] ?> clics</span>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>
