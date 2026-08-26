<?php
/** Contrôle d'entrée — réservé à l'équipe. */
$u = exiger_role('equipe');
$resultat = null;

if ($post) {
    verifier_csrf();
    $jeton = strtoupper(trim((string) ($_POST['jeton'] ?? '')));
    if ($jeton === '') {
        $resultat = ['ok' => false, 'message' => 'Saisissez un code.'];
    } else {
        $r = badge_scanner($jeton, $u['id']);
        $resultat = match (true) {
            $r['ok'] => [
                'ok' => true,
                'message' => 'Entrée validée — ' . $r['decor'],
                'detail' => $r['porteur']
                    ? $r['porteur'] . ' · ' . $r['koris'] . ' Koris crédités'
                    : 'Badge anonyme — présence comptée, aucun Kori',
            ],
            ($r['raison'] ?? '') === 'deja' => [
                'ok' => false,
                'message' => 'Ce badge a déjà été scanné.',
                'detail' => 'Un badge ne vaut qu’une entrée.',
            ],
            default => ['ok' => false, 'message' => 'Code inconnu.', 'detail' => 'Vérifiez les 10 caractères.'],
        };
    }
}

vue('scan', [
    'titre' => 'Contrôle d’entrée',
    'resultat' => $resultat,
    'passages' => passages_recents(),
    'prerempli' => strtoupper(substr((string) ($_GET['code'] ?? ''), 0, 10)),
]);
