<?php
/** Gestion des comptes : création par l'équipe, rôle, offre, suspension. */
$u = exiger_droit('comptes');

/**
 * L'écran des comptes, en deux listes.
 *
 * L'équipe d'un côté — quelques personnes, jamais tronquées — les clients
 * de l'autre, cherchables. Écrit ici plutôt que deux fois, parce que le
 * formulaire de création réaffiche le même écran quand il refuse.
 */
function ecran_comptes(): array
{
    $cherche = trim((string) ($_GET['q'] ?? ''));
    return [
        'equipe' => comptes_equipe(),
        'clients' => comptes_clients($cherche),
        'clients_total' => comptes_clients_combien($cherche),
        'cherche' => $cherche,
    ];
}

/* ---------------- créer un compte ---------------- */

/**
 * Deux formulaires, deux actions, et c'est délibéré.
 *
 * Un compte client et un compte de la maison n'ont ni les mêmes champs, ni
 * les mêmes conséquences. Tant qu'ils partageaient un seul formulaire, la
 * liste des rôles mêlait « Organisateur » et « Coordinateur » à deux
 * lignes d'écart, et le champ « Offre » restait là, à proposer une
 * Croissance à 25 000 F à quelqu'un qu'on embauche. On ne se vend pas des
 * fonctions à soi-même : le formulaire de l'équipe n'a donc pas d'offre du
 * tout, et celui des clients n'a pas de rôle interne.
 *
 * La vérification côté serveur ne dépend pas du formulaire d'où l'on
 * vient : chacune des deux actions refuse la famille de l'autre.
 */
if ($page === 'creer-compte' || $page === 'creer-equipier') {
    verifier_csrf();
    $interne_voulu = $page === 'creer-equipier';

    $v = [
        'nom' => trim((string) ($_POST['nom'] ?? '')),
        'email' => trim((string) ($_POST['email'] ?? '')),
        'role' => (string) ($_POST['role'] ?? ($interne_voulu ? 'scanner' : 'partenaire')),
        'formule' => $interne_voulu ? '' : (string) ($_POST['formule'] ?? 'decouverte'),
        'organisation' => trim((string) ($_POST['organisation'] ?? '')),
        'ville' => trim((string) ($_POST['ville'] ?? 'lome')),
    ];
    $mdp = (string) ($_POST['mot_de_passe'] ?? '');
    $famille = in_array($v['role'], ROLES_INTERNES, true);

    $erreur = match (true) {
        $v['nom'] === '' => 'Indiquez le nom de la personne.',
        !filter_var($v['email'], FILTER_VALIDATE_EMAIL) => 'Cette adresse e-mail n’est pas valide.',
        strlen($mdp) < 8 => 'Le mot de passe doit faire au moins 8 caractères.',
        !in_array($v['role'], ROLES, true) => 'Rôle inconnu.',
        $interne_voulu && !$famille =>
            'Ce formulaire crée des comptes de l’équipe. Pour un client, utilisez l’autre.',
        !$interne_voulu && $famille =>
            'Ce formulaire crée des comptes clients. Pour l’équipe, utilisez l’autre.',
        $famille && !droit($u, 'comptes_internes') =>
            'Seul un super-administrateur crée un compte de l’équipe.',
        !$interne_voulu && !isset(FORMULES[$v['formule']]) => 'Offre inconnue.',
        utilisateur_par_email($v['email']) !== null => 'Un compte existe déjà avec cette adresse.',
        default => null,
    };

    if ($erreur !== null) {
        // On réaffiche le formulaire rempli plutôt que de rediriger : refaire
        // sa saisie à cause d'un doublon d'adresse est une punition inutile.
        vue('comptes', [
            'titre' => 'Comptes',
            'erreur' => $erreur,
            'valeurs' => $v,
            'ouvert' => $interne_voulu ? 'equipe' : 'client',
        ] + ecran_comptes());
    }

    $id = creer_utilisateur([
        'email' => $v['email'],
        'mot_de_passe' => $mdp,
        'nom' => $v['nom'],
        'role' => $v['role'],
        'formule' => $v['formule'],
        'organisation' => $v['organisation'] ?: null,
        'ville' => $v['ville'] ?: null,
    ]);

    $quoi = $famille
        ? role_libelle($v['role']) . ', compte de l’équipe'
        : role_libelle($v['role']) . ', offre ' . formule_libelle($v['formule']);
    journal_ecrire($u, 'compte.cree', 'compte', $id, $v['nom'], $quoi);

    /**
     * Le lien de confirmation part MAINTENANT, comme à l'inscription.
     *
     * Sans cette ligne, un compte créé par l'équipe naissait avec une
     * adresse non confirmée et aucun moyen de la confirmer : le bouton
     * « renvoyer » n'existe que sur le tableau de bord du titulaire, et il
     * ne peut pas s'y rendre tant qu'on ne lui a pas transmis son mot de
     * passe. Le seul bouton à portée de l'équipe était celui du rôle — d'où
     * un courriel parlant d'offre là où l'on attendait une confirmation.
     */
    $suite = '';
    if (verification_exigee()) {
        $envoi = envoyer_verification(['id' => $id, 'email' => $v['email'], 'nom' => $v['nom']]);
        $suite = $envoi['ok']
            ? ' Un lien de confirmation vient de partir vers ' . $v['email'] . '.'
            : ' L’envoi du lien de confirmation a échoué : ' . $envoi['message'];
    }

    rediriger('?p=comptes&ok=' . urlencode(
        'Compte créé pour ' . $v['nom'] . ' (' . $quoi . '). '
        . 'Transmettez-lui son adresse et son mot de passe : ils ne sont affichés nulle part ailleurs.'
        . $suite
    ));
}

/* ---------------- modifier un compte existant ---------------- */

if ($page === 'role' || $page === 'suspendre') {
    verifier_csrf();
    $id = (string) ($_POST['id'] ?? '');
    // On revient d'où l'on vient : la liste, ou la fiche du compte.
    $retour = ($_POST['retour'] ?? '') === 'fiche'
        ? '?p=organisateur&id=' . rawurlencode($id)
        : '?p=comptes';
    if ($id === $u['id']) {
        // Se rétrograder ou se suspendre soi-même laisserait l'installation
        // sans administrateur. Refusé, quoi qu'il arrive.
        rediriger($retour . '&err=' . urlencode('Vous ne pouvez pas modifier votre propre compte.'));
    }
    /**
     * Un compte de l'ÉQUIPE ne se touche que par un super-administrateur.
     *
     * Sans cette barrière, `comptes` suffit à se promouvoir soi-même — il
     * suffit de promouvoir un complice, ou de suspendre le seul
     * administrateur restant. C'est exactement la différence entre une
     * équipe et une porte ouverte, et elle tient dans ces quatre lignes.
     */
    $vise = utilisateur_par_id($id);
    if (!$vise) {
        rediriger($retour . '&err=' . urlencode('Compte introuvable.'));
    }
    if (interne($vise) && !droit($u, 'comptes_internes')) {
        rediriger($retour . '&err=' . urlencode(
            'Un compte de l’équipe ne se modifie que par un super-administrateur.'));
    }

    if ($page === 'role') {
        $role = (string) ($_POST['role'] ?? '');
        if (!in_array($role, ROLES, true)) {
            rediriger($retour . '&err=' . urlencode('Rôle inconnu.'));
        }
        /**
         * L'offre suit le RÔLE, et un rôle interne n'en a pas.
         *
         * Le formulaire de l'équipe ne poste même pas de champ « offre » ;
         * mais la règle est appliquée ici, côté serveur, parce qu'un
         * formulaire trafiqué qui poste `formule=mouvement` avec
         * `role=coordinateur` ne doit pas fabriquer un compte de la maison
         * qui traîne une offre payante — et une échéance avec.
         */
        $formule = formule_pour($role, (string) ($_POST['formule'] ?? $vise['formule'] ?? 'decouverte'));
        if (!in_array($role, ROLES_INTERNES, true) && !isset(FORMULES[$formule])) {
            rediriger($retour . '&err=' . urlencode('Offre inconnue.'));
        }
        // Donner un rôle interne est le même geste que d'en modifier un.
        if (in_array($role, ROLES_INTERNES, true) && !droit($u, 'comptes_internes')) {
            rediriger($retour . '&err=' . urlencode(
                'Seul un super-administrateur crée un compte de l’équipe.'));
        }
        /**
         * Le DERNIER super-administrateur ne se rétrograde pas.
         *
         * Une installation sans lui ne se répare que par la base de
         * données — et l'on ne s'en aperçoit qu'au moment d'en avoir
         * besoin, c'est-à-dire trop tard.
         */
        if ($vise['role'] === 'super_admin' && $role !== 'super_admin' && compte_super_admins() <= 1) {
            rediriger($retour . '&err=' . urlencode(
                'C’est le dernier super-administrateur. Nommez-en un autre avant de le rétrograder.'));
        }
        $change_role = $role !== $vise['role'];
        $change_offre = $formule !== (string) ($vise['formule'] ?? '');
        if (!$change_role && !$change_offre) {
            // Rien n'a bougé : ni écriture, ni ligne de journal, ni courriel.
            // Un « OK » cliqué par acquit de conscience ne doit pas expédier
            // à quelqu'un l'annonce d'un changement qui n'a pas eu lieu.
            rediriger($retour . '&ok=' . urlencode('Rien n’a changé sur ce compte.'));
        }

        db()->prepare('UPDATE utilisateurs SET role = ?, formule = ? WHERE id = ?')
            ->execute([$role, $formule, $id]);
        journal_ecrire($u, 'compte.role', 'compte', $id, (string) $vise['nom'],
            trim(($change_role ? role_libelle($vise['role']) . ' → ' . role_libelle($role) : '')
               . ($change_role && $change_offre ? ', ' : '')
               . ($change_offre
                   ? (formule_affichee($vise) ?? 'sans offre') . ' → '
                     . (formule_pour($role, $formule) === '' ? 'sans offre' : formule_libelle($formule))
                   : '')));

        /**
         * L'échéance suit l'offre, automatiquement.
         *
         * Passer quelqu'un à une offre payante sans date de fin, c'est
         * fabriquer un abonnement à vie que personne ne relancera. Et
         * repasser à Découverte doit effacer l'échéance, sans quoi le cron
         * enverrait des rappels pour un abonnement qui n'existe plus.
         */
        $apres = utilisateur_par_id($id);
        if ($apres && abonnement_suivi($apres)) {
            if (($apres['echeance_le'] ?? '') === '') {
                echeance_prolonger($apres);
            }
        } else {
            echeance_retirer($id);
        }

        /**
         * La personne concernée l'apprend — et le message parle de CE qui
         * la concerne.
         *
         * Deux messages, parce que ce sont deux nouvelles différentes. À un
         * client, l'offre : son quota vient de bouger, mais aussi le
         * filigrane de ses badges, ses Koris et sa redirection — le message
         * les nomme, sinon elle découvrirait le changement sur un badge
         * déjà partagé. À un membre de l'équipe, le rôle et ce qu'il ouvre :
         * lui annoncer une offre serait lui parler d'un abonnement qu'il n'a
         * pas, et c'était exactement ce que faisait l'écran avant.
         */
        if (in_array($role, ROLES_INTERNES, true)) {
            $peut = [];
            foreach (ROLES_DROITS[$role] ?? [] as $d) {
                $peut[] = mb_strtolower(DROITS[$d] ?? $d);
            }
            notifier($id, 'compte', 'Votre rôle est maintenant ' . role_libelle($role),
                role_aide($role) . ($peut ? "\n\nCe rôle ouvre : " . implode(', ', $peut) . '.' : ''),
                accueil_de(['role' => $role]));
        } else {
            $f = FORMULES[$formule];
            $corps = 'Rôle : ' . role_libelle($role) . '. Offre : ' . formule_libelle($formule) . ".\n\n"
                . 'Elle couvre ' . ($f['campagnes'] < 0 ? 'un nombre illimité de campagnes' : $f['campagnes'] . ' campagne(s) active(s)')
                . ' et ' . ($f['telechargements'] < 0 ? 'des téléchargements sans limite' : $f['telechargements'] . ' téléchargements par mois')
                . '. Le filigrane Wakabi ' . ($f['sans_filigrane'] ? 'ne figure plus' : 'figure') . ' sur vos badges, '
                . 'les Koris sont ' . ($f['koris'] ? 'crédités' : 'désactivés') . ' au scan, et la redirection après '
                . 'téléchargement est ' . ($f['redirection'] ? 'active' : 'indisponible') . '. '
                . 'Le détail complet est sur votre tableau de bord.';
            notifier($id, 'compte',
                $change_offre
                    ? 'Votre offre est maintenant ' . formule_libelle($formule)
                    : 'Votre rôle est maintenant ' . role_libelle($role),
                $corps, accueil_de(['role' => $role]));
        }
    } else {
        if ($vise['role'] === 'super_admin' && !$vise['suspendu'] && compte_super_admins() <= 1) {
            rediriger($retour . '&err=' . urlencode(
                'C’est le dernier super-administrateur : le suspendre fermerait la porte à tout le monde.'));
        }
        db()->prepare('UPDATE utilisateurs SET suspendu = 1 - suspendu WHERE id = ?')->execute([$id]);
        journal_ecrire($u, 'compte.suspendu', 'compte', $id, (string) $vise['nom'],
            ((int) $vise['suspendu']) ? 'Réactivé' : 'Suspendu');
    }
    rediriger($retour . '&ok=' . urlencode('Compte mis à jour.'));
}

/* ---------------- renvoyer le lien de confirmation ---------------- */

/**
 * Le lien de confirmation, renvoyé par l'équipe.
 *
 * Le bouton « renvoyer » du titulaire ne suffit pas : il vit sur son
 * tableau de bord, et quelqu'un dont l'adresse n'est pas confirmée est
 * précisément quelqu'un qui n'a peut-être jamais réussi à s'y rendre. Sans
 * ce levier, l'équipe voyait « adresse non confirmée » sur une ligne sans
 * pouvoir rien y faire — et le seul bouton à portée était celui du rôle.
 *
 * Le compteur est celui du compte VISÉ, pas du nôtre : cliquer six fois
 * sur six comptes différents est un geste de ménage légitime ; cliquer six
 * fois sur le même est du harcèlement d'une boîte de réception.
 */
if ($page === 'verif-renvoyer') {
    verifier_csrf();
    $id = (string) ($_POST['id'] ?? '');
    $retour = ($_POST['retour'] ?? '') === 'fiche'
        ? '?p=organisateur&id=' . rawurlencode($id)
        : '?p=comptes';
    $vise = utilisateur_par_id($id);

    $refus = match (true) {
        !$vise => 'Compte introuvable.',
        interne($vise) && !droit($u, 'comptes_internes') =>
            'Un compte de l’équipe ne se modifie que par un super-administrateur.',
        email_verifie($vise) => 'L’adresse de ce compte est déjà confirmée.',
        !verification_exigee() =>
            'Le transport e-mail n’est pas réglé : aucun lien ne peut partir.',
        debit_depasse('verif|' . $id) =>
            'Un lien vient déjà de partir vers cette adresse. Réessayez dans '
            . FENETRE_MINUTES . ' minutes — et faites-lui regarder ses indésirables.',
        default => null,
    };
    if ($refus !== null) {
        rediriger($retour . '&err=' . urlencode($refus));
    }

    debit_noter('verif|' . $id);
    $envoi = envoyer_verification($vise);
    rediriger($retour . ($envoi['ok'] ? '&ok=' : '&err=') . urlencode($envoi['ok']
        ? 'Lien de confirmation renvoyé à ' . $vise['email'] . '.'
        : $envoi['message']));
}

/* ---------------- lever la double authentification ---------------- */

/**
 * Le téléphone perdu, et la seule issue.
 *
 * Sans ce levier, un membre de l'équipe qui change de téléphone sans
 * transférer son application est enfermé dehors définitivement, et la
 * seule réparation passe par la base de données. Réservé à qui gère les
 * comptes de l'équipe — c'est-à-dire au super-administrateur.
 */
if ($page === 'otp-lever') {
    verifier_csrf();
    exiger_droit('comptes_internes');
    $id = (string) ($_POST['id'] ?? '');
    $vise = utilisateur_par_id($id);
    if (!$vise) {
        rediriger('?p=comptes&err=' . urlencode('Compte introuvable.'));
    }
    db()->prepare('UPDATE utilisateurs SET otp_secret = NULL, otp_actif = 0 WHERE id = ?')
        ->execute([$id]);
    journal_ecrire($u, 'compte.role', 'compte', $id, (string) $vise['nom'],
        'Double authentification levée par un super-administrateur');
    notifier($id, 'compte', 'Votre double authentification a été retirée',
        'Un super-administrateur l’a levée — sans doute à votre demande. Remettez-la en place '
        . 'depuis votre profil dès que possible.', '?p=profil');
    rediriger('?p=organisateur&id=' . rawurlencode($id) . '&ok='
        . urlencode('Double authentification levée. Prévenez la personne de la remettre.'));
}

/* ---------------- enregistrer un paiement ---------------- */

/**
 * Un paiement encaissé ailleurs, consigné ici.
 *
 * Le logiciel n'encaisse pas : l'équipe reçoit un Mobile Money, un
 * virement ou des espèces, puis vient le noter. Ce geste-là fait trois
 * choses d'un coup — il repousse l'échéance, il émet une facture que
 * l'organisateur peut présenter à sa comptabilité, et il laisse une trace
 * au journal. Les trois se faisaient de mémoire, c'est-à-dire pas.
 */
if ($page === 'paiement') {
    verifier_csrf();
    $id = (string) ($_POST['id'] ?? '');
    $vise = utilisateur_par_id($id);
    $retour = '?p=organisateur&id=' . rawurlencode($id);
    if (!$vise) {
        rediriger('?p=comptes&err=' . urlencode('Compte introuvable.'));
    }
    if (!abonnement_suivi($vise)) {
        rediriger($retour . '&err=' . urlencode(
            'Ce compte est sur une offre gratuite : il n’y a pas d’échéance à repousser.'));
    }

    $jours = max(1, min(730, (int) ($_POST['jours'] ?? ABONNEMENT_JOURS)));
    $montant = max(0, min(10000000, (int) ($_POST['montant'] ?? FORMULES[$vise['formule']]['prix'])));
    $debut = maintenant();
    $fin = echeance_prolonger($vise, $jours);
    facture_emettre($vise, $debut, $fin, $u, $montant);

    journal_ecrire($u, 'abonnement.paye', 'compte', $id, (string) $vise['nom'],
        number_format($montant, 0, ',', ' ') . ' F, ' . $jours . ' jours, jusqu’au ' . date_fr($fin));
    notifier($id, 'compte', 'Votre abonnement est prolongé',
        'L’offre ' . formule_libelle($vise['formule']) . ' court désormais jusqu’au '
        . date_fr($fin) . '. La facture est disponible depuis votre profil.', '?p=profil');

    rediriger($retour . '&ok=' . urlencode(
        'Paiement enregistré. Échéance repoussée au ' . date_fr($fin) . ', facture émise.'));
}

/* ---------------- la soupape de téléchargements ---------------- */

if ($page === 'bonus') {
    verifier_csrf();
    $id = (string) ($_POST['id'] ?? '');
    $bonus = max(0, min(100000, (int) ($_POST['bonus'] ?? 0)));
    $avant = (int) (utilisateur_par_id($id)['bonus_telechargements'] ?? 0);
    db()->prepare('UPDATE utilisateurs SET bonus_telechargements = ? WHERE id = ?')->execute([$bonus, $id]);

    // Prévenir seulement si l'on DONNE : reprendre un bonus n'est pas une
    // bonne nouvelle à annoncer par notification automatique.
    if ($bonus > $avant) {
        notifier($id, 'compte', 'Des téléchargements vous ont été accordés',
            ($bonus - $avant) . ' téléchargements s’ajoutent à votre offre pour ce mois-ci.',
            '?p=partenaire');
    }
    rediriger('?p=organisateur&id=' . rawurlencode($id) . '&ok='
        . urlencode($bonus > 0 ? "Soupape réglée à $bonus téléchargements." : 'Soupape retirée.'));
}

/* ---------------- la note interne ---------------- */

if ($page === 'note-compte') {
    verifier_csrf();
    $id = (string) ($_POST['id'] ?? '');
    db()->prepare('UPDATE utilisateurs SET note_equipe = ? WHERE id = ?')
        ->execute([mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 4000) ?: null, $id]);
    rediriger('?p=organisateur&id=' . rawurlencode($id) . '&ok=' . urlencode('Note enregistrée.'));
}

/* ---------------- la fiche d'un compte ---------------- */

if ($page === 'organisateur') {
    $fiche = fiche_compte((string) ($_GET['id'] ?? ''));
    if (!$fiche) {
        rediriger('?p=comptes&err=' . urlencode('Ce compte n’existe pas.'));
    }
    vue('organisateur', ['titre' => $fiche['compte']['nom'], 'fiche' => $fiche]);
}

vue('comptes', ['titre' => 'Comptes'] + ecran_comptes());
