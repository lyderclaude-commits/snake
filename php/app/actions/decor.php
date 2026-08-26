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
    rediriger('?p=partenaire&ok=' . urlencode('Soumis — réponse sous 24 h ouvrées.'));
}

/* ---------------- création ---------------- */

$erreur = null;
$valeurs = [
    'titre' => '', 'sous_titre' => '', 'ville' => 'lome', 'rubrique' => 'campagne',
    'disposition' => 'bandeau', 'accroche' => 'J’Y SERAI', 'champ_libelle' => 'Ton prénom',
    'champ_valeur' => 'Kossi', 'redirection' => 'https://wakabileguide.com/',
    'redirection_libelle' => '', 'legende' => '', 'expire_le' => '', 'cadre_url' => '',
];

if ($modifie && !$post) {
    $g = json_lire($modifie['gabarit']);
    $textes = [];
    foreach ($g['layers'] ?? [] as $l) {
        if (($l['type'] ?? '') === 'text') {
            $textes[$l['id']] = $l;
        }
    }
    $valeurs = [
        'titre' => $modifie['titre'],
        'sous_titre' => (string) $modifie['sous_titre'],
        'ville' => $modifie['ville'],
        'rubrique' => $modifie['rubrique'],
        'disposition' => ($g['canvas']['ratio'] ?? '1:1') === '9:16' ? 'story'
            : (($textes['claim']['uppercase'] ?? false) ? 'bandeau' : 'angle'),
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

    if (!$erreur && $valeurs['titre'] === '') {
        $erreur = 'Donnez un titre à votre décor.';
    } elseif (!$erreur && $valeurs['accroche'] === '') {
        $erreur = 'Indiquez l’accroche affichée sur le badge.';
    } elseif (!$erreur && $valeurs['cadre_url'] === '') {
        $erreur = 'Téléversez le fichier de votre cadre.';
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
                'champ_libelle' => $valeurs['champ_libelle'] ?: 'Ton prénom',
                'champ_valeur' => $valeurs['champ_valeur'] ?: 'Kossi',
                'redirection' => $valeurs['redirection'],
                'redirection_libelle' => $valeurs['redirection_libelle'],
                'legende' => $valeurs['legende'],
                'expire_le' => $valeurs['expire_le'],
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
