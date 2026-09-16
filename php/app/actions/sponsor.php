<?php
/**
 * Le sponsor d'un décor : le poser, le relire, en sortir un document.
 *
 * Un écran par décor, atteint depuis le rapport de l'événement. Ce n'est
 * pas une entrée de menu : un sponsor n'a de sens que rattaché à une
 * soirée, et le menu porte déjà tout ce qu'il peut porter.
 *
 * La portée est celle du rapport et des segments : `segment_decor()`
 * tranche, et un slug venu de la requête ne donne le décor de personne
 * d'autre.
 */

declare(strict_types=1);

$u = exiger_role(...ROLES);
$equipe = droit($u, 'decors_tous');

if (!$equipe && (interne($u) || !droit($u, 'decors_siens'))) {
    rediriger(accueil_de($u));
}

$q = array_filter($_GET, 'is_scalar');

$d = segment_decor($u, (string) ($q['decor'] ?? ''));
if (!$d) {
    rediriger('?p=rapports');
}

$erreur = null;
$message = isset($q['ok']) ? (string) $q['ok'] : null;

/* ---------------- enregistrer, relire ---------------- */

if ($post) {
    verifier_csrf();
    $quoi = (string) ($_POST['quoi'] ?? 'enregistrer');

    if ($quoi === 'valider') {
        /**
         * Seule la maison approuve un lien de sponsor.
         *
         * C'est tout l'objet de la relecture : un organisateur qui pourrait
         * s'approuver lui-même n'aurait pas été relu. Le droit `valider`
         * est celui qui sert déjà pour les décors et les campagnes.
         */
        exiger_droit('valider');
        sponsor_valider($d, $u);
        rediriger('?p=sponsor&decor=' . rawurlencode((string) $d['slug'])
            . '&ok=' . rawurlencode('Lien approuvé : il est en service sur la page du décor.'));
    }

    /**
     * Le logo arrive AVEC le formulaire, et son adresse survit aux erreurs
     * de saisie : sinon l'organisateur le reperdrait à chaque essai.
     */
    $logo = trim((string) ($_POST['sponsor_logo'] ?? ''));
    if (!empty($_FILES['logo']['tmp_name']) && is_uploaded_file($_FILES['logo']['tmp_name'])) {
        $info = @getimagesize($_FILES['logo']['tmp_name']);
        $ext = match ($info[2] ?? 0) {
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
            IMAGETYPE_JPEG => 'jpg',
            default => null,
        };
        if (!$ext) {
            $erreur = 'Le logo doit être un PNG, un WebP ou un JPEG. Le SVG est refusé.';
        } elseif (($_FILES['logo']['size'] ?? 0) > 2 * 1024 * 1024) {
            $erreur = 'Le logo dépasse 2 Mo.';
        } else {
            $nom = nouvel_id() . '.' . $ext;
            move_uploaded_file($_FILES['logo']['tmp_name'], dossier_medias() . '/' . $nom);
            $logo = url('?p=media&f=' . $nom);
        }
    }

    if ($erreur === null) {
        $r = sponsor_enregistrer($d, $u, [
            'nom' => (string) ($_POST['sponsor_nom'] ?? ''),
            'lien' => (string) ($_POST['sponsor_lien'] ?? ''),
            'logo' => $logo,
        ]);
        if ($r['ok']) {
            journal_ecrire($u, 'sponsor.modifie', 'decor', (string) $d['id'],
                (string) $d['titre'], trim((string) ($_POST['sponsor_nom'] ?? '')) ?: 'retiré');
            rediriger('?p=sponsor&decor=' . rawurlencode((string) $d['slug'])
                . '&ok=' . rawurlencode($r['message']));
        }
        $erreur = $r['message'];
    }

    // La saisie refusée revient telle quelle : on ne fait pas recommencer
    // quelqu'un à cause d'un caractère.
    $d['sponsor_nom'] = (string) ($_POST['sponsor_nom'] ?? '');
    $d['sponsor_lien'] = (string) ($_POST['sponsor_lien'] ?? '');
    $d['sponsor_logo'] = $logo;
}

/* ---------------- le document ---------------- */

if ((string) ($q['export'] ?? '') === 'pdf') {
    $s = sponsor_de($d);
    if (!$s) {
        rediriger('?p=sponsor&decor=' . rawurlencode((string) $d['slug']));
    }
    $pdf = sponsor_pdf($d, sponsor_chiffres($d));
    journal_ecrire($u, 'sponsor.export', 'decor', (string) $d['id'],
        (string) $d['titre'], (string) $s['nom']);
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . sponsor_nom_fichier($d) . '"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
    exit;
}

/* ---------------- l'écran ---------------- */

vue('sponsor', [
    'titre' => 'Sponsor · ' . $d['titre'],
    'moi' => $u,
    'equipe' => $equipe,
    'decor' => $d,
    'sponsor' => sponsor_de($d),
    'chiffres' => sponsor_chiffres($d),
    'erreur' => $erreur,
    'message' => $message,
]);
