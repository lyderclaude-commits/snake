#!/bin/sh
# Fabrique socle-admin.zip : le socle, le site exemple prêt à téléverser (socle compris), les outils et le README.
set -e
cd "$(dirname "$0")/.."
rm -f socle-admin.zip
# `zip` suit les liens symboliques : `exemple/socle` y entre comme un vrai dossier.
zip -qr socle-admin.zip README.md socle exemple outils -x 'exemple/donnees/*.sqlite*' 'exemple/donnees/sessions/*' 'exemple/uploads/20*' 'exemple/config.php'
echo "socle-admin.zip : $(du -h socle-admin.zip | cut -f1)"
