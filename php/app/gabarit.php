<?php
/**
 * Le contrat de données, et les règles qui le rendent exécutable.
 *
 * Port fidèle de src/core/template.schema.ts et src/server/buildTemplate.ts.
 * Le gabarit produit ici est lu TEL QUEL par le renderer JavaScript côté
 * navigateur : c'est le même contrat des deux côtés, et c'est ce qui garantit
 * que l'aperçu et l'export coïncident.
 */

declare(strict_types=1);

const WAKABI_SIGNATURE = 'LE GUIDE DES BONS PLANS';

/**
 * Le garde-fou : un décor de partenaire ne peut renvoyer que vers un domaine
 * Wakabi. Sans lui, un décor deviendrait une passerelle vers n'importe quoi.
 */
const WAKABI_DOMAINES = ['wakabileguide.com', 'studio.wakabileguide.com'];

const STATUTS = ['brouillon', 'en_relecture', 'corrections', 'refuse', 'publie', 'expire', 'archive'];

/** Dispositions proposées au partenaire. Il ne place pas ses calques à la main. */
function dispositions(): array
{
    return [
        ['id' => 'bandeau', 'nom' => 'Bandeau bas', 'aide' => 'Carré · texte sur une bande en bas', 'ratio' => '1:1'],
        ['id' => 'angle',   'nom' => 'Coin & voile', 'aide' => 'Carré · texte en bas à gauche',     'ratio' => '1:1'],
        ['id' => 'story',   'nom' => 'Story verticale', 'aide' => '9:16 · pour le statut WhatsApp', 'ratio' => '9:16'],
    ];
}

/**
 * Transitions autorisées, par acteur.
 *
 * C'est ici que la modération devient une règle et non une consigne : un
 * partenaire ne peut ni publier ni s'auto-approuver, quoi qu'il envoie.
 */
function transition_permise(string $de, string $vers, string $acteur): bool
{
    $regles = [
        'partenaire' => [
            'brouillon'   => ['en_relecture'],
            'corrections' => ['en_relecture'],
            'refuse'      => ['brouillon'],
        ],
        'equipe' => [
            'brouillon'    => ['en_relecture', 'publie', 'archive'],
            'en_relecture' => ['publie', 'corrections', 'refuse'],
            'corrections'  => ['publie', 'refuse'],
            'refuse'       => ['brouillon', 'archive'],
            'publie'       => ['expire', 'archive'],
            'expire'       => ['publie', 'archive'],
        ],
    ];
    return in_array($vers, $regles[$acteur][$de] ?? [], true);
}

function statut_libelle(string $s): string
{
    return [
        'brouillon' => 'Brouillon',
        'en_relecture' => 'En relecture',
        'corrections' => 'Corrections demandées',
        'refuse' => 'Refusé',
        'publie' => 'Publié',
        'expire' => 'Expiré',
        'archive' => 'Archivé',
    ][$s] ?? $s;
}

/* ---------------- dimensions et positions ---------------- */

function canevas(string $disposition): array
{
    return match ($disposition) {
        'story' => ['w' => 1080, 'h' => 1920, 'ratio' => '9:16'],
        default => ['w' => 1080, 'h' => 1080, 'ratio' => '1:1'],
    };
}

/**
 * Positions des textes, par disposition.
 *
 * Calées pour ne heurter NI le filigrane (bas-droite) NI le QR (bas-gauche
 * avec sa zone de silence). D'où un texte qui commence à 25 % et s'arrête à
 * 73 % : la bande centrale est la seule vraiment libre.
 */
function positions_textes(string $disposition): array
{
    return match ($disposition) {
        'angle' => [
            'accroche' => ['x' => 0.25, 'y' => 0.79,  'w' => 0.48, 'h' => 0.08,  'size' => 0.05,  'font' => 'display'],
            'champ'    => ['x' => 0.25, 'y' => 0.872, 'w' => 0.48, 'h' => 0.05,  'size' => 0.026, 'font' => 'body'],
        ],
        'story' => [
            'accroche' => ['x' => 0.25, 'y' => 0.826, 'w' => 0.48, 'h' => 0.06,  'size' => 0.032, 'font' => 'display'],
            'champ'    => ['x' => 0.25, 'y' => 0.884, 'w' => 0.48, 'h' => 0.04,  'size' => 0.02,  'font' => 'body'],
        ],
        default => [
            'accroche' => ['x' => 0.25, 'y' => 0.795, 'w' => 0.48, 'h' => 0.09,  'size' => 0.058, 'font' => 'display'],
            'champ'    => ['x' => 0.25, 'y' => 0.888, 'w' => 0.48, 'h' => 0.055, 'size' => 0.03,  'font' => 'body'],
        ],
    };
}

/* ---------------- fabrication ---------------- */

class GabaritInvalide extends RuntimeException
{
}

/**
 * Construit un gabarit valide à partir du formulaire partenaire, et le
 * valide. Une saisie qui viole une règle lève une exception portant le
 * message destiné au partenaire.
 */
function construire_gabarit(array $i): array
{
    $c = canevas($i['disposition']);
    $t = positions_textes($i['disposition']);

    $gabarit = [
        'id' => nouvel_id(),
        'slug' => $i['slug'],
        'title' => $i['titre'],
        'subtitle' => $i['sous_titre'] ?: null,
        'city' => $i['ville'],
        'rubrique' => $i['rubrique'],
        'status' => 'brouillon',
        'createdBy' => $i['cree_par'],
        'partnerId' => $i['partenaire_id'] ?? null,
        'expiresAt' => $i['expire_le'] ?: null,
        'moderation' => new stdClass(),
        'canvas' => ['ratio' => $c['ratio'], 'width' => $c['w'], 'height' => $c['h'], 'background' => 'brand.ink'],
        // Tous les champs sont écrits EXPLICITEMENT, y compris ceux que la
        // version TypeScript laissait remplir par zod. Le renderer les lit
        // sans les vérifier : un `mask` absent, et il ne dessine plus rien.
        // scripts/verifier-gabarit.ts compare cette structure au vrai schéma.
        'layers' => [
            ['type' => 'photoSlot', 'id' => 'photo',
             'rect' => ['x' => 0, 'y' => 0, 'w' => 1, 'h' => 1],
             'fit' => 'cover', 'mask' => ['kind' => 'rect', 'radius' => 0],
             'minScale' => 0.5, 'maxScale' => 4, 'allowRotation' => false],
            ['type' => 'image', 'id' => 'frame', 'src' => $i['cadre_url'],
             'rect' => ['x' => 0, 'y' => 0, 'w' => 1, 'h' => 1],
             'opacity' => 1, 'blendMode' => 'normal'],
            ['type' => 'text', 'id' => 'claim', 'value' => $i['accroche'],
             'editable' => false, 'placeholder' => '', 'maxLength' => 40,
             'uppercase' => $i['disposition'] === 'bandeau',
             'rect' => ['x' => $t['accroche']['x'], 'y' => $t['accroche']['y'], 'w' => $t['accroche']['w'], 'h' => $t['accroche']['h']],
             'size' => $t['accroche']['size'], 'align' => 'left',
             'color' => 'brand.paper', 'font' => $t['accroche']['font'], 'autoShrink' => true],
            ['type' => 'text', 'id' => 'field', 'editable' => true,
             'placeholder' => $i['champ_libelle'], 'value' => $i['champ_valeur'],
             'maxLength' => 42, 'uppercase' => false,
             'rect' => ['x' => $t['champ']['x'], 'y' => $t['champ']['y'], 'w' => $t['champ']['w'], 'h' => $t['champ']['h']],
             'size' => $t['champ']['size'], 'align' => 'left',
             'color' => 'brand.paper', 'font' => $t['champ']['font'], 'autoShrink' => true],
        ],
        'watermark' => ['enabled' => true, 'position' => 'bottom-right', 'opacity' => 0.9, 'variant' => 'wordmark'],
        'qr' => ['enabled' => true, 'position' => 'bottom-left', 'size' => 0.16],
        'export' => ['formats' => [$c['ratio']], 'maxPx' => 2048, 'quality' => 0.92, 'mimeType' => 'image/jpeg'],
        'filters' => ['none', 'wakabi-blue'],
        'share' => [
            'defaultCaption' => $i['legende'] ?? '',
            'hashtags' => ['Wakabi', 'JySerai'],
            'redirectUrl' => $i['redirection'],
            'redirectLabel' => $i['redirection_libelle'] ?: 'Découvrir sur Wakabi',
        ],
    ];

    valider_gabarit($gabarit);
    return $gabarit;
}

/**
 * Les invariants structurels.
 *
 * Chacun correspond à une manière concrète de produire un décor cassé ou
 * abusif. Ils sont vérifiés à l'écriture, jamais à la lecture : un gabarit
 * invalide en base ne se manifeste qu'en page introuvable.
 */
function valider_gabarit(array $g): void
{
    $couches = $g['layers'] ?? [];

    $slots = array_filter($couches, fn($l) => ($l['type'] ?? '') === 'photoSlot');
    if (count($slots) !== 1) {
        throw new GabaritInvalide('Un décor doit contenir exactement un emplacement photo.');
    }

    $iPhoto = array_key_first($slots);
    $iCadre = null;
    foreach ($couches as $k => $l) {
        if (($l['type'] ?? '') === 'image' && ($l['id'] ?? '') === 'frame') {
            $iCadre = $k;
        }
    }
    if ($iCadre !== null && $iCadre < $iPhoto) {
        throw new GabaritInvalide('Le cadre doit être dessiné APRÈS la photo, sinon la photo le recouvre.');
    }

    $c = $g['canvas'] ?? [];
    $attendu = ($c['ratio'] ?? '') === '9:16' ? 9 / 16 : 1;
    $reel = ($c['width'] ?? 1) / max(1, $c['height'] ?? 1);
    if (abs($reel - $attendu) > 0.01) {
        throw new GabaritInvalide('Les dimensions du canevas ne correspondent pas au format annoncé.');
    }

    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) ($g['slug'] ?? ''))) {
        throw new GabaritInvalide('L’adresse du décor ne peut contenir que des minuscules, des chiffres et des tirets.');
    }

    foreach ($couches as $l) {
        if (!isset($l['rect'])) {
            continue;
        }
        $r = $l['rect'];
        if ($r['x'] < 0 || $r['y'] < 0 || $r['x'] + $r['w'] > 1.0001 || $r['y'] + $r['h'] > 1.0001) {
            throw new GabaritInvalide('Un élément déborde du canevas.');
        }
    }

    // Le garde-fou de redirection — validé côté serveur, jamais côté formulaire.
    $redir = (string) ($g['share']['redirectUrl'] ?? '');
    if ($redir === '') {
        throw new GabaritInvalide('Indiquez la page vers laquelle renvoyer après téléchargement.');
    }
    $hote = parse_url($redir, PHP_URL_HOST);
    if (!$hote) {
        throw new GabaritInvalide('L’adresse de redirection n’est pas une URL valide.');
    }
    if (($g['createdBy'] ?? '') === 'partenaire') {
        $permis = false;
        foreach (WAKABI_DOMAINES as $d) {
            if ($hote === $d || str_ends_with($hote, '.' . $d)) {
                $permis = true;
                break;
            }
        }
        if (!$permis) {
            throw new GabaritInvalide(sprintf(
                'Un décor de partenaire doit rediriger vers un domaine Wakabi (%s), pas vers « %s ».',
                implode(', ', WAKABI_DOMAINES),
                $hote
            ));
        }
    }

    if (($g['status'] ?? '') === 'publie' && ($g['createdBy'] ?? '') === 'partenaire'
        && empty($g['moderation']['reluPar'])) {
        throw new GabaritInvalide('Un décor de partenaire ne peut pas être publié sans relecture Wakabi.');
    }
    if (in_array($g['status'] ?? '', ['refuse', 'corrections'], true) && empty($g['moderation']['motif'])) {
        throw new GabaritInvalide('Un refus ou une demande de correction exige un motif.');
    }
}
