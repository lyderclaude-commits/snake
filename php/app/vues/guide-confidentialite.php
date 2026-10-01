<?php
/**
 * La politique de confidentialité, écrite depuis le code et non depuis un modèle.
 *
 * Chaque affirmation de cette page a été vérifiée dans le logiciel avant
 * d'être écrite, et c'est la seule façon d'en écrire une qui ne mente
 * pas :
 *
 *   — la photo de l'invité ne quitte pas son appareil : le badge est
 *     composé sur un canevas dans le navigateur (`php/studio/entry.ts`,
 *     `toBlob`), et les seuls téléversements du produit sont le cadre
 *     d'un décor, le logo d'un sponsor et les images d'un article
 *     (chercher `$_FILES` dans le code en donne la liste entière) ;
 *   — aucun mouchard tiers : pas une balise d'analyse, pas un pixel, et
 *     les polices sont servies depuis ce serveur — d'où l'absence de
 *     bandeau de cookies, qui n'aurait rien à proposer ;
 *   — ce que la suppression d'un compte efface, détache ou garde est la
 *     liste exacte de `supprimer_compte()`.
 *
 * Les durées de conservation sont la partie que le logiciel n'impose pas
 * encore partout : elles sont écrites comme des engagements, et c'est à
 * l'équipe de les tenir. Une page qui annoncerait une purge automatique
 * inexistante serait le mensonge le plus facile à écrire ici.
 */
$_courriel = identite_legale()['courriel'];
?>
<div class="page-header">
  <div class="container"><div class="page-header-inner">
    <div class="breadcrumb">
      <a href="<?= e(url('?p=accueil')) ?>">Accueil</a><span class="breadcrumb-sep">›</span>
      <span>Confidentialité</span>
    </div>
    <h1>Politique de confidentialité</h1>
    <p>Ce que nous collectons, pourquoi, combien de temps, et ce que vous pouvez en faire.
    À jour au <?= e(legal_date()) ?>.</p>
  </div></div>
</div>

<section class="section">
  <div class="container">
    <div class="legal-content">

      <h2>L’essentiel, en trois phrases</h2>
      <p><strong>Votre photo ne quitte pas votre appareil.</strong> Le badge est composé
      dans votre navigateur : la photo, le recadrage et le rendu final se font chez vous, et
      le fichier que vous enregistrez n’est jamais passé par nos serveurs.</p>
      <p><strong>Il n’y a aucun mouchard sur ce site.</strong> Pas de balise d’analyse, pas
      de pixel publicitaire, pas de police chargée depuis un autre domaine. C’est pourquoi
      vous n’avez pas vu de bandeau de cookies : nous n’avons rien à vous demander.</p>
      <p><strong>Vous pouvez tout emporter et tout effacer, vous-même.</strong> Votre profil
      porte un bouton qui exporte vos données et un bouton qui supprime votre compte, sans
      avoir à nous écrire ni à attendre une réponse.</p>

      <h2>1. Qui répond de ces données</h2>
      <?php require __DIR__ . '/partiels/identite-legale.php'; ?>
      <p>Une exception importante, au point 4 : pour les contacts qu’un organisateur
      constitue dans son carnet, c’est lui qui décide et qui répond, et nous ne faisons que
      les héberger pour son compte.</p>

      <h2>2. Si vous visitez le site sans compte</h2>
      <p>Vous pouvez parcourir le catalogue, lire le blog, fabriquer un badge et le
      télécharger sans créer de compte et sans nous donner votre nom. Dans ce cas, voici
      tout ce qui est enregistré :</p>
      <ul>
        <li><strong>Des compteurs par décor</strong> : une vue, un badge fabriqué, un badge
        emporté. Ils comptent des gestes, pas des personnes : il n’y a ni nom, ni adresse,
        ni identifiant de visiteur dans cette table.</li>
        <li><strong>Le code de votre badge</strong>, avec la campagne et la date. C’est ce
        code que le scanner de l’organisateur lit à l’entrée. Il n’est rattaché à aucune
        identité si vous n’avez pas de compte.</li>
        <li><strong>Votre adresse IP</strong>, pour les plafonds qui protègent le service :
        nombre d’essais de connexion, nombre d’envois du formulaire de contact, nombre de
        brouillons déposés sans compte. Elle sert à compter, pas à vous suivre, et ne
        voyage pas en dehors de ce serveur.</li>
        <li><strong>Un cookie technique</strong> si vous composez quelque chose avant de
        vous inscrire, pour retrouver votre brouillon au retour. Voir le point 7.</li>
      </ul>

      <h2>3. Si vous avez un compte</h2>
      <p>Nous conservons ce que vous nous donnez et ce que votre usage produit :</p>
      <ul>
        <li><strong>Votre adresse e-mail et votre nom</strong>, obligatoires. L’adresse
        identifie le compte et porte les messages de service.</li>
        <li><strong>Votre mot de passe</strong>, jamais en clair : seule une empreinte
        calculée à sens unique est stockée. Nous ne pouvons pas le lire, ni vous le
        rappeler, seulement vous laisser en choisir un autre.</li>
        <li><strong>Votre organisation, votre ville et votre téléphone</strong>, facultatifs.
        Le téléphone ne sert qu’à vous joindre au sujet de votre compte.</li>
        <li><strong>Votre offre et vos factures</strong> : l’offre en cours, son échéance,
        et les factures émises, que la comptabilité oblige à garder.</li>
        <li><strong>Vos contenus</strong> : vos décors et leurs cadres, vos articles, vos
        liens courts et leurs compteurs de clics, vos campagnes et leurs rapports.</li>
        <li><strong>Vos traces de sécurité</strong> : la date de création, la dernière
        journée où vous avez été vu (au jour près, pas à la seconde), la date de
        confirmation de votre adresse, le secret de la double authentification si vous
        l’activez, et votre clé d’interface de programmation si vous en demandez une.</li>
        <li><strong>Le journal d’activité</strong> : les actes faits sur le service, avec
        qui, quoi et quand. Voir le point 8, qui dit aussi ce qu’une suppression de compte
        n’emporte pas.</li>
      </ul>

      <h2>4. Les contacts d’un organisateur</h2>
      <p>Un organisateur peut constituer un carnet d’adresses et des listes pour écrire à
      son audience. Ces adresses sont les siennes : c’est lui qui décide à qui il écrit, qui
      doit avoir obtenu leur accord, et qui répond de l’exactitude de ses listes. Nous les
      hébergeons et les lui servons, rien de plus. Nous ne les mélangeons pas entre
      organisateurs, nous ne nous en servons pas pour nos propres envois, et nous ne les
      cédons à personne.</p>
      <p>Si vous avez reçu un message et souhaitez que vos coordonnées disparaissent,
      écrivez à l’organisateur qui vous a écrit. Si vous n’arrivez pas à l’identifier,
      écrivez-nous à <a href="mailto:<?= e($_courriel) ?>"><?= e($_courriel) ?></a> : nous
      transmettons, et nous écartons votre adresse des envois à venir.</p>

      <h2>5. Les notifications du navigateur</h2>
      <p>Si vous acceptez les notifications, votre navigateur nous remet une adresse
      d’abonnement et deux clés de chiffrement. Nous enregistrons ces trois éléments, le
      nom de votre navigateur, et le décor sous lequel vous vous êtes abonné, pour savoir
      qui vous prévenir.</p>
      <p>Ce n’est pas une identité : il n’y a ni nom, ni adresse e-mail dans cet
      enregistrement, et le même navigateur sur deux appareils compte pour deux
      abonnements. Vous pouvez vous désabonner d’un geste, depuis le même bouton qui a servi
      à vous abonner, ou depuis les réglages de votre navigateur. Un abonnement que le
      service de notifications déclare périmé est supprimé de lui-même.</p>

      <h2>6. Pourquoi, et sur quelle base</h2>
      <ul>
        <li><strong>Faire fonctionner le service</strong> (votre compte, vos décors, vos
        badges, vos rapports) : l’exécution du contrat qui nous lie.</li>
        <li><strong>Vous prévenir</strong> d’une décision de modération, d’une échéance, de
        la confirmation d’une adresse : l’exécution du contrat.</li>
        <li><strong>Les notifications et les messages de campagne</strong> : votre
        consentement, que vous retirez d’un clic.</li>
        <li><strong>Protéger le service</strong> (plafonds, journal, traces de connexion) :
        notre intérêt légitime à ce qu’il reste debout et à pouvoir dire qui a fait quoi.</li>
        <li><strong>La facturation et la comptabilité</strong> : une obligation légale.</li>
      </ul>
      <p>Nous ne vendons aucune donnée, nous n’en louons aucune, et nous ne faisons pas de
      publicité ciblée. Le modèle du service est l’abonnement, et c’est ce qui rend cette
      phrase tenable.</p>

      <h2>7. Les cookies, et ce qui tient dans votre navigateur</h2>
      <p>Trois choses, toutes nécessaires au fonctionnement, aucune publicitaire :</p>
      <ul>
        <li><strong>Le cookie de session</strong>, quand vous êtes connecté. Il disparaît à
        la déconnexion.</li>
        <li><strong>Le cookie de brouillon</strong>, si vous composez quelque chose avant de
        créer un compte. Il porte un jeton aléatoire, pas votre identité, et expire de
        lui-même.</li>
        <li><strong>Un souvenir local</strong> quand vous écartez l’invitation à vous
        abonner aux notifications, pour ne pas vous la remontrer. Il reste dans votre
        navigateur et ne nous est jamais envoyé.</li>
      </ul>

      <h2>8. Combien de temps</h2>
      <ul>
        <li><strong>Votre compte et vos contenus</strong> : tant que le compte existe. Vous
        pouvez le supprimer vous-même à tout moment.</li>
        <li><strong>Les factures</strong> : dix ans, comme l’oblige la comptabilité.</li>
        <li><strong>Le journal d’activité</strong> : trois ans. Il porte le nom recopié au
        moment de l’acte, pour que la suppression d’un compte n’efface pas la trace de ce
        qui a été fait avec.</li>
        <li><strong>Les plafonds anti-abus</strong> : quelques heures. Ce sont des compteurs
        glissants, pas un historique.</li>
        <li><strong>Les abonnements aux notifications</strong> : jusqu’au désabonnement, ou
        jusqu’à ce que le service de notifications les déclare périmés.</li>
        <li><strong>Les badges et leurs codes</strong> : la durée de la campagne, puis le
        temps où son décor reste en ligne. Un code doit rester vérifiable aussi longtemps
        qu’un invité peut se présenter avec.</li>
      </ul>

      <h2>9. Qui d’autre voit ces données</h2>
      <p>Personne, sauf ce qu’il faut pour que le service marche :</p>
      <ul>
        <?php
        /**
         * La seule phrase de cette page qui dépende de l'HÉBERGEUR et non
         * du logiciel.
         *
         * Elle est vraie pour LWS, qui héberge en France. Le même zip se
         * décompresse ailleurs : le jour où l'installation déménage hors
         * d'Europe, cette ligne doit suivre, sans quoi la page annoncerait
         * un transfert qui n'a plus lieu et en tairait un qui a lieu.
         */
        ?>
        <li><strong>L’hébergeur</strong>, qui stocke la base et les fichiers. Nos serveurs
        sont en Europe : vos données sortent donc du Togo, du Bénin ou de la Côte d’Ivoire
        pour y être conservées, dans un pays dont le niveau de protection est au moins
        équivalent.</li>
        <li><strong>Les services de notifications des navigateurs</strong> (ceux de Google,
        Mozilla ou Apple selon votre navigateur), qui transportent une notification jusqu’à
        vous. Son contenu leur est illisible : il est chiffré pour votre navigateur.</li>
        <li><strong>Le serveur d’envoi e-mail</strong> configuré par l’organisateur ou par
        nous, qui achemine les messages.</li>
        <li><strong>WhatsApp et Telegram</strong>, uniquement lorsqu’un organisateur branche
        ces canaux, et seulement pour les messages qu’il y envoie.</li>
        <li><strong>Une autorité</strong>, si la loi nous l’impose. Nous vous en informons
        quand nous en avons le droit.</li>
      </ul>

      <h2>10. Vos droits, et comment les exercer</h2>
      <p>Vous pouvez accéder à vos données, les rectifier, les effacer, vous opposer à
      certains traitements et récupérer vos données dans un format lisible. Deux de ces
      droits sont des boutons dans votre profil, et c’est volontaire : un droit qui demande
      d’écrire et d’attendre est un droit qu’on n’exerce pas.</p>
      <ul>
        <li><strong>Emporter</strong> : « Exporter mes données » dans votre profil rend un
        fichier contenant votre compte, vos campagnes et leurs chiffres, vos badges, vos
        liens et leurs clics, vos koris, vos articles et vos factures.</li>
        <li><strong>Effacer</strong> : « Supprimer mon compte » dans votre profil. La
        suppression efface vos identifiants, vos liens courts, vos notifications, vos
        abonnements aux notifications et votre historique de koris. Elle <em>détache</em>
        vos décors et les badges déjà émis au lieu de les détruire : un badge qu’un invité
        garde doit rester vérifiable à l’entrée, et l’adresse d’un décor qui circule doit
        continuer de répondre. Le journal garde la trace des actes, comme dit au point 8.</li>
        <li><strong>Rectifier</strong> : votre profil, directement.</li>
        <li><strong>Vous opposer</strong> aux notifications ou aux messages : le bouton de
        désabonnement, présent dans chaque e-mail et sous chaque invitation à s’abonner.</li>
      </ul>
      <p>Pour tout le reste, écrivez à
      <a href="mailto:<?= e($_courriel) ?>"><?= e($_courriel) ?></a>. Nous répondons sous
      trente jours, et presque toujours bien avant.</p>

      <h2>11. La sécurité</h2>
      <p>Les mots de passe sont stockés sous forme d’empreinte à sens unique. La double
      authentification est disponible sur tous les comptes. Le dossier des données n’est
      jamais servi par le serveur web, et deux règles indépendantes l’interdisent. Les
      échanges passent par HTTPS. Les jetons d’accès aux canaux d’envoi ne sont jamais
      réaffichés après leur enregistrement, ni placés dans une adresse.</p>
      <p>Aucune de ces mesures ne rend une fuite impossible. Si elle survenait et vous
      exposait, nous vous prévenons et nous prévenons l’autorité compétente.</p>

      <h2>12. Les mineurs</h2>
      <p>Le service n’est pas destiné aux moins de seize ans, et nous ne collectons pas
      sciemment leurs données. Un parent ou un tuteur qui constate le contraire peut nous
      écrire : nous supprimons le compte et ce qui s’y rattache.</p>

      <h2>13. Réclamer</h2>
      <p>Si notre réponse ne vous satisfait pas, vous pouvez saisir l’autorité de protection
      des données de votre pays : au Togo, l’Instance de protection des données à caractère
      personnel instituée par la loi n° 2019-014 du 29 octobre 2019 ; au Bénin, l’Autorité
      de protection des données à caractère personnel ; en Côte d’Ivoire, l’Autorité de
      régulation des télécommunications.</p>

      <h2>14. Les changements</h2>
      <p>Cette page change quand le service change. La date en haut est celle de la dernière
      révision, et non celle du jour : c’est ce qui vous permet de voir qu’elle a bougé. Un
      changement qui touche à vos droits ou à de nouvelles données collectées est annoncé
      dans l’application aux titulaires de compte.</p>
      <p>Les <a href="<?= e(url('?p=cgu')) ?>">conditions générales d’utilisation</a>
      complètent cette page : elles disent ce que chacun s’engage à faire du service.</p>

    </div>
  </div>
</section>
