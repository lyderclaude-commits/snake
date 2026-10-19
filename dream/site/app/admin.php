<?php
/**
 * L'administration : routes, actions et droits.
 *
 * Deux rôles. L'ÉDITEUR écrit — articles, campagnes, médias — et lit les
 * demandes de contact. L'ADMINISTRATEUR fait tout cela, et touche en plus
 * à ce qui peut casser le site : réglages, comptes, import, sauvegarde.
 *
 * Toute action qui écrit passe par POST et par le jeton anti-falsification.
 */

declare(strict_types=1);

function vue_admin(string $nom, array $donnees = [], int $code = 200): never
{
    http_response_code($code);
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    $donnees['me'] ??= utilisateur_courant();
    extract($donnees, EXTR_SKIP);
    ob_start();
    require RACINE . '/app/vues/admin/' . $nom . '.php';
    $contenu = ob_get_clean();
    require RACINE . '/app/vues/admin/_gabarit.php';
    exit;
}

function json_reponse(array $d, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($d, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function messages_non_lus(): int
{
    return (int) valeur('SELECT COUNT(*) FROM messages WHERE lu = 0');
}

function admin_router(array $s, string $methode): void
{
    $post = $methode === 'POST';
    if ($post) {
        csrf_verifier();
    }
    $r0 = $s[0] ?? '';
    $r1 = $s[1] ?? '';
    $r2 = $s[2] ?? '';

    /* La toute première visite : créer le compte administrateur. */
    $aucun = (int) valeur('SELECT COUNT(*) FROM utilisateurs') === 0;
    if ($aucun && $r0 !== 'premier-compte') {
        rediriger('admin/premier-compte');
    }

    switch ($r0) {
        case 'premier-compte':
            if (!$aucun) {
                rediriger('admin');
            }
            $erreur = null;
            if ($post) {
                $erreur = compte_valider($_POST, true);
                if ($erreur === null) {
                    q('INSERT INTO utilisateurs (nom, email, mdp, role, cree_le) VALUES (?,?,?,?,?)', [
                        trim($_POST['nom']), trim($_POST['email']), password_hash((string) $_POST['mdp'], PASSWORD_DEFAULT), 'admin', maintenant(),
                    ]);
                    $id = (int) db()->lastInsertId();
                    installer_campagnes_demonstration();
                    connecter(un('SELECT * FROM utilisateurs WHERE id = ?', [$id]));
                    flash('ok', 'Bienvenue ! Prochaine étape : importer les articles de l’ancien site WordPress.');
                    rediriger('admin/import');
                }
            }
            vue_admin('premier-compte', ['erreur' => $erreur, 'v' => $_POST, 'nu' => true]);

        case 'connexion':
            if (utilisateur_courant()) {
                rediriger('admin');
            }
            $erreur = null;
            if ($post) {
                $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
                $cle = 'connexion:' . ip_client();
                if (tentatives_recentes($cle, 900) >= 8) {
                    $erreur = 'Trop d’essais. Patientez un quart d’heure avant de réessayer.';
                } else {
                    $u = un('SELECT * FROM utilisateurs WHERE email = ?', [$email]);
                    if ($u && (int) $u['actif'] === 1 && password_verify((string) ($_POST['mdp'] ?? ''), $u['mdp'])) {
                        tentatives_oublier($cle);
                        if (password_needs_rehash($u['mdp'], PASSWORD_DEFAULT)) {
                            q('UPDATE utilisateurs SET mdp = ? WHERE id = ?', [password_hash((string) $_POST['mdp'], PASSWORD_DEFAULT), $u['id']]);
                        }
                        connecter($u);
                        $suite = (string) ($_POST['suite'] ?? '');
                        rediriger(preg_match('#^admin(/[\w/-]*)?$#', $suite) ? $suite : 'admin');
                    }
                    tentative_noter($cle);
                    $erreur = $u && (int) $u['actif'] !== 1 ? 'Ce compte est suspendu.' : 'E-mail ou mot de passe incorrect.';
                }
            }
            vue_admin('connexion', ['erreur' => $erreur, 'email' => $_POST['email'] ?? '', 'suite' => $_GET['suite'] ?? $_POST['suite'] ?? '', 'nu' => true]);

        case 'deconnexion':
            if ($post) {
                deconnecter();
            }
            rediriger('admin/connexion');
    }

    $me = exiger_connexion();

    switch ($r0) {
        case '':
            vue_admin('bord', ['me' => $me]);

        case 'recherche':
            $t = trim((string) ($_GET['q'] ?? ''));
            $l = "%$t%";
            vue_admin('recherche', [
                'q' => $t,
                'articles' => $t === '' ? [] : tous('SELECT id, titre, statut, publie_le FROM articles WHERE titre LIKE ? OR slug LIKE ? OR contenu LIKE ? ORDER BY modifie_le DESC LIMIT 20', [$l, $l, $l]),
                'campagnes' => $t === '' ? [] : tous('SELECT id, titre, client FROM campagnes WHERE titre LIKE ? OR client LIKE ? LIMIT 20', [$l, $l]),
                'messages' => $t === '' ? [] : tous('SELECT id, nom, email, cree_le FROM messages WHERE nom LIKE ? OR email LIKE ? OR entreprise LIKE ? OR message LIKE ? ORDER BY id DESC LIMIT 20', [$l, $l, $l, $l]),
            ]);

        /* ---------------- articles ---------------- */
        case 'articles':
            if ($r1 === '') {
                $statut = (string) ($_GET['statut'] ?? '');
                $t = trim((string) ($_GET['q'] ?? ''));
                $w = ['1'];
                $p = [];
                if (in_array($statut, ['publie', 'brouillon'], true)) {
                    $w[] = 'statut = ?';
                    $p[] = $statut;
                } elseif ($statut === 'wp') {
                    $w[] = 'wp_id IS NOT NULL';
                }
                if ($t !== '') {
                    $w[] = '(titre LIKE ? OR slug LIKE ?)';
                    array_push($p, "%$t%", "%$t%");
                }
                $page = max(1, (int) ($_GET['page'] ?? 1));
                $total = (int) valeur('SELECT COUNT(*) FROM articles WHERE ' . implode(' AND ', $w), $p);
                $liste = tous('SELECT id, slug, titre, image, contenu, categorie, auteur, statut, publie_le, modifie_le, wp_id, lectures FROM articles WHERE '
                    . implode(' AND ', $w) . ' ORDER BY COALESCE(publie_le, cree_le) DESC, id DESC LIMIT 30 OFFSET ' . (($page - 1) * 30), $p);
                vue_admin('articles', ['liste' => $liste, 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / 30)), 'statut' => $statut, 'q' => $t]);
            }
            $a = $r1 === 'nouveau' ? null : un('SELECT * FROM articles WHERE id = ?', [(int) $r1]);
            if ($r1 !== 'nouveau' && !$a) {
                vue_admin('introuvable', [], 404);
            }
            if ($post && $r2 === 'supprimer' && $a) {
                q('DELETE FROM articles WHERE id = ?', [$a['id']]);
                flash('ok', '« ' . $a['titre'] . ' » a été supprimé.');
                rediriger('admin/articles');
            }
            $erreur = null;
            if ($post) {
                [$erreur, $id] = article_enregistrer($_POST, $a, $me);
                if ($erreur === null) {
                    flash('ok', ($_POST['statut'] ?? '') === 'publie' ? 'Article enregistré et en ligne.' : 'Brouillon enregistré.');
                    rediriger('admin/articles/' . $id);
                }
                $a = array_merge($a ?? [], $_POST, ['tags' => json_encode(liste_depuis_saisie((string) ($_POST['tags'] ?? '')))]);
            }
            vue_admin('article', ['a' => $a, 'erreur' => $erreur, 'categories' => array_column(tous("SELECT DISTINCT categorie FROM articles WHERE categorie <> '' ORDER BY categorie"), 'categorie')]);

        /* ---------------- campagnes ---------------- */
        case 'campagnes':
            if ($r1 === '') {
                if ($post && isset($_POST['ordre']) && is_array($_POST['ordre'])) {
                    foreach ($_POST['ordre'] as $id => $o) {
                        q('UPDATE campagnes SET ordre = ? WHERE id = ?', [(int) $o, (int) $id]);
                    }
                    flash('ok', 'Ordre enregistré.');
                    rediriger('admin/campagnes');
                }
                vue_admin('campagnes', ['liste' => array_map('campagne_decoder', tous('SELECT * FROM campagnes ORDER BY vedette DESC, ordre, id DESC'))]);
            }
            $c = $r1 === 'nouveau' ? null : un('SELECT * FROM campagnes WHERE id = ?', [(int) $r1]);
            if ($r1 !== 'nouveau' && !$c) {
                vue_admin('introuvable', [], 404);
            }
            if ($post && $r2 === 'supprimer' && $c) {
                q('DELETE FROM campagnes WHERE id = ?', [$c['id']]);
                flash('ok', '« ' . $c['titre'] . ' » a été supprimée.');
                rediriger('admin/campagnes');
            }
            $erreur = null;
            if ($post) {
                [$erreur, $id] = campagne_enregistrer($_POST, $c);
                if ($erreur === null) {
                    flash('ok', 'Campagne enregistrée.');
                    rediriger('admin/campagnes/' . $id);
                }
                $c = array_merge($c ?? [], $_POST);
                $c['resultats'] = json_encode(array_map('strval', (array) ($_POST['resultats'] ?? [])));
                $c['tags'] = json_encode(liste_depuis_saisie((string) ($_POST['tags'] ?? '')));
            }
            vue_admin('campagne', ['c' => $c ? campagne_decoder($c) : null, 'erreur' => $erreur]);

        /* ---------------- médias ---------------- */
        case 'medias':
            if ($r1 === 'liste.json') {
                json_reponse(['medias' => array_map(fn($m) => $m + ['url' => url($m['chemin'])],
                    tous('SELECT id, chemin, nom, largeur, hauteur FROM medias ORDER BY id DESC LIMIT 300'))]);
            }
            if ($post && $r1 === 'televerser') {
                $res = media_televerser($_FILES['fichier'] ?? []);
                if (($_POST['format'] ?? '') === 'json' || str_contains((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'json')) {
                    json_reponse($res['ok'] ? ['ok' => true, 'media' => $res['media'] + ['url' => url($res['media']['chemin'])]] : $res, $res['ok'] ? 200 : 422);
                }
                flash($res['ok'] ? 'ok' : 'err', $res['ok'] ? 'Image ajoutée à la médiathèque.' : $res['erreur']);
                rediriger('admin/medias');
            }
            if ($post && $r2 === 'supprimer') {
                media_supprimer((int) $r1);
                flash('ok', 'Image supprimée.');
                rediriger('admin/medias');
            }
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $total = (int) valeur('SELECT COUNT(*) FROM medias');
            vue_admin('medias', [
                'liste' => tous('SELECT * FROM medias ORDER BY id DESC LIMIT 48 OFFSET ' . (($page - 1) * 48)),
                'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / 48)),
                'poids' => (int) valeur('SELECT COALESCE(SUM(taille), 0) FROM medias'),
            ]);

        /* ---------------- messages ---------------- */
        case 'messages':
            if ($r1 === '') {
                if ($post && ($_POST['action'] ?? '') === 'tout-lu') {
                    q('UPDATE messages SET lu = 1 WHERE lu = 0');
                    rediriger('admin/messages');
                }
                vue_admin('messages', ['liste' => tous('SELECT * FROM messages ORDER BY lu, id DESC LIMIT 200')]);
            }
            $m = un('SELECT * FROM messages WHERE id = ?', [(int) $r1]);
            if (!$m) {
                vue_admin('introuvable', [], 404);
            }
            if ($post && $r2 === 'supprimer') {
                q('DELETE FROM messages WHERE id = ?', [$m['id']]);
                flash('ok', 'Message supprimé.');
                rediriger('admin/messages');
            }
            if ($post && $r2 === 'renvoyer') {
                $res = courriel_demande($m);
                flash($res['ok'] ? 'ok' : 'err', $res['ok'] ? 'Demande renvoyée à ' . implode(', ', courriel_destinataires()) . '.' : 'Échec de l’envoi : ' . $res['detail']);
                rediriger('admin/messages/' . $m['id']);
            }
            if ($post && $r2 === 'non-lu') {
                q('UPDATE messages SET lu = 0 WHERE id = ?', [$m['id']]);
                rediriger('admin/messages');
            }
            if (!(int) $m['lu']) {
                q('UPDATE messages SET lu = 1 WHERE id = ?', [$m['id']]);
            }
            vue_admin('message', ['m' => $m]);

        /* ---------------- profil ---------------- */
        case 'profil':
            $erreur = null;
            if ($post) {
                $erreur = compte_valider($_POST + ['mdp' => ''], false, (int) $me['id']);
                if ($erreur === null && ($_POST['mdp'] ?? '') !== '' && !password_verify((string) ($_POST['actuel'] ?? ''), $me['mdp'])) {
                    $erreur = 'Le mot de passe actuel n’est pas le bon.';
                }
                if ($erreur === null) {
                    q('UPDATE utilisateurs SET nom = ?, email = ? WHERE id = ?', [trim($_POST['nom']), trim($_POST['email']), $me['id']]);
                    if (($_POST['mdp'] ?? '') !== '') {
                        q('UPDATE utilisateurs SET mdp = ? WHERE id = ?', [password_hash((string) $_POST['mdp'], PASSWORD_DEFAULT), $me['id']]);
                        session_regenerate_id(true);
                    }
                    flash('ok', 'Profil enregistré.');
                    rediriger('admin/profil');
                }
            }
            vue_admin('profil', ['erreur' => $erreur]);
    }

    /* ---------------- réservé aux administrateurs ---------------- */
    $me = exiger_connexion(true);

    switch ($r0) {
        case 'comptes':
            $erreur = null;
            if ($post) {
                $action = (string) ($_POST['action'] ?? '');
                $cible = isset($_POST['id']) ? un('SELECT * FROM utilisateurs WHERE id = ?', [(int) $_POST['id']]) : null;
                if ($action === 'creer') {
                    $erreur = compte_valider($_POST, true);
                    if ($erreur === null) {
                        q('INSERT INTO utilisateurs (nom, email, mdp, role, cree_le) VALUES (?,?,?,?,?)', [trim($_POST['nom']), trim($_POST['email']),
                            password_hash((string) $_POST['mdp'], PASSWORD_DEFAULT), array_key_exists($_POST['role'] ?? '', ROLES) ? $_POST['role'] : 'editeur', maintenant()]);
                        flash('ok', 'Compte créé. Transmettez son mot de passe à ' . trim($_POST['nom']) . ' de vive voix ou par WhatsApp.');
                        rediriger('admin/comptes');
                    }
                } elseif ($cible && (int) $cible['id'] === (int) $me['id']) {
                    flash('err', 'Vous ne pouvez ni vous suspendre, ni vous rétrograder, ni vous supprimer : l’administration se retrouverait sans administrateur.');
                    rediriger('admin/comptes');
                } elseif ($cible && $action === 'role' && array_key_exists($_POST['role'] ?? '', ROLES)) {
                    q('UPDATE utilisateurs SET role = ? WHERE id = ?', [$_POST['role'], $cible['id']]);
                    flash('ok', 'Rôle de ' . $cible['nom'] . ' : ' . ROLES[$_POST['role']] . '.');
                    rediriger('admin/comptes');
                } elseif ($cible && $action === 'actif') {
                    q('UPDATE utilisateurs SET actif = 1 - actif WHERE id = ?', [$cible['id']]);
                    flash('ok', $cible['nom'] . ((int) $cible['actif'] ? ' est suspendu.' : ' est réactivé.'));
                    rediriger('admin/comptes');
                } elseif ($cible && $action === 'mdp') {
                    $nouveau = substr(strtr(base64_encode(random_bytes(12)), '+/=', 'xyz'), 0, 14);
                    q('UPDATE utilisateurs SET mdp = ? WHERE id = ?', [password_hash($nouveau, PASSWORD_DEFAULT), $cible['id']]);
                    flash('ok', 'Nouveau mot de passe de ' . $cible['nom'] . ' : ' . $nouveau . ' — notez-le, il ne sera plus affiché.');
                    rediriger('admin/comptes');
                } elseif ($cible && $action === 'supprimer') {
                    q('DELETE FROM utilisateurs WHERE id = ?', [$cible['id']]);
                    flash('ok', 'Compte de ' . $cible['nom'] . ' supprimé.');
                    rediriger('admin/comptes');
                }
            }
            vue_admin('comptes', ['liste' => tous('SELECT * FROM utilisateurs ORDER BY role, nom'), 'erreur' => $erreur, 'v' => $erreur ? $_POST : []]);

        case 'reglages':
            $onglet = in_array($r1, ['agence', 'accueil', 'referencement', 'legal'], true) ? $r1 : 'agence';
            if ($post) {
                $permis = array_keys(reglages_defaut());
                $v = [];
                foreach ($permis as $k) {
                    if (array_key_exists($k, $_POST)) {
                        $v[$k] = trim((string) $_POST[$k]);
                    }
                }
                foreach (['mentions_legales', 'confidentialite'] as $k) {
                    if (isset($v[$k])) {
                        $v[$k] = contenu_stocker($v[$k]);
                    }
                }
                foreach (['facebook', 'instagram', 'linkedin', 'tiktok', 'site_url', 'wp_racine'] as $k) {
                    if (isset($v[$k]) && $v[$k] !== '' && !preg_match('#^https?://#i', $v[$k])) {
                        flash('err', 'L’adresse « ' . $v[$k] . ' » doit commencer par https://');
                        rediriger('admin/reglages/' . $onglet);
                    }
                }
                if (isset($v['site_url'])) {
                    $v['site_url'] = rtrim($v['site_url'], '/');
                }
                // Le mot de passe SMTP n'est jamais réaffiché : un champ vide le garde.
                if (($v['smtp_mdp'] ?? '') === '' && !isset($_POST['smtp_mdp_effacer'])) {
                    unset($v['smtp_mdp']);
                }
                foreach (['courriel_expediteur'] as $k) {
                    if (isset($v[$k]) && $v[$k] !== '' && !filter_var($v[$k], FILTER_VALIDATE_EMAIL)) {
                        flash('err', 'L’adresse d’expédition n’est pas valide.');
                        rediriger('admin/reglages/' . $onglet);
                    }
                }
                if (isset($v['smtp_port'])) {
                    $v['smtp_port'] = (string) max(1, min(65535, (int) $v['smtp_port'] ?: 587));
                }
                if (isset($v['smtp_chiffrement']) && !in_array($v['smtp_chiffrement'], ['tls', 'ssl', 'aucun'], true)) {
                    $v['smtp_chiffrement'] = 'tls';
                }
                reglages_enregistrer($v);
                if (isset($_POST['tester'])) {
                    $res = courriel_envoyer(courriel_destinataires(), 'Test d’envoi — ' . reglage('nom_site'),
                        "Ce message confirme que le site sait envoyer des e-mails.\nLes demandes de contact arriveront de la même façon.\n",
                        '<p>Ce message confirme que le site sait envoyer des e-mails.</p><p>Les demandes de contact arriveront de la même façon.</p>');
                    flash($res['ok'] ? 'ok' : 'err', $res['ok']
                        ? 'E-mail de test envoyé à ' . implode(', ', courriel_destinataires()) . ' (' . $res['detail'] . '). Vérifiez la boîte, et les indésirables.'
                        : 'Échec de l’e-mail de test : ' . $res['detail']);
                    rediriger('admin/reglages/' . $onglet);
                }
                flash('ok', 'Réglages enregistrés.');
                rediriger('admin/reglages/' . $onglet);
            }
            vue_admin('reglages', ['onglet' => $onglet, 'r' => reglages()]);

        case 'import':
            if ($post) {
                $racine = rtrim(trim((string) ($_POST['racine'] ?? wp_racine())), '/');
                if ($racine !== '' && !preg_match('#^https?://#', $racine)) {
                    json_reponse(['ok' => false, 'erreur' => 'L’adresse doit commencer par https://'], 422);
                }
                // L'adresse n'est retenue que si WordPress y a répondu : une faute de frappe ne la remplace pas.
                if ($r1 === 'sonder') {
                    $res = wp_sonder($racine);
                    if ($res['ok']) {
                        reglages_enregistrer(['wp_racine' => $racine]);
                    }
                    json_reponse($res);
                }
                if ($r1 === 'lot') {
                    $res = wp_importer_lot($racine, max(1, (int) ($_POST['page'] ?? 1)), ($_POST['ecraser'] ?? '') === '1');
                    if ($res['ok']) {
                        reglages_enregistrer(['wp_racine' => $racine]);
                    }
                    json_reponse($res);
                }
                if ($r1 === 'images') {
                    @set_time_limit(300);
                    if (($_POST['reprendre'] ?? '') === '1') {
                        reglages_enregistrer(['rapatriement_echecs' => '[]']);
                    }
                    json_reponse(['ok' => true] + rapatrier_images(6));
                }
            }
            vue_admin('import', [
                'racine' => wp_racine(),
                'importes' => (int) valeur('SELECT COUNT(*) FROM articles WHERE wp_id IS NOT NULL'),
                'exterieures' => count(images_exterieures()),
                'dernier' => reglage('wp_dernier_import'),
                'curl' => function_exists('curl_init'),
            ]);

        case 'sauvegarde':
            if ($post) {
                $f = dossier_donnees() . '/sauvegarde-' . date('Ymd-His') . '.sqlite';
                db()->exec('VACUUM INTO ' . db()->quote($f));
                header('Content-Type: application/vnd.sqlite3');
                header('Content-Disposition: attachment; filename="liveydream-' . date('Y-m-d') . '.sqlite"');
                header('Content-Length: ' . filesize($f));
                readfile($f);
                @unlink($f);
                exit;
            }
            rediriger('admin/reglages');
    }

    vue_admin('introuvable', [], 404);
}

/** Vérifie nom, e-mail et (si demandé) mot de passe d'un compte. */
function compte_valider(array $v, bool $mdp_requis, ?int $sauf = null): ?string
{
    $nom = trim((string) ($v['nom'] ?? ''));
    $email = trim((string) ($v['email'] ?? ''));
    $mdp = (string) ($v['mdp'] ?? '');
    if ($nom === '') {
        return 'Indiquez un nom.';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Cette adresse e-mail n’est pas valide.';
    }
    if (valeur('SELECT id FROM utilisateurs WHERE email = ? AND id IS NOT ?', [$email, $sauf]) !== null) {
        return 'Un compte utilise déjà cette adresse.';
    }
    if (($mdp_requis || $mdp !== '') && mb_strlen($mdp) < 10) {
        return 'Le mot de passe doit faire au moins 10 caractères : ce compte peut publier sur le site.';
    }
    if ($mdp !== '' && isset($v['mdp2']) && $v['mdp2'] !== $mdp) {
        return 'Les deux mots de passe ne correspondent pas.';
    }
    return null;
}

/** @return array{0: ?string, 1: int} */
function article_enregistrer(array $v, ?array $a, array $me): array
{
    $titre = trim((string) ($v['titre'] ?? ''));
    if ($titre === '') {
        return ['Donnez un titre à l’article.', 0];
    }
    $statut = ($v['statut'] ?? '') === 'publie' ? 'publie' : 'brouillon';
    $slug = slug_libre(trim((string) ($v['slug'] ?? '')) ?: $titre, $a ? (int) $a['id'] : null);
    $date = trim((string) ($v['publie_le'] ?? ''));
    $publie_le = $date !== '' && strtotime($date) !== false ? maintenant(strtotime($date))
        : ($a['publie_le'] ?? null);
    if ($statut === 'publie' && !$publie_le) {
        $publie_le = maintenant();
    }
    $image = trim((string) ($v['image'] ?? ''));
    if ($image !== '' && !preg_match('#^(https?://|uploads/|assets/)#', $image)) {
        $image = '';
    }
    $champs = [
        'titre' => mb_substr($titre, 0, 240),
        'slug' => $slug,
        'extrait' => mb_substr(trim((string) ($v['extrait'] ?? '')), 0, 600),
        'contenu' => contenu_stocker((string) ($v['contenu'] ?? '')),
        'image' => $image,
        'categorie' => mb_substr(trim((string) ($v['categorie'] ?? '')), 0, 80),
        'tags' => json_encode(liste_depuis_saisie((string) ($v['tags'] ?? '')), JSON_UNESCAPED_UNICODE),
        'auteur' => mb_substr(trim((string) ($v['auteur'] ?? '')) ?: $me['nom'], 0, 80),
        'statut' => $statut,
        'publie_le' => $publie_le,
        'modifie_le' => maintenant(),
    ];
    if ($a) {
        $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($champs)));
        // Retouché ici : un nouvel import WordPress ne l'écrasera plus.
        q("UPDATE articles SET $sets, retouche = 1 WHERE id = ?", [...array_values($champs), $a['id']]);
        return [null, (int) $a['id']];
    }
    $champs['cree_le'] = maintenant();
    q('INSERT INTO articles (' . implode(', ', array_keys($champs)) . ') VALUES (' . implode(', ', array_fill(0, count($champs), '?')) . ')',
        array_values($champs));
    return [null, (int) db()->lastInsertId()];
}

/** @return array{0: ?string, 1: int} */
function campagne_enregistrer(array $v, ?array $c): array
{
    $titre = trim((string) ($v['titre'] ?? ''));
    if ($titre === '') {
        return ['Donnez un titre à la campagne.', 0];
    }
    $resultats = [];
    foreach (array_chunk(array_map(fn($x) => mb_substr(trim((string) $x), 0, 40), (array) ($v['resultats'] ?? [])), 2) as $paire) {
        if (($paire[0] ?? '') !== '') {
            $resultats[] = $paire[0];
            $resultats[] = $paire[1] ?? '';
        }
    }
    $image = trim((string) ($v['image'] ?? ''));
    if ($image !== '' && !preg_match('#^(https?://|uploads/|assets/)#', $image)) {
        $image = '';
    }
    $champs = [
        'titre' => mb_substr($titre, 0, 200),
        'client' => mb_substr(trim((string) ($v['client'] ?? '')), 0, 120),
        'categorie' => array_key_exists($v['categorie'] ?? '', CATEGORIES_CAMPAGNE) ? $v['categorie'] : 'digital',
        'annee' => mb_substr(trim((string) ($v['annee'] ?? '')), 0, 20),
        'extrait' => mb_substr(trim((string) ($v['extrait'] ?? '')), 0, 300),
        'description' => mb_substr(trim((string) ($v['description'] ?? '')), 0, 5000),
        'image' => $image,
        'vedette' => isset($v['vedette']) ? 1 : 0,
        'resultats' => json_encode($resultats, JSON_UNESCAPED_UNICODE),
        'tags' => json_encode(liste_depuis_saisie((string) ($v['tags'] ?? '')), JSON_UNESCAPED_UNICODE),
        'publie' => isset($v['publie']) ? 1 : 0,
        'modifie_le' => maintenant(),
    ];
    if ($c) {
        $sets = implode(', ', array_map(fn($k) => "$k = ?", array_keys($champs)));
        q("UPDATE campagnes SET $sets WHERE id = ?", [...array_values($champs), $c['id']]);
        return [null, (int) $c['id']];
    }
    $champs['cree_le'] = maintenant();
    $champs['ordre'] = (int) valeur('SELECT COALESCE(MAX(ordre), 0) + 1 FROM campagnes');
    q('INSERT INTO campagnes (' . implode(', ', array_keys($champs)) . ') VALUES (' . implode(', ', array_fill(0, count($champs), '?')) . ')',
        array_values($champs));
    return [null, (int) db()->lastInsertId()];
}
