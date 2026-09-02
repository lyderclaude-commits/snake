<?php
/** Contrôle d'entrée — réservé à l'équipe. */
$u = exiger_droit('scan');
$resultat = null;

if ($post) {
    verifier_csrf();
    $jeton = strtoupper(trim((string) ($_POST['jeton'] ?? '')));
    if ($jeton === '') {
        $resultat = ['ok' => false, 'message' => 'Saisissez un code.'];
    } else {
        $resultat = verdict_scan(badge_scanner($jeton, $u['id']));
    }
}

vue('scan', [
    'titre' => 'Contrôle d’entrée',
    'resultat' => $resultat,
    'passages' => passages_recents(),
    'prerempli' => strtoupper(substr((string) ($_GET['code'] ?? ''), 0, 10)),
]);
