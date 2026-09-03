<?php
/**
 * Le carnet d'adresses : les gens à qui l'on écrit, gardés entre deux campagnes.
 *
 * Jusqu'ici, une liste apportée vivait dans un champ texte de la campagne
 * — et mourait avec elle. La campagne suivante recommençait le
 * copier-coller depuis le même tableur, réintroduisait les mêmes fautes de
 * frappe, et réécrivait à l'adresse qui avait rebondi la fois d'avant.
 * C'est pourtant le seul actif que fabrique une soirée réussie : la liste
 * des gens qui sont venus.
 *
 * Trois idées, et tout le fichier en découle :
 *
 *  1. **Le carnet est plat, les listes sont des étiquettes.** Une adresse
 *     existe UNE fois par propriétaire, et porte zéro, une ou dix
 *     étiquettes. L'inverse — une copie de la fiche par liste — obligerait
 *     à corriger une faute de frappe autant de fois qu'elle apparaît, donc
 *     à ne la corriger nulle part.
 *
 *  2. **Trois gestes, trois effets, et on ne les confond pas.** *Retirer
 *     de la liste* enlève l'étiquette et garde la fiche. *Archiver* garde
 *     la fiche et ne lui écrit plus jamais. *Supprimer* efface. Une seule
 *     commande pour les trois ferait perdre, un soir de ménage, un
 *     historique que rien ne reconstitue.
 *
 *  3. **Archiver n'est pas désabonner.** Archiver est NOTRE décision :
 *     la personne a changé de poste, l'adresse rebondit, le client est
 *     parti. Le désabonnement est LA SIENNE, il est global, et il ne se
 *     défait pas d'ici. Les mélanger permettrait de « désarchiver »
 *     quelqu'un qui a demandé qu'on lui fiche la paix — exactement le
 *     geste qui fait signaler un expéditeur comme indésirable.
 *
 * Le carnet appartient à un compte, pas à l'installation : un organisateur
 * y voit ses invités et rien d'autre. La base du guide reste une cible
 * calculée, jamais une liste qu'on recopie — la recopier serait en faire
 * un fichier qui circule.
 */

declare(strict_types=1);

/** Combien de contacts par page. Au-delà, une table devient un mur. */
const CARNET_PAR_PAGE = 60;

/**
 * D'où vient une fiche. Sert à savoir ce qu'on peut lui reprocher :
 * une adresse importée en masse mérite plus de méfiance qu'une adresse
 * saisie à la main, et une adresse venue de la base a déjà un compte.
 */
const CARNET_SOURCES = [
    'manuel' => 'Saisie à la main',
    'import' => 'Importée',
    'base'   => 'Depuis la base',
];

/* ------------------------------------------------------------------ */
/* Lire un collage                                                     */
/* ------------------------------------------------------------------ */

/**
 * Les adresses contenues dans un collage, quel qu'il soit.
 *
 * Un tableur exporte des points-virgules, un client de messagerie des
 * virgules, un copier-coller depuis un PDF des retours à la ligne : on
 * accepte les trois. `Nom <adresse>` et `adresse (Nom)` sont reconnus,
 * parce que ce sont les deux formes que produisent les outils réels.
 *
 * Une ligne illisible est SAUTÉE, jamais refusée. Rejeter tout le collage
 * à cause d'une virgule oubliée ferait recommencer une saisie de deux
 * cents lignes — et la deuxième tentative contiendrait d'autres fautes.
 *
 * @return array<string, string> adresse en minuscules => nom (souvent vide)
 */
function adresses_du_texte(string $texte): array
{
    $out = [];
    foreach (preg_split('/[\r\n,;]+/', $texte) ?: [] as $ligne) {
        $ligne = trim($ligne);
        if ($ligne === '') {
            continue;
        }
        $nom = '';
        if (preg_match('/^(.*?)<([^>]+)>\s*$/', $ligne, $m)) {
            $nom = trim($m[1], " \t\"'");
            $ligne = trim($m[2]);
        } elseif (preg_match('/^(\S+@\S+?)\s*[\(\[]([^\)\]]+)[\)\]]\s*$/', $ligne, $m)) {
            $ligne = trim($m[1]);
            $nom = trim($m[2]);
        }
        // Un tableur colle parfois une tabulation entre le nom et l'adresse.
        if (!filter_var($ligne, FILTER_VALIDATE_EMAIL) && preg_match('/(\S+@\S+)$/', $ligne, $m)) {
            $nom = $nom ?: trim(substr($ligne, 0, -strlen($m[1])), " \t\"';:");
            $ligne = $m[1];
        }
        if (filter_var($ligne, FILTER_VALIDATE_EMAIL)) {
            $cle = mb_strtolower($ligne);
            // Le premier nom rencontré gagne : un collage qui répète une
            // adresse avec un nom vide ne doit pas effacer le nom donné plus haut.
            if (!isset($out[$cle]) || ($out[$cle] === '' && $nom !== '')) {
                $out[$cle] = $nom;
            }
        }
    }
    return $out;
}

/* ------------------------------------------------------------------ */
/* Les listes                                                          */
/* ------------------------------------------------------------------ */

/**
 * Les listes d'un compte, avec le compte des adresses actives.
 *
 * Le décompte est fait ici, en une requête, et non par un appel par
 * ligne : trente listes feraient trente allers-retours pour afficher un
 * écran qu'on ouvre à chaque campagne.
 */
function carnet_listes(string $proprietaire_id): array
{
    $s = db()->prepare(
        'SELECT l.*,
                (SELECT COUNT(*) FROM contacts_listes cl JOIN contacts c ON c.id = cl.contact_id
                 WHERE cl.liste_id = l.id AND c.archive = 0) AS actifs,
                (SELECT COUNT(*) FROM contacts_listes cl JOIN contacts c ON c.id = cl.contact_id
                 WHERE cl.liste_id = l.id AND c.archive = 1) AS archives
         FROM listes_contacts l WHERE l.proprietaire_id = ? ORDER BY l.nom ASC'
    );
    $s->execute([$proprietaire_id]);
    return $s->fetchAll();
}

function carnet_liste(string $id): ?array
{
    $s = db()->prepare('SELECT * FROM listes_contacts WHERE id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

/** La liste, à condition qu'elle soit bien à ce compte. */
function carnet_liste_de(string $id, string $proprietaire_id): ?array
{
    $l = carnet_liste($id);
    return $l && $l['proprietaire_id'] === $proprietaire_id ? $l : null;
}

/**
 * Crée une liste, ou rend celle qui porte déjà ce nom.
 *
 * Deux listes « Invités » chez le même compte ne se distinguent que par
 * leur identifiant, c'est-à-dire par rien du tout à l'écran. Ré-importer
 * dans « Invités » doit donc alimenter la liste existante, pas en fonder
 * une deuxième que personne ne saura plus départager.
 */
function carnet_liste_poser(string $proprietaire_id, string $nom, string $note = ''): string
{
    $nom = trim($nom);
    if ($nom === '') {
        throw new RuntimeException('Une liste sans nom ne se retrouve pas. Donnez-lui-en un.');
    }
    $s = db()->prepare('SELECT id FROM listes_contacts WHERE proprietaire_id = ? AND nom = ?');
    $s->execute([$proprietaire_id, $nom]);
    $deja = $s->fetchColumn();
    if ($deja) {
        return (string) $deja;
    }
    $id = nouvel_id();
    $now = maintenant();
    db()->prepare('INSERT INTO listes_contacts (id, proprietaire_id, nom, note, cree_le, maj_le)
                   VALUES (?,?,?,?,?,?)')
        ->execute([$id, $proprietaire_id, $nom, trim($note) ?: null, $now, $now]);
    return $id;
}

function carnet_liste_renommer(string $id, string $nom, string $note = ''): void
{
    $nom = trim($nom);
    if ($nom === '') {
        throw new RuntimeException('Une liste sans nom ne se retrouve pas. Donnez-lui-en un.');
    }
    db()->prepare('UPDATE listes_contacts SET nom = ?, note = ?, maj_le = ? WHERE id = ?')
        ->execute([$nom, trim($note) ?: null, maintenant(), $id]);
}

/**
 * Supprime une liste — l'étiquette, pas les gens.
 *
 * Les fiches restent au carnet : quelqu'un qui range ses étiquettes ne
 * demande pas d'effacer deux cents adresses qu'il a mis un an à réunir.
 * Pour les effacer vraiment, il y a « supprimer du carnet », fiche par
 * fiche ou d'un geste sur la sélection.
 */
function carnet_liste_supprimer(string $id): void
{
    db()->prepare('DELETE FROM contacts_listes WHERE liste_id = ?')->execute([$id]);
    db()->prepare('UPDATE campagnes_email SET liste_id = NULL WHERE liste_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM listes_contacts WHERE id = ?')->execute([$id]);
}

/* ------------------------------------------------------------------ */
/* Les contacts                                                        */
/* ------------------------------------------------------------------ */

function carnet_contact(string $id): ?array
{
    $s = db()->prepare('SELECT * FROM contacts WHERE id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

function carnet_contact_de(string $id, string $proprietaire_id): ?array
{
    $c = carnet_contact($id);
    return $c && $c['proprietaire_id'] === $proprietaire_id ? $c : null;
}

function carnet_par_email(string $proprietaire_id, string $email): ?array
{
    $s = db()->prepare('SELECT * FROM contacts WHERE proprietaire_id = ? AND email = ?');
    $s->execute([$proprietaire_id, mb_strtolower(trim($email))]);
    return $s->fetch() ?: null;
}

/**
 * Le filtre, écrit une fois et partagé par la liste et le décompte.
 *
 * Les deux DOIVENT poser exactement la même question : une pagination qui
 * compte autrement qu'elle n'affiche produit une dernière page vide, et
 * l'on cherche longtemps pourquoi.
 *
 * @return array{0: string, 1: array<int, mixed>}
 */
function carnet_filtre(string $proprietaire_id, array $f): array
{
    $sql = ' FROM contacts c WHERE c.proprietaire_id = ?';
    $args = [$proprietaire_id];

    if (($f['liste'] ?? '') !== '') {
        $sql .= ' AND EXISTS (SELECT 1 FROM contacts_listes cl WHERE cl.contact_id = c.id AND cl.liste_id = ?)';
        $args[] = $f['liste'];
    }
    if (($f['etat'] ?? '') === 'archives') {
        $sql .= ' AND c.archive = 1';
    } elseif (($f['etat'] ?? '') !== 'toutes') {
        $sql .= ' AND c.archive = 0';
    }
    if (($f['sans_liste'] ?? false)) {
        $sql .= ' AND NOT EXISTS (SELECT 1 FROM contacts_listes cl2 WHERE cl2.contact_id = c.id)';
    }
    if (trim((string) ($f['q'] ?? '')) !== '') {
        $sql .= ' AND (c.email LIKE ? OR c.nom LIKE ? OR c.organisation LIKE ?)';
        $motif = '%' . trim((string) $f['q']) . '%';
        array_push($args, $motif, $motif, $motif);
    }
    return [$sql, $args];
}

function carnet_combien(string $proprietaire_id, array $f = []): int
{
    [$sql, $args] = carnet_filtre($proprietaire_id, $f);
    $s = db()->prepare('SELECT COUNT(*)' . $sql);
    $s->execute($args);
    return (int) $s->fetchColumn();
}

function carnet_contacts(string $proprietaire_id, array $f = [], int $page = 1): array
{
    [$sql, $args] = carnet_filtre($proprietaire_id, $f);
    $decalage = max(0, ($page - 1) * CARNET_PAR_PAGE);
    $s = db()->prepare('SELECT c.*' . $sql
        . ' ORDER BY c.archive ASC, c.cree_le DESC LIMIT ' . CARNET_PAR_PAGE . ' OFFSET ' . $decalage);
    $s->execute($args);
    return $s->fetchAll();
}

/** Les étiquettes portées par une fiche. */
function carnet_listes_du_contact(string $contact_id): array
{
    $s = db()->prepare('SELECT l.* FROM listes_contacts l
                        JOIN contacts_listes cl ON cl.liste_id = l.id
                        WHERE cl.contact_id = ? ORDER BY l.nom');
    $s->execute([$contact_id]);
    return $s->fetchAll();
}

/**
 * Les étiquettes de plusieurs fiches d'un coup.
 *
 * Une requête pour toute la page, et non une par ligne : soixante fiches
 * feraient soixante allers-retours pour afficher une colonne.
 *
 * @return array<string, array<int, string>> contact_id => noms de listes
 */
function carnet_etiquettes(array $contacts): array
{
    $ids = array_map(fn(array $c) => (string) $c['id'], $contacts);
    if (!$ids) {
        return [];
    }
    $trous = implode(',', array_fill(0, count($ids), '?'));
    $s = db()->prepare("SELECT cl.contact_id, l.nom FROM contacts_listes cl
                        JOIN listes_contacts l ON l.id = cl.liste_id
                        WHERE cl.contact_id IN ($trous) ORDER BY l.nom");
    $s->execute($ids);
    $out = [];
    foreach ($s->fetchAll() as $r) {
        $out[(string) $r['contact_id']][] = (string) $r['nom'];
    }
    return $out;
}

/**
 * Pose une fiche : la crée, ou complète celle qui existe déjà.
 *
 * Ré-importer la même liste le mois suivant est le cas NORMAL, pas
 * l'exception : on ajoute les nouveaux et on laisse les autres tranquilles.
 * Un nom déjà connu n'est jamais écrasé par un vide — un tableur exporté
 * sans la colonne « nom » effacerait sinon tous les noms du carnet.
 *
 * @return array{id: string, neuf: bool}
 */
function carnet_contact_poser(string $proprietaire_id, array $d, string $source = 'manuel'): array
{
    $email = mb_strtolower(trim((string) ($d['email'] ?? '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('« ' . ($d['email'] ?? '') . ' » n’est pas une adresse valide.');
    }
    $now = maintenant();
    $deja = carnet_par_email($proprietaire_id, $email);

    if ($deja) {
        $sets = [];
        $vals = [];
        foreach (['nom', 'organisation', 'telephone', 'note'] as $k) {
            $v = trim((string) ($d[$k] ?? ''));
            if ($v !== '' && $v !== (string) ($deja[$k] ?? '')) {
                $sets[] = "$k = ?";
                $vals[] = $v;
            }
        }
        if ($sets) {
            $vals[] = $now;
            $vals[] = $deja['id'];
            db()->prepare('UPDATE contacts SET ' . implode(', ', $sets) . ', maj_le = ? WHERE id = ?')
                ->execute($vals);
        }
        return ['id' => (string) $deja['id'], 'neuf' => false];
    }

    $id = nouvel_id();
    db()->prepare('INSERT INTO contacts
        (id, proprietaire_id, email, nom, organisation, telephone, note, source, archive, cree_le, maj_le)
        VALUES (?,?,?,?,?,?,?,?,0,?,?)')
        ->execute([
            $id, $proprietaire_id, $email,
            trim((string) ($d['nom'] ?? '')) ?: null,
            trim((string) ($d['organisation'] ?? '')) ?: null,
            trim((string) ($d['telephone'] ?? '')) ?: null,
            trim((string) ($d['note'] ?? '')) ?: null,
            isset(CARNET_SOURCES[$source]) ? $source : 'manuel',
            $now, $now,
        ]);
    return ['id' => $id, 'neuf' => true];
}

/**
 * Modifie une fiche, changement d'adresse compris.
 *
 * L'adresse est la clé du carnet : la changer pour une autre déjà présente
 * ferait deux fiches pour la même personne, et l'on écrirait deux fois. On
 * refuse, en disant laquelle gêne — c'est réparable en dix secondes,
 * contrairement à un doublon qu'on ne verra jamais.
 */
function carnet_contact_maj(string $id, array $d): void
{
    $c = carnet_contact($id);
    if (!$c) {
        throw new RuntimeException('Fiche introuvable.');
    }
    $email = mb_strtolower(trim((string) ($d['email'] ?? $c['email'])));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new RuntimeException('Cette adresse e-mail n’est pas valide.');
    }
    if ($email !== $c['email']) {
        $autre = carnet_par_email((string) $c['proprietaire_id'], $email);
        if ($autre) {
            throw new RuntimeException(
                'Une autre fiche porte déjà l’adresse ' . $email . '. Fusionnez-les à la main, '
                . 'ou supprimez celle qui est en trop.'
            );
        }
    }
    db()->prepare('UPDATE contacts SET email = ?, nom = ?, organisation = ?, telephone = ?,
                   note = ?, maj_le = ? WHERE id = ?')
        ->execute([
            $email,
            trim((string) ($d['nom'] ?? '')) ?: null,
            trim((string) ($d['organisation'] ?? '')) ?: null,
            trim((string) ($d['telephone'] ?? '')) ?: null,
            trim((string) ($d['note'] ?? '')) ?: null,
            maintenant(), $id,
        ]);
}

function carnet_contact_archiver(string $id, bool $oui): void
{
    db()->prepare('UPDATE contacts SET archive = ?, maj_le = ? WHERE id = ?')
        ->execute([$oui ? 1 : 0, maintenant(), $id]);
}

function carnet_contact_supprimer(string $id): void
{
    db()->prepare('DELETE FROM contacts_listes WHERE contact_id = ?')->execute([$id]);
    db()->prepare('DELETE FROM contacts WHERE id = ?')->execute([$id]);
}

/** Colle une étiquette. Recoller la même ne fait rien, et c'est voulu. */
function carnet_attacher(string $contact_id, string $liste_id): void
{
    try {
        db()->prepare('INSERT INTO contacts_listes (liste_id, contact_id, ajoute_le) VALUES (?,?,?)')
            ->execute([$liste_id, $contact_id, maintenant()]);
    } catch (PDOException) {
        // Déjà dans la liste : c'est le résultat voulu, pas une erreur.
    }
}

function carnet_detacher(string $contact_id, string $liste_id): void
{
    db()->prepare('DELETE FROM contacts_listes WHERE contact_id = ? AND liste_id = ?')
        ->execute([$contact_id, $liste_id]);
}

/* ------------------------------------------------------------------ */
/* Alimenter une liste                                                 */
/* ------------------------------------------------------------------ */

/**
 * Importe un collage dans une liste.
 *
 * C'est le geste que le produit fait le plus souvent, et le seul que
 * l'utilisateur voit comme « ma liste est sauvegardée ». Il rend le détail
 * de ce qui s'est passé — combien de nouvelles, combien de connues,
 * combien d'illisibles — parce que « 180 adresses importées » quand on en
 * a collé 200 pose une question à laquelle il faut pouvoir répondre.
 *
 * @return array{neuves: int, connues: int, illisibles: int, total: int}
 */
function carnet_importer(string $proprietaire_id, string $liste_id, string $texte,
                         string $source = 'import'): array
{
    $lues = adresses_du_texte($texte);
    $collees = count(array_filter(preg_split('/[\r\n,;]+/', $texte) ?: [], fn($l) => trim($l) !== ''));

    $neuves = $connues = 0;
    foreach ($lues as $email => $nom) {
        $r = carnet_contact_poser($proprietaire_id, ['email' => $email, 'nom' => $nom], $source);
        $r['neuf'] ? $neuves++ : $connues++;
        if ($liste_id !== '') {
            carnet_attacher($r['id'], $liste_id);
        }
    }
    if ($liste_id !== '') {
        db()->prepare('UPDATE listes_contacts SET maj_le = ? WHERE id = ?')
            ->execute([maintenant(), $liste_id]);
    }
    return [
        'neuves' => $neuves,
        'connues' => $connues,
        'illisibles' => max(0, $collees - count($lues)),
        'total' => count($lues),
    ];
}

/**
 * Recopie une cible calculée dans une liste, une fois.
 *
 * « Mes invités » est un segment vivant : il change à chaque badge créé.
 * Le figer dans une liste a un intérêt précis — pouvoir ensuite en RETIRER
 * quelqu'un, corriger un nom, archiver une adresse morte. Un segment
 * calculé ne se corrige pas ; une liste, si.
 *
 * @return array{neuves: int, connues: int, illisibles: int, total: int}
 */
function carnet_alimenter(array $proprietaire, string $liste_id, string $cible): array
{
    if (!isset(regie_cibles_de($proprietaire)[$cible]) || $cible === 'liste') {
        throw new RuntimeException('Cette source n’est pas à votre portée.');
    }
    $gens = regie_destinataires(['cible' => $cible, 'liste' => '', 'liste_id' => null], $proprietaire);

    $neuves = $connues = 0;
    foreach ($gens as $email => $nom) {
        $r = carnet_contact_poser((string) $proprietaire['id'], ['email' => $email, 'nom' => $nom], 'base');
        $r['neuf'] ? $neuves++ : $connues++;
        carnet_attacher($r['id'], $liste_id);
    }
    db()->prepare('UPDATE listes_contacts SET maj_le = ? WHERE id = ?')
        ->execute([maintenant(), $liste_id]);
    return ['neuves' => $neuves, 'connues' => $connues, 'illisibles' => 0, 'total' => count($gens)];
}

/* ------------------------------------------------------------------ */
/* Ce que la régie en tire                                             */
/* ------------------------------------------------------------------ */

/**
 * Les destinataires d'une liste : les fiches non archivées, et elles seules.
 *
 * Les désabonnés ne sont pas écartés ici mais chez l'appelant, avec ceux
 * de toutes les autres cibles : un seul endroit qui décide qui ne reçoit
 * rien vaut mieux que deux qui pourraient diverger.
 *
 * @return array<string, string> adresse => nom
 */
function carnet_destinataires(string $liste_id): array
{
    $s = db()->prepare('SELECT c.email, c.nom FROM contacts c
                        JOIN contacts_listes cl ON cl.contact_id = c.id
                        WHERE cl.liste_id = ? AND c.archive = 0');
    $s->execute([$liste_id]);
    $out = [];
    foreach ($s->fetchAll() as $r) {
        $out[mb_strtolower((string) $r['email'])] = (string) ($r['nom'] ?? '');
    }
    return $out;
}

/** Les campagnes qui pointent sur cette liste — de quoi prévenir avant d'effacer. */
function carnet_campagnes_de_la_liste(string $liste_id): int
{
    $s = db()->prepare('SELECT COUNT(*) FROM campagnes_email WHERE liste_id = ?');
    $s->execute([$liste_id]);
    return (int) $s->fetchColumn();
}
