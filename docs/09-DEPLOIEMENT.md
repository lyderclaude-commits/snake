# Déployer sur boost.wakabileguide.com

---

## 0. Ce qu'il faut savoir avant de commencer

**Wakabi Boost n'est pas un site statique.** Ce n'est pas non plus un thème
WordPress. C'est une application Node.js qui doit tourner en permanence :
elle reçoit les téléversements, exécute le pré-vol avec `sharp`, forge les QR
et écrit dans une base SQLite.

Trois conséquences, à vérifier **avant** d'aller plus loin :

| Il vous faut | Pourquoi |
|---|---|
| **Node.js 20 ou 22**, en processus permanent | Le rendu et les Server Actions s'exécutent côté serveur, à chaque requête |
| **Un disque qui persiste** | `wakabi.sqlite` et les cadres téléversés ; les perdre, c'est perdre les comptes et les campagnes |
| **La possibilité de compiler du natif** (ou une image Docker) | `better-sqlite3` et `sharp` ne sont pas du JavaScript pur |

> **Un hébergement mutualisé « HTML + PHP » ne suffira pas**, et un dépôt de
> fichiers par FTP dans `public_html` ne donnera qu'une page blanche. Vérifiez
> auprès de votre hébergeur que vous avez un VPS, ou au minimum un cPanel avec
> « Setup Node.js App » (voie B).
>
> **Vercel et Netlify ne conviennent pas en l'état** : leur disque est éphémère
> et en lecture seule. SQLite et les cadres téléversés y disparaîtraient à
> chaque déploiement. Il faudrait d'abord passer à Postgres et à un stockage
> objet — le chemin est décrit dans `07-BACKEND.md` §5, mais ce n'est pas
> l'affaire d'un après-midi.

---

## 1. Le réglage à ne pas rater

Une seule variable compte vraiment : **`WAKABI_BASE_URL`**.

Elle est incrustée dans le **QR de chaque badge**, donc dans un fichier PNG que
l'invité télécharge, garde et partage sur WhatsApp. Un badge émis avec une
mauvaise adresse reste faux **pour toujours** — on ne peut pas rappeler une
image déjà partagée.

```
WAKABI_BASE_URL=https://boost.wakabileguide.com
```

L'application **refuse de démarrer un badge** si cette variable manque en
production, plutôt que de retomber sur une valeur par défaut : mieux vaut une
erreur visible qu'un millier de QR morts.

Les deux autres :

```
WAKABI_DB=/var/lib/wakabi-boost/wakabi.sqlite
WAKABI_UPLOADS=/var/lib/wakabi-boost/uploads
```

Placez-les **hors** du dossier de l'application. C'est ce qui permet de
redéployer sans effacer vos données.

---

## 2. Le DNS — à faire en premier

Chez votre registrar (là où vit `wakabileguide.com`), ajoutez :

| Type | Nom | Valeur | TTL |
|---|---|---|---|
| `A` | `boost` | l'adresse IPv4 de votre serveur | 3600 |
| `AAAA` | `boost` | l'adresse IPv6, si vous en avez une | 3600 |

Vérifiez avant de continuer — sans DNS résolu, Let's Encrypt refusera le
certificat :

```bash
dig +short boost.wakabileguide.com
```

> Si votre domaine passe par Cloudflare, mettez le nuage **en gris** (DNS only)
> le temps d'obtenir le certificat, puis rallumez-le si vous le souhaitez.

---

## Voie A — VPS avec Node, systemd et Nginx *(recommandée, et vérifiée)*

C'est le chemin que j'ai réellement exécuté : la sortie de production a été
assemblée et lancée exactement comme décrit ici, et les pages répondent.

### A.1 Préparer le serveur

```bash
# Node 22
curl -fsSL https://deb.nodesource.com/setup_22.x | sudo -E bash -
sudo apt-get install -y nodejs nginx

# de quoi compiler better-sqlite3
sudo apt-get install -y python3 make g++

# un utilisateur sans droits inutiles
sudo useradd --system --create-home --home-dir /srv/wakabi-boost wakabi
sudo mkdir -p /srv/wakabi-boost/releases /var/lib/wakabi-boost/uploads
sudo chown -R wakabi:wakabi /srv/wakabi-boost /var/lib/wakabi-boost
```

### A.2 Poser les sources

```bash
sudo -u wakabi -H bash
cd /srv/wakabi-boost
# depuis le zip
unzip ~/wakabi-boost.zip -d src
# ou, si vous avez poussé sur GitHub
# git clone -b claude/wakabi-decors-generator-cnvmqn https://github.com/lyderclaude-commits/snake.git src
cd src
```

### A.3 Les réglages

```bash
cp deploy/wakabi.env.example /srv/wakabi-boost/wakabi.env
nano /srv/wakabi-boost/wakabi.env       # renseignez WAKABI_BASE_URL
chmod 600 /srv/wakabi-boost/wakabi.env
```

### A.4 Premier déploiement

```bash
./deploy/deployer.sh
```

Le script installe, construit, publie dans `releases/<horodatage>/`, bascule le
lien `current` et redémarre le service. Il conserve les 5 dernières versions :
revenir en arrière ne demande que de rebasculer le lien.

### A.5 Le service

```bash
exit                                     # revenir en root
sudo cp /srv/wakabi-boost/src/deploy/wakabi-boost.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now wakabi-boost
sudo systemctl status wakabi-boost
```

En cas de problème, le journal dit tout :

```bash
journalctl -u wakabi-boost -f
```

### A.6 Nginx et le certificat

```bash
sudo cp /srv/wakabi-boost/src/deploy/nginx-boost.conf \
        /etc/nginx/sites-available/boost.wakabileguide.com
sudo ln -s /etc/nginx/sites-available/boost.wakabileguide.com /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx

sudo apt-get install -y certbot python3-certbot-nginx
sudo certbot --nginx -d boost.wakabileguide.com
```

Certbot ajoute le bloc HTTPS et programme le renouvellement automatique.

> **L'en-tête `X-Forwarded-Proto` du fichier Nginx n'est pas décoratif.** Sans
> lui, l'application se croit en HTTP, le cookie de session perd son attribut
> `Secure`, et la connexion cesse de tenir d'une page à l'autre.

### A.7 Créer votre compte d'équipe

```bash
sudo -u wakabi -H bash
cd /srv/wakabi-boost/src
WAKABI_DB=/var/lib/wakabi-boost/wakabi.sqlite npm run seed
```

Le seed crée trois comptes de démonstration (mot de passe `wakabi2026`) et
quelques décors. **Changez le mot de passe de `admin@wakabileguide.com` dès la
première connexion**, et supprimez les deux autres comptes depuis
`/admin/comptes` une fois votre vrai compte créé.

### A.8 Redéployer, plus tard

```bash
sudo -u wakabi -H bash
cd /srv/wakabi-boost/src && git pull   # ou remplacez les sources
./deploy/deployer.sh
```

---

## Voie B — cPanel « Setup Node.js App »

> **Un guide dédié existe : [`10-LWS-CPANEL.md`](10-LWS-CPANEL.md).** Il est écrit
> écran par écran, s'appuie sur `npm run package:cpanel` (paquet déjà construit,
> pour ne pas heurter la limite de mémoire du mutualisé) et a été vérifié de bout
> en bout. Le résumé qui suit reste pour mémoire.

Si votre hébergement mutualisé propose *Setup Node.js App* (Passenger), c'est
jouable, avec deux réserves : la compilation de `better-sqlite3` échoue chez
certains hébergeurs, et le processus est parfois arrêté après une période
d'inactivité — le premier visiteur du matin attend alors quelques secondes.

1. **Domaines → Créer un sous-domaine** `boost` sur `wakabileguide.com`.
2. **Setup Node.js App → Create Application**
   - Node : 20 ou 22
   - Application root : `wakabi-boost`
   - Application URL : `boost.wakabileguide.com`
   - Startup file : `server.js`
3. Téléversez le zip dans `wakabi-boost/` et décompressez-le.
4. Dans **Environment variables**, ajoutez `WAKABI_BASE_URL`, `WAKABI_DB`,
   `WAKABI_UPLOADS`, `NODE_ENV=production`.
5. Ouvrez le terminal de l'application (bouton *Run JS script* ou SSH) :
   ```bash
   npm ci && npm run build
   cp -r .next/standalone/. . && cp -r .next/static .next/static && cp -r public .
   ```
6. **Restart** l'application.

Si `npm ci` échoue sur `better-sqlite3`, votre hébergeur n'autorise pas la
compilation native : passez à la voie A ou C.

---

## Voie C — Docker

Un `Dockerfile` est fourni. Il compile les modules natifs à part et ne garde
dans l'image finale que la sortie autonome.

```bash
docker build -t wakabi-boost .

docker run -d --name wakabi-boost --restart unless-stopped \
  -p 127.0.0.1:3000:3000 \
  -e WAKABI_BASE_URL=https://boost.wakabileguide.com \
  -v /var/lib/wakabi-boost:/data \
  wakabi-boost
```

Le volume `/data` porte la base et les cadres : le conteneur se remplace, elles
restent. Nginx et certbot se posent ensuite comme en A.6.

> **Cette voie n'a pas été vérifiée ici** : aucun démon Docker n'était
> disponible dans mon environnement. Le fichier est écrit avec soin, mais la
> voie A est celle que j'ai réellement exécutée.

---

## 3. Vérifier que c'est en ligne

```bash
curl -I https://boost.wakabileguide.com          # 200
curl -I https://boost.wakabileguide.com/decors   # 200
```

Puis, depuis un téléphone — c'est là que ça compte :

1. Ouvrez `/decors`, choisissez un décor, importez une photo.
2. Téléchargez le badge.
3. **Scannez son QR avec l'appareil photo du téléphone.**
   Il doit ouvrir `https://boost.wakabileguide.com/qr/XXXXXXXXXX`
   et afficher « Badge valide ».
4. Connectez-vous en équipe, ouvrez le lien du QR : le bouton
   « Valider l'entrée » apparaît, code déjà rempli.

**Si l'étape 3 ouvre autre chose que `boost.wakabileguide.com`,
`WAKABI_BASE_URL` est mal réglé.** Corrigez-le et redémarrez avant d'émettre le
moindre badge réel : ceux déjà créés sont perdus.

---

## 4. Les sauvegardes

SQLite tient dans un fichier, mais **le copier pendant une écriture donne une
copie corrompue.** Utilisez la commande dédiée :

```bash
sudo -u wakabi sqlite3 /var/lib/wakabi-boost/wakabi.sqlite \
  ".backup '/var/backups/wakabi-$(date +%F).sqlite'"

sudo tar czf /var/backups/wakabi-cadres-$(date +%F).tar.gz \
  -C /var/lib/wakabi-boost uploads
```

En tâche quotidienne (`sudo crontab -e`) :

```
15 3 * * * sqlite3 /var/lib/wakabi-boost/wakabi.sqlite ".backup '/var/backups/wakabi-$(date +\%F).sqlite'"
30 3 * * * tar czf /var/backups/wakabi-cadres-$(date +\%F).tar.gz -C /var/lib/wakabi-boost uploads
45 3 * * * find /var/backups -name 'wakabi-*' -mtime +30 -delete
```

Et sortez ces copies de la machine. Une sauvegarde qui vit sur le serveur
qu'elle protège ne protège de rien.

---

## 5. Les dépendances

`npm audit` doit rendre **0 vulnérabilité**. C'était le cas au 26/08/2026, après
trois relèvements :

| Paquet | De | À | Pourquoi |
|---|---|---|---|
| `next` | 15.5.4 | 15.5.24 | Une **exécution de code à distance** dans le protocole React Flight, plus une trentaine d'autres avis |
| `sharp` | 0.33.5 | 0.35.3 | Quatre CVE héritées de libvips |
| `postcss` | 8.4.x | 8.5.26 (`overrides`) | Lecture de fichiers arbitraires via `sourceMappingURL` |

Le relèvement de `postcss` passe par un `overrides` dans `package.json` : la
version fautive est celle que `next` embarque, et `npm` ne la remplacerait
autrement qu'en passant à Next 16 — une majeure, à ne pas prendre la veille
d'une mise en ligne.

Vérifiez avant chaque déploiement :

```bash
npm audit
```

Un avis critique sur une application exposée n'attend pas la prochaine
itération.

---

## 6. Ce qui reste à brancher après la mise en ligne

| Point | Sans quoi |
|---|---|
| **SMTP** | Le lien de vérification d'adresse s'affiche à l'écran au lieu de partir par courriel. Le circuit est complet, il manque le transport. |
| **Les vrais cadres** | Les décors installés par le seed sont des placeholders. |
| **Un compte d'équipe à vous** | Les comptes de démonstration ont un mot de passe public, écrit dans ce dépôt. |

---

## 7. Rester en dehors du chemin de WordPress

`boost.wakabileguide.com` est un sous-domaine **indépendant** :

- il ne partage ni base, ni thème, ni plugins avec le WordPress ;
- casser l'un ne casse pas l'autre ;
- le WordPress reste ce qu'il a toujours été, le back-end éditorial des
  articles.

Un seul point de contact : le garde-fou que vous m'aviez demandé de conserver.
Un décor de partenaire ne peut rediriger que vers `wakabileguide.com` ou l'un
de ses sous-domaines — la règle accepte `host === h || host.endsWith('.' + h)`,
donc **`boost.wakabileguide.com` passe déjà**, sans rien changer. Pour ouvrir un
autre domaine, `WAKABI_REDIRECT_HOSTS` dans `src/core/template.schema.ts` est la
seule ligne à toucher.
