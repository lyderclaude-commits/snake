<?php
/**
 * Le blog, côté rédaction — l'équipe ET les organisateurs.
 *
 * Un organisateur propose, l'équipe décide. C'est le même circuit que les
 * décors, avec les mêmes états et le même vocabulaire : brouillon,
 * en relecture, à corriger, refusé, publié. Réutiliser le parcours qu'on
 * connaît déjà vaut mieux qu'en inventer un second pour la même idée.
 *
 * L'article sponsorisé vendu avec l'offre Mouvement reste un service :
 * la rédaction peut écrire À LA PLACE d'un organisateur. Mais un
 * organisateur qui veut écrire lui-même n'a plus besoin d'attendre qu'on
 * ait le temps — il propose, et l'équipe relit.
 */
$u = exiger_droit('articles');
// Ce qui compte n'est pas d'être de l'équipe, c'est d'avoir le droit
// d'arbitrer. Un coordinateur publie ; un éditeur propose.
$equipe = droit($u, 'valider');

$erreur = null;
$message = $_GET['ok'] ?? null;
$alerte = $_GET['err'] ?? null;

/** L'article visé, et le droit d'y toucher. */
$mien = function (?array $a) use ($u, $equipe): array {
    if (!$a) {
        rediriger('?p=blog-admin&err=' . rawurlencode('Article introuvable.'));
    }
    if (!$equipe && $a['auteur_id'] !== $u['id']) {
        rediriger('?p=blog-admin&err=' . rawurlencode('Cet article ne vous appartient pas.'));
    }
    return $a;
};

/* ---------------- la source du guide ---------------- */

/**
 * D'où viennent les articles repris de wakabileguide.com.
 *
 * Réglé ici et non dans `config.php` : le jour où WordPress déménage, le
 * blog fusionné perd la moitié de son contenu, et il faut pouvoir le
 * réparer depuis un navigateur — pas par FTP, un dimanche, depuis un
 * téléphone.
 *
 * Vide, la source est débranchée : le blog ne montre plus que les articles
 * d'ici, et plus un seul appel ne part. C'est ce qu'on règle sur un
 * hébergement sans sortie réseau.
 */
if ($page === 'blog-admin' && $post) {
    verifier_csrf();
    exiger_droit('valider');
    $racine = rtrim(trim((string) ($_POST['wp_racine'] ?? '')), '/');
    if ($racine !== '' && !preg_match('~^https?://[^\s/]+~i', $racine)) {
        rediriger('?p=blog-admin&err=' . rawurlencode(
            'L’adresse doit commencer par http:// ou https://.'));
    }
    reglages_bdd_poser(['wp_racine' => $racine]);
    /* Le répit après panne vise l'ANCIENNE adresse : le garder ferait
       croire que la nouvelle ne répond pas non plus, pendant cinq
       minutes, juste après qu'on vient de la corriger. */
    wp_panne_noter(false);
    journal_ecrire($u, 'reglages.modifies', 'reglages', null, 'Source du blog');
    rediriger('?p=blog-admin&ok=' . rawurlencode(
        $racine === '' ? 'Source du guide débranchée.' : 'Source du guide enregistrée.'));
}

/* ---------------- importer depuis le guide ---------------- */

/**
 * Le clic d'import : un lot d'articles du guide entre ici pour toujours.
 *
 * Chaque article est repris UN PAR UN, et l'importeur est volontairement
 * rangé : fetch l'article, le traduire, télécharger ses images, écrire,
 * article suivant. Un chargement de page mutualisée meurt au bout de
 * trente à cent vingt secondes ; vingt-cinq articles de suite dépassent
 * rarement ceci, et SINON rien n'est perdu : les imports se voient à
 * `importe_de`, un article commencé reste entier, et relancer le même
 * clic pour les suivants passe au-dessus de ceux qui sont déjà entrés.
 *
 * L'import est écrit PUBLIÉ d'emblée, avec les dates du guide : un
 * article relu et publié là-bas n'a pas à repasser par le circuit
 * brouillon-relecture — sa revoyure a déjà eu lieu chez son auteur.
 */
if ($page === 'blog-import') {
    exiger_droit('valider');
    if (!$post) {
        rediriger('?p=blog-admin');
    }
    verifier_csrf();

    $ids = array_values(array_map('intval', (array) ($_POST['ids'] ?? [])));
    $ids = array_slice(array_values(array_filter($ids, static fn(int $i): bool => $i > 0)), 0, WP_IMPORT_LOT_MAX);
    if (!$ids) {
        rediriger('?p=blog-admin&err=' . rawurlencode('Cochez au moins un article à importer.'));
    }
    if (!wp_actif()) {
        rediriger('?p=blog-admin&err=' . rawurlencode(
            'L’adresse de l’API du guide est vide : réglez-la d’abord.'));
    }

    /* L'import peut être long — des images à télécharger — et l'écran
       demande tout ce que le mutalisé accorde. */
    @set_time_limit(0);
    ignore_user_abort(false);

    $faits = [];
    $rates = [];
    foreach ($ids as $idwp) {
        $titre = 'l’article #' . $idwp;
        $r = wp_get(wp_racine() . '/posts/' . $idwp . '?' . http_build_query(['_embed' => '1']), 20);

        /* WordPress rend un OBJET erreur (`code`, `message`) sur un
           identifiant inconnu, et un tableau seulement sur un article. */
        if (!$r['ok'] || !is_array($r['corps']) || isset($r['corps']['code'])) {
            $rates[] = $titre;
            continue;
        }
        $d = wp_normaliser($r['corps']);
        if ($d['titre'] !== '') {
            $titre = '« ' . $d['titre'] . ' »';
        }
        $import = wp_importer_article($d, (string) $u['id']);
        if (!$import['ok']) {
            $rates[] = $titre;
            continue;
        }
        if (!$import['deja']) {
            journal_ecrire($u, 'article.importe', 'article', (string) $import['id'],
                'Article importé du guide : ' . $d['titre']);
            $faits[] = $titre;
        }
    }

    $n = count($faits);
    $m = count($rates);
    $ok_msg = '';
    $err_msg = '';
    if ($n > 0) {
        $ok_msg = $n . ' article' . ($n > 1 ? 's' : '') . ' importé' . ($n > 1 ? 's' : '')
            . ' du guide, avec ses images.';
    } elseif ($m === 0) {
        $ok_msg = 'Rien à importer : tous ces articles sont déjà chez nous.';
    }
    if ($m > 0) {
        $err_msg = $m . ' article' . ($m > 1 ? 's' : '') . ' n’' . ($m > 1 ? 'ont' : 'a')
            . ' pas pu être ' . ($m > 1 ? 'importés' : 'importé') . ' — '
            . implode(', ', array_slice($rates, 0, 3)) . ($m > 3 ? ' et d’autres' : '')
            . '. WordPress a pu être injoignable : réessayez — un article déjà entré n’est jamais importé deux fois.';
    }
    rediriger('?p=blog-admin'
        . ($ok_msg !== '' ? '&ok=' . rawurlencode($ok_msg) : '')
        . ($err_msg !== '' ? '&err=' . rawurlencode($err_msg) : ''));
}

/* ---------------- soumettre, décider, supprimer ---------------- */

if ($page === 'blog-action') {
    verifier_csrf();
    $a = $mien(article_par_id((string) ($_POST['id'] ?? '')));
    $quoi = (string) ($_POST['quoi'] ?? '');
    $motif = trim((string) ($_POST['motif'] ?? ''));

    try {
        switch ($quoi) {
            case 'soumettre':
                article_transition((string) $a['id'], 'en_relecture', $u);
                notifier_equipe('blog', 'Un article attend votre relecture',
                    '« ' . $a['titre'] . ' » proposé par ' . $u['nom'] . '.', '?p=blog-relecture');
                rediriger('?p=blog-admin&ok=' . rawurlencode(
                    'Proposé à la rédaction. Réponse sous 24 h ouvrées.'));

            case 'publier':
                exiger_droit('valider');
                article_transition((string) $a['id'], 'publie', $u);
                if ($a['auteur_id'] && $a['auteur_id'] !== $u['id']) {
                    notifier((string) $a['auteur_id'], 'blog', 'Votre article est en ligne',
                        '« ' . $a['titre'] . ' » est publié sur le blog du guide.',
                        '?p=blog&a=' . $a['slug']);
                }
                rediriger('?p=blog-admin&ok=' . rawurlencode(
                    '« ' . $a['titre'] . ' » est en ligne : ' . base_url() . '/index.php?p=blog&a=' . $a['slug']));

            case 'corrections':
            case 'refuser':
                exiger_droit('valider');
                article_transition((string) $a['id'], $quoi === 'refuser' ? 'refuse' : 'corrections', $u, $motif);
                if ($a['auteur_id']) {
                    notifier((string) $a['auteur_id'], 'blog',
                        $quoi === 'refuser' ? 'Votre article est refusé' : 'Votre article demande une correction',
                        $motif, '?p=blog-editer&id=' . $a['id']);
                }
                rediriger('?p=blog-relecture&ok=' . rawurlencode('Décision enregistrée, l’auteur est prévenu.'));

            case 'reprendre':
                // Un refus n'est pas une condamnation : on peut repartir.
                article_transition((string) $a['id'], 'brouillon', $u);
                rediriger('?p=blog-editer&id=' . rawurlencode((string) $a['id']));

            case 'supprimer':
                // Un article PUBLIÉ ne se supprime que par l'équipe : son
                // adresse a pu être partagée, et son auteur ne décide pas
                // seul de casser les liens des autres.
                if ($a['statut'] === 'publie' && !$equipe) {
                    throw new TransitionRefusee(
                        'Un article en ligne ne se retire que par la rédaction : son adresse a pu être partagée.'
                    );
                }
                journal_ecrire($u, 'article.supprime', 'article', (string) $a['id'], (string) $a['titre']);
                article_supprimer((string) $a['id']);
                rediriger('?p=blog-admin&ok=' . rawurlencode('« ' . $a['titre'] . ' » supprimé.'));

            default:
                rediriger('?p=blog-admin');
        }
    } catch (Throwable $e) {
        rediriger('?p=blog-admin&err=' . rawurlencode($e->getMessage()));
    }
}

/* ---------------- la file de relecture ---------------- */

if ($page === 'blog-relecture') {
    exiger_droit('valider');
    vue('blog-relecture', [
        'titre' => 'Relecture du blog',
        'liste' => articles_en_attente(),
        'message' => $message,
        'erreur' => $alerte,
    ]);
}

/* ---------------- écrire ---------------- */

if ($page === 'blog-editer') {
    $a = ($_GET['id'] ?? '') !== '' ? $mien(article_par_id((string) $_GET['id'])) : null;
    $valeurs = [
        'titre' => $a['titre'] ?? '',
        'slug' => $a['slug'] ?? '',
        'chapo' => $a['chapo'] ?? '',
        'corps' => $a['corps'] ?? '',
        'couverture' => $a['couverture'] ?? '',
        'decor_id' => $a['decor_id'] ?? '',
    ];
    // L'auteur d'un article repris par la rédaction reste celui qui décide
    // de ce qu'il peut citer : la liste est la SIENNE, pas celle du relecteur.
    $liables = decors_liables($a && $a['auteur_id']
        ? (utilisateur_par_id((string) $a['auteur_id']) ?? $u)
        : $u);

    /**
     * Le décor DÉJÀ lié reste dans la liste, même s'il en sort par le
     * plafond ou par l'archivage.
     *
     * Sans cette ligne, rouvrir un vieil article pour corriger une virgule
     * détacherait son décor en silence : le déroulant retomberait sur
     * « Aucun », et l'enregistrement suivant l'écrirait pour de bon. C'est
     * la même famille de perte que la couverture effacée à chaque
     * enregistrement — invisible le jour même, découverte des mois après.
     */
    if (($valeurs['decor_id'] ?? '') !== ''
        && !in_array($valeurs['decor_id'], array_column($liables, 'id'), true)) {
        $deja = decor_par_id((string) $valeurs['decor_id']);
        if ($deja) {
            array_unshift($liables, $deja);
        }
    }

    /**
     * Un article en relecture ne se modifie plus sous le nez du relecteur.
     *
     * Sinon quelqu'un approuve un texte qui n'est déjà plus celui-là — et
     * c'est exactement le scénario qui fait publier ce qu'on avait refusé.
     */
    if ($a && $a['statut'] === 'en_relecture' && !$equipe) {
        rediriger('?p=blog-admin&err=' . rawurlencode(
            'Cet article est chez la rédaction : il ne se modifie plus tant qu’elle n’a pas répondu.'));
    }

    if ($post) {
        verifier_csrf();
        foreach (['titre', 'chapo', 'corps'] as $c) {
            $valeurs[$c] = trim((string) ($_POST[$c] ?? ''));
        }

        /**
         * La couverture revient par un champ CACHÉ, et elle est vérifiée.
         *
         * Sans ce champ, chaque enregistrement repartait avec une
         * couverture vide : corriger une faute de frappe effaçait l'image
         * de l'article, et personne ne fait le lien entre les deux — on
         * découvre la page sans image des semaines plus tard.
         *
         * La valeur reçue est confrontée à `cle_image()` : elle ne peut
         * désigner qu'un média de la maison. Un formulaire trafiqué ne
         * pose donc pas l'image d'un autre site en couverture d'article.
         */
        $envoyee = trim((string) ($_POST['couverture'] ?? ''));
        $valeurs['couverture'] = $envoyee !== '' && cle_image($envoyee) !== null
            ? $envoyee
            : (string) ($a['couverture'] ?? '');

        /**
         * L'adresse est FIGÉE une fois l'article publié.
         *
         * Elle a pu être partagée, mise en favori, indexée. La recalculer
         * parce qu'on a corrigé une faute dans le titre casserait tous ces
         * liens en silence — et c'est le genre de perte qu'on ne remarque
         * que six mois plus tard, dans les statistiques.
         */
        /**
         * Le décor cité, confronté à la liste des décors citables.
         *
         * Une liste déroulante n'est pas une autorisation : un formulaire
         * trafiqué qui poste l'identifiant du brouillon d'un autre en
         * révélerait le titre avant l'heure. Un identifiant hors liste est
         * traité comme « aucun décor », pas comme une erreur — on ne fait
         * pas recommencer un article pour un champ facultatif.
         */
        $decor_voulu = trim((string) ($_POST['decor_id'] ?? ''));
        $valeurs['decor_id'] = '';
        foreach ($liables as $d) {
            if ((string) $d['id'] === $decor_voulu) {
                $valeurs['decor_id'] = $decor_voulu;
                break;
            }
        }

        $slug_saisi = trim((string) ($_POST['slug'] ?? ''));
        $valeurs['slug'] = $a && $a['statut'] === 'publie'
            ? $a['slug']
            : slug_article_libre($slug_saisi !== '' ? $slug_saisi : $valeurs['titre'], $a['id'] ?? null);

        /* La couverture : téléversée, recompressée, puis rangée. */
        if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
            $info = @getimagesize($_FILES['image']['tmp_name']);
            $ext = match ($info[2] ?? 0) {
                IMAGETYPE_PNG => 'png',
                IMAGETYPE_WEBP => 'webp',
                IMAGETYPE_JPEG => 'jpg',
                default => null,
            };
            if (!$ext) {
                $erreur = 'La couverture doit être une image JPEG, PNG ou WebP.';
            } elseif (($_FILES['image']['size'] ?? 0) > 6 * 1024 * 1024) {
                $erreur = 'La couverture dépasse 6 Mo.';
            } else {
                $nom = nouvel_id() . '.' . $ext;
                move_uploaded_file($_FILES['image']['tmp_name'], dossier_medias() . '/' . $nom);
                // Une photo sort d'un appareil en 4000 px et 5 Mo ; elle
                // s'affiche en 900. Elle est ramenée à sa taille utile UNE
                // fois, ici, et plus jamais transférée entière.
                $nom = compresser_cadre(dossier_medias(), $nom)['nom'];
                @touch(dossier_medias() . '/' . $nom . '.opt');
                $valeurs['couverture'] = url('?p=media&f=' . $nom);
            }
        }
        if (($_POST['effacer_image'] ?? '') === '1') {
            $valeurs['couverture'] = '';
        }

        if ($erreur === null) {
            $erreur = match (true) {
                $valeurs['titre'] === '' => 'Donnez un titre à l’article.',
                mb_strlen($valeurs['titre']) > 160 => 'Ce titre est trop long.',
                mb_strlen($valeurs['corps']) < 40 =>
                    'Un article de moins de 40 caractères ne rendra service à personne.',
                default => null,
            };
        }

        if ($erreur === null) {
            if ($a) {
                article_maj((string) $a['id'], $valeurs);
                $id = (string) $a['id'];
            } else {
                $id = article_creer($valeurs + ['auteur_id' => $u['id'], 'auteur_nom' => $u['nom']]);
            }

            /**
             * L'équipe publie d'un geste ; un organisateur propose.
             *
             * Le bouton porte le mot juste dans les deux cas — « Publier »
             * ou « Proposer à la rédaction » — parce qu'une même étiquette
             * pour deux effets différents finit par tromper quelqu'un.
             */
            $suite = (string) ($_POST['action'] ?? 'enregistrer');
            try {
                if ($suite === 'publier' && $equipe) {
                    article_transition($id, 'publie', $u);
                    rediriger('?p=blog-admin&ok=' . rawurlencode(
                        '« ' . $valeurs['titre'] . ' » est en ligne : '
                        . base_url() . '/index.php?p=blog&a=' . $valeurs['slug']));
                }
                if ($suite === 'soumettre') {
                    article_transition($id, 'en_relecture', $u);
                    notifier_equipe('blog', 'Un article attend votre relecture',
                        '« ' . $valeurs['titre'] . ' » proposé par ' . $u['nom'] . '.', '?p=blog-relecture');
                    rediriger('?p=blog-admin&ok=' . rawurlencode(
                        'Proposé à la rédaction. Réponse sous 24 h ouvrées.'));
                }
            } catch (TransitionRefusee $e) {
                rediriger('?p=blog-admin&err=' . rawurlencode($e->getMessage()));
            }
            rediriger('?p=blog-admin&ok=' . rawurlencode('« ' . $valeurs['titre'] . ' » enregistré.'));
        }
    }

    vue('blog-editer', [
        'titre' => $a ? 'Modifier l’article' : 'Écrire un article',
        'valeurs' => $valeurs,
        'existant' => $a,
        'equipe' => $equipe,
        'liables' => $liables,
        'erreur' => $erreur,
    ]);
}

/* ---------------- la liste ---------------- */

/**
 * Le catalogue du guide, vu du panneau d'import.
 *
 * Chaque article du guide porte son drapeau : s'il a DÉJÀ un import à
 * son nom, il est affiché « importé » avec son lien local — cliquable,
 * pour relire le résultat ici plutôt que là-bas. La liste vient du cache
 * habituel, donc relancer l'écran coûte au pire un clic.
 *
 * `$wp_etat` vient de la LECTURE du catalogue plutôt que d'un appel à
 * part : le même `wp_jusqua` remplit les deux — le billet de connexion
 * (« 42 articles, frais ») et la liste elle-même.
 */
$guide = $equipe && wp_actif() ? wp_jusqua(WP_LOTS_MAX * WP_PAR_LOT) : null;
$liste_guide = [];
foreach ((array) ($guide['articles'] ?? []) as $g) {
    $deja = article_deja_importe((string) $g['lien_source']);
    $liste_guide[] = $g + [
        'idwp' => (int) ($g['idwp'] ?? 0),
        'importe_id' => $deja ? (string) $deja['id'] : '',
        'importe_le' => $deja ? (string) $deja['maj_le'] : '',
        'slug_local' => $deja ? (string) $deja['slug'] : '',
    ];
}

vue('blog-admin', [
    'titre' => $equipe ? 'Le blog' : 'Mes articles',
    'wp_racine' => wp_racine(),
    'wp_defaut' => WP_RACINE_DEFAUT,
    /* Un chiffre vaut mieux qu'une case cochée : « 42 articles » dit que
       l'adresse répond, que le format est le bon et que le cache est
       chaud. Une case « source active » n'aurait dit que ce qu'on a tapé. */
    'wp_etat' => $equipe ? ['total' => $guide ? (int) $guide['total'] : 0,
                           'frais' => $guide ? (bool) $guide['frais'] : false] : null,
    'liste_guide' => $liste_guide,
    'liste' => $equipe ? articles_tous() : articles_de((string) $u['id']),
    'equipe' => $equipe,
    'a_relire' => $equipe ? articles_a_relire() : 0,
    'message' => $message,
    'erreur' => $alerte,
]);
