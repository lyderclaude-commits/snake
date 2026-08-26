#!/usr/bin/env bash
#
# Déploiement de Wakabi Boost — à lancer SUR LE SERVEUR, depuis les sources.
#
#   cd /srv/wakabi-boost/src && ./deploy/deployer.sh
#
# Construit, publie dans une release datée, bascule le lien `current`, puis
# redémarre le service. La release précédente reste sur le disque : revenir en
# arrière ne demande que de rebasculer le lien.

set -euo pipefail

RACINE="${WAKABI_ROOT:-/srv/wakabi-boost}"
DONNEES="${WAKABI_DATA:-/var/lib/wakabi-boost}"
SERVICE="${WAKABI_SERVICE:-wakabi-boost}"
RELEASE="$RACINE/releases/$(date +%Y%m%d-%H%M%S)"

echo "→ dépendances"
npm ci

echo "→ build"
npm run build

echo "→ publication dans $RELEASE"
mkdir -p "$RELEASE"
# La sortie standalone embarque le serveur ; static et public s'y ajoutent.
cp -r .next/standalone/. "$RELEASE/"
mkdir -p "$RELEASE/.next"
cp -r .next/static "$RELEASE/.next/static"
[ -d public ] && cp -r public "$RELEASE/public"

echo "→ données persistantes dans $DONNEES"
mkdir -p "$DONNEES/uploads"

echo "→ bascule"
ln -sfn "$RELEASE" "$RACINE/current"

echo "→ redémarrage"
sudo systemctl restart "$SERVICE"
sleep 2
systemctl is-active --quiet "$SERVICE" && echo "✓ $SERVICE actif" || {
  echo "✗ le service n'est pas reparti :"; journalctl -u "$SERVICE" -n 30 --no-pager; exit 1;
}

# On garde les 5 dernières : de quoi revenir en arrière sans remplir le disque.
ls -1dt "$RACINE"/releases/* | tail -n +6 | xargs -r rm -rf

echo
echo "✓ déployé — $RELEASE"
echo "  retour arrière : ln -sfn <release précédente> $RACINE/current && sudo systemctl restart $SERVICE"
