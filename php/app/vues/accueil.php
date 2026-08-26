<div class="contenu">
  <section class="entete">
    <p style="color:var(--primary);font-weight:700;font-size:.8rem;letter-spacing:.12em;text-transform:uppercase">
      La suite marketing événementielle de Wakabi
    </p>
    <h1>Vos invitations méritent<br>une salle pleine.</h1>
    <p>Badges viraux, QR Code à l’entrée, présence réelle mesurée. Wakabi Boost transforme
    chaque contact en venue — et chaque venue en client fidèle.</p>
    <div class="rangee" style="margin-top:22px">
      <a class="bouton" href="<?= e(url('?p=decors')) ?>">Créer mon badge gratuit</a>
      <a class="bouton fant" href="<?= e(url('?p=inscription')) ?>">Devenir organisateur</a>
    </div>
  </section>

  <section class="grille g3" style="margin-top:10px">
    <div class="carte">
      <h3>1 · Le badge</h3>
      <p style="color:var(--text2);margin:0">Votre invité met sa photo dans votre décor et le
      partage. Sa photo ne quitte jamais son téléphone.</p>
    </div>
    <div class="carte">
      <h3>2 · Le QR</h3>
      <p style="color:var(--text2);margin:0">Chaque badge porte un code unique, incrusté dans
      l’image. Visible dès l’aperçu, pas seulement à l’export.</p>
    </div>
    <div class="carte">
      <h3>3 · La présence</h3>
      <p style="color:var(--text2);margin:0">Scanné à l’entrée, le code prouve une venue réelle
      et crédite des Koris. Un badge ne vaut qu’une entrée.</p>
    </div>
  </section>

  <section class="carte" style="margin-top:26px">
    <h2>Ce qu’un générateur d’images ne mesure pas</h2>
    <p style="color:var(--text2);max-width:62ch">Compter les téléchargements récompense un clic.
    Compter les scans récompense une venue. C’est la seule métrique qu’un organisateur paie
    volontiers — et la seule que la concurrence ne mesure pas.</p>
    <div class="grille g4" style="margin-top:16px">
      <div class="stat p"><b><?= (int) tableau_de_bord()['publies'] ?></b><span>décors publiés</span></div>
      <div class="stat o"><b><?= (int) tableau_de_bord()['badges'] ?></b><span>badges émis</span></div>
      <div class="stat v"><b><?= (int) tableau_de_bord()['presences'] ?></b><span>présences scannées</span></div>
      <div class="stat"><b><?= KORIS_PAR_SCAN ?> ₵</b><span>par entrée validée</span></div>
    </div>
  </section>
</div>
