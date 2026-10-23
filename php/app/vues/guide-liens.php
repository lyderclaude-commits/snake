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
<div class="page-header entete-boost">
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

<section class="section section-ouverture">
  <div class="container">

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

<?php
/**
 * LA section sombre de la page, et ce qu'elle dit depuis qu'elle a cessé
 * d'inventer.
 *
 * Elle montrait trois chiffres d'exemple — mille huit cent quarante-deux
 * clics, six cent douze badges, trente-trois pour cent — sous une ligne
 * qui avouait l'exemple. Un chiffre se lit avant sa légende : la page
 * vendait un résultat qu'elle n'avait pas mesuré.
 *
 * Elle dit maintenant ce que le logiciel FAIT, et chaque phrase se
 * vérifie dans le code : `liens.clics` est un compteur cumulé avec son
 * `dernier_clic`, la destination d'un lien se change sans toucher au
 * code à six caractères, et `rapport_liens()` range les clics d'une
 * campagne à côté de ses badges et de ses présences. Rien ici ne peut
 * vieillir, et plus rien ne demande de légende.
 *
 * La ligne sur la gratuité des clics en est une aussi : aucune table ne
 * facture un passage, et c'est le NOMBRE DE LIENS que l'offre limite.
 * Dire « gratuit » tout court aurait été la moitié de la phrase.
 */
?>
<section class="section section-sombre">
  <div class="container">
    <div style="text-align:center;margin-bottom:44px" class="fade-up">
      <div class="tag">Les avantages</div>
      <h2 class="headline">Ce qu’une adresse courte <span>sait faire</span></h2>
      <p style="max-width:64ch;margin:14px auto 0;line-height:1.8">
        Une adresse de billetterie ne sait rien faire d’autre que mener quelque part.
        Celle-ci se compte, se corrige après l’impression, et se range dans le rapport
        d’une campagne.
      </p>
    </div>

    <div class="why-grid fade-up" style="margin-bottom:0">
      <div class="carte-nuit accent a-ciel">
        <div class="carte-titre">Il se compte</div>
        <div class="carte-texte">Une affiche ne dit rien de ceux qui l’ont vue. Un lien court
        porte un compteur, cumulé depuis sa création, avec la date du dernier passage. C’est
        la seule chose qu’une campagne d’affichage sache vous dire de vrai.</div>
      </div>
      <div class="carte-nuit accent a-vert">
        <div class="carte-titre">Il se corrige après l’impression</div>
        <div class="carte-texte">La billetterie change d’adresse et les affiches sont déjà
        collées. Vous changez la destination ; le code à six caractères, lui, ne bouge pas, et
        les exemplaires en ville continuent de mener au bon endroit.</div>
      </div>
      <div class="carte-nuit accent a-orange">
        <div class="carte-titre">Il entre dans le rapport</div>
        <div class="carte-texte">Rattaché à l’une de vos campagnes, ses clics se lisent à côté
        des vues de la page, des badges fabriqués et des présences scannées à l’entrée. C’est
        ce qui transforme « on a collé cent affiches » en une phrase qui se vérifie.</div>
      </div>
    </div>

    <p class="legende" style="margin-top:28px">Aucun clic n’est facturé, quel que soit le nombre
    de passages : votre offre limite le nombre de liens, pas leur succès.</p>
  </div>
</section>

<section class="section section-grise">
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
          <a href="<?= e(url('?p=tarifs')) ?>">la page des offres</a>. Composer un lien ne
          demande pas de compte ; le créer, oui.</div></details>
      </div>
    </div>
  </div>
</section>

<section class="section-sm appel-final">
  <div class="container">
    <div class="colonne-etroite fade-up" style="text-align:center">
      <h2 class="headline">Essayez avant <span>de vous inscrire</span></h2>
      <p style="margin:14px 0 24px;line-height:1.8">
        Composez votre lien maintenant. On vous demandera qui vous êtes au moment de le
        créer, et pas une seconde avant.
      </p>
      <div class="hero-ctas" style="justify-content:center">
        <a class="btn btn-white btn-lg" href="<?= e(url('?p=lien-court')) ?>">Composer un lien</a>
        <a class="btn btn-outline btn-lg btn-sur-bleu" href="<?= e(url('?p=tarifs')) ?>">Voir les offres</a>
      </div>
      <p style="color:rgba(255,255,255,.75);margin-top:16px;font-size:14px">
        Déjà un compte ? <a href="<?= e(url('?p=liens')) ?>">Ouvrir mes liens</a>
      </p>
    </div>
  </div>
</section>
