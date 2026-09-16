<?php
/**
 * Le sponsor, à l'écran.
 *
 * À gauche ce qu'on saisit, à droite ce que ça produit. Les nombres de
 * droite sont ceux du document : les voir avant de l'exporter évite de
 * découvrir un rapport à zéro devant son sponsor.
 */
$nb = static fn(int $n): string => number_format($n, 0, ',', ' ');
?>
<div class="contenu">

  <section class="entete">
    <div class="rangee" style="justify-content:space-between;align-items:flex-start;gap:14px;flex-wrap:wrap">
      <div>
        <h1>Le sponsor de ce décor</h1>
        <p><strong><?= e((string) $decor['titre']) ?></strong> ·
        ce que vous revendez, c’est une audience, pas un logo</p>
      </div>
      <div class="rangee" style="gap:8px">
        <?php if ($sponsor): ?>
          <a class="bouton"
             href="<?= e(url('?p=sponsor&decor=' . rawurlencode((string) $decor['slug'])
                  . '&export=pdf')) ?>">Rapport d’exposition (PDF)</a>
        <?php endif; ?>
        <a class="bouton fant"
           href="<?= e(url('?p=rapports&decor=' . rawurlencode((string) $decor['slug']))) ?>">Retour au rapport</a>
      </div>
    </div>
  </section>

  <?php if ($erreur): ?><div class="msg err"><?= e($erreur) ?></div><?php endif; ?>
  <?php if ($message): ?><div class="msg ok"><?= e($message) ?></div><?php endif; ?>

  <div class="grille g2" style="align-items:start">

    <!-- ------------------- la saisie ------------------- -->
    <section class="carte">
      <h2>Nom, logo, lien</h2>
      <p class="aide" style="margin:4px 0 16px">
        Trois champs. Effacer le nom retire le sponsor de ce décor.
      </p>

      <form method="post" enctype="multipart/form-data"
            action="<?= e(url('?p=sponsor&decor=' . rawurlencode((string) $decor['slug']))) ?>">
        <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
        <input type="hidden" name="quoi" value="enregistrer">
        <input type="hidden" name="sponsor_logo" value="<?= e((string) ($decor['sponsor_logo'] ?? '')) ?>">

        <div class="champ">
          <label for="s-nom">Nom du sponsor</label>
          <input id="s-nom" name="sponsor_nom" type="text" maxlength="120"
                 value="<?= e((string) ($decor['sponsor_nom'] ?? '')) ?>"
                 placeholder="Brasserie du Golfe">
        </div>

        <div class="champ">
          <label for="s-lien">Son lien</label>
          <input id="s-lien" name="sponsor_lien" type="url" maxlength="400"
                 value="<?= e((string) ($decor['sponsor_lien'] ?? '')) ?>"
                 placeholder="https://brasseriedugolfe.tg">
          <p class="aide">Il devient un lien court, donc il se compte : c’est le seul chiffre
          du rapport qui prouve un geste, et pas seulement une présence à l’écran.</p>
        </div>

        <div class="champ">
          <label for="s-logo">Son logo</label>
          <input id="s-logo" name="logo" type="file" accept="image/png,image/webp,image/jpeg">
          <p class="aide">PNG, WebP ou JPEG, 2 Mo au plus. Le SVG est refusé.</p>
          <?php if (($decor['sponsor_logo'] ?? '') !== ''): ?>
            <p style="margin-top:8px">
              <img src="<?= e((string) $decor['sponsor_logo']) ?>" alt="Logo du sponsor"
                   style="max-height:56px;max-width:190px;border-radius:8px">
            </p>
          <?php endif; ?>
        </div>

        <button class="bouton" type="submit">Enregistrer</button>
      </form>

      <?php if ($sponsor && $sponsor['statut'] === 'a_relire'): ?>
        <div class="msg info" style="margin-top:16px">
          <strong>Ce lien attend la relecture.</strong>
          Il mène hors de wakabileguide.com, et c’est notre nom qui sert de caution :
          le nom et le logo s’affichent déjà sur la page du décor, le lien devient
          cliquable une fois relu.
          <?php if ($equipe): ?>
            <form method="post" style="margin-top:10px"
                  action="<?= e(url('?p=sponsor&decor=' . rawurlencode((string) $decor['slug']))) ?>">
              <input type="hidden" name="csrf" value="<?= e(jeton_csrf()) ?>">
              <input type="hidden" name="quoi" value="valider">
              <button class="bouton petit" type="submit">Approuver ce lien</button>
            </form>
          <?php endif; ?>
        </div>
      <?php elseif ($sponsor && $sponsor['url'] !== ''): ?>
        <p class="aide" style="margin-top:14px">
          Lien en service : <code><?= e((string) $sponsor['url']) ?></code>
        </p>
      <?php endif; ?>
    </section>

    <!-- ------------------- ce que le document dira ------------------- -->
    <section class="carte">
      <h2>Ce que le document dira</h2>
      <p class="aide" style="margin:4px 0 14px">
        Depuis le début de ce décor, sans borne de période : un sponsor achète
        un événement, pas un mois.
      </p>

      <div class="grille g2" style="gap:10px">
        <div class="stat p"><b><?= e($nb((int) $chiffres['vues'])) ?></b>
          <span>Vues de la page</span></div>
        <div class="stat v"><b><?= e($nb((int) $chiffres['badges'])) ?></b>
          <span>Badges créés</span></div>
        <div class="stat"><b><?= $sponsor && $sponsor['url'] !== ''
            ? e($nb((int) $chiffres['clics'])) : '<span class="rap-vide">sans lien</span>' ?></b>
          <span>Clics sur le lien</span></div>
        <div class="stat v"><b><?= e($nb((int) $chiffres['presences'])) ?></b>
          <span>Personnes à la porte</span></div>
      </div>

      <div style="margin-top:16px">
        <?php foreach ($chiffres['entonnoir'] as $pas): ?>
          <div class="marche">
            <div class="haut">
              <span><?= e((string) $pas['nom']) ?></span>
              <b><?= e($nb((int) $pas['n'])) ?></b>
            </div>
            <div class="rail"><i style="width:<?= round((float) $pas['part'] * 100, 2) ?>%"></i></div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="msg info" style="margin-top:16px">
        <strong>Occasions de voir, pas regards.</strong>
        Une page ouverte porte le nom du sponsor ; elle ne prouve pas qu’on l’a
        regardé. Le document le dit lui-même, et c’est cette phrase qui rend le
        reste crédible le jour où le sponsor pose la question.
      </div>

      <p class="aide" style="margin-top:12px">
        Le bloc du sponsor est sur la <strong>page du décor</strong>, pas sur l’image
        du badge : le document ne prétend donc pas que 486 badges portent son logo.
      </p>
    </section>
  </div>

</div>
