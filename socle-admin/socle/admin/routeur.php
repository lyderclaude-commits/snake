<?php
/**
 * L'administration : routes, actions et droits.
 *
 * Deux rôles. L'ÉDITEUR écrit — articles, collections, médias — et lit les
 * demandes de contact. L'ADMINISTRATEUR fait tout cela, et touche en plus
 * à ce qui peut casser le site : réglages, comptes, import, traduction,
 * sauvegarde.
 *
 * Toute action qui écrit passe par POST et par le jeton anti-falsification.
 */

declare(strict_types=1);

require __DIR__ . '/collections.php';
require __DIR__ . '/reglages.php';
require __DIR__ . '/push.php';

/** Les adresses que le socle se réserve : une collection ne peut pas s'appeler ainsi. */
const ADMIN_RESERVE = ['', 'premier-compte', 'connexion', 'deconnexion', 'manifest.json', 'recherche', 'articles', 'traduction',
    'push', 'medias', 'messages', 'profil', 'comptes', 'reglages', 'import', 'sauvegarde'];

function vue_admin(string $nom, array $donnees = [], int $code = 200): never
{
    http_response_code($code);
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    $donnees['me'] ??= utilisateur_courant();
    extract($donnees, EXTR_SKIP);
    ob_start();
    // Une vue du site (`app/vues/admin/x.php`) remplace celle du socle du même nom.
    $propre = RACINE . '/app/vues/admin/' . $nom . '.php';
    require is_file($propre) ? $propre : SOCLE . '/vues/' . $nom . '.php';
    $contenu = ob_get_clean();
    require SOCLE . '/vues/_gabarit.php';
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
    return module('messages') ? (int) valeur('SELECT COUNT(*) FROM messages WHERE lu = 0') : 0;
}

/**
 * Les pages que le site ajoute à l'administration (`admin.pages` dans la
 * définition) : `cle => [titre, aide, groupe, admin, action]`, où `action`
 * reçoit `($r1, $r2, $post, $me)` et rend la page (avec `vue_admin`).
 */
function admin_pages_site(): array
{
    $l = [];
    foreach ((array) def('admin.pages', []) as $k => $p) {
        if (!in_array($k, ADMIN_RESERVE, true) && is_callable($p['action'] ?? null)) {
            $l[$k] = (array) $p + ['titre' => ucfirst($k), 'aide' => '', 'groupe' => 'Contenus', 'admin' => false];
        }
    }
    return $l;
}

/**
 * Le menu, construit d'après les modules branchés et ce que le site déclare :
 * `[groupe => [[route, titre, aide], …]]`. Le groupe « Système » est réservé
 * aux administrateurs.
 */
function admin_menu(?array $me): array
{
    $g = ['Contenus' => []];
    if (module('articles')) {
        $g['Contenus'][] = ['admin/articles', 'Articles', 'Le blog'];
    }
    foreach (collections() as $nom => $c) {
        $g[$c['groupe']][] = ['admin/' . $nom, $c['titre'], $c['aide_menu']];
    }
    foreach (admin_pages_site() as $k => $p) {
        if (!$p['admin'] || est_admin($me)) {
            $g[$p['groupe']][] = ['admin/' . $k, $p['titre'], $p['aide']];
        }
    }
    $g['Contenus'][] = ['admin/medias', 'Médiathèque', 'Toutes les images'];
    if (est_admin($me)) {
        $g['Système'][] = ['admin/reglages', 'Réglages', (string) def('admin.aide_reglages', 'Coordonnées, référencement, e-mails')];
        $g['Système'][] = ['admin/comptes', 'Comptes', 'Qui peut publier'];
        if (module('wordpress')) {
            $g['Système'][] = ['admin/import', 'Import WordPress', 'Articles et images'];
        }
        if (module('traduction')) {
            $g['Système'][] = ['admin/traduction', 'Traduction', 'DeepL, français → anglais'];
        }
    }
    return array_filter($g);
}

/** Le manifeste de l'écran d'accueil : sur iPhone, les notifications n'existent que pour un site « installé ». */
function admin_manifeste(): never
{
    header('Content-Type: application/manifest+json; charset=utf-8');
    header('Cache-Control: no-cache');
    $nom = reglage('nom_site');
    echo json_encode([
        'name' => $nom . ' — Administration',
        'short_name' => mb_substr((string) def('marque.nom_court', $nom), 0, 12),
        'start_url' => base_chemin() . '/admin',
        'scope' => base_chemin() . '/',
        'display' => 'standalone',
        'background_color' => '#F8FAFF',
        'theme_color' => marque_couleur(),
        'lang' => 'fr',
        'icons' => [['src' => url_langue(marque_icone(), 'fr'), 'sizes' => '192x192', 'type' => 'image/png']],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

function admin_router(array $s, string $methode): void
{
    // L'administration parle français et garde ses adresses françaises, même visitée depuis /en.
    langue('fr');
    $post = $methode === 'POST';
    if ($post) {
        csrf_verifier();
    }
    $r0 = $s[0] ?? '';
    $r1 = $s[1] ?? '';
    $r2 = $s[2] ?? '';

    if ($r0 === 'manifest.json') {
        admin_manifeste();
    }

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
                    connecter(un('SELECT * FROM utilisateurs WHERE id = ?', [$id]));
                    if (module('wordpress')) {
                        flash('ok', 'Bienvenue ! Prochaine étape : importer les articles de l’ancien site WordPress.');
                        rediriger('admin/import');
                    }
                    flash('ok', 'Bienvenue ! Votre compte administrateur est prêt.');
                    rediriger('admin');
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
            $elements = [];
            if ($t !== '') {
                $noms = array_keys(collections());
                if ($noms) {
                    $rangs = tous('SELECT * FROM elements WHERE collection IN (' . implode(',', array_fill(0, count($noms), '?')) . ') AND (donnees LIKE ? OR en LIKE ?) ORDER BY modifie_le DESC LIMIT 30',
                        [...$noms, $l, $l]);
                    $elements = array_map('element_decoder', $rangs);
                }
            }
            vue_admin('recherche', [
                'q' => $t,
                'articles' => $t === '' || !module('articles') ? [] : tous('SELECT id, titre, statut, publie_le FROM articles WHERE titre LIKE ? OR slug LIKE ? OR contenu LIKE ? ORDER BY modifie_le DESC LIMIT 20', [$l, $l, $l]),
                'elements' => $elements,
                'messages' => $t === '' || !module('messages') ? [] : tous('SELECT id, nom, email, cree_le FROM messages WHERE nom LIKE ? OR email LIKE ? OR donnees LIKE ? OR message LIKE ? ORDER BY id DESC LIMIT 20', [$l, $l, $l, $l]),
            ]);

        /* ---------------- articles ---------------- */
        case 'articles':
            if (!module('articles')) {
                break;
            }
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
                } elseif ($statut === 'auto') {
                    $w[] = "traduction_auto = 1 AND trim(titre_en) <> ''";
                } elseif ($statut === 'sans-en') {
                    $w[] = "(trim(titre_en) = '' OR trim(contenu_en) = '')";
                }
                if ($t !== '') {
                    $w[] = '(titre LIKE ? OR slug LIKE ?)';
                    array_push($p, "%$t%", "%$t%");
                }
                $page = max(1, (int) ($_GET['page'] ?? 1));
                $total = (int) valeur('SELECT COUNT(*) FROM articles WHERE ' . implode(' AND ', $w), $p);
                $liste = tous('SELECT id, slug, titre, image, contenu, categorie, auteur, statut, publie_le, modifie_le, wp_id, lectures, traduction_auto,
                    (length(trim(titre_en)) > 0 AND length(trim(contenu_en)) > 0) AS a_en FROM articles WHERE '
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
                    $tr = module('traduction') ? traduire_si_auto($id) : null;
                    flash('ok', (($_POST['statut'] ?? '') === 'publie' ? 'Article enregistré et en ligne.' : 'Brouillon enregistré.')
                        . ($tr === null ? '' : ($tr['ok'] ? ' La version anglaise a été traduite par DeepL : pensez à la relire.' : ' Traduction anglaise impossible : ' . $tr['erreur'])));
                    rediriger('admin/articles/' . $id);
                }
                $a = array_merge($a ?? [], $_POST, ['tags' => json_encode(liste_depuis_saisie((string) ($_POST['tags'] ?? '')))]);
            }
            vue_admin('article', ['a' => $a, 'erreur' => $erreur, 'categories' => array_column(tous("SELECT DISTINCT categorie FROM articles WHERE categorie <> '' ORDER BY categorie"), 'categorie')]);

        /* ---------------- traduction DeepL depuis l'éditeur ---------------- */
        case 'traduction':
            if ($r1 === 'apercu' && $post && module('traduction')) {
                // Ce qui est DANS le formulaire, enregistré ou non : on traduit ce que la personne voit.
                $t = traduction_article(['titre' => (string) ($_POST['titre'] ?? ''), 'extrait' => (string) ($_POST['extrait'] ?? ''),
                    'contenu' => contenu_stocker((string) ($_POST['contenu'] ?? ''))]);
                if ($t['ok']) {
                    $t['contenu_en'] = contenu_afficher($t['contenu_en']);
                }
                json_reponse($t, $t['ok'] ? 200 : 502);
            }
            break;

        /* ---------------- notifications push de cet appareil ---------------- */
        case 'push':
            if (module('push')) {
                admin_push($r1, $post, $me);
            }
            break;

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
            if (!module('messages')) {
                break;
            }
            if ($r1 === '') {
                if ($post && ($_POST['action'] ?? '') === 'tout-lu') {
                    q('UPDATE messages SET lu = 1 WHERE lu = 0');
                    rediriger('admin/messages');
                }
                vue_admin('messages', ['liste' => array_map('message_decoder', tous('SELECT * FROM messages ORDER BY lu, id DESC LIMIT 200'))]);
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
            vue_admin('message', ['m' => message_decoder($m)]);

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

    /* ---------------- les collections déclarées par le site ---------------- */
    if (($col = collection($r0)) && !in_array($r0, ADMIN_RESERVE, true)) {
        admin_collection($col, $r1, $r2, $post);
    }

    /* ---------------- les pages ajoutées par le site ---------------- */
    $pages = admin_pages_site();
    if (isset($pages[$r0])) {
        if ($pages[$r0]['admin']) {
            $me = exiger_connexion(true);
        }
        ($pages[$r0]['action'])($r1, $r2, $post, $me);
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
            admin_reglages($r1, $post);

        case 'import':
            if (!module('wordpress')) {
                break;
            }
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

        case 'traduction':
            if (!module('traduction')) {
                break;
            }
            if ($post && $r1 === 'tester') {
                json_reponse(deepl_usage());
            }
            if ($post && $r1 === 'lot') {
                json_reponse(traduire_lot());
            }
            if ($post) {
                $v = ['deepl_cible' => array_key_exists($_POST['deepl_cible'] ?? '', DEEPL_CIBLES) ? $_POST['deepl_cible'] : 'EN-GB',
                    'deepl_auto' => isset($_POST['deepl_auto']) ? '1' : '0'];
                // La clé n'est jamais réaffichée : un champ vide la garde, la case l'efface.
                $cle = trim((string) ($_POST['deepl_cle'] ?? ''));
                if ($cle !== '') {
                    $v['deepl_cle'] = $cle;
                } elseif (isset($_POST['deepl_effacer'])) {
                    $v['deepl_cle'] = '';
                }
                reglages_enregistrer($v);
                $u = deepl_actif() ? deepl_usage() : null;
                flash($u === null || $u['ok'] ? 'ok' : 'err', $u === null ? 'Réglages enregistrés. Aucune clé : la traduction automatique est coupée.'
                    : ($u['ok'] ? 'Réglages enregistrés. DeepL répond : ' . number_format($u['utilises'], 0, ',', ' ') . ' caractères utilisés ce mois-ci sur '
                        . number_format($u['limite'], 0, ',', ' ') . '.' : 'Réglages enregistrés, mais ' . $u['erreur']));
                rediriger('admin/traduction');
            }
            vue_admin('traduction', [
                'a_traduire' => articles_a_traduire(),
                'auto' => (int) valeur("SELECT COUNT(*) FROM articles WHERE traduction_auto = 1 AND trim(titre_en) <> ''"),
                'releves' => (int) valeur("SELECT COUNT(*) FROM articles WHERE traduction_auto = 0 AND trim(titre_en) <> '' AND trim(contenu_en) <> ''"),
                'publies' => (int) valeur("SELECT COUNT(*) FROM articles WHERE statut = 'publie'"),
            ]);

        case 'sauvegarde':
            if ($post) {
                $f = dossier_donnees() . '/sauvegarde-' . date('Ymd-His') . '.sqlite';
                db()->exec('VACUUM INTO ' . db()->quote($f));
                header('Content-Type: application/vnd.sqlite3');
                header('Content-Disposition: attachment; filename="' . slugifier(reglage('nom_site'), 'site') . '-' . date('Y-m-d') . '.sqlite"');
                header('Content-Length: ' . filesize($f));
                readfile($f);
                @unlink($f);
                exit;
            }
            rediriger('admin/reglages');
    }

    vue_admin('introuvable', [], 404);
}

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
        // La version anglaise : publiée dès qu'elle a un titre et un texte, sinon le français s'affiche.
        'titre_en' => mb_substr(trim((string) ($v['titre_en'] ?? '')), 0, 240),
        'extrait_en' => mb_substr(trim((string) ($v['extrait_en'] ?? '')), 0, 600),
        'contenu_en' => contenu_stocker((string) ($v['contenu_en'] ?? '')),
    ];
    // « Traduction automatique » tant que personne n'a coché « relue » ; une version écrite à la main ne l'est pas.
    $champs['traduction_auto'] = ($v['traduction_auto'] ?? '') === '1' && !isset($v['traduction_relue']) && $champs['titre_en'] !== '' ? 1 : 0;
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

