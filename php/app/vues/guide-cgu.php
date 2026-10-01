<?php
/**
 * Les conditions générales d'utilisation, enfin sur le site.
 *
 * Elles vivaient sur wakabileguide.com/cgu.html, c'est-à-dire derrière le
 * pied de page de l'ancien site statique : on cliquait « CGU », on
 * changeait de site, on arrivait sous un autre header, et il fallait
 * revenir à la main. Pour une page qu'on ouvre au moment précis où l'on
 * hésite à payer, c'est la pire sortie possible.
 *
 * Elles sont écrites à partir de ce que le produit FAIT, et pas d'un
 * modèle : les quotas, la relecture des décors, le désabonnement en un
 * clic, l'export et la suppression du compte sont des écrans qui
 * existent. Une clause qui décrirait autre chose que le logiciel serait
 * fausse le jour où quelqu'un la lit en détail, et ce jour-là c'est
 * toujours parce qu'il y a un litige.
 *
 * Ce qu'elles ne peuvent pas inventer vient des réglages : raison
 * sociale, forme juridique, immatriculation, siège. Voir
 * `partiels/identite-legale.php`.
 */
?>
<div class="page-header">
  <div class="container"><div class="page-header-inner">
    <div class="breadcrumb">
      <a href="<?= e(url('?p=accueil')) ?>">Accueil</a><span class="breadcrumb-sep">›</span>
      <span>Conditions d’utilisation</span>
    </div>
    <h1>Conditions générales d’utilisation</h1>
    <p>Ce que vous pouvez attendre de nous, ce que nous attendons de vous, et ce qui se
    passe quand ça ne va pas. En vigueur depuis le <?= e(legal_date()) ?>.</p>
  </div></div>
</div>

<section class="section">
  <div class="container">
    <div class="legal-content">

      <h2>1. Qui nous sommes</h2>
      <?php require __DIR__ . '/partiels/identite-legale.php'; ?>
      <p>Dans tout ce texte, « le service » désigne ce site et les outils qu’il met à
      disposition : la fabrication de badges, le catalogue de décors, les liens courts,
      l’envoi de messages et les rapports qui vont avec. « Vous » désigne la personne qui
      utilise le service, avec ou sans compte.</p>

      <h2>2. Ce que le service fait</h2>
      <p>Le service permet à un organisateur de publier un décor, et à ses invités de
      composer un badge à partir de ce décor et de leur photo, puis de le partager. Il
      permet aussi d’envoyer des messages à une audience, de raccourcir des adresses, et de
      mesurer ce que tout cela produit : vues, badges fabriqués, badges emportés, présences
      scannées à l’entrée.</p>
      <p>Fabriquer un badge ne demande pas de compte. Publier un décor, envoyer des
      messages, créer des liens ou consulter des rapports en demande un.</p>

      <h2>3. Le compte</h2>
      <h3>3.1 La création</h3>
      <p>Vous nous donnez une adresse e-mail valable et un nom. L’adresse doit être la
      vôtre : c’est par elle que passent la confirmation, la réinitialisation du mot de
      passe et les décisions sur vos décors. Une adresse qui ne vous appartient pas est un
      compte que nous suspendrons.</p>
      <p>Vous êtes responsable de votre mot de passe et de ce qui se fait avec votre compte.
      La double authentification est disponible dans votre profil : sur un compte qui publie
      et qui envoie, elle vaut la minute qu’elle coûte.</p>

      <h3>3.2 Les équipiers</h3>
      <p>Un compte peut inviter des équipiers et leur donner des droits limités. Les actes
      d’un équipier engagent le compte qui l’a invité. Les droits de chacun sont visibles et
      révocables à tout moment depuis votre espace.</p>

      <h3>3.3 La suspension</h3>
      <p>Nous pouvons suspendre un compte qui enfreint ces conditions, qui met le service en
      danger, ou qui s’en sert pour nuire à quelqu’un. Nous disons toujours pourquoi. Une
      suspension n’efface rien : vos données restent, et un compte rétabli retrouve ses
      décors.</p>

      <h2>4. Vos contenus restent les vôtres</h2>
      <p>Les décors que vous publiez, les textes que vous écrivez, les images que vous
      téléversez et les listes de contacts que vous constituez vous appartiennent. Nous n’en
      devenons pas propriétaires, nous ne les vendons pas, et nous ne les utilisons pas pour
      autre chose que faire fonctionner le service pour vous.</p>
      <p>Pour afficher un décor dans le catalogue, l’envoyer dans une vignette de partage et
      le fabriquer dans le navigateur d’un invité, il faut techniquement que nous puissions
      le reproduire et le redimensionner. Vous nous en donnez donc l’autorisation, pour cela
      seulement, et pour le temps où le décor est publié.</p>
      <p>En publiant un contenu, vous affirmez que vous en avez le droit : que les photos
      sont les vôtres ou que leur auteur vous a autorisé, que les logos que vous apposez
      sont les vôtres ou ceux d’un partenaire qui a dit oui, et que les personnes
      reconnaissables sur une image ont accepté d’y figurer. Nous ne pouvons pas le
      vérifier, et c’est précisément pour cela que la responsabilité est la vôtre.</p>

      <h2>5. La relecture des décors</h2>
      <p>Un décor soumis par un partenaire passe par une relecture avant d’être publié. Nous
      regardons ce qui se verra : la lisibilité, le respect des marques, et l’absence de
      contenu illégal, haineux, trompeur ou sexuellement explicite. La décision est
      approuvée, à corriger avec le motif, ou refusée avec le motif. Elle vous est notifiée
      dans l’application, et par e-mail lorsque le transport e-mail est configuré.</p>
      <p>La relecture n’est pas un contrôle juridique de vos droits sur les images, et ne
      vous en décharge pas.</p>

      <h2>6. Ce que vous ne pouvez pas faire</h2>
      <ul>
        <li>Publier un contenu illégal, haineux, diffamatoire, trompeur sur l’identité de
        l’organisateur, ou sexuellement explicite.</li>
        <li>Faire passer un décor pour celui d’une marque, d’un lieu ou d’une personne sans
        son accord.</li>
        <li>Envoyer des messages à des personnes qui ne les ont pas demandés, ou continuer
        d’écrire à quelqu’un qui s’est désabonné.</li>
        <li>Utiliser les liens courts pour masquer une destination trompeuse, une
        hameçonnage ou un téléchargement malveillant.</li>
        <li>Chercher à contourner les quotas, les droits, l’authentification ou les limites
        de débit, ni automatiser l’usage du service en dehors de l’interface de
        programmation prévue pour cela.</li>
        <li>Revendre le service ou en donner l’accès à un tiers sans notre accord écrit.</li>
      </ul>

      <h2>7. Les offres, les quotas et le paiement</h2>
      <p>Le service propose plusieurs offres. Chacune porte ses propres limites : nombre de
      décors publiés, nombre de badges par campagne, accès à la régie, aux liens courts et
      aux canaux d’envoi. Le détail et le prix de chaque offre sont affichés avant l’achat,
      et vos factures sont dans votre espace.</p>
      <p>Un quota atteint ne supprime rien et n’interrompt pas une campagne en cours : il
      empêche d’en ouvrir une de plus. Un compte dont l’échéance est passée garde ses
      données et retrouve ses droits au renouvellement.</p>
      <p>Les sommes versées pour une période entamée ne sont pas remboursées, sauf si
      l’interruption vient de nous et dure plus de sept jours consécutifs.</p>

      <h2>8. Les envois, et le consentement de vos destinataires</h2>
      <p>Quand vous écrivez à une audience par la régie, par WhatsApp, par Telegram ou par
      les notifications du navigateur, c’est vous qui écrivez, pas nous. Vous êtes donc
      responsable d’avoir obtenu l’accord de vos destinataires et de l’exactitude de vos
      listes.</p>
      <p>Chaque message e-mail que vous envoyez porte un lien de désabonnement en un clic,
      et nous l’imposons : une liste d’envoi sans porte de sortie se retourne contre son
      propriétaire avant de se retourner contre nous. Une adresse désabonnée est écartée de
      vos envois suivants, y compris si elle figure encore dans votre carnet.</p>
      <p>Les adresses non confirmées sont également écartées des envois. Ce n’est pas une
      limite du produit, c’est ce qui protège votre réputation d’expéditeur : depuis 2024
      chez Gmail et Yahoo, un envoi mal authentifié n’est plus rangé en indésirables, il est
      refusé.</p>

      <h2>9. Les badges et les QR</h2>
      <p>Chaque badge fabriqué porte un code unique, lisible par le scanner de
      l’organisateur à l’entrée. Un badge est valable une fois par personne et par
      campagne : recharger la page rend le même code, elle n’en fabrique pas un second.</p>
      <p>Un code n’est pas un titre d’accès : c’est l’organisateur qui décide qui entre, et
      selon quelles règles. Nous ne garantissons ni l’entrée, ni le déroulement, ni la tenue
      d’un événement annoncé sur le service.</p>

      <h2>10. La disponibilité du service</h2>
      <p>Nous faisons de notre mieux pour que le service soit disponible, et nous ne
      promettons pas qu’il le sera sans interruption. Une maintenance, une panne de
      l’hébergement, une coupure réseau ou une défaillance d’un service tiers (un service de
      notifications, un opérateur de messagerie) peuvent l’interrompre.</p>
      <p>Les badges déjà fabriqués et téléchargés, eux, sont des fichiers que vos invités
      ont en main : ils ne dépendent plus de nous.</p>

      <h2>11. Notre responsabilité</h2>
      <p>Nous répondons des dommages directs causés par un manquement de notre part. Nous ne
      répondons pas des contenus que vous publiez, des messages que vous envoyez, des
      événements que vous organisez, ni des conséquences d’une information erronée que vous
      avez saisie.</p>
      <p>Rien dans ce texte ne limite notre responsabilité là où la loi ne le permet pas, en
      particulier en cas de faute lourde ou d’atteinte aux personnes.</p>

      <h2>12. Partir</h2>
      <p>Vous pouvez partir à tout moment, sans nous écrire : votre profil porte un bouton
      qui exporte toutes vos données dans un fichier lisible, et un bouton qui supprime
      votre compte. La suppression efface vos identifiants, vos liens courts, vos
      notifications, vos abonnements aux notifications du navigateur et votre historique de
      koris. Elle détache vos décors et les badges déjà émis de votre compte plutôt que de
      les détruire : un badge qu’un invité garde doit continuer de se vérifier à l’entrée, et
      un décor dont l’adresse circule doit continuer de répondre.</p>
      <p>Le journal d’activité conserve la trace des actes faits sur le service, avec le nom
      recopié au moment de l’acte. C’est ce qui permet de répondre à « qui a supprimé ce
      décor » des mois plus tard, et c’est la seule donnée qu’une suppression de compte
      n’emporte pas. La politique de confidentialité le détaille.</p>

      <h2>13. Les modifications</h2>
      <p>Nous pouvons modifier ces conditions. La date d’entrée en vigueur en haut de cette
      page change alors, et les titulaires de compte sont prévenus dans l’application. Si
      une modification ne vous convient pas, vous pouvez partir : voir le point 12.</p>

      <h2>14. Le droit applicable</h2>
      <p>Ces conditions sont régies par le droit togolais. En cas de différend, nous nous
      engageons à chercher d’abord une solution amiable : écrivez-nous, nous répondons. À
      défaut d’accord, les tribunaux compétents du siège de l’éditeur sont saisis.</p>
      <p>Si vous utilisez le service depuis le Bénin ou la Côte d’Ivoire, les dispositions
      impératives protectrices de votre pays de résidence vous restent acquises.</p>

      <h2>15. Nous écrire</h2>
      <p>Une question sur ces conditions, une réclamation, un contenu à signaler :
      <a href="mailto:<?= e(identite_legale()['courriel']) ?>"><?= e(identite_legale()['courriel']) ?></a>,
      ou le <a href="<?= e(url('?p=contact')) ?>">formulaire de contact</a>. Pour ce qui
      touche à vos données personnelles, la
      <a href="<?= e(url('?p=confidentialite')) ?>">politique de confidentialité</a> dit
      quels droits vous avez et comment les exercer sans nous écrire.</p>

    </div>
  </div>
</section>
