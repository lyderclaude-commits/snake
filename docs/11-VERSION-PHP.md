# La version PHP — décompresser, ouvrir une page, c'est en ligne

Cette version existe pour une raison simple : sur un mutualisé, un site PHP
avec une base de données se déploie en glissant un dossier. Une application
Node.js demande un processus permanent, une compilation et 843 Mo de mémoire.

---

## Installer

1. Décompressez `wakabi-boost-php.zip` dans le dossier de votre sous-domaine.
2. Ouvrez `https://boost.wakabileguide.com/install.php`.
3. Répondez à trois questions. C'est fini.
4. **Supprimez `install.php`.**

L'installateur commence par afficher ce que propose votre hébergement — version
de PHP, extensions, droits d'écriture. S'il manque quelque chose
d'indispensable, il le dit et s'arrête, plutôt que d'échouer à mi-chemin.

### La base de données

| Choix | Quand |
|---|---|
| **SQLite** *(recommandé pour démarrer)* | Rien à créer, rien à saisir. Tout tient dans `donnees/wakabi.sqlite`. |
| **MySQL / MariaDB** | Créez d'abord la base dans cPanel, puis donnez ses identifiants. Préférable dès que le trafic monte. |

Les deux ont été vérifiés de bout en bout : **26 scénarios, 26 réussis** sur
chacun.

---

## Ce qui change, et ce qui ne change pas

**Ce qui ne change pas — c'est l'essentiel.** Le Studio est le *même code*.
`renderScene`, le pipeline photo, le garde-fou anti-vide et le partage viennent
de `src/core`, importés tels quels et empaquetés par `npm run php:bundle`. Un
badge produit ici et un badge produit par la version Next.js sont le même
fichier : mêmes positions, même filigrane, même QR.

La photo ne quitte toujours pas l'appareil. Elle est décodée, recadrée et
exportée dans le navigateur ; le serveur ne reçoit que le jeton du badge.

**Ce qui change :**

| | Next.js | PHP |
|---|---|---|
| Hébergement | Node permanent, 843 Mo pour construire | N'importe quel mutualisé |
| Installation | Terminal, systemd, Nginx | Une page web |
| Base | SQLite | SQLite **ou** MySQL |
| Validation du gabarit | zod | code PHP, vérifié contre zod |
| QR Code | bibliothèque `qrcode` | encodeur écrit ici, vérifié contre elle |
| Poids du JavaScript | 128 Ko | **11 Ko** |

Les 11 Ko ne sont pas une coquetterie : la contrainte C1 est un Android
d'entrée de gamme sur données mobiles.

---

## Les deux morceaux réécrits, et comment ils sont vérifiés

Porter un contrat de données et un encodeur QR sans filet, c'est produire des
bugs invisibles : un QR qui ne se scanne pas, un gabarit auquel il manque un
champ. Les deux sont donc **comparés à l'implémentation d'origine**.

### Le QR — `npx tsx scripts/verifier-qr.ts`

Aucune bibliothèque à installer sur un mutualisé : l'encodeur est écrit dans
`app/qr.php` — mode octet, correction M, versions 1 à 10, Reed-Solomon compris.

Il est comparé **module par module** à la bibliothèque `qrcode` de Node, sur
douze charges utiles de 1 à 190 caractères. Les douze matrices sont identiques.

> Deux choses ont failli passer inaperçues. Le polynôme générateur était juste,
> mais **le placement des bits de format était en miroir** — et il écrasait au
> passage le module toujours sombre. Le QR *paraissait* correct ; rien ne
> l'aurait décodé.
>
> Et la comparaison elle-même était fausse au départ : laissée libre, la
> bibliothèque de référence découpe la charge utile en segments mixtes
> (octet + alphanumérique) pour gagner quelques modules. Comparer à ce
> résultat-là faisait échouer un encodeur correct. Le mode octet est désormais
> forcé des deux côtés.

### Le gabarit — `npx tsx scripts/verifier-gabarit.ts`

Le PHP produit un gabarit pour chaque disposition ; le script le fait valider
par **le vrai schéma zod**, puis compare champ par champ ce que zod aurait
ajouté et ce que PHP écrit.

> Sept valeurs par défaut manquaient au premier essai. L'une d'elles,
> `layers.0.mask`, faisait planter le rendu : le Studio affichait « cette image
> n'a pas pu être lue » sur toutes les photos. C'est exactement le genre de
> défaut qu'un port sans vérification laisse passer.

---

## Vérifier soi-même

```bash
npm run php:serve        # http://127.0.0.1:3600
```

Ouvrez `install.php`, installez, puis :

```bash
npm run php:e2e          # 26 scénarios, dans un vrai navigateur
npm run php:verifier     # QR et gabarit contre les implémentations d'origine
```

Contre une base MySQL :

```bash
BASE_URL=http://127.0.0.1:3700 npm run php:e2e
```

### Les 26 scénarios

| Groupe | Ce qui est vérifié |
|---|---|
| Vitrine et catalogue | La page d'accueil, les décors publiés |
| Le Studio | Le QR **visible dans l'aperçu** (45 % de blanc mesuré au coin bas-gauche), le jeton émis |
| La page du QR | `?p=qr&jeton=…` répond, annonce le badge valide, refuse un code inventé |
| Contrôle d'entrée | Entrée validée, rescan refusé, code inconnu refusé |
| Koris | Solde crédité **au scan**, pas au téléchargement |
| Le garde-fou | Une redirection hors domaine Wakabi est refusée |
| Le pré-vol | Un cadre opaque ne rejoint jamais la file |
| Modération | Rapport affiché, approbation traitée, décor réellement ouvrable ensuite |
| Sécurité | Cinq routes refusées à un anonyme, participant écarté de l'administration, fichiers internes non servis |
| Limitation de débit | « Trop de tentatives. Réessayez dans 15 minutes. » |

---

## Ce que la version PHP fait aussi

Tout le produit, pas un sous-ensemble :

- vitrine, catalogue, Studio sans compte ;
- comptes participant, partenaire, équipe ;
- création de décor par un partenaire, en trois blocs, sans compétence graphique ;
- **les 7 contrôles du pré-vol**, exécutés côté serveur avec GD ;
- file de relecture, motif obligatoire au refus, notifications ;
- QR incrusté, page de badge, contrôle d'entrée, Koris ;
- tableau de bord, courbe sur 14 jours, gestion des comptes ;
- limitation de débit, jetons anti-CSRF, sessions `httpOnly`.

---

## Sécurité — ce qui est en place

| | |
|---|---|
| Mots de passe | `password_hash()` — Argon2 ou bcrypt selon l'hébergeur |
| Sessions | Cookie `httpOnly`, `SameSite=Lax`, `Secure` dès que le site est en HTTPS |
| Formulaires | Jeton anti-CSRF vérifié sur **chaque** POST |
| Injection SQL | Requêtes préparées partout ; `app/depot.php` est la seule frontière avec le SQL |
| XSS | `e()` sur toute valeur venue de la base ; le gabarit est encodé avec `JSON_HEX_TAG` |
| Téléversements | PNG et WebP seulement, vérifiés par `getimagesize` — pas par l'extension. Le SVG est refusé. |
| Traversée de chemin | Le nom d'un cadre doit correspondre à `^[0-9a-f-]{36}\.(png\|webp)$` |
| Dossier de données | Hors de la racine servie, plus un `.htaccess` qui interdit l'accès |
| Auto-rétrogradation | Un administrateur ne peut ni se suspendre ni se rétrograder — sinon l'installation pourrait se retrouver sans administrateur |

> **Le `.htaccess` suppose Apache**, ce qu'utilise LWS. Sur un autre serveur,
> vérifiez que `donnees/`, `app/` et `config.php` ne sont pas servis.

---

## Mettre à jour

```bash
npm run package:php
```

Téléversez le nouveau zip **par-dessus**, sans toucher à `config.php` ni au
dossier `donnees/`. Vos comptes, campagnes et badges restent.

---

## Ce qui reste

| Point | Sans quoi |
|---|---|
| **SMTP** | Aucun e-mail n'est envoyé. Les notifications sont dans l'application, pas dans la boîte de réception. |
| **Les vrais cadres** | Les trois décors installés sont des placeholders. |
| **Lecture caméra du QR** | L'agent d'entrée saisit le code. Ça marche, ça ne tient pas une file d'attente. |
| **Sauvegardes** | Sauvegardez `donnees/` (ou exportez la base MySQL depuis cPanel), et sortez la copie du serveur. |
