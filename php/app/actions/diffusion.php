<?php
/**
 * Écrire aux navigateurs abonnés.
 *
 * L'équipe peut viser tout le monde ; un organisateur ne peut viser QUE
 * les invités de ses propres campagnes. La distinction n'est pas de la
 * politesse : la base d'abonnés est celle du guide, elle s'est constituée
 * sur la promesse de nouvelles du guide, et la revendre à qui paie une
 * offre la brûlerait en trois messages.
 *
 * L'envoi est synchrone et par paquets. Sur un mutualisé, une boucle de
 * dix mille requêtes sortantes finit en timeout à la moitié — et personne
 * ne sait quelle moitié. On envoie donc un paquet, on affiche le compte, et
 * le bouton propose de continuer. C'est moins élégant qu'une file, et c'est
 * la seule chose qui marche sans démon sur ce type d'hébergement.
 */
$u = exiger_role('partenaire', 'equipe');

$equipe = $u['role'] === 'equipe';
if (!$equipe && !capacite($u, 'telegram_push')) {
    vue('offre-requise', [
        'titre' => 'Notifications push',
        'quoi' => OFFRE_LIGNES['telegram_push'][0],
        'aide' => OFFRE_LIGNES['telegram_push'][2],
        'debloque' => offre_qui_debloque('telegram_push'),
    ]);
}

/** Les segments que CE compte a le droit de viser. */
$segments = $equipe
    ? PUSH_SEGMENTS
    : ['mes-invites' => 'Les invités de mes campagnes'];

$message = null;
$erreur = null;
$rapport = null;

$saisie = [
    'segment' => (string) ($_POST['segment'] ?? array_key_first($segments)),
    'titre' => trim((string) ($_POST['titre'] ?? '')),
    'corps' => trim((string) ($_POST['corps'] ?? '')),
    'lien' => trim((string) ($_POST['lien'] ?? '')),
];

if ($post) {
    verifier_csrf();

    if (!isset($segments[$saisie['segment']])) {
        $saisie['segment'] = array_key_first($segments);
    }

    /**
     * Le lien d'une notification reste CHEZ NOUS.
     *
     * Le même garde-fou que la redirection d'un décor, pour la même
     * raison : une notification signée Wakabi qui ouvre un site tiers,
     * c'est notre nom qui sert de caution. L'équipe, elle, écrit ce
     * qu'elle veut — c'est sa marque.
     */
    if ($saisie['lien'] !== '' && !$equipe && !redirection_autorisee($saisie['lien'])) {
        $erreur = 'Le lien doit mener vers ' . implode(' ou ', WAKABI_DOMAINES) . '.';
    } elseif ($saisie['titre'] === '') {
        $erreur = 'Un titre, sinon la notification s’affiche vide.';
    } elseif (mb_strlen($saisie['titre']) > 60) {
        $erreur = 'Le titre est tronqué au-delà de 60 caractères sur la plupart des téléphones.';
    } elseif (mb_strlen($saisie['corps']) > 180) {
        $erreur = 'Le texte est tronqué au-delà de 180 caractères.';
    } elseif (!push_disponible()) {
        $erreur = 'Cet hébergement n’a pas les fonctions de chiffrement nécessaires (OpenSSL, hash_hkdf).';
    } else {
        /**
         * Un compteur, parce qu'un bouton d'envoi de masse sans compteur
         * est une arme. La même fenêtre que partout ailleurs.
         */
        $cle_debit = 'push|' . $u['id'];
        if (debit_depasse($cle_debit)) {
            $erreur = 'Trop d’envois d’affilée. Réessayez dans ' . FENETRE_MINUTES . ' minutes.';
        } else {
            debit_noter($cle_debit);
            $cibles = push_destinataires($saisie['segment'], (string) $u['id']);
            $rapport = push_diffuser($cibles, [
                'titre' => $saisie['titre'],
                'corps' => $saisie['corps'],
                'lien' => $saisie['lien'] ?: base_url() . '/index.php?p=decors',
                'tag' => 'diffusion-' . substr(sha1($saisie['titre'] . $saisie['corps']), 0, 8),
            ]);
            $message = sprintf(
                '%d notification(s) partie(s), %d échec(s), %d abonnement(s) périmé(s) nettoyé(s).',
                $rapport['envoyes'], $rapport['echecs'], $rapport['nettoyes']
            );
            if ($rapport['envoyes'] === 0 && $rapport['echecs'] === 0) {
                $message = 'Personne n’est encore abonné dans ce segment. Rien n’est parti.';
            }
        }
    }
}

/* Le nombre d'abonnés par segment : sans lui, on écrit dans le vide. */
$compte = [];
foreach ($segments as $cle => $lib) {
    $compte[$cle] = count(push_destinataires($cle, (string) $u['id']));
}

vue('diffusion', [
    'titre' => 'Notifications push',
    'segments' => $segments,
    'compte' => $compte,
    'saisie' => $saisie,
    'message' => $message,
    'erreur' => $erreur,
    'equipe' => $equipe,
    'disponible' => push_disponible(),
]);
