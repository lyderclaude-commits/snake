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
    // Ce qui décide n'est pas le rôle mais le DROIT d'arbitrer : un
    // coordinateur publie, un éditeur propose. Comparer à « equipe » a
    // cessé d'être juste le jour où sept rôles ont existé.
    if (!transition_permise($d['statut'], $vers, droit($acteur, 'valider') ? 'equipe' : 'partenaire')) {
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

    // Les DÉCISIONS entrent au journal ; les soumissions non. Un auteur qui
    // propose son propre décor ne fait qu'avancer dans son travail ; celui
    // qui publie, refuse ou archive engage l'équipe.
    if (isset(JOURNAL_ACTIONS['decor.' . $vers])) {
        journal_ecrire($acteur, 'decor.' . $vers, 'decor', $id, (string) $d['titre'],
            trim((string) $motif) ?: null);
    }
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

    /**
     * Les Koris sont une ligne de l'offre de l'ORGANISATEUR.
     *
     * « QR Code Koris » est barré sur Découverte et coché à partir
     * d'Impact : la présence est comptée dans les deux cas — c'est la
     * mesure qui fait la valeur du produit — mais elle ne récompense
     * l'invité que si la campagne qui l'a émis y donne droit.
     */
    $decor = decor_par_id((string) $b['decor_id']);
    $auteur = $decor ? utilisateur_par_id((string) $decor['auteur_id']) : null;
    $koris = ($b['utilisateur_id'] && capacite($auteur, 'koris')) ? KORIS_PAR_SCAN : 0;

    $s = db()->prepare('UPDATE badges SET scanne_le = ?, scanne_par = ?, koris = ?
                        WHERE jeton = ? AND scanne_le IS NULL');
    $s->execute([$now, $agent_id, $koris, $b['jeton']]);
    if ($s->rowCount() === 0) {
        return ['ok' => false, 'raison' => 'deja', 'decor' => $b['decor_titre']];
    }

    // Les Koris ne vont qu'à un compte identifié : un badge créé sans compte
    // reste comptabilisé en présence, mais ne rapporte rien.
    if ($koris > 0) {
        db()->prepare('INSERT INTO koris (utilisateur_id, montant, motif, badge_jeton, cree_le) VALUES (?,?,?,?,?)')
            ->execute([$b['utilisateur_id'], $koris, 'Présence : ' . $b['decor_titre'], $b['jeton'], $now]);
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

/**
 * Le verdict d'un scan, en français, à partir de son résultat brut.
 *
 * Une seule traduction pour les DEUX chemins : le formulaire, qui recharge
 * la page, et l'API que la caméra appelle. Deux formulations séparées
 * finiraient par se contredire — et c'est la phrase que lit l'agent à
 * l'entrée, sous la pression d'une file d'attente.
 */
function verdict_scan(array $r): array
{
    return match (true) {
        $r['ok'] => [
            'ok' => true,
            'message' => 'Entrée validée : ' . $r['decor'],
            'detail' => match (true) {
                $r['koris'] > 0 => $r['porteur'] . ' · ' . $r['koris'] . ' Koris crédités',
                // La présence compte toujours : c'est elle qu'on vend. Seule
                // la récompense de l'invité dépend de l'offre.
                (bool) $r['porteur'] => $r['porteur'] . ' · présence comptée',
                default => 'Badge anonyme : présence comptée, aucun Kori',
            },
        ],
        ($r['raison'] ?? '') === 'deja' => [
            'ok' => false,
            'message' => 'Ce badge a déjà été scanné.',
            'detail' => 'Un badge ne vaut qu’une entrée.',
        ],
        default => ['ok' => false, 'message' => 'Code inconnu.', 'detail' => 'Vérifiez les 10 caractères.'],
    };
}

/**
 * Supprime un compte, et ce qui n'a plus de sens sans lui.
 *
 * Ce qui S'EN VA : le compte, ses liens courts, ses notifications, ses
 * Koris, le rattachement de ses créations et de ses badges.
 *
 * Ce qui RESTE : les décors publiés — d'autres personnes s'en servent, et
 * les badges déjà téléchargés portent leur QR —, et les présences scannées,
 * qui sont l'historique d'un événement réel. On les DÉTACHE plutôt que de
 * les effacer : la campagne reste au catalogue, sans propriétaire, et
 * l'équipe décide ensuite. Effacer les décors d'un partant casserait les
 * badges de tous ses invités.
 */
function supprimer_compte(string $id): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        foreach ([
            'DELETE FROM liens WHERE auteur_id = ?',
            'DELETE FROM notifications WHERE utilisateur_id = ?',
            // L'abonnement push est nominatif : le garder ferait arriver
            // des messages du guide sur le navigateur de quelqu'un qui
            // vient de nous demander de l'oublier.
            'DELETE FROM push WHERE utilisateur_id = ?',
            'DELETE FROM koris WHERE utilisateur_id = ?',
            'DELETE FROM creations WHERE utilisateur_id = ?',
            'UPDATE badges SET utilisateur_id = NULL WHERE utilisateur_id = ?',
            'UPDATE decors SET auteur_id = NULL WHERE auteur_id = ?',
            'DELETE FROM utilisateurs WHERE id = ?',
        ] as $sql) {
            $pdo->prepare($sql)->execute([$id]);
        }
        $pdo->commit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** La dernière fois qu'on a vu quelqu'un — au jour près, pas à la seconde. */
function marquer_vu(string $id): void
{
    static $fait = false;
    if ($fait) {
        return;
    }
    $fait = true;
    // Une écriture par jour et par compte : marquer chaque page vue
    // multiplierait les écritures par le nombre de clics, pour une
    // information dont la précision utile est la journée.
    db()->prepare('UPDATE utilisateurs SET vu_le = ? WHERE id = ? AND (vu_le IS NULL OR vu_le < ?)')
        ->execute([maintenant(), $id, gmdate('Y-m-d\T00:00:00\Z')]);
}

/* ================= liens courts ================= */

/**
 * Un code court, lisible à voix haute.
 *
 * Ni `0`/`O` ni `1`/`l`/`I` : un lien se dicte au téléphone et se recopie
 * d'une affiche. Six caractères sur cet alphabet donnent plus d'un
 * milliard de combinaisons — largement de quoi ne jamais tirer deux fois le
 * même, et on vérifie quand même.
 */
function nouveau_code_lien(): string
{
    $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    do {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }
        $s = db()->prepare('SELECT 1 FROM liens WHERE code = ?');
        $s->execute([$code]);
    } while ($s->fetch());
    return $code;
}

/**
 * L'adresse publique d'un lien court, la plus courte que l'installation permette.
 *
 * Trois formes, de la meilleure à la plus sûre :
 *
 *  1. `https://wkb.link/AbC123` — quand un domaine dédié est réglé. C'est
 *     la forme qu'on met sur une affiche : quinze caractères de moins que
 *     le domaine principal, et une marque à soi.
 *  2. `https://boost.wakabileguide.com/AbC123` — quand la réécriture d'URL
 *     fonctionne mais qu'aucun domaine dédié n'est réglé.
 *  3. `…/index.php?p=l&c=AbC123` — le repli qui marche partout, même sans
 *     `mod_rewrite`. Moins joli, jamais cassé.
 *
 * Le choix ne se devine pas : l'équipe le règle, et l'écran des réglages
 * vérifie que la forme courte répond vraiment avant de la proposer.
 */
function lien_court_url(string $code): string
{
    $r = reglages_bdd(['domaine_liens', 'liens_chemin_court']);
    $domaine = trim((string) ($r['domaine_liens'] ?? ''));

    if ($domaine !== '') {
        return rtrim($domaine, '/') . '/' . rawurlencode($code);
    }
    if (($r['liens_chemin_court'] ?? '') === '1') {
        return base_url() . '/' . rawurlencode($code);
    }
    return base_url() . '/index.php?p=l&c=' . rawurlencode($code);
}

function liens_de(string $auteur_id, int $limite = 200): array
{
    $s = db()->prepare("SELECT l.*, d.titre AS decor_titre FROM liens l
        LEFT JOIN decors d ON d.id = l.decor_id
        WHERE l.auteur_id = ? ORDER BY l.cree_le DESC LIMIT $limite");
    $s->execute([$auteur_id]);
    return $s->fetchAll();
}

function compter_liens(string $auteur_id): int
{
    return compter('SELECT COUNT(*) AS n FROM liens WHERE auteur_id = ?', [$auteur_id]);
}

function creer_lien(string $auteur_id, string $cible, string $titre, ?string $decor_id = null): string
{
    $code = nouveau_code_lien();
    db()->prepare('INSERT INTO liens (id, code, cible, titre, auteur_id, decor_id, clics, cree_le)
                   VALUES (?,?,?,?,?,?,0,?)')
        ->execute([nouvel_id(), $code, $cible, $titre ?: null, $auteur_id, $decor_id, maintenant()]);
    return $code;
}

function supprimer_lien(string $auteur_id, string $code): bool
{
    $s = db()->prepare('DELETE FROM liens WHERE code = ? AND auteur_id = ?');
    $s->execute([$code, $auteur_id]);
    return $s->rowCount() > 0;
}

/**
 * Suit un lien et compte le clic.
 *
 * Le compteur est incrémenté en SQL et non lu-puis-écrit : deux personnes
 * qui cliquent dans la même seconde compteraient sinon pour une.
 */
function suivre_lien(string $code): ?string
{
    $s = db()->prepare('SELECT cible FROM liens WHERE code = ?');
    $s->execute([$code]);
    $cible = $s->fetchColumn();
    if (!$cible) {
        return null;
    }
    db()->prepare('UPDATE liens SET clics = clics + 1, dernier_clic = ? WHERE code = ?')
        ->execute([maintenant(), $code]);
    return (string) $cible;
}

/* ================= ce que l'offre autorise ================= */

/**
 * L'organisateur d'un décor peut-il encore émettre un badge ce mois-ci ?
 *
 * C'est ICI que l'offre devient réelle. Le tableau de bord affichait un
 * « repère indicatif » et ne refusait rien : une ligne vendue 5 000 FCFA
 * que personne n'appliquait. Le compteur est mensuel, remis à zéro le 1er,
 * et l'équipe n'y est jamais soumise.
 *
 * @return array{ok: bool, message: string, reste: int, quota: int, consomme: int}
 */
function quota_telechargements(array $decor): array
{
    $auteur = utilisateur_par_id((string) $decor['auteur_id']);
    if (!$auteur) {
        // Compte supprimé : le décor n'a plus de propriétaire à limiter.
        return ['ok' => true, 'message' => '', 'reste' => -1, 'quota' => -1, 'consomme' => 0];
    }

    $max = quota($auteur, 'telechargements');
    $consomme = telechargements_du_mois((string) $auteur['id']);
    if ($max < 0) {
        return ['ok' => true, 'message' => '', 'reste' => -1, 'quota' => -1, 'consomme' => $consomme];
    }

    $reste = max(0, $max - $consomme);
    return [
        'ok' => $reste > 0,
        'message' => $reste > 0 ? '' :
            'Cette campagne a atteint son nombre de badges pour ce mois-ci. '
            . 'Revenez le 1er du mois prochain, ou prévenez l’organisateur.',
        'reste' => $reste,
        'quota' => $max,
        'consomme' => $consomme,
    ];
}

/**
 * Prévient l'organisateur que son quota est plein — une fois par mois.
 *
 * Sans le « une fois », chaque invité refusé enverrait un courriel : le
 * jour où la campagne marche vraiment, l'organisateur recevrait deux cents
 * messages disant qu'elle marche trop bien.
 */
function alerter_quota_plein(array $decor): void
{
    $cle = 'quota_alerte|' . $decor['auteur_id'] . '|' . gmdate('Y-m');
    if ((reglages_bdd([$cle])[$cle] ?? '') !== '') {
        return;
    }
    reglages_bdd_poser([$cle => maintenant()]);

    $auteur = utilisateur_par_id((string) $decor['auteur_id']);
    if (!$auteur) {
        return;
    }
    notifier(
        (string) $auteur['id'],
        'compte',
        'Votre quota de téléchargements est atteint',
        'Votre offre ' . formule_libelle($auteur['formule'] ?? null) . ' couvre '
        . quota($auteur, 'telechargements') . ' badges par mois, et ils sont pris. '
        . 'Vos invités ne peuvent plus télécharger jusqu’au 1er du mois prochain. '
        . 'Passer à l’offre supérieure rouvre immédiatement le robinet.',
        '?p=partenaire'
    );
}

/**
 * Tout ce que l'équipe doit voir d'un compte, en une requête par sujet.
 *
 * Le tableau de bord d'un organisateur lui montre SON compte ; celui-ci
 * montre le même compte vu de l'autre côté du guichet, avec ce qu'un
 * commercial demande toujours : depuis quand, combien, et est-ce que ça
 * bute sur une limite.
 */
function fiche_compte(string $id): ?array
{
    $u = utilisateur_par_id($id);
    if (!$u) {
        return null;
    }
    $decors = decors_de($id);

    $emis = $scannes = $vues = $telecharges = 0;
    foreach ($decors as $d) {
        $p = presence((string) $d['id']);
        $emis += $p['emis'];
        $scannes += $p['scannes'];
        $vues += (int) $d['vues'];
        $telecharges += (int) $d['telechargements'];
    }

    return [
        'compte' => $u,
        'bilan' => bilan_offre($u),
        'decors' => $decors,
        'liens' => liens_de($id, 20),
        'totaux' => [
            'campagnes' => count($decors),
            'vues' => $vues,
            'telecharges' => $telecharges,
            'badges' => $emis,
            'presences' => $scannes,
            'taux' => $emis ? $scannes / $emis : 0.0,
            'koris' => koris_solde($id),
            'clics' => compter('SELECT COALESCE(SUM(clics),0) AS n FROM liens WHERE auteur_id = ?', [$id]),
        ],
    ];
}

/**
 * Le bilan complet d'un compte face à son offre.
 *
 * Une seule fonction, deux lecteurs : l'organisateur sur son tableau de
 * bord, et l'équipe sur la fiche du compte. Deux calculs séparés finiraient
 * par ne plus dire la même chose — et c'est précisément le genre de
 * désaccord qu'on découvre en pleine discussion commerciale.
 */
function bilan_offre(array $u): array
{
    $o = offre($u);
    $lignes = [];

    foreach (['campagnes', 'telechargements', 'liens_courts', 'emails_par_mois'] as $cle) {
        $max = quota($u, $cle);
        $consomme = match ($cle) {
            'campagnes' => campagnes_actives((string) $u['id']),
            'telechargements' => telechargements_du_mois((string) $u['id']),
            'liens_courts' => compter_liens((string) $u['id']),
            'emails_par_mois' => emails_du_mois((string) $u['id']),
        };
        $lignes[$cle] = [
            'nature' => 'compteur',
            'libelle' => OFFRE_LIGNES[$cle][0],
            'aide' => OFFRE_LIGNES[$cle][2],
            'inclus' => $max !== 0,
            'max' => $max,
            'consomme' => $consomme,
            'reste' => $max < 0 ? -1 : max(0, $max - $consomme),
            'part' => $max <= 0 ? 0 : min(100, (int) round($consomme / $max * 100)),
        ];
    }

    foreach (OFFRE_LIGNES as $cle => [$libelle, $nature, $aide]) {
        if ($nature === 'compteur') {
            continue;
        }
        $lignes[$cle] = [
            'nature' => $nature,
            'libelle' => $libelle,
            'aide' => $aide,
            'inclus' => capacite($u, $cle),
            'debloque' => capacite($u, $cle) ? null : offre_qui_debloque($cle),
        ];
    }

    return [
        'offre' => $o,
        'cle' => $u['formule'] ?? 'decouverte',
        'bonus' => max(0, (int) ($u['bonus_telechargements'] ?? 0)),
        'lignes' => $lignes,
    ];
}

/* ================= réglages ================= */

/**
 * Les réglages que l'équipe change en ligne, par opposition à `config.php`.
 *
 * `config.php` décrit la MACHINE — base de données, chemins — et il est
 * écrit une fois par l'installateur. Ce qui se règle en marchant, comme le
 * transport e-mail, vit ici : une décompression du zip par-dessus ne
 * l'efface pas, et un mauvais réglage se corrige sans FTP.
 */
function reglages_bdd(array $cles): array
{
    if (!$cles) {
        return [];
    }
    $trous = implode(',', array_fill(0, count($cles), '?'));
    try {
        $s = db()->prepare("SELECT cle, valeur FROM reglages WHERE cle IN ($trous)");
        $s->execute(array_values($cles));
    } catch (PDOException) {
        // Table absente : installation pas encore migrée. Les valeurs de
        // départ suffisent, et surtout la page ne tombe pas.
        return [];
    }
    $out = [];
    foreach ($s->fetchAll() as $r) {
        $out[$r['cle']] = (string) ($r['valeur'] ?? '');
    }
    return $out;
}

function reglages_bdd_poser(array $valeurs): void
{
    $now = maintenant();
    $pdo = db();
    // Pas d'UPSERT : sa syntaxe diffère entre MySQL et SQLite, et deux
    // requêtes triviales valent mieux qu'un dialecte à maintenir en double.
    $maj = $pdo->prepare('UPDATE reglages SET valeur = ?, maj_le = ? WHERE cle = ?');
    $ins = $pdo->prepare('INSERT INTO reglages (cle, valeur, maj_le) VALUES (?,?,?)');
    foreach ($valeurs as $cle => $valeur) {
        $maj->execute([(string) $valeur, $now, (string) $cle]);
        if ($maj->rowCount() === 0) {
            try {
                $ins->execute([(string) $cle, (string) $valeur, $now]);
            } catch (PDOException) {
                // Course entre deux enregistrements simultanés : la valeur
                // de l'autre est aussi bonne que la nôtre.
            }
        }
    }
}

/* ================= notifications ================= */

/**
 * Notifie dans l'application, ET par courriel quand le transport est branché.
 *
 * Les deux vont ensemble : une notification qu'il faut venir chercher ne
 * prévient personne. Un envoi qui échoue ne remonte pas — la décision de
 * modération qui l'a déclenché reste prise, et on ne va pas la refuser
 * parce qu'un serveur SMTP a hoqueté.
 */
function notifier(
    string $utilisateur_id,
    string $genre,
    string $titre,
    ?string $corps = null,
    ?string $lien = null,
    bool $par_courriel = true
): void {
    db()->prepare('INSERT INTO notifications (id, utilisateur_id, genre, titre, corps, lien, cree_le)
                   VALUES (?,?,?,?,?,?,?)')
        ->execute([nouvel_id(), $utilisateur_id, $genre, $titre, $corps, $lien, maintenant()]);

    if (!$par_courriel || !function_exists('courriel_branche') || !courriel_branche()) {
        return;
    }
    $u = utilisateur_par_id($utilisateur_id);
    if (!$u || ($u['suspendu'] ?? 0)) {
        return;
    }
    $url = $lien ? base_url() . '/index.php' . (str_starts_with($lien, '?') ? $lien : '?' . ltrim($lien, '?')) : '';
    courriel_mis_en_page(
        (string) $u['email'],
        (string) $u['nom'],
        $titre,
        $titre,
        (string) ($corps ?? ''),
        $url,
        'Ouvrir Wakabi Boost'
    );
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

/**
 * Prévient toute l'équipe d'un même fait.
 *
 * Le motif « boucler sur `equipe()` pour notifier chacun » revenait à trois
 * endroits ; à la quatrième copie, on finit par en oublier une et une file
 * d'attente ne se surveille plus.
 */
function notifier_equipe(string $genre, string $titre, ?string $corps = null, ?string $lien = null): void
{
    foreach (equipe() as $membre) {
        notifier((string) $membre['id'], $genre, $titre, $corps, $lien);
    }
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

/**
 * Combien de comptes par formule : la photo du portefeuille.
 *
 * Seuls les rôles qui ACHÈTENT y figurent. Un coordinateur, un scanner ou
 * un invité comptaient dans le portefeuille comme des clients payants, ce
 * qui gonflait silencieusement la ligne « Découverte » — et c'est le
 * chiffre qu'on regarde pour savoir si l'affaire marche.
 */
function comptes_par_formule(): array
{
    $trous = implode(',', array_fill(0, count(ROLES_AVEC_OFFRE), '?'));
    $s = db()->prepare("SELECT formule, COUNT(*) AS n FROM utilisateurs
                        WHERE role IN ($trous) GROUP BY formule");
    $s->execute(ROLES_AVEC_OFFRE);
    $out = [];
    foreach ($s->fetchAll() as $r) {
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

/**
 * Le décompte des décors, écrit une fois pour les deux listes de comptes.
 *
 * Sans lui, la liste ne dit pas lesquels travaillent et lesquels dorment.
 */
const COMPTE_COLONNES = "u.*,
        (SELECT COUNT(*) FROM decors d WHERE d.auteur_id = u.id) AS decors,
        (SELECT COUNT(*) FROM decors d WHERE d.auteur_id = u.id
            AND d.statut IN ('publie','en_relecture','corrections')) AS actives";

/* ---------------- confier UNE campagne ---------------- */

/**
 * Les équipiers d'un décor : les personnes invitées à travailler dessus.
 *
 * Un organisateur qui fait appel à un graphiste pour une soirée ne veut
 * pas lui donner ses statistiques, ses liens, sa régie et son historique
 * de facturation. Jusqu'ici le choix était binaire : tout, ou le mot de
 * passe du compte — et c'est le mot de passe qui circulait.
 */
function equipiers_de(string $decor_id): array
{
    $s = db()->prepare('SELECT e.*, u.nom, u.email FROM equipiers e
                        JOIN utilisateurs u ON u.id = e.utilisateur_id
                        WHERE e.decor_id = ? ORDER BY e.cree_le');
    $s->execute([$decor_id]);
    return $s->fetchAll();
}

/** Les décors qu'on m'a confiés sans me donner le compte entier. */
function decors_confies(string $utilisateur_id): array
{
    $s = db()->prepare("SELECT d.*, u.nom AS auteur_nom, " . STATS_SQL . "
        FROM equipiers e JOIN decors d ON d.id = e.decor_id
        LEFT JOIN utilisateurs u ON u.id = d.auteur_id
        WHERE e.utilisateur_id = ? ORDER BY d.maj_le DESC");
    $s->execute([$utilisateur_id]);
    return $s->fetchAll();
}

function est_equipier(string $decor_id, string $utilisateur_id): bool
{
    $s = db()->prepare('SELECT 1 FROM equipiers WHERE decor_id = ? AND utilisateur_id = ?');
    $s->execute([$decor_id, $utilisateur_id]);
    return (bool) $s->fetchColumn();
}

/**
 * Cette personne peut-elle travailler sur ce décor ?
 *
 * Trois façons, et une seule fonction — pour qu'aucun écran ne réponde
 * autrement qu'un autre : l'équipe qui voit tout, l'auteur, et l'équipier
 * invité sur CE décor-là.
 */
function decor_accessible(?array $u, ?array $d): bool
{
    if (!$u || !$d) {
        return false;
    }
    return droit($u, 'decors_tous')
        || $d['auteur_id'] === $u['id']
        || est_equipier((string) $d['id'], (string) $u['id']);
}

function equipier_inviter(string $decor_id, string $utilisateur_id, string $par): void
{
    if (est_equipier($decor_id, $utilisateur_id)) {
        return;
    }
    db()->prepare('INSERT INTO equipiers (id, decor_id, utilisateur_id, invite_par, cree_le)
                   VALUES (?,?,?,?,?)')
        ->execute([nouvel_id(), $decor_id, $utilisateur_id, $par, maintenant()]);
}

function equipier_retirer(string $decor_id, string $utilisateur_id): void
{
    db()->prepare('DELETE FROM equipiers WHERE decor_id = ? AND utilisateur_id = ?')
        ->execute([$decor_id, $utilisateur_id]);
}

/**
 * Les comptes de la MAISON, du plus ancien au plus récent.
 *
 * Ils tiennent sur un écran et ne se cherchent pas : on les connaît par
 * leur prénom. Les séparer de la longue liste des clients règle surtout un
 * défaut qu'on ne voit qu'au bout d'un an : une liste plafonnée et rangée
 * du plus récent au plus ancien finit par pousser le fondateur derrière
 * deux cents organisateurs, c'est-à-dire hors de portée — et c'est
 * justement le compte dont on a besoin le jour où quelque chose cloche.
 */
function comptes_equipe(): array
{
    $roles = implode(',', array_fill(0, count(ROLES_INTERNES), '?'));
    $s = db()->prepare('SELECT ' . COMPTE_COLONNES . "
        FROM utilisateurs u WHERE u.role IN ($roles) ORDER BY u.cree_le ASC");
    $s->execute(ROLES_INTERNES);
    return $s->fetchAll();
}

/**
 * Les comptes CLIENTS, cherchables et plafonnés.
 *
 * On cherche par nom, par adresse ou par structure : ce sont les trois
 * choses qu'on a sous les yeux quand quelqu'un écrit pour demander de
 * l'aide.
 */
function comptes_clients(string $cherche = '', int $limite = 100): array
{
    $roles = implode(',', array_fill(0, count(ROLES_PUBLICS), '?'));
    $ou = "u.role IN ($roles)";
    $args = ROLES_PUBLICS;
    if (trim($cherche) !== '') {
        $ou .= ' AND (u.nom LIKE ? OR u.email LIKE ? OR u.organisation LIKE ?)';
        $m = '%' . trim($cherche) . '%';
        array_push($args, $m, $m, $m);
    }
    $s = db()->prepare('SELECT ' . COMPTE_COLONNES . "
        FROM utilisateurs u WHERE $ou ORDER BY u.cree_le DESC LIMIT " . max(1, $limite));
    $s->execute($args);
    return $s->fetchAll();
}

/** Combien de clients répondent à la recherche — pour dire la vérité sur ce qui est caché. */
function comptes_clients_combien(string $cherche = ''): int
{
    $roles = implode(',', array_fill(0, count(ROLES_PUBLICS), '?'));
    $ou = "role IN ($roles)";
    $args = ROLES_PUBLICS;
    if (trim($cherche) !== '') {
        $ou .= ' AND (nom LIKE ? OR email LIKE ? OR organisation LIKE ?)';
        $m = '%' . trim($cherche) . '%';
        array_push($args, $m, $m, $m);
    }
    $s = db()->prepare("SELECT COUNT(*) FROM utilisateurs WHERE $ou");
    $s->execute($args);
    return (int) $s->fetchColumn();
}

/**
 * Catalogue complet pour l'équipe, filtrable par statut.
 *
 * Distinct de `decors_en_tete()`, qui alimente le tableau de bord : ici on
 * filtre et on cherche, parce que la liste est faite pour agir dessus.
 */
/** Combien de décors le filtre courant est-il en train de décrire. */
const CATALOGUE_PAR_PAGE = 40;

/** Le `WHERE` du catalogue, écrit une fois pour la liste et pour le compte. */
function catalogue_filtre(?string $statut, string $cherche): array
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
    return [$ou ? 'WHERE ' . implode(' AND ', $ou) : '', $args];
}

/**
 * Une PAGE du catalogue, et non les deux cents premiers.
 *
 * La liste plafonnée annonçait « 260 au total » puis en montrait 200, sans
 * un mot : l'écran se contredisait lui-même, et les soixante plus anciens
 * n'étaient atteignables que par la recherche — à condition de deviner
 * qu'il en manquait.
 */
function decors_catalogue(?string $statut = null, string $cherche = '', int $page = 1): array
{
    [$where, $args] = catalogue_filtre($statut, $cherche);
    $decalage = max(0, ($page - 1) * CATALOGUE_PAR_PAGE);

    $s = db()->prepare("SELECT d.*, u.nom AS auteur_nom, " . STATS_SQL . "
        FROM decors d LEFT JOIN utilisateurs u ON u.id = d.auteur_id
        $where ORDER BY d.maj_le DESC LIMIT " . CATALOGUE_PAR_PAGE . " OFFSET $decalage");
    $s->execute($args);
    return $s->fetchAll();
}

/** Le nombre de décors que le filtre décrit — pour savoir combien de pages. */
function decors_catalogue_combien(?string $statut = null, string $cherche = ''): int
{
    [$where, $args] = catalogue_filtre($statut, $cherche);
    $s = db()->prepare("SELECT COUNT(*) FROM decors d $where");
    $s->execute($args);
    return (int) $s->fetchColumn();
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

/* ================= blog ================= */

/**
 * Le blog est une PAGE PUBLIQUE, pas un journal interne.
 *
 * Il sert deux choses à la fois, et c'est pour cela qu'il existe : il donne
 * au guide de quoi se faire trouver par un moteur de recherche — un site
 * dont toutes les pages sont des formulaires ne se référence pas — et il
 * rend concret l'« article sponsorisé » vendu avec l'offre Mouvement.
 */
/**
 * Le `WHERE` du blog public — écrit une fois pour la liste et pour le compte.
 *
 * La recherche porte sur le titre, le chapô ET le corps : quelqu'un se
 * souvient rarement d'un titre, mais très bien d'un mot lu dedans.
 */
function blog_filtre(string $cherche): array
{
    $ou = ["statut = 'publie'", 'publie_le <= ?'];
    $args = [maintenant()];
    if (trim($cherche) !== '') {
        $ou[] = '(titre LIKE ? OR chapo LIKE ? OR corps LIKE ?)';
        $m = '%' . trim($cherche) . '%';
        array_push($args, $m, $m, $m);
    }
    return ['WHERE ' . implode(' AND ', $ou), $args];
}

function articles_publies(int $limite = 30, int $depuis = 0, string $cherche = ''): array
{
    $limite = max(1, min(100, $limite));
    $depuis = max(0, $depuis);
    [$where, $args] = blog_filtre($cherche);
    $s = db()->prepare("SELECT * FROM articles $where
                        ORDER BY publie_le DESC LIMIT $limite OFFSET $depuis");
    $s->execute($args);
    return $s->fetchAll();
}

/** Combien d'articles publiés répondent à la recherche. */
function compter_articles_publies_cherches(string $cherche = ''): int
{
    [$where, $args] = blog_filtre($cherche);
    $s = db()->prepare("SELECT COUNT(*) FROM articles $where");
    $s->execute($args);
    return (int) $s->fetchColumn();
}

function compter_articles_publies(): int
{
    $s = db()->prepare("SELECT COUNT(*) FROM articles WHERE statut = 'publie' AND publie_le <= ?");
    $s->execute([maintenant()]);
    return (int) $s->fetchColumn();
}

/** Tous les articles, brouillons compris. Réservé à l'équipe. */
function articles_tous(): array
{
    return db()->query('SELECT * FROM articles ORDER BY
        CASE WHEN statut = \'publie\' THEN 1 ELSE 0 END, COALESCE(publie_le, maj_le) DESC')->fetchAll();
}

function article_par_slug(string $slug): ?array
{
    $s = db()->prepare('SELECT * FROM articles WHERE slug = ?');
    $s->execute([$slug]);
    return $s->fetch() ?: null;
}

function article_par_id(string $id): ?array
{
    $s = db()->prepare('SELECT * FROM articles WHERE id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

function slug_article_libre(string $titre, ?string $sauf = null): string
{
    $base = slugifier($titre) ?: 'article';
    $slug = $base;
    $n = 2;
    while (true) {
        $a = article_par_slug($slug);
        if (!$a || $a['id'] === $sauf) {
            return $slug;
        }
        $slug = $base . '-' . $n++;
    }
}

/**
 * Un article naît TOUJOURS en brouillon.
 *
 * Le statut n'est pas un champ du formulaire : il appartient à
 * `article_transition()`, qui seule connaît le circuit. Le laisser entrer
 * par la porte de la création rouvrirait exactement le trou que la
 * modération sert à fermer — un auteur qui poste `statut=publie`.
 */
function article_creer(array $a): string
{
    $id = nouvel_id();
    $now = maintenant();
    db()->prepare('INSERT INTO articles
        (id, slug, titre, chapo, corps, couverture, decor_id, statut, auteur_id, auteur_nom, cree_le, maj_le)
        VALUES (?,?,?,?,?,?,?,\'brouillon\',?,?,?,?)')
      ->execute([
          $id, $a['slug'], $a['titre'], $a['chapo'] ?: null, $a['corps'],
          $a['couverture'] ?: null, ($a['decor_id'] ?? '') ?: null,
          $a['auteur_id'], $a['auteur_nom'], $now, $now,
      ]);
    return $id;
}

/**
 * Le CONTENU d'un article, et rien d'autre.
 *
 * Ni le statut, ni la date de publication : ils appartiennent à
 * `article_transition()`. Un formulaire d'édition qui pourrait aussi
 * publier serait une modération contournable en changeant un champ caché.
 */
function article_maj(string $id, array $a): void
{
    db()->prepare('UPDATE articles SET slug = ?, titre = ?, chapo = ?, corps = ?, couverture = ?,
                   decor_id = ?, maj_le = ? WHERE id = ?')
        ->execute([
            $a['slug'], $a['titre'], $a['chapo'] ?: null, $a['corps'], $a['couverture'] ?: null,
            ($a['decor_id'] ?? '') ?: null, maintenant(), $id,
        ]);
}

/**
 * Les décors qu'un auteur a le droit de citer dans un article.
 *
 * Ceux qui sont PUBLIÉS — un lecteur pourra les ouvrir — et, en plus, les
 * siens quels que soient leur état : un organisateur qui écrit sur sa
 * soirée de samedi lie son décor avant de le publier, et les deux
 * paraissent ensemble. Citer le brouillon d'un AUTRE en révélerait le
 * titre avant l'heure ; c'est le seul cas qu'on ferme.
 *
 * L'équipe voit tout, comme partout ailleurs.
 *
 * LES SIENS D'ABORD, et la liste est courte. Neuf fois sur dix on cite son
 * propre décor, celui qu'on vient de faire ; le chercher au milieu de
 * quatre cents entrées classées par date de publication, c'est renoncer et
 * n'en lier aucun. Un déroulant de quatre cents lignes n'est pas une
 * liste, c'est un mur.
 *
 * @return array<int, array<string, mixed>>
 */
const DECORS_LIABLES_MAX = 60;

function decors_liables(?array $u): array
{
    if ($u === null) {
        return decors_publies(DECORS_LIABLES_MAX);
    }
    $siens = droit($u, 'decors_tous')
        ? db()->query('SELECT d.*, ' . STATS_SQL . ' FROM decors d
                       ORDER BY d.maj_le DESC LIMIT ' . DECORS_LIABLES_MAX)->fetchAll()
        : decors_de((string) $u['id']);

    $vus = [];
    $out = [];
    foreach ([...$siens, ...decors_publies(DECORS_LIABLES_MAX)] as $d) {
        if (isset($vus[$d['id']]) || count($out) >= DECORS_LIABLES_MAX) {
            continue;
        }
        $vus[$d['id']] = true;
        $out[] = $d;
    }
    return $out;
}

/**
 * Le décor à MONTRER au bas d'un article, ou rien.
 *
 * On revérifie qu'il est publié et non expiré au moment de la lecture, et
 * non au moment de l'écriture : un décor archivé six mois après la
 * parution ne doit pas laisser dans l'article une carte qui mène à une
 * page morte. L'article, lui, survit — c'est la carte qui disparaît.
 */
function decor_lie(?array $article): ?array
{
    if (($article['decor_id'] ?? '') === '' || $article['decor_id'] === null) {
        return null;
    }
    $d = decor_par_id((string) $article['decor_id']);
    if (!$d || $d['statut'] !== 'publie') {
        return null;
    }
    if (($d['expire_le'] ?? null) !== null && $d['expire_le'] <= maintenant()) {
        return null;
    }
    return $d;
}

/**
 * L'illustration d'un article dans une grille. Jamais rien.
 *
 * Trois sources, dans cet ordre : sa couverture, le cadre du décor qu'il
 * cite, et à défaut la vignette de la maison. Une carte sans image au
 * milieu de deux cartes qui en ont ne se lit pas comme « cet article n'a
 * pas d'image » mais comme « cette carte est cassée » — et la grille
 * entière se met de travers, parce que les cartes n'ont plus la même
 * hauteur.
 *
 * Le cadre du décor est le repli le plus utile : un article qui parle
 * d'une soirée montre l'affiche de cette soirée. C'est aussi ce qui rend
 * la grille cohérente sans demander une image de plus à l'auteur.
 */
function illustration_article(?array $a): string
{
    if (($a['couverture'] ?? '') !== '') {
        return (string) $a['couverture'];
    }
    $d = decor_lie($a);
    if ($d && ($d['cadre_url'] ?? '') !== '') {
        return (string) $d['cadre_url'];
    }
    return url_og(null);
}

/** Les articles d'un auteur, tous états confondus. */
function articles_de(string $auteur_id): array
{
    $s = db()->prepare('SELECT * FROM articles WHERE auteur_id = ? ORDER BY maj_le DESC');
    $s->execute([$auteur_id]);
    return $s->fetchAll();
}

/** La file de relecture du blog, la plus ancienne soumission d'abord. */
function articles_en_attente(): array
{
    return db()->query("SELECT a.*, u.nom AS propose_par
                        FROM articles a LEFT JOIN utilisateurs u ON u.id = a.auteur_id
                        WHERE a.statut = 'en_relecture' ORDER BY a.soumis_le ASC")->fetchAll();
}

function articles_a_relire(): int
{
    return (int) db()->query("SELECT COUNT(*) FROM articles WHERE statut = 'en_relecture'")->fetchColumn();
}

/**
 * Change l'état d'un article, en faisant respecter le circuit.
 *
 * La MÊME machine à états que les décors, littéralement : `transition_permise()`
 * est réutilisée telle quelle. Les deux objets suivent le même parcours —
 * quelqu'un propose, l'équipe décide — et deux tables de règles pour un
 * seul circuit, c'est une des deux qu'on oubliera de corriger.
 */
function article_transition(string $id, string $vers, array $acteur, ?string $motif = null): void
{
    $a = article_par_id($id);
    if (!$a) {
        throw new TransitionRefusee('Article introuvable.');
    }
    $role = droit($acteur, 'valider') ? 'equipe' : 'partenaire';
    if (!transition_permise((string) $a['statut'], $vers, $role)) {
        throw new TransitionRefusee(sprintf(
            'Passage « %s → %s » non autorisé pour ce rôle.', $a['statut'], $vers
        ));
    }
    if (in_array($vers, ['refuse', 'corrections'], true) && !trim((string) $motif)) {
        throw new TransitionRefusee('Un motif est obligatoire pour refuser ou demander des corrections.');
    }

    $now = maintenant();
    $sets = ['statut = ?', 'maj_le = ?'];
    $vals = [$vers, $now];

    if ($vers === 'en_relecture') {
        $sets[] = 'soumis_le = ?';
        $vals[] = $now;
    }
    if ($vers === 'publie') {
        // La date de publication ne se réécrit pas : un article republié
        // après correction ne doit pas remonter en tête comme s'il était neuf.
        $sets[] = 'publie_le = ?';
        $vals[] = $a['publie_le'] ?: $now;
    }
    if (in_array($vers, ['publie', 'refuse', 'corrections'], true)) {
        $sets[] = 'relu_le = ?';
        $sets[] = 'relu_par = ?';
        $sets[] = 'motif = ?';
        $vals[] = $now;
        $vals[] = $acteur['id'];
        $vals[] = trim((string) $motif) ?: null;
    }
    $vals[] = $id;
    db()->prepare('UPDATE articles SET ' . implode(', ', $sets) . ' WHERE id = ?')->execute($vals);

    if (isset(JOURNAL_ACTIONS['article.' . $vers])) {
        journal_ecrire($acteur, 'article.' . $vers, 'article', $id, (string) $a['titre'],
            trim((string) $motif) ?: null);
    }
}

function article_supprimer(string $id): void
{
    $a = article_par_id($id);
    db()->prepare('DELETE FROM articles WHERE id = ?')->execute([$id]);
    if (!$a) {
        return;
    }

    /**
     * Les images s'en vont avec l'article — couverture ET illustrations.
     *
     * Ne nettoyer que la couverture laissait grossir `donnees/medias/`
     * indéfiniment : un article illustré supprimé, et ses cinq images
     * restaient sur le disque pour toujours, sans que rien ne les
     * référence. Sur un hébergement mutualisé, c'est le quota qui finit
     * par se remplir — et un disque plein empêche d'écrire la sauvegarde.
     */
    preg_match_all(
        '/[?&]f=([0-9a-f-]{36}\.(?:png|webp|jpg))(?:$|&|\)|"|\s)/',
        (string) $a['couverture'] . ' ' . (string) $a['corps'],
        $trouves
    );
    foreach (array_unique($trouves[1]) as $fichier) {
        // Une image partagée par deux articles reste : c'est le cas d'un
        // texte dupliqué pour en faire une variante.
        $autre = db()->prepare('SELECT 1 FROM articles WHERE couverture LIKE ? OR corps LIKE ?');
        $autre->execute(['%' . $fichier . '%', '%' . $fichier . '%']);
        if (!$autre->fetch()) {
            @unlink(dossier_medias() . '/' . $fichier);
        }
    }
}

/**
 * Compte une lecture, une fois par visiteur et par article.
 *
 * Sans le garde en session, recharger la page ferait un lecteur de plus, et
 * le chiffre qu'on montre à un annonceur ne voudrait plus rien dire.
 */
function article_lu(string $id): void
{
    demarrer_session();
    $vus = $_SESSION['articles_vus'] ?? [];
    if (in_array($id, $vus, true)) {
        return;
    }
    $vus[] = $id;
    $_SESSION['articles_vus'] = array_slice($vus, -50);
    db()->prepare('UPDATE articles SET vues = vues + 1 WHERE id = ?')->execute([$id]);
}
