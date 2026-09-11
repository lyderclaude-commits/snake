<?php
/**
 * Les échéances d'abonnement, les rappels, et les factures.
 *
 * Le paiement lui-même reste hors du logiciel : l'équipe encaisse comme
 * elle l'entend — Mobile Money, espèces, virement — puis pose l'offre sur
 * le compte. Ce fichier s'occupe de ce qui, sans lui, s'oublie :
 *
 *  - **jusqu'à quand** un abonnement est payé ;
 *  - **prévenir** avant qu'il ne tombe, pas après ;
 *  - **redescendre** le compte à l'offre gratuite quand il est tombé ;
 *  - **laisser une trace** que l'organisateur puisse présenter à sa
 *    comptabilité.
 *
 * Sans échéance, un abonnement mensuel se transforme en abonnement à vie
 * dès que quelqu'un oublie de relancer — et personne ne s'en aperçoit,
 * parce que rien ne change à l'écran. C'est du revenu qui s'évapore sans
 * bruit.
 */

declare(strict_types=1);

/** La durée qu'un paiement achète, en jours. */
const ABONNEMENT_JOURS = 30;

/**
 * Le délai de grâce APRÈS l'échéance, avant de redescendre.
 *
 * Un organisateur qui paie le 3 au lieu du 1er ne doit pas voir sa
 * campagne se couper un samedi soir. Sept jours couvrent un week-end, un
 * virement lent et un aller-retour de relance.
 */
const ABONNEMENT_GRACE = 7;

/** Combien de jours avant l'échéance on prévient. Du plus tôt au plus tard. */
const ABONNEMENT_RAPPELS = [7, 1];

/**
 * Un compte a-t-il une échéance à suivre ?
 *
 * Seuls les comptes CLIENTS sur une offre payante en ont une. L'équipe n'a
 * pas d'abonnement, et l'offre Découverte ne tombe jamais : lui poser une
 * échéance ferait apparaître un compte à rebours sur un compte gratuit,
 * ce que personne ne comprendrait.
 */
function abonnement_suivi(?array $u): bool
{
    if (!$u || interne($u)) {
        return false;
    }
    return (int) (FORMULES[$u['formule'] ?? '']['prix'] ?? 0) > 0;
}

/** L'échéance d'un compte, ou `null` s'il n'en a pas. */
function echeance_de(?array $u): ?string
{
    return abonnement_suivi($u) ? (($u['echeance_le'] ?? null) ?: null) : null;
}

/**
 * Combien de jours restent — négatif si l'échéance est passée.
 *
 * `null` quand il n'y a rien à compter : c'est différent de zéro, et les
 * écrans doivent pouvoir faire la différence.
 */
function jours_restants(?array $u): ?int
{
    $e = echeance_de($u);
    if ($e === null) {
        return null;
    }
    $fin = strtotime($e);
    return $fin === false ? null : (int) floor(($fin - time()) / 86400);
}

/** Le compte est-il au-delà de son délai de grâce ? */
function abonnement_tombe(?array $u): bool
{
    $j = jours_restants($u);
    return $j !== null && $j < -ABONNEMENT_GRACE;
}

/**
 * Pose une échéance et rend la date écrite.
 *
 * Le point de départ est la PLUS TARDIVE de deux dates : aujourd'hui, ou
 * l'échéance déjà en cours. Renouveler le 25 pour un abonnement qui court
 * jusqu'au 30 ajoute donc trente jours au 30, et non au 25 — sans quoi
 * payer en avance ferait perdre cinq jours, ce qui décourage exactement le
 * comportement qu'on veut encourager.
 */
function echeance_prolonger(array $u, int $jours = ABONNEMENT_JOURS, ?string $debut = null): string
{
    $depart = max(time(), strtotime((string) ($u['echeance_le'] ?? '')) ?: 0);
    $fin = maintenant($depart + $jours * 86400);

    /**
     * Le début de la période en cours, enfin gardé quelque part.
     *
     * Il ne l'était nulle part : seule la date de FIN existait, et l'écran
     * de facturation devait la deviner en lisant la dernière facture. Un
     * compte qui n'en avait pas encore n'avait donc pas de date de début
     * du tout. On l'écrit au PREMIER paiement et on ne le touche plus :
     * c'est la date d'entrée du client, pas celle du dernier virement.
     */
    $depuis = ($u['abonne_depuis'] ?? '') ?: ($debut ?: maintenant());
    db()->prepare('UPDATE utilisateurs SET echeance_le = ?, rappel_echeance = NULL,
                   abonne_depuis = ? WHERE id = ?')
        ->execute([$fin, $depuis, $u['id']]);
    return $fin;
}

/**
 * Un changement d'offre demandé par le client, appliqué À L'ÉCHÉANCE.
 *
 * Jamais au prorata : « vous avez payé douze mille pour trente jours, vous
 * en avez consommé dix-sept, on vous doit six mille huit cents moins la
 * différence d'offre » ne s'explique pas au téléphone, et c'est au
 * téléphone que ça se discute. La demande attend donc la fin de la période
 * déjà payée — ce qui est aussi la seule façon de ne rien rembourser.
 *
 * Rend la formule effectivement posée.
 */
function offre_demandee_appliquer(array $u): string
{
    $veut = (string) ($u['offre_demandee'] ?? '');
    $actuelle = (string) ($u['formule'] ?? 'decouverte');
    if ($veut === '' || $veut === $actuelle || !isset(FORMULES[$veut])) {
        return $actuelle;
    }
    db()->prepare('UPDATE utilisateurs SET formule = ?, offre_demandee = NULL,
                   offre_demandee_le = NULL WHERE id = ?')
        ->execute([$veut, $u['id']]);
    journal_ecrire(null, 'abonnement.offre', 'compte', (string) $u['id'], (string) $u['nom'],
        formule_libelle($actuelle) . ' vers ' . formule_libelle($veut));
    return $veut;
}

/** Le client demande une autre offre. L'équipe la posera à l'échéance. */
function offre_demander(array $u, string $formule): bool
{
    if (!isset(FORMULES[$formule]) || $formule === ($u['formule'] ?? '')) {
        return false;
    }
    db()->prepare('UPDATE utilisateurs SET offre_demandee = ?, offre_demandee_le = ? WHERE id = ?')
        ->execute([$formule, maintenant(), $u['id']]);
    return true;
}

/** Retire l'échéance — au passage à l'offre gratuite, ou pour un compte interne. */
function echeance_retirer(string $id): void
{
    db()->prepare('UPDATE utilisateurs SET echeance_le = NULL, rappel_echeance = NULL WHERE id = ?')
        ->execute([$id]);
}

/* ------------------------------------------------------------------ */
/* Le passage quotidien                                                */
/* ------------------------------------------------------------------ */

/**
 * Prévenir avant, redescendre après. Une fois par jour, par le cron.
 *
 * Trois moments, et un seul message pour chacun :
 *
 *  - **sept jours avant** : le temps de faire un virement ;
 *  - **la veille** : le rappel qu'on lit vraiment ;
 *  - **après le délai de grâce** : le compte redescend à Découverte, et
 *    on le dit franchement plutôt que de laisser quelqu'un découvrir sa
 *    campagne coupée.
 *
 * `rappel_echeance` garde le dernier seuil envoyé : sans lui, le cron
 * enverrait le même message tous les jours pendant une semaine, ce qui
 * est la meilleure façon de faire filtrer ses e-mails.
 */
function rappeler_echeances(): array
{
    $bilan = ['rappeles' => 0, 'retrogrades' => 0];

    $comptes = db()->query("SELECT * FROM utilisateurs
        WHERE echeance_le IS NOT NULL AND echeance_le <> '' AND suspendu = 0")->fetchAll();

    foreach ($comptes as $u) {
        if (!abonnement_suivi($u)) {
            // L'offre a changé entre-temps : l'échéance n'a plus d'objet.
            echeance_retirer((string) $u['id']);
            continue;
        }
        $jours = jours_restants($u);
        if ($jours === null) {
            continue;
        }

        if ($jours < -ABONNEMENT_GRACE) {
            $avant = formule_libelle($u['formule']);
            db()->prepare("UPDATE utilisateurs SET formule = 'decouverte',
                           echeance_le = NULL, rappel_echeance = NULL WHERE id = ?")
                ->execute([$u['id']]);
            notifier((string) $u['id'], 'compte', 'Votre offre est repassée en Découverte',
                'L’abonnement ' . $avant . ' est arrivé à terme il y a plus de '
                . ABONNEMENT_GRACE . ' jours. Le compte fonctionne toujours, avec les limites '
                . 'de l’offre gratuite. Écrivez-nous pour le réactiver : rien n’est perdu, '
                . 'vos campagnes et vos badges sont intacts.', '?p=partenaire');
            journal_ecrire(null, 'abonnement.echu', 'compte', (string) $u['id'],
                (string) $u['nom'], 'Retour à Découverte depuis ' . $avant);
            $bilan['retrogrades']++;
            continue;
        }

        foreach (ABONNEMENT_RAPPELS as $seuil) {
            if ($jours > $seuil || (int) ($u['rappel_echeance'] ?? 0) === $seuil
                || ((int) ($u['rappel_echeance'] ?? 0) > 0 && (int) $u['rappel_echeance'] < $seuil)) {
                continue;
            }
            notifier((string) $u['id'], 'compte',
                $jours <= 1 ? 'Votre abonnement se termine demain' : 'Votre abonnement se termine dans une semaine',
                'L’offre ' . formule_libelle($u['formule']) . ' court jusqu’au '
                . date_fr((string) $u['echeance_le']) . '. Passé un délai de '
                . ABONNEMENT_GRACE . ' jours, le compte repasse en Découverte, sans rien perdre, '
                . 'mais avec les limites de l’offre gratuite.', '?p=partenaire');
            db()->prepare('UPDATE utilisateurs SET rappel_echeance = ? WHERE id = ?')
                ->execute([(string) $seuil, $u['id']]);
            $bilan['rappeles']++;
            break;
        }
    }

    return $bilan;
}
