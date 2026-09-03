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
require __DIR__ . '/app/courriel.php';
require __DIR__ . '/app/og.php';
require __DIR__ . '/app/zip.php';
require __DIR__ . '/app/sauvegarde.php';
require __DIR__ . '/app/texte.php';
require __DIR__ . '/app/regie.php';
require __DIR__ . '/app/carnet.php';
require __DIR__ . '/app/images.php';
require __DIR__ . '/app/push.php';
require __DIR__ . '/app/qr.php';
require __DIR__ . '/app/icones.php';
require __DIR__ . '/app/avatars.php';
require __DIR__ . '/app/journal.php';
require __DIR__ . '/app/otp.php';
require __DIR__ . '/app/abonnement.php';
require __DIR__ . '/app/api.php';

assurer_schema();
demarrer_session();

$page = (string) ($_GET['p'] ?? 'accueil');
$post = $_SERVER['REQUEST_METHOD'] === 'POST';
$me = utilisateur_courant();
// « Vu le » : une écriture par jour et par compte, pas une par clic.
if ($me) {
    marquer_vu((string) $me['id']);
}

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
    // Ce qui part dans les balises de partage. Mis de côté comme le titre :
    // une variable de boucle du même nom dans une vue les écraserait.
    $_og_titre = $donnees['og_titre'] ?? null;
    $_og_image = $donnees['og_image'] ?? null;

    extract($donnees, EXTR_SKIP);
    $me = utilisateur_courant();

    ob_start();
    require RACINE . '/app/vues/' . $nom . '.php';
    $contenu = ob_get_clean();

    $titre = $_titre;
    $description = $_description;
    $og_titre = $_og_titre;
    $og_image = $_og_image;
    require RACINE . '/app/vues/layout.php';
    exit;
}

/* ---------------- routes ---------------- */

switch ($page) {

    /* ---- vitrine et catalogue ---- */

    case 'accueil':
        vue('accueil', ['titre' => 'Wakabi Boost — le badge qui remplit la salle']);

    /**
     * La vignette de partage.
     *
     * Servie par PHP et non depuis `public/` : elle est CALCULÉE, elle
     * dépend d'un décor, et elle doit se refaire quand le cadre change.
     * L'en-tête de cache est très long parce que l'adresse porte déjà une
     * empreinte de la date de modification : une nouvelle image, c'est une
     * nouvelle adresse.
     */
    case 'og':
        $slug = (string) ($_GET['slug'] ?? '');
        $d = $slug !== '' ? decor_par_slug($slug) : null;
        $fichier = fichier_og($d && $d['statut'] === 'publie' ? $d : null);
        if (!$fichier) {
            http_response_code(404);
            exit;
        }
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . filesize($fichier));
        header('Cache-Control: public, max-age=31536000, immutable');
        readfile($fichier);
        exit;

    /**
     * Une vignette de cadre, fabriquée une fois puis servie du disque.
     *
     * `f` n'est pas un chemin : c'est une clé que `image_de_la_cle()`
     * retraduit avec un motif strict. Un nom de fichier venu de la requête
     * ne désigne jamais un chemin, ici pas plus qu'ailleurs.
     *
     * Si la fabrication échoue — GD sans WebP, image illisible — on répond
     * l'ORIGINAL plutôt qu'une erreur : une page de catalogue sans images
     * serait un dégât bien pire qu'une page un peu lourde.
     */
    /**
     * L'API REST — elle s'authentifie par clé, jamais par session.
     *
     * Placée ici, avant tout écran : une réponse JSON ne doit jamais
     * traverser une redirection vers l'écran de connexion, sans quoi un
     * script reçoit une page HTML là où il attend un objet.
     */
    case 'api':
        api_servir();

    case 'vignette':
        $source = image_de_la_cle((string) ($_GET['f'] ?? ''));
        if (!$source) {
            http_response_code(404);
            exit('Introuvable');
        }
        $fichier = vignette($source, (int) ($_GET['l'] ?? 320));
        $type = $fichier ? 'image/webp' : ((string) (@getimagesize($source)['mime'] ?: 'image/png'));
        $fichier ??= $source;
        header('Content-Type: ' . $type);
        header('Content-Length: ' . filesize($fichier));
        // La clé du cache porte la date de la source : une image servie ici
        // ne change jamais sous la même adresse.
        header('Cache-Control: public, max-age=31536000, immutable');
        readfile($fichier);
        exit;

    case 'decors':
        vue('decors', ['titre' => 'Décors — Wakabi Boost', 'liste' => decors_publies()]);

    /**
     * Un média téléversé — la couverture d'un article.
     *
     * Comme `?p=cadre` : les fichiers vivent hors de la racine web, et un
     * nom contrôlé ici est la seule voie d'accès. Le motif exclut toute
     * traversée de chemin.
     */
    case 'media':
        $nom = (string) ($_GET['f'] ?? '');
        if (!preg_match('/^[0-9a-f-]{36}\.(png|webp|jpg)$/', $nom)) {
            http_response_code(404);
            exit('Introuvable');
        }
        $chemin = dossier_medias() . '/' . $nom;
        if (!is_file($chemin)) {
            http_response_code(404);
            exit('Introuvable');
        }
        /**
         * Le cadrage est honoré ICI aussi, pas seulement par `?p=vignette`.
         *
         * Sans cela, une installation sans WebP — c'est le cas sur
         * certains mutualisés — retomberait sur cette route et servirait
         * l'image ENTIÈRE : l'auteur a recadré, la page publie autre
         * chose, et il n'a aucun moyen de comprendre pourquoi.
         */
        $cadre = cadrage_de_url($_SERVER['REQUEST_URI'] ?? '');
        if ($cadre) {
            $chemin = image_cadree($chemin, $cadre) ?? $chemin;
        }
        header('Content-Type: ' . match (pathinfo($chemin, PATHINFO_EXTENSION)) {
            'png' => 'image/png',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        });
        header('Content-Length: ' . filesize($chemin));
        header('Cache-Control: public, max-age=31536000, immutable');
        readfile($chemin);
        exit;

    case 'blog':
        require RACINE . '/app/actions/blog.php';

    case 'blog-admin':
    case 'blog-editer':
    case 'blog-relecture':
    case 'blog-action':
        require RACINE . '/app/actions/blog-admin.php';

    /**
     * Téléverser une image DEPUIS l'éditeur, sans quitter la page.
     *
     * Obliger à enregistrer, aller ailleurs choisir un fichier, revenir et
     * coller une adresse est le genre de parcours qui fait qu'on n'illustre
     * jamais rien. L'image part, revient avec son adresse, et se pose dans
     * le texte — c'est tout.
     *
     * Le droit exigé est `articles` : celui-là même qui autorise à écrire.
     * Rien ne servirait de laisser écrire sans laisser illustrer.
     */
    case 'api-media':
        $u = exiger_droit('articles');
        verifier_csrf();
        if (empty($_FILES['image']['tmp_name']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
            json_repondre(['ok' => false, 'message' => 'Aucun fichier reçu.'], 400);
        }
        $info = @getimagesize($_FILES['image']['tmp_name']);
        $ext = match ($info[2] ?? 0) {
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
            IMAGETYPE_JPEG => 'jpg',
            default => null,
        };
        if (!$ext) {
            json_repondre(['ok' => false,
                'message' => 'Cette image doit être un JPEG, un PNG ou un WebP.'], 400);
        }
        if (($_FILES['image']['size'] ?? 0) > 6 * 1024 * 1024) {
            json_repondre(['ok' => false, 'message' => 'Cette image dépasse 6 Mo.'], 400);
        }
        $nom = nouvel_id() . '.' . $ext;
        move_uploaded_file($_FILES['image']['tmp_name'], dossier_medias() . '/' . $nom);
        // Redimensionnée et recompressée à l'arrivée, comme une couverture :
        // une photo d'appareil fait 4 000 px et 5 Mo, et s'affiche en 900.
        $c = compresser_cadre(dossier_medias(), $nom);
        // Le marqueur d'« déjà optimisée » : la passe de maintenance des
        // réglages saute ce fichier, au lieu de le recompresser une
        // seconde fois et de l'abîmer un peu plus.
        @touch(dossier_medias() . '/' . $c['nom'] . '.opt');
        json_repondre([
            'ok' => true,
            'url' => url('?p=media&f=' . $c['nom']),
            'poids' => poids($c['apres']),
        ]);

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
        // L'offre de l'auteur décide du filigrane et de la redirection —
        // à cet instant, pas au jour où le décor a été créé.
        $g = gabarit_selon_offre($g, utilisateur_par_id((string) $d['auteur_id']));

        evenement($d['id'], 'vue');
        vue('studio', [
            'titre' => $d['titre'] . ' — Wakabi Boost',
            // Ce que WhatsApp montrera du lien : le nom de la campagne, ce
            // qu'on y fait, et le badge lui-même.
            'description' => ($d['sous_titre'] ?: 'Créez votre badge en 30 secondes, et partagez-le.'),
            'og_titre' => $d['titre'],
            'og_image' => url_og($d),
            'd' => $d,
            'g' => $g,
        ]);

    /* ---- comptes ---- */

    case 'connexion':
        require RACINE . '/app/actions/connexion.php';

    case 'inscription':
        require RACINE . '/app/actions/inscription.php';

    /**
     * Le mot de passe oublié — accessible SANS être connecté, forcément.
     */
    case 'oubli':
    case 'reinitialiser':
        require RACINE . '/app/actions/oubli.php';

    case 'deconnexion':
        verifier_csrf();
        deconnecter();
        rediriger('');

    case 'profil':
    case 'profil-identite':
    case 'profil-motdepasse':
    case 'profil-otp':
    case 'profil-export':
    case 'profil-supprimer':
        require RACINE . '/app/actions/profil.php';

    case 'compte':
        $u = exiger_role(...ROLES);
        vue('compte', [
            'titre' => 'Mon compte — Wakabi Boost',
            'creations' => creations_de($u['id']),
            'solde' => koris_solde($u['id']),
            'historique' => koris_historique($u['id']),
        ]);

    case 'api-doc':
        $u = exiger_role(...ROLES);
        vue('api-doc', ['titre' => 'L’API — Wakabi Boost']);

    case 'api-cle':
        $u = exiger_role(...ROLES);
        verifier_csrf();
        if (!capacite($u, 'api')) {
            vue('offre-requise', [
                'titre' => 'L’API',
                'quoi' => OFFRE_LIGNES['api'][0],
                'aide' => OFFRE_LIGNES['api'][2],
                'debloque' => offre_qui_debloque('api'),
            ]);
        }
        if (($_POST['quoi'] ?? '') === 'revoquer') {
            api_cle_revoquer((string) $u['id']);
            rediriger('?p=api-doc&ok=' . urlencode('Clé révoquée. Les appels qui l’utilisaient sont refusés dès maintenant.'));
        }
        // La clé n'est lisible qu'ICI, une fois. Elle vaut mot de passe :
        // la réafficher à chaque visite du profil en ferait une donnée qui
        // traîne sur un écran resté ouvert.
        vue('api-doc', ['titre' => 'L’API — Wakabi Boost', 'cle_neuve' => api_cle_creer((string) $u['id'])]);

    case 'notifications':
        $u = exiger_role(...ROLES);
        $liste = notifications_de($u['id']);
        notifications_marquer_lues($u['id']);
        vue('notifications', ['titre' => 'Notifications', 'liste' => $liste]);

    /**
     * S'abonner aux notifications du navigateur. SANS compte obligatoire.
     *
     * C'est le point : un invité qui vient de faire son badge n'a pas de
     * compte, et c'est précisément à lui qu'on veut pouvoir reparler. Un
     * abonnement appartient à un navigateur ; `utilisateur_id` reste nul
     * tant que personne n'est connecté, et se remplit à la connexion
     * suivante puisque le même navigateur se réabonne.
     */
    case 'api-push-abonner':
        verifier_csrf();
        $endpoint = trim((string) ($_POST['endpoint'] ?? ''));
        $p256dh = trim((string) ($_POST['p256dh'] ?? ''));
        $auth_cle = trim((string) ($_POST['auth'] ?? ''));
        // L'adresse vient du navigateur, mais elle sera APPELÉE par le
        // serveur : sans ce garde, on accepterait de faire faire une requête
        // sortante vers n'importe quoi.
        if (!preg_match('~^https://~i', $endpoint) || $p256dh === '' || $auth_cle === '') {
            json_repondre(['ok' => false, 'message' => 'Abonnement incomplet.'], 400);
        }
        push_abonner(
            $me['id'] ?? null,
            $endpoint,
            $p256dh,
            $auth_cle,
            substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 180)
        );
        json_repondre(['ok' => true]);

    case 'api-push-desabonner':
        verifier_csrf();
        push_desabonner(trim((string) ($_POST['endpoint'] ?? '')));
        json_repondre(['ok' => true]);

    /**
     * Le renouvellement d'un abonnement, appelé par le service worker.
     *
     * SANS jeton CSRF, et c'est assumé : un service worker n'a pas de
     * page, donc pas de jeton. Ce que la route peut faire est
     * volontairement minuscule — remplacer une adresse par une autre.
     * Elle ne lit rien, ne supprime rien d'autre, et n'attache le nouvel
     * abonnement à un compte que si l'ANCIENNE adresse y était déjà
     * attachée. Quelqu'un qui la devinerait ne pourrait qu'enregistrer un
     * abonnement anonyme de plus.
     */
    case 'api-push-renouveler':
        $neuf = trim((string) ($_POST['endpoint'] ?? ''));
        $ancien = trim((string) ($_POST['remplace'] ?? ''));
        $p256dh = trim((string) ($_POST['p256dh'] ?? ''));
        $auth_cle = trim((string) ($_POST['auth'] ?? ''));
        if (!preg_match('~^https://~i', $neuf) || $p256dh === '' || $auth_cle === '') {
            json_repondre(['ok' => false], 400);
        }
        $precedent = $ancien !== '' ? push_abonnement_de($ancien) : null;
        push_abonner(
            $precedent['utilisateur_id'] ?? null,
            $neuf,
            $p256dh,
            $auth_cle,
            (string) ($precedent['agent'] ?? substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 180))
        );
        if ($ancien !== '' && $ancien !== $neuf) {
            push_desabonner($ancien);
        }
        json_repondre(['ok' => true]);

    case 'diffusion':
        require RACINE . '/app/actions/diffusion.php';

    case 'regie':
    case 'regie-ecrire':
    case 'regie-campagne':
    case 'regie-action':
        require RACINE . '/app/actions/regie.php';

    case 'regie-carnet':
    case 'regie-carnet-fiche':
    case 'regie-carnet-action':
    case 'regie-carnet-export':
        require RACINE . '/app/actions/carnet.php';

    /**
     * Le désabonnement. Publique, SANS session, et volontairement courte.
     *
     * Le lien est cliqué depuis une boîte mail, souvent sur un autre
     * appareil. Exiger une connexion ici transformerait un geste d'une
     * seconde en parcours, et quelqu'un qui n'arrive pas à se désabonner
     * clique sur « signaler comme indésirable » — le seul geste dont on ne
     * se relève pas.
     */
    case 'desabonnement':
        $jeton = (string) ($_GET['j'] ?? '');
        $envoi = $jeton !== '' ? envoi_par_jeton($jeton) : null;
        $fait = false;
        if ($post && $envoi) {
            verifier_csrf();
            desabonner((string) $envoi['email'], trim((string) ($_POST['motif'] ?? '')));
            $fait = true;
        }
        vue('desabonnement', [
            'titre' => 'Désabonnement — Wakabi Boost',
            'envoi' => $envoi,
            'jeton' => $jeton,
            'adresse' => (string) ($envoi['email'] ?? ''),
            'fait' => $fait || ($envoi && desabonne((string) $envoi['email'])),
        ]);

    /**
     * Le drain de la file d'envoi, appelé par le cron.
     *
     * Sans lui, une campagne de deux mille destinataires demanderait à
     * quelqu'un de cliquer quatre-vingts fois. La même clé et la même
     * comparaison à temps constant que la sauvegarde automatique.
     */
    case 'regie-cron':
        header('Content-Type: text/plain; charset=utf-8');
        if (!hash_equals(cle_sauvegarde(), (string) ($_GET['cle'] ?? ''))) {
            http_response_code(403);
            exit("Clé invalide.\n");
        }
        $encours = db()->query("SELECT id FROM campagnes_email WHERE statut = 'envoi'
                                ORDER BY maj_le LIMIT 1")->fetchColumn();
        if (!$encours) {
            exit("Rien à envoyer.\n");
        }
        $r = regie_envoyer_lot((string) $encours);
        echo $r['message'], "\n";
        exit;

    /* ---- partenaire ---- */

    case 'partenaire':
        $u = exiger_droit('decors_siens');
        vue('partenaire', [
            'titre' => 'Mes campagnes',
            'liste' => decors_de($u['id']),
            // Les campagnes qu'on lui a confiées, listées à part : les
            // mêler aux siennes ferait croire qu'elles comptent dans son
            // quota, ce qui n'est pas le cas.
            'confiees' => decors_confies((string) $u['id']),
        ]);

    case 'nouveau':
    case 'modifier':
    case 'equipier':
    case 'soumettre':
        require RACINE . '/app/actions/decor.php';

    case 'liens':
    case 'creer-lien':
    case 'supprimer-lien':
        require RACINE . '/app/actions/liens.php';

    /**
     * Suivre un lien court. Publique, et volontairement minuscule.
     *
     * Une redirection 302 et rien d'autre : pas de session démarrée, pas de
     * gabarit HTML rendu. C'est la page la plus sollicitée d'une campagne
     * qui marche, et chaque milliseconde y est multipliée par le nombre de
     * personnes qui cliquent.
     */
    case 'l':
        $cible = suivre_lien(trim((string) ($_GET['c'] ?? '')));
        if (!$cible) {
            http_response_code(404);
            vue('introuvable', ['titre' => 'Lien introuvable']);
        }
        header('Location: ' . $cible, true, 302);
        // Un lien court ne doit pas se figer dans un cache : sa cible peut
        // changer, et son compteur ne verrait plus passer personne.
        header('Cache-Control: no-store');
        exit;

    /* ---- équipe ---- */

    case 'admin':
        exiger_droit('decors_tous');
        vue('admin', [
            'titre' => 'Tableau de bord',
            'stats' => tableau_de_bord(),
            // Les trois files que l'équipe alimente elle-même : décors,
            // articles, campagnes. Elles sont comptées ici plutôt que dans
            // `tableau_de_bord()` parce qu'elles n'ont de sens que sur cet
            // écran — le reste du produit ne les regarde jamais.
            'blog_a_relire' => articles_a_relire(),
            'regie_a_relire' => campagnes_email_en_attente(),
            'regie_en_file' => regie_en_attente_denvoi(),
            'semaine' => indicateurs(7),
            'boucle' => entonnoir(30),
            'serie' => telechargements_par_jour(),
            'formules' => comptes_par_formule(),
            'roles' => comptes_par_role(),
            // Quatre et non six : la carte doit faire la hauteur de celle du
            // tunnel qui lui fait face, sinon la rangée se troue.
            'nouveaux' => comptes_recents(4),
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
    case 'creer-equipier':
    case 'verif-renvoyer':
    case 'role':
    case 'suspendre':
    case 'bonus':
    case 'paiement':
    case 'otp-lever':
    case 'note-compte':
    case 'organisateur':
        require RACINE . '/app/actions/comptes.php';

    /**
     * Le journal — réservé à qui gère les comptes.
     *
     * C'est le droit qui correspond : savoir qui a suspendu qui est de la
     * même nature que pouvoir le faire. Un coordinateur arbitre les
     * contenus, il n'a pas à surveiller ses collègues.
     */
    /**
     * Une facture — visible par son destinataire ET par l'équipe.
     *
     * Le client doit pouvoir la retrouver seul : lui faire écrire à
     * l'équipe pour obtenir un document qui le concerne est exactement le
     * genre d'aller-retour qu'on cherche à supprimer.
     */
    case 'facture':
        $u = exiger_role(...ROLES);
        $f = facture_par_id((string) ($_GET['id'] ?? ''));
        if (!$f || ($f['utilisateur_id'] !== $u['id'] && !droit($u, 'comptes'))) {
            http_response_code(404);
            vue('introuvable', ['titre' => 'Facture introuvable']);
        }
        vue('facture', [
            'titre' => 'Facture ' . $f['numero'],
            'f' => $f,
            'retour' => droit($u, 'comptes') && $f['utilisateur_id'] !== $u['id']
                ? '?p=organisateur&id=' . rawurlencode((string) $f['utilisateur_id'])
                : '?p=profil',
        ]);

    case 'journal':
        $u = exiger_droit('comptes');
        $qui = (string) ($_GET['qui'] ?? '');
        $quoi = (string) ($_GET['quoi'] ?? '');
        $n = max(1, (int) ($_GET['n'] ?? 1));
        $total = journal_combien($qui, $quoi);
        vue('journal', [
            'titre' => 'Journal',
            'lignes' => journal_lire($n, $qui, $quoi),
            'acteurs' => journal_acteurs(),
            'acteur' => $qui,
            'action' => $quoi,
            'page_n' => $n,
            'combien' => $total,
            'pages' => max(1, (int) ceil($total / 50)),
        ]);

    case 'reglages':
        require RACINE . '/app/actions/reglages.php';

    case 'sauvegardes':
    case 'sauvegarder':
    case 'telecharger-sauvegarde':
    case 'restaurer':
    case 'supprimer-sauvegarde':
        require RACINE . '/app/actions/sauvegardes.php';

    /**
     * Le point d'entrée du cron, hors session.
     *
     * Une tâche planifiée n'a pas de cookie : elle présente une clé. Le
     * `hash_equals` n'est pas de la coquetterie — comparer deux chaînes avec
     * `===` rend une réponse d'autant plus tardive que le préfixe est juste,
     * et cette différence de quelques microsecondes suffit à deviner une clé
     * caractère par caractère.
     */
    case 'sauvegarde-auto':
        header('Content-Type: text/plain; charset=utf-8');
        if (!hash_equals(cle_sauvegarde(), (string) ($_GET['cle'] ?? ''))) {
            http_response_code(403);
            exit("Clé invalide.\n");
        }
        try {
            $f = ecrire_sauvegarde(dossier_sauvegardes() . '/' . nom_sauvegarde());
            $effacees = tourner_sauvegardes();
            // L'entretien quotidien voyage avec la sauvegarde : le cache
            // d'images qu'on ne demande plus, et les abonnements qui
            // arrivent à terme. Deux choses qu'on n'irait jamais faire à la
            // main, et qui coûtent cher quand personne ne les fait.
            $cache = nettoyer_vignettes();
            $echeances = rappeler_echeances();
            printf("OK %s (%d Ko), %d ancienne(s) effacée(s).\n",
                   basename($f), (int) round(filesize($f) / 1024), $effacees);
            printf("Cache : %d vignette(s) effacée(s), %s libéré(s), %s restants.\n",
                   $cache['effaces'], poids($cache['octets']), poids($cache['poids']));
            printf("Échéances : %d rappel(s), %d rétrogradation(s).\n",
                   $echeances['rappeles'], $echeances['retrogrades']);
        } catch (Throwable $e) {
            http_response_code(500);
            echo 'ÉCHEC ', $e->getMessage(), "\n";
        }
        exit;

    /* ---- confirmation d'adresse ---- */

    /**
     * Volontairement accessible SANS être connecté.
     *
     * Le lien est cliqué depuis une boîte mail, souvent sur un autre
     * appareil que celui de l'inscription. Exiger une session ici ferait
     * tomber la moitié des confirmations sur un écran de connexion, et
     * personne ne comprendrait pourquoi.
     */
    case 'verifier':
        $r = consommer_jeton_verification((string) ($_GET['j'] ?? ''));
        vue('verification', [
            'titre' => $r['ok'] ? 'Adresse confirmée' : 'Lien de confirmation',
            'resultat' => $r,
        ]);

    case 'renvoyer-verification':
        $moi = utilisateur_courant();
        if (!$moi) {
            rediriger('?p=connexion');
        }
        verifier_csrf();
        if (email_verifie($moi)) {
            rediriger('?p=compte&ok=' . rawurlencode('Votre adresse est déjà confirmée.'));
        }
        /**
         * Un bouton « renvoyer » sans compteur est un robot d'envoi.
         *
         * L'adresse visée est la sienne, donc le risque est faible — mais
         * c'est notre serveur SMTP qui expédierait, et un hébergeur coupe un
         * compte qui envoie mille messages en une heure. Le même compteur
         * que la connexion, la même fenêtre.
         */
        $cle_renvoi = 'verif|' . $moi['id'];
        if (debit_depasse($cle_renvoi)) {
            rediriger(($moi['role'] === 'partenaire' ? '?p=partenaire' : '?p=compte') . '&err=' . rawurlencode(
                'Trop de demandes. Réessayez dans ' . FENETRE_MINUTES . ' minutes — et regardez vos indésirables.'
            ));
        }
        debit_noter($cle_renvoi);
        $envoi = envoyer_verification($moi);
        $retour = $moi['role'] === 'partenaire' ? '?p=partenaire' : '?p=compte';
        rediriger($retour . ($envoi['ok'] ? '&ok=' : '&err=') . rawurlencode(
            $envoi['ok']
                ? 'Message envoyé à ' . $moi['email'] . '. Regardez aussi les indésirables.'
                : $envoi['message']
        ));

    /* ---- QR, badges, entrée ---- */

    case 'qr':
        $b = badge_lire((string) ($_GET['jeton'] ?? ''));
        vue('qr', ['titre' => 'Badge Wakabi Boost', 'b' => $b, 'jeton' => (string) ($_GET['jeton'] ?? '')]);

    /**
     * Le scan sans rechargement de page, pour la caméra.
     *
     * Une file d'attente ne supporte pas un aller-retour complet par
     * invité : la page se recharge, la caméra se rallume, l'agent réattend
     * la mise au point. Ici, la caméra reste ouverte et seule la réponse
     * change à l'écran. Le formulaire, lui, continue de marcher sans
     * JavaScript — c'est la même fonction qui rend le verdict.
     */
    case 'api-scan':
        $u = exiger_droit('scan');
        verifier_csrf();
        $jeton = strtoupper(trim((string) ($_POST['jeton'] ?? '')));
        if ($jeton === '') {
            json_repondre(['ok' => false, 'message' => 'Aucun code lu.', 'detail' => '']);
        }
        $v = verdict_scan(badge_scanner($jeton, $u['id']));
        $v['jeton'] = $jeton;
        $v['passages'] = array_map(fn($p) => [
            'heure' => substr((string) $p['scanne_le'], 11, 5),
            'porteur' => $p['porteur'] ?: 'Badge anonyme',
            'decor' => $p['decor'],
        ], passages_recents());
        json_repondre($v);

    case 'scan':
        require RACINE . '/app/actions/scan.php';

    /* ---- interfaces appelées par le Studio ---- */

    /**
     * L'aperçu du formulaire de décor.
     *
     * Le gabarit est construit ICI, par la même fonction que celle qui
     * l'enregistre. Le navigateur ne fabrique rien : il reçoit la structure
     * exacte qui sera stockée et la dessine avec le même renderer que le
     * Studio. Un aperçu qui mentirait ne servirait à rien.
     */
    case 'api-apercu':
        $u = exiger_droit('decors_siens');
        verifier_csrf();

        $disposition = (string) ($_POST['disposition'] ?? 'bandeau');
        if (!in_array($disposition, array_column(dispositions(), 'id'), true)) {
            $disposition = 'bandeau';
        }
        /**
         * `reinit` : la disposition ou le format viennent de changer, on
         * repart des réglages d'usine plutôt que de traîner les précédents.
         *
         * La NUANCE compte : changer de gabarit (`reinit=disposition`) en
         * reprend aussi le format d'origine — un gabarit Instagram est 4:5,
         * sans quoi il resterait au carré du gabarit qu'on vient de quitter.
         * Changer de format (`reinit=format`), au contraire, garde celui
         * qu'on vient de choisir, et ne recalcule que ce qui en dépend.
         */
        $format = (string) ($_POST['format'] ?? '');
        $reinit = (string) ($_POST['reinit'] ?? '');
        $apparence = $reinit !== ''
            ? apparence_par_defaut(
                $disposition,
                $reinit === 'format' && isset(FORMATS[$format]) ? $format : ''
            )
            : apparence_propre($disposition, $_POST);

        $cadre = (string) ($_POST['cadre_url'] ?? '');
        if ($cadre === '' && ($_POST['cadre_fourni'] ?? '') !== '') {
            $nom = basename((string) $_POST['cadre_fourni']);
            $cadre = isset(cadres_fournis()[$nom]) ? url('public/cadres/' . $nom) : '';
        }
        // Une page blanche s'affiche SANS cadre : lui en prêter un pour
        // l'aperçu montrerait un décor qu'on n'enregistrera pas.
        if ($cadre === '' && $disposition !== 'vierge') {
            $cadre = cadre_du_format($disposition);
        }

        try {
            $g = construire_gabarit([
                'slug' => 'apercu',
                'titre' => (string) ($_POST['titre'] ?? 'Aperçu'),
                'sous_titre' => '',
                'ville' => 'all',
                'rubrique' => 'campagne',
                'disposition' => $disposition,
                'cadre_url' => $cadre,
                'accroche' => (string) ($_POST['accroche'] ?? 'J’Y SERAI'),
                'champ_libelle' => (string) ($_POST['champ_libelle'] ?? 'Ton prénom'),
                'champ_valeur' => (string) ($_POST['champ_valeur'] ?? 'Ama'),
                // La redirection réelle n'a pas à passer le garde-fou pour un
                // aperçu : c'est à l'enregistrement qu'elle est jugée.
                'redirection' => 'https://wakabileguide.com/',
                'redirection_libelle' => '',
                'legende' => '',
                'expire_le' => '',
                'apparence' => $apparence,
                'cree_par' => 'equipe',
            ]);
        } catch (GabaritInvalide $e) {
            json_repondre(['erreur' => $e->getMessage()]);
        }

        json_repondre([
            'gabarit' => $g,
            'apparence' => $apparence,
            'cadre' => $cadre,
            'photo' => url('public/apercu-photo.webp'),
            // Un QR d'illustration, pas un badge : rien n'est émis en base.
            'qr' => Qr::dataUri(url(''), 320),
        ]);

    case 'api-badge':
        $corps = json_lire(file_get_contents('php://input') ?: '{}');
        $d = decor_par_id((string) ($corps['decor'] ?? ''));
        if (!$d || $d['statut'] !== 'publie') {
            json_repondre(['erreur' => 'Décor introuvable.'], 404);
        }
        /**
         * Le quota de l'organisateur est opposé ICI, et nulle part ailleurs.
         *
         * C'est l'émission du badge qui coûte : c'est donc elle qu'on
         * refuse. Le message part à l'INVITÉ, qui n'y est pour rien — il
         * doit comprendre que ce n'est ni sa faute ni une panne.
         */
        $limite = quota_telechargements($d);
        if (!$limite['ok']) {
            alerter_quota_plein($d);
            json_repondre(['erreur' => $limite['message'], 'quota' => true], 429);
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
