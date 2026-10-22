<?php
/**
 * Les écrans des collections : la liste ordonnable et la fiche d'un
 * élément, tirées de la déclaration du site. Même grammaire pour toutes :
 * une liste, une fiche, des champs français et leurs jumeaux anglais.
 */

declare(strict_types=1);

function admin_collection(array $col, string $r1, string $r2, bool $post): never
{
    $base = 'admin/' . $col['nom'];
    if ($r1 === '') {
        if ($post && isset($_POST['ordre']) && is_array($_POST['ordre'])) {
            foreach ($_POST['ordre'] as $id => $o) {
                q('UPDATE elements SET ordre = ? WHERE id = ? AND collection = ?', [(int) $o, (int) $id, $col['nom']]);
            }
            flash('ok', 'Ordre enregistré.');
            rediriger($base);
        }
        $liste = elements_trier(array_map('element_decoder', tous('SELECT * FROM elements WHERE collection = ?', [$col['nom']])), $col['tri']);
        vue_admin('collection', ['col' => $col, 'liste' => $liste]);
    }
    $e = $r1 === 'nouveau' ? null : element((int) $r1, $col['nom']);
    if ($r1 !== 'nouveau' && !$e) {
        vue_admin('introuvable', [], 404);
    }
    $Element = mb_strtoupper(mb_substr($col['element'], 0, 1)) . mb_substr($col['element'], 1);
    if ($post && $r2 === 'supprimer' && $e) {
        q('DELETE FROM elements WHERE id = ?', [$e['id']]);
        flash('ok', '« ' . element_titre($e) . ' » ' . collection_mot($col, 'a été supprimé.', 'a été supprimée.'));
        rediriger($base);
    }
    $erreur = null;
    if ($post) {
        [$erreur, $id, $saisi] = element_enregistrer($col, $e, $_POST);
        if ($erreur === null) {
            flash('ok', $Element . ' ' . collection_mot($col, 'enregistré.', 'enregistrée.'));
            rediriger($base . '/' . $id);
        }
        $e = $saisi;
    }
    vue_admin('element', ['col' => $col, 'e' => $e, 'erreur' => $erreur]);
}
