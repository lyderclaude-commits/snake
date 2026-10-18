#!/bin/sh
# Fabrique dream-site.zip : le dossier à téléverser chez l'hébergeur, sans base ni images importées.
set -e
cd "$(dirname "$0")/.."
rm -f dream-site.zip
(cd site && zip -qr ../dream-site.zip . -x 'donnees/*.sqlite*' 'donnees/sessions/*' 'uploads/20*' 'config.php')
echo "dream-site.zip : $(du -h dream-site.zip | cut -f1)"
