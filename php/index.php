<?php
/**
 * Point d'entrée unique.
 *
 * Tout passe par ici : ?p=<page>. Un seul fichier exposé, donc une seule
 * porte à surveiller — et aucune réécriture d'URL à configurer, ce qui évite
 * la moitié des ennuis sur un mutualisé.
 */

declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/schema.php';
require __DIR__ . '/app/gabarit.php';
require __DIR__ . '/app/auth.php';
require __DIR__ . '/app/depot.php';
require __DIR__ . '/app/prevol.php';
require __DIR__ . '/app/qr.php';
require __DIR__ . '/app/icones.php';
require __DIR__ . '/app/avatars.php';

assurer_schema();
demarrer_session();

$page = (string) ($_GET['p'] ?? 'accueil');
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
$me = utilisateur_courant();

/**
 * Rend une vue dans le gabarit commun.
 *
 * Le titre est mis de côté AVANT d'inclure la vue, puis restitué. PHP partage
 * la portée entre les deux : une simple variable de boucle nommée `$titre`
 * dans une vue écrasait sinon le titre de la page — et l'accueil s'annonçait
 * « Studio Badge » parce qu'une boucle réutilisait ce nom.
 */
function vue(string $nom, array $donnees = []): never
{
    $_titre = $donnees['titre'] ?? 'Wakabi Boost';
    $_description = $donnees['description'] ?? null;

    extract($donnees, EXTR_SKIP);
    $me = utilisateur_courant();

    ob_start();
    require RACINE . '/app/vues/' . $nom . '.php';
    $contenu = ob_get_clean();

    $titre = $_titre;
    $description = $_description;
    require RACINE . '/app/vues/layout.php';
    exit;
}

/* ---------------- routes ---------------- */

switch ($page) {

    /* ---- vitrine et catalogue ---- */

    case 'accueil':
        vue('accueil', ['titre' => 'Wakabi Boost — le badge qui remplit la salle']);

    case 'decors':
        vue('decors', ['titre' => 'Décors — Wakabi Boost', 'liste' => decors_publies()]);

    case 'decor':
        $d = decor_par_slug((string) ($_GET['slug'] ?? ''));
        if (!$d || $d['statut'] !== 'publie') {
            http_response_code(404);
            vue('introuvable', ['titre' => 'Décor introuvable']);
        }
        $g = json_lire($d['gabarit']);
        // Le gabarit doit rester valide : sinon le Studio dessinerait faux.
        try {
            valider_gabarit($g);
        } catch (GabaritInvalide) {
            http_response_code(404);
            vue('introuvable', ['titre' => 'Décor introuvable']);
        }
        evenement($d['id'], 'vue');
        vue('studio', ['titre' => $d['titre'] . ' — Wakabi Boost', 'd' => $d, 'g' => $g]);

    /* ---- comptes ---- */

    case 'connexion':
        require RACINE . '/app/actions/connexion.php';

    case 'inscription':
        require RACINE . '/app/actions/inscription.php';

    case 'deconnexion':
        verifier_csrf();
        deconnecter();
        rediriger('');

    case 'compte':
        $u = exiger_role('participant', 'partenaire', 'equipe');
        vue('compte', [
            'titre' => 'Mon compte — Wakabi Boost',
            'creations' => creations_de($u['id']),
            'solde' => koris_solde($u['id']),
            'historique' => koris_historique($u['id']),
        ]);

    case 'notifications':
        $u = exiger_role('participant', 'partenaire', 'equipe');
        $liste = notifications_de($u['id']);
        notifications_marquer_lues($u['id']);
        vue('notifications', ['titre' => 'Notifications', 'liste' => $liste]);

    /* ---- partenaire ---- */

    case 'partenaire':
        $u = exiger_role('partenaire', 'equipe');
        vue('partenaire', ['titre' => 'Mes campagnes', 'liste' => decors_de($u['id'])]);

    case 'nouveau':
    case 'modifier':
    case 'soumettre':
        require RACINE . '/app/actions/decor.php';

    /* ---- équipe ---- */

    case 'admin':
        exiger_role('equipe');
        vue('admin', [
            'titre' => 'Tableau de bord',
            'stats' => tableau_de_bord(),
            'semaine' => indicateurs(7),
            'boucle' => entonnoir(30),
            'serie' => telechargements_par_jour(),
            'formules' => comptes_par_formule(),
            'roles' => comptes_par_role(),
            'nouveaux' => comptes_recents(6),
            'tetes' => decors_en_tete(6),
        ]);

    case 'catalogue':
    case 'statut':
    case 'supprimer':
        require RACINE . '/app/actions/catalogue.php';

    case 'relecture':
    case 'decider':
        require RACINE . '/app/actions/relecture.php';

    case 'comptes':
    case 'creer-compte':
    case 'role':
    case 'suspendre':
        require RACINE . '/app/actions/comptes.php';

    /* ---- QR, badges, entrée ---- */

    case 'qr':
        $b = badge_lire((string) ($_GET['jeton'] ?? ''));
        vue('qr', ['titre' => 'Badge Wakabi Boost', 'b' => $b, 'jeton' => (string) ($_GET['jeton'] ?? '')]);

    case 'scan':
        require RACINE . '/app/actions/scan.php';

    /* ---- interfaces appelées par le Studio ---- */

    case 'api-badge':
        $corps = json_lire(file_get_contents('php://input') ?: '{}');
        $d = decor_par_id((string) ($corps['decor'] ?? ''));
        if (!$d || $d['statut'] !== 'publie') {
            json_repondre(['erreur' => 'Décor introuvable.'], 404);
        }
        $jeton = badge_emettre($d['id'], $me['id'] ?? null);
        json_repondre(['jeton' => $jeton, 'qr' => Qr::dataUri(url('?p=qr&jeton=' . $jeton), 512)]);

    case 'api-telechargement':
        $corps = json_lire(file_get_contents('php://input') ?: '{}');
        $d = decor_par_id((string) ($corps['decor'] ?? ''));
        if ($d) {
            evenement($d['id'], 'telechargement');
            if ($me) {
                creation_noter($me['id'], $d['id']);
            }
        }
        json_repondre(['ok' => true]);

    /* ---- fichiers téléversés ---- */

    case 'cadre':
        require RACINE . '/app/actions/cadre.php';

    default:
        http_response_code(404);
        vue('introuvable', ['titre' => 'Page introuvable']);
}
