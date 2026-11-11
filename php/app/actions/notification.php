<?php
/**
 * La page Notification : push, Telegram et WhatsApp, un seul geste.
 *
 * Avant, il y avait trois écritures : la diffusion push (ici), la chaîne
 * Telegram (dans les canaux), et WhatsApp (par une campagne de la régie).
 * Trois formulaires, donc trois habitudes, et pas une page qui dit « ce
 * qu'on a annoncé aujourd'hui ». Cette page rassemble : le même titre, le
 * même texte, le même lien, et on choisit où ça va.
 *
 * Les mêmes garde-fous que partout : l'équipe peut viser tout le monde, un
 * organisateur ne peut viser que ses invités ; un lien reste chez nous
 * quand on ne peut pas en juger ; WhatsApp exige un modèle approuvé par
 * Meta ; un quota bloque avant l'envoi, jamais pendant. L'envoi reste
 * synchrone et par paquets — sur un mutualisé, une file de dix mille
 * requêtes finit en timeout, et personne ne sait quelle moitié.
 */
$u = exiger_droit('push');

$equipe = droit($u, 'valider');
if (!$equipe && !capacite($u, 'telegram_push')) {
    vue('offre-requise', [
        'titre' => 'Notification',
        'quoi' => OFFRE_LIGNES['telegram_push'][0],
        'aide' => OFFRE_LIGNES['telegram_push'][2],
        'debloque' => offre_qui_debloque('telegram_push'),
    ]);
}

/** Les segments visables : tout le monde pour l'équipe, ses invités pour un organisateur. */
$segments = $equipe ? PUSH_SEGMENTS : ['mes-invites' => 'Les invités de mes campagnes'];

/**
 * Les canaux branchés de CE compte, en un seul appel.
 *
 * Une carte par choix possible : la publication d'un Telegram (une chaîne
 * ne reçoit QU'UN message, quel que soit le nombre de ses lecteurs), le
 * tête-à-tête du bot, et les numéros WhatsApp qui ont donné leur accord.
 */
$mes_canaux = array_values(array_filter(
    canaux_de((string) $u['id']),
    static fn(array $c): bool => (string) $c['statut'] === 'branche'
));

$saisie = [
    'segment' => (string) ($_POST['segment'] ?? array_key_first($segments)),
    'titre' => trim((string) ($_POST['titre'] ?? '')),
    'corps' => trim((string) ($_POST['corps'] ?? '')),
    'lien' => trim((string) ($_POST['lien'] ?? '')),
    'libelle' => trim((string) ($_POST['libelle'] ?? '')),
    'modele_whatsapp' => trim((string) ($_POST['modele_whatsapp'] ?? '')),
];

/** Les portées affichées AVANT de cocher, comme partout ailleurs. */
$portee = [
    'push' => push_disponible() ? count(push_destinataires(array_key_first($segments), (string) $u['id'])) : 0,
    'destinations' => [],   // ['tele:DID', n]
    'tete_a_tete' => [],    // ['c:CID', n]
];
foreach ($mes_canaux as $mc) {
    if ($mc['genre'] === 'telegram') {
        foreach ($mc['destinations'] as $d) {
            $portee['destinations'][(string) $d['id']] = (int) $d['abonnes'];
        }
    }
    if ((int) $mc['abonnes'] > 0) {
        $portee['tete_a_tete'][(string) $mc['id']] = (int) $mc['abonnes'];
    }
}

$message = null;
$erreur = null;
$essai = null;

/* ---------------- l'essai sur soi-même ---------------- */

/**
 * S'envoyer la notification à SOI, avec le compte rendu exact.
 *
 * Le même geste que sur la diffusion push : une enveloppe hors du serveur
 * ne laisse DE FINIR aucune trace autre que ce rapport, et un essai qui
 * part bien vaut mille hypothèses.
 */
if ($post && ($_POST['action'] ?? '') === 'essai') {
    verifier_csrf();
    $miens = push_abonnements_de((string) $u['id']);
    if (!$miens) {
        $erreur = 'Ce compte n’a aucun abonnement push. Ouvrez Mon profil et cliquez '
                . '« Recevoir les notifications » sur CE navigateur, puis revenez ici.';
    } else {
        $lignes = [];
        foreach ($miens as $a) {
            $r = push_envoyer($a, [
                'titre' => 'Essai Wakabi Boost',
                'corps' => 'Si vous lisez ceci, les notifications fonctionnent. '
                         . gmdate('d/m/Y à H:i') . ' UTC.',
                'lien' => base_url() . '/index.php?p=notification',
                'tag' => 'essai-' . substr(sha1((string) $a['id']), 0, 8),
            ]);
            $lignes[] = [
                'agent' => (string) ($a['agent'] ?: 'navigateur inconnu'),
                'hote' => (string) (parse_url((string) $a['endpoint'], PHP_URL_HOST) ?: 'hôte inconnu'),
                'ok' => $r['ok'],
                'code' => $r['code'],
                'message' => $r['message'],
                'mort' => $r['mort'],
            ];
            if ($r['mort']) {
                push_desabonner((string) $a['endpoint']);
            }
        }
        $essai = $lignes;
        $reussis = count(array_filter($lignes, fn(array $l) => $l['ok']));
        $message = $reussis > 0
            ? $reussis . ' notification(s) remise(s) au service de push. Regardez votre écran : '
              . 'si rien n’apparaît, le problème est côté navigateur, pas côté serveur.'
            : null;
        $erreur ??= $reussis === 0 ? 'Aucune notification n’a pu partir. Le détail est ci-dessous.' : null;
    }
}

/* ---------------- l'envoi réel, tous canaux cochés ---------------- */

if ($post && ($_POST['action'] ?? '') !== 'essai') {
    verifier_csrf();

    if (!isset($segments[$saisie['segment']])) {
        $saisie['segment'] = array_key_first($segments);
    }

    /**
     * Les cases cochées, traduites en lignes d'envoi, et re-vérifiées.
     *
     * Une case est une saisie : un identifiant venu du POST ne donne
     * l'écriture à personne d'autre qu'à soi. Chaque ligne porte ce qu'il
     * faut pour envoyer sans relire la base deux fois.
     *
     * @return list<array{legende:string, canal:array, cible:string, n:int}>
     */
    $lignes = [];
    $push_voulu = false;
    foreach ((array) ($_POST['canaux'] ?? []) as $cle) {
        $cle = (string) $cle;
        if ($cle === 'push') {
            $push_voulu = true;
            continue;
        }
        if (str_starts_with($cle, 'd:') && ($d = destination_par_id(substr($cle, 2)))) {
            $canal = canal_par_id((string) $d['canal_id']);
            if ($canal && (string) $canal['proprietaire_id'] === $u['id']) {
                $nom = CANAUX_GENRES[$canal['genre']]['nom'] ?? $canal['genre'];
                $lignes[] = ['legende' => $nom . ' : ' . $d['nom'], 'canal' => $canal,
                             'cible' => (string) $d['cible'], 'n' => 1];
            }
        } elseif (str_starts_with($cle, 'c:') && ($canal = canal_par_id(substr($cle, 2)))) {
            if ((string) $canal['proprietaire_id'] === $u['id']) {
                $nom = CANAUX_GENRES[$canal['genre']]['nom'] ?? $canal['genre'];
                $n = count(abonnes_canal((string) $canal['id']));
                $lignes[] = ['legende' => $nom . ' : tête-à-tête', 'canal' => $canal,
                             'cible' => '*', 'n' => $n];
            }
        }
    }
    $push_coche = $push_voulu;
    $push_voulu = $push_voulu && push_disponible();

    /** Le quota se compte en messages, pas en lecteurs : une chaîne vaut un. */
    $demandes = [];
    foreach ($lignes as $l) {
        $demandes[(string) $l['canal']['genre']] = ($demandes[(string) $l['canal']['genre']] ?? 0) + $l['n'];
    }
    foreach ($demandes as $genre => $n) {
        $q = quota_canal($u, (string) $genre, (int) $n);
        if (!$q['ok']) {
            $erreur ??= $q['message'];
        }
    }

    $erreur = match (true) {
        $erreur !== null => $erreur,
        $saisie['titre'] === '' => 'Un titre, sinon la notification s’affiche vide.',
        mb_strlen($saisie['titre']) > 60 => 'Le titre est tronqué au-delà de 60 caractères sur la plupart des téléphones.',
        $saisie['corps'] === '' => 'Écrivez le texte du message.',
        mb_strlen($saisie['corps']) > 320 => 'Le texte est coupé à 320 caractères : raccourcissez-le.',
        $saisie['lien'] !== '' && !$equipe && !redirection_autorisee($saisie['lien'])
            => 'Le lien doit mener vers ' . implode(' ou ', WAKABI_DOMAINES) . '.',
        $saisie['lien'] !== '' && !filter_var($saisie['lien'], FILTER_VALIDATE_URL)
            => 'Ce lien n’est pas une adresse valide.',
        // Aucun canal, ou le push demandé sans les fonctions de chiffrement :
        // dire lequel des deux problèmes, sinon l'écran en cherche un où
        // il n'y en a pas.
        !$push_coche && !$lignes => 'Cochez au moins un canal : sans lui, rien ne part.',
        $push_coche && !push_disponible() && !$lignes
            => 'Cet hébergement n’a pas les fonctions de chiffrement du push (OpenSSL, hash_hkdf).',
        default => null,
    };

    if ($erreur === null) {
        /** Un compteur d'un seul tenant : la même fenêtre que partout ailleurs. */
        $cle_debit = 'notification|' . $u['id'];
        if (debit_depasse($cle_debit)) {
            $erreur = 'Trop d’envois d’affilée. Réessayez dans ' . FENETRE_MINUTES . ' minutes.';
        } else {
            debit_noter($cle_debit);

            $libelle = mb_substr($saisie['libelle'] !== '' ? $saisie['libelle'] : 'Ouvrir', 0, 40);

            /* ---- le push navigateur ---- */
            if ($push_voulu) {
                $objets = push_destinataires($saisie['segment'], (string) $u['id']);
                $rapport = push_diffuser($objets, [
                    'titre' => mb_substr($saisie['titre'], 0, 60),
                    // Le push du navigateur est court : on coupe net, proprement.
                    'corps' => mb_substr($saisie['corps'], 0, PUSH_APERCU),
                    'lien' => $saisie['lien'] ?: base_url() . '/index.php?p=decors',
                    'tag' => 'notification-' . substr(sha1($saisie['titre'] . $saisie['corps']), 0, 8),
                ]);
                diffusion_enregistrer((string) $u['id'], [
                    'segment' => $saisie['segment'],
                    'titre' => $saisie['titre'],
                    'corps' => $saisie['corps'],
                    'lien' => $saisie['lien'],
                ], count($objets), $rapport);
                $message = 'Push : ' . sprintf(
                    '%d notification(s) vers %d personne(s), %d échec(s).',
                    $rapport['envoyes'], $rapport['personnes'], $rapport['echecs']
                );
                if ($rapport['envoyes'] === 0 && $rapport['echecs'] === 0) {
                    $message = 'Push : personne n’est abonné dans ce segment, rien n’est parti.';
                }
                if ($rapport['echecs'] > 0 && !empty($rapport['motifs'])) {
                    $erreur ??= 'Push, échecs : ' . implode(' · ', array_map(
                        fn(string $motif, int $n) => $n . ' × ' . $motif,
                        array_keys($rapport['motifs']), array_values($rapport['motifs'])
                    ));
                }
            }

            /* ---- les canaux branchés, un reinforce de la même enveloppe ---- */
            foreach ($lignes as $l) {
                $genre = (string) $l['canal']['genre'];
                $n_ok = 0;
                $n_echec = 0;
                $motifs = [];
                if ($l['cible'] !== '*') {
                    // Une chaîne reçoit UNE publication, pour tous ses lecteurs.
                    $r = canal_remettre($l['canal'], $l['cible'], [
                        'titre' => $saisie['titre'],
                        'corps' => $saisie['corps'],
                        'lien' => $saisie['lien'],
                        'libelle' => $libelle,
                        'modele' => $genre === 'whatsapp' ? $saisie['modele_whatsapp'] : null,
                    ]);
                    if ($r['ok']) {
                        $n_ok = 1;
                    } else {
                        $n_echec = 1;
                        $motifs[] = $r['message'];
                    }
                } else {
                    // Le tête-à-tête : un message par personne joignable.
                    foreach (abonnes_canal((string) $l['canal']['id']) as $a) {
                        $r = canal_remettre($l['canal'], (string) $a['cible'], [
                            'titre' => $saisie['titre'],
                            'corps' => $saisie['corps'],
                            'lien' => $saisie['lien'],
                            'libelle' => $libelle,
                            'modele' => $genre === 'whatsapp' ? $saisie['modele_whatsapp'] : null,
                        ]);
                        if ($r['ok']) {
                            $n_ok++;
                        } else {
                            $n_echec++;
                            $motifs[] = $r['message'];
                        }
                        // Ne pas dérouler une liste morte jusqu'au bout :
                        // 25 échecs d'affilée, c'est le réglage à revoir.
                        if ($n_echec >= 25) {
                            $motifs[] = 'envoi stoppé après 25 échecs d’affilée';
                            break;
                        }
                    }
                }
                diffusion_enregistrer((string) $u['id'], [
                    'segment' => $genre,
                    'titre' => $saisie['titre'],
                    'corps' => $saisie['corps'],
                    'lien' => $saisie['lien'],
                ], $l['n'], ['envoyes' => $n_ok, 'personnes' => $l['n'], 'echecs' => $n_echec,
                             'nettoyes' => 0, 'motifs' => []]);
                $message ??= '';
                $message .= ($message === '' ? '' : ' ') . $l['legende'] . ' : '
                          . ($l['n'] === 1 ? ($n_ok ? 'parti.' : 'échec.')
                                           : sprintf('%d/%d remis.', $n_ok, $l['n']));
                if ($motifs) {
                    $erreur ??= $l['legende'] . ' : ' . $motifs[0];
                }
            }

            $totals = sprintf(' %d personnes cochées au total.', array_sum(array_map(fn($l) => $l['n'], $lignes))
                + ($push_voulu ? count(push_destinataires($saisie['segment'], (string) $u['id'])) : 0));
            $message = trim((string) $message) === '' ? null : ($message . $totals);
        }
    }
}

/* ---------------- re-calcul des portées pour l'affichage ---------------- */

/**
 * La portée affichée suit le segment CHOISI. Une portée figée à l'ouverture
 * écrirait dans le vide en silence.
 */
$portee['push'] = push_disponible() && isset($segments[$saisie['segment']])
    ? count(push_destinataires($saisie['segment'], (string) $u['id'])) : 0;

/**
 * L'historique : le sien, ou celui de tout le monde pour l'équipe.
 *
 * La table `diffusions` porte déjà tout : le canal élargi à Telegram et
 * WhatsApp se range dans le même journal, le `segment` y devient le nom
 * du canal. Une seule liste, aucun scénario à côté.
 */
$qui = $equipe ? null : (string) $u['id'];
$page_n = max(1, (int) ($_GET['n'] ?? 1));
$combien_diff = diffusions_combien($qui);

vue('notification', [
    'titre' => 'Notification',
    'historique' => diffusions_lire($qui, $page_n),
    'combien_diff' => $combien_diff,
    'page_n' => $page_n,
    'pages' => max(1, (int) ceil($combien_diff / DIFFUSIONS_PAR_PAGE)),
    'ce_mois' => diffusions_du_mois($qui),
    'segments' => $segments,
    'portee' => $portee,
    'mes_canaux' => $mes_canaux,
    'saisie' => $saisie,
    'message' => $message,
    'erreur' => $erreur,
    'equipe' => $equipe,
    'disponible' => push_disponible(),
    'prerequis' => push_pre_requis(),
    'mes_abonnements' => count(push_abonnements_de((string) $u['id'])),
    'essai' => $essai,
]);
