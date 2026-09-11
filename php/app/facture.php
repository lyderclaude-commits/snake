<?php
/**
 * La facture : le document, son identité légale, et son envoi.
 *
 * `abonnement.php` tient les échéances — jusqu'à quand un compte est payé,
 * quand le prévenir, quand le redescendre. Ce fichier-ci tient ce qu'on
 * REMET au client : un document numéroté qu'il présente à sa comptabilité.
 *
 * DEUX RÈGLES QUI COMMANDENT TOUT LE RESTE
 *
 * 1. **Une facture est figée à l'émission.** Le nom du client, le montant,
 *    le taux de TVA et l'identité complète de l'émetteur y sont RECOPIÉS.
 *    Déménager l'an prochain, changer de taux, corriger une raison sociale
 *    ne doit pas réécrire un document déjà remis : ce serait un papier qui
 *    ne dit plus ce qu'il disait le jour où on l'a donné.
 *
 * 2. **Une facture ne se supprime jamais.** On l'annule par un AVOIR, qui
 *    porte son propre numéro, la désigne, et porte le montant en négatif.
 *    C'est la règle comptable partout, et c'est aussi la seule façon de
 *    garder une numérotation continue — une suite trouée est le premier
 *    signe qu'un contrôleur regarde de plus près.
 */

declare(strict_types=1);

/** Ce qu'une facture peut être. */
const FACTURE_STATUTS = [
    'a_regler' => 'À régler',
    'reglee'   => 'Réglée',
    'annulee'  => 'Annulée',
];

/** Comment on a été payé. Le logiciel n'encaisse pas : il note. */
const FACTURE_MODES = [
    'mobile_money' => 'Mobile Money',
    'virement'     => 'Virement bancaire',
    'especes'      => 'Espèces',
    'cheque'       => 'Chèque',
    'autre'        => 'Autre',
];

/**
 * L'identité de l'émetteur, saisie une fois dans Système.
 *
 * Sans elle, la facture porte « Wakabi Boost · Lomé » et rien d'autre :
 * un comptable la refuse, et l'organisateur rappelle. Ces champs sont
 * volontairement libres plutôt que contraints — les mentions obligatoires
 * ne sont pas les mêmes au Togo, au Bénin et en Côte d'Ivoire.
 */
const FACTURATION_DEFAUTS = [
    'fact_raison'    => 'Wakabi Boost',
    'fact_forme'     => '',
    'fact_adresse'   => 'Lomé, Togo',
    'fact_rccm'      => '',
    'fact_nif'       => '',
    'fact_capital'   => '',
    'fact_telephone' => '',
    'fact_courriel'  => '',
    /** Le taux en CENTIÈMES de point : 1800 vaut 18 %. Zéro : non assujetti. */
    'fact_tva'       => '1800',
    'fact_devise'    => 'F CFA',
    'fact_pied'      => 'Prestation de service dématérialisée, exécutée en totalité sur la période '
                      . 'indiquée. En cas de retard de paiement, aucune pénalité n’est appliquée : '
                      . 'l’accès repasse à l’offre gratuite sept jours après l’échéance.',
    'fact_mention_tva' => 'TVA non applicable.',
];

function facturation_reglages(): array
{
    $lus = reglages_bdd(array_keys(FACTURATION_DEFAUTS));
    $r = FACTURATION_DEFAUTS;
    foreach ($r as $cle => $defaut) {
        $v = (string) ($lus[$cle] ?? '');
        $r[$cle] = $v === '' ? $defaut : $v;
    }
    $r['fact_tva'] = (string) max(0, min(10000, (int) $r['fact_tva']));
    return $r;
}

function facturation_reglages_poser(array $valeurs): void
{
    $garde = [];
    foreach (FACTURATION_DEFAUTS as $cle => $_) {
        if (array_key_exists($cle, $valeurs)) {
            $garde[$cle] = trim((string) $valeurs[$cle]);
        }
    }
    if (isset($garde['fact_tva'])) {
        $garde['fact_tva'] = (string) max(0, min(10000, (int) round((float) str_replace(',', '.', $garde['fact_tva']) * 100)));
    }
    if ($garde) {
        reglages_bdd_poser($garde);
    }
}

/**
 * Un montant TTC, décomposé.
 *
 * Les prix affichés sur la vitrine sont TTC : c'est ce que le client paie,
 * et c'est le nombre dont il se souvient. La facture doit pourtant montrer
 * la base hors taxes et la taxe, sans quoi elle n'est pas une facture. On
 * remonte donc de l'un à l'autre, et on écrit la TVA par DIFFÉRENCE : la
 * somme des trois lignes doit tomber juste au franc près, ce qu'un arrondi
 * fait à part ne garantit pas.
 *
 * @return array{ht: int, tva: int, ttc: int, taux: int}
 */
function facture_montants(int $ttc, int $taux): array
{
    if ($taux <= 0) {
        return ['ht' => $ttc, 'tva' => 0, 'ttc' => $ttc, 'taux' => 0];
    }
    $ht = (int) round($ttc * 10000 / (10000 + $taux));
    return ['ht' => $ht, 'tva' => $ttc - $ht, 'ttc' => $ttc, 'taux' => $taux];
}

/**
 * Un mois en toutes lettres, en français.
 *
 * `gmdate('F')` rend « September » quel que soit le pays du serveur : la
 * locale d'un mutualisé n'est pas réglable, et `strftime` a disparu de
 * PHP 8.3. Douze mots écrits ici coûtent moins qu'une dépendance.
 */
function mois_fr(string $iso): string
{
    static $mois = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
                    'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $t = strtotime($iso);
    return $t ? $mois[(int) gmdate('n', $t)] . ' ' . gmdate('Y', $t) : '';
}

/** Un montant, écrit comme on l'écrit ici : « 12 000 F CFA ». */
function montant_fr(int $n, string $devise = ''): string
{
    $signe = $n < 0 ? '−' : '';
    $s = $signe . number_format(abs($n), 0, ',', ' ');
    return $devise === '' ? $s : $s . ' ' . $devise;
}

/* ------------------------------------------------------------------ */
/* Les factures                                                        */
/* ------------------------------------------------------------------ */

/**
 * Un numéro lisible et croissant : `WB-2026-0007`.
 *
 * Une facture se cite au téléphone et se recopie à la main. Un
 * identifiant aléatoire de trente-six caractères ne se cite pas.
 */
function facture_numero(): string
{
    $annee = gmdate('Y');
    $s = db()->prepare("SELECT numero FROM factures WHERE numero LIKE ? ORDER BY numero DESC LIMIT 1");
    $s->execute(['WB-' . $annee . '-%']);
    $dernier = (string) ($s->fetchColumn() ?: '');
    $n = $dernier !== '' ? ((int) substr($dernier, -4)) + 1 : 1;
    return sprintf('WB-%s-%04d', $annee, $n);
}

/** Les factures d'un compte, la plus récente d'abord. */
function factures_de(string $utilisateur_id): array
{
    $s = db()->prepare('SELECT * FROM factures WHERE utilisateur_id = ? ORDER BY cree_le DESC LIMIT 50');
    $s->execute([$utilisateur_id]);
    return $s->fetchAll();
}

function facture_par_id(string $id): ?array
{
    $s = db()->prepare('SELECT * FROM factures WHERE id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

/* ------------------------------------------------------------------ */
/* Émettre                                                             */
/* ------------------------------------------------------------------ */

/**
 * Émet une facture, et rend son identifiant.
 *
 * `$options` porte ce qui varie : `statut`, `mode`, `reference`, `note`,
 * `montant`. Tout le reste est recopié depuis le client et les réglages.
 */
function facture_poser(array $client, string $debut, string $fin, ?array $par = null, array $options = []): string
{
    $reglages = facturation_reglages();
    $formule = (string) ($client['formule'] ?? 'decouverte');
    $ttc = (int) ($options['montant'] ?? (FORMULES[$formule]['prix'] ?? 0));
    $m = facture_montants($ttc, (int) $reglages['fact_tva']);
    $statut = isset(FACTURE_STATUTS[$options['statut'] ?? '']) ? (string) $options['statut'] : 'reglee';

    $id = nouvel_id();
    db()->prepare('INSERT INTO factures
        (id, numero, utilisateur_id, client_nom, client_org, formule, montant,
         debut_le, fin_le, reglee_le, note, emise_par, statut, mode, reference,
         avoir_de, tva_taux, montant_ht, montant_tva, emetteur, envoyee_le, cree_le)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([
            $id, facture_numero(), $client['id'],
            $client['nom'] ?? null, $client['organisation'] ?? null,
            $formule, $m['ttc'], $debut, $fin,
            $statut === 'reglee' ? maintenant() : null,
            ($options['note'] ?? '') !== '' ? (string) $options['note'] : null,
            $par['id'] ?? null, $statut,
            isset(FACTURE_MODES[$options['mode'] ?? '']) ? (string) $options['mode'] : null,
            ($options['reference'] ?? '') !== '' ? (string) $options['reference'] : null,
            null, $m['taux'], $m['ht'], $m['tva'],
            json_encode($reglages, JSON_UNESCAPED_UNICODE), null, maintenant(),
        ]);
    return $id;
}

/**
 * L'ancienne signature, gardée telle quelle.
 *
 * `?p=paiement` l'appelle depuis la fiche organisateur, et un appel qui
 * change de forme au milieu d'une refonte est une panne qu'on découvre en
 * production. Elle délègue.
 */
function facture_emettre(array $client, string $debut, string $fin, ?array $par = null, ?int $montant = null): string
{
    return facture_poser($client, $debut, $fin, $par, [
        'montant' => $montant ?? (int) (FORMULES[$client['formule'] ?? '']['prix'] ?? 0),
        'statut' => 'reglee',
    ]);
}

/** Marque une facture réglée, avec le mode et la référence du versement. */
function facture_regler(string $id, string $mode = '', string $reference = ''): void
{
    db()->prepare('UPDATE factures SET statut = ?, reglee_le = ?, mode = ?, reference = ?
                   WHERE id = ? AND statut <> ?')
        ->execute([
            'reglee', maintenant(),
            isset(FACTURE_MODES[$mode]) ? $mode : null,
            $reference !== '' ? $reference : null,
            $id, 'annulee',
        ]);
}

/**
 * Annule une facture par un avoir, et rend l'identifiant de l'avoir.
 *
 * L'avoir reprend tout de la facture annulée — client, période, offre,
 * taux — en négatif. La facture d'origine passe en « annulée » mais reste
 * lisible : c'est ce couple de documents qui explique le trou, et non son
 * absence.
 */
function facture_avoir(string $id, ?array $par = null, string $motif = ''): ?string
{
    $f = facture_par_id($id);
    if (!$f || $f['statut'] === 'annulee' || ($f['avoir_de'] ?? null)) {
        return null;
    }
    $avoir = nouvel_id();
    db()->prepare('INSERT INTO factures
        (id, numero, utilisateur_id, client_nom, client_org, formule, montant,
         debut_le, fin_le, reglee_le, note, emise_par, statut, mode, reference,
         avoir_de, tva_taux, montant_ht, montant_tva, emetteur, envoyee_le, cree_le)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')
        ->execute([
            $avoir, facture_numero(), $f['utilisateur_id'], $f['client_nom'], $f['client_org'],
            $f['formule'], -((int) $f['montant']), $f['debut_le'], $f['fin_le'],
            maintenant(), $motif !== '' ? $motif : null, $par['id'] ?? null,
            'reglee', null, null, $f['id'],
            (int) $f['tva_taux'], -((int) $f['montant_ht']), -((int) $f['montant_tva']),
            $f['emetteur'], null, maintenant(),
        ]);
    db()->prepare('UPDATE factures SET statut = ? WHERE id = ?')->execute(['annulee', $id]);
    return $avoir;
}

/** L'identité de l'émetteur telle qu'elle était le jour de l'émission. */
function facture_emetteur(array $f): array
{
    $lu = $f['emetteur'] ? json_decode((string) $f['emetteur'], true) : null;
    return is_array($lu) ? $lu + FACTURATION_DEFAUTS : facturation_reglages();
}

/** Le nom du fichier remis au client : citable, triable, sans accent. */
function facture_nom_fichier(array $f): string
{
    return 'facture-' . strtolower(preg_replace('/[^A-Za-z0-9-]+/', '-', (string) $f['numero']) ?: 'wakabi') . '.pdf';
}

/* ------------------------------------------------------------------ */
/* Le document                                                         */
/* ------------------------------------------------------------------ */

/**
 * La facture en PDF, prête à télécharger ou à joindre.
 *
 * Une seule page, un seul chemin de lecture : qui émet, pour qui, quoi,
 * combien, réglé ou non. L'ordre est celui que cherche l'œil d'un
 * comptable, et les montants sont alignés à droite parce que c'est ainsi
 * qu'on vérifie une addition.
 */
function facture_pdf(array $f): string
{
    $e = facture_emetteur($f);
    $avoir = (bool) ($f['avoir_de'] ?? null);
    $devise = (string) ($e['fact_devise'] ?? 'F CFA');
    $pdf = new EcrivainPdf(($avoir ? 'Avoir ' : 'Facture ') . $f['numero']);
    $pdf->page();

    $g = 18.0;              // marge de gauche
    $d = PDF_L - 18.0;      // bord droit du contenu
    $y = 20.0;

    /* ---- l'en-tête : qui émet, et quel document ---- */
    $logo = RACINE . '/public/logo.png';
    $bas_logo = $y;
    if ($pdf->image_png('logo', $logo, 13)) {
        $bas_logo = $pdf->image('logo', $g, $y - 2, 13);
    }
    $pdf->texte($g + ($bas_logo > $y ? 16 : 0), $y + 4, (string) $e['fact_raison'], 15, true);
    $yg = max($bas_logo, $y + 7) + 4;
    foreach (array_filter([
        trim(($e['fact_forme'] ?? '') . ' ' . ($e['fact_capital'] ?? '')),
        (string) ($e['fact_adresse'] ?? ''),
        trim(implode(' · ', array_filter([
            ($e['fact_rccm'] ?? '') !== '' ? 'RCCM ' . $e['fact_rccm'] : '',
            ($e['fact_nif'] ?? '') !== '' ? 'NIF ' . $e['fact_nif'] : '',
        ]))),
        trim(implode(' · ', array_filter([
            (string) ($e['fact_telephone'] ?? ''), (string) ($e['fact_courriel'] ?? ''),
        ]))),
    ], fn(string $l): bool => trim($l) !== '') as $ligne) {
        $pdf->texte($g, $yg, $ligne, 8, false, '#475569');
        $yg += 3.8;
    }

    $pdf->texte($d, $y + 2, $avoir ? 'AVOIR' : 'FACTURE', 11, true, '#94A3B8', 'droite');
    $pdf->texte($d, $y + 9, (string) $f['numero'], 16, true, '#0F172A', 'droite');
    $pdf->texte($d, $y + 14.5, 'Émise le ' . date_fr((string) $f['cree_le']), 8.5, false, '#475569', 'droite');
    $statut = (string) ($f['statut'] ?? 'reglee');
    $pdf->texte($d, $y + 19, FACTURE_STATUTS[$statut] ?? '', 9, true,
        $statut === 'reglee' ? '#0D9488' : ($statut === 'annulee' ? '#94A3B8' : '#C2410C'), 'droite');

    $y = max($yg, $y + 22) + 4;
    $pdf->filet($g, $y, $d, $y, 0.5, '#0F172A');
    $y += 9;

    /* ---- les deux parties ---- */
    $milieu = $g + ($d - $g) / 2 + 6;
    $pdf->texte($g, $y, 'FACTURÉ À', 7.5, true, '#94A3B8');
    $pdf->texte($milieu, $y, 'PÉRIODE COUVERTE', 7.5, true, '#94A3B8');
    $y += 5;
    $pdf->texte($g, $y, (string) ($f['client_org'] ?: $f['client_nom']), 10.5, true);
    $pdf->texte($milieu, $y, date_fr((string) $f['debut_le']) . ' au ' . date_fr((string) $f['fin_le']), 10, false);
    $y += 4.6;
    if ($f['client_org'] && $f['client_nom']) {
        $pdf->texte($g, $y, (string) $f['client_nom'], 9, false, '#475569');
        $y += 4.2;
    }
    if ($avoir) {
        $origine = facture_par_id((string) $f['avoir_de']);
        $pdf->texte($milieu, $y, 'Annule la facture ' . ($origine['numero'] ?? ''), 9, false, '#475569');
    }
    $y += 8;

    /* ---- la ligne facturée ---- */
    $taux = (int) ($f['tva_taux'] ?? 0);
    $pdf->pave($g, $y, $d - $g, 8, '#F1F5FC');
    $pdf->texte($g + 3, $y + 5.6, 'DÉSIGNATION', 7.5, true, '#475569');
    // Avec TVA, la ligne porte le HORS TAXES : c'est la somme des lignes qui
    // doit faire le total hors taxes, sans quoi l'addition ne se vérifie pas.
    $pdf->texte($d - 3, $y + 5.6, $taux > 0 ? 'MONTANT HT' : 'MONTANT', 7.5, true, '#475569', 'droite');
    $y += 12;

    $pdf->texte($g + 3, $y, 'Offre ' . formule_libelle((string) $f['formule']), 10, true);
    $pdf->texte($d - 3, $y, montant_fr((int) ($taux > 0 ? $f['montant_ht'] : $f['montant'])),
        10, false, '#0F172A', 'droite');
    $y += 4.4;
    $pdf->texte($g + 3, $y, 'Abonnement du ' . date_fr((string) $f['debut_le'])
        . ' au ' . date_fr((string) $f['fin_le']), 8.5, false, '#475569');
    $y += 6;
    $pdf->filet($g, $y, $d, $y, 0.2, '#E2E8F5');
    $y += 7;

    /* ---- les totaux, calés à droite ---- */
    $gauche_total = $d - 62;
    if ($taux > 0) {
        $pdf->texte($gauche_total, $y, 'Total hors taxes', 9, false, '#475569');
        $pdf->texte($d - 3, $y, montant_fr((int) $f['montant_ht']), 9.5, false, '#0F172A', 'droite');
        $y += 5;
        $pdf->texte($gauche_total, $y, 'TVA ' . rtrim(rtrim(number_format($taux / 100, 2, ',', ' '), '0'), ',') . ' %',
            9, false, '#475569');
        $pdf->texte($d - 3, $y, montant_fr((int) $f['montant_tva']), 9.5, false, '#0F172A', 'droite');
        $y += 6;
    }
    $pdf->pave($gauche_total - 4, $y - 4.5, $d - $gauche_total + 4, 10, '#0F172A');
    $pdf->texte($gauche_total, $y + 1.8, $avoir ? 'Total de l’avoir' : 'Total', 9.5, true, '#FFFFFF');
    $pdf->texte($d - 3, $y + 1.8, montant_fr((int) $f['montant'], $devise), 11, true, '#FFFFFF', 'droite');
    $y += 14;

    if ($taux <= 0 && ($e['fact_mention_tva'] ?? '') !== '') {
        $pdf->texte($gauche_total, $y, (string) $e['fact_mention_tva'], 8, false, '#475569');
        $y += 6;
    }

    /* ---- le règlement ---- */
    $reglement = match (true) {
        $avoir => 'Avoir établi le ' . date_fr((string) $f['cree_le'])
                . (($f['note'] ?? '') !== '' ? ' : ' . $f['note'] : '') . '.',
        $statut === 'reglee' => 'Réglée le ' . date_fr((string) ($f['reglee_le'] ?: $f['cree_le']))
            . (($f['mode'] ?? null) ? ' par ' . (FACTURE_MODES[$f['mode']] ?? $f['mode']) : '')
            . (($f['reference'] ?? '') !== '' ? ', réf. ' . $f['reference'] : '')
            . '. Ce document vaut reçu.',
        $statut === 'annulee' => 'Facture annulée. Voir l’avoir qui s’y rapporte.',
        default => 'En attente de règlement, à réception.',
    };
    $y = $pdf->paragraphe($g, $y, $d - $g, $reglement, 9, 4.4, false, '#0F172A') + 2;
    if (($f['note'] ?? '') !== '' && !$avoir) {
        $y = $pdf->paragraphe($g, $y, $d - $g, (string) $f['note'], 8.5, 4.2) + 2;
    }

    /* ---- le pied : les mentions, tout en bas de la page ---- */
    $pied = PDF_H - 28;
    $pdf->filet($g, $pied, $d, $pied, 0.2, '#E2E8F5');
    $identite = trim(implode(', ', array_filter([
        (string) $e['fact_raison'],
        trim(($e['fact_forme'] ?? '') . ' ' . ($e['fact_capital'] ?? '')),
        ($e['fact_rccm'] ?? '') !== '' ? 'RCCM ' . $e['fact_rccm'] : '',
        ($e['fact_nif'] ?? '') !== '' ? 'NIF ' . $e['fact_nif'] : '',
    ], fn(string $x): bool => trim($x) !== '')));
    $pdf->paragraphe($g, $pied + 5, $d - $g,
        trim($identite . '. ' . (string) ($e['fact_pied'] ?? '')), 7.5, 3.4, false, '#94A3B8');

    return $pdf->rendu();
}

/* ------------------------------------------------------------------ */
/* L'envoi                                                             */
/* ------------------------------------------------------------------ */

/**
 * Envoie la facture au client, le document JOINT.
 *
 * Un lien vers un écran demande de se connecter, et quelqu'un qui transmet
 * la facture à sa comptabilité ne transmet pas ses identifiants. Le PDF
 * part donc en pièce jointe, comme n'importe quelle facture depuis vingt
 * ans, et le lien reste dans le message pour qui préfère.
 *
 * @return array{ok: bool, message: string}
 */
function facture_envoyer(array $f, ?array $client = null): array
{
    $client ??= utilisateur_par_id((string) $f['utilisateur_id']);
    if (!$client || ($client['email'] ?? '') === '') {
        return ['ok' => false, 'message' => 'Ce compte n’a pas d’adresse e-mail.'];
    }
    if (!courriel_branche()) {
        return ['ok' => false, 'message' => 'Le transport e-mail est éteint : réglez-le d’abord.'];
    }

    $avoir = (bool) ($f['avoir_de'] ?? null);
    $titre = ($avoir ? 'Avoir ' : 'Facture ') . $f['numero'];
    $corps = $avoir
        ? 'Vous trouverez ci-joint l’avoir ' . $f['numero'] . ', qui annule la facture '
          . 'correspondante. Rien ne vous est demandé.'
        : 'Vous trouverez ci-joint votre facture ' . $f['numero'] . ' pour l’offre '
          . formule_libelle((string) $f['formule']) . ', du ' . date_fr((string) $f['debut_le'])
          . ' au ' . date_fr((string) $f['fin_le']) . '. '
          . (($f['statut'] ?? '') === 'reglee'
              ? 'Elle est réglée : ce document vaut reçu.'
              : 'Elle est à régler à réception.');

    $r = courriel_mis_en_page(
        (string) $client['email'],
        (string) ($client['nom'] ?? ''),
        $titre,
        $titre,
        $corps,
        base_url() . '/index.php?p=facturation',
        'Voir mes factures',
        [],
        null,
        [['nom' => facture_nom_fichier($f), 'type' => 'application/pdf', 'contenu' => facture_pdf($f)]]
    );
    if (!empty($r['ok'])) {
        db()->prepare('UPDATE factures SET envoyee_le = ? WHERE id = ?')
            ->execute([maintenant(), $f['id']]);
    }
    return ['ok' => (bool) ($r['ok'] ?? false), 'message' => (string) ($r['message'] ?? '')];
}

/* ------------------------------------------------------------------ */
/* Les vues de la facturation                                          */
/* ------------------------------------------------------------------ */

/**
 * L'état d'un abonnement, dit d'un seul mot.
 *
 * Les cinq cas sont exclusifs et couvrent tout : pas d'offre payante, pas
 * d'échéance posée, échu au-delà du délai de grâce, en retard dedans, ou
 * en cours. L'écran n'a plus qu'à les afficher.
 *
 * @return array{cle: string, libelle: string, ton: string, jours: ?int}
 */
function abonnement_etat(array $u): array
{
    if (!abonnement_suivi($u)) {
        return ['cle' => 'gratuit', 'libelle' => 'Offre gratuite', 'ton' => 'brouillon', 'jours' => null];
    }
    $j = jours_restants($u);
    if ($j === null) {
        return ['cle' => 'sans_echeance', 'libelle' => 'Sans échéance', 'ton' => 'brouillon', 'jours' => null];
    }
    if ($j < -ABONNEMENT_GRACE) {
        return ['cle' => 'echu', 'libelle' => 'Échu', 'ton' => 'refuse', 'jours' => $j];
    }
    if ($j < 0) {
        return ['cle' => 'retard', 'libelle' => 'En retard de ' . abs($j) . ' j', 'ton' => 'refuse', 'jours' => $j];
    }
    if ($j <= 7) {
        return ['cle' => 'proche', 'libelle' => $j <= 1 ? 'Demain' : $j . ' jours', 'ton' => 'corrections', 'jours' => $j];
    }
    return ['cle' => 'actif', 'libelle' => $j . ' jours', 'ton' => 'publie', 'jours' => $j];
}

/**
 * Tous les comptes clients, avec leur abonnement, triés PAR URGENCE.
 *
 * Personne n'ouvre cet écran pour chercher un nom : on l'ouvre le 1er du
 * mois pour savoir qui relancer. L'ordre alphabétique obligerait à lire
 * les sept lignes ; celui-ci met la réponse en haut.
 */
function facturation_lignes(): array
{
    $rang = ['echu' => 0, 'retard' => 1, 'proche' => 2, 'sans_echeance' => 3, 'actif' => 4, 'gratuit' => 5];
    $lignes = [];
    foreach (utilisateurs_clients() as $u) {
        $etat = abonnement_etat($u);
        $lignes[] = [
            'compte' => $u,
            'etat' => $etat,
            'prix' => (int) (FORMULES[$u['formule'] ?? '']['prix'] ?? 0),
            'derniere' => facture_derniere((string) $u['id']),
            /**
             * La date de début, avec son filet de sécurité.
             *
             * La colonne `abonne_depuis` n'existe que depuis la v19 : les
             * comptes abonnés avant elle ne l'ont pas. Plutôt qu'un point
             * d'interrogation, on lit alors la plus ancienne facture — ce
             * que la migration fait aussi, mais elle ne peut rien pour un
             * compte qui n'a jamais été facturé.
             */
            'depuis' => ($u['abonne_depuis'] ?? '') ?: facture_premiere_debut((string) $u['id']),
        ];
    }
    usort($lignes, function (array $a, array $b) use ($rang): int {
        $ra = $rang[$a['etat']['cle']] ?? 9;
        $rb = $rang[$b['etat']['cle']] ?? 9;
        return $ra <=> $rb
            ?: (($a['etat']['jours'] ?? 99999) <=> ($b['etat']['jours'] ?? 99999))
            ?: strcasecmp((string) $a['compte']['nom'], (string) $b['compte']['nom']);
    });
    return $lignes;
}

/** Les comptes clients : ni l'équipe, ni les simples participants. */
function utilisateurs_clients(): array
{
    $trous = implode(',', array_fill(0, count(ROLES_INTERNES), '?'));
    $s = db()->prepare("SELECT * FROM utilisateurs
        WHERE role NOT IN ($trous) AND role <> 'participant' ORDER BY nom");
    $s->execute(ROLES_INTERNES);
    return $s->fetchAll();
}

/** Le début de la plus ancienne facture d'un compte, ou une chaîne vide. */
function facture_premiere_debut(string $utilisateur_id): string
{
    $s = db()->prepare('SELECT debut_le FROM factures WHERE utilisateur_id = ?
                        ORDER BY cree_le LIMIT 1');
    $s->execute([$utilisateur_id]);
    return (string) ($s->fetchColumn() ?: '');
}

/** La dernière facture d'un compte, avoirs compris. */
function facture_derniere(string $utilisateur_id): ?array
{
    $s = db()->prepare('SELECT * FROM factures WHERE utilisateur_id = ? ORDER BY cree_le DESC LIMIT 1');
    $s->execute([$utilisateur_id]);
    return $s->fetch() ?: null;
}

/**
 * Le bandeau de tête : ce qu'on veut savoir avant de lire une seule ligne.
 *
 * Les avoirs comptent en NÉGATIF dans l'encaissé — c'est tout leur objet.
 * Les factures annulées, elles, n'y sont plus du tout.
 *
 * @return array{encaisse: int, attendu: int, recurrent: int, proches: int,
 *               proches_montant: int, retards: int, retards_montant: int}
 */
function facturation_bilan(array $lignes): array
{
    $debut = gmdate('Y-m-01\T00:00:00\Z');
    $s = db()->prepare("SELECT COALESCE(SUM(montant),0) FROM factures
                        WHERE statut = 'reglee' AND cree_le >= ?");
    $s->execute([$debut]);
    $encaisse = (int) $s->fetchColumn();

    $a = db()->query("SELECT COALESCE(SUM(montant),0) FROM factures WHERE statut = 'a_regler'");
    $attendu = (int) $a->fetchColumn();

    $bilan = ['encaisse' => $encaisse, 'attendu' => $attendu, 'recurrent' => 0,
              'proches' => 0, 'proches_montant' => 0, 'retards' => 0, 'retards_montant' => 0];
    foreach ($lignes as $l) {
        $cle = $l['etat']['cle'];
        if (in_array($cle, ['actif', 'proche', 'retard'], true)) {
            $bilan['recurrent'] += $l['prix'];
        }
        if ($cle === 'proche') {
            $bilan['proches']++;
            $bilan['proches_montant'] += $l['prix'];
        }
        if ($cle === 'retard' || $cle === 'echu') {
            $bilan['retards']++;
            $bilan['retards_montant'] += $l['prix'];
        }
    }
    return $bilan;
}

/** Les factures du mois, pour l'écran de l'équipe et pour l'export. */
function factures_du_mois(?string $mois = null): array
{
    $mois ??= gmdate('Y-m');
    $s = db()->prepare('SELECT * FROM factures WHERE cree_le >= ? AND cree_le < ? ORDER BY numero DESC');
    $debut = $mois . '-01T00:00:00Z';
    $fin = gmdate('Y-m-01\T00:00:00\Z', strtotime($mois . '-01 +1 month') ?: time());
    $s->execute([$debut, $fin]);
    return $s->fetchAll();
}

/**
 * L'export du comptable : une ligne par document, séparées par des
 * points-virgules parce que c'est ce qu'attend un tableur francophone.
 */
function factures_csv(array $factures): string
{
    $out = "\xEF\xBB\xBF" . implode(';', [
        'Numero', 'Date', 'Statut', 'Client', 'Structure', 'Offre',
        'Debut', 'Fin', 'HT', 'TVA', 'TTC', 'Taux', 'Mode', 'Reference',
    ]) . "\r\n";
    foreach ($factures as $f) {
        $out .= implode(';', array_map(
            fn($v): string => '"' . str_replace('"', '""', (string) $v) . '"',
            [
                $f['numero'], date_fr((string) $f['cree_le']),
                FACTURE_STATUTS[$f['statut'] ?? 'reglee'] ?? '',
                $f['client_nom'], $f['client_org'], formule_libelle((string) $f['formule']),
                date_fr((string) $f['debut_le']), date_fr((string) $f['fin_le']),
                (int) $f['montant_ht'], (int) $f['montant_tva'], (int) $f['montant'],
                number_format((int) $f['tva_taux'] / 100, 2, ',', ''),
                FACTURE_MODES[$f['mode'] ?? ''] ?? '', $f['reference'],
            ]
        )) . "\r\n";
    }
    return $out;
}
