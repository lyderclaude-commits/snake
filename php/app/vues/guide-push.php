<?php
/**
 * Push — page de présentation, côté vitrine.
 *
 * Elle existe parce que le menu public mène ici. Envoyer un visiteur
 * directement sur `?p=canaux` l'aurait jeté contre un mur de connexion,
 * au moment précis où il cherchait à comprendre ce qu'on vend — c'est ce
 * que le produit a passé plusieurs versions à défaire ailleurs.
 *
 * Elle répondait à « qu'est-ce que c'est », et s'arrêtait là : trois
 * cartes, cinq lignes de liste, un bouton. Quelqu'un qui hésite se pose
 * trois questions de plus, et il les pose dans cet ordre : comment ça
 * marche, qu'est-ce qu'il me faut pour commencer, et qu'est-ce que ça
 * donne vraiment. Les sections suivent cet ordre-là.
 *
 * Le prix n'est pas écrit ici : il se lit dans `partiels/offre-ligne.php`,
 * qui va le chercher dans les offres. Une page de vitrine qui annoncerait
 * un chiffre en dur mentirait dès la première modification.
 *
 * LES CHIFFRES DES DEUX TABLEAUX DE BORD SONT DES ILLUSTRATIONS, et le
 * disent. Montrer un écran vide ne dirait rien de ce qu'on y lit ; faire
 * passer des chiffres inventés pour une mesure serait pire.
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
    Vous écrivez une fois, vous choisissez qui reçoit, et vous voyez ce qui est arrivé.</p>
    <div class="hero-ctas" style="justify-content:center;margin-top:26px">
      <a class="btn btn-white btn-lg" href="<?= e(url('?p=inscription')) ?>">Créer mon compte</a>
      <a class="btn btn-outline btn-lg btn-sur-bleu" href="<?= e(url('?p=accueil#tarifs')) ?>">Voir les offres</a>
    </div>
    <?php
    /* `.rassure` et non `.trust-item` : le bloc « EDIT » en tête de
       `guide.css` masque ce dernier partout, et la rangée serait
       invisible sans que rien ne l'explique. */
    ?>
    <div class="rassure" style="margin-top:20px">
      <span class="rassure-item">Sans carte bancaire</span>
      <span class="rassure-item">Désabonnement en un clic</span>
      <span class="rassure-item">Vos contacts restent les vôtres</span>
    </div>
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
      <div class="tag">En quatre gestes</div>
      <h2 class="headline">D’une idée de message à <span>ce qui est vraiment arrivé</span></h2>
      <p style="color:var(--text2);max-width:62ch;margin:14px auto 0;line-height:1.8">
        Le même message part sur trois canaux. Vous ne le réécrivez pas trois fois, et vous
        ne comptez pas les retours à trois endroits.
      </p>
    </div>

    <div class="steps-grid steps-quatre fade-up">
      <div class="step-card">
        <div class="step-num">1</div>
        <div class="step-title">Vous écrivez</div>
        <div class="step-desc">Un titre, un texte, un lien. L’aperçu montre ce que recevra
        un téléphone, pas un brouillon d’éditeur.</div>
      </div>
      <div class="step-card">
        <div class="step-num">2</div>
        <div class="step-title">Vous choisissez qui</div>
        <div class="step-desc">La ville, la rubrique, la campagne, la date, et « est
        réellement venu ». Les règles se combinent.</div>
      </div>
      <div class="step-card">
        <div class="step-num">3</div>
        <div class="step-title">Vous choisissez par où</div>
        <div class="step-desc">WhatsApp, Telegram, le navigateur. Une case par canal, et le
        compte des destinataires joignables sur chacun.</div>
      </div>
      <div class="step-card">
        <div class="step-num">4</div>
        <div class="step-title">Vous regardez</div>
        <div class="step-desc">Parti, reçu, ouvert, suivi, échoué. Et les échecs listés,
        avec leur motif, relançables en un geste.</div>
      </div>
    </div>
  </div>
</section>

<section class="section section-grise">
  <div class="container">
    <div style="text-align:center;margin-bottom:40px" class="fade-up">
      <div class="tag">Trois canaux</div>
      <h2 class="headline">Le message part <span>là où on le lit</span></h2>
      <p style="color:var(--text2);max-width:64ch;margin:14px auto 0;line-height:1.8">
        Chacun demande quelque chose de différent pour s’ouvrir. C’est écrit ici plutôt que
        découvert le soir de la campagne.
      </p>
    </div>

    <div class="why-grid fade-up">
      <div class="why-card">
        <div class="why-icon"><?= icone_guide('whatsapp', 32) ?></div>
        <div class="why-title">WhatsApp</div>
        <div class="why-desc">Le canal que tout le monde ouvre. Vos messages partent par
        l’API officielle, avec vos gabarits validés, et pas depuis un téléphone qui finit
        par se faire bloquer.</div>
        <div class="feature-pills" style="margin:16px 0 0">
          <span class="pill"><span class="pill-dot"></span>Numéro vérifié</span>
          <span class="pill"><span class="pill-dot"></span>Gabarits validés</span>
          <span class="pill"><span class="pill-dot"></span>Quota mensuel</span>
        </div>
      </div>
      <div class="why-card">
        <div class="why-icon"><?= icone_guide('telegram', 32) ?></div>
        <div class="why-title">Telegram</div>
        <div class="why-desc">Pour les groupes et les canaux d’annonce. Un message, un lien
        court traçable, et vous savez combien de personnes l’ont réellement suivi.</div>
        <div class="feature-pills" style="margin:16px 0 0">
          <span class="pill"><span class="pill-dot"></span>Jeton de bot</span>
          <span class="pill"><span class="pill-dot"></span>Canal ou groupe</span>
          <span class="pill"><span class="pill-dot"></span>Sans quota</span>
        </div>
      </div>
      <div class="why-card">
        <div class="why-icon"><?= icone_guide('push-notifications', 32) ?></div>
        <div class="why-title">Notifications navigateur</div>
        <div class="why-desc">La personne accepte une fois, depuis votre page de décor, et
        vous la joignez ensuite sans connaître ni son numéro ni son adresse.</div>
        <div class="feature-pills" style="margin:16px 0 0">
          <span class="pill"><span class="pill-dot"></span>Rien à configurer</span>
          <span class="pill"><span class="pill-dot"></span>Anonyme</span>
          <span class="pill"><span class="pill-dot"></span>Compris dans l’offre</span>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="app-showcase fade-up">
      <div>
        <div class="tag">Les rappels</div>
        <h2 class="headline" style="margin:10px 0 16px">Quatre messages que vous
        <span>n’aurez pas à envoyer</span></h2>
        <p style="color:var(--text2);line-height:1.85;margin-bottom:22px">
          Une soirée se remplit rarement d’un seul message. Elle se remplit par rappels, et
          ce sont exactement les messages qu’on oublie d’envoyer parce qu’on est occupé à
          organiser. Vous les réglez une fois, à la création de la campagne.
        </p>

        <div class="kb-item"><span class="kb-icon">J-7</span>
          <div class="kb-text"><strong>« C’est dans une semaine »</strong>
          <span class="kb-sous">Le rappel qui fait réserver une soirée.</span></div></div>
        <div class="kb-item"><span class="kb-icon">J-1</span>
          <div class="kb-text"><strong>« C’est demain »</strong>
          <span class="kb-sous">Celui qui fait venir ceux qui avaient dit oui.</span></div></div>
        <div class="kb-item"><span class="kb-icon">H-2</span>
          <div class="kb-text"><strong>« On ouvre dans deux heures »</strong>
          <span class="kb-sous">Avec le plan, et le lien du badge pour ceux qui ne l’ont pas fait.</span></div></div>
        <div class="kb-item"><span class="kb-icon">J+1</span>
          <div class="kb-text"><strong>« Merci, voici les photos »</strong>
          <span class="kb-sous">Le message le plus rentable : il prépare la campagne suivante.</span></div></div>
      </div>

      <div>
        <div class="hero-float-card carte-posee">
          <div class="hfc-left">
            <div class="hfc-label">Campagne « Soirée blanche »</div>
            <div class="hfc-val">4 rappels armés</div>
            <div class="hfc-sub">le prochain part dans 3 jours</div>
          </div>
          <div class="hfc-right"><span class="badge badge-green">Actif</span></div>
        </div>
        <div class="dash-stats dash-deux">
          <div class="dash-stat"><div class="dash-stat-label">Partis</div>
            <div class="dash-stat-val">1 248</div>
            <div class="dash-stat-change change-up">tous canaux</div></div>
          <div class="dash-stat"><div class="dash-stat-label">Reçus</div>
            <div class="dash-stat-val">1 196</div>
            <div class="dash-stat-change change-up">95,8 %</div></div>
          <div class="dash-stat"><div class="dash-stat-label">Ouverts</div>
            <div class="dash-stat-val">842</div>
            <div class="dash-stat-change change-up">70,4 %</div></div>
          <div class="dash-stat"><div class="dash-stat-label">Échecs</div>
            <div class="dash-stat-val">52</div>
            <div class="dash-stat-change change-down">relançables</div></div>
        </div>
        <p class="legende">Chiffres d’illustration. L’écran affiche les vôtres.</p>
      </div>
    </div>
  </div>
</section>

<section class="section section-grise">
  <div class="container">
    <div class="colonne-lecture">
      <div style="text-align:center;margin-bottom:34px" class="fade-up">
        <div class="tag tag-orange">La partie qu’on ne vous montre jamais</div>
        <h2 class="headline">Les échecs sont <span>listés, et relançables</span></h2>
        <p style="color:var(--text2);max-width:62ch;margin:14px auto 0;line-height:1.8">
          Un envoi n’est jamais parfait. Ce qui change tout, c’est de savoir lesquels ont
          raté et pourquoi : c’est la seule partie du rapport qui se corrige.
        </p>
      </div>

      <div class="admin-table-wrap fade-up">
        <table class="admin-table">
          <thead><tr><th>Destinataire</th><th>Canal</th><th>Motif</th><th>Suite</th></tr></thead>
          <tbody>
            <tr><td>+228 90 ** ** 12</td><td>WhatsApp</td>
                <td><span class="badge badge-orange">Hors fenêtre 24 h</span></td>
                <td>Repart en gabarit</td></tr>
            <tr><td>k****@gmail.com</td><td>E-mail</td>
                <td><span class="badge badge-red">Adresse inexistante</span></td>
                <td>Retirée du carnet</td></tr>
            <tr><td>Navigateur Chrome</td><td>Push</td>
                <td><span class="badge badge-gray">Abonnement périmé</span></td>
                <td>Supprimé tout seul</td></tr>
            <tr><td>+229 97 ** ** 44</td><td>WhatsApp</td>
                <td><span class="badge badge-orange">Débit dépassé</span></td>
                <td>Relancer</td></tr>
          </tbody>
        </table>
      </div>
      <p class="legende">Exemples de motifs. Le rapport affiche les vôtres, avec leur compte.</p>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div style="text-align:center;margin-bottom:36px" class="fade-up">
      <h2 class="headline">Et par rapport à <span>ce que vous faites déjà</span></h2>
    </div>
    <div class="admin-table-wrap colonne-large fade-up">
      <table class="admin-table">
        <thead><tr><th></th><th>Une affiche</th><th>Une story</th><th>Push Wakabi</th></tr></thead>
        <tbody>
          <tr><td><strong>Qui la voit</strong></td><td>Qui passe devant</td>
              <td>Qui ouvre dans les 24 h</td><td><strong>Qui est déjà venu chez vous</strong></td></tr>
          <tr><td><strong>Ce que ça coûte</strong></td><td>L’impression</td>
              <td>Gratuit, puis la publicité</td><td><strong>Compris dans l’offre</strong></td></tr>
          <tr><td><strong>Ce que vous mesurez</strong></td><td>Rien</td>
              <td>Des vues</td><td><strong>Reçu, ouvert, suivi, venu</strong></td></tr>
          <tr><td><strong>À qui appartient l’audience</strong></td><td>À personne</td>
              <td>Au réseau social</td><td><strong>À vous</strong></td></tr>
        </tbody>
      </table>
    </div>
  </div>
</section>

<section class="section section-grise">
  <div class="container">
    <div class="colonne-lecture">
      <div style="text-align:center;margin-bottom:30px" class="fade-up">
        <div class="tag">Questions fréquentes</div>
        <h2 class="headline">Ce qu’on nous demande <span>avant de commencer</span></h2>
      </div>
      <div class="faq-list fade-up">
        <details class="faq-item"><summary class="faq-q">Faut-il un compte WhatsApp Business ?<span>+</span></summary>
          <div class="faq-a">Oui, pour ce canal-là : un numéro vérifié et des gabarits validés par
          le fournisseur. L’écran des canaux vous dit à quelle étape vous en êtes. Les
          notifications du navigateur, elles, ne demandent rien : elles marchent dès le premier
          décor publié.</div></details>
        <details class="faq-item"><summary class="faq-q">Et si je n’ai aucun numéro de téléphone ?<span>+</span></summary>
          <div class="faq-a">C’est le cas le plus fréquent au départ, et c’est exactement ce que les
          notifications du navigateur résolvent : la personne accepte une fois depuis votre page de
          décor, et vous pouvez la prévenir sans jamais connaître ni son numéro ni son
          adresse.</div></details>
        <details class="faq-item"><summary class="faq-q">Combien de messages par mois ?<span>+</span></summary>
          <div class="faq-a">Cela dépend de l’offre, et chaque canal a son propre compteur. Le détail
          est sur <a href="<?= e(url('?p=accueil#tarifs')) ?>">la page des offres</a>, et votre
          tableau de bord affiche ce qu’il vous reste.</div></details>
        <details class="faq-item"><summary class="faq-q">Comment quelqu’un se désabonne ?<span>+</span></summary>
          <div class="faq-a">En un clic, et c’est nous qui l’imposons : chaque e-mail porte son lien,
          chaque notification se coupe depuis le navigateur, et une personne désabonnée est écartée
          de vos envois suivants même si elle figure encore dans votre carnet.</div></details>
        <details class="faq-item"><summary class="faq-q">Est-ce que je peux programmer un envoi ?<span>+</span></summary>
          <div class="faq-a">Oui, et les quatre rappels de campagne partent seuls une fois armés. Un
          envoi programmé se relit avant de partir : rien ne part par accident.</div></details>
        <details class="faq-item"><summary class="faq-q">Que deviennent mes contacts si je pars ?<span>+</span></summary>
          <div class="faq-a">Ils repartent avec vous. Votre profil porte un bouton qui exporte tout
          dans un fichier lisible, et un bouton qui supprime le compte. La
          <a href="<?= e(url('?p=confidentialite')) ?>">politique de confidentialité</a> dit
          exactement ce que chacun efface.</div></details>
      </div>
    </div>
  </div>
</section>

<section class="section-sm">
  <div class="container">
    <div class="colonne-etroite fade-up" style="text-align:center">
      <h2 class="headline">Votre prochaine soirée a déjà <span>une liste d’invités</span></h2>
      <p style="color:var(--text2);margin:14px 0 24px;line-height:1.8">
        Ce sont les gens qui sont venus à la dernière. Il ne manque qu’un message.
      </p>
      <div class="hero-ctas" style="justify-content:center">
        <a class="btn btn-primary btn-lg" href="<?= e(url('?p=inscription')) ?>">Créer mon compte</a>
        <a class="btn btn-outline btn-lg" href="<?= e(url('?p=accueil#tarifs')) ?>">Voir les offres</a>
      </div>
      <p style="color:var(--text3);margin-top:16px;font-size:14px">
        Déjà un compte ? <a href="<?= e(url('?p=canaux')) ?>">Ouvrir mes canaux</a>
      </p>
    </div>
  </div>
</section>
