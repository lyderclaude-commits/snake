<?php
/**
 * Les deux portes vers un décor.
 *
 * Chaque carte dit ce qu'elle donne ET ce qu'elle retire : sans la seconde
 * moitié, on choisit au hasard puis on cherche pendant dix minutes le
 * bouton qui n'existe pas de ce côté-là.
 */
?>
<div class="contenu" style="max-width:880px">

  <section class="entete" style="text-align:center">
    <h1>Votre décor, d’où part-il ?</h1>
    <p>Les deux mènent au même résultat. Le chemin, lui, n’a rien à voir.</p>
  </section>

  <div class="grille g2">

    <!-- ─────────── A · le fichier ─────────── -->
    <a class="chemin-carte" href="<?= e(url('?p=nouveau&depart=fichier')) ?>">
      <span class="chemin-ico"><?= icone('haut') ?></span>
      <h2>J’ai déjà mon décor</h2>
      <p>Votre graphiste vous l’a rendu, ou vous l’avez fait ailleurs. Vous le déposez,
      on s’occupe du reste.</p>
      <ul>
        <li>Une seule chose à faire : déposer votre PNG ou WebP</li>
        <li>Le format se lit dans le fichier, on ne le demande pas</li>
        <li>Puis les textes : titre, ville, date, destination</li>
      </ul>
      <span class="chemin-coupe">Ni modèle, ni format, ni calques à régler</span>
      <span class="bouton" style="width:100%;justify-content:center">Déposer mon fichier</span>
    </a>

    <!-- ─────────── B · le studio ─────────── -->
    <a class="chemin-carte" href="<?= e(url('?p=nouveau&depart=studio')) ?>">
      <span class="chemin-ico"><?= icone('type') ?></span>
      <h2>Je pars de zéro</h2>
      <p>Vous n’avez pas de fichier. Le Studio compose le décor avec vous, calque
      par calque.</p>
      <ul>
        <li>Modèle, format, palette, police</li>
        <li>Textes et images en calques libres</li>
        <li>Titre, ville, date, destination</li>
      </ul>
      <span class="chemin-coupe">Pas de téléversement de cadre</span>
      <span class="bouton" style="width:100%;justify-content:center">Ouvrir le Studio</span>
    </a>
  </div>

  <?php
  /**
   * Ce que les deux chemins partagent, dit une fois.
   *
   * Sans cette phrase, « pas de téléversement de cadre » se lit comme
   * « pas de cadre du tout », et le second chemin passerait pour le
   * parent pauvre alors qu'il porte tout le Studio.
   */
  ?>
  <div class="carte" style="margin-top:20px">
    <h3 style="margin:0 0 6px">Des deux côtés</h3>
    <p class="aide" style="margin:0">
      Le QR Code est facultatif des deux côtés : un décor qui ne contrôle aucune
      entrée n’en a pas besoin. Les cadres fournis par Wakabi (« J’y serai »,
      « Bon plan », les trois formats réseaux) vivent en revanche dans le Studio
      seul : ce sont des points de départ, et qui arrive avec son fichier en a
      déjà un.
      <?php if (!$moi): ?>
        Vous composez sans compte ; il ne vous en faudra un qu’au moment de publier.
      <?php endif; ?>
    </p>
  </div>

  <p class="aide" style="text-align:center;margin-top:16px">
    Vous vouliez plutôt <a href="<?= e(url('?p=decors')) ?>">faire votre badge</a>
    sur un décor existant ?
  </p>
</div>
