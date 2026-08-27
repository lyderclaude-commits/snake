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
 * formulaire de création de compte et la limite de campagnes lisent la même
 * table. Une offre changée sur la vitrine sans l'être dans le produit est
 * une promesse qu'on ne tient pas.
 *
 * `campagnes` et `telechargements` : -1 signifie sans limite.
 */
const FORMULES = [
    'decouverte' => ['nom' => 'Découverte', 'prix' => 0,     'campagnes' => 1,  'telechargements' => 50],
    'impact'     => ['nom' => 'Impact',     'prix' => 5000,  'campagnes' => 3,  'telechargements' => 500],
    'croissance' => ['nom' => 'Croissance', 'prix' => 12000, 'campagnes' => 5,  'telechargements' => 2000],
    'mouvement'  => ['nom' => 'Mouvement',  'prix' => 30000, 'campagnes' => -1, 'telechargements' => -1],
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

/** Le quota d'un compte, ou -1 s'il n'y en a pas. L'équipe n'en a jamais. */
function quota(array $u, string $quoi): int
{
    if (($u['role'] ?? '') === 'equipe') {
        return -1;
    }
    return FORMULES[$u['formule'] ?? 'decouverte'][$quoi] ?? FORMULES['decouverte'][$quoi];
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
