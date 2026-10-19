<?php
/**
 * Un faux `sendmail` pour la recette : chaque message que `mail()` remet est
 * écrit dans le dossier `LYD_COURRIELS`, avec la ligne de commande reçue.
 *
 *     php -d sendmail_path="php dream/outils/capture-mail.php" …
 */
$dossier = getenv('LYD_COURRIELS') ?: sys_get_temp_dir();
file_put_contents($dossier . '/' . microtime(true) . '.eml', 'X-Arguments: ' . implode(' ', array_slice($argv, 1)) . "\n" . stream_get_contents(STDIN));
