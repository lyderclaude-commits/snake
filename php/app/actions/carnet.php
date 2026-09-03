<?php
/**
 * Le carnet, côté écran : ranger, corriger, retirer.
 *
 * Un seul fichier, comme la régie dont il est le prolongement — c'est le
 * même utilisateur, dans le même geste : il prépare à qui il va écrire.
 *
 * L'écran obéit à une règle qui n'a l'air de rien : **rien n'y est
 * définitif sauf ce qu'on nomme « supprimer »**. Retirer d'une liste,
 * archiver, renommer, vider une liste : tout se refait. C'est ce qui
 * permet de ranger vite, un soir, sans relire trois fois chaque bouton.
 */
$u = exiger_droit('regie');
$equipe = droit($u, 'valider');

if (!$equipe && !capacite($u, 'regie')) {
    vue('offre-requise', [
        'titre' => 'Carnet d’adresses',
        'quoi' => OFFRE_LIGNES['regie'][0],
        'aide' => OFFRE_LIGNES['regie'][2],
        'debloque' => offre_qui_debloque('regie'),
    ]);
}

$moi = (string) $u['id'];
$message = $_GET['ok'] ?? null;
$alerte = $_GET['err'] ?? null;

/** Le retour à l'écran d'où l'on vient, filtre compris. */
$retour = function (array $sup = []): string {
    $q = array_filter([
        'p' => 'regie-carnet',
        'l' => (string) ($_POST['revenir_l'] ?? $_GET['l'] ?? ''),
        'etat' => (string) ($_POST['revenir_etat'] ?? $_GET['etat'] ?? ''),
        'q' => (string) ($_POST['revenir_q'] ?? $_GET['q'] ?? ''),
    ] + $sup, fn($v) => $v !== '' && $v !== null);
    return '?' . http_build_query($q);
};

/** Une liste à moi, ou rien. On ne dit pas laquelle existe chez les autres. */
$ma_liste = function (string $id) use ($moi, $retour): array {
    $l = $id !== '' ? carnet_liste_de($id, $moi) : null;
    if (!$l) {
        rediriger($retour() . '&err=' . rawurlencode('Cette liste n’existe pas, ou n’est pas la vôtre.'));
    }
    return $l;
};

$ma_fiche = function (string $id) use ($moi, $retour): array {
    $c = $id !== '' ? carnet_contact_de($id, $moi) : null;
    if (!$c) {
        rediriger($retour() . '&err=' . rawurlencode('Cette fiche n’existe pas, ou n’est pas la vôtre.'));
    }
    return $c;
};

/* ------------------------------------------------------------------ */
/* Les actions                                                         */
/* ------------------------------------------------------------------ */

if ($page === 'regie-carnet-action') {
    verifier_csrf();
    $quoi = (string) ($_POST['quoi'] ?? '');

    try {
        switch ($quoi) {

            /* ---- les listes ---- */

            case 'liste-creer': {
                $id = carnet_liste_poser($moi, (string) ($_POST['nom'] ?? ''), (string) ($_POST['note'] ?? ''));
                rediriger('?p=regie-carnet&l=' . rawurlencode($id) . '&ok='
                    . rawurlencode('Liste créée. Ajoutez-y des adresses.'));
            }

            case 'liste-renommer': {
                $l = $ma_liste((string) ($_POST['liste_id'] ?? ''));
                carnet_liste_renommer((string) $l['id'], (string) ($_POST['nom'] ?? ''), (string) ($_POST['note'] ?? ''));
                rediriger('?p=regie-carnet&l=' . rawurlencode((string) $l['id']) . '&ok=' . rawurlencode('Liste renommée.'));
            }

            case 'liste-supprimer': {
                $l = $ma_liste((string) ($_POST['liste_id'] ?? ''));
                $n = count(carnet_destinataires((string) $l['id']));
                carnet_liste_supprimer((string) $l['id']);
                journal_ecrire($u, 'carnet.liste', 'liste', (string) $l['id'], (string) $l['nom'],
                    $n . ' adresse(s) rendue(s) au carnet');
                rediriger('?p=regie-carnet&ok=' . rawurlencode(
                    'Liste « ' . $l['nom'] . ' » supprimée. Les ' . $n . ' adresses restent au carnet.'));
            }

            /* ---- alimenter ---- */

            case 'importer': {
                /**
                 * C'est ICI que « les listes importées sont automatiquement
                 * sauvegardées » devient vrai : le collage n'atterrit jamais
                 * dans un champ de campagne, il devient des fiches.
                 */
                $liste_id = (string) ($_POST['liste_id'] ?? '');
                $liste_id = $liste_id === '' || $liste_id === 'nouvelle'
                    ? carnet_liste_poser($moi, (string) ($_POST['nouveau_nom'] ?? ''))
                    : (string) $ma_liste($liste_id)['id'];

                $bilan = carnet_importer($moi, $liste_id, (string) ($_POST['adresses'] ?? ''));
                if ($bilan['total'] === 0) {
                    throw new RuntimeException(
                        'Aucune adresse lisible dans ce collage. Une adresse par ligne, '
                        . 'ou « Nom <adresse> ».'
                    );
                }
                journal_ecrire($u, 'carnet.importe', 'liste', $liste_id,
                    (string) carnet_liste($liste_id)['nom'],
                    $bilan['neuves'] . ' nouvelle(s), ' . $bilan['connues'] . ' déjà connue(s)');
                rediriger('?p=regie-carnet&l=' . rawurlencode($liste_id) . '&ok=' . rawurlencode(sprintf(
                    '%d adresse(s) enregistrée(s) : %d nouvelle(s), %d déjà connue(s)%s.',
                    $bilan['total'], $bilan['neuves'], $bilan['connues'],
                    $bilan['illisibles'] ? ', ' . $bilan['illisibles'] . ' ligne(s) illisible(s) ignorée(s)' : ''
                )));
            }

            case 'alimenter': {
                $l = $ma_liste((string) ($_POST['liste_id'] ?? ''));
                $bilan = carnet_alimenter($u, (string) $l['id'], (string) ($_POST['cible'] ?? ''));
                rediriger('?p=regie-carnet&l=' . rawurlencode((string) $l['id']) . '&ok=' . rawurlencode(sprintf(
                    '%d adresse(s) reprises : %d nouvelle(s), %d déjà au carnet.',
                    $bilan['total'], $bilan['neuves'], $bilan['connues']
                )));
            }

            /* ---- une fiche ---- */

            case 'contact-ajouter': {
                $r = carnet_contact_poser($moi, [
                    'email' => $_POST['email'] ?? '',
                    'nom' => $_POST['nom'] ?? '',
                    'organisation' => $_POST['organisation'] ?? '',
                    'telephone' => $_POST['telephone'] ?? '',
                ], 'manuel');
                foreach ((array) ($_POST['listes'] ?? []) as $lid) {
                    carnet_attacher($r['id'], (string) $ma_liste((string) $lid)['id']);
                }
                rediriger($retour() . '&ok=' . rawurlencode($r['neuf']
                    ? 'Adresse ajoutée au carnet.'
                    : 'Cette adresse était déjà au carnet : la fiche a été complétée.'));
            }

            case 'contact-maj': {
                $c = $ma_fiche((string) ($_POST['id'] ?? ''));
                carnet_contact_maj((string) $c['id'], [
                    'email' => $_POST['email'] ?? '',
                    'nom' => $_POST['nom'] ?? '',
                    'organisation' => $_POST['organisation'] ?? '',
                    'telephone' => $_POST['telephone'] ?? '',
                    'note' => $_POST['note'] ?? '',
                ]);
                // Les étiquettes cochées font foi : ce qui est décoché est retiré.
                $voulues = array_map('strval', (array) ($_POST['listes'] ?? []));
                foreach (carnet_listes($moi) as $l) {
                    in_array((string) $l['id'], $voulues, true)
                        ? carnet_attacher((string) $c['id'], (string) $l['id'])
                        : carnet_detacher((string) $c['id'], (string) $l['id']);
                }
                rediriger('?p=regie-carnet-fiche&id=' . rawurlencode((string) $c['id'])
                    . '&ok=' . rawurlencode('Fiche enregistrée.'));
            }

            case 'contact-archiver': {
                $c = $ma_fiche((string) ($_POST['id'] ?? ''));
                $oui = (string) ($_POST['oui'] ?? '1') === '1';
                carnet_contact_archiver((string) $c['id'], $oui);
                rediriger($retour() . '&ok=' . rawurlencode($oui
                    ? 'Adresse archivée : elle reste au carnet, mais ne recevra plus rien.'
                    : 'Adresse réactivée.'));
            }

            case 'contact-retirer': {
                $c = $ma_fiche((string) ($_POST['id'] ?? ''));
                $l = $ma_liste((string) ($_POST['liste_id'] ?? ''));
                carnet_detacher((string) $c['id'], (string) $l['id']);
                rediriger($retour() . '&ok=' . rawurlencode(
                    'Sortie de « ' . $l['nom'] .' ». La fiche reste au carnet.'));
            }

            case 'contact-supprimer': {
                $c = $ma_fiche((string) ($_POST['id'] ?? ''));
                carnet_contact_supprimer((string) $c['id']);
                journal_ecrire($u, 'carnet.supprime', 'contact', (string) $c['id'], (string) $c['email']);
                rediriger($retour() . '&ok=' . rawurlencode('Fiche supprimée du carnet.'));
            }

            /* ---- plusieurs d'un coup ---- */

            case 'lot': {
                /**
                 * Sortir vingt adresses d'une liste, une par une, personne ne
                 * le fait : on referme l'onglet et la liste reste sale. Le
                 * geste groupé n'est donc pas un confort, c'est ce qui rend
                 * le rangement possible.
                 */
                $ids = array_map('strval', (array) ($_POST['choix'] ?? []));
                $sur = (string) ($_POST['sur'] ?? '');
                if (!$ids) {
                    throw new RuntimeException('Cochez d’abord les adresses concernées.');
                }
                /**
                 * Deux listes différentes, et il faut les distinguer.
                 *
                 * « Sortir de » porte toujours sur la liste OUVERTE — c'est
                 * celle dont on lit les lignes. « Ajouter à » porte sur celle
                 * du déroulant, qui est là pour ranger ailleurs. Les
                 * confondre faisait sortir les gens d'une liste qu'on ne
                 * regardait même pas, sans rien dire.
                 */
                $ouverte = (string) ($_POST['revenir_l'] ?? '');
                $choisie = (string) ($_POST['liste_cible'] ?? '');
                $n = 0;
                foreach ($ids as $id) {
                    $c = carnet_contact_de($id, $moi);
                    if (!$c) {
                        continue;
                    }
                    switch ($sur) {
                        case 'archiver':   carnet_contact_archiver($id, true); break;
                        case 'reactiver':  carnet_contact_archiver($id, false); break;
                        case 'retirer':    carnet_detacher($id, (string) $ma_liste($ouverte)['id']); break;
                        case 'ajouter':    carnet_attacher($id, (string) $ma_liste($choisie)['id']); break;
                        case 'supprimer':
                            carnet_contact_supprimer($id);
                            break;
                        default: throw new RuntimeException('Action inconnue.');
                    }
                    $n++;
                }
                if ($sur === 'supprimer') {
                    journal_ecrire($u, 'carnet.supprime', 'contact', null, null, $n . ' fiche(s)');
                }
                rediriger($retour() . '&ok=' . rawurlencode(match ($sur) {
                    'archiver'  => $n . ' adresse(s) archivée(s).',
                    'reactiver' => $n . ' adresse(s) réactivée(s).',
                    'retirer'   => $n . ' adresse(s) sortie(s) de la liste.',
                    'ajouter'   => $n . ' adresse(s) ajoutée(s) à la liste.',
                    default     => $n . ' fiche(s) supprimée(s) du carnet.',
                }));
            }

            default:
                rediriger('?p=regie-carnet');
        }
    } catch (Throwable $e) {
        rediriger($retour() . '&err=' . rawurlencode($e->getMessage()));
    }
}

/* ------------------------------------------------------------------ */
/* L'export                                                            */
/* ------------------------------------------------------------------ */

if ($page === 'regie-carnet-export') {
    /**
     * Un carnet qu'on ne peut pas ressortir est un carnet qu'on n'ose pas
     * remplir. L'export est le contraire d'un enfermement : il dit que ces
     * adresses appartiennent à celui qui les a réunies, pas au logiciel.
     */
    $l = ($_GET['l'] ?? '') !== '' ? $ma_liste((string) $_GET['l']) : null;
    // On repagine à la main : l'export doit tout rendre, pas la page 1.
    $tout = [];
    for ($page_n = 1; ; $page_n++) {
        $paquet = carnet_contacts($moi, ['liste' => $l['id'] ?? '', 'etat' => 'toutes'], $page_n);
        if (!$paquet) {
            break;
        }
        $tout = array_merge($tout, $paquet);
    }

    $nom = 'carnet-' . preg_replace('/[^a-z0-9]+/i', '-', mb_strtolower((string) ($l['nom'] ?? 'complet')))
         . '-' . gmdate('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $nom . '"');
    $sortie = fopen('php://output', 'w');
    // Le BOM : sans lui, Excel lit « Kossi Mensah » comme « Kossi Mensah »
    // et l'utilisateur croit que c'est nous qui abîmons les accents.
    fwrite($sortie, "\xEF\xBB\xBF");
    fputcsv($sortie, ['Adresse', 'Nom', 'Organisation', 'Téléphone', 'État', 'Source', 'Listes', 'Ajoutée le']);
    $etiquettes = carnet_etiquettes($tout);
    foreach ($tout as $c) {
        fputcsv($sortie, [
            $c['email'], $c['nom'] ?? '', $c['organisation'] ?? '', $c['telephone'] ?? '',
            ((int) $c['archive']) ? 'archivée' : 'active',
            CARNET_SOURCES[$c['source']] ?? $c['source'],
            implode(' · ', $etiquettes[(string) $c['id']] ?? []),
            date_fr((string) $c['cree_le']),
        ]);
    }
    fclose($sortie);
    exit;
}

/* ------------------------------------------------------------------ */
/* Une fiche                                                           */
/* ------------------------------------------------------------------ */

if ($page === 'regie-carnet-fiche') {
    $c = $ma_fiche((string) ($_GET['id'] ?? ''));
    vue('carnet-contact', [
        'titre' => ($c['nom'] ?: $c['email']) . ' · Carnet',
        'c' => $c,
        'listes' => carnet_listes($moi),
        'siennes' => array_map(fn(array $l) => (string) $l['id'], carnet_listes_du_contact((string) $c['id'])),
        'desabonne' => desabonne((string) $c['email']),
        'message' => $message,
        'erreur' => $alerte,
    ]);
}

/* ------------------------------------------------------------------ */
/* Le carnet                                                           */
/* ------------------------------------------------------------------ */

$listes = carnet_listes($moi);

/**
 * La liste ouverte est PRISE DANS `$listes`, pas relue à part.
 *
 * `carnet_listes()` est la seule requête qui compte les adresses actives et
 * archivées ; une relecture par `carnet_liste_de()` rendrait la même ligne
 * sans ces deux colonnes, et la carte annonçait « 0 adresse active » au-dessus
 * d'un tableau qui en montrait deux.
 */
$l = null;
if (($_GET['l'] ?? '') !== '') {
    foreach ($listes as $candidate) {
        if ((string) $candidate['id'] === (string) $_GET['l']) {
            $l = $candidate;
            break;
        }
    }
}

$filtres = [
    'liste' => $l['id'] ?? '',
    'etat' => in_array((string) ($_GET['etat'] ?? ''), ['toutes', 'archives'], true)
        ? (string) $_GET['etat'] : 'actives',
    'q' => trim((string) ($_GET['q'] ?? '')),
];
$combien = carnet_combien($moi, $filtres);
$page_n = max(1, (int) ($_GET['n'] ?? 1));
$contacts = carnet_contacts($moi, $filtres, $page_n);

vue('carnet', [
    'titre' => 'Carnet d’adresses',
    'listes' => $listes,
    'liste' => $l,
    'contacts' => $contacts,
    'etiquettes' => carnet_etiquettes($contacts),
    'filtres' => $filtres,
    'combien' => $combien,
    'page_n' => $page_n,
    'pages' => max(1, (int) ceil($combien / CARNET_PAR_PAGE)),
    'total_carnet' => carnet_combien($moi, ['etat' => 'toutes']),
    'cibles' => array_diff_key(regie_cibles_de($u), ['liste' => 1]),
    'message' => $message,
    'erreur' => $alerte,
]);
