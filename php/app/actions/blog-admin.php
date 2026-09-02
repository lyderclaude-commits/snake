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
    ];

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
        'erreur' => $erreur,
    ]);
}

/* ---------------- la liste ---------------- */

vue('blog-admin', [
    'titre' => $equipe ? 'Le blog' : 'Mes articles',
    'liste' => $equipe ? articles_tous() : articles_de((string) $u['id']),
    'equipe' => $equipe,
    'a_relire' => $equipe ? articles_a_relire() : 0,
    'message' => $message,
    'erreur' => $alerte,
]);
