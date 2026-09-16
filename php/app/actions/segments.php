<?php
/**
 * « À qui j'écris » : le pont entre un rapport et une campagne.
 *
 * Cet écran n'existe que pour un décor. C'est voulu : un segment se
 * raisonne sur un événement — ceux qui ne sont pas venus AU GALA, pas
 * « ceux qui ne sont venus nulle part ». On y arrive donc depuis le
 * rapport de cet événement, et on en repart vers la régie avec la cible
 * déjà choisie.
 *
 * La portée est celle du rapport, mot pour mot : un organisateur n'ouvre
 * que ses décors, l'équipe les ouvre tous, et un slug venu de la requête
 * ne donne le décor de personne d'autre. C'est `segment_decor()` qui
 * tranche, et rien d'autre.
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
    /**
     * Un décor introuvable ou qui n'est pas le sien renvoie au rapport,
     * sans message : on ne confirme pas à un curieux que le slug qu'il a
     * essayé existe. C'est la même discipline que `rapport_portee()`.
     */
    rediriger('?p=rapports');
}

$cle = segment_cle((string) ($q['s'] ?? ''));
$segments = segments_du_decor((string) $d['id']);
$ici = $segments[$cle];

/* ---------------- l'export ---------------- */

if ((string) ($q['export'] ?? '') === 'csv') {
    $lignes = segment_liste($cle, (string) $d['id']);
    $csv = segment_csv($d, $cle, $lignes);
    journal_ecrire($u, 'segment.export', 'decor', (string) $d['id'],
        (string) $d['titre'], segment_nom($cle) . ' · ' . count($lignes) . ' ligne(s)');
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . segment_nom_fichier($d, $cle) . '"');
    header('Content-Length: ' . strlen($csv));
    echo $csv;
    exit;
}

/* ---------------- l'écran ---------------- */

/**
 * Peut-on vraiment écrire à ce segment ?
 *
 * La régie a ses propres conditions — un droit, une offre — et les
 * découvrir après avoir cliqué sur « Écrire à ces 96 personnes » serait
 * une impasse. On pose la question ici pour proposer le bouton, ou
 * l'expliquer.
 */
$peut_ecrire = droit($u, 'regie') && ($equipe || capacite($u, 'regie'));

vue('segments', [
    'titre' => 'À qui j’écris · ' . $d['titre'],
    'moi' => $u,
    'decor' => $d,
    'segments' => $segments,
    'cle' => $cle,
    'ici' => $ici,
    'apercu' => segment_liste($cle, (string) $d['id'], 8),
    'peut_ecrire' => $peut_ecrire,
]);
