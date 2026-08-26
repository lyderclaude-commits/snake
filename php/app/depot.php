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

function decors_tous(): array
{
    $s = db()->query("SELECT d.*, u.nom AS auteur_nom, " . STATS_SQL . "
        FROM decors d LEFT JOIN utilisateurs u ON u.id = d.auteur_id
        ORDER BY d.maj_le DESC LIMIT 100");
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
            ->execute([$b['utilisateur_id'], KORIS_PAR_SCAN, 'Présence — ' . $b['decor_titre'], $b['jeton'], $now]);
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

function tableau_de_bord(): array
{
    $n = fn(string $sql) => (int) db()->query($sql)->fetch()['n'];
    return [
        'publies' => $n("SELECT COUNT(*) AS n FROM decors WHERE statut='publie'"),
        'en_attente' => $n("SELECT COUNT(*) AS n FROM decors WHERE statut='en_relecture'"),
        'badges' => $n('SELECT COUNT(*) AS n FROM badges'),
        'presences' => $n('SELECT COUNT(*) AS n FROM badges WHERE scanne_le IS NOT NULL'),
        'comptes' => $n('SELECT COUNT(*) AS n FROM utilisateurs'),
        'vues' => $n("SELECT COUNT(*) AS n FROM evenements WHERE genre='vue'"),
    ];
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
    return db()->query('SELECT * FROM utilisateurs ORDER BY cree_le DESC LIMIT 200')->fetchAll();
}
