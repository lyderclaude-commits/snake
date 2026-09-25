<?php
/**
 * Le formulaire de contact du guide, qui envoie vraiment.
 *
 * Il arrivait de wakabileguide.com avec `onsubmit="handleContact(event)"`
 * et cette fonction n'existait nulle part : le bouton lançait une erreur
 * JavaScript, le message n'allait nulle part, et le visiteur repartait
 * convaincu d'avoir écrit. Un formulaire muet est pire que pas de
 * formulaire du tout — l'adresse e-mail juste au-dessus, elle, marchait.
 *
 * Le message part donc par `notifier_equipe()`, qui fait les deux choses
 * dont on a besoin : il pose une notification DANS le produit, visible
 * même sans transport e-mail configuré, et il envoie un courriel à chaque
 * membre de l'équipe quand le transport est branché. Une demande de
 * partenariat ne se perd alors ni parce que le SMTP est mal réglé, ni
 * parce que personne ne relève la boîte.
 */

$erreur = null;
$envoye = ($_GET['ok'] ?? '') === '1';

/** Les objets proposés. Écrits ici pour que le serveur n'accepte QUE ceux-là. */
const CONTACT_OBJETS = [
    'partenaire' => 'Devenir partenaire',
    'sponsoring' => 'Partenariat stratégique / Sponsoring',
    'support'    => 'Support technique',
    'presse'     => 'Presse & Médias',
    'autre'      => 'Autre demande',
];

if ($post) {
    verifier_csrf();

    /**
     * La limite porte sur l'ADRESSE IP, pas sur le couple e-mail + IP.
     *
     * Ailleurs dans le produit, la limite protège un compte : le mot de
     * passe qu'on essaie en boucle est celui d'une adresse précise. Ici
     * c'est l'inverse — l'expéditeur choisit son adresse, et la faire
     * varier suffirait à recommencer. Huit messages par quart d'heure
     * depuis une même connexion : personne d'honnête n'y touche.
     */
    $cle = 'contact|' . ($_SERVER['REMOTE_ADDR'] ?? '?');
    if (debit_depasse($cle)) {
        $erreur = 'Trop de messages envoyés. Réessayez dans 15 minutes.';
    } else {
        $nom = trim((string) ($_POST['nom'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $objet = (string) ($_POST['objet'] ?? '');
        $ville = trim((string) ($_POST['ville'] ?? ''));
        $tel = trim((string) ($_POST['telephone'] ?? ''));
        $message = trim((string) ($_POST['message'] ?? ''));

        if ($nom === '' || $message === '') {
            $erreur = 'Votre nom et votre message sont nécessaires.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erreur = 'Cette adresse e-mail ne semble pas valide.';
        } elseif (!isset(CONTACT_OBJETS[$objet])) {
            $erreur = 'Choisissez un objet dans la liste.';
        } else {
            debit_noter($cle);

            /* Le corps est du TEXTE, et il le reste : `notifier()` le range
               en base et `courriel_mis_en_page()` l'échappe au rendu. Rien
               de ce qui est tapé ici ne devient du balisage. */
            $corps = 'De : ' . $nom . ' <' . $email . '>'
                . ($ville !== '' ? "\n" . 'Ville : ' . $ville : '')
                . ($tel !== '' ? "\n" . 'Téléphone : ' . $tel : '')
                . "\n\n" . mb_substr($message, 0, 4000);

            /* Pas de ligne au journal : il retrace ce que fait l'ÉQUIPE,
               et recopier l'adresse d'un visiteur dans une table que
               consultent tous les gestionnaires de comptes n'apporterait
               rien que la notification ne porte déjà. */
            /**
             * `destinataires_contact()` et non `notifier_equipe()`.
             *
             * La seconde ne vise que le rôle « equipe », ce qui convient à
             * une file de relecture mais pas à ceci : une installation
             * neuve n'a qu'un super-administrateur, et ses premières
             * demandes de partenariat se seraient perdues en silence.
             */
            $titre = CONTACT_OBJETS[$objet] . ' · ' . $nom;
            foreach (destinataires_contact() as $membre) {
                notifier((string) $membre['id'], 'contact', $titre, $corps);
            }

            /* Rediriger après l'envoi : une page rechargée ne doit pas
               renvoyer le même message une deuxième fois. */
            rediriger('?p=contact&ok=1');
        }
    }
}

[$_v, $_t, $_d] = PAGES_CONTENU['contact'];
vue($_v, [
    'titre' => $_t . ' · ' . seo_reglage('seo_nom_site'),
    'description' => $_d,
    'erreur' => $erreur,
    'envoye' => $envoye,
    'objets' => CONTACT_OBJETS,
    'valeurs' => [
        'nom' => (string) ($_POST['nom'] ?? ''),
        'email' => (string) ($_POST['email'] ?? ''),
        'objet' => (string) ($_POST['objet'] ?? ''),
        'ville' => (string) ($_POST['ville'] ?? ''),
        'telephone' => (string) ($_POST['telephone'] ?? ''),
        'message' => (string) ($_POST['message'] ?? ''),
    ],
]);
