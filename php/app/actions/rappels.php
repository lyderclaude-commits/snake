<?php
/**
 * Les rappels d'un événement.
 *
 * Un décor porte une date ; les rappels s'en déduisent. J−7 n'est pas un
 * réglage de plus à saisir, c'est un calcul — et c'est toute la différence
 * entre « on pourra programmer » et « c'est programmé ».
 *
 * Les rappels ne sont pas une nouvelle sorte d'objet : ce sont des
 * campagnes de la régie, avec une date d'envoi et le décor auquel elles se
 * rattachent. Elles suivent donc la même relecture, la même file, le même
 * quota, et se retrouvent au même endroit que le reste.
 */

declare(strict_types=1);

$u = exiger_droit('regie');
$proprio = (string) $u['id'];
$equipe = droit($u, 'valider');

$decor = decor_par_id((string) ($_GET['id'] ?? ''));
if (!$decor || (!$equipe && (string) $decor['auteur_id'] !== $proprio)) {
    http_response_code(404);
    vue('introuvable', ['titre' => 'Décor introuvable', 'indexable' => false]);
}

$message = null;
$erreur = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifier_csrf();
    $quoi = (string) ($_POST['quoi'] ?? '');

    if ($quoi === 'poser') {
        if (!$decor['evenement_le']) {
            $erreur = 'Ce décor n’a pas de date d’événement : c’est d’elle que se déduisent les rappels. '
                    . 'Ajoutez-la depuis « Modifier ».';
        } else {
            /**
             * Les canaux de ces rappels : ceux du compte, e-mail compris.
             *
             * On coche par défaut ce qui est gratuit et immédiat. WhatsApp
             * reste décoché : il se facture, et cocher pour quelqu'un une
             * dépense qu'il n'a pas demandée n'est pas un service.
             */
            $cibles = [['canal' => 'email']];
            if (push_disponible()) {
                $cibles[] = ['canal' => 'push'];
            }
            foreach (canaux_de($proprio) as $canal) {
                if ($canal['statut'] !== 'branche' || $canal['genre'] !== 'telegram') {
                    continue;
                }
                foreach ($canal['destinations'] as $d) {
                    $cibles[] = ['canal' => 'telegram', 'canal_id' => (string) $canal['id'],
                                 'destination_id' => (string) $d['id']];
                }
                $cibles[] = ['canal' => 'telegram', 'canal_id' => (string) $canal['id']];
            }
            $json = json_encode($cibles, JSON_UNESCAPED_UNICODE);

            $poses = 0;
            $deja = [];
            foreach (campagnes_du_decor((string) $decor['id']) as $c) {
                $deja[] = (string) $c['rappel'];
            }
            foreach (RAPPELS_MODELE as $m) {
                if (in_array($m['cle'], $deja, true)) {
                    continue;   // déjà posé : on ne le double pas
                }
                $quand = rappel_quand((string) $decor['evenement_le'], (int) $m['heures']);
                campagne_email_creer([
                    'auteur_id' => $proprio,
                    'sujet' => $m['titre'] . ' · ' . $decor['titre'],
                    'titre' => $m['titre'],
                    'corps' => $m['corps'],
                    'lien' => $m['libelle'] !== '' ? url('?p=decor&slug=' . rawurlencode((string) $decor['slug'])) : '',
                    'lien_libelle' => $m['libelle'],
                    'cible' => 'mes-invites',
                    'liste' => '',
                    'liste_id' => '',
                    'canaux' => $json,
                    'planifie_le' => $quand,
                    'decor_id' => (string) $decor['id'],
                    'rappel' => $m['cle'],
                ]);
                $poses++;
            }
            $message = $poses > 0
                ? $poses . ' rappel(s) posé(s), en brouillon. Relisez-les : les dates sont calculées, les textes sont à vous.'
                : 'Les cinq rappels sont déjà posés.';
        }
    }
}

vue('rappels', [
    'titre' => 'Rappels · ' . $decor['titre'],
    'decor' => $decor,
    'rappels' => campagnes_du_decor((string) $decor['id']),
    'modele' => RAPPELS_MODELE,
    'equipe' => $equipe,
    'message' => $message,
    'erreur' => $erreur,
]);
