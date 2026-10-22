<?php
/**
 * Un faux `sendmail` pour la recette : chaque message que `mail()` remet est
 * écrit dans le dossier `COURRIELS_RECETTE`, avec la ligne de commande reçue.
 *
 *     php -d sendmail_path="php dream/outils/capture-mail.php" …
 */
$dossier = getenv('COURRIELS_RECETTE') ?: sys_get_temp_dir();
file_put_contents($dossier . '/' . microtime(true) . '.eml', 'X-Arguments: ' . implode(' ', array_slice($argv, 1)) . "\n" . stream_get_contents(STDIN));
