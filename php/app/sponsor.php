<?php
/**
 * Le sponsor d'un décor, et le document qu'on lui remet.
 *
 * Un organisateur ne vend pas un logo : il vend une audience. Le produit
 * sait déjà la compter — il lui manquait de savoir à qui elle appartient.
 * Trois colonnes sur le décor (un nom, un logo, un lien), et le rapport de
 * l'an dernier devient l'argument de vente de l'an prochain.
 *
 * TROIS DÉCISIONS
 *
 *  1. **Le document compte des occasions de voir, pas des regards.** Il le
 *     dit lui-même, en toutes lettres, sur sa seule page. Écrire « 2 140
 *     personnes ont vu votre logo » serait faux, et se retournerait contre
 *     l'organisateur le jour où le sponsor pose la question. Les 298
 *     présences, elles, sont comptées une par une à la porte : c'est le
 *     seul nombre du document qui prouve une personne.
 *
 *  2. **Le lien du sponsor est un lien court, donc il se compte.** C'est
 *     le seul chiffre qui prouve un GESTE, et pas seulement une présence à
 *     l'écran. Il passe par la table `liens`, comme tous les autres, et
 *     son compteur est celui que l'organisateur voit déjà.
 *
 *  3. **Un lien de sponsor est relu par la maison.** Il mène chez le
 *     sponsor, donc hors de nos domaines : le garde-fou de redirection ne
 *     peut pas s'y appliquer tel quel, et le laisser passer sans rien
 *     rouvrirait la porte que ce garde-fou ferme partout ailleurs. Alors
 *     il suit le chemin de tout ce qu'un organisateur publie sous notre
 *     nom : la relecture. Le nom et le logo s'affichent tout de suite ; le
 *     lien devient cliquable une fois relu.
 *
 * CE QUE LE DOCUMENT NE DIT PAS
 *
 * Il ne dit pas « 486 badges portant votre logo ». Le bloc du sponsor est
 * sur la PAGE du décor, pas sur l'image du badge : l'affirmer serait
 * exactement la faute que la décision 1 cherche à éviter. Le jour où le
 * logo entrera dans le gabarit, ce nombre-là deviendra vrai, et pas avant.
 */

declare(strict_types=1);

const SPONSOR_STATUTS = [
    'a_relire' => 'Lien en relecture',
    'valide' => 'Lien en service',
];

/** Le bloc sponsor d'un décor, ou null quand il n'y en a pas. */
function sponsor_de(array $decor): ?array
{
    $nom = trim((string) ($decor['sponsor_nom'] ?? ''));
    if ($nom === '') {
        return null;
    }
    $statut = (string) ($decor['sponsor_statut'] ?? 'a_relire');
    $code = trim((string) ($decor['sponsor_code'] ?? ''));
    return [
        'nom' => $nom,
        'logo' => trim((string) ($decor['sponsor_logo'] ?? '')),
        'lien' => trim((string) ($decor['sponsor_lien'] ?? '')),
        'statut' => $statut,
        'code' => $code,
        /**
         * L'adresse à cliquer n'est le lien court QUE s'il est relu.
         *
         * Tant qu'il ne l'est pas, il n'y a pas d'adresse du tout : ni le
         * lien court, qui porterait notre domaine, ni l'original, qui
         * reviendrait au même pour le lecteur. Le bloc reste, muet.
         */
        'url' => ($statut === 'valide' && $code !== '') ? lien_court_url($code) : '',
    ];
}

/**
 * Enregistre le sponsor d'un décor.
 *
 * Le lien est relu, SAUF quand il mène chez nous ou quand c'est la maison
 * qui l'a posé : dans ces deux cas la question ne se pose pas, et faire
 * attendre un membre de l'équipe pour sa propre saisie serait une
 * cérémonie sans objet.
 *
 * @param array{nom:string, logo:string, lien:string} $champs
 * @return array{ok:bool, message:string, relire:bool}
 */
function sponsor_enregistrer(array $decor, array $u, array $champs): array
{
    $nom = trim($champs['nom']);
    $lien = trim($champs['lien']);
    $logo = trim($champs['logo']);

    if ($nom === '') {
        /**
         * Effacer le nom efface le sponsor.
         *
         * C'est le geste que fait quelqu'un dont le partenariat s'arrête,
         * et il ne devrait pas avoir à chercher un bouton « supprimer »
         * pour cela. Le lien court, lui, reste : il a été partagé, et une
         * adresse qui tombe en erreur est pire qu'une page de sponsor.
         */
        db()->prepare("UPDATE decors SET sponsor_nom = NULL, sponsor_logo = NULL,
                       sponsor_lien = NULL, sponsor_statut = 'a_relire', maj_le = ?
                       WHERE id = ?")
            ->execute([maintenant(), (string) $decor['id']]);
        return ['ok' => true, 'message' => 'Sponsor retiré de ce décor.', 'relire' => false];
    }

    if ($lien !== '' && !filter_var($lien, FILTER_VALIDATE_URL)) {
        return ['ok' => false, 'message' => 'Ce lien n’est pas une adresse valide.', 'relire' => false];
    }
    if ($lien !== '' && !preg_match('#^https?://#i', $lien)) {
        return ['ok' => false, 'message' => 'Le lien doit commencer par http:// ou https://.',
                'relire' => false];
    }

    $sans_relecture = $lien === '' || droit($u, 'valider') || redirection_autorisee($lien);
    $statut = $sans_relecture ? 'valide' : 'a_relire';

    /**
     * Le lien court n'est fabriqué qu'une fois, et pointe toujours au bon
     * endroit.
     *
     * Le recréer à chaque enregistrement laisserait derrière lui des codes
     * orphelins, et surtout casserait le compteur : le sponsor de l'an
     * dernier repartirait de zéro parce qu'on a corrigé une faute dans son
     * nom. On met donc la CIBLE à jour, en gardant le code.
     */
    $code = trim((string) ($decor['sponsor_code'] ?? ''));
    if ($lien !== '') {
        if ($code === '') {
            $code = creer_lien((string) ($decor['auteur_id'] ?? $u['id']), $lien,
                'Sponsor · ' . $nom, (string) $decor['id']);
        } else {
            db()->prepare('UPDATE liens SET cible = ?, titre = ? WHERE code = ?')
                ->execute([$lien, 'Sponsor · ' . $nom, $code]);
        }
    }

    db()->prepare('UPDATE decors SET sponsor_nom = ?, sponsor_logo = ?, sponsor_lien = ?,
                   sponsor_code = ?, sponsor_statut = ?, maj_le = ? WHERE id = ?')
        ->execute([$nom, $logo ?: null, $lien ?: null, $code ?: null, $statut,
                   maintenant(), (string) $decor['id']]);

    if ($statut === 'a_relire') {
        notifier_equipe('regie', 'Un lien de sponsor attend la relecture',
            '« ' . $nom .' » sur le décor ' . $decor['titre'] . ' · ' . $lien,
            '?p=sponsor&decor=' . $decor['slug']);
        return ['ok' => true, 'relire' => true,
                'message' => 'Sponsor enregistré. Son lien devient cliquable une fois relu '
                           . 'par l’équipe : il mène hors de nos domaines, et c’est notre nom '
                           . 'qui sert de caution.'];
    }
    return ['ok' => true, 'relire' => false, 'message' => 'Sponsor enregistré.'];
}

/** La maison approuve le lien. */
function sponsor_valider(array $decor, array $u): void
{
    db()->prepare("UPDATE decors SET sponsor_statut = 'valide', maj_le = ? WHERE id = ?")
        ->execute([maintenant(), (string) $decor['id']]);
    journal_ecrire($u, 'sponsor.modifie', 'decor', (string) $decor['id'],
        (string) $decor['titre'], 'Lien de sponsor approuvé');
    if (($decor['auteur_id'] ?? null)) {
        notifier((string) $decor['auteur_id'], 'regie', 'Le lien de votre sponsor est approuvé',
            '« ' . $decor['sponsor_nom'] . ' » est maintenant cliquable sur la page du décor.',
            '?p=sponsor&decor=' . $decor['slug']);
    }
}

/**
 * Ce que le sponsor a obtenu, en quatre nombres et un entonnoir.
 *
 * Tous depuis le début du décor, sans borne de période : un sponsor achète
 * un ÉVÉNEMENT, pas un mois. Découper en septembre et octobre ce qui s'est
 * joué sur une soirée ne répondrait à aucune question qu'il se pose.
 */
function sponsor_chiffres(array $decor): array
{
    $id = (string) $decor['id'];
    $vues = compter("SELECT COUNT(*) AS n FROM evenements WHERE decor_id = ? AND genre = 'vue'", [$id]);
    $badges = compter('SELECT COUNT(*) AS n FROM badges WHERE decor_id = ?', [$id]);
    $emportes = compter('SELECT COUNT(*) AS n FROM badges WHERE decor_id = ? AND telecharge_le IS NOT NULL', [$id]);
    $presences = compter('SELECT COUNT(*) AS n FROM badges WHERE decor_id = ? AND scanne_le IS NOT NULL', [$id]);

    $code = trim((string) ($decor['sponsor_code'] ?? ''));
    $clics = $code === ''
        ? 0
        : compter('SELECT COALESCE(SUM(clics), 0) AS n FROM liens WHERE code = ?', [$code]);

    /**
     * Les messages qui ont porté le bloc du sponsor.
     *
     * Ce sont les campagnes rattachées au décor : ce sont elles qui mènent
     * à la page où le logo se trouve. On compte les destinataires PARTIS,
     * pas les programmés — « parti » n'est déjà pas « reçu », et annoncer
     * à un sponsor des messages qui ont échoué serait aller plus loin
     * encore dans le mauvais sens.
     */
    $campagnes = compter(
        "SELECT COUNT(*) AS n FROM campagnes_email WHERE decor_id = ? AND statut = 'envoye'", [$id]);
    $destinataires = compter(
        "SELECT COUNT(*) AS n FROM envois_email e JOIN campagnes_email c ON c.id = e.campagne_id
         WHERE c.decor_id = ? AND e.statut = 'envoye'", [$id]);

    $tete = max(1, $vues);
    return [
        'vues' => $vues,
        'badges' => $badges,
        'emportes' => $emportes,
        'presences' => $presences,
        'clics' => $clics,
        'campagnes' => $campagnes,
        'destinataires' => $destinataires,
        'entonnoir' => [
            ['nom' => 'Vues de la page', 'n' => $vues, 'part' => 1.0],
            ['nom' => 'Badges créés', 'n' => $badges, 'part' => min(1.0, $badges / $tete)],
            ['nom' => 'Badges emportés', 'n' => $emportes, 'part' => min(1.0, $emportes / $tete)],
            ['nom' => 'Présents à la porte', 'n' => $presences, 'part' => min(1.0, $presences / $tete)],
        ],
    ];
}

/** Le nom du fichier, lisible dans un dossier de téléchargements. */
function sponsor_nom_fichier(array $decor): string
{
    // `slugifier()` sait déjà enlever les accents et les espaces : c’est
    // elle qui fabrique les adresses de décors, autant que le nom d’un
    // fichier suive la même règle.
    $sponsor = slugifier((string) ($decor['sponsor_nom'] ?? 'sponsor')) ?: 'sponsor';
    return 'exposition-' . $sponsor . '-' . slugifier((string) $decor['slug']) . '.pdf';
}

/**
 * Le rapport d'exposition : une page, quatre nombres, et la phrase qui dit
 * ce qu'ils ne prouvent pas.
 *
 * C'est cette phrase qui rend le reste crédible. Un document de sponsor
 * sans elle se lit comme une brochure ; avec elle, il se lit comme une
 * mesure — et c'est ce qu'on revend l'année suivante.
 */
function sponsor_pdf(array $decor, array $c): string
{
    $s = sponsor_de($decor) ?? ['nom' => 'Sponsor', 'logo' => '', 'url' => '', 'lien' => ''];
    $e = facturation_reglages();
    $pdf = new EcrivainPdf('Rapport d’exposition · ' . $s['nom'] . ' · ' . $decor['titre']);

    $g = 16.0;
    $d = PDF_L - 16.0;
    $n = static fn(int $x): string => number_format($x, 0, ',', ' ');

    $pdf->page();
    $y = 16.0;
    $pdf->texte($g, $y, (string) $e['fact_raison'], 9, true, '#475569');
    $pdf->texte($d, $y, 'Édité le ' . date_fr(maintenant()), 9, false, '#94A3B8', 'droite');
    $y += 2.5;
    $pdf->filet($g, $y, $d, $y, 0.3, '#E2E8F5');
    // Le pied s'écrit avant la numérotation : une écriture après elle
    // ouvrirait une page vide à la fin du document.
    $pdf->texte($g, PDF_H - 12, sponsor_nom_fichier($decor) . ' · Wakabi Boost ' . VERSION,
        7.5, false, '#94A3B8');
    $y += 10;

    /* ---- le titre ---- */
    $pdf->texte($g, $y, 'Rapport d’exposition', 10, true, '#94A3B8');
    $y += 8;
    $pdf->texte($g, $y, (string) $s['nom'], 19, true);
    $y += 6.5;
    $pdf->texte($g, $y, (string) $decor['titre']
        . (($decor['evenement_le'] ?? null) ? ' · ' . date_fr((string) $decor['evenement_le']) : ''),
        10, false, '#475569');
    $y += 12;

    /* ---- les quatre nombres ---- */
    $tuiles = [
        ['Vues de la page', $n($c['vues'])],
        ['Badges créés', $n($c['badges'])],
        ['Clics sur le lien', $s['url'] === '' ? 'sans lien' : $n($c['clics'])],
        ['Personnes à la porte', $n($c['presences'])],
    ];
    $largeur = ($d - $g - 3 * 4) / 4;
    foreach ($tuiles as $i => [$titre, $valeur]) {
        $x = $g + $i * ($largeur + 4);
        $pdf->pave($x, $y, $largeur, 20, '#F1F5FC');
        $pdf->texte($x + 4, $y + 9.5, $valeur, 15, true);
        $pdf->texte($x + 4, $y + 15.5, $titre, 7, false, '#475569');
    }
    $y += 28;

    /* ---- où le logo a été vu ---- */
    $y = rapport_pdf_titre($pdf, $g, $y, 'Où votre nom a été vu');
    $lignes = [
        'Sur la page du décor « ' . $decor['titre'] . ' », ouverte '
            . $n($c['vues']) . ' fois. C’est là que se trouve votre bloc : votre nom, '
            . 'votre logo et votre lien.',
        $c['campagnes'] > 0
            ? 'Dans ' . $n($c['campagnes']) . ' message' . ($c['campagnes'] > 1 ? 's' : '')
              . ' de rappel, partis vers ' . $n($c['destinataires']) . ' destinataire'
              . ($c['destinataires'] > 1 ? 's' : '') . ' qui menaient à cette page.'
            : 'Aucun message de rappel n’est encore parti pour cet événement.',
        $n($c['badges']) . ' badge' . ($c['badges'] > 1 ? 's ont' : ' a')
            . ' été fabriqué' . ($c['badges'] > 1 ? 's' : '') . ' depuis cette page, dont '
            . $n($c['emportes']) . ' emporté' . ($c['emportes'] > 1 ? 's' : '')
            . ' puis partagé' . ($c['emportes'] > 1 ? 's' : '') . ' sur les réseaux de leurs auteurs.',
    ];
    foreach ($lignes as $l) {
        $pdf->texte($g, $y + 2.6, '•', 9, true, '#2563EB');
        $y = $pdf->paragraphe($g + 5, $y + 2.6, $d - $g - 5, $l, 9, 4.4, false, '#0F172A') + 2.5;
    }
    $y += 6;

    /* ---- de la vue à la porte ---- */
    $y = rapport_pdf_titre($pdf, $g, $y, 'De la vue à la porte');
    $piste = $d - $g - 46 - 24;
    foreach ($c['entonnoir'] as $pas) {
        $pdf->texte($g, $y + 3, (string) $pas['nom'], 8.5, false);
        $pdf->pave($g + 46, $y, $piste, 4.6, '#F1F5FC');
        if ((int) $pas['n'] > 0) {
            $pdf->pave($g + 46, $y, max(0.6, $piste * (float) $pas['part']), 4.6, '#2563EB');
        }
        $pdf->texte($d, $y + 3, $n((int) $pas['n']), 8.5, true, '#0F172A', 'droite');
        $y += 7.5;
    }
    $y += 6;

    /* ---- ce que ces nombres ne prouvent pas ---- */
    $pdf->pave($g, $y, $d - $g, 24, '#FFFBEB');
    $y = $pdf->paragraphe($g + 5, $y + 7, $d - $g - 10,
        'Ce document compte des occasions de voir, pas des regards. Une page ouverte porte '
        . 'votre nom ; elle ne prouve pas qu’on l’a regardé. Les ' . $n($c['presences'])
        . ' présences, elles, sont comptées une par une à la porte, et les '
        . ($s['url'] === '' ? '0' : $n($c['clics'])) . ' clics prouvent un geste.',
        8.5, 4.4, false, '#92400E');
    $y += 10;

    if ($s['url'] !== '') {
        $pdf->texte($g, $y, 'Votre lien : ' . $s['url'], 8, false, '#475569');
        $y += 6;
    }

    $pdf->numeroter('Page %n / %t', $d, PDF_H - 12, 7.5, '#94A3B8');
    return $pdf->rendu();
}
