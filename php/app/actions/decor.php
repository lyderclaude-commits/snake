<?php
/** Création d'un décor par un partenaire, et soumission à la relecture. */

/**
 * Le Studio s'ouvre à qui n'a pas de compte, et à lui seul.
 *
 * Composer demandait d'être connecté, alors qu'on ne sait ce qu'on veut
 * qu'en voyant le badge se dessiner. Le mur est déplacé à la publication.
 *
 * Un compte SANS le droit `decors_siens` — un participant, un scanner —
 * garde l'ancien comportement : il est renvoyé chez lui. Le laisser
 * composer lui ferait traverser un mur qui ne peut rien pour lui, puisqu'il
 * a déjà le compte que ce mur propose de créer.
 */
$u = utilisateur_courant();
$anonyme = $u === null;
if (!$anonyme && !droit($u, 'decors_siens')) {
    rediriger(accueil_de($u));
}
if ($anonyme && $page !== 'nouveau') {
    // Modifier, confier, soumettre : tout cela désigne un décor qui
    // appartient à quelqu'un. Rien à y faire sans compte.
    rediriger('?p=connexion');
}

/**
 * D'où part ce décor : d'un fichier, ou de rien.
 *
 * L'étape « Le cadre » mélangeait les deux métiers. L'écran `?p=creer`
 * demande désormais lequel, et le Studio n'ouvre que la moitié qui sert.
 * Le défaut reste le Studio complet : c'est ce que voit l'équipe, qui
 * arrive par le catalogue et non par la vitrine.
 */
$depart = ($_GET['depart'] ?? $_POST['depart'] ?? '') === 'fichier' ? 'fichier' : 'studio';

/**
 * Le même formulaire sert à créer et à modifier.
 *
 * Un écran d'édition séparé divergerait du formulaire de création à la
 * première évolution : ils décrivent la même chose.
 */
$modifie = $page === 'modifier' ? decor_par_id((string) ($_GET['id'] ?? $_POST['id'] ?? '')) : null;
if ($page === 'modifier') {
    if (!$modifie) {
        rediriger(droit($u, 'decors_tous') ? '?p=catalogue&err=' . urlencode('Décor introuvable.') : '?p=partenaire');
    }
    // Un partenaire ne modifie que ses propres décors — ou ceux qu'on lui a
    // confiés — et pas après publication.
    if (!droit($u, 'decors_tous')) {
        if (!decor_accessible($u, $modifie)) {
            rediriger('?p=partenaire&err=' . urlencode('Ce décor ne vous appartient pas.'));
        }
        if (in_array($modifie['statut'], ['publie', 'en_relecture'], true)) {
            rediriger('?p=partenaire&err=' . urlencode(
                'Un décor publié ou en relecture ne se modifie plus. Demandez à l’équipe Wakabi.'
            ));
        }
    }
}

/* ---------------- confier cette campagne ---------------- */

if ($page === 'equipier') {
    verifier_csrf();
    $d = decor_par_id((string) ($_POST['id'] ?? ''));
    if (!$d) {
        rediriger('?p=partenaire&err=' . urlencode('Décor introuvable.'));
    }
    /**
     * Seul l'AUTEUR invite, jamais un équipier.
     *
     * Sans cette règle, une invitation en entraîne d'autres et l'auteur
     * perd de vue qui travaille sur sa campagne — ce qui est exactement
     * ce qu'on cherchait à lui rendre.
     */
    if ($d['auteur_id'] !== $u['id'] && !droit($u, 'decors_tous')) {
        rediriger('?p=partenaire&err=' . urlencode(
            'Seul l’auteur d’une campagne décide de qui y travaille.'));
    }
    $retour = '?p=modifier&id=' . rawurlencode((string) $d['id']);

    if (($_POST['quoi'] ?? '') === 'retirer') {
        equipier_retirer((string) $d['id'], (string) ($_POST['qui'] ?? ''));
        rediriger($retour . '&ok=' . urlencode('Accès retiré.'));
    }

    $email = trim((string) ($_POST['email'] ?? ''));
    $invite = utilisateur_par_email($email);
    if (!$invite) {
        rediriger($retour . '&err=' . urlencode(
            'Aucun compte à cette adresse. La personne doit d’abord créer un compte '
            . 'sur ' . base_url() . ', c’est gratuit.'));
    }
    if ($invite['id'] === $d['auteur_id']) {
        rediriger($retour . '&err=' . urlencode('C’est déjà la campagne de cette personne.'));
    }
    equipier_inviter((string) $d['id'], (string) $invite['id'], (string) $u['id']);
    notifier((string) $invite['id'], 'compte', 'On vous confie une campagne',
        $u['nom'] . ' vous a donné accès à « ' . $d['titre'] . ' ». Vous pouvez la modifier '
        . 'et la soumettre à la relecture, et rien d’autre sur son compte.',
        '?p=modifier&id=' . $d['id']);
    rediriger($retour . '&ok=' . urlencode(
        $invite['nom'] . ' peut maintenant travailler sur cette campagne, et sur elle seule.'));
}

/* ---------------- soumission ---------------- */

if ($page === 'soumettre') {
    verifier_csrf();
    $d = decor_par_id((string) ($_POST['id'] ?? ''));
    if (!$d) {
        rediriger('?p=partenaire&err=' . urlencode('Décor introuvable.'));
    }
    if (!droit($u, 'decors_tous') && !decor_accessible($u, $d)) {
        rediriger('?p=partenaire&err=' . urlencode('Ce décor ne vous appartient pas.'));
    }

    /**
     * L'adresse doit être confirmée avant de demander une relecture.
     *
     * Un décor part en file d'attente, l'équipe le relit, décide — et la
     * décision s'envoie par courriel. À une adresse dont personne n'a
     * vérifié qu'elle existe, tout ce travail tombe dans le vide.
     *
     * La garde ne s'applique QUE si l'on sait envoyer le lien : sans
     * transport réglé, exiger une confirmation reviendrait à fermer
     * l'application à clé. L'équipe, elle, n'y est jamais soumise — son
     * compte est créé par une autre personne de l'équipe.
     */
    if ($u['role'] === 'partenaire' && verification_exigee() && !email_verifie($u)) {
        rediriger('?p=partenaire&err=' . urlencode(
            'Confirmez d’abord votre adresse e-mail : c’est par là que part la décision de '
            . 'relecture. Le lien vous a été envoyé à ' . $u['email'] . ' ; vous pouvez le '
            . 'redemander depuis cette page.'
        ));
    }

    /**
     * Le quota de l'offre se joue ICI.
     *
     * Un brouillon ne coûte rien : personne ne le voit. Ce qui occupe une
     * place, c'est une campagne en ligne ou en route vers la relecture.
     * L'équipe n'a jamais de quota.
     */
    $max = quota($u, 'campagnes');
    if ($max >= 0 && campagnes_actives($u['id'], $d['id']) >= $max) {
        rediriger('?p=partenaire&err=' . urlencode(
            'Votre offre ' . formule_libelle($u['formule'] ?? null) . ' autorise '
            . $max . ' campagne' . ($max > 1 ? 's' : '') . ' à la fois, et elle'
            . ($max > 1 ? 's sont prises' : ' est prise') . '. Demandez à l’équipe d’archiver une '
            . 'campagne terminée, ou passez à l’offre supérieure.'
        ));
    }

    // Le pré-vol AVANT la file : un décor qui échoue n'y rejoint jamais,
    // ce qui permet de tenir l'engagement des 24 h.
    $rapport = prevol(json_lire($d['gabarit']), $d['cadre_url']);
    enregistrer_prevol($d['id'], $rapport);

    if (!$rapport['passe']) {
        $echecs = array_values(array_filter($rapport['controles'], fn($c) => $c['etat'] === 'echec'));
        rediriger('?p=partenaire&err=' . urlencode(
            'Le contrôle automatique a relevé des problèmes bloquants : '
            . implode(' ', array_column($echecs, 'message'))
        ));
    }

    try {
        decor_transition($d['id'], 'en_relecture', $u);
    } catch (RuntimeException $e) {
        rediriger('?p=partenaire&err=' . urlencode($e->getMessage()));
    }

    // La file d'attente ne se surveille pas toute seule.
    notifier_equipe('relecture', 'Un décor attend votre relecture', $d['titre'], '?p=relecture');
    rediriger('?p=partenaire&ok=' . urlencode('Soumis : réponse sous 24 h ouvrées.'));
}

/* ---------------- création ---------------- */

$erreur = null;

/**
 * Les clés d'apparence voyagent dans $valeurs comme les autres champs.
 *
 * Elles sont initialisées avec les valeurs de départ de la disposition : le
 * formulaire montre donc dès l'ouverture ce que le gabarit vaut réellement,
 * et non des curseurs à zéro.
 */
const CLES_APPARENCE = [
    'texte_couleur', 'texte_align', 'bloc_x', 'bloc_y', 'bloc_w',
    'accroche_taille', 'champ_taille', 'qr_actif', 'qr_position', 'qr_taille', 'filigrane_position',
    'format', 'fond', 'photo_x', 'photo_y', 'photo_w', 'photo_h', 'photo_forme',
];

$valeurs = [
    'titre' => '', 'sous_titre' => '', 'ville' => 'lome', 'rubrique' => 'campagne',
    'disposition' => 'bandeau', 'accroche' => 'J’Y SERAI', 'champ_libelle' => 'Ton prénom',
    'champ_valeur' => 'Kossi', 'redirection' => 'https://wakabileguide.com/',
    'redirection_libelle' => '', 'legende' => '', 'expire_le' => '', 'evenement_le' => '', 'cadre_url' => '',
    'cadre_fourni' => '',
    /**
     * Les calques libres voyagent en JSON dans un champ caché.
     *
     * Ils sont de longueur variable — zéro à douze objets, chacun avec neuf
     * propriétés. Un formulaire HTML ne sait pas décrire cela sans une nuée
     * de champs indexés qu'il faudrait renuméroter à chaque suppression.
     * Le champ caché est réécrit par le panneau de calques à chaque geste,
     * et relu tel quel côté serveur, où il est nettoyé comme une saisie.
     */
    'calques' => '[]',
    /** Les déclinaisons, en JSON pour la même raison que les calques. */
    'variantes' => '{}',
] + apparence_par_defaut('bandeau');

if ($modifie && !$post) {
    $g = json_lire($modifie['gabarit']);
    $textes = [];
    foreach ($g['layers'] ?? [] as $l) {
        if (($l['type'] ?? '') === 'text') {
            $textes[$l['id']] = $l;
        }
    }
    $claim = $textes['claim'] ?? [];
    $champ = $textes['field'] ?? [];
    $photo = [];
    foreach ($g['layers'] ?? [] as $l) {
        if (($l['type'] ?? '') === 'photoSlot') {
            $photo = $l;
        }
    }

    $valeurs = [
        // L'apparence enregistrée est relue telle quelle : rouvrir un décor
        // doit montrer le décor, pas les réglages d'usine de sa disposition.
        'texte_couleur' => (string) ($claim['color'] ?? 'brand.paper'),
        'texte_align' => (string) ($claim['align'] ?? 'left'),
        'bloc_x' => (float) ($claim['rect']['x'] ?? 0.25),
        'bloc_y' => (float) ($claim['rect']['y'] ?? 0.795),
        'bloc_w' => (float) ($claim['rect']['w'] ?? 0.48),
        'accroche_taille' => (float) ($claim['size'] ?? 0.058),
        'champ_taille' => (float) ($champ['size'] ?? 0.03),
        // Absent vaut « oui » : les gabarits enregistrés avant que le choix
        // existe portent tous leur QR, et rouvrir un décor ne doit pas le
        // lui retirer en silence.
        'qr_actif' => ($g['qr']['enabled'] ?? true) ? '1' : '0',
        'qr_position' => (string) ($g['qr']['position'] ?? 'bottom-left'),
        'qr_taille' => (float) ($g['qr']['size'] ?? 0.16),
        'filigrane_position' => (string) ($g['watermark']['position'] ?? 'bottom-right'),
        'format' => (string) ($g['canvas']['ratio'] ?? '1:1'),
        'fond' => (string) ($g['canvas']['background'] ?? 'brand.ink'),
        'photo_x' => (float) ($photo['rect']['x'] ?? 0),
        'photo_y' => (float) ($photo['rect']['y'] ?? 0),
        'photo_w' => (float) ($photo['rect']['w'] ?? 1),
        'photo_h' => (float) ($photo['rect']['h'] ?? 1),
        'photo_forme' => ($photo['mask']['kind'] ?? 'rect') === 'circle'
            ? 'cercle'
            : (($photo['mask']['radius'] ?? 0) > 0 ? 'arrondi' : 'rect'),
        'cadre_fourni' => '',
        'calques' => json_encode(calques_depuis_gabarit($g), JSON_UNESCAPED_UNICODE),
        'variantes' => json_encode((object) ($g['variantes'] ?? []), JSON_UNESCAPED_SLASHES),
        'titre' => $modifie['titre'],
        'sous_titre' => (string) $modifie['sous_titre'],
        'ville' => $modifie['ville'],
        'rubrique' => $modifie['rubrique'],
        // La disposition n'est pas stockée telle quelle : on la retrouve par
        // le format et la mise en page. Avec six dispositions dont trois
        // partagent leur ratio, le repère devient la position du QR et la
        // hauteur du bloc de texte.
        'disposition' => disposition_devinee($g),
        'accroche' => (string) ($textes['claim']['value'] ?? ''),
        'champ_libelle' => (string) ($textes['field']['placeholder'] ?? 'Ton prénom'),
        'champ_valeur' => (string) ($textes['field']['value'] ?? ''),
        'redirection' => (string) ($g['share']['redirectUrl'] ?? ''),
        'redirection_libelle' => (string) ($g['share']['redirectLabel'] ?? ''),
        'legende' => (string) ($g['share']['defaultCaption'] ?? ''),
        'expire_le' => substr((string) $modifie['expire_le'], 0, 10),
        'evenement_le' => substr((string) ($modifie['evenement_le'] ?? ''), 0, 10),
        'cadre_url' => (string) $modifie['cadre_url'],
    ];
}

/* ---------------- reprendre ce qu'on avait commencé ---------------- */

/**
 * Le décor composé avant d'avoir un compte.
 *
 * On ne crée toujours RIEN ici : on remplit le formulaire, et la personne
 * revérifie puis publie elle-même. C'est un clic de plus qu'une création
 * automatique, et c'est voulu : le décor repasse par le chemin normal, avec
 * son jeton anti-CSRF, ses quotas et sa relecture. Une création déclenchée
 * par une simple redirection aurait contourné les trois.
 *
 * Le fichier, lui, sort de quarantaine maintenant : il a désormais un
 * propriétaire, et l'aperçu a besoin de le voir.
 */
$repris = false;
if (!$anonyme && !$modifie && ($_GET['reprendre'] ?? '') === '1' && !$post) {
    if ($b = brouillon_prendre('decor')) {
        foreach ($b['charge'] as $k => $v) {
            if (array_key_exists($k, $valeurs) && is_scalar($v)) {
                $valeurs[$k] = (string) $v;
            }
        }
        /**
         * Les images sortent de quarantaine AVANT qu'on efface le brouillon.
         *
         * L'ordre n'est pas un détail : consommer d'abord effaçait la ligne
         * qui dit à qui le fichier appartient, et l'adoption ne trouvait
         * plus rien. Le décor s'ouvrait alors sans cadre, et le Studio
         * retombait sur le modèle par défaut — ce qui se voyait comme
         * « il m'affiche un autre cadre que celui que j'ai déposé ».
         */
        $adopte = brouillon_fichier_adopter($valeurs['cadre_url']);
        if ($adopte !== '') {
            $valeurs['cadre_url'] = $adopte;
        }
        // Les calques image aussi : ils portent la même sorte d'adresse.
        $valeurs['calques'] = brouillon_calques_adopter($valeurs['calques']);

        $depart = ($b['charge']['depart'] ?? '') === 'fichier' ? 'fichier' : $depart;
        brouillon_consommer('decor');
        $repris = true;
    }
}

if ($post) {
    verifier_csrf();
    foreach (array_keys($valeurs) as $k) {
        $valeurs[$k] = trim((string) ($_POST[$k] ?? $valeurs[$k]));
    }

    /* ---------------- sans compte : on met de côté ---------------- */

    /**
     * Le mur, et ce qui le traverse.
     *
     * On ne crée RIEN ici : ni décor, ni slug, ni ligne au catalogue. On
     * range ce qui a été saisi, et on va chercher un compte. La création
     * repassera par le chemin normal, joué par un utilisateur réel — c'est
     * la seule façon que les quotas, la relecture et le garde-fou de
     * redirection s'appliquent comme d'habitude.
     *
     * Le fichier, lui, part en quarantaine. `donnees/cadres/` sert les
     * cadres des décors publiés ; un fichier qui n'appartient encore à
     * personne n'a rien à y faire.
     */
    if ($anonyme) {
        /**
         * Le fichier est déjà parti, en général.
         *
         * Le Studio le téléverse dès qu'on le choisit, pour que l'aperçu le
         * montre : `cadre_url` porte alors une adresse de quarantaine, et il
         * n'y a plus rien dans `$_FILES`. Ce bloc ne sert donc qu'au
         * formulaire envoyé sans JavaScript, et il fait la même chose.
         */
        if (!empty($_FILES['cadre']['tmp_name']) && is_uploaded_file($_FILES['cadre']['tmp_name'])) {
            $info = @getimagesize($_FILES['cadre']['tmp_name']);
            $ext = match ($info[2] ?? 0) {
                IMAGETYPE_PNG => 'png',
                IMAGETYPE_WEBP => 'webp',
                default => null,
            };
            if (!$ext) {
                $erreur = 'Le cadre doit être un PNG ou un WebP à fond transparent. Le SVG est refusé.';
            } elseif (($_FILES['cadre']['size'] ?? 0) > 2 * 1024 * 1024) {
                $erreur = 'Le cadre dépasse 2 Mo.';
            } elseif ($nomQ = brouillon_fichier_ranger((string) $_FILES['cadre']['tmp_name'], $ext)) {
                if (brouillon_fichier_noter($nomQ)) {
                    $valeurs['cadre_url'] = brouillon_fichier_url($nomQ);
                } else {
                    brouillon_fichier_effacer($nomQ);
                    $erreur = 'Trop d’images en attente depuis cette connexion. '
                            . 'Créez votre compte pour les garder.';
                }
            }
        }
        if ($erreur === null) {
            /**
             * Le brouillon du décor ne porte AUCUN fichier, exprès.
             *
             * Chaque image déposée a déjà sa propre ligne, qui dit à qui elle
             * est et la fait périmer. Recopier le nom ici donnait deux
             * propriétaires au même fichier — et `brouillon_consommer()`
             * l'effaçait au moment même où la reprise allait le chercher.
             */
            $charge = $valeurs;
            $charge['depart'] = $depart;
            if (!brouillon_poser('decor', $charge)) {
                $erreur = 'Trop de décors en attente depuis cette connexion. '
                        . 'Créez votre compte pour reprendre celui-ci.';
            } else {
                // `vers` dit à quelle porte on frappe. Le brouillon est posé
                // dans les deux cas : c'est tout l'intérêt de passer par un
                // envoi de formulaire plutôt que par un lien.
                rediriger((string) ($_POST['vers'] ?? '') === 'connexion'
                    ? '?p=connexion&suite=decor'
                    : '?p=inscription&suite=decor');
            }
        }
    }

    // Le cadre est téléversé AVEC le formulaire, mais son URL survit aux
    // erreurs de saisie : sinon le partenaire le reperdrait à chaque essai.
    $allege = '';
    if (!empty($_FILES['cadre']['tmp_name']) && is_uploaded_file($_FILES['cadre']['tmp_name'])) {
        $info = @getimagesize($_FILES['cadre']['tmp_name']);
        $ext = match ($info[2] ?? 0) {
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
            default => null,
        };
        if (!$ext) {
            $erreur = 'Le cadre doit être un PNG ou un WebP à fond transparent. Le SVG est refusé.';
        } elseif (($_FILES['cadre']['size'] ?? 0) > 2 * 1024 * 1024) {
            $erreur = 'Le cadre dépasse 2 Mo.';
        } else {
            $nom = nouvel_id() . '.' . $ext;
            move_uploaded_file($_FILES['cadre']['tmp_name'], dossier_cadres() . '/' . $nom);
            /**
             * Recompressé UNE fois, à l'arrivée.
             *
             * Un cadre sorti de Canva fait couramment 3 Mo pour 1080 px :
             * chaque invité paierait ce transfert, sur une connexion où le
             * mégaoctet se compte. La fonction ne garde le résultat que
             * s'il est plus léger, et laisse l'original intact sinon.
             */
            $c = compresser_cadre(dossier_cadres(), $nom);
            $nom = $c['nom'];
            $allege = $c['apres'] < $c['avant']
                ? sprintf(' Cadre optimisé : %s au lieu de %s.', poids($c['apres']), poids($c['avant']))
                : '';
            $valeurs['cadre_url'] = url('?p=cadre&f=' . $nom);
        }
    }

    // Un cadre livré avec l'application évite d'avoir à en dessiner un pour
    // essayer un format : c'est ce qui rend les gabarits de réseau utilisables
    // tout de suite, sans passer par un graphiste.
    if (!$erreur && $valeurs['cadre_url'] === '' && $valeurs['cadre_fourni'] !== '') {
        $nom = basename($valeurs['cadre_fourni']);
        if (isset(cadres_fournis()[$nom])) {
            $valeurs['cadre_url'] = url('public/cadres/' . $nom);
        }
    }

    // L'accroche et le libellé du champ sont facultatifs : un décor peut se
    // passer de texte, le cadre porte déjà ce qu'il y a à dire.
    /**
     * Le ciblage multi-villes est vérifié ICI, pas seulement dans le menu.
     *
     * L'option est affichée désactivée dans le formulaire, ce qui suffit à
     * l'usage — mais une option désactivée est un indice visuel, pas une
     * serrure : rien n'empêche d'envoyer la valeur à la main. Tout ce qui
     * se vend se vérifie côté serveur.
     */
    if (!$erreur && $valeurs['ville'] === 'all' && !capacite($u, 'ciblage')) {
        $erreur = 'Le ciblage sur toutes les villes arrive avec l’offre Croissance. '
                . 'Choisissez la ville de votre événement.';
    }

    if (!$erreur && $valeurs['titre'] === '') {
        $erreur = 'Donnez un titre à votre décor.';
    } elseif (!$erreur && $valeurs['cadre_url'] === '' && $valeurs['disposition'] !== 'vierge') {
        // La page blanche est le seul gabarit qui se passe de cadre : son
        // décor tient au fond, à la fenêtre photo et au texte.
        $erreur = 'Téléversez le fichier de votre cadre, ou choisissez le gabarit « Page blanche ».';
    }

    if (!$erreur) {
        // Le slug ne bouge pas à la modification : il est dans des liens déjà
        // partagés, et dans les QR de badges déjà téléchargés.
        $slug = $modifie ? $modifie['slug'] : slug_libre($valeurs['titre']);
        try {
            $gabarit = construire_gabarit([
                'slug' => $slug,
                'titre' => $valeurs['titre'],
                'sous_titre' => $valeurs['sous_titre'],
                'ville' => $valeurs['ville'],
                'rubrique' => $valeurs['rubrique'],
                'disposition' => $valeurs['disposition'],
                'cadre_url' => $valeurs['cadre_url'],
                'accroche' => $valeurs['accroche'],
                'champ_libelle' => $valeurs['champ_libelle'],
                'champ_valeur' => $valeurs['champ_valeur'],
                'redirection' => $valeurs['redirection'],
                'redirection_libelle' => $valeurs['redirection_libelle'],
                'legende' => $valeurs['legende'],
                'expire_le' => $valeurs['expire_le'],
                'apparence' => array_intersect_key($valeurs, array_flip(CLES_APPARENCE)),
                'calques' => $valeurs['calques'],
                'variantes' => $valeurs['variantes'],
                'cree_par' => $modifie ? $modifie['cree_par'] : (droit($u, 'valider') ? 'equipe' : 'partenaire'),
                'partenaire_id' => $u['role'] === 'partenaire' ? $u['id'] : null,
            ]);

            if ($modifie) {
                decor_modifier($modifie['id'], [
                    'titre' => $valeurs['titre'],
                    'sous_titre' => $valeurs['sous_titre'],
                    'ville' => $valeurs['ville'],
                    'rubrique' => $valeurs['rubrique'],
                    'gabarit' => $gabarit,
                    'cadre_url' => $valeurs['cadre_url'],
                    'expire_le' => $valeurs['expire_le'],
                    'evenement_le' => $valeurs['evenement_le'],
                ]);
                rediriger((droit($u, 'decors_tous') ? '?p=catalogue' : '?p=partenaire')
                    . '&ok=' . urlencode('« ' . $valeurs['titre'] . ' » mis à jour.' . $allege));
            }

            $id = decor_creer([
                'slug' => $slug,
                'titre' => $valeurs['titre'],
                'sous_titre' => $valeurs['sous_titre'],
                'ville' => $valeurs['ville'],
                'rubrique' => $valeurs['rubrique'],
                // `cree_par` décide du garde-fou de redirection ET de
                // l'obligation de relecture : c'est le droit d'arbitrer
                // qui le fixe, pas le libellé du rôle.
                'cree_par' => droit($u, 'valider') ? 'equipe' : 'partenaire',
                'auteur_id' => $u['id'],
                'gabarit' => $gabarit,
                'cadre_url' => $valeurs['cadre_url'],
                'expire_le' => $valeurs['expire_le'],
                'evenement_le' => $valeurs['evenement_le'],
            ]);
            rediriger(droit($u, 'decors_tous')
                ? '?p=catalogue&ok=' . urlencode('« ' . $valeurs['titre'] . ' » créé. Publiez-le quand il vous convient.' . $allege)
                : '?p=partenaire&ok=' . urlencode('Décor créé. Soumettez-le à la relecture quand il vous convient.' . $allege));
        } catch (GabaritInvalide $e) {
            // Le message vient du contrat : c'est lui qui sait pourquoi.
            $erreur = $e->getMessage();
        }
    }
}

vue('nouveau', [
    'titre' => $modifie ? 'Modifier « ' . $modifie['titre'] . ' »' : 'Nouveau décor',
    'erreur' => $erreur,
    'valeurs' => $valeurs,
    'modifie' => $modifie,
    // `fichier` ou `studio` : la moitié du panneau « Le cadre » qui sert.
    'depart' => $depart,
    // Sans compte : le bouton dit « Créer mon compte et publier », et non
    // « Enregistrer », parce que ce n'est pas ce qui va se passer.
    'anonyme' => $anonyme,
    // Au retour du mur : l'écran dit que le décor a été retrouvé.
    'repris' => $repris,
]);
