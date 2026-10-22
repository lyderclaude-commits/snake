<?php
/**
 * Les notifications push de CET appareil : s'abonner, se désabonner,
 * s'envoyer un essai. Chaque membre de l'équipe gère les siens depuis son
 * profil ; une demande de contact part à tous les appareils abonnés.
 */

declare(strict_types=1);

function admin_push(string $r1, bool $post, array $me): never
{
    if ($r1 === 'cle') {
        try {
            json_reponse(['ok' => push_disponible(), 'cle' => push_disponible() ? vapid()['publique'] : null, 'manque' => array_keys(array_filter(push_pre_requis(), fn($v) => !$v))]);
        } catch (Throwable $e) {
            json_reponse(['ok' => false, 'erreur' => $e->getMessage()], 500);
        }
    }
    if (!$post) {
        rediriger('admin/profil');
    }
    if ($r1 === 'abonner') {
        $endpoint = trim((string) ($_POST['endpoint'] ?? ''));
        $p256dh = trim((string) ($_POST['p256dh'] ?? ''));
        $auth = trim((string) ($_POST['auth'] ?? ''));
        if (!preg_match('#^https?://#', $endpoint) || strlen(b64u_lire($p256dh)) !== 65 || strlen(b64u_lire($auth)) < 16) {
            json_reponse(['ok' => false, 'erreur' => 'Abonnement incomplet : le navigateur n’a pas fourni ses clés.'], 422);
        }
        q('INSERT INTO push_abonnements (utilisateur_id, endpoint, p256dh, auth, appareil, cree_le) VALUES (?,?,?,?,?,?)
            ON CONFLICT(endpoint) DO UPDATE SET utilisateur_id = excluded.utilisateur_id, p256dh = excluded.p256dh, auth = excluded.auth, appareil = excluded.appareil',
            [$me['id'], $endpoint, $p256dh, $auth, appareil_depuis_ua((string) ($_SERVER['HTTP_USER_AGENT'] ?? '')), maintenant()]);
        json_reponse(['ok' => true]);
    }
    if ($r1 === 'desabonner') {
        $id = (int) ($_POST['id'] ?? 0);
        $endpoint = trim((string) ($_POST['endpoint'] ?? ''));
        q('DELETE FROM push_abonnements WHERE utilisateur_id = ? AND (id = ? OR endpoint = ?)', [$me['id'], $id, $endpoint]);
        if (($_POST['format'] ?? '') === 'json') {
            json_reponse(['ok' => true]);
        }
        flash('ok', 'Cet appareil ne recevra plus de notifications.');
        rediriger('admin/profil');
    }
    if ($r1 === 'tester') {
        $abos = tous('SELECT * FROM push_abonnements WHERE utilisateur_id = ?', [$me['id']]);
        if (!$abos) {
            flash('err', 'Aucun appareil abonné : activez d’abord les notifications sur cet appareil.');
            rediriger('admin/profil');
        }
        $b = push_diffuser($abos, ['titre' => 'Test — ' . reglage('nom_site'), 'corps' => 'Les notifications fonctionnent sur cet appareil.',
            'lien' => url_langue('admin/messages', 'fr'), 'tag' => 'test', 'icone' => url_langue(marque_icone(), 'fr')]);
        flash($b['echecs'] ? 'err' : 'ok', $b['ok'] . ' notification(s) envoyée(s)' . ($b['echecs'] ? ', ' . $b['echecs'] . ' échec(s) : ' . implode(' ; ', $b['details']) : '')
            . ($b['morts'] ? ', ' . $b['morts'] . ' appareil(s) qui n’existe(nt) plus, retiré(s)' : '') . '.');
        rediriger('admin/profil');
    }
    rediriger('admin/profil');
}
