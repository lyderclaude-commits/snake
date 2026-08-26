<?php
/** Gestion des comptes : rôle et suspension. */
$u = exiger_role('equipe');

if ($page === 'role' || $page === 'suspendre') {
    verifier_csrf();
    $id = (string) ($_POST['id'] ?? '');
    if ($id === $u['id']) {
        // Se rétrograder ou se suspendre soi-même laisserait l'installation
        // sans administrateur. Refusé, quoi qu'il arrive.
        rediriger('?p=comptes&err=' . urlencode('Vous ne pouvez pas modifier votre propre compte.'));
    }
    if ($page === 'role') {
        $role = (string) ($_POST['role'] ?? '');
        if (!in_array($role, ROLES, true)) {
            rediriger('?p=comptes&err=' . urlencode('Rôle inconnu.'));
        }
        db()->prepare('UPDATE utilisateurs SET role = ? WHERE id = ?')->execute([$role, $id]);
    } else {
        db()->prepare('UPDATE utilisateurs SET suspendu = 1 - suspendu WHERE id = ?')->execute([$id]);
    }
    rediriger('?p=comptes&ok=' . urlencode('Compte mis à jour.'));
}

vue('comptes', ['titre' => 'Comptes', 'liste' => comptes_tous()]);
