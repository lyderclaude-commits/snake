<?php
/** Création d'un décor par un partenaire, et soumission à la relecture. */

$u = exiger_role('partenaire', 'equipe');

/**
 * Le même formulaire sert à créer et à modifier.
 *
 * Un écran d'édition séparé divergerait du formulaire de création à la
 * première évolution : ils décrivent la même chose.
 */
$modifie = $page === 'modifier' ? decor_par_id((string) ($_GET['id'] ?? $_POST['id'] ?? '')) : null;
if ($page === 'modifier') {
    if (!$modifie) {
        rediriger($u['role'] === 'equipe' ? '?p=catalogue&err=' . urlencode('Décor introuvable.') : '?p=partenaire');
    }
    // Un partenaire ne modifie que ses propres décors, et pas après publication.
    if ($u['role'] === 'partenaire') {
        if ($modifie['auteur_id'] !== $u['id']) {
            rediriger('?p=partenaire&err=' . urlencode('Ce décor ne vous appartient pas.'));
        }
        if (in_array($modifie['statut'], ['publie', 'en_relecture'], true)) {
            rediriger('?p=partenaire&err=' . urlencode(
                'Un décor publié ou en relecture ne se modifie plus. Demandez à l’équipe Wakabi.'
            ));
        }
    }
}

/* ---------------- soumission ---------------- */

if ($page === 'soumettre') {
    verifier_csrf();
    $d = decor_par_id((string) ($_POST['id'] ?? ''));
    if (!$d) {
        rediriger('?p=partenaire&err=' . urlencode('Décor introuvable.'));
    }
    if ($u['role'] === 'partenaire' && $d['auteur_id'] !== $u['id']) {
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
            . 'relecture. Le lien vous a été envoyé à ' . $u['email'] . ' — vous pouvez le '
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
    foreach (equipe() as $membre) {
        notifier($membre['id'], 'relecture', 'Un décor attend votre relecture', $d['titre'], '?p=relecture');
    }
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
    'accroche_taille', 'champ_taille', 'qr_position', 'qr_taille', 'filigrane_position',
    'format', 'fond', 'photo_x', 'photo_y', 'photo_w', 'photo_h', 'photo_forme',
];

$valeurs = [
    'titre' => '', 'sous_titre' => '', 'ville' => 'lome', 'rubrique' => 'campagne',
    'disposition' => 'bandeau', 'accroche' => 'J’Y SERAI', 'champ_libelle' => 'Ton prénom',
    'champ_valeur' => 'Kossi', 'redirection' => 'https://wakabileguide.com/',
    'redirection_libelle' => '', 'legende' => '', 'expire_le' => '', 'cadre_url' => '',
    'cadre_fourni' => '',
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
        'cadre_url' => (string) $modifie['cadre_url'],
    ];
}

if ($post) {
    verifier_csrf();
    foreach (array_keys($valeurs) as $k) {
        $valeurs[$k] = trim((string) ($_POST[$k] ?? $valeurs[$k]));
    }

    // Le cadre est téléversé AVEC le formulaire, mais son URL survit aux
    // erreurs de saisie : sinon le partenaire le reperdrait à chaque essai.
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
                'cree_par' => $modifie ? $modifie['cree_par'] : ($u['role'] === 'equipe' ? 'equipe' : 'partenaire'),
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
                ]);
                rediriger(($u['role'] === 'equipe' ? '?p=catalogue' : '?p=partenaire')
                    . '&ok=' . urlencode('« ' . $valeurs['titre'] . ' » mis à jour.'));
            }

            $id = decor_creer([
                'slug' => $slug,
                'titre' => $valeurs['titre'],
                'sous_titre' => $valeurs['sous_titre'],
                'ville' => $valeurs['ville'],
                'rubrique' => $valeurs['rubrique'],
                'cree_par' => $u['role'] === 'equipe' ? 'equipe' : 'partenaire',
                'auteur_id' => $u['id'],
                'gabarit' => $gabarit,
                'cadre_url' => $valeurs['cadre_url'],
                'expire_le' => $valeurs['expire_le'],
            ]);
            rediriger($u['role'] === 'equipe'
                ? '?p=catalogue&ok=' . urlencode('« ' . $valeurs['titre'] . ' » créé. Publiez-le quand il vous convient.')
                : '?p=partenaire&ok=' . urlencode('Décor créé. Soumettez-le à la relecture quand il vous convient.'));
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
]);
