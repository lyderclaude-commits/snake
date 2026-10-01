<?php
/**
 * Régie — page de présentation, côté vitrine.
 *
 * Même raison que Push : le menu public mène ici, et un visiteur doit
 * pouvoir comprendre ce qu'on vend avant qu'on lui demande un compte.
 *
 * La page suit la question qu'on se pose vraiment devant une régie :
 * d'où vient l'audience, à quoi ressemble l'écriture, comment on choisit
 * qui reçoit, est-ce que ça ARRIVE, et qu'est-ce qu'on lit après. La
 * quatrième est celle qu'aucun concurrent n'affiche, et c'est celle qui
 * décide : un message mal authentifié n'est plus rangé en indésirables,
 * il est refusé.
 *
 * Les chiffres des tableaux sont des ILLUSTRATIONS, et le disent.
 */
?>
<div class="page-header">
  <div class="container"><div class="page-header-inner">
    <div class="breadcrumb">
      <a href="<?= e(url('?p=accueil')) ?>">Accueil</a><span class="breadcrumb-sep">›</span>
      <span>Boost · Régie</span>
    </div>
    <h1>Vos invités vous appartiennent</h1>
    <p>Chaque badge généré laisse une adresse. La régie en fait une audience, et cette
    audience reste la vôtre, et non celle d’un réseau social qui décide qui vous voit.</p>
    <div class="hero-ctas" style="justify-content:center;margin-top:26px">
      <a class="btn btn-white btn-lg" href="<?= e(url('?p=inscription')) ?>">Créer mon compte</a>
      <a class="btn btn-outline btn-lg btn-sur-bleu" href="<?= e(url('?p=accueil#tarifs')) ?>">Voir les offres</a>
    </div>
    <div class="rassure" style="margin-top:20px">
      <span class="rassure-item">Vos listes ne sont jamais mélangées</span>
      <span class="rassure-item">Export à tout moment</span>
      <span class="rassure-item">Désabonnement en un clic</span>
    </div>
  </div></div>
</div>

<section class="section">
  <div class="container">

    <?php $ligne = 'regie'; $quoi = 'la régie'; $pluriel = false;
          require __DIR__ . '/partiels/offre-ligne.php'; ?>

    <div style="text-align:center;margin-bottom:46px" class="fade-up">
      <div class="tag">De la salle à la boîte aux lettres</div>
      <h2 class="headline">Une campagne remplit une salle.<br>Les adresses qu’elle laisse
      <span>remplissent les suivantes</span></h2>
      <p style="color:var(--text2);max-width:64ch;margin:14px auto 0;line-height:1.8">
        C’est la différence entre une affiche et une audience. L’affiche travaille un soir ;
        la liste travaille pendant deux ans.
      </p>
    </div>

    <div class="steps-grid steps-quatre fade-up">
      <div class="step-card">
        <div class="step-num">1</div>
        <div class="step-title">Le badge laisse une adresse</div>
        <div class="step-desc">Votre invité fait son badge et le partage. S’il veut recevoir
        la suite, il laisse son adresse à ce moment-là : celui où il est content.</div>
      </div>
      <div class="step-card">
        <div class="step-num">2</div>
        <div class="step-title">Le carnet se tient seul</div>
        <div class="step-desc">Les adresses entrent dans votre carnet. Les non confirmées
        sont écartées des envois : votre réputation d’expéditeur ne se brûle pas sur des
        adresses mortes.</div>
      </div>
      <div class="step-card">
        <div class="step-num">3</div>
        <div class="step-title">Vous choisissez qui reçoit</div>
        <div class="step-desc">Cinq règles qui se combinent. Écrire à tout le monde coûte
        cher et lasse vite ; écrire à ceux que ça concerne se remarque.</div>
      </div>
      <div class="step-card">
        <div class="step-num">4</div>
        <div class="step-title">Vous savez ce qui est arrivé</div>
        <div class="step-desc">Parti, reçu, ouvert, suivi, échoué. Et les échecs listés,
        avec leur motif, relançables.</div>
      </div>
    </div>
  </div>
</section>

<section class="section section-grise">
  <div class="container">
    <div class="app-showcase fade-up">
      <div>
        <div class="tag">L’atelier</div>
        <h2 class="headline" style="margin:10px 0 16px">On écrit à gauche,<br>
        <span>on voit à droite</span></h2>
        <p style="color:var(--text2);line-height:1.85;margin-bottom:18px">
          Un message se juge sur un téléphone, pas dans un champ de formulaire. L’aperçu est
          à côté du texte, pas derrière un bouton : c’est ce qui fait qu’on corrige la ligne
          d’objet avant d’envoyer, et pas après.
        </p>
        <div class="pf-list">
          <div class="pf-item"><span class="pf-check">✓</span>Votre objet, à la largeur d’un téléphone</div>
          <div class="pf-item"><span class="pf-check">✓</span>Le prénom de la personne, s’il est connu</div>
          <div class="pf-item"><span class="pf-check">✓</span>Le lien court de la campagne, traçable</div>
          <div class="pf-item"><span class="pf-check">✓</span>La relecture avant envoi : rien ne part par accident</div>
          <div class="pf-item"><span class="pf-check">✓</span>Un envoi d’essai à vous-même, en un clic</div>
        </div>
      </div>

      <div>
        <?php
        /* Un aperçu de message, dessiné plutôt que photographié : une
           capture d'écran vieillit au premier changement de l'atelier, et
           personne ne pense à la refaire. */
        ?>
        <div class="encart" style="padding:0;overflow:hidden">
          <div style="padding:14px 18px;border-bottom:1px solid var(--border);display:flex;
               align-items:center;justify-content:space-between;gap:12px;background:var(--bg2)">
            <span class="encart-titre" style="margin:0">Aperçu du message</span>
            <span class="badge badge-blue">Brouillon</span>
          </div>
          <div style="padding:22px">
            <div style="font-size:12px;color:var(--text3);margin-bottom:4px">De : Le Rooftop 228</div>
            <div style="font-weight:700;font-size:17px;color:var(--text);margin-bottom:14px">
              Kossi, on remet ça samedi ?
            </div>
            <div style="height:120px;border-radius:12px;background:linear-gradient(135deg,#1E40AF,#3B82F6);
                 display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;
                 letter-spacing:.04em;margin-bottom:14px">SOIRÉE BLANCHE · 12 AVRIL</div>
            <p style="color:var(--text2);font-size:14px;line-height:1.75;margin:0 0 14px">
              Vous étiez là en mars, et la salle était pleine. On recommence samedi, même
              endroit, même heure. Votre badge est déjà prêt.
            </p>
            <span class="btn btn-primary btn-sm">Faire mon badge</span>
            <p style="color:var(--text3);font-size:11.5px;margin:18px 0 0;
               border-top:1px solid var(--border);padding-top:12px">
              Vous recevez ce message parce que vous avez fait un badge pour une soirée du
              Rooftop 228. <span style="text-decoration:underline">Se désabonner en un clic</span>
            </p>
          </div>
        </div>
        <p class="legende">Exemple. Le vôtre se compose dans l’atelier.</p>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div style="text-align:center;margin-bottom:40px" class="fade-up">
      <div class="tag">Cinq règles</div>
      <h2 class="headline">Choisir qui reçoit, <span>sans tableur</span></h2>
      <p style="color:var(--text2);max-width:62ch;margin:14px auto 0;line-height:1.8">
        Les règles se combinent, et le compte des destinataires se met à jour pendant que
        vous les posez. Vous savez à combien de personnes vous écrivez avant d’écrire.
      </p>
    </div>

    <div class="values-grid fade-up">
      <div class="value-card">
        <div class="value-icon"><?= icone_guide('map-marker', 26) ?></div>
        <div class="value-title">La ville</div>
        <div class="value-desc">Lomé, Cotonou, Abidjan. Une soirée à Lomé n’intéresse
        personne à Abidjan.</div>
      </div>
      <div class="value-card">
        <div class="value-icon"><?= icone_guide('calendar', 26) ?></div>
        <div class="value-title">La date</div>
        <div class="value-desc">Venus ce mois-ci, ce trimestre, l’an dernier. Une liste
        ancienne se réveille autrement.</div>
      </div>
      <div class="value-card">
        <div class="value-icon"><?= icone_guide('rubrique', 26) ?></div>
        <div class="value-title">La rubrique</div>
        <div class="value-desc">Concerts, restaurants, sport. Ce qui a plu une fois plaira
        sans doute encore.</div>
      </div>
      <div class="value-card">
        <div class="value-icon"><?= icone_guide('carnet', 26) ?></div>
        <div class="value-title">La campagne</div>
        <div class="value-desc">Ceux qui ont fait le badge de telle soirée, et pas d’une
        autre.</div>
      </div>
      <div class="value-card" style="border-color:var(--blue-200);background:var(--blue-50)">
        <div class="value-icon"><?= icone_guide('coche', 26) ?></div>
        <div class="value-title">Est réellement venu</div>
        <div class="value-desc">Son badge a été scanné à l’entrée. C’est la règle que
        personne d’autre ne peut vous donner, parce qu’elle vient du contrôle d’accès.</div>
      </div>
    </div>

    <div class="colonne-etroite fade-up" style="margin-top:30px">
      <div class="encart">
        <div class="encart-titre">Un exemple qu’on écrit en quinze secondes</div>
        <div class="feature-pills" style="margin:0 0 12px">
          <span class="pill"><span class="pill-dot"></span>Ville : Lomé</span>
          <span class="pill"><span class="pill-dot"></span>Rubrique : Concerts</span>
          <span class="pill"><span class="pill-dot"></span>Venu depuis : 6 mois</span>
          <span class="pill"><span class="pill-dot"></span>Scanné à l’entrée : oui</span>
        </div>
        <p><strong style="color:var(--primary)">428 personnes</strong> recevront ce message.
        Elles sont venues, elles sont entrées, et c’était chez vous.</p>
      </div>
      <p class="legende">Chiffre d’illustration. L’écran compte les vôtres pendant que vous
      posez les règles.</p>
    </div>
  </div>
</section>

<section class="section section-grise">
  <div class="container">
    <div class="app-showcase fade-up">
      <div>
        <div class="encart">
          <div class="encart-titre">Ce que Gmail verra de votre domaine</div>
          <div class="kb-item"><span class="kb-icon kb-vert">✓</span>
            <div class="kb-text"><strong>SPF</strong>
            <span class="kb-sous">Le serveur d’envoi est déclaré dans votre DNS.</span></div></div>
          <div class="kb-item" style="margin-top:10px"><span class="kb-icon kb-vert">✓</span>
            <div class="kb-text"><strong>DKIM</strong>
            <span class="kb-sous">Vos messages sont signés, et la signature est vérifiable.</span></div></div>
          <div class="kb-item" style="margin-top:10px"><span class="kb-icon kb-orange">!</span>
            <div class="kb-text"><strong>DMARC</strong>
            <span class="kb-sous">Absent. L’écran donne la ligne exacte à poser dans votre zone DNS.</span></div></div>
          <div class="kb-item" style="margin-top:10px"><span class="kb-icon kb-vert">✓</span>
            <div class="kb-text"><strong>Adresse expéditrice</strong>
            <span class="kb-sous">Du même domaine que le serveur d’envoi.</span></div></div>
        </div>
        <p class="legende">Relevé d’exemple. Le vôtre se fait depuis vos réglages.</p>
      </div>

      <div>
        <div class="tag tag-orange">Ce qui a changé en 2024</div>
        <h2 class="headline" style="margin:10px 0 16px">Un message mal authentifié<br>
        <span>n’est plus rangé en indésirables</span></h2>
        <p style="color:var(--text2);line-height:1.85;margin-bottom:16px">
          Il est refusé. Chez Gmail et Yahoo depuis 2024, chez Outlook depuis 2025. C’est le
          genre de règle qu’on découvre le jour où mille messages ne sont jamais arrivés, et
          dont personne ne vous prévient.
        </p>
        <p style="color:var(--text2);line-height:1.85;margin-bottom:22px">
          L’écran des réglages relève votre domaine et vous dit ce qui manque, avec la ligne
          à poser. C’est trois minutes chez votre hébergeur, une fois pour toutes, et c’est
          la différence entre une campagne qui arrive et une campagne qui disparaît.
        </p>
        <div class="feature-pills">
          <span class="pill"><span class="pill-dot"></span>Votre serveur d’envoi, ou le nôtre</span>
          <span class="pill"><span class="pill-dot"></span>Rythme d’envoi réglable</span>
          <span class="pill"><span class="pill-dot"></span>Essai avant campagne</span>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div style="text-align:center;margin-bottom:36px" class="fade-up">
      <div class="tag">Après l’envoi</div>
      <h2 class="headline">Cinq états, et <span>un seul qui se corrige</span></h2>
    </div>

    <div class="colonne-large fade-up">
      <div class="dash-stats dash-cinq" style="margin-bottom:0">
        <div class="dash-stat"><div class="dash-stat-label">Partis</div>
          <div class="dash-stat-val">2 140</div></div>
        <div class="dash-stat"><div class="dash-stat-label">Reçus</div>
          <div class="dash-stat-val">2 066</div>
          <div class="dash-stat-change change-up">96,5 %</div></div>
        <div class="dash-stat"><div class="dash-stat-label">Ouverts</div>
          <div class="dash-stat-val">1 329</div>
          <div class="dash-stat-change change-up">64,3 %</div></div>
        <div class="dash-stat"><div class="dash-stat-label">Suivis</div>
          <div class="dash-stat-val">487</div>
          <div class="dash-stat-change change-up">23,6 %</div></div>
        <div class="dash-stat" style="border-color:var(--blue-200)">
          <div class="dash-stat-label">Échoués</div>
          <div class="dash-stat-val">74</div>
          <div class="dash-stat-change change-down">listés et relançables</div></div>
      </div>
      <p class="legende">Chiffres d’illustration. L’écran affiche les vôtres, campagne par
      campagne.</p>

      <div class="encart" style="margin-top:28px">
        <div class="encart-titre">Et ce que les autres ne montrent pas</div>
        <p>Les soixante-quatorze échecs ont chacun un motif : adresse inexistante, boîte
        pleine, domaine qui refuse, débit dépassé. Les trois derniers se relancent ; le
        premier se retire du carnet. C’est la seule partie du rapport sur laquelle vous
        pouvez agir, et c’est presque toujours celle qu’on vous cache.</p>
      </div>
    </div>
  </div>
</section>

<section class="section section-grise">
  <div class="container">
    <div class="admin-table-wrap colonne-large fade-up">
      <table class="admin-table">
        <thead><tr><th></th><th>Une page de réseau social</th><th>Un groupe de discussion</th><th>Votre régie</th></tr></thead>
        <tbody>
          <tr><td><strong>Qui décide qui voit</strong></td><td>Le réseau</td>
              <td>Vous, dans la limite du groupe</td><td><strong>Vous</strong></td></tr>
          <tr><td><strong>Si le compte ferme</strong></td><td>Tout est perdu</td>
              <td>Tout est perdu</td><td><strong>Vous avez le fichier</strong></td></tr>
          <tr><td><strong>Payer pour être vu</strong></td><td>De plus en plus</td>
              <td>Non</td><td><strong>Non</strong></td></tr>
          <tr><td><strong>Savoir qui est venu</strong></td><td>Non</td>
              <td>Non</td><td><strong>Oui, scanné à l’entrée</strong></td></tr>
        </tbody>
      </table>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div class="colonne-lecture" style="max-width:780px">
      <div style="text-align:center;margin-bottom:30px" class="fade-up">
        <div class="tag">Questions fréquentes</div>
        <h2 class="headline">Ce qu’on nous demande <span>avant de commencer</span></h2>
      </div>
      <div class="faq-list fade-up">
        <details class="faq-item"><summary class="faq-q">Puis-je importer ma liste existante ?<span>+</span></summary>
          <div class="faq-a">Oui, depuis un fichier. Les adresses importées entrent dans votre carnet
          comme les autres, et vous restez responsable d’avoir obtenu leur accord : c’est votre
          liste, pas la nôtre.</div></details>
        <details class="faq-item"><summary class="faq-q">Mes contacts sont-ils mélangés avec ceux des autres ?<span>+</span></summary>
          <div class="faq-a">Jamais. Chaque carnet appartient à un compte et n’est visible que de lui.
          Nous ne nous en servons pas pour nos propres envois, et nous ne les cédons à personne : la
          <a href="<?= e(url('?p=confidentialite')) ?>">politique de confidentialité</a> le dit
          noir sur blanc.</div></details>
        <details class="faq-item"><summary class="faq-q">Puis-je utiliser mon propre serveur d’envoi ?<span>+</span></summary>
          <div class="faq-a">Oui. Vous renseignez votre SMTP dans les réglages, et l’écran vérifie
          qu’il répond avant que vous n’envoyiez quoi que ce soit à votre liste.</div></details>
        <details class="faq-item"><summary class="faq-q">Que se passe-t-il si quelqu’un se désabonne ?<span>+</span></summary>
          <div class="faq-a">Son adresse est écartée de tous vos envois suivants, immédiatement, même
          si elle figure encore dans votre carnet. Vous la voyez dans la liste, marquée comme
          telle.</div></details>
        <details class="faq-item"><summary class="faq-q">Combien d’envois par mois ?<span>+</span></summary>
          <div class="faq-a">Cela dépend de l’offre. Le détail est sur
          <a href="<?= e(url('?p=accueil#tarifs')) ?>">la page des offres</a>, et votre tableau de
          bord affiche ce qu’il vous reste.</div></details>
        <details class="faq-item"><summary class="faq-q">Et si je veux partir avec ma liste ?<span>+</span></summary>
          <div class="faq-a">Un bouton dans votre profil exporte tout dans un fichier lisible : votre
          carnet, vos campagnes, vos chiffres. Sans nous écrire et sans attendre.</div></details>
      </div>
    </div>
  </div>
</section>

<section class="section-sm section-grise">
  <div class="container">
    <div class="colonne-etroite fade-up" style="text-align:center">
      <h2 class="headline">La liste commence <span>au premier badge</span></h2>
      <p style="color:var(--text2);margin:14px 0 24px;line-height:1.8">
        Publiez un décor ce soir, et vous aurez une audience demain matin.
      </p>
      <div class="hero-ctas" style="justify-content:center">
        <a class="btn btn-primary btn-lg" href="<?= e(url('?p=inscription')) ?>">Créer mon compte</a>
        <a class="btn btn-outline btn-lg" href="<?= e(url('?p=accueil#tarifs')) ?>">Voir les offres</a>
      </div>
      <p style="color:var(--text3);margin-top:16px;font-size:14px">
        Déjà un compte ? <a href="<?= e(url('?p=regie')) ?>">Ouvrir ma régie</a>
      </p>
    </div>
  </div>
</section>
