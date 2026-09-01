<?php
/**
 * La régie, côté écran : rédiger, soumettre, relire, envoyer.
 *
 * Un seul fichier pour les deux rôles, parce que c'est le même objet vu de
 * deux côtés. Ce qui change tient dans deux lignes : ce qu'on a le droit de
 * viser, et ce qu'on a le droit de décider.
 */
$u = exiger_role('partenaire', 'equipe');
$equipe = $u['role'] === 'equipe';

if (!$equipe && !capacite($u, 'regie')) {
    vue('offre-requise', [
        'titre' => 'Régie e-mail',
        'quoi' => OFFRE_LIGNES['regie'][0],
        'aide' => OFFRE_LIGNES['regie'][2],
        'debloque' => offre_qui_debloque('regie'),
    ]);
}

$erreur = null;
$message = $_GET['ok'] ?? null;
$alerte = $_GET['err'] ?? null;

/** La campagne visée, et le droit d'y toucher. */
$mienne = function (?array $c) use ($u, $equipe): array {
    if (!$c) {
        rediriger('?p=regie&err=' . rawurlencode('Campagne introuvable.'));
    }
    if (!$equipe && $c['auteur_id'] !== $u['id']) {
        rediriger('?p=regie&err=' . rawurlencode('Cette campagne ne vous appartient pas.'));
    }
    return $c;
};

/* ---------------- soumettre, décider, envoyer ---------------- */

if ($page === 'regie-action') {
    verifier_csrf();
    $c = $mienne(campagne_email((string) ($_POST['id'] ?? '')));
    $quoi = (string) ($_POST['quoi'] ?? '');
    $motif = trim((string) ($_POST['motif'] ?? ''));
    $auteur = utilisateur_par_id((string) $c['auteur_id']) ?? $u;

    try {
        switch ($quoi) {
            case 'soumettre':
                /**
                 * Le quota est opposé ICI, à la soumission.
                 *
                 * C'est le dernier moment où l'on peut encore réduire la
                 * cible sans avoir dérangé personne. L'opposer à l'envoi
                 * ferait découvrir la limite après la relecture de
                 * l'équipe — donc après avoir fait travailler quelqu'un.
                 */
                $n = regie_compter($c, $auteur);
                if ($n === 0) {
                    throw new RuntimeException(
                        'Cette campagne ne toucherait personne. Vérifiez la cible : peut-être '
                        . 'qu’aucun de vos invités n’a encore de compte, ou que la liste est vide.'
                    );
                }
                $q = quota_emails($auteur, $n);
                if (!$q['ok']) {
                    throw new RuntimeException($q['message']);
                }
                campagne_email_transition((string) $c['id'], 'en_relecture', $u);
                if ($equipe) {
                    // L'équipe se relit elle-même : autant aller au bout.
                    campagne_email_transition((string) $c['id'], 'prete', $u);
                    regie_figer((string) $c['id'], $auteur);
                    rediriger('?p=regie-campagne&id=' . rawurlencode((string) $c['id']) . '&ok='
                        . rawurlencode('Campagne prête : ' . $n . ' destinataire(s).'));
                }
                notifier_equipe('regie', 'Une campagne e-mail attend la relecture',
                    '« ' . $c['sujet'] .' » de ' . $auteur['nom'] . ' — ' . $n . ' destinataire(s).',
                    '?p=regie-campagne&id=' . $c['id']);
                rediriger('?p=regie&ok=' . rawurlencode(
                    'Soumise à la régie : ' . $n . ' destinataire(s). Réponse sous 24 h ouvrées.'));

            case 'approuver':
                exiger_role('equipe');
                $q = quota_emails($auteur, regie_compter($c, $auteur));
                if (!$q['ok']) {
                    throw new RuntimeException('Refusé par le quota de l’auteur : ' . $q['message']);
                }
                campagne_email_transition((string) $c['id'], 'prete', $u);
                $n = regie_figer((string) $c['id'], $auteur);
                notifier((string) $c['auteur_id'], 'regie', 'Votre campagne e-mail est approuvée',
                    '« ' . $c['sujet'] . ' » partira vers ' . $n . ' destinataire(s).',
                    '?p=regie-campagne&id=' . $c['id']);
                rediriger('?p=regie-campagne&id=' . rawurlencode((string) $c['id']) . '&ok='
                    . rawurlencode('Approuvée. ' . $n . ' destinataire(s) en attente d’envoi.'));

            case 'corrections':
            case 'refuser':
                exiger_role('equipe');
                campagne_email_transition((string) $c['id'], $quoi === 'refuser' ? 'refuse' : 'corrections', $u, $motif);
                notifier((string) $c['auteur_id'], 'regie',
                    $quoi === 'refuser' ? 'Votre campagne e-mail est refusée' : 'Votre campagne e-mail demande une correction',
                    $motif, '?p=regie-campagne&id=' . $c['id']);
                rediriger('?p=regie&ok=' . rawurlencode('Décision enregistrée, l’auteur est prévenu.'));

            case 'envoyer':
                exiger_role('equipe');
                if ($c['statut'] === 'prete') {
                    campagne_email_transition((string) $c['id'], 'envoi', $u);
                }
                $r = regie_envoyer_lot((string) $c['id']);
                rediriger('?p=regie-campagne&id=' . rawurlencode((string) $c['id'])
                    . ($r['envoyes'] || $r['fini'] ? '&ok=' : '&err=') . rawurlencode($r['message']));

            case 'supprimer':
                campagne_email_supprimer((string) $c['id']);
                rediriger('?p=regie&ok=' . rawurlencode('Campagne supprimée.'));

            default:
                rediriger('?p=regie');
        }
    } catch (Throwable $e) {
        rediriger('?p=regie-campagne&id=' . rawurlencode((string) $c['id'])
            . '&err=' . rawurlencode($e->getMessage()));
    }
}

/* ---------------- écrire ---------------- */

if ($page === 'regie-ecrire') {
    $c = ($_GET['id'] ?? '') !== '' ? $mienne(campagne_email((string) $_GET['id'])) : null;
    $cibles = regie_cibles_de($u);

    $valeurs = [
        'sujet' => $c['sujet'] ?? '',
        'titre' => $c['titre'] ?? '',
        'corps' => $c['corps'] ?? '',
        'lien' => $c['lien'] ?? '',
        'lien_libelle' => $c['lien_libelle'] ?? '',
        'cible' => $c['cible'] ?? array_key_first($cibles),
        'liste' => $c['liste'] ?? '',
    ];

    /**
     * Une campagne partie ne se modifie plus.
     *
     * Le message est chez les gens : le corriger dans la base ne le
     * corrigerait nulle part, et ferait croire le contraire.
     */
    if ($c && in_array($c['statut'], ['envoi', 'envoye', 'prete'], true)) {
        rediriger('?p=regie-campagne&id=' . rawurlencode((string) $c['id']) . '&err='
            . rawurlencode('Une campagne approuvée ne se modifie plus.'));
    }

    if ($post) {
        verifier_csrf();
        foreach (['sujet', 'titre', 'corps', 'lien', 'lien_libelle', 'liste'] as $k) {
            $valeurs[$k] = trim((string) ($_POST[$k] ?? ''));
        }
        $valeurs['cible'] = (string) ($_POST['cible'] ?? '');

        $erreur = match (true) {
            !isset($cibles[$valeurs['cible']]) => 'Choisissez une cible parmi celles proposées.',
            $valeurs['sujet'] === '' => 'L’objet est ce que les gens lisent avant d’ouvrir. Il est obligatoire.',
            mb_strlen($valeurs['sujet']) > 120 => 'Cet objet est trop long : il sera coupé dans la boîte de réception.',
            $valeurs['titre'] === '' => 'Donnez un titre au message.',
            mb_strlen($valeurs['corps']) < 30 => 'Un message de moins de 30 caractères ne convaincra personne.',
            // Le même garde-fou que la redirection d'un décor : un message
            // signé Wakabi qui ouvre un site tiers, c'est notre nom qui sert
            // de caution. L'équipe, elle, écrit ce qu'elle veut.
            $valeurs['lien'] !== '' && !$equipe && !redirection_autorisee($valeurs['lien']) =>
                'Le lien doit mener vers ' . implode(' ou ', WAKABI_DOMAINES) . '.',
            $valeurs['lien'] !== '' && !filter_var($valeurs['lien'], FILTER_VALIDATE_URL) =>
                'Ce lien n’est pas une adresse valide.',
            $valeurs['cible'] === 'liste' && $valeurs['liste'] === '' =>
                'Collez les adresses, une par ligne.',
            default => null,
        };

        if ($erreur === null) {
            if ($c) {
                campagne_email_maj((string) $c['id'], $valeurs);
                $id = (string) $c['id'];
            } else {
                $id = campagne_email_creer($valeurs + ['auteur_id' => $u['id']]);
            }
            rediriger('?p=regie-campagne&id=' . rawurlencode($id) . '&ok='
                . rawurlencode('Enregistrée. Relisez-la, puis soumettez-la.'));
        }
    }

    vue('regie-ecrire', [
        'titre' => $c ? 'Modifier la campagne' : 'Nouvelle campagne e-mail',
        'valeurs' => $valeurs,
        'existante' => $c,
        'cibles' => $cibles,
        'equipe' => $equipe,
        'erreur' => $erreur,
    ]);
}

/* ---------------- une campagne ---------------- */

if ($page === 'regie-campagne') {
    $c = $mienne(campagne_email((string) ($_GET['id'] ?? '')));
    $auteur = utilisateur_par_id((string) $c['auteur_id']);

    vue('regie-campagne', [
        'titre' => $c['sujet'],
        'c' => $c,
        'auteur' => $auteur,
        'equipe' => $equipe,
        // Le compte est RECALCULÉ tant que la liste n'est pas figée : la
        // cible d'une campagne en brouillon bouge avec la base.
        'vise' => in_array($c['statut'], ['brouillon', 'en_relecture', 'corrections', 'refuse'], true)
            ? regie_compter($c, $auteur ?? $u)
            : (int) $c['destinataires'],
        'quota' => quota_emails($auteur ?? $u),
        'message' => $message,
        'erreur' => $alerte,
    ]);
}

/* ---------------- la liste ---------------- */

vue('regie', [
    'titre' => 'Régie e-mail',
    // La même clé que les sauvegardes : une seule à garder, une seule à
    // faire tourner si elle fuit.
    'url_cron' => base_url() . '/index.php?p=regie-cron&cle=' . cle_sauvegarde(),
    'liste' => $equipe ? campagnes_email_toutes() : campagnes_email_de((string) $u['id']),
    'equipe' => $equipe,
    'quota' => quota_emails($u),
    'branche' => courriel_branche(),
    'message' => $message,
    'erreur' => $alerte,
]);
