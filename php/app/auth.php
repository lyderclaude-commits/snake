<?php
/**
 * Comptes, sessions, limitation de débit.
 *
 * Les mots de passe passent par password_hash() — Argon2 ou bcrypt selon ce
 * que propose l'hébergeur. Aucune bibliothèque à installer : PHP fait ça
 * mieux que ce qu'on écrirait à la main.
 */

declare(strict_types=1);

const ROLES = ['participant', 'partenaire', 'equipe'];
const KORIS_PAR_SCAN = 50;

/**
 * Les formules, telles qu'elles sont vendues sur la vitrine.
 *
 * Elles vivent ici et nulle part ailleurs : la page des offres, le
 * formulaire de création de compte, la limite de campagnes, le filigrane,
 * les Koris et les liens courts lisent tous la même table. Une offre
 * changée sur la vitrine sans l'être dans le produit est une promesse qu'on
 * ne tient pas — et une ligne de la vitrine que rien n'applique dans le
 * code est exactement cela.
 *
 * Trois natures de lignes, et il faut les distinguer honnêtement :
 *
 *  - les COMPTEURS (`campagnes`, `telechargements`, `liens_courts`) :
 *    -1 signifie sans limite, et ils sont réellement opposables ;
 *  - les CAPACITÉS (`filigrane`, `koris`, `redirection`, `ciblage`, `api`) :
 *    le produit les applique lui-même, à la ligne près ;
 *  - les SERVICES (`diffusion`, `telegram_push`, `article`,
 *    `account_manager`) : ils demandent une intervention humaine ou un
 *    système extérieur. Le produit ne peut pas les « exécuter » ; il dit
 *    qu'ils sont dus, et l'équipe les voit sur la fiche du compte. Prétendre
 *    le contraire serait pire que de l'écrire.
 *
 * Toutes les capacités se lisent dans le MÊME sens : `true` veut dire que
 * l'offre la donne. D'où `sans_filigrane` plutôt que `filigrane` — une
 * seule clé qui se lit à l'envers des autres, et un jour quelqu'un pose un
 * filigrane sur les badges qu'on a vendus sans.
 */
const FORMULES = [
    'decouverte' => [
        'nom' => 'Découverte', 'prix' => 0, 'lancement' => 0,
        'campagnes' => 1, 'telechargements' => 50, 'liens_courts' => 0,
        'sans_filigrane' => false, 'koris' => false, 'redirection' => false,
        'stats' => 'base', 'ciblage' => false,
        'diffusion' => false, 'telegram_push' => false, 'api' => false,
        'article' => false, 'account_manager' => false,
    ],
    'impact' => [
        'nom' => 'Impact', 'prix' => 5000, 'lancement' => 2500,
        'campagnes' => 3, 'telechargements' => 500, 'liens_courts' => 20,
        'sans_filigrane' => true, 'koris' => true, 'redirection' => true,
        'stats' => 'completes', 'ciblage' => false,
        'diffusion' => false, 'telegram_push' => false, 'api' => false,
        'article' => false, 'account_manager' => false,
    ],
    'croissance' => [
        'nom' => 'Croissance', 'prix' => 12000, 'lancement' => 6000,
        'campagnes' => 5, 'telechargements' => 2000, 'liens_courts' => 100,
        'sans_filigrane' => true, 'koris' => true, 'redirection' => true,
        'stats' => 'completes', 'ciblage' => true,
        'diffusion' => true, 'telegram_push' => true, 'api' => false,
        'article' => false, 'account_manager' => false,
    ],
    'mouvement' => [
        'nom' => 'Mouvement', 'prix' => 30000, 'lancement' => 15000,
        'campagnes' => -1, 'telechargements' => -1, 'liens_courts' => -1,
        'sans_filigrane' => true, 'koris' => true, 'redirection' => true,
        'stats' => 'completes', 'ciblage' => true,
        'diffusion' => true, 'telegram_push' => true, 'api' => true,
        'article' => true, 'account_manager' => true,
    ],
];

/**
 * Ce que chaque ligne veut dire, pour l'organisateur et pour l'équipe.
 *
 * Le libellé, ce qu'il faut en comprendre, et si le produit l'APPLIQUE
 * lui-même ou si c'est un service que l'équipe rend. La distinction est
 * affichée telle quelle : un organisateur doit savoir ce qui se déclenche
 * tout seul et ce pour quoi il faut nous écrire.
 */
const OFFRE_LIGNES = [
    'campagnes' => ['Campagnes actives', 'compteur',
        'Un brouillon ne compte pas : seules les campagnes en ligne ou en relecture occupent une place.'],
    'telechargements' => ['Téléchargements de badges', 'compteur',
        'Remis à zéro le 1er de chaque mois. Au-delà, vos invités ne peuvent plus télécharger.'],
    'liens_courts' => ['Liens courts', 'compteur',
        'Des adresses courtes et traçables, avec le nombre de clics.'],
    'sans_filigrane' => ['Badges sans filigrane Wakabi', 'capacite',
        'Le filigrane discret disparaît des badges de vos invités.'],
    'koris' => ['QR Code Koris', 'capacite',
        'Chaque présence scannée à l’entrée crédite des Koris à l’invité.'],
    'redirection' => ['Redirection après téléchargement', 'capacite',
        'Après son badge, l’invité arrive sur la page de votre choix.'],
    'stats' => ['Statistiques complètes', 'capacite',
        'Présences réelles, taux de conversion et courbe sur 14 jours, en plus des vues et téléchargements.'],
    'ciblage' => ['Ciblage ville et rubrique', 'capacite',
        'Diffuser une campagne sur toutes les villes, au lieu d’une seule.'],
    'api' => ['Accès API REST', 'capacite',
        'Une clé de lecture pour brancher vos propres outils sur vos chiffres.'],
    'diffusion' => ['Diffusion à la base Wakabi', 'service',
        'Votre campagne poussée à l’audience du guide. L’équipe s’en charge.'],
    'telegram_push' => ['Campagnes Telegram et Web Push', 'service',
        'Canaux de diffusion animés par l’équipe.'],
    'article' => ['Article sponsorisé sur le blog', 'service',
        'Rédigé et publié par la rédaction Wakabi.'],
    'account_manager' => ['Account manager dédié', 'service',
        'Une personne de l’équipe suit votre compte.'],
];

function role_libelle(?string $r): string
{
    return [
        'participant' => 'Participant',
        'partenaire' => 'Organisateur',
        'equipe' => 'Équipe',
    ][$r ?? ''] ?? (string) $r;
}

function formule_libelle(?string $cle): string
{
    return FORMULES[$cle ?? '']['nom'] ?? FORMULES['decouverte']['nom'];
}

/** L'offre d'un compte, au complet. */
function offre(?array $u): array
{
    $cle = $u['formule'] ?? 'decouverte';
    return FORMULES[$cle] ?? FORMULES['decouverte'];
}

/**
 * Le quota d'un compte, ou -1 s'il n'y en a pas. L'équipe n'en a jamais.
 *
 * Le bonus accordé par l'équipe s'ajoute au quota de l'offre : c'est la
 * soupape qui évite d'avoir à faire passer quelqu'un à l'offre supérieure
 * pour un pic d'un soir.
 */
function quota(array $u, string $quoi): int
{
    if (($u['role'] ?? '') === 'equipe') {
        return -1;
    }
    $base = offre($u)[$quoi] ?? FORMULES['decouverte'][$quoi] ?? -1;
    if ($base < 0 || $quoi !== 'telechargements') {
        return (int) $base;
    }
    return (int) $base + max(0, (int) ($u['bonus_telechargements'] ?? 0));
}

/**
 * L'offre de ce compte inclut-elle cette capacité ?
 *
 * L'équipe a tout : elle publie les décors de la maison, et rien ne
 * justifierait de lui poser un filigrane sur ses propres badges.
 */
function capacite(?array $u, string $quoi): bool
{
    if (($u['role'] ?? '') === 'equipe') {
        return true;
    }
    $v = offre($u)[$quoi] ?? false;
    return $quoi === 'stats' ? $v === 'completes' : (bool) $v;
}

/** La première offre qui donne accès à cette ligne. Pour savoir quoi proposer. */
function offre_qui_debloque(string $quoi): ?string
{
    foreach (FORMULES as $cle => $f) {
        $v = $f[$quoi] ?? false;
        $ouvert = match ($quoi) {
            'stats' => $v === 'completes',
            // Les compteurs ne « se débloquent » pas : ils grandissent.
            'campagnes', 'telechargements', 'liens_courts' => false,
            default => (bool) $v,
        };
        if ($ouvert) {
            return $cle;
        }
    }
    return null;
}

function hacher(string $mdp): string
{
    return password_hash($mdp, PASSWORD_DEFAULT);
}

function creer_utilisateur(array $u): string
{
    $id = nouvel_id();
    $formule = $u['formule'] ?? 'decouverte';
    db()->prepare(
        'INSERT INTO utilisateurs (id, email, mot_de_passe, nom, role, formule, organisation, ville, cree_le)
         VALUES (?,?,?,?,?,?,?,?,?)'
    )->execute([
        $id,
        mb_strtolower(trim($u['email'])),
        hacher($u['mot_de_passe']),
        trim($u['nom']),
        $u['role'] ?? 'participant',
        isset(FORMULES[$formule]) ? $formule : 'decouverte',
        $u['organisation'] ?? null,
        $u['ville'] ?? null,
        maintenant(),
    ]);
    return $id;
}

function utilisateur_par_email(string $email): ?array
{
    $s = db()->prepare('SELECT * FROM utilisateurs WHERE email = ?');
    $s->execute([mb_strtolower(trim($email))]);
    return $s->fetch() ?: null;
}

function utilisateur_par_id(string $id): ?array
{
    $s = db()->prepare('SELECT * FROM utilisateurs WHERE id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

/** L'utilisateur connecté, ou null. */
function utilisateur_courant(): ?array
{
    static $cache = false;
    if ($cache !== false) {
        return $cache;
    }
    demarrer_session();
    $id = $_SESSION['utilisateur'] ?? null;
    if (!$id) {
        return $cache = null;
    }
    $u = utilisateur_par_id($id);
    if (!$u || (int) $u['suspendu'] === 1) {
        unset($_SESSION['utilisateur']);
        return $cache = null;
    }
    return $cache = $u;
}

function connecter(string $id): void
{
    demarrer_session();
    // Contre la fixation de session : l'identifiant change à la connexion.
    session_regenerate_id(true);
    $_SESSION['utilisateur'] = $id;
}

function deconnecter(): void
{
    demarrer_session();
    $_SESSION = [];
    session_destroy();
}

/** Exige un rôle ; redirige sinon. Le seul verrou d'accès de l'application. */
function exiger_role(string ...$roles): array
{
    $u = utilisateur_courant();
    if (!$u) {
        rediriger('?p=connexion');
    }
    if (!in_array($u['role'], $roles, true)) {
        rediriger($u['role'] === 'partenaire' ? '?p=partenaire' : '?p=compte');
    }
    return $u;
}

/* ---------------- limitation de débit ---------------- */

const TENTATIVES_MAX = 8;
const FENETRE_MINUTES = 15;

/**
 * Compte les essais récents pour un couple e-mail + adresse.
 *
 * En base et non en session : sinon il suffirait de vider ses cookies. C'est
 * la seule protection contre l'essai de mots de passe en boucle.
 */
function debit_depasse(string $cle): bool
{
    $depuis = gmdate('Y-m-d\TH:i:s\Z', time() - FENETRE_MINUTES * 60);
    $s = db()->prepare('SELECT COUNT(*) AS n FROM tentatives WHERE cle = ? AND cree_le > ?');
    $s->execute([$cle, $depuis]);
    return (int) $s->fetch()['n'] >= TENTATIVES_MAX;
}

function debit_noter(string $cle): void
{
    db()->prepare('INSERT INTO tentatives (cle, cree_le) VALUES (?,?)')
        ->execute([$cle, maintenant()]);
}

function debit_effacer(string $cle): void
{
    db()->prepare('DELETE FROM tentatives WHERE cle = ?')->execute([$cle]);
}

function cle_debit(string $email): string
{
    return mb_strtolower(trim($email)) . '|' . ($_SERVER['REMOTE_ADDR'] ?? '?');
}

/* ================= vérification de l'adresse e-mail ================= */

/** 48 h : assez pour un week-end, assez court pour qu'un lien volé expire. */
const VERIF_HEURES = 48;

/**
 * Pose un jeton de vérification et rend le lien à envoyer.
 *
 * Un seul jeton vivant par compte : en redemander un invalide le précédent.
 * C'est ce qu'attend quelqu'un qui clique « renvoyer » parce que le premier
 * message est perdu — deux liens valides en même temps, eux, ne servent
 * qu'à compliquer le retrait.
 */
function creer_jeton_verification(string $utilisateur_id): string
{
    $jeton = bin2hex(random_bytes(24));
    db()->prepare('UPDATE utilisateurs SET verif_jeton = ?, verif_expire_le = ? WHERE id = ?')
        ->execute([$jeton, maintenant(time() + VERIF_HEURES * 3600), $utilisateur_id]);
    return $jeton;
}

function lien_verification(string $jeton): string
{
    return base_url() . '/index.php?p=verifier&j=' . rawurlencode($jeton);
}

/**
 * Consomme un jeton. À usage unique, et daté.
 *
 * @return array{ok: bool, message: string, utilisateur: ?array}
 */
function consommer_jeton_verification(string $jeton): array
{
    if ($jeton === '') {
        return ['ok' => false, 'message' => 'Lien de vérification incomplet.', 'utilisateur' => null];
    }
    $s = db()->prepare('SELECT * FROM utilisateurs WHERE verif_jeton = ?');
    $s->execute([$jeton]);
    $u = $s->fetch() ?: null;
    if (!$u) {
        return ['ok' => false, 'message' => 'Ce lien a déjà servi, ou il a été remplacé par un plus récent.', 'utilisateur' => null];
    }
    if (($u['verif_expire_le'] ?? '') !== '' && $u['verif_expire_le'] < maintenant()) {
        return ['ok' => false, 'message' => 'Ce lien a expiré. Demandez-en un nouveau depuis votre compte.', 'utilisateur' => $u];
    }
    db()->prepare('UPDATE utilisateurs SET email_verifie_le = ?, verif_jeton = NULL, verif_expire_le = NULL WHERE id = ?')
        ->execute([maintenant(), $u['id']]);
    return ['ok' => true, 'message' => 'Votre adresse est vérifiée. Merci !', 'utilisateur' => $u];
}

/**
 * L'adresse de ce compte est-elle confirmée ?
 *
 * Faux tant que le transport n'est pas branché serait cruel dans l'autre
 * sens : ici on répond sur le FAIT, et c'est l'appelant qui décide s'il en
 * fait une condition — voir `verification_exigee()`.
 */
function email_verifie(?array $u): bool
{
    return $u !== null && !empty($u['email_verifie_le']);
}

/**
 * Peut-on EXIGER une adresse vérifiée ?
 *
 * Seulement si l'on est capable d'envoyer le lien. Exiger une vérification
 * qu'on ne sait pas transmettre, ce n'est pas une sécurité : c'est une
 * porte fermée à clé, sur une application qui marchait la veille.
 */
function verification_exigee(): bool
{
    return function_exists('courriel_branche') && courriel_branche();
}

/**
 * Envoie (ou renvoie) le message de vérification.
 *
 * @return array{ok: bool, message: string}
 */
function envoyer_verification(array $u): array
{
    if (!verification_exigee()) {
        return ['ok' => false, 'message' => 'Aucun transport e-mail n’est réglé : impossible d’envoyer le lien.'];
    }
    $lien = lien_verification(creer_jeton_verification((string) $u['id']));
    return courriel_mis_en_page(
        (string) $u['email'],
        (string) $u['nom'],
        'Confirmez votre adresse — Wakabi Boost',
        'Bienvenue, ' . $u['nom'] . ' !',
        "Il reste une chose à faire : confirmer que cette adresse est bien la vôtre.\n\n"
        . 'Le lien ci-dessous est valable ' . VERIF_HEURES . " heures et ne sert qu'une fois.\n\n"
        . 'Si vous n’êtes pas à l’origine de cette inscription, ignorez ce message : sans confirmation, le compte reste inactif.',
        $lien,
        'Confirmer mon adresse'
    );
}
