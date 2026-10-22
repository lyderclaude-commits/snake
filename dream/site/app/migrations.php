<?php
/**
 * Les données de Live Y Dream d'avant le socle, rangées à la façon du socle.
 *
 * Chaque migration est jouée une seule fois (le socle note son nom), et ne
 * fait rien sur une base neuve : il n'y a alors rien à déplacer.
 */

declare(strict_types=1);

/** L'ancien réglage « destinataire » devient une copie : contact@ est désormais fixe. */
function migration_lyd_contact_copies(PDO $pdo): void
{
    $pdo->exec("INSERT OR IGNORE INTO reglages (cle, valeur) SELECT 'contact_copies', valeur FROM reglages
        WHERE cle = 'contact_destinataire' AND lower(valeur) <> 'contact@liveydream.com'");
    $pdo->exec("DELETE FROM reglages WHERE cle = 'contact_destinataire'");
}

/** Les champs propres au formulaire de Live Y Dream (entreprise, WhatsApp…) rejoignent `donnees`. */
function migration_lyd_messages(PDO $pdo): void
{
    $cols = array_column($pdo->query('PRAGMA table_info(messages)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    $anciens = array_values(array_intersect(['entreprise', 'whatsapp', 'besoin', 'budget'], $cols));
    if (!$anciens) {
        return;
    }
    $maj = $pdo->prepare('UPDATE messages SET donnees = ? WHERE id = ?');
    foreach ($pdo->query('SELECT id, donnees, ' . implode(', ', $anciens) . ' FROM messages')->fetchAll(PDO::FETCH_ASSOC) as $m) {
        $d = json_decode((string) $m['donnees'], true) ?: [];
        foreach ($anciens as $k) {
            $d[$k] ??= (string) $m[$k];
        }
        $maj->execute([json_encode($d, JSON_UNESCAPED_UNICODE), $m['id']]);
    }
}

/**
 * Campagnes, services, témoignages et logos quittent leurs tables pour la
 * table commune `elements`. Les campagnes gardent leur numéro : les liens
 * `/campagnes#campagne-3` déjà partagés continuent d'ouvrir la bonne fiche.
 * Les anciennes tables sont gardées sous le nom `archive_…`.
 */
function migration_lyd_elements(PDO $pdo): void
{
    $lire = fn(string $t): array => table_existe($pdo, $t) ? $pdo->query("SELECT * FROM $t ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) : [];
    $json = fn(?string $s): array => is_array($v = json_decode((string) $s, true)) ? $v : [];
    $ajouter = function (string $col, array $r, array $donnees, array $en, int $visible, ?int $id = null) use ($pdo): void {
        $st = $pdo->prepare('INSERT INTO elements (' . ($id !== null ? 'id, ' : '') . 'collection, donnees, en, ordre, visible, cree_le, modifie_le) VALUES ('
            . ($id !== null ? '?, ' : '') . '?, ?, ?, ?, ?, ?, ?)');
        $st->execute(array_merge($id !== null ? [$id] : [], [$col, json_net($donnees), json_net(array_filter($en, 'rempli')), (int) ($r['ordre'] ?? 0),
            $visible, $r['cree_le'] ?? $r['modifie_le'] ?? maintenant(), $r['modifie_le'] ?? maintenant()]));
    };

    foreach ($lire('campagnes') as $c) {
        $paires = array_chunk(json_liste($c['resultats']), 2);
        $en = $json($c['en']);
        // L'anglais rangeait les résultats à plat (« +340% », « Engagement », …) : seuls les libellés comptent.
        $libelles_en = isset($en['resultats']) ? array_map(fn($p) => (string) ($p[1] ?? ''), array_chunk((array) $en['resultats'], 2)) : (array) ($en['libelles'] ?? []);
        $resultats = [];
        $resultats_en = [];
        foreach ($paires as $i => $p) {
            $resultats[] = ['valeur' => (string) ($p[0] ?? ''), 'libelle' => (string) ($p[1] ?? '')];
            $l = trim((string) ($libelles_en[$i] ?? ''));
            $resultats_en[] = $l !== '' && $l !== (string) ($p[1] ?? '') ? ['libelle' => $l] : [];
        }
        while ($resultats_en && end($resultats_en) === []) {
            array_pop($resultats_en);
        }
        $ajouter('campagnes', $c, [
            'titre' => $c['titre'], 'client' => $c['client'], 'annee' => $c['annee'], 'categorie' => $c['categorie'],
            'extrait' => $c['extrait'], 'description' => $c['description'], 'resultats' => $resultats,
            'tags' => json_liste($c['tags']), 'vedette' => (int) $c['vedette'], 'image' => $c['image'],
        ], [
            'titre' => $en['titre'] ?? '', 'extrait' => $en['extrait'] ?? '', 'description' => $en['description'] ?? '',
            'tags' => (array) ($en['tags'] ?? []), 'resultats' => $resultats_en,
        ], (int) $c['publie'], (int) $c['id']);
    }
    foreach ($lire('services') as $s) {
        $ajouter('services', $s, $json($s['fr']) + [
            'ancre' => $s['ancre'], 'numero' => $s['numero'], 'icone' => $s['icone'], 'image' => $s['image'],
            'vedette' => (int) $s['vedette'], 'detail' => (int) $s['detail'],
        ], $json($s['en']), (int) $s['visible']);
    }
    foreach ($lire('temoignages') as $t) {
        $ajouter('temoignages', $t, $json($t['fr']) + ['entreprise' => $t['entreprise'], 'note' => (int) $t['note']], $json($t['en']), (int) $t['visible']);
    }
    foreach ($lire('clients') as $c) {
        $ajouter('clients', $c, ['nom' => $c['nom'], 'logo' => $c['logo'], 'lien' => $c['lien']], [], (int) $c['visible']);
    }
    foreach (['campagnes', 'services', 'temoignages', 'clients'] as $t) {
        if (table_existe($pdo, $t)) {
            $pdo->exec("ALTER TABLE $t RENAME TO archive_$t");
        }
    }
}
