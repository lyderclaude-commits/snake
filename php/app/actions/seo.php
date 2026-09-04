<?php
/**
 * Les réglages de référencement, et surtout leur vérification.
 *
 * Un écran de SEO qui se contente d'enregistrer des champs ne sert à rien :
 * on ne saura pas si ça marche. Celui-ci montre CE QUE VOIT un robot —
 * l'aperçu exact d'un partage WhatsApp, et l'état des deux fichiers que
 * cherchent les moteurs. Le réglage se corrige alors sans quitter la page.
 */
$u = exiger_droit('reglages');

$message = null;
$erreur = null;

if ($post) {
    verifier_csrf();
    $v = [];
    foreach (array_keys(SEO_DEFAUTS) as $cle) {
        $v[$cle] = trim((string) ($_POST[$cle] ?? ''));
    }
    $v['seo_indexable'] = ($_POST['seo_indexable'] ?? '') === '1' ? '1' : '0';

    /**
     * L'image de partage est confrontée à `cle_image()`.
     *
     * Elle ne peut désigner qu'un média de la maison : sans ce contrôle,
     * un champ texte poserait l'image d'un autre site en vignette de
     * chaque partage — et nous la ferions charger à tous nos lecteurs.
     */
    if ($v['seo_image'] !== '' && cle_image($v['seo_image']) === null) {
        $erreur = 'L’image de partage doit être un média téléversé ici. '
                . 'Envoyez-la ci-dessous plutôt que d’en coller l’adresse.';
    }

    if (!empty($_FILES['image']['tmp_name']) && is_uploaded_file($_FILES['image']['tmp_name'])) {
        $info = @getimagesize($_FILES['image']['tmp_name']);
        $ext = match ($info[2] ?? 0) {
            IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp', IMAGETYPE_JPEG => 'jpg',
            default => null,
        };
        if (!$ext) {
            $erreur = 'L’image de partage doit être un JPEG, un PNG ou un WebP.';
        } elseif ((int) ($info[0] ?? 0) < SEO_IMAGE_MIN) {
            // Le dire ICI plutôt que de laisser découvrir une vignette
            // minuscule des semaines plus tard, sur le téléphone d'un autre.
            $erreur = 'Cette image fait ' . (int) ($info[0] ?? 0) . ' px de large. '
                    . 'En dessous de ' . SEO_IMAGE_MIN . ' px, les messageries rendent une '
                    . 'vignette timbre-poste. Visez 1200 × 630.';
        } else {
            $nom = nouvel_id() . '.' . $ext;
            move_uploaded_file($_FILES['image']['tmp_name'], dossier_medias() . '/' . $nom);
            $v['seo_image'] = url('?p=media&f=' . $nom);
        }
    }
    if (($_POST['effacer_image'] ?? '') === '1') {
        $v['seo_image'] = '';
    }

    if ($erreur === null) {
        reglages_bdd_poser($v);
        journal_ecrire($u, 'reglages.modifies', 'reglages', null, 'Référencement');
        rediriger('?p=reglages-seo&ok=' . rawurlencode('Réglages enregistrés.'));
    }
}

/**
 * Les deux adresses, et laquelle marchera vraiment.
 *
 * On ne les interroge PAS depuis cette page. Un appel réseau bloquant à
 * chaque affichage coûte trois secondes quand l'hébergeur refuse de se
 * joindre lui-même — ce qui est fréquent en mutualisé — et l'écran des
 * réglages devient alors le plus lent du site, pour un renseignement qu'un
 * clic donne mieux.
 *
 * On dit donc ce dont ça dépend, et l'on donne les deux adresses. Celle
 * qui marche toujours est la seconde.
 */
$rewrite = function_exists('apache_get_modules')
    ? in_array('mod_rewrite', apache_get_modules(), true)
    : null;

vue('seo', [
    'titre' => 'Référencement',
    'valeurs' => seo_reglages(),
    'message' => $message ?? ($_GET['ok'] ?? null),
    'erreur' => $erreur,
    'apercu' => seo_image(seo_reglage('seo_image') ?: null),
    'rewrite' => $rewrite,
    'robots_court' => base_url() . '/robots.txt',
    'plan_court' => base_url() . '/sitemap.xml',
    'robots_php' => base_url() . '/index.php?p=robots',
    'plan_php' => base_url() . '/index.php?p=sitemap',
    'combien' => substr_count(sitemap_xml(), '<loc>'),
]);
