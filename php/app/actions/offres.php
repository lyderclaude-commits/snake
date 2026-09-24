<?php
/**
 * Les offres commerciales, tenues depuis l'écran.
 *
 * Un prix n'est pas une décision d'ingénieur. Il vivait pourtant dans la
 * constante `FORMULES`, c'est-à-dire dans le code : passer Impact de 5 000
 * à 6 000 demandait un paquet, une mise en ligne et quelqu'un qui sache
 * faire les deux. L'écran rend ce geste à celui à qui il appartient.
 *
 * Ce que la constante garde : QUELLES lignes existent. Ajouter « messages
 * WhatsApp » au produit reste un développement, parce qu'il faut du code
 * pour les envoyer. Décider que Croissance en donne 2 000 ou 3 000, non.
 */

declare(strict_types=1);

$u = exiger_droit('reglages');

$message = null;
$erreur = null;

/**
 * Les clés que l'écran sait écrire, et comment les lire.
 *
 * Déduites de `OFFRE_LIGNES` : la liste des capacités bouge à chaque
 * fonctionnalité, et une liste recopiée ici aurait divergé à la première.
 * Le genre décide de la saisie, `compteur` prenant un nombre où `-1` veut
 * dire « sans limite », et `stats` restant le seul champ à trois états.
 */
$champs = [];
foreach (OFFRE_LIGNES as $cle => [$libelle, $genre, $aide]) {
    $champs[$cle] = $genre === 'compteur' ? 'nombre' : ($cle === 'stats' ? 'stats' : 'oui-non');
}

/** Ce que le formulaire vient d'envoyer, nettoyé. */
$lire_capacites = static function () use ($champs): array {
    $out = [];
    foreach ($champs as $cle => $genre) {
        $brut = $_POST['cap'][$cle] ?? null;
        $out[$cle] = match ($genre) {
            // Un champ vide vaut zéro, jamais « sans limite » : une
            // distraction ne doit pas offrir l'illimité à tout le monde.
            'nombre' => $brut === '' || $brut === null ? 0 : max(-1, (int) $brut),
            'stats'  => (string) $brut === 'completes' ? 'completes' : 'base',
            default  => (string) $brut === '1',
        };
    }
    return $out;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();
    $quoi = (string) ($_POST['quoi'] ?? '');
    $cle = strtolower(trim((string) ($_POST['cle'] ?? '')));

    if ($quoi === 'supprimer') {
        /**
         * Trois refus, et ils ne se négocient pas.
         *
         * « decouverte » est le filet de tout le produit : `offre()` y
         * retombe quand un compte n'a plus rien, et un décor dont l'auteur
         * a fermé son compte reste au catalogue. La supprimer ne casserait
         * pas une page, elle en casserait toutes.
         *
         * Une offre que des comptes portent ne se supprime pas non plus :
         * ils basculeraient en silence sur Découverte, c'est-à-dire qu'on
         * reprendrait ce qu'ils ont payé sans le leur dire. Pour arrêter
         * de la vendre, il y a « retirer de la vitrine », qui ne touche à
         * personne.
         */
        if ($cle === 'decouverte') {
            $erreur = 'L’offre Découverte ne se supprime pas : c’est elle que le produit '
                    . 'applique quand un compte n’a plus d’offre du tout.';
        } elseif (!isset(formules()[$cle])) {
            $erreur = 'Cette offre n’existe pas.';
        } elseif (($n = formule_portee($cle)) > 0) {
            $erreur = $n . ' compte' . ($n > 1 ? 's portent' : ' porte') . ' cette offre. '
                    . 'Retirez-la de la vitrine pour cesser de la vendre : '
                    . 'la supprimer reprendrait à ' . ($n > 1 ? 'ces comptes' : 'ce compte')
                    . ' ce qu’ils ont payé.';
        } else {
            $nom = (string) formules()[$cle]['nom'];
            db()->prepare('DELETE FROM formules WHERE cle = ?')->execute([$cle]);
            formules_oublier();
            journal_ecrire($u, 'offre.supprimee', 'offre', $cle, $nom, 'Aucun compte ne la portait');
            $message = 'Offre « ' . $nom . ' » supprimée.';
        }
    }

    if ($quoi === 'enregistrer') {
        $neuve = (string) ($_POST['neuve'] ?? '') === '1';
        $nom = trim((string) ($_POST['nom'] ?? ''));
        $prix = max(0, (int) ($_POST['prix'] ?? 0));
        $lancement = max(0, (int) ($_POST['lancement'] ?? 0));
        $rang = max(0, (int) ($_POST['rang'] ?? 0));
        $actif = (string) ($_POST['actif'] ?? '') === '1' ? 1 : 0;
        $phare = (string) ($_POST['phare'] ?? '') === '1' ? 1 : 0;
        $tag = trim((string) ($_POST['tag'] ?? ''));
        $cta = trim((string) ($_POST['cta'] ?? ''));

        if ($nom === '') {
            $erreur = 'Donnez un nom à l’offre : c’est ce que le client lit.';
        } elseif ($neuve && !preg_match('/^[a-z][a-z0-9_-]{1,38}$/', $cle)) {
            $erreur = 'La clé ne prend que des minuscules, des chiffres et des tirets, '
                    . 'et commence par une lettre. Elle est écrite sur chaque compte : '
                    . 'elle ne changera plus.';
        } elseif ($neuve && isset(formules()[$cle])) {
            $erreur = 'Une offre porte déjà la clé « ' . $cle . ' ».';
        } elseif (!$neuve && !isset(formules()[$cle])) {
            $erreur = 'Cette offre n’existe pas.';
        } elseif ($lancement > $prix) {
            $erreur = 'Le prix de lancement est au-dessus du prix normal : '
                    . 'la vitrine annoncerait une remise qui coûte plus cher.';
        } else {
            $cap = $lire_capacites();

            /**
             * Le phare est unique, sinon ce n'est plus un phare.
             *
             * « Le plus choisi » posé sur trois offres ne dit plus rien.
             * On l'éteint partout avant de le rallumer ici, plutôt que de
             * refuser : l'administrateur a dit laquelle, on le croit.
             */
            if ($phare === 1) {
                db()->exec('UPDATE formules SET phare = 0');
            }

            if ($neuve) {
                db()->prepare(
                    'INSERT INTO formules (cle, nom, prix, lancement, rang, actif, tag, cta, phare, capacites, cree_le)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                )->execute([$cle, $nom, $prix, $lancement, $rang, $actif, $tag, $cta, $phare,
                            json_encode($cap, JSON_UNESCAPED_UNICODE), maintenant()]);
                journal_ecrire($u, 'offre.creee', 'offre', $cle, $nom, montant_fr($prix) . ' par mois');
                $message = 'Offre « ' . $nom . ' » créée.';
            } else {
                db()->prepare(
                    'UPDATE formules SET nom = ?, prix = ?, lancement = ?, rang = ?, actif = ?,
                     tag = ?, cta = ?, phare = ?, capacites = ?, maj_le = ? WHERE cle = ?'
                )->execute([$nom, $prix, $lancement, $rang, $actif, $tag, $cta, $phare,
                            json_encode($cap, JSON_UNESCAPED_UNICODE), maintenant(), $cle]);
                journal_ecrire($u, 'offre.modifiee', 'offre', $cle, $nom, montant_fr($prix) . ' par mois');
                $message = 'Offre « ' . $nom . ' » enregistrée.';
            }
            formules_oublier();
        }
    }
}

/**
 * Celle qu'on édite : demandée, ou la première.
 *
 * Un écran sans offre ouverte aurait montré une liste et rien d'autre,
 * alors qu'on vient ici pour changer un prix.
 */
$toutes = formules();
$edite = (string) ($_GET['offre'] ?? ($erreur !== null ? ($_POST['cle'] ?? '') : ''));
$neuve = (string) ($_GET['neuve'] ?? '') === '1';
if (!$neuve && !isset($toutes[$edite])) {
    $edite = (string) array_key_first($toutes);
}

/** Combien de comptes portent chaque offre : ce qui décide de la suppression. */
$portees = [];
foreach ($toutes as $cle => $_f) {
    $portees[$cle] = formule_portee((string) $cle);
}

vue('offres', [
    'titre' => 'Les offres · Wakabi Boost',
    'toutes' => $toutes,
    'edite' => $edite,
    'neuve' => $neuve,
    'portees' => $portees,
    'champs' => $champs,
    'message' => $message,
    'erreur' => $erreur,
]);
