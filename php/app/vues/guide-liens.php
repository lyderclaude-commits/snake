<?php
/**
 * Liens courts — page de présentation, côté vitrine.
 *
 * Celle-ci a une chance que les deux autres n'ont pas : le composeur est
 * DÉJÀ public. On peut donc faire mieux qu'expliquer — on donne le champ
 * tout de suite, dans le bandeau, et le compte ne se demande qu'au moment
 * de créer le lien. Le mur reste à la fin, comme partout ailleurs dans le
 * produit.
 *
 * Le champ du bandeau n'est pas un décor : c'est un vrai formulaire qui
 * emmène l'adresse saisie jusqu'à `?p=lien-court`, où elle arrive déjà
 * remplie. Un champ qui ne ferait rien, ou qui redemanderait de retaper,
 * serait pire que pas de champ du tout.
 */
?>
<div class="page-header">
  <div class="container"><div class="page-header-inner">
    <div class="breadcrumb">
      <a href="<?= e(url('?p=accueil')) ?>">Accueil</a><span class="breadcrumb-sep">›</span>
      <span>Boost · Liens courts</span>
    </div>
    <h1>Une adresse courte, et ce qu’elle rapporte</h1>
    <p>Sur une affiche, dans un statut WhatsApp, à la radio : une adresse qu’on retient,
    et le nombre de personnes qui l’ont réellement suivie.</p>

    <?php
    /**
     * `method="get"` et non `post` : l'écran suivant n'écrit rien encore,
     * il ouvre le composeur avec l'adresse déjà en place. Un POST aurait
     * demandé un jeton anti-CSRF pour une navigation qui ne modifie rien,
     * et aurait cassé le retour arrière du navigateur.
     */
    ?>
    <form class="composeur" method="get" action="<?= e(url('?p=lien-court')) ?>">
      <?= permaliens_champ_page('lien-court') ?>
      <input type="url" name="cible" inputmode="url" aria-label="L’adresse à raccourcir"
             placeholder="https://votre-billetterie.com/soiree-du-12-avril">
      <button class="btn btn-primary" type="submit">Raccourcir</button>
    </form>
    <p style="color:rgba(255,255,255,.82);font-size:13.5px;margin-top:14px">
      Sans compte. On vous demande qui vous êtes au moment de créer le lien, pas avant.
    </p>
  </div></div>
</div>

<section class="section">
  <div class="container">

    <?php $ligne = 'liens_courts'; $quoi = 'les liens courts'; $pluriel = true;
          require __DIR__ . '/partiels/offre-ligne.php'; ?>

    <div style="text-align:center;margin-bottom:40px" class="fade-up">
      <div class="tag">Six caractères</div>
      <h2 class="headline">Assez court pour <span>se recopier d’un mur</span></h2>
      <p style="color:var(--text2);max-width:62ch;margin:14px auto 0;line-height:1.8">
        Une adresse de billetterie fait quatre-vingts caractères. Personne ne la tape depuis
        une affiche collée sur un mur, et personne ne la dit à la radio.
      </p>
    </div>

    <div class="colonne-lecture fade-up" style="max-width:780px">
      <div class="encart" style="text-align:center;padding:36px 24px">
        <div class="adresse-geante">
          <span class="domaine">wkb.link</span><span class="barre">/</span><span class="code">AbC123</span>
        </div>
        <div style="display:flex;gap:28px;justify-content:center;flex-wrap:wrap;margin-top:18px;text-align:left">
          <div style="max-width:260px">
            <div style="font-weight:700;font-size:13px;color:var(--text);margin-bottom:4px">Votre domaine, ou le nôtre</div>
            <div style="color:var(--text2);font-size:13px;line-height:1.7">Un domaine dédié se
            branche dans les réglages, et les liens déjà créés continuent de marcher.</div>
          </div>
          <div style="max-width:260px">
            <div style="font-weight:700;font-size:13px;color:var(--text);margin-bottom:4px">Six caractères sans piège</div>
            <div style="color:var(--text2);font-size:13px;line-height:1.7">Ni i, ni l, ni O, ni
            zéro : les caractères qu’on confond en lisant à voix haute sont écartés de
            l’alphabet.</div>
          </div>
        </div>
      </div>

      <div class="anatomie">
        <div class="anatomie-bloc">
          <div class="anatomie-quoi">Avant</div>
          <div class="anatomie-adresse">https://billetterie.example.com/evenements/soiree-blanche-12-avril?utm_source=affiche</div>
          <div class="anatomie-compte">86 caractères</div>
        </div>
        <div class="anatomie-bloc apres">
          <div class="anatomie-quoi">Après</div>
          <div class="anatomie-adresse">https://wkb.link/AbC123</div>
          <div class="anatomie-compte">23 caractères, et un compteur</div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section section-grise">
  <div class="container">
    <div style="text-align:center;margin-bottom:36px" class="fade-up">
      <div class="tag">En chiffres</div>
      <h2 class="headline">Ce qu’un lien court <span>change pour vous</span></h2>
      <p style="color:var(--text2);max-width:64ch;margin:14px auto 0;line-height:1.8">
        Une adresse qu’on retient, qu’on peut corriger après l’impression, et qui dit
        combien de personnes l’ont vraiment suivie.
      </p>
    </div>

    <div class="colonne-lecture fade-up">
      <?php
      /**
       * Des faits du produit, pas des résultats promis : la longueur du code,
       * l'écart sur l'adresse d'exemple plus haut (86 → 23 caractères), et
       * la redirection qui évite de réimprimer.
       */
      ?>
      <div class="stats-grid">
        <div class="stat-block"><div class="stat-num">6</div>
          <div class="stat-label">caractères à retenir, sans i, l, O ni zéro</div></div>
        <div class="stat-block"><div class="stat-num">−73<sub>%</sub></div>
          <div class="stat-label">de longueur face à une adresse de billetterie</div></div>
        <div class="stat-block"><div class="stat-num">0</div>
          <div class="stat-label">réimpression quand la destination change</div></div>
      </div>
      <p class="legende">Six caractères par code ; 86 caractères ramenés à 23 sur l’adresse d’exemple plus haut.</p>

      <div class="encart" style="margin-top:28px">
        <div class="encart-titre">Et chaque clic se compte, support par support</div>
        <p style="margin-bottom:14px">Un lien par support, et vous savez lequel a marché.
        Rattaché à l’une de vos campagnes, un lien voit ses clics entrer dans son rapport, à
        côté des vues de la page, des badges fabriqués et des présences scannées à l’entrée.
        « On a collé cent affiches » devient une phrase qui se vérifie.</p>
        <div class="feature-pills" style="margin:0">
          <span class="pill"><span class="pill-dot"></span>Clics comptés sans mouchard</span>
          <span class="pill"><span class="pill-dot"></span>Votre domaine, ou le nôtre</span>
          <span class="pill"><span class="pill-dot"></span>Destination modifiable</span>
          <span class="pill"><span class="pill-dot"></span>Rapport par campagne</span>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container">
    <div style="text-align:center;margin-bottom:40px" class="fade-up">
      <div class="tag">Quatre endroits</div>
      <h2 class="headline">Un lien par support, <span>et on sait lequel a marché</span></h2>
    </div>
    <div class="why-grid why-quatre fade-up">
      <div class="why-card">
        <div class="why-icon"><?= icone_guide('affiche', 30) ?></div>
        <div class="why-title">L’affiche</div>
        <div class="why-desc">Collée sur un mur, lue à trois mètres. Le lien doit tenir sur
        une ligne et se taper sans erreur.</div>
      </div>
      <div class="why-card">
        <div class="why-icon"><?= icone_guide('telephone', 30) ?></div>
        <div class="why-title">Le statut</div>
        <div class="why-desc">WhatsApp, Instagram. Un lien long se fait couper par l’aperçu ;
        un lien court reste entier.</div>
      </div>
      <div class="why-card">
        <div class="why-icon"><?= icone_guide('radio', 30) ?></div>
        <div class="why-title">La radio</div>
        <div class="why-desc">Six caractères se disent à voix haute. C’est pour cela que
        l’alphabet écarte ce qui s’entend pareil.</div>
      </div>
      <div class="why-card">
        <div class="why-icon"><?= icone_guide('flyer', 30) ?></div>
        <div class="why-title">Le flyer</div>
        <div class="why-desc">Avec son QR. Celui qui tape et celui qui scanne comptent dans
        le même total.</div>
      </div>
    </div>
  </div>
</section>

<section class="section section-grise">
  <div class="container">
    <div class="colonne-etroite fade-up" style="max-width:760px">
      <div class="encart appui">
        <div class="encart-titre">Ce qu’un lien court n’est pas</div>
        <p>Ce n’est pas un masque. Un lien qui cacherait une destination trompeuse, une
        collecte de mots de passe ou un téléchargement malveillant est supprimé, et le compte
        avec. Un raccourcisseur n’a de valeur que si les gens cliquent dessus les yeux
        fermés : c’est une confiance qui se garde en la défendant. Les
        <a href="<?= e(url('?p=cgu')) ?>">conditions d’utilisation</a> le disent aussi.</p>
      </div>
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
        <details class="faq-item"><summary class="faq-q">Puis-je changer la destination d’un lien déjà imprimé ?<span>+</span></summary>
          <div class="faq-a">Oui, et c’est la meilleure raison d’utiliser un lien court. L’affiche
          garde la même adresse, le QR garde le même dessin, et vous redirigez vers la billetterie
          du moment.</div></details>
        <details class="faq-item"><summary class="faq-q">Que se passe-t-il si je supprime un lien ?<span>+</span></summary>
          <div class="faq-a">Il cesse de répondre. Les affiches déjà collées mènent alors à une page
          qui le dit, plutôt qu’à une erreur de serveur. Mieux vaut le rediriger que le
          supprimer.</div></details>
        <details class="faq-item"><summary class="faq-q">Puis-je utiliser mon propre domaine ?<span>+</span></summary>
          <div class="faq-a">Oui. Vous l’achetez, vous le faites pointer ici, et vous le renseignez
          dans les réglages : vos liens s’écrivent alors sous votre marque. Les liens déjà créés
          continuent de marcher, parce que c’est le code à six caractères qui compte, pas le
          domaine.</div></details>
        <details class="faq-item"><summary class="faq-q">Les clics sont-ils comptés une seule fois par personne ?<span>+</span></summary>
          <div class="faq-a">Un clic est un clic. Nous comptons les passages, sans poser de mouchard
          sur celui qui clique : c’est une mesure de volume, pas un suivi de personne.</div></details>
        <details class="faq-item"><summary class="faq-q">Combien de liens par mois ?<span>+</span></summary>
          <div class="faq-a">Cela dépend de l’offre, et le détail est sur
          <a href="<?= e(url('?p=accueil#tarifs')) ?>">la page des offres</a>. Composer un lien ne
          demande pas de compte ; le créer, oui.</div></details>
      </div>
    </div>
  </div>
</section>

<section class="section-sm section-grise">
  <div class="container">
    <div class="colonne-etroite fade-up" style="text-align:center">
      <h2 class="headline">Essayez avant <span>de vous inscrire</span></h2>
      <p style="color:var(--text2);margin:14px 0 24px;line-height:1.8">
        Composez votre lien maintenant. On vous demandera qui vous êtes au moment de le
        créer, et pas une seconde avant.
      </p>
      <div class="hero-ctas" style="justify-content:center">
        <a class="btn btn-primary btn-lg" href="<?= e(url('?p=lien-court')) ?>">Composer un lien</a>
        <a class="btn btn-outline btn-lg" href="<?= e(url('?p=accueil#tarifs')) ?>">Voir les offres</a>
      </div>
      <p style="color:var(--text3);margin-top:16px;font-size:14px">
        Déjà un compte ? <a href="<?= e(url('?p=liens')) ?>">Ouvrir mes liens</a>
      </p>
    </div>
  </div>
</section>
