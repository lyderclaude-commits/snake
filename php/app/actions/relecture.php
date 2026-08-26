<?php
/** File de relecture et décisions. */
$u = exiger_role('equipe');

if ($page === 'decider') {
    verifier_csrf();
    $id = (string) ($_POST['id'] ?? '');
    $vers = (string) ($_POST['vers'] ?? '');
    $motif = trim((string) ($_POST['motif'] ?? ''));
    $d = decor_par_id($id);

    if (!$d) {
        rediriger('?p=relecture&err=' . urlencode('Décor introuvable.'));
    }
    try {
        decor_transition($id, $vers, $u, $motif);
    } catch (RuntimeException $e) {
        rediriger('?p=relecture&err=' . urlencode($e->getMessage()));
    }

    if ($d['auteur_id']) {
        [$titre, $corps] = match ($vers) {
            'publie' => ['Votre décor est publié', $d['titre'] . ' est en ligne dans le catalogue.'],
            'corrections' => ['Corrections demandées', $motif],
            'refuse' => ['Décor refusé', $motif],
            default => ['Décor mis à jour', $d['titre']],
        };
        notifier($d['auteur_id'], 'decision', $titre, $corps, '?p=partenaire');
    }
    rediriger('?p=relecture&ok=' . urlencode('Décision enregistrée.'));
}

$file = decors_en_attente();
$rapports = [];
foreach ($file as $d) {
    $rapports[$d['id']] = lire_prevol($d['id']);
}
vue('relecture', ['titre' => 'Relecture', 'file' => $file, 'rapports' => $rapports]);
