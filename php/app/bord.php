<?php
/**
 * Ce que le tableau de bord demande, et que personne d'autre ne demande.
 *
 * Trois questions, et elles n'ont de sens que sur cet écran :
 *
 *   1. Qu'est-ce qui m'attend, MOI, rangé du plus vieux au plus récent ?
 *   2. Qu'est-ce que j'ai touché en dernier, pour y revenir ?
 *   3. Où puis-je aller, sachant ce que mon rôle ouvre vraiment ?
 *
 * La première remplace une rangée de compteurs. Un compteur dit « 2 décors
 * à relire » et oblige à ouvrir un autre écran pour savoir lesquels ; il ne
 * dit jamais que l'un des deux attend depuis six jours. Une liste d'objets
 * dit les deux, et porte le bouton au bout de la ligne.
 */

declare(strict_types=1);

/** Au-delà, une soumission n'attend plus : elle traîne. */
const BORD_VIEUX_JOURS = 3;

/**
 * Tout ce qui attend une décision de CE compte, le plus vieux d'abord.
 *
 * Chaque source est gardée par le droit qui ouvre son écran. Sans ce
 * filtre, un coordinateur verrait la ligne « comptes suspendus » et son
 * bouton le renverrait d'où il vient, sans un mot — le même défaut que les
 * raccourcis avaient, et pour la même raison.
 *
 * @return list<array{genre:string,ton:string,titre:string,qui:string,
 *                    depuis:string,lien:string,action:string}>
 */
function bord_a_traiter(?array $u): array
{
    $lignes = [];

    if (droit($u, 'valider')) {
        foreach (decors_en_attente() as $d) {
            $lignes[] = [
                'genre' => 'Décor', 'ton' => 'decor',
                'titre' => (string) $d['titre'],
                'qui' => (string) ($d['auteur_nom'] ?: 'Équipe Wakabi'),
                'depuis' => (string) ($d['soumis_le'] ?: $d['maj_le']),
                'lien' => '?p=relecture', 'action' => 'Relire',
            ];
        }
    }

    if (droit($u, 'articles') && droit($u, 'valider')) {
        foreach (articles_en_attente() as $a) {
            $lignes[] = [
                'genre' => 'Article', 'ton' => 'article',
                'titre' => (string) $a['titre'],
                'qui' => (string) ($a['propose_par'] ?: 'La rédaction'),
                'depuis' => (string) ($a['soumis_le'] ?: $a['maj_le']),
                'lien' => '?p=blog-relecture', 'action' => 'Relire',
            ];
        }
    }

    if (droit($u, 'regie')) {
        foreach (campagnes_email_a_relire() as $c) {
            $lignes[] = [
                'genre' => 'Régie', 'ton' => 'regie',
                'titre' => (string) $c['sujet'],
                'qui' => (string) ($c['auteur_nom'] ?: 'Équipe Wakabi'),
                'depuis' => (string) ($c['maj_le'] ?: $c['cree_le']),
                'lien' => '?p=regie', 'action' => 'Relire',
            ];
        }
    }

    /**
     * Le plus vieux d'abord, et c'est tout l'ordre.
     *
     * Trier par genre grouperait les décors ensemble et cacherait qu'un
     * article attend depuis huit jours derrière deux décors d'hier. Ce
     * qu'on veut voir en haut, c'est ce qui a le plus attendu.
     */
    usort($lignes, static fn(array $a, array $b): int
        => strcmp((string) $a['depuis'], (string) $b['depuis']));

    /**
     * Les comptes suspendus ferment la liste, et en UNE ligne.
     *
     * Ils n'ont pas d'âge commun et ne se relisent pas un par un : c'est un
     * état à regarder, pas une file à vider. Une ligne par compte aurait
     * noyé trois relectures sous quinze suspensions.
     */
    if (droit($u, 'comptes')) {
        $n = compter('SELECT COUNT(*) AS n FROM utilisateurs WHERE suspendu = 1');
        if ($n > 0) {
            $lignes[] = [
                'genre' => 'Compte', 'ton' => 'compte',
                'titre' => $n . ' compte' . ($n > 1 ? 's suspendus' : ' suspendu'),
                'qui' => '', 'depuis' => '',
                'lien' => '?p=comptes', 'action' => 'Voir',
            ];
        }
    }

    return $lignes;
}

/** Les campagnes e-mail qui attendent une relecture, la plus vieille d'abord. */
function campagnes_email_a_relire(): array
{
    return db()->query("SELECT c.*, u.nom AS auteur_nom
                        FROM campagnes_email c LEFT JOIN utilisateurs u ON u.id = c.auteur_id
                        WHERE c.statut = 'en_relecture'
                        ORDER BY COALESCE(c.maj_le, c.cree_le) ASC")->fetchAll();
}

/**
 * Ce qui attend, mais peut attendre encore.
 *
 * Séparé de la file : un brouillon jamais soumis n'appelle aucune
 * décision, il informe. Mélangé aux relectures, il allonge une liste dont
 * la longueur est justement le signal.
 *
 * @return list<array{0:string,1:int,2:string}> lien, nombre, libellé
 */
function bord_sans_urgence(?array $u, array $stats, int $regie_en_file): array
{
    $out = [];
    if (droit($u, 'decors_tous') && $stats['brouillons']) {
        $out[] = ['?p=catalogue&statut=brouillon', (int) $stats['brouillons'],
                  'brouillon' . ($stats['brouillons'] > 1 ? 's' : '') . ' jamais soumis'];
    }
    if (droit($u, 'decors_tous') && $stats['corrections']) {
        $out[] = ['?p=catalogue&statut=corrections', (int) $stats['corrections'],
                  'décor' . ($stats['corrections'] > 1 ? 's' : '') . ' en correction chez leur auteur'];
    }
    if (droit($u, 'regie') && $regie_en_file) {
        $out[] = ['?p=regie', $regie_en_file,
                  'e-mail' . ($regie_en_file > 1 ? 's' : '') . ' en attente d’envoi'];
    }
    return $out;
}

/**
 * Les dernières choses que CE compte a touchées.
 *
 * Lues dans le journal, qui existe déjà et enregistre l'acteur de chaque
 * geste. C'est ce qui manque quand on referme l'ordinateur au milieu d'une
 * relecture : rouvrir le produit le lendemain ne dit pas où l'on en était.
 *
 * Un objet ne paraît qu'UNE fois, à sa date la plus récente : publier puis
 * corriger puis republier le même décor remplirait sinon les quatre places
 * avec le même titre.
 */
function bord_reprendre(?array $u, int $combien = 4): array
{
    $id = (string) ($u['id'] ?? '');
    if ($id === '') {
        return [];
    }
    try {
        $s = db()->prepare("SELECT action, objet_type, objet_id, objet_titre, cree_le
                            FROM journal
                            WHERE acteur_id = ? AND objet_id IS NOT NULL AND objet_titre IS NOT NULL
                            ORDER BY cree_le DESC LIMIT 60");
        $s->execute([$id]);
        $brut = $s->fetchAll();
    } catch (PDOException) {
        // Table absente sur une installation à moitié migrée : le bloc
        // disparaît, le reste du tableau de bord tient debout.
        return [];
    }

    $vus = [];
    foreach ($brut as $l) {
        $cle = $l['objet_type'] . '|' . $l['objet_id'];
        if (isset($vus[$cle])) {
            continue;
        }
        $vus[$cle] = [
            'genre' => bord_genre_libelle((string) $l['objet_type']),
            'ton' => (string) $l['objet_type'],
            'titre' => (string) $l['objet_titre'],
            'quand' => (string) $l['cree_le'],
            'quoi' => journal_libelle((string) $l['action']),
            'lien' => bord_lien_objet((string) $l['objet_type'], (string) $l['objet_id']),
        ];
        if (count($vus) >= $combien) {
            break;
        }
    }
    return array_values($vus);
}

/** Le nom qu'on donne à un type d'objet du journal, en français. */
function bord_genre_libelle(string $type): string
{
    return match ($type) {
        'decor' => 'Décor',
        'article' => 'Article',
        'compte' => 'Compte',
        'campagne' => 'Campagne',
        'lien' => 'Lien court',
        'liste' => 'Liste de contacts',
        'offre' => 'Offre',
        'rapport' => 'Rapport',
        'reglage', 'reglages' => 'Réglages',
        'sauvegarde' => 'Sauvegarde',
        'contact' => 'Message',
        default => ucfirst($type),
    };
}

/**
 * Où mène un objet du journal.
 *
 * Un type inconnu rend l'écran de la famille plutôt qu'un lien mort : le
 * journal garde des lignes plus vieilles que le code qui les a écrites, et
 * une entrée de 2024 ne doit pas casser le tableau de bord de 2026.
 */
function bord_lien_objet(string $type, string $id): string
{
    return match ($type) {
        'decor' => '?p=modifier&id=' . rawurlencode($id),
        'article' => '?p=blog-editer&id=' . rawurlencode($id),
        'compte' => '?p=organisateur&id=' . rawurlencode($id),
        'campagne' => '?p=regie',
        'lien' => '?p=liens',
        'liste' => '?p=regie-carnet',
        'offre' => '?p=offres',
        'rapport' => '?p=rapports',
        'reglage', 'reglages' => '?p=reglages',
        'sauvegarde' => '?p=sauvegardes',
        default => '?p=journal',
    };
}

/**
 * Où aller, et combien il y en a.
 *
 * Une pastille chiffrée dit deux choses d'un coup : la destination, et s'il
 * s'y passe quelque chose. « Comptes 340 » se lit plus vite que « Comptes /
 * Rôles, offres, suspensions », et occupe le quart de la place — ce qui
 * permet de tout montrer sans faire défiler.
 *
 * Chaque entrée porte le droit qui ouvre son écran, et la liste est filtrée
 * dessus : un raccourci qui refuse est pire qu'un raccourci absent, parce
 * qu'on le réessaie.
 */
function bord_ou_aller(?array $u, array $stats): array
{
    $n = static function (string $sql): ?int {
        try {
            return (int) db()->query($sql)->fetchColumn();
        } catch (PDOException) {
            return null;
        }
    };

    $tout = [
        ['?p=catalogue', 'Décors', (int) $stats['publies'], 'decors_tous'],
        ['?p=comptes', 'Comptes', (int) $stats['comptes'], 'comptes'],
        ['?p=blog-admin', 'Le blog', $n("SELECT COUNT(*) FROM articles WHERE statut='publie'"), 'articles'],
        ['?p=regie', 'Régie e-mail', $n("SELECT COUNT(*) FROM campagnes_email"), 'regie'],
        ['?p=liens', 'Liens courts', $n('SELECT COUNT(*) FROM liens'), 'liens'],
        ['?p=offres', 'Les offres', count(formules_actives()), 'reglages'],
        ['?p=diffusion', 'Notifications push', null, 'push'],
        ['?p=scan', 'Contrôle d’entrée', null, 'scan'],
        /* Les rapports et la facturation ne refusent personne : l'équipe y
           voit la plateforme entière, un organisateur ses propres décors et
           ses propres factures. Ils n'ont donc pas de droit à porter. */
        ['?p=rapports', 'Rapports', null, ''],
        ['?p=facturation', 'Facturation', null, ''],
        ['?p=reglages', 'Réglages', null, 'reglages'],
        ['?p=sauvegardes', 'Sauvegardes', null, 'reglages'],
        ['?p=journal', 'Le journal', null, 'comptes'],
    ];

    return array_values(array_filter(
        $tout,
        static fn(array $e): bool => $e[3] === '' || droit($u, $e[3])
    ));
}

/**
 * Ce que « + Nouveau » propose, selon ce qu'on a le droit de fabriquer.
 *
 * Un seul bouton plutôt que quatre : la barre de commande porte déjà un
 * champ de recherche, et quatre boutons de création côte à côte se lisent
 * comme un menu alors qu'un seul geste sur cinq en est un.
 */
function bord_nouveau(?array $u): array
{
    $tout = [
        ['?p=nouveau', 'Un décor', 'decors_siens'],
        ['?p=blog-editer', 'Un article', 'articles'],
        ['?p=regie-ecrire', 'Une campagne e-mail', 'regie'],
        ['?p=liens', 'Un lien court', 'liens'],
        ['?p=comptes', 'Un compte', 'comptes'],
    ];
    return array_values(array_filter(
        $tout,
        static fn(array $e): bool => droit($u, $e[2])
    ));
}

/**
 * L'âge d'une soumission, dit comme on le dirait à voix haute.
 *
 * « il y a 6 jours » se comprend sans calcul ; « 2026-09-19T08:12:00Z »
 * demande de soustraire deux dates de tête, à chaque ligne.
 */
function bord_depuis(string $iso): string
{
    $t = strtotime($iso);
    if ($t === false) {
        return '';
    }
    $s = max(0, time() - $t);
    if ($s < 3600) {
        $m = max(1, (int) round($s / 60));
        return 'il y a ' . $m . ' min';
    }
    if ($s < 86400) {
        $h = (int) round($s / 3600);
        return 'il y a ' . $h . ' h';
    }
    $j = (int) floor($s / 86400);
    return $j === 1 ? 'hier' : 'il y a ' . $j . ' jours';
}

/** Cette ligne a-t-elle trop attendu ? */
function bord_urgent(string $iso): bool
{
    $t = strtotime($iso);
    return $t !== false && (time() - $t) >= BORD_VIEUX_JOURS * 86400;
}

/* ------------------------------------------------------------------ */
/* La recherche de la barre de commande                                */
/* ------------------------------------------------------------------ */

/** Combien de résultats par famille. Au-delà, on renvoie à l'écran dédié. */
const BORD_RESULTATS = 6;

/**
 * Chercher dans tout ce qu'on a le droit de voir, en une fois.
 *
 * Atteindre un décor précis demandait d'ouvrir le catalogue puis d'y
 * filtrer : deux navigations pour un coup d'oeil, vingt fois par jour. Et
 * chaque famille avait sa propre recherche, à un endroit différent — donc
 * il fallait d'abord se rappeler DANS QUEL écran chercher.
 *
 * Le droit garde chaque famille, comme partout ailleurs. Une recherche qui
 * rendrait un compte à quelqu'un qui n'a pas `comptes` serait une fuite,
 * pas une commodité : le nom et l'adresse s'y lisent avant tout clic.
 *
 * @return list<array{famille:string,lignes:list<array{titre:string,note:string,lien:string}>,tout:string}>
 */
function bord_chercher(?array $u, string $q): array
{
    $q = trim($q);
    if (mb_strlen($q) < 2) {
        return [];
    }
    $m = '%' . $q . '%';
    $n = BORD_RESULTATS;
    $out = [];

    $prendre = static function (string $sql, array $args): array {
        try {
            $s = db()->prepare($sql);
            $s->execute($args);
            return $s->fetchAll();
        } catch (PDOException) {
            return [];
        }
    };

    if (droit($u, 'decors_tous')) {
        $r = $prendre("SELECT id, titre, slug, statut FROM decors
                       WHERE titre LIKE ? OR slug LIKE ?
                       ORDER BY maj_le DESC LIMIT $n", [$m, $m]);
        if ($r) {
            $out[] = ['famille' => 'Décors', 'tout' => '?p=catalogue&q=' . rawurlencode($q),
                'lignes' => array_map(static fn(array $d): array => [
                    'titre' => (string) $d['titre'],
                    'note' => '/' . $d['slug'] . ' · ' . statut_libelle((string) $d['statut']),
                    'lien' => '?p=modifier&id=' . rawurlencode((string) $d['id']),
                ], $r)];
        }
    }

    if (droit($u, 'comptes')) {
        $r = $prendre('SELECT id, nom, email, role FROM utilisateurs
                       WHERE nom LIKE ? OR email LIKE ?
                       ORDER BY cree_le DESC LIMIT ' . $n, [$m, $m]);
        if ($r) {
            $out[] = ['famille' => 'Comptes', 'tout' => '?p=comptes&q=' . rawurlencode($q),
                'lignes' => array_map(static fn(array $c): array => [
                    'titre' => (string) $c['nom'],
                    'note' => $c['email'] . ' · ' . role_libelle((string) $c['role']),
                    'lien' => '?p=organisateur&id=' . rawurlencode((string) $c['id']),
                ], $r)];
        }
    }

    if (droit($u, 'articles')) {
        /* Un éditeur ne voit QUE les siens : sa recherche ne doit pas lui
           rendre le brouillon d'un autre, qu'il ne pourrait pas ouvrir. */
        $sien = droit($u, 'valider') ? '' : ' AND auteur_id = ?';
        $args = [$m, $m];
        if ($sien !== '') {
            $args[] = (string) ($u['id'] ?? '');
        }
        $r = $prendre("SELECT id, titre, slug, statut FROM articles
                       WHERE (titre LIKE ? OR slug LIKE ?)$sien
                       ORDER BY maj_le DESC LIMIT $n", $args);
        if ($r) {
            $out[] = ['famille' => 'Articles', 'tout' => '?p=blog-admin',
                'lignes' => array_map(static fn(array $a): array => [
                    'titre' => (string) $a['titre'],
                    'note' => '/' . $a['slug'] . ' · ' . statut_libelle((string) $a['statut']),
                    'lien' => '?p=blog-editer&id=' . rawurlencode((string) $a['id']),
                ], $r)];
        }
    }

    if (droit($u, 'liens')) {
        /**
         * SES liens, et seulement les siens.
         *
         * `?p=liens` ne montre que ceux de son auteur — y compris à
         * l'équipe. Une recherche qui rendrait ceux des autres serait deux
         * fautes en une : une fuite (la cible d'un lien dit ce qu'on
         * prépare), et un résultat dont le bouton « Ouvrir » mène à une
         * liste qui ne le contient pas.
         */
        $r = $prendre("SELECT code, cible, titre FROM liens
                       WHERE auteur_id = ? AND (code LIKE ? OR cible LIKE ? OR titre LIKE ?)
                       ORDER BY cree_le DESC LIMIT $n",
                      [(string) ($u['id'] ?? ''), $m, $m, $m]);
        if ($r) {
            $out[] = ['famille' => 'Liens courts', 'tout' => '?p=liens',
                'lignes' => array_map(static fn(array $l): array => [
                    'titre' => (string) ($l['titre'] ?: $l['code']),
                    'note' => '/l/' . $l['code'],
                    'lien' => '?p=liens',
                ], $r)];
        }
    }

    return $out;
}
