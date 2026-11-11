<?php
/**
 * Le client de l'API du backend officiel de l'application.
 *
 * Les pages Sortir (Lomé, Cotonou), l'agenda et la billetterie ne lisent
 * RIEN dans la base locale : leurs données vivent dans le backend de
 * l'application Wakabi, et arrivent ici par son API. C'est une décision
 * à deux visages. D'un côté, aucune double saisie : l'application reste
 * la seule source, et le guide reflète ce qu'elle dit. De l'autre, une
 * dépendance : si le backend ne répond pas, ces pages doivent rester
 * debout — d'où le cache, le répit, et l'état vide en guise d'erreur.
 *
 * L'adresse du backend se règle dans `config.php`, clé `api_url` :
 *
 *     'api_url' => 'https://wakabi-backoffice.amsgomado.com/api/v1',
 *
 * Vide ou absente, les pages affichent leur état vide, sans erreur.
 *
 * LA VILLE NE SE DEMANDE PAS À L'API. Les deux routes rendent tout, et
 * le tri par ville se fait ici, sur ce qui est arrivé. Deux raisons : un
 * paramètre que l'API ne connaît pas serait ignoré en silence — on
 * croirait filtrer sans filtrer — et deux adresses pour la même liste,
 * c'est deux entrées de cache qui vieillissent séparément.
 *
 * Le contrat, tel que le client l'attend — chaque champ manque peut-être,
 * le client les tolère tous absents, sauf le titre (ou le nom) qui décide
 * si l'entrée existe :
 *
 *   GET {api_url}/events
 *     [ { "id": "senzaa-2026", "titre": "Senzaa En Concert",
 *         "lieu": "Palais des Congrès", "ville": "lome",
 *         "date": "2026-12-18T18:00:00+00:00", "prix_min": 5000,
 *         "categorie": "Concerts live", "billets": true,
 *         "image": "https://..." }, ... ]
 *
 *   GET {api_url}/venues
 *     [ { "id": "best-t", "nom": "Best.t Lounge Bar Restaurant",
 *         "categorie": "Bars", "adresse": "Carrefour Elavagnon, Lomé",
 *         "ville": "lome", "info": "Capacité d'accueil : 100 personnes",
 *         "description": "Le Best T Lomé est un bar lounge moderne…",
 *         "commodites": ["Parking", "Restauration", …],
 *         "horaires": [ { "jours": "Lundi au Vendredi",
 *                         "heures": "11:00 à 05:00" }, … ],
 *         "telephone": "+228 91 09 21 21",
 *         "image": "https://..." }, ... ]
 *
 * Les champs de la fiche (description, commodites, horaires, telephone)
 * manquent peut-être sur un lieu : la fiche omet alors la section, sans
 * erreur. Seuls `nom` — et une adresse pour la carte — comptent vraiment.
 *
 * Les dates se lisent en heure de Lomé et de Cotonou, c'est-à-dire en
 * UTC : les deux villes sont à GMT+0, et `gmdate` suffit donc — pas de
 * fuseau à transporter, pas de locale à régler sur un mutualisé.
 */

/** Le répit après une panne : pendant cinq minutes, on ne rappelle pas. */
const API_REPIT = 300;

/** Combien de temps une réponse reste en cache. */
const API_CACHE_SECONDES = 300;

/**
 * CE QUE LA VITRINE MONTRE, ET PAS UN DE PLUS.
 *
 * Le site n'est pas le catalogue : il en est la devanture. Sept lieux,
 * six événements, et le reste s'ouvre dans l'application — c'est une
 * décision de produit, pas une limite technique. L'API rend tout, le
 * tri et le compte se font ici.
 *
 * Les deux nombres vivent à UN SEUL endroit : cinq pages les appliquent,
 * et cinq nombres écrits à la main auraient fini par diverger le jour où
 * l'on en change un.
 */
const VITRINE_EVENEMENTS = 6;
const VITRINE_LIEUX = 7;

/**
 * L'adresse du backend officiel, sans barre finale.
 *
 * @return string|null null tant que `api_url` n'est pas réglée.
 */
function api_base(): ?string
{
    $u = reglage('api_url', '');
    $u = is_string($u) ? trim($u) : '';
    return $u === '' ? null : rtrim($u, '/');
}

/**
 * Un GET sur l'API, avec repli quand cURL manque.
 *
 * Le même dessin que `wp_get()` : cURL d'abord, flux sinon, code de
 * réponse dans les deux cas. Rend le corps décodé, ou null — null et
 * pas une exception, parce qu'aucun appelant ne peut rien y faire :
 * il affichera son état vide.
 *
 * @return array|null null si l'API ne répond pas ou ne dit pas du JSON.
 */
function api_get(string $chemin): ?array
{
    $base = api_base();
    if ($base === null) {
        return null;
    }
    if (api_en_panne()) {
        return api_cache_lire($chemin, API_CACHE_SECONDES * 24);
    }

    $url = $base . $chemin;
    $brut = null;
    $code = 0;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 6,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_USERAGENT => 'Wakabi/' . VERSION,
        ]);
        $rep = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if (is_string($rep)) {
            $brut = $rep;
        }
    } else {
        $ctx = stream_context_create(['http' => [
            'method' => 'GET',
            'timeout' => 6,
            'ignore_errors' => true,
            'header' => 'User-Agent: Wakabi/' . VERSION,
        ]]);
        $brut = @file_get_contents($url, false, $ctx);
        foreach ($http_response_header ?? [] as $l) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $l, $m)) {
                $code = (int) $m[1];
            }
        }
    }

    /* Un code hors 2xx, ou un corps qui ne se décode pas : panne notée,
       et le cache un peu vieux vaut mieux que rien du tout. */
    $decode = null;
    if ($code >= 200 && $code < 300 && is_string($brut)) {
        $essai = json_decode($brut, true);
        if (is_array($essai)) {
            $decode = $essai;
        }
    }
    api_panne_noter($decode === null);
    if ($decode === null) {
        return api_cache_lire($chemin, API_CACHE_SECONDES * 24);
    }

    api_cache_ecrire($chemin, $decode);
    return $decode;
}

/**
 * La liste des événements, normalisée.
 *
 * @param string|null $ville 'lome' ou 'cotonou', null pour tout.
 * @return array<int, array{id: string, titre: string, lieu: string, ville: string,
 *     date: string, prix_min: int, categorie: string, billets: bool, image: string}>
 */
function api_evenements(?string $ville = null): array
{
    $bruts = api_get('/events');
    if ($bruts === null) {
        return [];
    }
    /* L'API peut répondre par une liste nue ou par un objet qui la porte. */
    if (isset($bruts['data']) && is_array($bruts['data'])) {
        $bruts = $bruts['data'];
    }
    $out = [];
    foreach ($bruts as $e) {
        if (!is_array($e)) {
            continue;
        }
        $titre = api_champ($e, ['titre', 'title', 'nom', 'name']);
        if ($titre === '') {
            continue;
        }
        $ev = [
            'id' => api_champ($e, ['id', 'slug']),
            'titre' => $titre,
            'lieu' => api_champ($e, ['lieu', 'venue', 'endroit']),
            'ville' => mb_strtolower(api_champ($e, ['ville', 'city'])),
            'date' => api_champ($e, ['date', 'date_debut', 'starts_at', 'start']),
            'prix_min' => (int) (api_champ($e, ['prix_min', 'prix', 'price_min', 'price']) ?: 0),
            'categorie' => api_champ($e, ['categorie', 'category', 'type']),
            'billets' => api_vrai($e['billets'] ?? $e['billetterie'] ?? $e['tickets'] ?? false),
            'image' => api_champ($e, ['image', 'image_url', 'photo', 'affiche']),
            'categories' => $e['categories'] ?? $e['tiers'] ?? null,
        ];
        if ($ville !== null && $ev['ville'] !== '' && $ev['ville'] !== $ville) {
            continue;
        }
        $out[] = $ev;
    }
    /* Du plus proche au plus lointain : une date absente part à la fin. */
    usort($out, fn($a, $b) => strtotime($a['date'] ?: '9999') <=> strtotime($b['date'] ?: '9999'));
    return $out;
}

/**
 * La liste des lieux, normalisée.
 *
 * @param string|null $ville 'lome' ou 'cotonou', null pour tout.
 * @return array<int, array{id: string, nom: string, categorie: string,
 *     adresse: string, info: string, image: string, photos: array<int, string>}>
 */
function api_lieux(?string $ville = null): array
{
    $bruts = api_get('/venues');
    if ($bruts === null) {
        return [];
    }
    if (isset($bruts['data']) && is_array($bruts['data'])) {
        $bruts = $bruts['data'];
    }
    $out = [];
    foreach ($bruts as $l) {
        if (!is_array($l)) {
            continue;
        }
        $nom = api_champ($l, ['nom', 'name', 'titre', 'title']);
        if ($nom === '') {
            continue;
        }
        $li = [
            'id' => api_champ($l, ['id', 'slug']),
            'nom' => $nom,
            'categorie' => api_champ($l, ['categorie', 'category', 'type']),
            'adresse' => api_champ($l, ['adresse', 'address', 'quartier']),
            'ville' => mb_strtolower(api_champ($l, ['ville', 'city'])),
            'info' => api_champ($l, ['info', 'capacite', 'sous_titre']),
            'description' => api_champ($l, ['description', 'presentation', 'texte']),
            'commodites' => api_liste($l['commodites'] ?? $l['amenities'] ?? $l['services'] ?? null),
            'horaires' => api_horaires($l['horaires'] ?? $l['hours'] ?? null),
            'telephone' => api_champ($l, ['telephone', 'phone', 'tel']),
            'image' => api_champ($l, ['image', 'image_url', 'photo', 'logo']),
            'photos' => api_liste($l['photos'] ?? $l['photos_url'] ?? $l['galerie'] ?? $l['gallery'] ?? $l['images'] ?? null),
        ];
        $v = mb_strtolower(api_champ($l, ['ville', 'city']));
        if ($ville !== null && $v !== '' && $v !== $ville) {
            continue;
        }
        $out[] = $li;
    }
    return $out;
}

/**
 * Un lieu par son identifiant — la fiche détaillée.
 *
 * La liste des lieux sert de source : une seule route à l'API, un seul
 * cache, et la fiche ne peut pas diverger de ce que la liste montre.
 * Si l'API n'a pas d'identifiant, le nom sert de clé de secours — deux
 * lieux du même nom se confondent, mais une liste sans identifiant
 * serait inatteignable tout court.
 *
 * @return array|null null si aucun lieu ne porte cet identifiant.
 */
function api_lieu_par_id(string $id): ?array
{
    $id = trim($id);
    if ($id === '') {
        return null;
    }
    foreach (api_lieux(null) as $li) {
        if (($li['id'] !== '' && $li['id'] === $id) || ($li['id'] === '' && $li['nom'] === $id)) {
            return $li;
        }
    }
    return null;
}

/**
 * Une liste de chaînes, quel que soit le caprice du JSON.
 *
 * Les commodités arrivent tantôt en liste de textes, tantôt en liste
 * d'objets, tantôt en texte unique : les trois formes donnent une liste
 * de textes propres, vide si l'API n'a rien dit.
 *
 * @return list<string>
 */
function api_liste(mixed $v): array
{
    if ($v === null) {
        return [];
    }
    if (is_string($v)) {
        return trim($v) === '' ? [] : [trim($v)];
    }
    if (!is_array($v)) {
        return [];
    }
    $out = [];
    foreach ($v as $e) {
        if (is_string($e) && trim($e) !== '') {
            $out[] = trim($e);
        } elseif (is_array($e)) {
            $n = api_champ($e, ['nom', 'name', 'titre', 'title', 'texte', 'label']);
            if ($n !== '') {
                $out[] = $n;
            }
        }
    }
    return $out;
}

/**
 * Des horaires d'ouverture, en paires [jours, heures].
 *
 * @return list<array{jours: string, heures: string}>
 */
function api_horaires(mixed $v): array
{
    if (!is_array($v)) {
        return [];
    }
    $out = [];
    foreach ($v as $e) {
        if (is_array($e)) {
            $j = api_champ($e, ['jours', 'jour', 'days', 'day', 'label']);
            $h = api_champ($e, ['heures', 'hours', 'horaire', 'ouverture', 'horaires']);
            if ($j !== '' || $h !== '') {
                $out[] = ['jours' => $j, 'heures' => $h];
            }
        } elseif (is_string($e) && trim($e) !== '') {
            /* « Lundi au Vendredi : 11:00 à 05:00 » d'un bloc : on coupe
               au premier deux-points, s'il y en a un. */
            $m = explode(':', $e, 2);
            $out[] = count($m) === 2
                ? ['jours' => trim($m[0]), 'heures' => trim($m[1])]
                : ['jours' => trim($e), 'heures' => ''];
        }
    }
    return $out;
}

/**
 * L'agenda : tous les événements à venir, dans l'ordre des dates.
 *
 * @return array<int, array> ce que rend `api_evenements()`, passé au crible.
 */
function api_agenda(): array
{
    $aujourdhui = gmdate('Y-m-d');
    return array_values(array_filter(
        api_evenements(null),
        fn(array $e) => $e['date'] === '' || substr($e['date'], 0, 10) >= $aujourdhui
    ));
}

/**
 * Les événements dont les billets sont en vente.
 *
 * @return array<int, array> ce que rend `api_evenements()`, passé au crible.
 */
function api_en_vente(): array
{
    return array_values(array_filter(api_evenements(null), fn(array $e) => $e['billets']));
}

/**
 * Filtre une liste par catégorie, tolérant sur l'orthographe.
 *
 * Les puces de filtre portent « Auberges », l'API répond peut-être
 * « Auberge » : la comparaison cherche l'un dans l'autre, en minuscules.
 *
 * @param array<int, array> $liste ce que rendent `api_evenements()` ou `api_lieux()`.
 */
function api_par_categorie(array $liste, string $cle): array
{
    $cle = mb_strtolower(trim($cle));
    if ($cle === '') {
        return $liste;
    }
    return array_values(array_filter($liste, function (array $e) use ($cle): bool {
        $c = mb_strtolower((string) ($e['categorie'] ?? ''));
        return $c !== '' && (str_contains($c, $cle) || str_contains($cle, $c));
    }));
}

/**
 * La clé d'URL d'une catégorie de filtre : « Boîtes de nuit » -> 'boites-de-nuit'.
 */
function api_cle_type(string $categorie): string
{
    /* api_plat rabote les accents et les majuscules : « Boîtes de nuit »
       donne « boites-de-nuit », pas « bo-tes-de-nuit ». */
    $c = api_plat($categorie);
    $c = str_replace(['’', '\''], ' ', $c);
    $c = preg_replace('~[^a-z0-9]+~', '-', $c) ?? '';
    return trim($c, '-');
}

/**
 * Un texte sans accent ni majuscule, pour comparer une recherche.
 *
 * « Cadjèhoun » doit répondre à « cadjehoun » : on rabote les accents à
 * la main plutôt qu'avec `iconv`, dont le comportement TRANSLIT change
 * d'une locale à l'autre et surprend sur un mutualisé.
 */
function api_plat(string $s): string
{
    $s = mb_strtolower(trim($s));
    return strtr($s, [
        'à' => 'a', 'â' => 'a', 'ä' => 'a', 'é' => 'e', 'è' => 'e', 'ê' => 'e',
        'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u',
        'û' => 'u', 'ü' => 'u', 'ç' => 'c', 'œ' => 'oe', '’' => "'",
    ]);
}

/**
 * Une liste filtrée par le texte cherché.
 *
 * La recherche porte sur tout ce que l'entrée dit d'elle-même : titre ou
 * nom, lieu, catégorie, adresse, info, description, ville. Sans accent ni
 * majuscule des deux côtés — « Fidjrossè » répond à « fidjrosse ».
 *
 * @param array<int, array> $liste ce que rendent `api_evenements()` ou `api_lieux()`.
 */
function api_cherche(array $liste, string $q): array
{
    $q = api_plat($q);
    if ($q === '') {
        return $liste;
    }
    return array_values(array_filter($liste, function (array $e) use ($q): bool {
        $morceaux = [
            (string) ($e['titre'] ?? ''), (string) ($e['nom'] ?? ''),
            (string) ($e['lieu'] ?? ''), (string) ($e['categorie'] ?? ''),
            (string) ($e['adresse'] ?? ''), (string) ($e['info'] ?? ''),
            (string) ($e['description'] ?? ''), (string) ($e['ville'] ?? ''),
        ];
        $botte = api_plat(implode(' ', array_filter($morceaux, fn(string $v) => $v !== '')));
        return $botte !== '' && str_contains($botte, $q);
    }));
}

/**
 * Les catégories présentes dans une liste, prêtes pour des puces.
 *
 * Dérivées des données, pas écrites à la main : une nouvelle catégorie
 * qui arrive par l'API gagne sa puce toute seule.
 *
 * @param array<int, array> $liste
 * @return array<string, string> [clé d'URL => libellé]
 */
function api_categories(array $liste): array
{
    $out = [];
    foreach ($liste as $e) {
        $c = (string) ($e['categorie'] ?? '');
        if ($c === '') {
            continue;
        }
        $cle = api_cle_type($c);
        if ($cle === '' || isset($out[$cle])) {
            continue;
        }
        $out[$cle] = $c;
    }
    ksort($out);
    return $out;
}

/** « Lomé » ou « Cotonou », dit proprement ; rien si la ville est inconnue. */
function api_ville_label(string $ville): string
{
    return $ville === 'cotonou' ? 'Cotonou' : ($ville === 'lome' ? 'Lomé' : '');
}

/**
 * L'adresse d'une page du guide, avec ses filtres.
 *
 * Les filtres voyagent dans l'adresse : une recherche filtrée est une
 * adresse qu'on peut coller à un ami. Les paramètres vides ne partent
 * pas — `?p=agenda` reste `?p=agenda`, pas `?p=agenda&periode=&q=`.
 *
 * @param array<string, string|int> $params
 */
function api_url_filtres(string $page, array $params = []): string
{
    $q = array_merge(['p' => $page], $params);
    $q = array_filter($q, fn($v) => $v !== null && $v !== '');
    $s = http_build_query($q);
    return url('?' . $s);
}

/* ------------------------------------------------------------------ */
/* Les outils : dates, prix, cache, panne                              */
/* ------------------------------------------------------------------ */

/**
 * Le premier champ présent, en texte propre.
 *
 * Le backend est celui de l'application, pas le nôtre : ses noms de
 * champs peuvent bouger d'une version à l'autre. Plutôt qu'un contrat
 * rigide qui casse au premier renommage, chaque lecteur énonce les noms
 * qu'il accepte, dans l'ordre où il les préfère.
 *
 * @param array<string, mixed> $e
 * @param list<string> $cles
 */
function api_champ(array $e, array $cles): string
{
    foreach ($cles as $c) {
        $v = $e[$c] ?? null;
        if (is_string($v) && trim($v) !== '') {
            return trim($v);
        }
        if (is_numeric($v)) {
            return (string) $v;
        }
    }
    return '';
}

/** La vérité, telle que la dit un JSON : true, "1", "true", 1. */
function api_vrai(mixed $v): bool
{
    return $v === true || $v === 1 || $v === '1' || $v === 'true';
}

/** Le jour du mois : « 18 ». */
function api_date_jour(string $iso): string
{
    $t = strtotime($iso);
    return $t ? gmdate('j', $t) : '';
}

/** Le mois abrégé, en majuscules : « DÉC ». */
function api_date_mois(string $iso): string
{
    static $mois = ['', 'JAN', 'FÉV', 'MAR', 'AVR', 'MAI', 'JUIN',
                    'JUIL', 'AOÛT', 'SEPT', 'OCT', 'NOV', 'DÉC'];
    $t = strtotime($iso);
    return $t ? $mois[(int) gmdate('n', $t)] : '';
}

/** L'heure : « 18:00 ». */
function api_date_heure(string $iso): string
{
    $t = strtotime($iso);
    return $t ? gmdate('H\hi', $t) : '';
}

/** Le mois en toutes lettres avec l'année : « Décembre 2026 ». */
function api_date_mois_complet(string $iso): string
{
    static $mois = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
                    'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
    $t = strtotime($iso);
    return $t ? $mois[(int) gmdate('n', $t)] . ' ' . gmdate('Y', $t) : '';
}

/**
 * « Dans 44 jours », « Demain », « Aujourd'hui » — ou rien si c'est passé.
 */
function api_dans_combien(string $iso): ?string
{
    $t = strtotime($iso);
    if ($t === false) {
        return null;
    }
    $n = (int) floor(($t - time()) / 86400);
    if ($n < 0) {
        return null;
    }
    if ($n === 0) {
        return 'Aujourd\'hui';
    }
    if ($n === 1) {
        return 'Demain';
    }
    return 'Dans ' . $n . ' jours';
}

/** Un prix, écrit comme ici : « 5 000 FCFA ». Rien si aucun prix. */
function api_prix(int|string $prix): string
{
    $n = (int) $prix;
    return $n > 0 ? number_format($n, 0, ',', ' ') . ' FCFA' : '';
}

/** L'initiale qui tient lieu d'image tant que l'API n'en fournit pas. */
function api_initiale(string $texte): string
{
    $t = mb_strtoupper(mb_substr(trim($texte), 0, 1));
    return $t !== '' ? $t : '·';
}

/**
 * Le dégradé d'un visuel d'attente, choisi par l'initiale.
 *
 * Cinq dégradés, tournés par la première lettre : deux cartes côte à
 * côte ne portent presque jamais la même couleur, sans qu'on ait rien
 * à choisir ni à stocker.
 */
function api_visuel(string $texte): string
{
    $t = mb_strtoupper(mb_substr(trim($texte), 0, 1));
    $n = $t === '' ? 0 : (mb_ord($t) % 5);
    return ['visuel-bleu', 'visuel-vert', 'visuel-orange', 'visuel-violet', 'visuel-rouge'][$n];
}

/** Le lien « Voir sur la carte » d'un lieu : une recherche Google Maps. */
function api_carte(string $adresse): string
{
    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($adresse);
}

/* --------------------------- le cache --------------------------- */

function api_cache_chemin(string $cle): string
{
    return dossier_cache() . '/guide-api-' . md5($cle) . '.json';
}

/**
 * Une réponse du cache, si elle a moins de `$ttl` secondes.
 *
 * @return array|null
 */
function api_cache_lire(string $cle, int $ttl): ?array
{
    $f = api_cache_chemin($cle);
    if (!is_file($f) || (time() - (int) @filemtime($f)) > $ttl) {
        return null;
    }
    $d = json_decode((string) @file_get_contents($f), true);
    return is_array($d) ? $d : null;
}

function api_cache_ecrire(string $cle, array $donnees): void
{
    @file_put_contents(api_cache_chemin($cle), json_encode($donnees, JSON_UNESCAPED_UNICODE));
}

/** L'API vient-elle d'échouer ? Alors on ne la rappelle pas tout de suite. */
function api_en_panne(): bool
{
    $f = dossier_cache() . '/guide-api-panne';
    return is_file($f) && (time() - (int) @filemtime($f)) < API_REPIT;
}

function api_panne_noter(bool $echec): void
{
    $f = dossier_cache() . '/guide-api-panne';
    if ($echec) {
        @file_put_contents($f, (string) time());
    } elseif (is_file($f)) {
        @unlink($f);
    }
}
