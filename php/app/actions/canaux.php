<?php
/**
 * Brancher un canal, et le vérifier tout de suite.
 *
 * Un jeton qu'on enregistre sans l'essayer est un jeton qui échouera le
 * soir de l'événement, quand plus personne ne regarde l'écran. Chaque
 * enregistrement passe donc par la plateforme : elle répond, on écrit son
 * nom à côté, et l'organisateur voit qu'il est joignable.
 */

declare(strict_types=1);

$u = exiger_droit('push');
$message = null;
$erreur = null;

/**
 * Telegram et WhatsApp appartiennent à l'offre, comme le reste.
 *
 * `telegram_push` porte les deux : c'est la ligne que le client a achetée,
 * et la scinder maintenant lui retirerait quelque chose qu'il a payé.
 */
$equipe = droit($u, 'valider');
if (!$equipe && !capacite($u, 'telegram_push')) {
    vue('offre-requise', [
        'titre' => 'Canaux de diffusion',
        'quoi' => OFFRE_LIGNES['telegram_push'][0],
        'aide' => OFFRE_LIGNES['telegram_push'][2],
        'debloque' => offre_qui_debloque('telegram_push'),
    ]);
}

/** Le canal visé par une action, s'il appartient bien à ce compte. */
$mien = function (string $id) use ($u): ?array {
    $c = canal_par_id($id);
    return $c && (string) $c['proprietaire_id'] === (string) $u['id'] ? $c : null;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();
    $quoi = (string) ($_POST['quoi'] ?? '');

    if ($quoi === 'brancher') {
        $genre = (string) ($_POST['genre'] ?? '');
        $jeton = trim((string) ($_POST['jeton'] ?? ''));

        if (!isset(CANAUX_GENRES[$genre])) {
            $erreur = 'Canal inconnu.';
        } elseif ($jeton === '') {
            $erreur = 'Collez le jeton fourni par la plateforme.';
        } elseif ($genre === 'telegram') {
            /**
             * On vérifie AVANT d'enregistrer.
             *
             * Un jeton refusé n'a rien à faire en base : il y resterait
             * comme un canal « branché » qui n'écrit nulle part.
             */
            $v = telegram_verifier($jeton);
            if (!$v['ok']) {
                $erreur = $v['message'];
            } else {
                $id = canal_creer((string) $u['id'], 'telegram', $v['nom'], $jeton, $v['nom']);
                canal_maj($id, ['statut' => 'branche', 'message' => null, 'verifie_le' => maintenant()]);
                $message = 'Telegram branché : ' . $v['nom']
                    . '. Ajoutez maintenant une chaîne ou un groupe — le bot doit y être administrateur.';
            }
        } else {
            $numero = trim((string) ($_POST['reference'] ?? ''));
            if ($numero === '') {
                $erreur = 'Indiquez l’identifiant du numéro d’envoi (Phone number ID), pris dans le tableau de bord Meta.';
            } else {
                $id = canal_creer((string) $u['id'], 'whatsapp', 'WhatsApp Business', $jeton, $numero);
                $v = whatsapp_verifier(canal_par_id($id) ?? []);
                canal_maj($id, $v['ok']
                    ? ['statut' => 'branche', 'nom' => $v['nom'], 'message' => null, 'verifie_le' => maintenant()]
                    : ['statut' => 'a_verifier', 'message' => $v['message']]);
                $message = $v['ok']
                    ? 'WhatsApp branché : ' . $v['nom'] . '.'
                    : null;
                $erreur = $v['ok'] ? null : $v['message'];
            }
        }
    }

    if ($quoi === 'verifier' && ($c = $mien((string) ($_POST['id'] ?? '')))) {
        $v = $c['genre'] === 'telegram'
            ? telegram_verifier((string) $c['jeton'])
            : whatsapp_verifier($c);
        canal_maj((string) $c['id'], $v['ok']
            ? ['statut' => 'branche', 'nom' => $v['nom'], 'message' => null, 'verifie_le' => maintenant()]
            : ['statut' => 'a_verifier', 'message' => $v['message']]);
        $message = $v['ok'] ? 'Canal joignable : ' . $v['nom'] . '.' : null;
        $erreur = $v['ok'] ? null : $v['message'];
    }

    if ($quoi === 'destination' && ($c = $mien((string) ($_POST['id'] ?? '')))) {
        if ($c['genre'] !== 'telegram') {
            $erreur = 'WhatsApp n’a ni chaîne ni groupe : l’API n’écrit qu’en tête-à-tête.';
        } else {
            $cible = telegram_cible_propre((string) ($_POST['cible'] ?? ''));
            if ($cible === '') {
                $erreur = 'Collez @nom_de_la_chaine, son lien t.me, ou son identifiant numérique.';
            } else {
                $d = telegram_destination((string) $c['jeton'], $cible);
                if (!$d['ok']) {
                    $erreur = $d['message'];
                } else {
                    destination_poser((string) $c['id'], $d['genre'], $d['nom'], $cible, $d['abonnes']);
                    $message = sprintf('%s « %s » ajouté%s — %d abonné(s).',
                        CANAUX_DESTINATIONS[$d['genre']] ?? 'Destination', $d['nom'],
                        $d['genre'] === 'chaine' ? 'e' : '', $d['abonnes']);
                }
            }
        }
    }

    if ($quoi === 'retirer' && ($d = destination_par_id((string) ($_POST['destination'] ?? '')))) {
        if ($mien((string) $d['canal_id'])) {
            destination_retirer((string) $d['id']);
            $message = 'Destination retirée. Le bot y reste administrateur : retirez-le côté Telegram si besoin.';
        }
    }

    if ($quoi === 'relever' && ($c = $mien((string) ($_POST['id'] ?? '')))) {
        if ($c['genre'] !== 'telegram') {
            $erreur = 'Le relevé des abonnés est propre à Telegram.';
        } else {
            $r = telegram_relever_abonnes($c);
            $erreur = $r['ok'] ? null : $r['message'];
            $message = $r['ok']
                ? ($r['nouveaux'] > 0
                    ? $r['nouveaux'] . ' abonné(s) relevé(s).'
                    : 'Aucun nouvel abonné. Telegram ne garde ces messages que 24 h : le cron les relève '
                      . 'régulièrement pour ne rien manquer.')
                : null;
        }
    }

    if ($quoi === 'supprimer' && ($c = $mien((string) ($_POST['id'] ?? '')))) {
        canal_supprimer((string) $c['id']);
        $message = 'Canal débranché. Rien n’a été supprimé côté plateforme.';
    }
}

vue('canaux', [
    'titre' => 'Canaux de diffusion',
    'canaux' => canaux_de((string) $u['id']),
    'equipe' => $equipe,
    'moi' => $u,
    /**
     * Les notifications navigateur figurent ici comme un canal, parce
     * qu'elles en sont un : gratuit, immédiat, déjà branché sur les décors.
     * L'écran qui les envoie reste le sien — on y renvoie.
     */
    'push' => [
        'disponible' => push_disponible(),
        'abonnes' => count(push_destinataires($equipe ? 'tous' : 'mes-invites', (string) $u['id'])),
    ],
    'courriel' => courriel_branche(),
    'quotas' => [
        'telegram' => quota_canal($u, 'telegram'),
        'whatsapp' => quota_canal($u, 'whatsapp'),
    ],
    'message' => $message,
    'erreur' => $erreur,
]);
