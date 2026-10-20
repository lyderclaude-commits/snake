<?php
/**
 * L'administration des contenus d'accueil — services, témoignages, logos
 * clients — et des notifications push.
 *
 * Même grammaire que les articles et les campagnes : une liste ordonnée,
 * une fiche par élément, des champs français et leurs jumeaux anglais.
 */

declare(strict_types=1);

/** Les champs texte d'une saisie `fr[...]` / `en[...]`, nettoyés. */
function saisie_langue(array $v, array $champs): array
{
    $sortie = [];
    foreach ($champs as $k => $max) {
        $x = $v[$k] ?? '';
        if ($k === 'tags') {
            $sortie[$k] = liste_depuis_saisie((string) $x);
        } elseif ($k === 'livrables') {
            $l = [];
            foreach ((array) ($x['titre'] ?? []) as $i => $titre) {
                $titre = mb_substr(trim((string) $titre), 0, 120);
                $texte = mb_substr(trim((string) ($x['texte'][$i] ?? '')), 0, 300);
                if ($titre !== '' || $texte !== '') {
                    $l[] = ['titre' => $titre, 'texte' => $texte];
                }
            }
            $sortie[$k] = $l;
        } else {
            $sortie[$k] = mb_substr(trim(str_replace("\r", '', (string) $x)), 0, $max);
        }
    }
    return $sortie;
}

/** Enregistre l'ordre saisi dans une liste (`ordre[id] = n`). */
function ordre_enregistrer(string $table, array $ordre): void
{
    foreach ($ordre as $id => $o) {
        q("UPDATE $table SET ordre = ? WHERE id = ?", [(int) $o, (int) $id]);
    }
}

function image_saisie(string $v): string
{
    $v = trim($v);
    return $v !== '' && preg_match('#^(https?://|uploads/|assets/)#', $v) ? $v : '';
}

const CHAMPS_SERVICE = ['titre' => 120, 'titre_detail' => 160, 'resume' => 400, 'resume_court' => 200, 'tags' => 0,
    'description' => 800, 'livrables' => 0, 'prix_libelle' => 80, 'prix_valeur' => 80, 'bouton' => 60, 'etiquette' => 60];

function admin_services(string $r1, string $r2, bool $post): never
{
    if ($r1 === '') {
        if ($post) {
            ordre_enregistrer('services', (array) ($_POST['ordre'] ?? []));
            flash('ok', 'Ordre enregistré.');
            rediriger('admin/services');
        }
        vue_admin('services', ['liste' => tous('SELECT * FROM services ORDER BY ordre, id')]);
    }
    $s = $r1 === 'nouveau' ? null : un('SELECT * FROM services WHERE id = ?', [(int) $r1]);
    if ($r1 !== 'nouveau' && !$s) {
        vue_admin('introuvable', [], 404);
    }
    if ($post && $r2 === 'supprimer' && $s) {
        q('DELETE FROM services WHERE id = ?', [$s['id']]);
        flash('ok', 'Service supprimé.');
        rediriger('admin/services');
    }
    $erreur = null;
    if ($post) {
        $fr = saisie_langue((array) ($_POST['fr'] ?? []), CHAMPS_SERVICE);
        $en = saisie_langue((array) ($_POST['en'] ?? []), CHAMPS_SERVICE);
        $ancre = slugifier((string) ($_POST['ancre'] ?? '') ?: $fr['titre']);
        if ($fr['titre'] === '') {
            $erreur = 'Donnez un titre au service.';
        } elseif (valeur('SELECT id FROM services WHERE ancre = ? AND id IS NOT ?', [$ancre, $s['id'] ?? null]) !== null) {
            $erreur = 'Un autre service utilise déjà l’ancre « ' . $ancre . ' ».';
        }
        $champs = [
            'ancre' => $ancre,
            'numero' => mb_substr(trim((string) ($_POST['numero'] ?? '')), 0, 4),
            'icone' => array_key_exists($_POST['icone'] ?? '', ICONES_SERVICE) ? $_POST['icone'] : 'cible',
            'image' => image_saisie((string) ($_POST['image'] ?? '')),
            'vedette' => isset($_POST['vedette']) ? 1 : 0,
            'detail' => isset($_POST['detail']) ? 1 : 0,
            'visible' => isset($_POST['visible']) ? 1 : 0,
            'fr' => json_texte($fr),
            'en' => json_texte($en),
            'modifie_le' => maintenant(),
        ];
        if ($erreur === null) {
            if ($s) {
                q('UPDATE services SET ' . implode(', ', array_map(fn($k) => "$k = ?", array_keys($champs))) . ' WHERE id = ?', [...array_values($champs), $s['id']]);
                $id = (int) $s['id'];
            } else {
                $champs['ordre'] = (int) valeur('SELECT COALESCE(MAX(ordre), 0) + 1 FROM services');
                q('INSERT INTO services (' . implode(', ', array_keys($champs)) . ') VALUES (' . implode(', ', array_fill(0, count($champs), '?')) . ')', array_values($champs));
                $id = (int) db()->lastInsertId();
            }
            flash('ok', 'Service enregistré.');
            rediriger('admin/services/' . $id);
        }
        $s = array_merge($s ?? [], $champs);
    }
    vue_admin('service', ['s' => $s, 'erreur' => $erreur]);
}

function admin_temoignages(string $r1, string $r2, bool $post): never
{
    if ($r1 === '') {
        if ($post) {
            ordre_enregistrer('temoignages', (array) ($_POST['ordre'] ?? []));
            flash('ok', 'Ordre enregistré.');
            rediriger('admin/temoignages');
        }
        vue_admin('temoignages', ['liste' => tous('SELECT * FROM temoignages ORDER BY ordre, id')]);
    }
    $x = $r1 === 'nouveau' ? null : un('SELECT * FROM temoignages WHERE id = ?', [(int) $r1]);
    if ($r1 !== 'nouveau' && !$x) {
        vue_admin('introuvable', [], 404);
    }
    if ($post && $r2 === 'supprimer' && $x) {
        q('DELETE FROM temoignages WHERE id = ?', [$x['id']]);
        flash('ok', 'Témoignage supprimé.');
        rediriger('admin/temoignages');
    }
    $erreur = null;
    if ($post) {
        $fr = saisie_langue((array) ($_POST['fr'] ?? []), ['texte' => 1200, 'auteur' => 120]);
        $en = saisie_langue((array) ($_POST['en'] ?? []), ['texte' => 1200, 'auteur' => 120]);
        $champs = [
            'entreprise' => mb_substr(trim((string) ($_POST['entreprise'] ?? '')), 0, 120),
            'note' => max(1, min(5, (int) ($_POST['note'] ?? 5))),
            'visible' => isset($_POST['visible']) ? 1 : 0,
            'fr' => json_texte($fr),
            'en' => json_texte($en),
            'modifie_le' => maintenant(),
        ];
        if ($fr['texte'] === '') {
            $erreur = 'Le témoignage est vide.';
        } elseif ($champs['entreprise'] === '') {
            $erreur = 'Indiquez l’entreprise du client : son initiale s’affiche dans la pastille.';
        } else {
            if ($x) {
                q('UPDATE temoignages SET ' . implode(', ', array_map(fn($k) => "$k = ?", array_keys($champs))) . ' WHERE id = ?', [...array_values($champs), $x['id']]);
                $id = (int) $x['id'];
            } else {
                $champs['ordre'] = (int) valeur('SELECT COALESCE(MAX(ordre), 0) + 1 FROM temoignages');
                q('INSERT INTO temoignages (' . implode(', ', array_keys($champs)) . ') VALUES (' . implode(', ', array_fill(0, count($champs), '?')) . ')', array_values($champs));
                $id = (int) db()->lastInsertId();
            }
            flash('ok', 'Témoignage enregistré.');
            rediriger('admin/temoignages/' . $id);
        }
        $x = array_merge($x ?? [], $champs);
    }
    vue_admin('temoignage', ['x' => $x, 'erreur' => $erreur]);
}

function admin_clients(string $r1, string $r2, bool $post): never
{
    if ($post && $r1 === '') {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'ordre') {
            ordre_enregistrer('clients', (array) ($_POST['ordre'] ?? []));
            flash('ok', 'Ordre enregistré.');
        } elseif ($action === 'ajouter') {
            $nom = mb_substr(trim((string) ($_POST['nom'] ?? '')), 0, 80);
            if ($nom === '') {
                flash('err', 'Indiquez le nom du client : il s’affiche quand il n’y a pas de logo, et sert de texte alternatif sinon.');
            } else {
                q('INSERT INTO clients (nom, logo, lien, ordre, modifie_le) VALUES (?,?,?,?,?)', [$nom, image_saisie((string) ($_POST['logo'] ?? '')),
                    lien_saisie((string) ($_POST['lien'] ?? '')), (int) valeur('SELECT COALESCE(MAX(ordre), 0) + 1 FROM clients'), maintenant()]);
                flash('ok', '« ' . $nom . ' » ajouté.');
            }
        }
        rediriger('admin/clients');
    }
    if ($r1 === '') {
        vue_admin('clients', ['liste' => tous('SELECT * FROM clients ORDER BY ordre, id')]);
    }
    $c = un('SELECT * FROM clients WHERE id = ?', [(int) $r1]);
    if (!$c) {
        vue_admin('introuvable', [], 404);
    }
    if ($post && $r2 === 'supprimer') {
        q('DELETE FROM clients WHERE id = ?', [$c['id']]);
        flash('ok', '« ' . $c['nom'] . ' » retiré.');
        rediriger('admin/clients');
    }
    if ($post) {
        $nom = mb_substr(trim((string) ($_POST['nom'] ?? '')), 0, 80);
        if ($nom === '') {
            flash('err', 'Le nom ne peut pas être vide.');
            rediriger('admin/clients/' . $c['id']);
        }
        q('UPDATE clients SET nom = ?, logo = ?, lien = ?, visible = ?, modifie_le = ? WHERE id = ?', [$nom, image_saisie((string) ($_POST['logo'] ?? '')),
            lien_saisie((string) ($_POST['lien'] ?? '')), isset($_POST['visible']) ? 1 : 0, maintenant(), $c['id']]);
        flash('ok', 'Client enregistré.');
        rediriger('admin/clients');
    }
    vue_admin('client', ['c' => $c]);
}

function lien_saisie(string $v): string
{
    $v = trim($v);
    return preg_match('#^https?://#i', $v) ? mb_substr($v, 0, 300) : '';
}

/* ------------------------------------------------------------------ */
/* Notifications push                                                  */
/* ------------------------------------------------------------------ */

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
            'lien' => url_langue('admin/messages', 'fr'), 'tag' => 'test', 'icone' => url_langue('assets/images/icone-notification.png', 'fr')]);
        flash($b['echecs'] ? 'err' : 'ok', $b['ok'] . ' notification(s) envoyée(s)' . ($b['echecs'] ? ', ' . $b['echecs'] . ' échec(s) : ' . implode(' ; ', $b['details']) : '')
            . ($b['morts'] ? ', ' . $b['morts'] . ' appareil(s) qui n’existe(nt) plus, retiré(s)' : '') . '.');
        rediriger('admin/profil');
    }
    rediriger('admin/profil');
}
