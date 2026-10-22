<?php
/**
 * Les demandes de contact : reçues par le formulaire du site, rangées dans
 * « Messages », copiées par e-mail au destinataire déclaré et signalées
 * par notification push aux appareils de l'équipe.
 *
 * Les champs du formulaire se déclarent dans la définition du site
 * (`contact.champs`). `nom`, `email` et `message` ont leur colonne ; tous les
 * autres (entreprise, téléphone, budget…) sont rangés ensemble, en JSON,
 * dans `donnees` — un nouveau champ ne demande donc aucune migration.
 */

declare(strict_types=1);

/** Les colonnes propres d'un message ; le reste va dans `donnees`. */
const MESSAGE_COLONNES = ['nom', 'email', 'message'];

/**
 * Les champs du formulaire, dans l'ordre : `cle => [libelle, type, requis, max, sujet]`.
 * `type` : texte, email, tel, whatsapp, texte_long. `sujet` : le champ qui résume la demande.
 */
function contact_champs(): array
{
    $champs = (array) def('contact.champs', [
        'nom' => ['libelle' => 'Nom', 'requis' => true],
        'email' => ['libelle' => 'E-mail', 'type' => 'email', 'requis' => true],
        'telephone' => ['libelle' => 'Téléphone', 'type' => 'tel'],
        'sujet' => ['libelle' => 'Sujet', 'sujet' => true],
        'message' => ['libelle' => 'Message', 'type' => 'texte_long'],
    ]);
    $l = [];
    foreach ($champs as $k => $c) {
        $c = (array) $c;
        $type = (string) ($c['type'] ?? ($k === 'email' ? 'email' : ($k === 'message' ? 'texte_long' : 'texte')));
        $l[$k] = [
            'libelle' => (string) ($c['libelle'] ?? ucfirst($k)),
            'type' => $type,
            'requis' => !empty($c['requis']) || $k === 'nom' || $k === 'email',
            'max' => (int) ($c['max'] ?? ($type === 'texte_long' ? 5000 : 160)),
            'sujet' => !empty($c['sujet']),
            // Une colonne dans la liste des messages (le sujet en a toujours une).
            'liste' => !empty($c['liste']) || !empty($c['sujet']),
        ];
    }
    return $l;
}

/** Un message, avec ses champs supplémentaires remis à plat : `$m['entreprise']`. */
function message_decoder(array $m): array
{
    $d = json_decode((string) ($m['donnees'] ?? '{}'), true);
    foreach (is_array($d) ? $d : [] as $k => $v) {
        if (!in_array($k, MESSAGE_COLONNES, true) && is_scalar($v)) {
            $m[$k] = (string) $v;
        }
    }
    return $m;
}

/** Ce que la demande concerne, en quelques mots (le champ `sujet` de la définition). */
function message_sujet(array $m): string
{
    foreach (contact_champs() as $k => $c) {
        if ($c['sujet'] && trim((string) ($m[$k] ?? '')) !== '') {
            return trim((string) $m[$k]);
        }
    }
    return '';
}

/** Le premier champ libre qui n'est ni le nom, ni l'e-mail, ni en colonne : l'entreprise, le plus souvent. */
function message_complement(array $m): string
{
    foreach (contact_champs() as $k => $c) {
        if (!in_array($k, ['nom', 'email', 'message'], true) && !$c['liste'] && $c['type'] === 'texte' && trim((string) ($m[$k] ?? '')) !== '') {
            return trim((string) $m[$k]);
        }
    }
    return '';
}

/** Le numéro WhatsApp d'un message, en chiffres, s'il y en a un. */
function message_whatsapp(array $m): string
{
    foreach (contact_champs() as $k => $c) {
        if ($c['type'] === 'whatsapp' && ($n = preg_replace('/\D+/', '', (string) ($m[$k] ?? ''))) !== '') {
            return $n;
        }
    }
    return '';
}

/**
 * Une demande de contact : vérifiée, puis enregistrée. Rend l'erreur à
 * afficher, ou null — `$id` reçoit alors le numéro du message (null aussi
 * pour un robot pris au piège, à qui l'on fait croire que tout va bien).
 */
function contact_recevoir(array $v, ?int &$id = null): ?string
{
    if (trim((string) ($v['site_web'] ?? '')) !== '') {
        return null; // le champ piège : un robot l'a rempli, on fait semblant d'accepter
    }
    $d = [];
    $manquants = [];
    foreach (contact_champs() as $k => $c) {
        $d[$k] = mb_substr(trim(str_replace("\r", '', (string) ($v[$k] ?? ''))), 0, $c['max']);
        if ($c['requis'] && $d[$k] === '' && $k !== 'email') {
            $manquants[] = $c['libelle'];
        }
    }
    if ($manquants) {
        $e = def('contact.erreur_requis');
        return socle_t(is_string($e) ? $e : 'Indiquez : ' . implode(', ', $manquants) . '.');
    }
    if (!filter_var($d['email'] ?? '', FILTER_VALIDATE_EMAIL)) {
        return socle_t('Cette adresse e-mail ne semble pas valide.');
    }
    $cle = 'contact:' . ip_client();
    // Huit par heure et par adresse : assez pour une équipe derrière une même box, trop peu pour un robot.
    if (tentatives_recentes($cle, 3600) >= (int) def('contact.limite_heure', 8)) {
        return socle_t((string) def('contact.erreur_limite', 'Vous avez déjà envoyé plusieurs demandes. Réessayez plus tard.'));
    }
    tentative_noter($cle);
    $autres = array_diff_key($d, array_flip(MESSAGE_COLONNES));
    q('INSERT INTO messages (nom, email, message, donnees, ip, langue, cree_le) VALUES (?,?,?,?,?,?,?)',
        [$d['nom'] ?? '', $d['email'] ?? '', $d['message'] ?? '', json_encode($autres, JSON_UNESCAPED_UNICODE), ip_client(), langue(), maintenant()]);
    $id = (int) db()->lastInsertId();
    return null;
}

/**
 * Le formulaire de contact d'une page publique, de bout en bout : reçoit
 * la demande, redirige le visiteur vers `$page?envoye=1`, puis prévient
 * l'équipe. Rend ce que la vue doit afficher : `[erreur, envoye, v]`.
 *
 * Le visiteur n'attend ni l'e-mail ni les notifications : sur PHP-FPM (la
 * plupart des hébergeurs), la réponse part d'abord et les envois se font
 * ensuite. Ailleurs, ils se font avant la redirection : plus lent, mais
 * rien ne se perd.
 */
function contact_traiter(string $page, string $ancre = ''): array
{
    $erreur = null;
    $v = [];
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        csrf_verifier();
        $id = null;
        $erreur = contact_recevoir($_POST, $id);
        if ($erreur === null) {
            $cible = $page . '?envoye=1' . ($ancre !== '' ? '#' . $ancre : '');
            if ($id !== null && function_exists('fastcgi_finish_request')) {
                header('Location: ' . url($cible), true, 303);
                fastcgi_finish_request();
                contact_notifier($id);
                exit;
            }
            if ($id !== null) {
                contact_notifier($id);
            }
            rediriger($cible);
        }
        $v = $_POST;
    }
    return ['erreur' => $erreur, 'envoye' => isset($_GET['envoye']), 'v' => $v];
}

/**
 * Prévient l'équipe d'une demande : e-mail au destinataire, notification push
 * aux appareils abonnés. À appeler APRÈS avoir répondu au visiteur
 * (`fastcgi_finish_request()`) : un serveur SMTP lent ne le fait pas attendre.
 */
function contact_notifier(int $id): void
{
    @set_time_limit(90);
    ignore_user_abort(true);
    $m = un('SELECT * FROM messages WHERE id = ?', [$id]);
    if (!$m) {
        return;
    }
    if (courriel_destinataires()) {
        courriel_demande($m);
    }
    if (module('push')) {
        push_nouveau_message($m);
    }
}

/** La copie d'une demande de contact. Note le résultat sur la demande. */
function courriel_demande(array $m): array
{
    $m = message_decoder($m);
    $champs = [];
    foreach (contact_champs() as $k => $c) {
        if ($k !== 'message') {
            $champs[$c['libelle']] = (string) ($m[$k] ?? '');
        }
    }
    if (count(LANGUES) > 1) {
        $l = (string) ($m['langue'] ?? 'fr');
        $champs['Langue'] = $l === 'fr' ? 'français' : langue_nom($l) . ' (site /' . $l . ') — répondre en ' . langue_nom($l);
    }
    $lien = url_absolue('admin/messages/' . (int) $m['id'], 'fr');
    $wa = message_whatsapp($m);
    $couleur = (string) def('marque.couleur', '#2563EB');

    $texte = 'Nouvelle demande reçue sur le site ' . reglage('nom_site') . ".\n\n";
    foreach ($champs as $k => $v) {
        $texte .= str_pad($k . ' :', 13) . ($v !== '' ? $v : '—') . "\n";
    }
    $texte .= "\nMessage :\n" . ($m['message'] !== '' ? $m['message'] : '—') . "\n\n"
        . "Répondre : répondez simplement à cet e-mail, il part à {$m['email']}.\n"
        . ($wa !== '' ? "WhatsApp : https://wa.me/$wa\n" : '')
        . "Dans l’administration : $lien\n";

    $lignes = '';
    foreach ($champs as $k => $v) {
        $lignes .= '<tr><td style="padding:6px 14px 6px 0;color:#64748B;white-space:nowrap;vertical-align:top">' . e($k)
            . '</td><td style="padding:6px 0;color:#0F172A"><b>' . ($v !== '' ? e($v) : '—') . '</b></td></tr>';
    }
    $logo = (string) def('marque.courriel_logo', '');
    $html = '<!doctype html><html><body style="margin:0;background:#F8FAFF;font-family:Arial,Helvetica,sans-serif">'
        . '<div style="max-width:560px;margin:0 auto;padding:24px 16px">'
        . '<div style="font-size:22px;font-weight:bold;letter-spacing:2px;color:#000;margin-bottom:16px">' . ($logo !== '' ? $logo : e(reglage('nom_site'))) . '</div>'
        . '<div style="background:#fff;border:1px solid #E2E8F5;border-radius:14px;padding:22px">'
        . '<h1 style="font-size:19px;margin:0 0 4px;color:#0F172A">Nouvelle demande de contact</h1>'
        . '<p style="margin:0 0 16px;color:#64748B;font-size:13px">Reçue le ' . e(date_fr($m['cree_le'])) . ' à ' . e(date('H:i', strtotime((string) $m['cree_le']) ?: time())) . '</p>'
        . '<table style="border-collapse:collapse;font-size:14px">' . $lignes . '</table>'
        . '<div style="margin-top:16px;padding:14px 16px;background:#F1F5FC;border-radius:10px;font-size:14px;color:#0F172A;white-space:pre-wrap">'
        . ($m['message'] !== '' ? e($m['message']) : '<span style="color:#94A3B8">Pas de message.</span>') . '</div>'
        . '<p style="margin:18px 0 0">'
        . ($wa !== '' ? '<a href="https://wa.me/' . e($wa) . '" style="display:inline-block;background:#25D366;color:#fff;text-decoration:none;font-weight:bold;padding:10px 16px;border-radius:9px;margin:0 8px 8px 0">Répondre sur WhatsApp</a>' : '')
        . '<a href="' . e($lien) . '" style="display:inline-block;background:' . e($couleur) . ';color:#fff;text-decoration:none;font-weight:bold;padding:10px 16px;border-radius:9px;margin:0 0 8px">Ouvrir dans l’administration</a></p>'
        . '<p style="margin:10px 0 0;color:#94A3B8;font-size:12px">Répondez à cet e-mail : la réponse part directement à ' . e($m['email']) . '.</p>'
        . '</div></div></body></html>';

    $sujet = message_sujet($m);
    $a = courriel_destinataires();
    $r = courriel_envoyer($a, 'Nouvelle demande : ' . $m['nom'] . ($sujet !== '' ? ' — ' . $sujet : ''), $texte, $html, (string) $m['email']);
    $note = ($r['ok'] ? 'envoyé' : 'échec') . ' · ' . date('d/m H:i') . ' · ' . implode(', ', $a) . ' · ' . $r['detail'];
    q('UPDATE messages SET courriel = ? WHERE id = ?', [mb_substr($note, 0, 500), (int) $m['id']]);
    return $r;
}

/** La notification d'une nouvelle demande, à tous les appareils de l'équipe encore actifs. */
function push_nouveau_message(array $m): array
{
    $abonnements = tous('SELECT p.* FROM push_abonnements p JOIN utilisateurs u ON u.id = p.utilisateur_id WHERE u.actif = 1');
    if (!$abonnements) {
        return ['ok' => 0, 'echecs' => 0, 'morts' => 0, 'details' => []];
    }
    $m = message_decoder($m);
    $morceaux = [message_sujet($m), message_complement($m)];
    $morceaux[] = $m['message'] !== '' ? tronquer((string) $m['message'], 110) : (message_whatsapp($m) ?: $m['email']);
    return push_diffuser($abonnements, [
        'titre' => 'Nouvelle demande : ' . $m['nom'] . (($m['langue'] ?? 'fr') !== 'fr' ? ' (' . strtoupper((string) $m['langue']) . ')' : ''),
        'corps' => implode(' · ', array_filter($morceaux, fn($x) => $x !== '')),
        'lien' => url_langue('admin/messages/' . (int) $m['id'], 'fr'),
        'tag' => 'message-' . (int) $m['id'],
        'icone' => url_langue(marque_icone(), 'fr'),
    ]);
}
