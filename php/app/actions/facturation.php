<?php
/**
 * La facturation : un écran pour l'équipe, un pour le client.
 *
 * La même adresse pour les deux, et c'est voulu : l'organisateur qui reçoit
 * « voir mes factures » dans un e-mail arrive au bon endroit sans qu'on ait
 * à lui expliquer lequel. Le droit `comptes` décide de ce qu'il voit.
 *
 * CE QU'UN ÉQUIPIER NE VOIT PAS
 *
 * Un organisateur peut inviter quelqu'un sur UN décor — un graphiste, un
 * stagiaire. Cette personne a son propre compte, et cet écran ne lui montre
 * donc que SA propre facturation, jamais celle de qui l'a invitée. Ce n'est
 * pas un filtre qu'on ajoute : c'est que rien ici ne lit jamais autre chose
 * que `$u['id']`. Un écran qui accepterait un identifiant de compte en
 * paramètre serait la faille ; il n'y en a pas.
 */

declare(strict_types=1);

$u = exiger_role(...ROLES);
$equipe = droit($u, 'comptes');

/**
 * Un compte de la maison n'a pas d'abonnement, donc pas d'espace.
 *
 * Un éditeur, un scanner, un coordinateur travaillent POUR le guide : ils
 * ne paient rien et ne reçoivent aucune facture. Leur ouvrir une page qui
 * parlerait de leur offre serait au mieux incompréhensible, au pire une
 * porte de plus vers des chiffres qui ne les regardent pas.
 */
if (interne($u) && !$equipe) {
    rediriger(accueil_de($u));
}
$message = null;
$erreur = null;

/** Le compte visé par une action d'équipe. */
$client_vise = function (string $id) use ($equipe): ?array {
    return $equipe ? utilisateur_par_id($id) : null;
};

if ($post) {
    verifier_csrf();
    $quoi = (string) ($_POST['quoi'] ?? '');

    /* ---------------- côté équipe ---------------- */

    if ($equipe && $quoi === 'emettre' && ($c = $client_vise((string) ($_POST['id'] ?? '')))) {
        /**
         * Émettre, prolonger, envoyer : un seul geste, dans cet ordre.
         *
         * L'échéance d'abord, parce que la facture porte la période qu'elle
         * ouvre : l'inverse écrirait une date de fin qui n'existe pas
         * encore. Et l'envoi en dernier, pour que le document parti soit
         * celui qui est en base.
         */
        if (!abonnement_suivi($c)) {
            $erreur = 'Ce compte est sur une offre gratuite : posez-lui d’abord une offre payante.';
        } else {
            $jours = max(1, min(730, (int) ($_POST['jours'] ?? ABONNEMENT_JOURS)));
            $montant = max(0, min(10000000, (int) ($_POST['montant'] ?? 0)));
            $statut = (string) ($_POST['statut'] ?? 'reglee');
            $debut = ($_POST['debut'] ?? '') !== ''
                ? maintenant(strtotime((string) $_POST['debut']) ?: time())
                : maintenant();

            // Un changement d'offre demandé par le client s'applique ICI, à
            // la reprise de l'abonnement, et jamais au milieu d'une période
            // déjà payée. C'est la règle, et c'est le seul endroit qui l'applique.
            $posee = offre_demandee_appliquer($c);
            $c = utilisateur_par_id((string) $c['id']) ?? $c;

            $fin = echeance_prolonger($c, $jours, $debut);
            $id = facture_poser($c, $debut, $fin, $u, [
                'montant' => $montant ?: (int) (FORMULES[$posee]['prix'] ?? 0),
                'statut' => $statut,
                'mode' => (string) ($_POST['mode'] ?? ''),
                'reference' => trim((string) ($_POST['reference'] ?? '')),
                'note' => trim((string) ($_POST['note'] ?? '')),
            ]);
            $f = facture_par_id($id);

            journal_ecrire($u, 'facture.emise', 'compte', (string) $c['id'], (string) $c['nom'],
                $f['numero'] . ' · ' . montant_fr((int) $f['montant']) . ' · '
                . (FACTURE_STATUTS[$statut] ?? $statut));

            $envoi = '';
            if (($_POST['envoyer'] ?? '') === '1' && $f) {
                $r = facture_envoyer($f, $c);
                $envoi = $r['ok'] ? ' Envoyée par e-mail.' : ' Mais l’envoi a échoué : ' . $r['message'];
            } else {
                notifier((string) $c['id'], 'compte', 'Votre facture ' . $f['numero'],
                    'L’offre ' . formule_libelle($posee) . ' court jusqu’au ' . date_fr($fin)
                    . '. La facture est dans votre espace Facturation.', '?p=facturation');
            }
            $message = 'Facture ' . $f['numero'] . ' émise, échéance au ' . date_fr($fin) . '.' . $envoi;
        }
    }

    if ($equipe && $quoi === 'regler' && ($f = facture_par_id((string) ($_POST['facture'] ?? '')))) {
        facture_regler((string) $f['id'], (string) ($_POST['mode'] ?? ''),
            trim((string) ($_POST['reference'] ?? '')));
        journal_ecrire($u, 'facture.reglee', 'compte', (string) $f['utilisateur_id'],
            (string) $f['client_nom'], (string) $f['numero']);
        $message = 'Facture ' . $f['numero'] . ' marquée réglée.';
    }

    if ($equipe && $quoi === 'avoir' && ($f = facture_par_id((string) ($_POST['facture'] ?? '')))) {
        $av = facture_avoir((string) $f['id'], $u, trim((string) ($_POST['motif'] ?? '')));
        if ($av === null) {
            $erreur = 'Cette facture est déjà annulée, ou c’est elle-même un avoir.';
        } else {
            $a = facture_par_id($av);
            journal_ecrire($u, 'facture.avoir', 'compte', (string) $f['utilisateur_id'],
                (string) $f['client_nom'], $a['numero'] . ' annule ' . $f['numero']);
            $message = 'Avoir ' . $a['numero'] . ' émis : la facture ' . $f['numero'] . ' est annulée.';
        }
    }

    if ($equipe && $quoi === 'envoyer' && ($f = facture_par_id((string) ($_POST['facture'] ?? '')))) {
        $r = facture_envoyer($f);
        $message = $r['ok'] ? 'Facture ' . $f['numero'] . ' envoyée.' : null;
        $erreur = $r['ok'] ? null : $r['message'];
    }

    if ($equipe && $quoi === 'relancer') {
        $b = rappeler_echeances();
        $message = $b['rappeles'] . ' rappel(s) envoyé(s), ' . $b['retrogrades']
            . ' compte(s) repassé(s) en Découverte.';
    }

    /* ---------------- côté client ---------------- */

    if (!$equipe && $quoi === 'renouveler') {
        notifier_equipe('compte', 'Renouvellement demandé',
            ($u['organisation'] ?: $u['nom']) . ' souhaite renouveler son offre '
            . formule_libelle($u['formule'] ?? null) . '. Encaissez, puis émettez la facture.',
            '?p=facturation');
        journal_ecrire($u, 'abonnement.demande', 'compte', (string) $u['id'], (string) $u['nom'],
            'Renouvellement ' . formule_libelle($u['formule'] ?? null));
        $message = 'Demande transmise. L’équipe vous recontacte pour le règlement : '
                 . 'rien n’est prélevé automatiquement.';
    }

    if (!$equipe && $quoi === 'changer') {
        $veut = (string) ($_POST['formule'] ?? '');
        if (!offre_demander($u, $veut)) {
            $erreur = 'Choisissez une offre différente de la vôtre.';
        } else {
            notifier_equipe('compte', 'Changement d’offre demandé',
                ($u['organisation'] ?: $u['nom']) . ' demande à passer de '
                . formule_libelle($u['formule'] ?? null) . ' à ' . formule_libelle($veut)
                . '. Le changement s’applique à la prochaine échéance.', '?p=facturation');
            $message = 'Demande enregistrée : le passage à ' . formule_libelle($veut)
                     . ' prendra effet à votre prochaine échéance, sans rien changer d’ici là.';
        }
        $u = utilisateur_par_id((string) $u['id']) ?? $u;
    }
}

/* ---------------- l'export du comptable ---------------- */

if ($equipe && ($_GET['export'] ?? '') === 'csv') {
    $mois = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['mois'] ?? '')) ? (string) $_GET['mois'] : gmdate('Y-m');
    $csv = factures_csv(factures_du_mois($mois));
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="factures-' . $mois . '.csv"');
    header('Content-Length: ' . strlen($csv));
    echo $csv;
    exit;
}

/* ---------------- ce qu'on affiche ---------------- */

if ($equipe) {
    $lignes = facturation_lignes();
    $facturer = null;
    $vise = (string) ($_GET['facturer'] ?? '');
    if ($vise !== '') {
        $c = utilisateur_par_id($vise);
        $facturer = $c && abonnement_suivi($c) ? $c : null;
    }
    vue('facturation', [
        'titre' => 'Facturation',
        'moi' => $u,
        'lignes' => $lignes,
        'bilan' => facturation_bilan($lignes),
        'factures' => factures_du_mois(),
        'facturer' => $facturer,
        'filtre' => (string) ($_GET['etat'] ?? ''),
        'reglages' => facturation_reglages(),
        'courriel' => courriel_branche(),
        'message' => $message,
        'erreur' => $erreur,
    ]);
}

vue('facturation-client', [
    'titre' => 'Facturation',
    'me' => $u,
    'etat' => abonnement_etat($u),
    'factures' => factures_de((string) $u['id']),
    /**
     * Ce qu'il consomme, à côté de ce qu'il paie.
     *
     * C'est l'argument qui fait renouveler, et il est déjà calculé pour le
     * tableau de bord : mille deux cent quarante e-mails sur deux mille se
     * lit mieux qu'une promesse d'offre. On passe par `quota()` plutôt que
     * par `quota_telechargements()`, qui raisonne sur un décor et non sur
     * un compte.
     */
    'quotas' => [
        'emails' => ['max' => quota($u, 'emails_par_mois'),
                     'utilises' => emails_du_mois((string) $u['id'])],
        'badges' => ['max' => quota($u, 'telechargements'),
                     'utilises' => telechargements_du_mois((string) $u['id'])],
    ],
    'regle' => (int) db()->query('SELECT COALESCE(SUM(montant),0) FROM factures WHERE utilisateur_id = '
        . db()->quote((string) $u['id']) . " AND statut = 'reglee'")->fetchColumn(),
    'message' => $message,
    'erreur' => $erreur,
]);
