<?php
/**
 * L'identité légale de l'émetteur, et le taux de TVA.
 *
 * Un écran de réglages qui se contente d'enregistrer des champs laisse
 * découvrir ses erreurs trois semaines plus tard, sur la facture d'un
 * client. Celui-ci montre donc le calcul sur un prix réel pendant qu'on
 * saisit, et ouvre un exemple de facture complet en un clic.
 */

declare(strict_types=1);

$u = exiger_droit('reglages');
$message = null;
$erreur = null;

if ($post) {
    verifier_csrf();
    $avant = facturation_reglages();
    facturation_reglages_poser($_POST);
    $apres = facturation_reglages();

    if ((int) $avant['fact_tva'] !== (int) $apres['fact_tva']) {
        /**
         * Un changement de taux se journalise.
         *
         * Il ne touche PAS les factures déjà émises — chacune porte le
         * taux qui était le sien — mais il change toutes les suivantes, et
         * c'est le genre de décision qu'on veut pouvoir dater.
         */
        journal_ecrire($u, 'facturation.tva', 'reglage', 'fact_tva', 'Taux de TVA',
            number_format((int) $avant['fact_tva'] / 100, 2, ',', '') . ' % vers '
            . number_format((int) $apres['fact_tva'] / 100, 2, ',', '') . ' %');
    }
    $message = 'Identité de facturation enregistrée. Les factures déjà émises gardent la leur.';
}

vue('reglages-facturation', [
    'titre' => 'Identité de facturation',
    'reglages' => facturation_reglages(),
    'message' => $message,
    'erreur' => $erreur,
]);
