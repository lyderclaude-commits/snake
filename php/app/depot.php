<?php
/**
 * Accès aux données — la SEULE frontière avec le SQL.
 *
 * Aucune vue, aucun contrôleur n'écrit de requête. Changer de moteur ou de
 * schéma revient à réécrire ce fichier.
 */

declare(strict_types=1);

/* ================= décors ================= */

const STATS_SQL = "
  (SELECT COUNT(*) FROM evenements e WHERE e.decor_id = d.id AND e.genre='telechargement') AS telechargements,
  (SELECT COUNT(*) FROM evenements e WHERE e.decor_id = d.id AND e.genre='vue')            AS vues";

function decors_publies(int $limite = 60): array
{
    $s = db()->prepare("SELECT d.*, " . STATS_SQL . "
        FROM decors d
        WHERE d.statut = 'publie' AND (d.expire_le IS NULL OR d.expire_le > ?)
        ORDER BY d.publie_le DESC LIMIT $limite");
    $s->execute([maintenant()]);
    return $s->fetchAll();
}

function decor_par_slug(string $slug): ?array
{
    $s = db()->prepare("SELECT d.*, " . STATS_SQL . " FROM decors d WHERE d.slug = ?");
    $s->execute([$slug]);
    return $s->fetch() ?: null;
}

function decor_par_id(string $id): ?array
{
    $s = db()->prepare("SELECT d.*, " . STATS_SQL . " FROM decors d WHERE d.id = ?");
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

function decors_de(string $auteur_id): array
{
    $s = db()->prepare("SELECT d.*, " . STATS_SQL . " FROM decors d WHERE d.auteur_id = ? ORDER BY d.maj_le DESC");
    $s->execute([$auteur_id]);
    return $s->fetchAll();
}

function decors_en_attente(): array
{
    $s = db()->query("SELECT d.*, u.nom AS auteur_nom, " . STATS_SQL . "
        FROM decors d LEFT JOIN utilisateurs u ON u.id = d.auteur_id
        WHERE d.statut = 'en_relecture' ORDER BY d.soumis_le ASC");
    return $s->fetchAll();
}

function slug_existe(string $slug): bool
{
    $s = db()->prepare('SELECT 1 FROM decors WHERE slug = ?');
    $s->execute([$slug]);
    return (bool) $s->fetch();
}

function slug_libre(string $titre): string
{
    $base = slugifier($titre);
    $slug = $base;
    $n = 2;
    while (slug_existe($slug)) {
        $slug = $base . '-' . $n++;
    }
    return $slug;
}

function decor_creer(array $d): string
{
    $id = nouvel_id();
    $now = maintenant();
    // Le statut vit à deux endroits : la colonne et le gabarit. Ils partent
    // d'accord, et transition() les fait évoluer ensemble.
    $g = $d['gabarit'];
    $g['status'] = 'brouillon';
    $g['moderation'] = new stdClass();
    unset($g['publishedAt']);

    db()->prepare('INSERT INTO decors
        (id, slug, titre, sous_titre, ville, rubrique, statut, cree_par, auteur_id,
         gabarit, cadre_url, expire_le, cree_le, maj_le)
        VALUES (?,?,?,?,?,?,\'brouillon\',?,?,?,?,?,?,?)')
      ->execute([
          $id, $d['slug'], $d['titre'], $d['sous_titre'] ?: null, $d['ville'], $d['rubrique'],
          $d['cree_par'], $d['auteur_id'],
          json_encode($g, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
          $d['cadre_url'], $d['expire_le'] ?: null, $now, $now,
      ]);
    return $id;
}

class TransitionRefusee extends RuntimeException
{
}

/**
 * Change le statut d'un décor en faisant respecter la machine à états.
 *
 * Le gabarit est réécrit EN MÊME TEMPS que la colonne, et revalidé. Les
 * laisser diverger produirait un décor publié en base mais invalide au
 * rendu — donc une page introuvable depuis le catalogue.
 */
function decor_transition(string $id, string $vers, array $acteur, ?string $motif = null): void
{
    $d = decor_par_id($id);
    if (!$d) {
        throw new TransitionRefusee('Décor introuvable.');
    }
    if (!transition_permise($d['statut'], $vers, $acteur['role'] === 'equipe' ? 'equipe' : 'partenaire')) {
        throw new TransitionRefusee(sprintf('Transition « %s → %s » non autorisée pour ce rôle.', $d['statut'], $vers));
    }
    if (in_array($vers, ['refuse', 'corrections'], true) && !trim((string) $motif)) {
        throw new TransitionRefusee('Un motif est obligatoire pour refuser ou demander des corrections.');
    }

    $now = maintenant();
    $g = json_lire($d['gabarit']);
    $mod = (array) ($g['moderation'] ?? []);

    $sets = ['statut = ?', 'maj_le = ?'];
    $vals = [$vers, $now];

    if ($vers === 'en_relecture') {
        $sets[] = 'soumis_le = ?';
        $vals[] = $now;
        $mod['soumisLe'] = $now;
        $mod['soumisPar'] = $acteur['id'];
    }
    if ($vers === 'publie') {
        $sets[] = 'publie_le = ?';
        $vals[] = $d['publie_le'] ?: $now;
        if ($d['cree_par'] === 'partenaire') {
            $sets[] = 'relu_le = ?';
            $sets[] = 'relu_par = ?';
            $vals[] = $now;
            $vals[] = $acteur['id'];
            $mod['reluLe'] = $now;
            $mod['reluPar'] = $acteur['id'];
        }
        $g['publishedAt'] = $d['publie_le'] ?: $now;
    }
    if (in_array($vers, ['refuse', 'corrections'], true)) {
        $sets[] = 'relu_le = ?';
        $sets[] = 'relu_par = ?';
        $sets[] = 'motif = ?';
        $vals[] = $now;
        $vals[] = $acteur['id'];
        $vals[] = trim((string) $motif);
        $mod['reluLe'] = $now;
        $mod['reluPar'] = $acteur['id'];
        $mod['motif'] = trim((string) $motif);
    }

    $g['status'] = $vers;
    $g['moderation'] = $mod ?: new stdClass();

    // Revalider ICI est le seul moment où l'on peut encore refuser.
    valider_gabarit($g);

    $sets[] = 'gabarit = ?';
    $vals[] = json_encode($g, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $vals[] = $id;

    db()->prepare('UPDATE decors SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);
}

function evenement(string $decor_id, string $genre): void
{
    db()->prepare('INSERT INTO evenements (decor_id, genre, cree_le) VALUES (?,?,?)')
        ->execute([$decor_id, $genre, maintenant()]);
}

/* ================= créations ================= */

function creation_noter(string $utilisateur_id, string $decor_id): void
{
    db()->prepare('INSERT INTO creations (id, utilisateur_id, decor_id, cree_le) VALUES (?,?,?,?)')
        ->execute([nouvel_id(), $utilisateur_id, $decor_id, maintenant()]);
}

function creations_de(string $utilisateur_id, int $limite = 30): array
{
    $s = db()->prepare("SELECT c.*, d.titre, d.slug FROM creations c
        JOIN decors d ON d.id = c.decor_id
        WHERE c.utilisateur_id = ? ORDER BY c.cree_le DESC LIMIT $limite");
    $s->execute([$utilisateur_id]);
    return $s->fetchAll();
}

/* ================= badges, présence, koris ================= */

/** Jeton court, lisible, sans caractères ambigus — il finit dans un QR. */
function nouveau_jeton(): string
{
    $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $j = '';
    for ($i = 0; $i < 10; $i++) {
        $j .= $alphabet[random_int(0, 31)];
    }
    return $j;
}

function badge_emettre(string $decor_id, ?string $utilisateur_id): string
{
    do {
        $jeton = nouveau_jeton();
        $s = db()->prepare('SELECT 1 FROM badges WHERE jeton = ?');
        $s->execute([$jeton]);
    } while ($s->fetch());

    db()->prepare('INSERT INTO badges (jeton, decor_id, utilisateur_id, cree_le) VALUES (?,?,?,?)')
        ->execute([$jeton, $decor_id, $utilisateur_id, maintenant()]);
    return $jeton;
}

function badge_lire(string $jeton): ?array
{
    $s = db()->prepare('SELECT b.*, d.titre AS decor_titre, d.slug AS decor_slug, u.nom AS porteur
        FROM badges b JOIN decors d ON d.id = b.decor_id
        LEFT JOIN utilisateurs u ON u.id = b.utilisateur_id
        WHERE b.jeton = ?');
    $s->execute([strtoupper(trim($jeton))]);
    return $s->fetch() ?: null;
}

/**
 * Valide une entrée. Idempotent par construction : la mise à jour ne prend
 * que si scanne_le est encore nul, donc deux agents qui scannent le même
 * badge en même temps ne peuvent pas créditer deux fois.
 */
function badge_scanner(string $jeton, string $agent_id): array
{
    $b = badge_lire($jeton);
    if (!$b) {
        return ['ok' => false, 'raison' => 'inconnu'];
    }
    if ($b['scanne_le']) {
        return ['ok' => false, 'raison' => 'deja', 'quand' => $b['scanne_le'], 'decor' => $b['decor_titre']];
    }

    $now = maintenant();
    $koris = $b['utilisateur_id'] ? KORIS_PAR_SCAN : 0;

    $s = db()->prepare('UPDATE badges SET scanne_le = ?, scanne_par = ?, koris = ?
                        WHERE jeton = ? AND scanne_le IS NULL');
    $s->execute([$now, $agent_id, $koris, $b['jeton']]);
    if ($s->rowCount() === 0) {
        return ['ok' => false, 'raison' => 'deja', 'decor' => $b['decor_titre']];
    }

    // Les Koris ne vont qu'à un compte identifié : un badge créé sans compte
    // reste comptabilisé en présence, mais ne rapporte rien.
    if ($b['utilisateur_id']) {
        db()->prepare('INSERT INTO koris (utilisateur_id, montant, motif, badge_jeton, cree_le) VALUES (?,?,?,?,?)')
            ->execute([$b['utilisateur_id'], KORIS_PAR_SCAN, 'Présence : ' . $b['decor_titre'], $b['jeton'], $now]);
    }

    return ['ok' => true, 'koris' => $koris, 'decor' => $b['decor_titre'], 'porteur' => $b['porteur']];
}

/** Solde — recalculé, jamais stocké. */
function koris_solde(string $utilisateur_id): int
{
    $s = db()->prepare('SELECT COALESCE(SUM(montant),0) AS n FROM koris WHERE utilisateur_id = ?');
    $s->execute([$utilisateur_id]);
    return (int) $s->fetch()['n'];
}

function koris_historique(string $utilisateur_id, int $limite = 20): array
{
    $s = db()->prepare("SELECT * FROM koris WHERE utilisateur_id = ? ORDER BY cree_le DESC LIMIT $limite");
    $s->execute([$utilisateur_id]);
    return $s->fetchAll();
}

function passages_recents(int $limite = 15): array
{
    $s = db()->query("SELECT b.jeton, b.scanne_le, d.titre AS decor, u.nom AS porteur
        FROM badges b JOIN decors d ON d.id = b.decor_id
        LEFT JOIN utilisateurs u ON u.id = b.utilisateur_id
        WHERE b.scanne_le IS NOT NULL ORDER BY b.scanne_le DESC LIMIT $limite");
    return $s->fetchAll();
}

/** Présence par décor : émis, scannés, taux. */
function presence(string $decor_id): array
{
    $s = db()->prepare('SELECT COUNT(*) AS emis,
        SUM(CASE WHEN scanne_le IS NOT NULL THEN 1 ELSE 0 END) AS scannes
        FROM badges WHERE decor_id = ?');
    $s->execute([$decor_id]);
    $r = $s->fetch();
    $emis = (int) $r['emis'];
    $scannes = (int) ($r['scannes'] ?? 0);
    return ['emis' => $emis, 'scannes' => $scannes, 'taux' => $emis ? $scannes / $emis : 0.0];
}

/* ================= notifications ================= */

function notifier(string $utilisateur_id, string $genre, string $titre, ?string $corps = null, ?string $lien = null): void
{
    db()->prepare('INSERT INTO notifications (id, utilisateur_id, genre, titre, corps, lien, cree_le)
                   VALUES (?,?,?,?,?,?,?)')
        ->execute([nouvel_id(), $utilisateur_id, $genre, $titre, $corps, $lien, maintenant()]);
}

function notifications_de(string $utilisateur_id, int $limite = 20): array
{
    $s = db()->prepare("SELECT * FROM notifications WHERE utilisateur_id = ? ORDER BY cree_le DESC LIMIT $limite");
    $s->execute([$utilisateur_id]);
    return $s->fetchAll();
}

function notifications_non_lues(string $utilisateur_id): int
{
    $s = db()->prepare('SELECT COUNT(*) AS n FROM notifications WHERE utilisateur_id = ? AND lu_le IS NULL');
    $s->execute([$utilisateur_id]);
    return (int) $s->fetch()['n'];
}

function notifications_marquer_lues(string $utilisateur_id): void
{
    db()->prepare('UPDATE notifications SET lu_le = ? WHERE utilisateur_id = ? AND lu_le IS NULL')
        ->execute([maintenant(), $utilisateur_id]);
}

function equipe(): array
{
    return db()->query("SELECT * FROM utilisateurs WHERE role = 'equipe' AND suspendu = 0")->fetchAll();
}

/* ================= pilotage ================= */

/** Un COUNT paramétré. Le seul endroit qui compte, pour n'oublier aucun cas. */
function compter(string $sql, array $args = []): int
{
    $s = db()->prepare($sql);
    $s->execute($args);
    return (int) $s->fetch()['n'];
}

/** Une borne de temps, en arrière depuis maintenant. */
function il_y_a(int $jours): string
{
    return gmdate('Y-m-d\TH:i:s\Z', time() - $jours * 86400);
}

function tableau_de_bord(): array
{
    $n = fn(string $sql) => (int) db()->query($sql)->fetch()['n'];
    return [
        'publies' => $n("SELECT COUNT(*) AS n FROM decors WHERE statut='publie'"),
        'en_attente' => $n("SELECT COUNT(*) AS n FROM decors WHERE statut='en_relecture'"),
        'corrections' => $n("SELECT COUNT(*) AS n FROM decors WHERE statut='corrections'"),
        'brouillons' => $n("SELECT COUNT(*) AS n FROM decors WHERE statut='brouillon'"),
        'badges' => $n('SELECT COUNT(*) AS n FROM badges'),
        'presences' => $n('SELECT COUNT(*) AS n FROM badges WHERE scanne_le IS NOT NULL'),
        'comptes' => $n('SELECT COUNT(*) AS n FROM utilisateurs'),
        'partenaires' => $n("SELECT COUNT(*) AS n FROM utilisateurs WHERE role='partenaire'"),
        'suspendus' => $n('SELECT COUNT(*) AS n FROM utilisateurs WHERE suspendu = 1'),
        'vues' => $n("SELECT COUNT(*) AS n FROM evenements WHERE genre='vue'"),
        'koris' => $n('SELECT COALESCE(SUM(montant),0) AS n FROM koris'),
    ];
}

/**
 * Les indicateurs de la période, et ceux de la période précédente.
 *
 * Un chiffre seul ne dit rien : 40 badges est bon ou mauvais selon ce qu'a
 * fait la semaine d'avant. Chaque indicateur porte donc sa variation, et
 * `null` quand la période précédente était vide — « +∞ % » n'informe personne.
 */
function indicateurs(int $jours = 7): array
{
    $courant = [il_y_a($jours), il_y_a(0)];
    $avant = [il_y_a($jours * 2), il_y_a($jours)];

    $mesures = [
        'badges' => ['Badges créés', 'SELECT COUNT(*) AS n FROM badges WHERE cree_le >= ? AND cree_le < ?'],
        'telechargements' => ['Téléchargements', "SELECT COUNT(*) AS n FROM evenements WHERE genre='telechargement' AND cree_le >= ? AND cree_le < ?"],
        'presences' => ['Présences scannées', 'SELECT COUNT(*) AS n FROM badges WHERE scanne_le >= ? AND scanne_le < ?'],
        'vues' => ['Vues de décors', "SELECT COUNT(*) AS n FROM evenements WHERE genre='vue' AND cree_le >= ? AND cree_le < ?"],
        'comptes' => ['Nouveaux comptes', 'SELECT COUNT(*) AS n FROM utilisateurs WHERE cree_le >= ? AND cree_le < ?'],
    ];

    $out = [];
    foreach ($mesures as $cle => [$titre, $sql]) {
        $ici = compter($sql, $courant);
        $la = compter($sql, $avant);
        $out[$cle] = [
            'titre' => $titre,
            'valeur' => $ici,
            'avant' => $la,
            'variation' => $la > 0 ? (int) round(($ici - $la) / $la * 100) : null,
        ];
    }
    return $out;
}

/**
 * L'entonnoir du produit : on regarde une page, on fabrique, on emporte, on vient.
 *
 * C'est la seule vue qui dit si la boucle tourne. Un décor très vu qui ne
 * produit pas de badges est un décor qui ne parle pas ; des badges qui ne
 * sont jamais scannés, c'est du bruit sans présence.
 */
function entonnoir(int $jours = 30): array
{
    $d = il_y_a($jours);
    $etapes = [
        ['Vues de décors', compter("SELECT COUNT(*) AS n FROM evenements WHERE genre='vue' AND cree_le >= ?", [$d])],
        ['Badges créés', compter('SELECT COUNT(*) AS n FROM badges WHERE cree_le >= ?', [$d])],
        ['Téléchargés', compter("SELECT COUNT(*) AS n FROM evenements WHERE genre='telechargement' AND cree_le >= ?", [$d])],
        ['Présences à l’entrée', compter('SELECT COUNT(*) AS n FROM badges WHERE scanne_le >= ?', [$d])],
    ];
    $tete = max(1, $etapes[0][1]);
    $out = [];
    foreach ($etapes as $i => [$nom, $n]) {
        $precedent = $i === 0 ? $n : $etapes[$i - 1][1];
        $out[] = [
            'nom' => $nom,
            'n' => $n,
            'part' => $n / $tete,
            // Le taux de passage depuis l'étape d'AVANT : c'est là que ça fuit.
            'passage' => $i === 0 ? null : ($precedent > 0 ? $n / $precedent : 0.0),
        ];
    }
    return $out;
}

/** Les décors qui travaillent le plus, présence comprise. */
function decors_en_tete(int $limite = 5): array
{
    return db()->query("SELECT d.id, d.titre, d.slug, d.statut, u.nom AS auteur_nom, " . STATS_SQL . ",
        (SELECT COUNT(*) FROM badges b WHERE b.decor_id = d.id) AS badges,
        (SELECT COUNT(*) FROM badges b WHERE b.decor_id = d.id AND b.scanne_le IS NOT NULL) AS presences
        FROM decors d LEFT JOIN utilisateurs u ON u.id = d.auteur_id
        WHERE d.statut = 'publie'
        ORDER BY telechargements DESC, vues DESC LIMIT $limite")->fetchAll();
}

function comptes_recents(int $limite = 6): array
{
    return db()->query("SELECT * FROM utilisateurs ORDER BY cree_le DESC LIMIT $limite")->fetchAll();
}

/** Combien de comptes par formule, hors équipe : la photo du portefeuille. */
function comptes_par_formule(): array
{
    $out = [];
    foreach (db()->query("SELECT formule, COUNT(*) AS n FROM utilisateurs
                          WHERE role <> 'equipe' GROUP BY formule")->fetchAll() as $r) {
        $out[(string) $r['formule']] = (int) $r['n'];
    }
    return $out;
}

function comptes_par_role(): array
{
    $out = [];
    foreach (db()->query('SELECT role, COUNT(*) AS n FROM utilisateurs GROUP BY role')->fetchAll() as $r) {
        $out[(string) $r['role']] = (int) $r['n'];
    }
    return $out;
}

/**
 * Les campagnes qui comptent dans le quota d'une offre.
 *
 * Un brouillon ne consomme rien : il n'est visible de personne. Un décor
 * archivé, refusé ou expiré non plus. Ce qui occupe une place, c'est ce qui
 * est en ligne ou en train d'y aller.
 */
function campagnes_actives(string $auteur_id, ?string $sauf = null): int
{
    // `$sauf` : le décor qu'on est en train de soumettre occupe déjà sa
    // place s'il revient de corrections. Sans cette exception, un quota de
    // 1 empêcherait de renvoyer le seul décor qu'on a.
    $sql = "SELECT COUNT(*) AS n FROM decors
        WHERE auteur_id = ? AND statut IN ('publie','en_relecture','corrections')";
    $args = [$auteur_id];
    if ($sauf !== null) {
        $sql .= ' AND id <> ?';
        $args[] = $sauf;
    }
    return compter($sql, $args);
}

/** Téléchargements du mois en cours, tous décors confondus, pour un compte. */
function telechargements_du_mois(string $auteur_id): int
{
    return compter("SELECT COUNT(*) AS n FROM evenements e JOIN decors d ON d.id = e.decor_id
        WHERE d.auteur_id = ? AND e.genre = 'telechargement' AND e.cree_le >= ?",
        [$auteur_id, gmdate('Y-m-01\T00:00:00\Z')]);
}

/** Série des 14 derniers jours, trous comblés — sinon l'histogramme ment. */
function telechargements_par_jour(int $jours = 14): array
{
    $s = db()->query("SELECT substr(cree_le,1,10) AS j, COUNT(*) AS n
        FROM evenements WHERE genre='telechargement' GROUP BY substr(cree_le,1,10)");
    $par_jour = [];
    foreach ($s->fetchAll() as $r) {
        $par_jour[$r['j']] = (int) $r['n'];
    }
    $out = [];
    for ($i = $jours - 1; $i >= 0; $i--) {
        $j = gmdate('Y-m-d', time() - $i * 86400);
        $out[] = ['jour' => $j, 'n' => $par_jour[$j] ?? 0];
    }
    return $out;
}

function comptes_tous(): array
{
    // Le nombre de décors accompagne chaque compte : sans lui, la liste ne
    // dit pas lesquels travaillent et lesquels dorment.
    return db()->query("SELECT u.*,
        (SELECT COUNT(*) FROM decors d WHERE d.auteur_id = u.id) AS decors,
        (SELECT COUNT(*) FROM decors d WHERE d.auteur_id = u.id
            AND d.statut IN ('publie','en_relecture','corrections')) AS actives
        FROM utilisateurs u ORDER BY u.cree_le DESC LIMIT 200")->fetchAll();
}

/**
 * Catalogue complet pour l'équipe, filtrable par statut.
 *
 * Distinct de `decors_en_tete()`, qui alimente le tableau de bord : ici on
 * filtre et on cherche, parce que la liste est faite pour agir dessus.
 */
function decors_catalogue(?string $statut = null, string $cherche = ''): array
{
    $ou = [];
    $args = [];
    if ($statut && in_array($statut, STATUTS, true)) {
        $ou[] = 'd.statut = ?';
        $args[] = $statut;
    }
    if (trim($cherche) !== '') {
        $ou[] = '(d.titre LIKE ? OR d.slug LIKE ?)';
        $args[] = '%' . trim($cherche) . '%';
        $args[] = '%' . trim($cherche) . '%';
    }
    $where = $ou ? 'WHERE ' . implode(' AND ', $ou) : '';

    $s = db()->prepare("SELECT d.*, u.nom AS auteur_nom, " . STATS_SQL . "
        FROM decors d LEFT JOIN utilisateurs u ON u.id = d.auteur_id
        $where ORDER BY d.maj_le DESC LIMIT 200");
    $s->execute($args);
    return $s->fetchAll();
}

/** Combien de décors par statut — pour les onglets de filtre. */
function decors_par_statut(): array
{
    $out = [];
    foreach (db()->query('SELECT statut, COUNT(*) AS n FROM decors GROUP BY statut')->fetchAll() as $r) {
        $out[$r['statut']] = (int) $r['n'];
    }
    return $out;
}

/**
 * Remplace le gabarit d'un décor existant.
 *
 * Le statut et la trace de relecture sont CONSERVÉS : modifier un décor
 * publié ne doit pas le dépublier, ni effacer qui l'a relu. Le gabarit est
 * revalidé — une modification qui violerait le contrat échoue ici, pas
 * plus tard en page introuvable.
 */
function decor_modifier(string $id, array $d): void
{
    $ancien = decor_par_id($id);
    if (!$ancien) {
        throw new TransitionRefusee('Décor introuvable.');
    }

    $g = $d['gabarit'];
    $courant = json_lire($ancien['gabarit']);
    $g['status'] = $ancien['statut'];
    $g['moderation'] = $courant['moderation'] ?? new stdClass();
    if ($ancien['publie_le']) {
        $g['publishedAt'] = $ancien['publie_le'];
    }
    // Le créateur ne change jamais : c'est lui qui décide si le garde-fou
    // de redirection s'applique.
    $g['createdBy'] = $ancien['cree_par'];

    valider_gabarit($g);

    db()->prepare('UPDATE decors SET titre = ?, sous_titre = ?, ville = ?, rubrique = ?,
                   gabarit = ?, cadre_url = ?, expire_le = ?, maj_le = ? WHERE id = ?')
        ->execute([
            $d['titre'], $d['sous_titre'] ?: null, $d['ville'], $d['rubrique'],
            json_encode($g, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $d['cadre_url'], $d['expire_le'] ?: null, maintenant(), $id,
        ]);
}

/**
 * Supprime un décor et tout ce qui en dépend.
 *
 * Les clés étrangères ne portent pas de cascade — MySQL et SQLite ne
 * l'appliquent pas de la même façon selon la configuration de l'hébergeur,
 * et une suppression partielle laisserait des badges pointant vers un décor
 * disparu. On efface donc explicitement, dans l'ordre.
 */
function decor_supprimer(string $id): array
{
    $d = decor_par_id($id);
    if (!$d) {
        throw new TransitionRefusee('Décor introuvable.');
    }

    // Compté AVANT la suppression, pour pouvoir dire à l'équipe ce qu'elle a
    // détruit : des badges déjà entre les mains d'invités.
    $s = db()->prepare('SELECT COUNT(*) AS n FROM badges WHERE decor_id = ?');
    $s->execute([$id]);
    $nb_badges = (int) $s->fetch()['n'];

    db()->beginTransaction();
    try {
        foreach (['evenements', 'creations', 'badges', 'prevol'] as $table) {
            db()->prepare("DELETE FROM $table WHERE decor_id = ?")->execute([$id]);
        }
        db()->prepare('DELETE FROM decors WHERE id = ?')->execute([$id]);
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }

    // Le fichier du cadre, s'il n'est plus référencé par aucun autre décor.
    $fichier = null;
    $requete = parse_url((string) $d['cadre_url'], PHP_URL_QUERY);
    if ($requete) {
        parse_str($requete, $params);
        $nom = (string) ($params['f'] ?? '');
        if (preg_match('/^[0-9a-f-]{36}\.(png|webp)$/', $nom)) {
            $autre = db()->prepare('SELECT 1 FROM decors WHERE cadre_url LIKE ?');
            $autre->execute(['%' . $nom]);
            if (!$autre->fetch()) {
                $chemin = dossier_cadres() . '/' . $nom;
                if (is_file($chemin) && @unlink($chemin)) {
                    $fichier = $nom;
                }
            }
        }
    }

    return ['titre' => $d['titre'], 'badges' => $nb_badges, 'cadre' => $fichier];
}
