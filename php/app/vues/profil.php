<?php
/** Mon profil : mon identité, mon mot de passe, mon départ. */
$erreur = $erreur ?? null;
$message = $message ?? null;
$verifie = email_verifie($me);
?>
<div class="contenu">
  <section class="entete">
    <h1>Mon profil</h1>
    <p><?= e(role_libelle($me['role'])) ?><?php if ($me['role'] === 'partenaire'): ?>
      · offre <?= e(formule_libelle($me['formule'] ?? null)) ?><?php endif; ?>
      · compte créé le <?= e(gmdate('d/m/Y', strtotime((string) $me['cree_le']))) ?></p>
  </section>

  <?php if ($message): ?><div class="msg ok" role="status"><?= e($message) ?></div><?php endif; ?>
  <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

  <?php if (verification_exigee() && !$verifie): ?>
    <div class="msg err" style="margin-bottom:16px">
      <strong>Votre adresse n’est pas confirmée</strong>
      <p style="margin:.35em 0 .7em">Un lien vous attend à <?= e($me['email']) ?>.</p>
      <form method="post" action="<?= e(url('?p=renvoyer-verification')) ?>" style="margin:0">
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <button class="bouton petit" type="submit">M’envoyer un nouveau lien</button>
      </form>
    </div>
  <?php endif; ?>

  <!-- ---------- identité ---------- -->
  <div class="carte">
    <h3 style="margin:0 0 4px">Mes informations</h3>
    <p class="aide" style="margin:0 0 16px">Elles servent à vous joindre, et à signer vos
    campagnes. Rien de tout cela n’est public.</p>

    <form method="post" action="<?= e(url('?p=profil-identite')) ?>">
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
      <div class="grille g2">
        <div class="champ">
          <label for="p-nom">Nom et prénom</label>
          <input id="p-nom" name="nom" type="text" required maxlength="120" value="<?= e($me['nom']) ?>">
        </div>
        <div class="champ">
          <label for="p-email">Adresse e-mail</label>
          <input id="p-email" name="email" type="email" required value="<?= e($me['email']) ?>">
          <p class="aide">
            <?php if ($verifie): ?>
              Confirmée. En changer redemandera une confirmation.
            <?php else: ?>
              Non confirmée pour l’instant.
            <?php endif; ?>
          </p>
        </div>
        <div class="champ">
          <label for="p-organisation">Structure <span style="font-weight:400">(facultatif)</span></label>
          <input id="p-organisation" name="organisation" type="text" maxlength="120"
                 value="<?= e((string) ($me['organisation'] ?? '')) ?>">
        </div>
        <div class="champ">
          <label for="p-ville">Ville</label>
          <select id="p-ville" name="ville">
            <option value="">Non renseignée</option>
            <?php foreach (['lome' => 'Lomé', 'cotonou' => 'Cotonou', 'abidjan' => 'Abidjan'] as $k => $v): ?>
              <option value="<?= e($k) ?>" <?= ($me['ville'] ?? '') === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="champ" style="grid-column:1/-1">
          <label for="p-telephone">Téléphone <span style="font-weight:400">(facultatif)</span></label>
          <input id="p-telephone" name="telephone" type="tel" maxlength="25" placeholder="+228 90 00 00 00"
                 value="<?= e((string) ($me['telephone'] ?? '')) ?>">
          <p class="aide">Pour qu’on puisse vous joindre vite le jour d’un événement.</p>
        </div>
      </div>
      <button class="bouton" type="submit" style="margin-top:14px">Enregistrer</button>
    </form>
  </div>

  <!-- ---------- mot de passe ---------- -->
  <?php
  /**
   * L'abonnement, vu du côté du client.
   *
   * Il voit sa date de fin et ses factures sans avoir à écrire à
   * personne : la question « jusqu'à quand ai-je payé ? » n'a aucune
   * raison de coûter un aller-retour.
   */
  $mes_factures = factures_de((string) $me['id']);
  ?>
  <?php if (abonnement_suivi($me) || $mes_factures): ?>
    <div class="carte" style="margin-top:16px">
      <h3 style="margin:0 0 4px">Mon abonnement</h3>
      <?php $reste = jours_restants($me); ?>
      <p class="aide" style="margin:0 0 12px">
        Offre <strong><?= e(formule_libelle($me['formule'] ?? null)) ?></strong>.
        <?php if ($reste === null): ?>
          Sans date de fin pour l’instant.
        <?php elseif ($reste < 0): ?>
          Échue le <?= e(date_fr((string) $me['echeance_le'])) ?> —
          écrivez-nous pour la reprendre, rien n’est perdu.
        <?php else: ?>
          Elle court jusqu’au <strong><?= e(date_fr((string) $me['echeance_le'])) ?></strong>,
          soit <?= (int) $reste ?> jour<?= $reste > 1 ? 's' : '' ?>.
        <?php endif; ?>
      </p>

      <?php if ($mes_factures): ?>
        <ul class="liste-comptes">
          <?php foreach ($mes_factures as $f): ?>
            <li>
              <span><b><?= e((string) $f['numero']) ?></b>
                <span class="aide"><?= e(date_fr((string) $f['debut_le'])) ?>
                → <?= e(date_fr((string) $f['fin_le'])) ?> ·
                <?= number_format((int) $f['montant'], 0, ',', ' ') ?> F</span></span>
              <a class="bouton fant petit" href="<?= e(url('?p=facture&id=' . rawurlencode((string) $f['id']))) ?>">Voir</a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php
  /**
   * La double authentification — pour les comptes de la maison.
   *
   * Le secret s'affiche en clair À CÔTÉ du QR : toutes les caméras ne
   * lisent pas un code sur un écran, et se retrouver à recopier une
   * chaîne qu'on ne peut pas voir est une impasse silencieuse.
   */
  ?>
  <?php if (otp_proposable($me)): ?>
    <div class="carte" style="margin-top:16px" id="otp">
      <div class="rangee" style="justify-content:space-between;align-items:baseline">
        <h3 style="margin:0">Double authentification</h3>
        <span class="pastille <?= otp_actif($me) ? 'publie' : 'brouillon' ?>">
          <?= otp_actif($me) ? 'En service' : 'Inactive' ?></span>
      </div>

      <?php if (otp_actif($me)): ?>
        <p class="aide" style="margin:8px 0 12px">Un code à six chiffres vous est demandé à
        chaque connexion, en plus du mot de passe. Un mot de passe qui fuit ailleurs ne suffit
        plus à entrer ici.</p>
        <form method="post" action="<?= e(url('?p=profil-otp')) ?>" class="rangee" style="gap:10px;flex-wrap:wrap;align-items:flex-end">
          <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
          <input type="hidden" name="quoi" value="retirer">
          <div class="champ" style="margin:0">
            <label for="otp-retrait">Code actuel, pour la retirer</label>
            <input id="otp-retrait" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}"
                   maxlength="6" required style="width:130px;font-family:ui-monospace,monospace">
          </div>
          <button class="bouton danger" type="submit">Retirer</button>
        </form>

      <?php elseif (($me['otp_secret'] ?? '') !== '' && !empty($_GET['otp'])): ?>
        <p class="aide" style="margin:8px 0 12px">Scannez ce code avec votre application
        d’authentification — Google Authenticator, Aegis, FreeOTP, celle de votre gestionnaire
        de mots de passe. Puis recopiez le code affiché pour confirmer.</p>
        <div class="rangee" style="gap:18px;flex-wrap:wrap;align-items:flex-start">
          <img src="<?= e(Qr::dataUri(otp_uri($me, (string) $me['otp_secret']), 220)) ?>"
               alt="Code à scanner" width="220" height="220"
               style="border-radius:var(--r10);background:#fff;padding:8px">
          <div style="flex:1;min-width:220px">
            <p class="aide" style="margin:0 0 6px">Si la caméra ne veut pas, saisissez cette
            clé à la main :</p>
            <pre class="bloc-code" style="user-select:all;white-space:pre-wrap"><?= e(implode(' ', str_split((string) $me['otp_secret'], 4))) ?></pre>
            <form method="post" action="<?= e(url('?p=profil-otp')) ?>" style="margin-top:14px">
              <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
              <input type="hidden" name="quoi" value="confirmer">
              <div class="champ">
                <label for="otp-code">Le code affiché maintenant</label>
                <input id="otp-code" name="code" type="text" inputmode="numeric" pattern="[0-9]{6}"
                       maxlength="6" required autocomplete="one-time-code"
                       style="width:140px;font-family:ui-monospace,monospace;font-size:1.2rem;letter-spacing:.2em">
              </div>
              <button class="bouton" type="submit">Activer</button>
            </form>
          </div>
        </div>

      <?php else: ?>
        <?php
        /* Ce que le compte ouvre VRAIMENT, et non ce qu'ouvre un compte
           d'équipe : promettre « le catalogue entier et les réglages » à un
           éditeur qui n'a ni l'un ni l'autre décrédibilise le reste de la
           phrase — et c'est celle qui doit convaincre. */
        $ouvre = array_values(array_filter([
            droit($me, 'reglages') ? 'les réglages de l’installation' : null,
            droit($me, 'comptes') ? 'les comptes clients' : null,
            droit($me, 'decors_tous') ? 'le catalogue entier' : null,
            droit($me, 'valider') ? 'la publication de ce que les autres proposent' : null,
            droit($me, 'articles') ? 'la rédaction du blog' : null,
        ]));
        ?>
        <p class="aide" style="margin:8px 0 12px">Votre compte ouvre
        <?= e($ouvre ? implode(', ', array_slice($ouvre, 0, -1))
              . (count($ouvre) > 1 ? ' et ' : '') . end($ouvre) : 'des écrans internes') ?>.
        Un second facteur rend inutile un mot de passe qui fuirait ailleurs — il coûte six
        chiffres à la connexion.</p>
        <form method="post" action="<?= e(url('?p=profil-otp')) ?>">
          <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
          <input type="hidden" name="quoi" value="preparer">
          <button class="bouton" type="submit">Mettre en place</button>
        </form>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- ---------- emporter ses données ---------- -->
  <div class="carte" style="margin-top:16px">
    <h3 style="margin:0 0 4px">Emporter mes données</h3>
    <p class="aide" style="margin:0 0 12px">Un fichier JSON avec votre compte, vos campagnes,
    vos badges, vos liens, vos Koris, vos articles et vos factures. Il se lit dans n’importe
    quel tableur ou éditeur de texte. La suppression existait déjà ; il manquait de pouvoir
    <strong>partir avec</strong> plutôt que seulement partir.</p>
    <a class="bouton fant" href="<?= e(url('?p=profil-export')) ?>">Télécharger mes données</a>
  </div>

  <div class="carte" style="margin-top:16px">
    <h3 style="margin:0 0 4px">Mon mot de passe</h3>
    <p class="aide" style="margin:0 0 16px">L’actuel est demandé : un poste laissé ouvert
    ne doit pas suffire à s’approprier un compte.</p>

    <form method="post" action="<?= e(url('?p=profil-motdepasse')) ?>">
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
      <div class="grille g2">
        <div class="champ">
          <label for="p-actuel">Mot de passe actuel</label>
          <input id="p-actuel" name="actuel" type="password" required autocomplete="current-password">
        </div>
        <div class="champ">
          <label for="p-nouveau">Nouveau mot de passe</label>
          <input id="p-nouveau" name="nouveau" type="password" required minlength="8" autocomplete="new-password">
          <p class="aide">Huit caractères au minimum.</p>
        </div>
      </div>
      <button class="bouton fant" type="submit" style="margin-top:14px">Changer le mot de passe</button>
    </form>
  </div>

  <!-- ---------- notifications ---------- -->
  <?php
  /**
   * Proposé à qui a quelque chose à recevoir, et à personne d'autre.
   *
   * La décision est prise ICI et non dans le partiel : celui-ci sert aussi
   * sur la page d'un décor, où le visiteur vient de faire son badge et
   * n'est souvent même pas connecté — c'est là qu'on accepte le plus, et
   * il ne faut surtout pas l'y faire taire.
   */
  if (push_proposable($me)) {
      require RACINE . '/app/vues/partiels/push-abonnement.php';
  }
  ?>

  <!-- ---------- partir ---------- -->
  <?php if (!$interne): ?>
    <details class="carte" style="margin-top:16px">
      <summary style="cursor:pointer;font-weight:700">Supprimer mon compte</summary>
      <div style="margin-top:14px">
        <p class="aide" style="margin:0 0 4px"><strong>Ce qui s’en va :</strong> votre compte,
        vos liens courts, vos notifications et vos Koris.</p>
        <p class="aide" style="margin:0 0 14px"><strong>Ce qui reste :</strong>
        <?php if ($me['role'] === 'partenaire' && $campagnes > 0): ?>
          vos <?= (int) $campagnes ?> campagne(s) publiée(s) et les présences déjà scannées.
          Les badges que vos invités ont téléchargés portent leur QR : les effacer les casserait
          tous. Vos campagnes restent au catalogue, sans propriétaire, et l’équipe décide de la suite.
        <?php else: ?>
          les présences déjà scannées, qui sont l’historique d’événements réels.
        <?php endif; ?>
        </p>

        <form method="post" action="<?= e(url('?p=profil-supprimer')) ?>">
          <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
          <div class="champ">
            <label for="p-confirmation">Recopiez <code><?= e($me['email']) ?></code> pour confirmer</label>
            <input id="p-confirmation" name="confirmation" type="text" required autocomplete="off">
          </div>
          <button class="bouton danger" type="submit">Supprimer définitivement</button>
        </form>
      </div>
    </details>
  <?php endif; ?>
</div>
