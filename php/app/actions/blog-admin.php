<?php
/**
 * Le blog, côté rédaction. Réservé à l'équipe.
 *
 * L'écriture n'est pas ouverte aux organisateurs, même sur l'offre
 * Mouvement : l'« article sponsorisé » y est vendu comme un service —
 * rédigé et publié PAR la rédaction. Un champ de saisie libre sur une page
 * publique confié à des comptes clients, c'est le début d'une modération
 * qu'on n'a pas les moyens de tenir.
 */
$u = exiger_role('equipe');

$erreur = null;
$message = $_GET['ok'] ?? null;

/* ---------------- supprimer ---------------- */

if ($page === 'blog-supprimer') {
    verifier_csrf();
    $a = article_par_id((string) ($_POST['id'] ?? ''));
    if (!$a) {
        rediriger('?p=blog-admin&err=' . rawurlencode('Article introuvable.'));
    }
    article_supprimer((string) $a['id']);
    rediriger('?p=blog-admin&ok=' . rawurlencode('« ' . $a['titre'] .' » supprimé.'));
}

/* ---------------- écrire ---------------- */

if ($page === 'blog-editer') {
    $a = article_par_id((string) ($_GET['id'] ?? ''));
    $valeurs = [
        'id' => $a['id'] ?? '',
        'titre' => $a['titre'] ?? '',
        'slug' => $a['slug'] ?? '',
        'chapo' => $a['chapo'] ?? '',
        'corps' => $a['corps'] ?? '',
        'couverture' => $a['couverture'] ?? '',
        'statut' => $a['statut'] ?? 'brouillon',
    ];

    if ($post) {
        verifier_csrf();
        foreach (['titre', 'chapo', 'corps', 'couverture'] as $c) {
            $valeurs[$c] = trim((string) ($_POST[$c] ?? ''));
        }
        $valeurs['statut'] = ($_POST['action'] ?? '') === 'publier' ? 'publie' : 'brouillon';

        /**
         * L'adresse est FIGÉE une fois l'article publié.
         *
         * Elle a pu être partagée, mise en favori, indexée. La recalculer
         * parce qu'on a corrigé une faute dans le titre casserait tous ces
         * liens en silence — et c'est exactement le genre de perte qu'on ne
         * remarque que six mois plus tard, dans les statistiques.
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
                // Une photo de couverture sort d'un appareil en 4000 px et
                // 5 Mo ; elle s'affiche en 900. Elle est ramenée à sa taille
                // utile UNE fois, ici, et plus jamais transférée entière.
                $nom = compresser_cadre(dossier_medias(), $nom)['nom'];
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
                $id = $a['id'];
            } else {
                $id = article_creer($valeurs + [
                    'auteur_id' => $u['id'],
                    'auteur_nom' => $u['nom'],
                ]);
            }
            rediriger('?p=blog-admin&ok=' . rawurlencode(
                $valeurs['statut'] === 'publie'
                    ? '« ' . $valeurs['titre'] . ' » est en ligne : ' . base_url() . '/index.php?p=blog&a=' . $valeurs['slug']
                    : '« ' . $valeurs['titre'] . ' » enregistré en brouillon.'
            ));
        }
    }

    vue('blog-editer', [
        'titre' => $a ? 'Modifier un article' : 'Écrire un article',
        'valeurs' => $valeurs,
        'existant' => $a,
        'erreur' => $erreur,
    ]);
}

/* ---------------- la liste ---------------- */

vue('blog-admin', [
    'titre' => 'Le blog',
    'liste' => articles_tous(),
    'message' => $message,
    'erreur' => $_GET['err'] ?? null,
]);
