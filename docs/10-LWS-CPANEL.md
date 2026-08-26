# Déployer sur LWS — cPanel, pas à pas

Ce document remplace `09-DEPLOIEMENT.md` si votre hébergement est un **cPanel
mutualisé LWS**. Les chiffres et les commandes ci-dessous ont été vérifiés sur
le paquet que vous allez téléverser.

---

## Étape 0 — La question qui décide de tout

**Votre offre LWS propose-t-elle Node.js ?**

1. Connectez-vous à votre cPanel.
2. Cherchez `Node` dans la barre de recherche en haut.
3. Vous devez voir **« Setup Node.js App »** (ou « Configurer une application
   Node.js »).

| Ce que vous voyez | Ce que ça veut dire |
|---|---|
| **« Setup Node.js App » existe** | Continuez à l'étape 1. |
| **Rien** | Wakabi Boost ne peut pas tourner sur cette offre. Il faut un VPS — voir `09-DEPLOIEMENT.md`, voie A. Aucun réglage ne contournera ça : l'application a besoin d'un processus Node permanent, pas de PHP. |

> Je ne peux pas vérifier à votre place quelles offres LWS incluent Node.js —
> cela dépend de votre formule. Cette vérification en trois clics est la
> première chose à faire : tout le reste en dépend.

---

## Pourquoi un paquet spécial pour cPanel

Deux mesures prises sur le code réel expliquent la méthode qui suit.

**`next build` demande 843 Mo de mémoire et 58 secondes de processeur.** Un
mutualisé plafonne souvent à 1 Go, parfois 512 Mo, et surveille le processeur.
Construire sur le serveur, c'est se faire tuer le processus en plein build,
avec un message obscur. **L'application est donc livrée déjà construite.**

**Les modules natifs sont liés à la bibliothèque C de la machine qui les a
produits.** Expédier les miens vers un serveur plus ancien donnerait
`GLIBC_2.38 not found`. Ils sont donc installés **sur votre serveur**, où npm
télécharge la bonne variante. Bonne nouvelle vérifiée : `better-sqlite3` et
`sharp` fournissent des binaires précompilés — **aucun compilateur n'est
nécessaire**, ce qui est justement ce que les mutualisés n'ont pas.

---

## Étape 1 — Le sous-domaine

cPanel → **Domaines** → **Créer un domaine** (ou « Sous-domaines »).

| Champ | Valeur |
|---|---|
| Domaine | `boost.wakabileguide.com` |
| Racine du document | *laissez la valeur proposée* |

Décochez « Partager le document root » si la case existe.

Vérifiez ensuite depuis votre machine :

```bash
dig +short boost.wakabileguide.com
```

L'adresse IP de votre hébergement doit apparaître. Si rien ne sort, le DNS
n'est pas encore propagé — attendez avant de continuer, sinon le certificat
HTTPS échouera.

---

## Étape 2 — Le dossier des données, hors de l'application

**C'est l'étape qu'on oublie, et elle coûte cher.** La base et les cadres
téléversés doivent survivre au prochain téléversement de l'application. S'ils
vivent dans le dossier de l'application, vous les effacerez à la première mise
à jour — comptes, campagnes et badges compris.

cPanel → **Gestionnaire de fichiers** → placez-vous dans `/home/VOTRE_COMPTE`
(la racine, pas `public_html`) → **+ Dossier** :

```
wakabi-donnees
```

Puis, dans `wakabi-donnees`, créez un second dossier :

```
uploads
```

Notez le chemin complet — il apparaît en haut du gestionnaire de fichiers :

```
/home/VOTRE_COMPTE/wakabi-donnees
```

Vous en aurez besoin à l'étape 5.

---

## Étape 3 — Téléverser l'application

1. Gestionnaire de fichiers → placez-vous dans `/home/VOTRE_COMPTE`.
2. **+ Dossier** → `wakabi-boost`.
3. Entrez dedans → **Téléverser** → choisissez `wakabi-boost-cpanel.zip`.
4. Retour au gestionnaire → clic droit sur le zip → **Extraire**.
5. L'extraction crée un sous-dossier `wakabi-boost`. **Entrez dedans, et
   remontez son contenu d'un niveau** (sélectionner tout → Déplacer), de sorte
   que `app.js` se trouve directement dans `/home/VOTRE_COMPTE/wakabi-boost/`.
6. Supprimez le zip et le sous-dossier vide.

Vous devez voir, à la racine de `wakabi-boost` :

```
app.js   charger-env.js   next.config.js   package.json
seed.js  creer-admin.js   .env.exemple
.next/   public/   src/
```

> Si `.next` n'apparaît pas, activez **Paramètres → Afficher les fichiers
> cachés** dans le gestionnaire de fichiers. Sans `.next`, rien ne démarrera :
> c'est l'application construite.

---

## Étape 4 — Créer l'application Node.js

cPanel → **Setup Node.js App** → **Create Application**.

| Champ | Valeur |
|---|---|
| **Node.js version** | La plus élevée proposée — **20 ou 22** |
| **Application mode** | `Production` |
| **Application root** | `wakabi-boost` |
| **Application URL** | `boost.wakabileguide.com` |
| **Application startup file** | `app.js` |

**Create**. cPanel crée un environnement virtuel et affiche, en haut de la
page, une commande commençant par `source /home/.../bin/activate`.

**Copiez cette commande.** Elle vous servira à chaque fois que vous ouvrirez un
terminal : sans elle, `npm` et `node` ne sont pas ceux de votre application.

---

## Étape 5 — Le fichier de réglages

Gestionnaire de fichiers → dans `wakabi-boost` → renommez `.env.exemple` en
`.env` → clic droit → **Modifier**.

```
WAKABI_BASE_URL=https://boost.wakabileguide.com

WAKABI_DB=/home/VOTRE_COMPTE/wakabi-donnees/wakabi.sqlite
WAKABI_UPLOADS=/home/VOTRE_COMPTE/wakabi-donnees/uploads

NODE_ENV=production
```

Remplacez `VOTRE_COMPTE` par votre identifiant LWS — celui qui apparaît dans le
chemin en haut du gestionnaire de fichiers.

> **`WAKABI_BASE_URL` est le réglage à ne pas rater.** Il est incrusté dans le
> QR de **chaque badge**, donc dans un fichier PNG que l'invité télécharge,
> garde et partage sur WhatsApp. Un badge émis avec une mauvaise adresse est
> faux pour toujours — on ne rappelle pas une image déjà partagée.
>
> L'application **refuse de démarrer** si cette variable manque, plutôt que
> d'émettre mille QR morts. Si vous voyez ce refus dans le journal, c'est le
> garde-fou qui fonctionne.

---

## Étape 6 — Installer les dépendances

Deux voies. La première est plus simple, la seconde plus lisible quand ça
coince.

### Voie simple — le bouton

**Setup Node.js App** → votre application → **Run NPM Install**.

### Voie terminal — recommandée

cPanel → **Terminal** :

```bash
# la commande copiée à l'étape 4
source /home/VOTRE_COMPTE/nodevenv/wakabi-boost/22/bin/activate
cd /home/VOTRE_COMPTE/wakabi-boost

npm install --omit=dev
```

Comptez une à trois minutes. À la fin, vous devez lire `found 0
vulnerabilities`.

> `--omit=dev` n'est pas un détail : il évite d'installer les outils de
> développement, inutiles en production et lourds. Le paquet est conçu pour
> fonctionner sans eux.

---

## Étape 7 — Préparer la base

Toujours dans le terminal, environnement activé :

```bash
cd /home/VOTRE_COMPTE/wakabi-boost

# la base, les décors de démonstration
node seed.js

# VOTRE compte d'équipe — mot de passe à vous, pas celui du dépôt
node creer-admin.js "vous@wakabileguide.com" "Votre Nom" "un-mot-de-passe-long-et-unique"
```

Le second refuse un mot de passe de moins de douze caractères, et une adresse
mal formée. Relancé plus tard sur la même adresse, il remplace le mot de passe
— c'est aussi la sortie de secours si vous perdez l'accès.

Vérifiez que la base est au bon endroit :

```bash
ls -la /home/VOTRE_COMPTE/wakabi-donnees/
```

Vous devez y voir `wakabi.sqlite`. **S'il est apparu dans
`wakabi-boost/.data/` à la place, votre `.env` n'a pas été lu** : vérifiez son
nom (`.env`, pas `.env.txt`) et les chemins.

---

## Étape 8 — Démarrer

**Setup Node.js App** → votre application → **Restart**.

Ouvrez `https://boost.wakabileguide.com`.

En cas d'écran d'erreur, le journal est votre ami :

```bash
tail -50 /home/VOTRE_COMPTE/logs/boost.wakabileguide.com.error.log
```

Au démarrage réussi, vous devez y lire :

```
[wakabi] 4 variable(s) lue(s) dans .env
[wakabi] adresse publique : https://boost.wakabileguide.com
[wakabi] prêt sur le port ...
```

---

## Étape 9 — Le certificat HTTPS

cPanel → **SSL/TLS Status** → cochez `boost.wakabileguide.com` → **Run AutoSSL**.

Comptez quelques minutes. LWS active aussi Let's Encrypt depuis son espace
client selon les offres.

Forcez ensuite le HTTPS — cPanel → **Domaines** → interrupteur **Force HTTPS
Redirect** sur `boost.wakabileguide.com`.

> Sans HTTPS, le cookie de session perd son attribut `Secure` et la connexion
> ne tiendra pas d'une page à l'autre. Ce n'est pas optionnel.

---

## Étape 10 — Vérifier, depuis un téléphone

C'est le seul test qui compte.

1. Ouvrez `boost.wakabileguide.com/decors`, choisissez un décor.
2. Importez une photo, recadrez, écrivez un prénom.
3. Téléchargez le badge.
4. **Scannez son QR avec l'appareil photo du téléphone.**

Il doit ouvrir `https://boost.wakabileguide.com/qr/XXXXXXXXXX` et afficher
**« Badge valide »**.

5. Connectez-vous avec votre compte d'équipe, rouvrez ce lien : le bouton
   **« Valider l'entrée »** apparaît, code déjà rempli.

**Si l'étape 4 ouvre autre chose, arrêtez tout et corrigez `WAKABI_BASE_URL`**
avant d'émettre le moindre badge réel.

---

## Quand ça coince

| Symptôme | Cause la plus probable |
|---|---|
| **Page blanche, ou 503** | L'application n'a pas démarré. Lisez le journal d'erreur (étape 8). |
| `Cannot find module 'next'` | `npm install --omit=dev` n'a pas tourné, ou pas dans le bon dossier. |
| `WAKABI_BASE_URL est absent` | Le `.env` n'est pas lu : mauvais nom de fichier, ou pas à la racine de `wakabi-boost`. |
| `GLIBC_... not found` | Des modules natifs venus d'ailleurs. Supprimez `node_modules` et relancez `npm install --omit=dev` **sur le serveur**. |
| **Le style ne s'affiche pas** | Le dossier `.next` est incomplet. Retéléversez le zip en entier. |
| **Erreur au téléversement d'un cadre** | Droits sur `wakabi-donnees/uploads`, ou chemin `WAKABI_UPLOADS` erroné. |
| **La première visite du matin est lente** | Le mutualisé arrête le processus après une période d'inactivité. Normal sur cette formule ; un VPS n'a pas ce comportement. |
| **Le build échoue si vous le lancez** | Ne le lancez pas sur le serveur : 843 Mo de mémoire. Le paquet est déjà construit. |

---

## Mettre à jour, plus tard

```bash
# sur votre machine
npm run package:cpanel
```

Puis :

1. Téléversez le nouveau zip et extrayez-le **par-dessus** `wakabi-boost`.
2. Terminal : `npm install --omit=dev` (seulement si les dépendances ont changé).
3. **Restart** dans Setup Node.js App.

Le `.env` et le dossier `wakabi-donnees` ne bougent pas : vos données restent.

---

## Les sauvegardes

SQLite tient dans un fichier, mais **le copier pendant une écriture donne une
copie corrompue.**

cPanel → **Sauvegarde** → sauvegardez le dossier `wakabi-donnees`. Et
téléchargez-la : une sauvegarde qui vit sur le serveur qu'elle protège ne
protège de rien.

Si votre offre donne accès aux tâches planifiées (**Cron Jobs**), une copie
quotidienne :

```
15 3 * * * cp /home/VOTRE_COMPTE/wakabi-donnees/wakabi.sqlite /home/VOTRE_COMPTE/sauvegardes/wakabi-$(date +\%F).sqlite
```

---

## Ce qui reste à brancher

| Point | Sans quoi |
|---|---|
| **SMTP** | Le lien de vérification d'adresse s'affiche à l'écran au lieu de partir par courriel. Le circuit est complet, il manque le transport. |
| **Les vrais cadres** | Les décors installés par `seed.js` sont des placeholders. |
| **Supprimer les comptes de démonstration** | `seed.js` crée trois comptes dont le mot de passe est public. Une fois le vôtre créé (étape 7), supprimez-les depuis `/admin/comptes`. |
