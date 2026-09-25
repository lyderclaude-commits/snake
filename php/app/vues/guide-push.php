<?php
/**
 * Push — page de présentation, côté vitrine.
 *
 * Elle existe parce que le menu public mène ici. Envoyer un visiteur
 * directement sur `?p=canaux` l'aurait jeté contre un mur de connexion,
 * au moment précis où il cherchait à comprendre ce qu'on vend — c'est ce
 * que le produit a passé plusieurs versions à défaire ailleurs.
 *
 * Le prix n'est pas écrit ici : il se lit dans les offres.
 */
?>
<div class="page-header">
  <div class="container"><div class="page-header-inner">
    <div class="breadcrumb">
      <a href="<?= e(url('?p=accueil')) ?>">Accueil</a><span class="breadcrumb-sep">›</span>
      <span>Boost · Push</span>
    </div>
    <h1>Parler à ceux qui sont déjà venus</h1>
    <p>WhatsApp, Telegram et les notifications du navigateur, depuis un seul écran.
    Vos invités reçoivent le message là où ils lisent vraiment.</p>
  </div></div>
</div>

<section class="section">
  <div class="container">

    <?php
    /* `telegram_push` est la ligne qui OUVRE l'écran des canaux ; les
       messages WhatsApp et Telegram se comptent ensuite par des quotas
       à part (`whatsapp_par_mois`, `telegram_par_mois`). C'est la
       capacité qu'on annonce, parce que c'est elle qui donne la clé. */
    $ligne = 'telegram_push';
    $quoi = 'l’envoi de messages';
    $pluriel = false;
    require __DIR__ . '/partiels/offre-ligne.php';
    ?>

    <div style="text-align:center;margin-bottom:46px" class="fade-up">
      <div class="tag">Trois canaux, un seul écran</div>
      <h2 class="headline">Le message part <span>là où on le lit</span></h2>
      <p style="color:var(--text2);max-width:62ch;margin:14px auto 0;line-height:1.8">
        Un e-mail se lit une fois sur trois. Un message WhatsApp se lit presque toujours,
        et tout de suite. C'est pour ça que les trois canaux vivent au même endroit :
        vous écrivez une fois, vous choisissez par où ça part.
      </p>
    </div>

    <div class="why-grid fade-up">
      <div class="why-card">
        <div class="why-icon"><?= icone_guide('push-notifications', 32) ?></div>
        <div class="why-title">WhatsApp</div>
        <div class="why-desc">Le canal que tout le monde ouvre. Vos messages partent par
        l’API officielle, avec vos gabarits validés, et pas depuis un téléphone qui finit
        par se faire bloquer.</div>
      </div>
      <div class="why-card">
        <div class="why-icon"><?= icone_guide('assistance', 32) ?></div>
        <div class="why-title">Telegram</div>
        <div class="why-desc">Pour les groupes et les canaux d’annonce. Un message,
        un lien court traçable, et vous savez combien de personnes l’ont réellement suivi.</div>
      </div>
      <div class="why-card">
        <div class="why-icon"><?= icone_guide('compass', 32) ?></div>
        <div class="why-title">Notifications navigateur</div>
        <div class="why-desc">La personne accepte une fois, depuis votre page de décor, et
        vous la joignez ensuite sans connaître ni son numéro ni son adresse.</div>
      </div>
    </div>

    <div class="fade-up" style="margin-top:52px">
      <h2 class="headline" style="text-align:center;margin-bottom:26px">Ce qui est compris</h2>
      <div class="pf-list" style="max-width:640px;margin:0 auto">
        <div class="pf-item"><span class="pf-check">✓</span>Écrire une fois, envoyer sur les trois canaux</div>
        <div class="pf-item"><span class="pf-check">✓</span>Choisir qui reçoit : ville, rubrique, « est déjà venu »</div>
        <div class="pf-item"><span class="pf-check">✓</span>Des rappels qui partent seuls : J−7, la veille, deux heures avant</div>
        <div class="pf-item"><span class="pf-check">✓</span>Le compte de ce qui est parti, reçu, ouvert et suivi</div>
        <div class="pf-item"><span class="pf-check">✓</span>Les échecs listés, et relançables en un geste</div>
      </div>
    </div>

    <div class="fade-up" style="text-align:center;margin-top:52px">
      <a class="btn btn-primary btn-lg" href="<?= e(url('?p=inscription')) ?>">Créer mon compte</a>
      <p style="color:var(--text2);margin-top:14px;font-size:14px">
        Déjà un compte ? <a href="<?= e(url('?p=canaux')) ?>">Ouvrir mes canaux</a>
      </p>
    </div>

  </div>
</section>
