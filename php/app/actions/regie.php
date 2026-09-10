<?php
/**
 * La régie, côté écran : rédiger, soumettre, relire, envoyer.
 *
 * Un seul fichier pour les deux rôles, parce que c'est le même objet vu de
 * deux côtés. Ce qui change tient dans deux lignes : ce qu'on a le droit de
 * viser, et ce qu'on a le droit de décider.
 */
$u = exiger_droit('regie');
$equipe = droit($u, 'valider');

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
                 *
                 * La portée se compte sur TOUS les canaux, le quota sur le
                 * seul e-mail. Confondre les deux refuserait une campagne
                 * Telegram faute de destinataire e-mail, ou imputerait une
                 * publication de chaîne au quota mensuel d'un client
                 * qu'elle ne coûte rien.
                 */
                $portee = regie_portee($c, $auteur);
                $n = (int) $portee['destinations'];
                if ($n === 0) {
                    /**
                     * Dire la VRAIE raison, pas la plus probable.
                     *
                     * « Personne n'a de compte » et « personne n'a confirmé
                     * son adresse » demandent deux gestes opposés : chercher
                     * une autre cible, ou relancer les confirmations. Un
                     * message qui se trompe de cause fait perdre l'après-midi.
                     */
                    $ecartes = 0;
                    regie_destinataires($c, $auteur, $ecartes);
                    throw new RuntimeException($ecartes > 0
                        ? sprintf(
                            'Cette campagne ne toucherait personne : les %d adresse(s) de cette cible '
                            . 'n’ont jamais été confirmées, et une adresse non confirmée ne reçoit pas '
                            . 'de campagne : c’est ce qui protège la délivrabilité de tous vos envois. '
                            . 'Une liste de votre carnet, elle, n’est pas soumise à cette règle.',
                            $ecartes)
                        : 'Cette campagne ne toucherait personne. Vérifiez la cible : peut-être '
                          . 'qu’aucun de vos invités n’a encore de compte, ou que la liste est vide.');
                }
                $q = quota_emails($auteur, (int) $portee['email']);
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
                    '« ' . $c['sujet'] .' » de ' . $auteur['nom'] . ' · ' . $n . ' destinataire(s).',
                    '?p=regie-campagne&id=' . $c['id']);
                rediriger('?p=regie&ok=' . rawurlencode(
                    'Soumise à la régie : ' . $n . ' destinataire(s). Réponse sous 24 h ouvrées.'));

            case 'approuver':
                exiger_droit('valider');
                $q = quota_emails($auteur, (int) regie_portee($c, $auteur)['email']);
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
                exiger_droit('valider');
                campagne_email_transition((string) $c['id'], $quoi === 'refuser' ? 'refuse' : 'corrections', $u, $motif);
                notifier((string) $c['auteur_id'], 'regie',
                    $quoi === 'refuser' ? 'Votre campagne e-mail est refusée' : 'Votre campagne e-mail demande une correction',
                    $motif, '?p=regie-campagne&id=' . $c['id']);
                rediriger('?p=regie&ok=' . rawurlencode('Décision enregistrée, l’auteur est prévenu.'));

            case 'envoyer':
                exiger_droit('valider');
                if ($c['statut'] === 'prete') {
                    campagne_email_transition((string) $c['id'], 'envoi', $u);
                }
                $r = regie_envoyer_lot((string) $c['id']);
                if ($r['envoyes'] ?? 0) {
                    journal_ecrire($u, 'campagne.envoyee', 'campagne', (string) $c['id'],
                        (string) $c['sujet'], $r['envoyes'] . ' message(s) remis');
                }
                rediriger('?p=regie-campagne&id=' . rawurlencode((string) $c['id'])
                    . ($r['envoyes'] || $r['fini'] ? '&ok=' : '&err=') . rawurlencode($r['message']));

            /**
             * Relancer, archiver : deux gestes, et un seul interdit.
             *
             * Ils restent ouverts à l'auteur de la campagne, et non à la
             * seule équipe : le message a DÉJÀ été relu et approuvé, une
             * relance ne fait que remettre en file ce qui était prévu. En
             * revanche, une destination morte ne se relance jamais — c'est
             * la réputation du domaine de tout le monde qui est en jeu, et
             * ce n'est pas un arbitrage qu'on laisse à un bouton.
             */
            case 'relancer':
                $n = regie_relancer((string) $c['id']);
                rediriger('?p=regie-campagne&id=' . rawurlencode((string) $c['id'])
                    . ($n ? '&ok=' . rawurlencode($n . ' ligne(s) remise(s) en file. L’envoi reprend au prochain lot.')
                          : '&err=' . rawurlencode('Aucun de ces échecs ne se reprend : ce sont des refus définitifs.')));

            case 'relancer-un':
                $n = regie_relancer((string) $c['id'], [(string) ($_POST['envoi'] ?? '')]);
                rediriger('?p=regie-campagne&id=' . rawurlencode((string) $c['id'])
                    . ($n ? '&ok=' . rawurlencode('Remise en file. Elle repartira au prochain lot.')
                          : '&err=' . rawurlencode('Cette ligne ne se relance pas : la destination est morte.')));

            case 'archiver':
                $ok = regie_archiver((string) $c['id'], (string) ($_POST['envoi'] ?? ''));
                rediriger('?p=regie-campagne&id=' . rawurlencode((string) $c['id'])
                    . ($ok ? '&ok=' . rawurlencode('Archivée. Cette destination ne sera plus servie, ici ni ailleurs.')
                           : '&err=' . rawurlencode('Ligne introuvable, ou déjà traitée.')));

            case 'supprimer':
                journal_ecrire($u, 'campagne.supprimee', 'campagne', (string) $c['id'],
                    (string) $c['sujet'], (int) $c['envoyes'] . ' message(s) déjà partis');
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

    /**
     * Le carnet visé est celui de l'AUTEUR, pas du rédacteur.
     *
     * Un membre de l'équipe qui corrige la campagne d'un organisateur doit
     * voir les listes de cet organisateur : c'est son quota qui sera
     * débité et son nom qui signera. Prendre les listes de l'équipe
     * enverrait la campagne d'un client à la base d'un autre.
     */
    $proprio = (string) ($c['auteur_id'] ?? $u['id']);
    $mes_listes = carnet_listes($proprio);

    $valeurs = [
        'sujet' => $c['sujet'] ?? '',
        'titre' => $c['titre'] ?? '',
        'corps' => $c['corps'] ?? '',
        'lien' => $c['lien'] ?? '',
        'lien_libelle' => $c['lien_libelle'] ?? '',
        'cible' => $c['cible'] ?? array_key_first($cibles),
        'liste' => $c['liste'] ?? '',
        'liste_id' => $c['liste_id'] ?? '',
        'canaux' => $c['canaux'] ?? '',
        'planifie_le' => $c['planifie_le'] ?? '',
        'decor_id' => $c['decor_id'] ?? '',
        'rappel' => $c['rappel'] ?? '',
    ];

    /**
     * Les canaux qu'on peut cocher : ceux de ce compte, et les deux qui
     * sont déjà en service.
     *
     * Construits ici et pas dans la vue : c'est le seul endroit qui sait à
     * qui appartient quoi, et une vue qui interroge la base finit par le
     * faire trente fois.
     */
    $mes_canaux = canaux_de($proprio);

    /**
     * Chaque carte porte trois chiffres différents, et il faut les trois.
     *
     * `n` : ce que le canal touche — des personnes. `envois` : ce que cela
     * coûte en messages, et ce n'est pas le même nombre. Une chaîne de
     * 4 210 lecteurs, c'est UN envoi ; annoncer 4 210 envois flatterait la
     * portée d'un facteur mille et ferait croire à un quota consommé.
     * `ecartes` : ce qu'on laisse volontairement de côté, parce qu'un
     * chiffre qui baisse sans explication passe pour une panne.
     */
    /**
     * La portée de chaque cible, calculée d'avance pour toutes.
     *
     * L'e-mail et les notifications suivent la cible choisie plus bas dans
     * la page : leur nombre change quand on change de cible. Le calculer
     * au chargement, pour toutes les cibles à la fois, permet à l'écran de
     * le mettre à jour sans aller-retour — et surtout d'afficher la portée
     * AVANT de cocher, plutôt qu'après avoir envoyé.
     */
    $portees = [];
    foreach ($cibles as $cle => $_lib) {
        if ($cle === 'liste') {
            continue;   // une liste se compte par liste, juste après
        }
        $p = regie_compte_cible($cle, ['id' => $proprio]);
        $portees[$cle] = ['n' => $p['n'], 'ecartes' => $p['ecartes'],
                          'push' => push_disponible() ? push_combien($cle, $proprio) : 0];
    }
    foreach ($mes_listes as $li) {
        $p = regie_compte_cible('liste', ['id' => $proprio], (string) $li['id']);
        $portees['liste:' . $li['id']] = ['n' => $p['n'], 'ecartes' => 0,
                                          'push' => push_disponible() ? push_combien('liste', $proprio) : 0];
    }

    $portee_ici = $portees[$valeurs['cible'] === 'liste'
        ? 'liste:' . $valeurs['liste_id'] : $valeurs['cible']] ?? ['n' => 0, 'ecartes' => 0, 'push' => 0];

    $choix_canaux = [['cle' => 'email', 'genre' => 'email', 'libelle' => 'E-mail',
                      'sous' => 'Selon la cible choisie', 'unite' => 'adresses confirmées',
                      'aide' => 'Relu par l’équipe avant de partir.', 'payant' => false,
                      'n' => $portee_ici['n'], 'envois' => $portee_ici['n'],
                      'ecartes' => $portee_ici['ecartes'], 'suit' => 'n']];
    if (push_disponible()) {
        $choix_canaux[] = ['cle' => 'push', 'genre' => 'push', 'libelle' => 'Notifications',
                           // Comme l'e-mail, ce nombre suit la cible : le dire
                           // évite de lire un zéro comme une panne alors que
                           // c'est la portée de CETTE cible-là.
                           'sous' => 'Selon la cible choisie', 'unite' => 'appareil(s) abonné(s)',
                           'aide' => 'Les invités de vos campagnes, sur leur navigateur.',
                           'payant' => false, 'n' => $portee_ici['push'],
                           'envois' => 1, 'ecartes' => 0, 'suit' => 'push',
                           'note' => 'Ceux qui ont accepté les notifications sous leur badge. '
                                   . 'Votre texte sera coupé à ' . PUSH_APERCU . ' caractères.',
                           'ton' => 'attention'];
    }
    foreach ($mes_canaux as $mc) {
        if ($mc['statut'] !== 'branche') {
            continue;
        }
        $payant = !empty(CANAUX_GENRES[$mc['genre']]['payant']);
        foreach ($mc['destinations'] as $d) {
            $choix_canaux[] = [
                'cle' => 'd:' . $d['id'],
                'genre' => (string) $mc['genre'],
                'libelle' => (string) $d['nom'],
                'sous' => CANAUX_DESTINATIONS[$d['genre']] ?? '',
                'unite' => 'lecteurs · 1 envoi',
                'aide' => 'Une publication, quel que soit le nombre de lecteurs.',
                'note' => 'Une publication, quel que soit le nombre d’abonnés. Une place de quota.',
                'payant' => $payant,
                'n' => (int) $d['abonnes'],
                'envois' => 1,
                'ecartes' => 0,
            ];
        }
        $choix_canaux[] = [
            'cle' => 'c:' . $mc['id'],
            'genre' => (string) $mc['genre'],
            'libelle' => (string) $mc['nom'],
            'sous' => 'Tête-à-tête',
            'unite' => $mc['genre'] === 'telegram' ? 'personnes' : 'numéros avec accord',
            'aide' => $mc['genre'] === 'telegram'
                ? 'Les personnes qui ont écrit au bot.'
                : 'Les numéros qui ont donné leur accord. Facturé par Meta, au message.',
            'note' => $mc['genre'] === 'telegram'
                ? 'Celles qui ont écrit au bot au moins une fois.'
                : 'Demande un modèle approuvé par Meta, et se facture au message.',
            'ton' => $mc['genre'] === 'whatsapp' ? 'stop' : '',
            'payant' => $payant,
            'n' => (int) $mc['abonnes'],
            'envois' => (int) $mc['abonnes'],
            'ecartes' => 0,
        ];
    }

    // Arrivé du carnet par « Écrire à cette liste » : la cible est déjà
    // choisie. La redemander ferait recommencer un geste déjà fait.
    if (!$c && ($_GET['l'] ?? '') !== '' && carnet_liste_de((string) $_GET['l'], $proprio)) {
        $valeurs['cible'] = 'liste';
        $valeurs['liste_id'] = (string) $_GET['l'];
    }

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
            $valeurs['cible'] === 'liste' && $valeurs['liste'] === ''
                && (string) ($_POST['liste_id'] ?? '') === 'nouvelle' =>
                'Une liste neuve a besoin d’adresses : collez-les ci-dessous.',
            $valeurs['cible'] === 'liste' && $valeurs['liste'] === ''
                && (string) ($_POST['liste_id'] ?? '') === '' =>
                'Choisissez une liste de votre carnet, ou collez les adresses.',
            default => null,
        };

        /**
         * Le collage devient une liste du carnet, ici et tout de suite.
         *
         * C'est le point où « les listes importées sont automatiquement
         * sauvegardées » cesse d'être une promesse : la campagne ne porte
         * plus les adresses, elle DÉSIGNE une liste. On peut ensuite en
         * sortir quelqu'un, corriger un nom, archiver une adresse morte —
         * et la campagne suivante repart de la même liste, corrigée, au
         * lieu d'un nouveau copier-coller depuis le même tableur.
         */
        if ($erreur === null && $valeurs['cible'] === 'liste') {
            try {
                $choix = (string) ($_POST['liste_id'] ?? '');
                if ($choix === '' || $choix === 'nouvelle') {
                    $liste_id = carnet_liste_poser($proprio,
                        trim((string) ($_POST['nouveau_nom'] ?? '')) ?: $valeurs['sujet']);
                } else {
                    $l = carnet_liste_de($choix, $proprio);
                    if (!$l) {
                        throw new RuntimeException('Cette liste n’existe pas, ou n’est pas la vôtre.');
                    }
                    $liste_id = (string) $l['id'];
                }
                if ($valeurs['liste'] !== '') {
                    $bilan = carnet_importer($proprio, $liste_id, $valeurs['liste']);
                    if ($bilan['total'] === 0) {
                        throw new RuntimeException(
                            'Aucune adresse lisible dans ce collage. Une par ligne, '
                            . 'ou « Nom <adresse> ».'
                        );
                    }
                }
                $valeurs['liste_id'] = $liste_id;
                // Le collage a fait son travail : il ne reste pas en double
                // dans la campagne, où il vieillirait sans qu'on le corrige.
                $valeurs['liste'] = '';
                $mes_listes = carnet_listes($proprio);
            } catch (Throwable $e) {
                $erreur = $e->getMessage();
            }
        }

        /**
         * Les canaux cochés, traduits en cibles.
         *
         * On ne garde que ce qui existe ENCORE et qui appartient à ce
         * compte : une case cochée est une saisie, et une saisie se
         * vérifie. Sans e-mail ni aucun canal, on retombe sur l'e-mail —
         * c'est ce que faisait la régie depuis toujours.
         */
        $coches = (array) ($_POST['canaux'] ?? []);
        $connus = array_column($choix_canaux, 'cle');
        $cibles_canal = [];
        foreach ($coches as $cle) {
            $cle = (string) $cle;
            if (!in_array($cle, $connus, true)) {
                continue;
            }
            if ($cle === 'email' || $cle === 'push') {
                $cibles_canal[] = ['canal' => $cle];
            } elseif (str_starts_with($cle, 'd:') && ($d = destination_par_id(substr($cle, 2)))) {
                $canal = canal_par_id((string) $d['canal_id']);
                if ($canal && (string) $canal['proprietaire_id'] === $proprio) {
                    $cibles_canal[] = ['canal' => (string) $canal['genre'],
                                       'canal_id' => (string) $canal['id'],
                                       'destination_id' => (string) $d['id']];
                }
            } elseif (str_starts_with($cle, 'c:') && ($canal = canal_par_id(substr($cle, 2)))) {
                if ((string) $canal['proprietaire_id'] === $proprio) {
                    $entree = ['canal' => (string) $canal['genre'], 'canal_id' => (string) $canal['id']];
                    if ($canal['genre'] === 'whatsapp') {
                        $entree['modele'] = trim((string) ($_POST['modele_whatsapp'] ?? ''));
                    }
                    $cibles_canal[] = $entree;
                }
            }
        }
        if (!$cibles_canal) {
            $cibles_canal[] = ['canal' => 'email'];
        }
        $valeurs['canaux'] = json_encode($cibles_canal, JSON_UNESCAPED_UNICODE);

        /**
         * L'heure d'envoi, si on en veut une.
         *
         * Saisie dans le fuseau du navigateur, rangée en UTC comme tout le
         * reste de la base : sans cela un rappel « 19 h » partirait à 19 h
         * du serveur, qui n'est pas celui de Lomé.
         */
        $quand = trim((string) ($_POST['planifie_le'] ?? ''));
        if ($quand !== '' && preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $quand)) {
            $decalage = (int) ($_POST['decalage'] ?? 0);   // minutes, comme le donne le navigateur
            $t = strtotime($quand . ':00 UTC');
            $valeurs['planifie_le'] = $t !== false
                ? gmdate('Y-m-d\TH:i:s\Z', $t + $decalage * 60)
                : null;
        } else {
            $valeurs['planifie_le'] = null;
        }

        /**
         * WhatsApp sans modèle ne partira pas : autant le dire ici.
         *
         * Meta refuse le texte libre hors des vingt-quatre heures qui
         * suivent un message du destinataire, c'est-à-dire toujours pour un
         * rappel. Laisser enregistrer donnerait une campagne qui échoue
         * ligne par ligne, le soir de l'événement.
         */
        foreach ($cibles_canal as $cc) {
            if (($cc['canal'] ?? '') === 'whatsapp' && ($cc['modele'] ?? '') === '') {
                $erreur = 'WhatsApp demande le nom d’un modèle approuvé par Meta : sans lui, '
                        . 'aucun message ne peut partir hors de la fenêtre de vingt-quatre heures.';
            }
        }

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
        'titre' => $c ? 'Modifier le message' : 'Nouveau message',
        'valeurs' => $valeurs,
        'existante' => $c,
        'cibles' => $cibles,
        'equipe' => $equipe,
        'listes' => $mes_listes,
        'choix_canaux' => $choix_canaux,
        'portees' => $portees,
        'apercu_push' => PUSH_APERCU,
        /**
         * Les cases à recocher : on refait le chemin inverse, de la cible
         * enregistrée vers la clé du formulaire. Sans quoi rouvrir une
         * campagne décocherait tout ce qu'on avait choisi.
         */
        'canaux_coches' => array_map(
            static function (array $cc): string {
                if (!empty($cc['destination_id'])) {
                    return 'd:' . $cc['destination_id'];
                }
                if (!empty($cc['canal_id'])) {
                    return 'c:' . $cc['canal_id'];
                }
                return (string) ($cc['canal'] ?? '');
            },
            json_decode((string) $valeurs['canaux'], true) ?: [['canal' => 'email']]
        ),
        'modele_whatsapp' => (function (string $json): string {
            foreach (json_decode($json, true) ?: [] as $cc) {
                if (($cc['canal'] ?? '') === 'whatsapp' && !empty($cc['modele'])) {
                    return (string) $cc['modele'];
                }
            }
            return '';
        })((string) $valeurs['canaux']),
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
        'liste' => ($c['liste_id'] ?? '') !== '' ? carnet_liste((string) $c['liste_id']) : null,
        'auteur' => $auteur,
        'equipe' => $equipe,
        // Le compte est RECALCULÉ tant que la liste n'est pas figée : la
        // cible d'une campagne en brouillon bouge avec la base.
        'vise' => in_array($c['statut'], ['brouillon', 'en_relecture', 'corrections', 'refuse'], true)
            ? regie_compter($c, $auteur ?? $u)
            : (int) $c['destinataires'],
        'quota' => quota_emails($auteur ?? $u),
        // Les canaux et les échecs : deux questions qu'on se pose sur cet
        // écran et nulle part ailleurs, donc résolues ici plutôt que dans
        // la vue, qui n'a pas à interroger la base.
        'canaux' => regie_canaux($c),
        'echecs' => in_array($c['statut'], ['envoi', 'envoye'], true)
            ? regie_echecs((string) $c['id'])
            : [],
        'message' => $message,
        'erreur' => $alerte,
    ]);
}

/* ---------------- les échecs, en tableur ---------------- */

if ($page === 'regie-echecs-export') {
    $c = $mienne(campagne_email((string) ($_GET['id'] ?? '')));

    $nom = 'echecs-' . preg_replace('/[^a-z0-9]+/i', '-', mb_strtolower((string) $c['sujet']))
         . '-' . gmdate('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nom . '"');
    $sortie = fopen('php://output', 'w');
    // Le BOM, pour qu'Excel ne massacre pas les accents.
    fwrite($sortie, "\xEF\xBB\xBF");
    fputcsv($sortie, ['Destinataire', 'Nom', 'Canal', 'État', 'Code', 'Essais',
                      'Ce que le serveur a répondu', 'Se relance']);
    foreach (regie_echecs((string) $c['id']) as $e) {
        fputcsv($sortie, [
            $e['qui'], $e['nom'] ?? '', $e['genre_canal'],
            REGIE_ENVOIS_STATUTS[$e['statut']] ?? $e['statut'],
            $e['code'], (int) $e['tentatives'], (string) ($e['message'] ?? ''),
            match (true) {
                $e['statut'] !== 'echec' => 'sans objet',
                (bool) $e['reprenable'] => 'oui',
                (bool) $e['mortel'] => 'non, destination morte',
                default => 'à la main',
            },
        ]);
    }
    fclose($sortie);
    exit;
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
