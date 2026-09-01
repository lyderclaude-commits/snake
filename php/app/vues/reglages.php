<?php
/** Le transport e-mail : le régler, et surtout l'essayer. */
$erreur = $erreur ?? null;
$message = $message ?? null;
$branche = courriel_branche();
?>
<div class="contenu">
  <section class="entete">
    <h1>Réglages</h1>
    <p>Le transport e-mail. Sans lui, un partenaire ne sait qu’en revenant sur le site
    que son décor a été relu — et une adresse ne peut pas être confirmée.</p>
  </section>

  <?php if ($message): ?><div class="msg ok" role="status"><?= e($message) ?></div><?php endif; ?>
  <?php if ($erreur): ?><div class="msg err" role="alert"><?= e($erreur) ?></div><?php endif; ?>

  <div class="msg <?= $branche ? 'ok' : '' ?>" style="margin-bottom:16px">
    <strong><?= $branche ? 'Transport branché' : 'Transport éteint' ?></strong>
    <p style="margin:.35em 0 0">
      <?php if ($branche): ?>
        Les décisions de modération, les demandes de correction et les liens de confirmation
        partent par <?= e($valeurs['smtp_hote']) ?>.
      <?php else: ?>
        Rien ne quitte le serveur. Les notifications restent dans l’application, et le lien de
        confirmation d’adresse s’affiche à l’écran au lieu d’être envoyé.
      <?php endif; ?>
    </p>
  </div>

  <form method="post" action="<?= e(url('?p=reglages')) ?>">
    <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">

    <div class="carte">
      <h3 style="margin:0 0 4px">Le serveur d’envoi</h3>
      <p class="aide" style="margin:0 0 16px">Chez LWS, ces valeurs figurent dans l’espace client,
      rubrique « comptes e-mail ». Le serveur ressemble à <code>mail.votredomaine.tld</code>.</p>

      <div class="grille g2">
        <div class="champ">
          <label for="smtp_hote">Serveur SMTP</label>
          <input id="smtp_hote" name="smtp_hote" type="text" autocomplete="off"
                 placeholder="mail.wakabileguide.com" value="<?= e($valeurs['smtp_hote']) ?>">
          <p class="aide">Laissez vide pour couper le transport.</p>
        </div>
        <div class="champ">
          <label for="smtp_port">Port</label>
          <input id="smtp_port" name="smtp_port" type="number" min="1" max="65535"
                 value="<?= e($valeurs['smtp_port']) ?>">
        </div>
        <div class="champ">
          <label for="smtp_securite">Chiffrement</label>
          <select id="smtp_securite" name="smtp_securite">
            <?php foreach (COURRIEL_SECURITES as $k => $lib): ?>
              <option value="<?= e($k) ?>" <?= $valeurs['smtp_securite'] === $k ? 'selected' : '' ?>><?= e($lib) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="aide">587 avec STARTTLS dans la quasi-totalité des cas.</p>
        </div>
        <div class="champ">
          <label for="smtp_utilisateur">Identifiant</label>
          <input id="smtp_utilisateur" name="smtp_utilisateur" type="text" autocomplete="off"
                 placeholder="boost@wakabileguide.com" value="<?= e($valeurs['smtp_utilisateur']) ?>">
          <p class="aide">Le plus souvent l’adresse e-mail elle-même.</p>
        </div>
        <div class="champ">
          <label for="smtp_motdepasse">Mot de passe</label>
          <input id="smtp_motdepasse" name="smtp_motdepasse" type="password" autocomplete="new-password"
                 placeholder="<?= $a_mot_de_passe ? '••••••••  (inchangé)' : 'aucun enregistré' ?>">
          <p class="aide">
            <?php if ($a_mot_de_passe): ?>
              Un mot de passe est enregistré. Laissez vide pour le garder tel quel,
              ou <label style="display:inline"><input type="checkbox" name="effacer_mdp" value="1"> effacez-le</label>.
            <?php else: ?>
              Jamais réaffiché : une page qui montre un mot de passe le donne à tout ce qui la lit.
            <?php endif; ?>
          </p>
        </div>
      </div>
    </div>

    <div class="carte" style="margin-top:16px">
      <h3 style="margin:0 0 4px">L’expéditeur</h3>
      <p class="aide" style="margin:0 0 16px">L’adresse doit appartenir au domaine du serveur d’envoi.
      Une adresse d’un autre domaine — gmail, yahoo — part en indésirables ou se fait rejeter.</p>

      <div class="grille g2">
        <div class="champ">
          <label for="courriel_expediteur">Adresse expéditrice</label>
          <input id="courriel_expediteur" name="courriel_expediteur" type="email"
                 placeholder="boost@wakabileguide.com" value="<?= e($valeurs['courriel_expediteur']) ?>">
        </div>
        <div class="champ">
          <label for="courriel_nom">Nom affiché</label>
          <input id="courriel_nom" name="courriel_nom" type="text" value="<?= e($valeurs['courriel_nom']) ?>">
        </div>
        <div class="champ" style="grid-column:1/-1">
          <label for="courriel_repondre_a">Adresse de réponse <span style="font-weight:400">(facultatif)</span></label>
          <input id="courriel_repondre_a" name="courriel_repondre_a" type="email"
                 placeholder="contact@wakabileguide.com" value="<?= e($valeurs['courriel_repondre_a']) ?>">
          <p class="aide">Là où arrive une réponse, si quelqu’un répond quand même.</p>
        </div>
      </div>
    </div>

    <div class="carte" style="margin-top:16px">
      <h3 style="margin:0 0 4px">L’essai</h3>
      <p class="aide" style="margin:0 0 16px">Un réglage qu’on n’a pas essayé n’est pas un réglage.
      L’essai enregistre d’abord, puis envoie avec ce qui vient d’être enregistré.</p>
      <div class="champ">
        <label for="essai_vers">Envoyer un message d’essai à</label>
        <input id="essai_vers" name="essai_vers" type="email" value="<?= e($essai_vers) ?>">
      </div>
      <div class="rangee" style="margin-top:14px">
        <button class="bouton" type="submit" name="action" value="enregistrer">Enregistrer</button>
        <button class="bouton fant" type="submit" name="action" value="essai">Enregistrer et envoyer l’essai</button>
      </div>
    </div>
  </form>

  <div class="carte" style="margin-top:16px">
    <h3 style="margin:0 0 4px">Les liens courts</h3>
    <p class="aide" style="margin:0 0 16px">Aujourd’hui, vos liens s’écrivent
    <code><?= e($exemple_lien) ?></code>.</p>

    <form method="post" action="<?= e(url('?p=reglages')) ?>">
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
      <?php
      /* Les champs du transport voyagent avec, sinon les enregistrer ici les
         écraserait par des valeurs vides. */
      foreach (array_keys(COURRIEL_DEFAUTS) as $cle):
          if ($cle === 'smtp_motdepasse') { continue; } ?>
        <input type="hidden" name="<?= e($cle) ?>" value="<?= e((string) $valeurs[$cle]) ?>">
      <?php endforeach; ?>

      <div class="champ">
        <label for="domaine_liens">Domaine dédié <span style="font-weight:400">(facultatif)</span></label>
        <input id="domaine_liens" name="domaine_liens" type="text" placeholder="wkb.link"
               value="<?= e($domaine_liens) ?>">
        <p class="aide">Laissez vide pour utiliser le domaine du site.</p>
      </div>

      <div class="rangee" style="margin-top:12px;gap:10px">
        <button class="bouton" type="submit" name="action" value="enregistrer">Enregistrer</button>
        <button class="bouton fant" type="submit" name="action" value="liens">Vérifier la forme courte</button>
      </div>
    </form>

    <p class="aide" style="margin:14px 0 0">
      <strong><?= $chemin_court ? 'La forme courte fonctionne.' : 'La forme courte n’a pas été vérifiée.' ?></strong>
      Le bouton demande à votre site de se répondre à lui-même : c’est la seule façon de savoir si
      votre hébergement lit bien le fichier <code>.htaccess</code>. Tant que la réponse n’est pas
      venue, les liens gardent la forme longue — moins jolie, jamais cassée.
    </p>

    <details style="margin-top:12px">
      <summary class="aide" style="cursor:pointer">Brancher <code>wkb.link</code> — la marche à suivre</summary>
      <div class="aide" style="margin-top:10px;line-height:1.7">
        <p style="margin:0 0 8px"><strong>Le domaine ne s’invente pas côté logiciel :</strong> il faut
        le posséder et le faire pointer ici. Trois gestes, une fois pour toutes.</p>
        <ol style="margin:0;padding-left:1.2em">
          <li>Achetez <code>wkb.link</code> chez un registraire (le TLD <code>.link</code>
          coûte une dizaine d’euros par an).</li>
          <li>Chez LWS, dans cPanel → <strong>Domaines</strong>, ajoutez-le en
          <em>domaine additionnel</em> et faites-le pointer vers <strong>ce même dossier</strong>
          — celui qui contient <code>index.php</code>. Puis chez le registraire, réglez les
          serveurs DNS sur ceux de LWS.</li>
          <li>Revenez ici, saisissez <code>wkb.link</code> ci-dessus, et cliquez
          <em>Vérifier la forme courte</em>.</li>
        </ol>
        <p style="margin:8px 0 0">Les liens déjà créés continuent de fonctionner : c’est le code
        à six caractères qui compte, pas le domaine par lequel on arrive.</p>
      </div>
    </details>
  </div>

  <div class="carte" style="margin-top:16px">
    <h3 style="margin:0 0 4px">Les images</h3>
    <p class="aide" style="margin:0 0 12px">Les vignettes du catalogue sont fabriquées et
    mises en cache toutes seules : rien à faire pour elles. Le bouton ci-dessous s’occupe des
    <strong>fichiers de cadres eux-mêmes</strong> — ceux que le Studio charge en entier, et qui
    ont été téléversés avant que la compression n’existe.</p>

    <form method="post" action="<?= e(url('?p=reglages')) ?>">
      <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
      <?php foreach (array_keys(COURRIEL_DEFAUTS) as $cle):
          if ($cle === 'smtp_motdepasse') { continue; } ?>
        <input type="hidden" name="<?= e($cle) ?>" value="<?= e((string) $valeurs[$cle]) ?>">
      <?php endforeach; ?>
      <button class="bouton fant" type="submit" name="action" value="images">Alléger les cadres déjà en ligne</button>
    </form>

    <p class="aide" style="margin:12px 0 0">Par lots d’une douzaine, parce qu’un hébergement
    mutualisé coupe un script au bout de trente secondes. Relancez jusqu’à ce que le message
    dise que c’est terminé. L’opération ne touche jamais à un fichier qu’elle ne saurait pas
    rendre plus léger : en cas de doute, l’original est gardé tel quel.</p>
  </div>

  <div class="carte" style="margin-top:16px">
    <h3 style="margin:0 0 8px">Ce que le transport change</h3>
    <ul style="margin:0;padding-left:1.1em;line-height:1.7">
      <li>Un partenaire reçoit la décision sur son décor : approuvé, à corriger, refusé — avec le motif.</li>
      <li>L’équipe est prévenue qu’un décor attend sa relecture.</li>
      <li>Une nouvelle inscription reçoit un lien de confirmation, valable <?= VERIF_HEURES ?> heures.</li>
      <li><strong>Tant que le transport est éteint</strong>, la confirmation d’adresse n’est pas exigée :
      on ne bloque personne derrière un message qu’on ne sait pas envoyer.</li>
    </ul>
  </div>
</div>
