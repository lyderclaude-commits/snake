<?php
/**
 * La forme des adresses publiques.
 *
 * Jusqu'ici une page s'écrivait `?p=decors` et un décor `?p=decor&slug=…`.
 * Ça marche partout, sans réécriture à configurer — c'est même pour cette
 * raison que le produit a commencé ainsi, et c'est pourquoi cette forme
 * reste le défaut. Mais elle coûte deux choses :
 *
 *   — Ce qui se PARTAGE. Une adresse collée dans un groupe WhatsApp est
 *     lue avant d'être cliquée : `/decors/soiree-blanche-lome` dit ce
 *     qu'il y a au bout, `?p=decor&slug=soiree-blanche-lome` dit qu'on
 *     s'y connaît en informatique. C'est le canal principal du produit.
 *   — Ce qui se RECOPIE. Une adresse lue à la radio ou imprimée sur une
 *     affiche doit se taper sans expliquer le point d'interrogation,
 *     l'égal et l'esperluette.
 *
 * D'où cet écran, et son air de famille avec celui de WordPress : on
 * choisit une forme, on nomme les deux préfixes, et le site s'en sert.
 *
 * ──────────────────────────────────────────────────────────────────────
 * LA RÈGLE QUI TIENT TOUT LE RESTE : *résoudre* est permanent, *écrire*
 * est un réglage.
 *
 * Une adresse lisible est TOUJOURS comprise, même quand le réglage est
 * sur la forme simple. Seule la fabrication des liens dépend du réglage.
 * Sans cette asymétrie, revenir en arrière — ou un hébergeur qui perd
 * `mod_rewrite` au détour d'une migration — casserait d'un coup toutes
 * les adresses déjà parties dans des messageries, des QR imprimés et
 * l'index de Google. Ici, elles continuent de répondre.
 *
 * Et dans l'autre sens : la forme lisible ne s'active QUE si le site a
 * réussi à se répondre à lui-même sur une adresse lisible. C'est le seul
 * moyen de savoir que l'hébergement lit bien `.htaccess` et que
 * `mod_rewrite` y est actif — autrement on distribuerait tout un site
 * d'adresses mortes, et on ne le découvrirait que par le silence.
 */

declare(strict_types=1);

/** Les réglages, et ce qu'ils valent tant que personne n'y a touché. */
const PERMALIENS_DEFAUTS = [
    'permaliens_forme'   => 'simple',   // simple | lisible
    'permaliens_blog'    => 'blog',
    'permaliens_decors'  => 'decors',
    'permaliens_barre'   => '0',        // terminer les adresses par « / »
    'permaliens_verifie' => '',         // date du dernier essai concluant
];

/**
 * Les pages dont l'adresse lisible est un seul mot, et lequel.
 *
 * Le nom de la route et le mot de l'adresse sont volontairement
 * DISTINCTS : `boost-push` est un nom de route, `push` est ce qu'on écrit
 * sur une affiche. Les séparer coûte une ligne ici et évite de renommer
 * une route — donc de casser les adresses de la forme simple, qui restent
 * valables à jamais.
 *
 * Aucun de ces mots ne doit faire SIX caractères de l'alphabet des liens
 * courts (`[A-HJ-NP-Za-hj-np-z2-9]`), sinon la règle des liens courts,
 * qui passe avant dans le `.htaccess`, les avalerait. `villes` et
 * `decors` en font six mais contiennent un `i` et un `o`, que cet
 * alphabet exclut. `permaliens_jolie()` le vérifie de toute façon à
 * chaque lien : une liste qu'on relit à la main finit par mentir.
 */
const PERMALIENS_FIXES = [
    'accueil'         => '',
    'application'     => 'application',
    'partenaires'     => 'partenaires',
    'villes'          => 'villes',
    'a-propos'        => 'a-propos',
    'contact'         => 'contact',
    'boost-push'      => 'push',
    'boost-regie'     => 'regie',
    'boost-liens'     => 'liens-courts',
    'cgu'             => 'cgu',
    'confidentialite' => 'confidentialite',
    'inscription'     => 'inscription',
    'connexion'       => 'connexion',
    'creer'           => 'creer',
    'lien-court'      => 'lien-court',
];

/** L'adresse que le site demande à lui-même pour éprouver la réécriture. */
const PERMALIENS_ESSAI = 'wk-permaliens-essai';

/** Ce que cette adresse répond. Un mot, en clair, et rien d'autre. */
const PERMALIENS_ESSAI_JETON = 'permaliens-ok';

/** Combien de temps une réécriture CONSTATÉE vaut encore preuve. */
const PERMALIENS_VU_MINUTES = 30;

/**
 * Les réglages, gardés en mémoire pour la durée de la requête.
 *
 * `url()` les demande des dizaines de fois par page — une grille de douze
 * décors, c'est douze liens, plus le menu, plus le pied. Une requête par
 * lien ferait de ce confort d'affichage un coût mesurable sur la page la
 * plus visitée du site.
 *
 * Le souvenir est indexé sur `reglages_version()`, qui bouge à chaque
 * écriture : sans cela, l'écran de réglages afficherait encore l'ancienne
 * forme juste après l'avoir changée.
 */
function permaliens_reglages(): array
{
    static $cache = null;
    static $version = -1;

    /* L'installateur charge le socle SANS le dépôt, et appelle `url()`
       pour composer ses propres liens. Sans cette porte de sortie, poser
       la première pierre du site se terminerait sur « fonction inconnue :
       reglages_bdd() » — un écran blanc au pire moment possible. */
    if (!function_exists('reglages_bdd')) {
        return PERMALIENS_DEFAUTS;
    }
    if ($cache !== null && $version === reglages_version()) {
        return $cache;
    }

    $lus = reglages_bdd(array_keys(PERMALIENS_DEFAUTS));
    $r = [];
    foreach (PERMALIENS_DEFAUTS as $cle => $defaut) {
        $v = trim((string) ($lus[$cle] ?? ''));
        $r[$cle] = $v !== '' ? $v : $defaut;
    }

    /* Un préfixe vide donnerait des adresses d'un seul segment :
       `/soiree-blanche` entrerait alors en concurrence avec `/contact` et
       avec les codes de liens courts. On retombe sur le défaut plutôt que
       d'accepter un réglage qui mange le reste du site. */
    foreach (['permaliens_blog', 'permaliens_decors'] as $cle) {
        $r[$cle] = permaliens_base($r[$cle]) ?: PERMALIENS_DEFAUTS[$cle];
    }
    if ($r['permaliens_blog'] === $r['permaliens_decors']) {
        // Deux préfixes identiques rendraient `/x/y` indécidable.
        $r['permaliens_decors'] = PERMALIENS_DEFAUTS['permaliens_decors'];
    }

    $version = reglages_version();
    return $cache = $r;
}

/**
 * Un préfixe propre, ou la chaîne vide s'il n'y a rien à garder.
 *
 * Pas `slugifier()` : celle-là rend « decor » quand elle ne trouve rien à
 * garder, ce qui est bon pour un titre de décor et trompeur pour un
 * réglage — l'écran dirait « enregistré » en ayant posé autre chose que
 * ce qui a été tapé.
 */
function permaliens_base(string $brut): string
{
    $b = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $brut) ?: $brut);
    $b = preg_replace('~[^a-z0-9]+~', '-', $b) ?? '';
    return trim($b, '-');
}

/** Vrai quand les liens doivent s'écrire sous leur forme lisible. */
function permaliens_actifs(): bool
{
    $r = permaliens_reglages();
    // Les deux conditions, et pas une seule : la forme choisie ne vaut
    // rien tant que l'hébergement n'a pas prouvé qu'il la servait.
    return $r['permaliens_forme'] === 'lisible' && $r['permaliens_verifie'] !== '';
}

/* ------------------------------------------------------------------ */
/* Écrire : de `?p=…` vers `/…`                                        */
/* ------------------------------------------------------------------ */

/**
 * La forme lisible d'une adresse interne, ou `null` s'il n'y en a pas.
 *
 * Branchée dans `url()`, donc appelée par les quelques centaines de liens
 * du site sans qu'aucun ait eu à changer. C'est voulu : une page oubliée
 * garderait la forme simple, qui marche — alors qu'une liste de liens à
 * convertir un par un aurait laissé des trous qu'on ne voit pas.
 *
 * Elle ne traduit QUE la vitrine. Les écrans de travail, les points
 * d'entrée machine (`og`, `vignette`, `api-*`, `media`) et les liens
 * courts gardent leur forme : ils ne se partagent pas, ne s'impriment
 * pas, et certains sont déjà dans des caches de messageries qu'on ne peut
 * pas invalider.
 */
function permaliens_jolie(string $chemin): ?string
{
    // `url('public/wakabi.css')` : un fichier, pas une page.
    if ($chemin === '' || $chemin[0] !== '?' || !permaliens_actifs()) {
        return null;
    }

    parse_str(substr($chemin, 1), $q);
    $p = (string) ($q['p'] ?? 'accueil');
    unset($q['p']);
    $r = permaliens_reglages();
    $joli = null;

    if (array_key_exists($p, PERMALIENS_FIXES)) {
        $joli = PERMALIENS_FIXES[$p];
    } elseif ($p === 'decors') {
        $joli = $r['permaliens_decors'] . permaliens_page($q);
    } elseif ($p === 'decor' && ($q['slug'] ?? '') !== '') {
        $joli = $r['permaliens_decors'] . '/' . permaliens_segment((string) $q['slug']);
        unset($q['slug']);
    } elseif ($p === 'blog' && ($q['a'] ?? '') !== '') {
        $joli = $r['permaliens_blog'] . '/' . permaliens_segment((string) $q['a']);
        unset($q['a']);
    } elseif ($p === 'blog' && ($q['g'] ?? '') !== '') {
        /* Les articles venus du WordPress du guide passent par `guide/` :
           rien n'empêche un article d'ici et un article de là-bas de
           porter le même slug, et ce jour-là l'un des deux disparaîtrait
           derrière l'autre sans un mot. */
        $joli = $r['permaliens_blog'] . '/guide/' . permaliens_segment((string) $q['g']);
        unset($q['g']);
    } elseif ($p === 'blog') {
        $joli = $r['permaliens_blog'] . permaliens_page($q);
    }

    if ($joli === null) {
        return null;
    }

    /**
     * Le garde-fou contre les liens courts.
     *
     * Un segment unique de six caractères de l'alphabet des codes serait
     * détourné par la règle des liens courts, qui passe avant dans le
     * `.htaccess` — la page répondrait « lien introuvable ». Plutôt que
     * de faire confiance à une liste relue à la main, on retombe sur la
     * forme simple : moins jolie, jamais cassée.
     */
    if ($joli !== '' && !str_contains($joli, '/')
        && preg_match('~^[A-HJ-NP-Za-hj-np-z2-9]{6}$~', $joli)) {
        return null;
    }

    if ($joli !== '' && $r['permaliens_barre'] === '1') {
        $joli .= '/';
    }

    // Ce qui reste n'a pas de place dans le chemin et repart en requête :
    // une recherche, un message de confirmation, un jeton. La traduction
    // ne perd donc jamais rien.
    $reste = [];
    foreach ($q as $cle => $valeur) {
        if (!is_array($valeur) && $valeur !== '' && $valeur !== null) {
            $reste[] = rawurlencode((string) $cle) . '=' . rawurlencode((string) $valeur);
        }
    }
    return $joli . ($reste ? '?' . implode('&', $reste) : '');
}

/** `/page/3` pour une liste, rien pour sa première page. */
function permaliens_page(array &$q): string
{
    $n = (int) ($q['n'] ?? 1);
    unset($q['n']);
    return $n > 1 ? '/page/' . $n : '';
}

/** Un slug tel qu'il peut tenir dans un chemin. */
function permaliens_segment(string $s): string
{
    return rawurlencode($s);
}

/* ------------------------------------------------------------------ */
/* Résoudre : de `/…` vers `?p=…`                                      */
/* ------------------------------------------------------------------ */

/**
 * Traduit l'adresse lisible reçue en paramètres, AVANT que le routeur ne
 * regarde `p`.
 *
 * Le `.htaccess` dépose le chemin dans `wk`. Ce qu'on en tire est écrit
 * dans `$_GET` : le routeur n'a donc rien à savoir de tout ceci, et une
 * route nouvelle marche sous les deux formes sans qu'on y pense.
 *
 * Le `p` d'une requête lisible est ÉCRASÉ, et c'est volontaire : le
 * formulaire de recherche du blog et celui du catalogue portent un champ
 * caché `p` (sans lui, un `method="get"` efface la requête de son propre
 * `action`). Le chemin fait foi, le champ caché ne peut pas contredire.
 */
function permaliens_entrer(): bool
{
    $brut = (string) ($_GET['wk'] ?? '');
    unset($_GET['wk']);
    if ($brut === '') {
        return false;
    }

    $params = permaliens_resoudre($brut);
    if ($params === null) {
        // Un chemin qu'on ne sait pas lire est une page absente, pas une
        // erreur de serveur : le routeur rendra son 404 habituel.
        $_GET['p'] = 'introuvable';
        return true;
    }
    foreach ($params as $cle => $valeur) {
        $_GET[$cle] = $valeur;
    }
    return true;
}

/**
 * Les paramètres derrière un chemin lisible, ou `null` si inconnu.
 *
 * Toujours active, quel que soit le réglage : c'est elle qui fait qu'une
 * adresse déjà partagée continue de répondre après un retour à la forme
 * simple.
 *
 * @return array<string,string>|null
 */
function permaliens_resoudre(string $brut): ?array
{
    // La barre finale est acceptée dans les deux sens, quel que soit le
    // réglage : la même page ne peut pas répondre à l'une et pas à l'autre.
    $chemin = trim($brut, '/');

    /* Un chemin n'est fait que de slugs. Tout le reste — un point, un
       pourcent, une espace — n'a jamais été fabriqué par ce site, donc
       n'a aucune page au bout. Refuser ici ferme d'un coup la question du
       double décodage et celle des chemins fantaisistes. */
    if ($chemin !== '' && !preg_match('~^[A-Za-z0-9/_-]+$~', $chemin)) {
        return null;
    }

    if ($chemin === '') {
        return ['p' => 'accueil'];
    }
    if ($chemin === PERMALIENS_ESSAI) {
        return ['p' => 'permaliens-essai'];
    }

    $bas = strtolower($chemin);
    $route = array_search($bas, PERMALIENS_FIXES, true);
    if ($route !== false && $route !== 'accueil') {
        return ['p' => (string) $route];
    }

    $bouts = explode('/', $bas);
    $r = permaliens_reglages();

    if ($bouts[0] === $r['permaliens_decors']) {
        return permaliens_liste($bouts, 'decors', 'decor', 'slug');
    }
    if ($bouts[0] === $r['permaliens_blog']) {
        // `blog/guide/<slug>` : l'article vient du WordPress du guide.
        if (count($bouts) === 3 && $bouts[1] === 'guide' && $bouts[2] !== '') {
            return ['p' => 'blog', 'g' => $bouts[2]];
        }
        return permaliens_liste($bouts, 'blog', 'blog', 'a');
    }

    return null;
}

/**
 * Les trois formes d'une famille : la liste, sa page N, et une fiche.
 *
 * Écrite une fois pour le catalogue et pour le blog. Deux copies auraient
 * divergé au premier ajout — et c'est la pagination qu'on aurait oubliée,
 * parce qu'elle ne se voit qu'à la deuxième page.
 *
 * @param list<string> $bouts
 * @return array<string,string>|null
 */
function permaliens_liste(array $bouts, string $liste, string $fiche, string $cle): ?array
{
    $n = count($bouts);
    if ($n === 1) {
        return ['p' => $liste];
    }
    if ($n === 3 && $bouts[1] === 'page' && ctype_digit($bouts[2])) {
        return ['p' => $liste, 'n' => $bouts[2]];
    }
    /**
     * Un décor peut très bien s'appeler « Page », et son adresse est
     * alors `/decors/page`.
     *
     * Ce cas-là était refusé, par prudence mal placée : l'ambiguïté avec
     * la pagination n'existe qu'à TROIS segments, où `page` est suivi
     * d'un nombre. À deux, il n'y a rien à confondre, et refuser revenait
     * à rendre une fiche introuvable alors que le site distribuait son
     * adresse.
     */
    if ($n === 2 && $bouts[1] !== '') {
        return ['p' => $fiche, $cle => $bouts[1]];
    }
    return null;
}

/* ------------------------------------------------------------------ */
/* La redirection canonique                                            */
/* ------------------------------------------------------------------ */

/**
 * Une seule adresse par page, et c'est la jolie.
 *
 * Sans cette redirection, chaque page du site aurait deux adresses qui
 * répondent — un moteur les compte deux fois et les note deux fois moins
 * bien, et les partages se répartissent entre les deux.
 *
 * `301` et non `302`, parce que c'est définitif, et un navigateur garde
 * un 301 très longtemps. Ce qui rend ce choix tenable est la règle du
 * haut de ce fichier : la cible ne peut pas mourir, puisque la résolution
 * ne s'éteint jamais, même si le réglage repasse à la forme simple.
 *
 * Trois gardes :
 *   — jamais un POST : on ne redirige pas un formulaire, le navigateur
 *     perdrait son corps en route ;
 *   — jamais une page sans forme lisible, c'est-à-dire tout l'espace de
 *     travail, l'API et les vignettes ;
 *   — jamais vers l'adresse où l'on est DÉJÀ.
 *
 * Le troisième garde est le seul qui compte vraiment, et il est écrit
 * comme une comparaison et non comme une liste de cas. La première
 * version demandait « la requête est-elle arrivée par la forme
 * lisible ? » — ce qui laissait l'accueil hors du compte : il arrive par
 * `/`, sans chemin réécrit, et sa forme lisible est `/`. Il se
 * redirigeait donc vers lui-même, indéfiniment, et la page d'accueil du
 * site ne s'ouvrait plus. Comparer l'adresse visée à l'adresse courante
 * ferme ce cas et tous ses cousins d'un coup : la barre finale en trop,
 * celle qui manque, `index.php` écrit à la main, une majuscule dans un
 * slug. Chacun est redirigé une fois, vers une adresse qui, elle, ne
 * redirige plus.
 */
function permaliens_canoniser(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || !permaliens_actifs()) {
        return;
    }

    $q = $_GET;
    $p = (string) ($q['p'] ?? 'accueil');
    unset($q['p']);
    $suite = '';
    foreach ($q as $cle => $valeur) {
        if (!is_array($valeur) && $valeur !== '' && $valeur !== null) {
            $suite .= '&' . rawurlencode((string) $cle) . '=' . rawurlencode((string) $valeur);
        }
    }

    $joli = permaliens_jolie('?p=' . rawurlencode($p) . $suite);
    if ($joli === null) {
        return;
    }

    /* Le chemin demandé, débarrassé du dossier d'installation : le même
       zip se décompresse à la racine d'un domaine comme dans un
       sous-dossier, et la comparaison doit valoir dans les deux cas. */
    $ici = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $dossier = rtrim((string) parse_url(base_url(), PHP_URL_PATH), '/');
    if ($dossier !== '' && str_starts_with($ici, $dossier)) {
        $ici = substr($ici, strlen($dossier));
    }
    $vise = explode('?', $joli)[0];
    if (ltrim(rawurldecode($ici), '/') === $vise) {
        return;
    }

    header('Location: ' . base_url() . '/' . $joli, true, 301);
    exit;
}

/* ------------------------------------------------------------------ */
/* L'essai                                                             */
/* ------------------------------------------------------------------ */

/**
 * On DEMANDE à l'installation de se répondre, on ne suppose pas.
 *
 * `mod_rewrite` peut être absent, `AllowOverride` interdire le
 * `.htaccess`, un hébergeur servir une page d'erreur maison en 200 : dans
 * les trois cas la règle est ignorée en silence. Le site se demande donc
 * une adresse lisible dont il connaît la réponse exacte, et c'est ce
 * résultat — pas une hypothèse — qui autorise la forme lisible.
 *
 * La requête d'essai n'emporte PAS le cookie de session, et c'est ce qui
 * la rend possible : avec lui, elle attendrait la fin de la requête en
 * cours pour obtenir le verrou du fichier de session, laquelle attend sa
 * réponse. Les deux se regarderaient jusqu'au délai.
 */
function permaliens_essai(): array
{
    $adresse = base_url() . '/' . PERMALIENS_ESSAI;
    $r = permaliens_sonder($adresse);

    if ($r['code'] === 200 && trim($r['corps']) === PERMALIENS_ESSAI_JETON) {
        return ['ok' => true, 'adresse' => $adresse, 'code' => 200, 'vu' => false,
            'message' => 'La forme lisible répond : ' . $adresse . ' a été servie par le site.'];
    }

    /**
     * La deuxième preuve, pour les hébergements qui ne s'appellent pas.
     *
     * Certains mutualisés coupent toute sortie réseau, et d'autres ne
     * servent qu'une requête à la fois : dans les deux cas l'appel
     * ci-dessus échoue alors que la réécriture, elle, marche très bien.
     * Refuser l'activation sur cette seule foi priverait de la forme
     * lisible des sites qui la servent parfaitement.
     *
     * D'où la preuve par le CONSTAT : la route d'essai note le moment où
     * elle a été atteinte PAR la réécriture. Si quelqu'un vient
     * d'ouvrir l'adresse dans un onglet, on sait que le serveur l'a
     * servie. C'est même la meilleure des deux preuves : elle vient d'une
     * vraie requête, pas d'une requête que le site se fabrique.
     */
    $vu = trim((string) (reglages_bdd(['permaliens_vu'])['permaliens_vu'] ?? ''));
    $frais = $vu !== '' && (time() - (strtotime($vu) ?: 0)) < PERMALIENS_VU_MINUTES * 60;
    if ($frais) {
        return ['ok' => true, 'adresse' => $adresse, 'code' => $r['code'], 'vu' => true,
            'message' => 'La forme lisible répond : le serveur a servi ' . $adresse
                . ' il y a moins de ' . PERMALIENS_VU_MINUTES . ' minutes. '
                . 'Le site n’a pas pu se le prouver tout seul, mais la réécriture marche.'];
    }

    return ['ok' => false, 'adresse' => $adresse, 'code' => $r['code'], 'vu' => false,
        'message' => $r['code'] === 0
            ? 'Le site n’a pas réussi à s’appeler lui-même : cet hébergement coupe sans doute '
              . 'les sorties réseau, ou ne sert qu’une requête à la fois. Ouvrez ' . $adresse
              . ' dans un nouvel onglet, puis revenez cliquer « Refaire l’essai » : si la page '
              . 'affiche « ' . PERMALIENS_ESSAI_JETON . ' », la réécriture marche et cela suffira.'
            : 'L’adresse ' . $adresse . ' a répondu ' . $r['code'] . ' au lieu de 200. '
              . 'Votre hébergement ignore le fichier .htaccess, ou mod_rewrite n’y est pas actif.'];
}

/**
 * Noter que la réécriture a servi cette adresse, et quand.
 *
 * Appelée par la route d'essai, et seulement quand la requête est arrivée
 * PAR la réécriture : atteindre `?p=permaliens-essai` à la main ne prouve
 * rien du tout, et le noter mentirait à l'écran des réglages.
 *
 * Une écriture par minute au plus. Cette adresse n'est presque jamais
 * demandée, mais rien n'empêche quelqu'un de la rafraîchir cent fois.
 */
function permaliens_constater(): void
{
    $vu = trim((string) (reglages_bdd(['permaliens_vu'])['permaliens_vu'] ?? ''));
    if ($vu !== '' && (time() - (strtotime($vu) ?: 0)) < 60) {
        return;
    }
    reglages_bdd_poser(['permaliens_vu' => maintenant()]);
}

/**
 * Un GET qui rend le corps TEL QUEL.
 *
 * `wp_get()` décode du JSON ; ici on attend un mot en clair, et c'est
 * justement sa platitude qui prouve quelque chose : une page d'erreur
 * maison servie en 200 ne ressemble pas à `permaliens-ok`.
 *
 * @return array{code: int, corps: string}
 */
function permaliens_sonder(string $url, int $delai = 6): array
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $delai,
            CURLOPT_CONNECTTIMEOUT => 3,
            // PAS de redirection suivie : un 301 vers autre chose est une
            // réponse en soi, et la suivre masquerait le diagnostic.
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_USERAGENT => 'Wakabi/' . VERSION . ' (essai permaliens)',
        ]);
        $corps = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return ['code' => $code, 'corps' => is_string($corps) ? $corps : ''];
    }

    $ctx = stream_context_create(['http' => [
        'method' => 'GET',
        'timeout' => $delai,
        'ignore_errors' => true,
        'follow_location' => 0,
        'header' => 'User-Agent: Wakabi/' . VERSION . " (essai permaliens)\r\n",
    ]]);
    $corps = @file_get_contents($url, false, $ctx);
    $code = 0;
    foreach ($http_response_header ?? [] as $l) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $l, $m)) {
            $code = (int) $m[1];
        }
    }
    return ['code' => $code, 'corps' => is_string($corps) ? $corps : ''];
}

/* ------------------------------------------------------------------ */
/* Ce que l'écran de réglages montre                                   */
/* ------------------------------------------------------------------ */

/**
 * Les adresses d'exemple, sous la forme choisie.
 *
 * Montrées AVANT d'enregistrer : un réglage d'adresses qu'on ne peut pas
 * voir avant de l'appliquer est un réglage qu'on applique à l'aveugle, et
 * les adresses sont la seule chose du site qu'on ne peut plus reprendre
 * une fois partie.
 *
 * @return list<array{string,string}>
 */
function permaliens_exemples(string $forme, string $base_blog, string $base_decors, bool $barre): array
{
    $b = permaliens_base($base_blog) ?: PERMALIENS_DEFAUTS['permaliens_blog'];
    $d = permaliens_base($base_decors) ?: PERMALIENS_DEFAUTS['permaliens_decors'];
    $fin = $barre ? '/' : '';

    if ($forme !== 'lisible') {
        return [
            ['Le catalogue',   'index.php?p=decors'],
            ['Un décor',       'index.php?p=decor&slug=soiree-blanche-lome'],
            ['Le blog',        'index.php?p=blog'],
            ['Un article',     'index.php?p=blog&a=remplir-une-salle-a-lome'],
            ['Devenir partenaire', 'index.php?p=partenaires'],
        ];
    }
    return [
        ['Le catalogue',   $d . $fin],
        ['Un décor',       $d . '/soiree-blanche-lome' . $fin],
        ['Le blog',        $b . $fin],
        ['Un article',     $b . '/remplir-une-salle-a-lome' . $fin],
        ['Devenir partenaire', 'partenaires' . $fin],
    ];
}

/**
 * Le champ caché `p` d'un formulaire de recherche, s'il en faut un.
 *
 * Un `method="get"` remplace toute la requête de son `action` par ses
 * propres champs : sans ce champ caché, chercher dans le catalogue
 * ramènerait à l'accueil. Sous la forme lisible, le chemin porte déjà la
 * page — le champ ferait un `?p=decors` inutile au bout d'une adresse
 * qu'on vient justement de rendre lisible.
 */
function permaliens_champ_page(string $page): string
{
    return permaliens_actifs()
        ? ''
        : '<input type="hidden" name="p" value="' . e($page) . '">';
}

/**
 * Le bloc à coller dans `.htaccess` quand l'essai échoue.
 *
 * Montré plutôt que caché : sur un mutualisé, le fichier est parfois
 * remplacé par celui d'un autre outil, et c'est la seule information qui
 * permette de réparer sans nous écrire.
 */
function permaliens_bloc_htaccess(): string
{
    return "<IfModule mod_rewrite.c>\n"
        . "  RewriteEngine On\n"
        . "  RewriteCond %{REQUEST_FILENAME} !-f\n"
        . "  RewriteCond %{REQUEST_FILENAME} !-d\n"
        . "  RewriteRule ^(.+)$ index.php?wk=$1 [B,L,QSA]\n"
        . "</IfModule>";
}
