<?php
/**
 * Contact · Wakabi — page venue de wakabileguide.com.
 *
 * Le balisage est celui du site d'origine, à trois choses près, toutes
 * nécessaires à la fusion :
 *
 *   · la navigation par script (`onclick="navigate(…)"`) est devenue de
 *     vrais liens, qui s'ouvrent dans un onglet, se copient et se
 *     partagent — ce qu'un `onclick` ne sait pas faire ;
 *   · les icônes chargées chez un tiers sont dessinées ici
 *     (`icone_guide()`, `drapeau()`), pour ne pas payer trente-quatre
 *     allers-retours par page sur une connexion 3G ;
 *   · l'adresse du magasin d'applications passe par `APPLICATION_URL`.
 *
 * La mise en page vient de `public/guide.css`, servie à cette page SEULE
 * — jamais en même temps que celle de Boost. Voir `app/vitrine.php`.
 */
?>
<div class="page-header">
    <div class="container"><div class="page-header-inner">
      <div class="breadcrumb"><a href="<?= e(url('?p=accueil')) ?>">Accueil</a><span class="breadcrumb-sep">›</span><span>Contact</span></div>
      <h1>Contactez-nous</h1>
      <p>Notre équipe vous répond sous 24h. Partenariats, support, presse, on gère tout.</p>
    </div></div>
  </div>
  <section class="section">
    <div class="container">
      <div class="contact-grid">
        <div class="fade-up">
          <div class="tag">Restons en contact</div>
          <h3>Une question ? Un projet ? Écrivez-nous.</h3>
          <p style="color:var(--text2);margin:14px 0 28px;line-height:1.8;">Que vous souhaitiez rejoindre la plateforme, discuter d'un partenariat stratégique ou simplement en savoir plus, notre équipe vous répond sous 24h.</p>
          <div class="contact-links">
            <a href="mailto:contact@wakabileguide.com" class="contact-link">contact@wakabileguide.com</a>
            <a href="https://www.facebook.com/wakabileguide" target="_blank" class="contact-link">Facebook · Wakabi le Guide</a>
            <a href="https://www.instagram.com/wakabileguide" target="_blank" class="contact-link">Instagram · @wakabileguide</a>
            <a href="https://www.tiktok.com/@wakabileguide" target="_blank" class="contact-link">TikTok · @wakabileguide</a>
            <a href="https://www.youtube.com/@WakabiLeGuideDesBonsCoins" target="_blank" class="contact-link">YouTube · Wakabi Le Guide</a>
          </div>
        </div>
        <div class="fade-up fade-up-d2">
          <?php
          /**
           * Le formulaire POSTE VERS LE SERVEUR, sans une ligne de script.
           *
           * Il arrivait avec `onsubmit="handleContact(event)"`, fonction
           * qui n'existait nulle part : le bouton levait une erreur et le
           * message n'allait nulle part. Un envoi ordinaire de formulaire
           * marche partout, y compris quand le script n'a pas chargé — ce
           * qui arrive sur la connexion depuis laquelle ces messages
           * partent le plus souvent.
           *
           * Les valeurs saisies reviennent après un refus : réécrire six
           * champs parce qu'une adresse avait une faute de frappe, c'est le
           * moment où l'on renonce à écrire.
           */
          ?>
          <?php if ($envoye): ?>
            <div class="form-success" style="display:block;margin-bottom:18px" role="status">
              ✓ Message envoyé. Notre équipe vous répond sous 24 h.
            </div>
          <?php endif; ?>
          <?php if ($erreur): ?>
            <div class="form-success" style="display:block;margin-bottom:18px;
                 background:#FEF2F2;border-color:#FECACA;color:#991B1B" role="alert">
              <?= e($erreur) ?>
            </div>
          <?php endif; ?>
          <form class="contact-form" method="post" action="<?= e(url('?p=contact')) ?>">
            <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
            <div class="form-row">
              <div class="form-group"><label for="c-nom">Prénom / Nom</label>
                <input type="text" id="c-nom" name="nom" placeholder="Kofi Mensah"
                       value="<?= e($valeurs['nom']) ?>" required maxlength="120"></div>
              <div class="form-group"><label for="c-email">Email</label>
                <input type="email" id="c-email" name="email" placeholder="kofi@exemple.com"
                       value="<?= e($valeurs['email']) ?>" required maxlength="160"
                       inputmode="email" autocapitalize="off"></div>
            </div>
            <div class="form-group">
              <label for="c-objet">Objet</label>
              <select id="c-objet" name="objet" required>
                <option value="" disabled <?= $valeurs['objet'] === '' ? 'selected' : '' ?>>Choisir un objet</option>
                <?php foreach ($objets as $cle => $libelle): ?>
                  <option value="<?= e($cle) ?>" <?= $valeurs['objet'] === $cle ? 'selected' : '' ?>><?= e($libelle) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-row">
              <div class="form-group"><label for="c-ville">Ville</label>
                <input type="text" id="c-ville" name="ville" placeholder="Lomé, Togo"
                       value="<?= e($valeurs['ville']) ?>" maxlength="80"></div>
              <div class="form-group"><label for="c-tel">Téléphone (optionnel)</label>
                <input type="tel" id="c-tel" name="telephone" placeholder="+228 XX XX XX XX"
                       value="<?= e($valeurs['telephone']) ?>" maxlength="40" inputmode="tel"></div>
            </div>
            <div class="form-group"><label for="c-message">Message</label>
              <textarea id="c-message" name="message" placeholder="Décrivez votre projet…"
                        required maxlength="4000"><?= e($valeurs['message']) ?></textarea></div>
            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:14px;">Envoyer le message</button>
          </form>
        </div>
      </div>
    </div>
  </section>
</div>
