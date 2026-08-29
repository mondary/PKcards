![Project icon](icon.png)

[🇫🇷 FR](README.md) · [🇬🇧 EN](README_en.md)

# PKcards

Découvrez plus de 700 jeux de cartes en swipant : parcourez, apprenez les règles et sauvegardez vos favoris.

## ✅ Fonctionnalités

- Catalogue de 700+ jeux de cartes avec règles complètes
- Interface de découverte par swipe (oui / non)
- Fiches enrichies : difficulté, type, autres noms, nombre de joueurs et de cartes
- Règle courte et version longue pour chaque jeu
- Miniatures de cartes dans les règles : les mentions de cartes (« 7 de trèfle », « A♥️ ») s'affichent automatiquement en visuels (v3)
- Une seule fiche par jeu : versions maison 👑 et classique, avec un drawer limité aux différences éditoriales vérifiées et tous les alias fusionnés (v3)
- Thème V5 Main optionnel : navigation inédite en éventail de cartes (rail familles + main à faire défiler, couper le paquet, flèches/drag/molette) (v3)
- 10 thèmes classés dans un écran Réglages plein écran, dont Compare : recherche sticky, bibliothèque directe et drawer de variantes (v3)
- Recherche des règles en vidéo sur YouTube en un clic
- Votez pour vos jeux préférés et consultez le classement « Meilleurs jeux »
- Favoris synchronisés sur tous vos appareils via un simple email (sans mot de passe)
- Filtres et catégories par couleur

## 🧠 Utilisation

1. Swipez une carte vers la droite (oui) ou la gauche (non) pour découvrir les jeux.
2. Ouvrez une fiche pour lire la règle courte puis la version longue.
3. Ajoutez un jeu à vos favoris avec le bouton ♥.

## ⚙️ Réglages

- Filtrez le catalogue via le bouton de filtre du header.
- Les favoris sont liés à un email (sans mot de passe) et stockés côté serveur, ce qui permet de les retrouver sur n'importe quel appareil.
- Votez pour un jeu depuis sa fiche ; le classement est visible via le bouton 🏆 du header.

## 🧾 Commandes

```bash
node assets/scripts/build.js       # génère site/v1/data.js depuis assets/rules/rules_original/*.md
```

## 📦 Build & Package

Le catalogue est généré depuis les fichiers Markdown de `assets/rules/rules_original/`. Après toute modification des règles, régénérez les données :

```bash
node assets/scripts/build.js
```

## 🗄️ Backend (votes & favoris)

Les votes et favoris sont persistés côté serveur par `site/api.php` dans une base **SQLite** (`site/data/rules.sqlite`, créée automatiquement au premier appel).

- **À uploader pour V1** : tout le contenu du dossier `site/v1/` — `index.html`, `style.css`, `app.js`, `data.js`, `api.php`, `data/.htaccess`. Le dossier `scripts/` sert uniquement à générer `site/v1/data.js` en local et ne doit **pas** être uploadé.
- Nécessite un **hébergement PHP** (PHP 8+, extension `pdo_sqlite`). Le reste du site (`index.html`, `app.js`, `style.css`, `data.js`) reste 100 % statique.
- Le dossier `site/data/` doit être **inscriptible par PHP** (chmod 755/775) pour que la base puisse s'y créer.
- Sans backend PHP, le catalogue fonctionne quand même ; seuls les votes et la synchro des favoris sont indisponibles.
- La base est protégée de tout accès HTTP direct par `site/data/.htaccess` (Apache). Sous **nginx**, bloquez l'accès à `/data/` dans la configuration serveur.
- Endpoints : `POST vote`, `GET top`, `POST fav_add` / `fav_remove`, `GET favorites`.

## 🧪 Installation

Servez le dossier `site/` avec un serveur PHP pour activer votes et favoris :

```bash
cd site
php -S localhost:8000
# puis ouvrez http://localhost:8000
```

Pour un aperçu du catalogue seul (sans votes/favoris), un serveur statique suffit (`python3 -m http.server 8000`).

## 🛠️ Outils

- **Compteur de scores** (`tools/score/`) — créez une table par groupe et alternez entre leurs parties, joueurs et palmarès séparés ; points par manche, totaux automatiques et historique corrigeable. 100 % statique, pensé mobile. En ligne : https://mondary.design/pk/-Games-cards/score/
- **Tracker PV Régicide** (`tools/regicide-hp.html`) — compteur de points de vie pour le jeu Régicide. Ouvrez le fichier dans un navigateur mobile. Grille de cartes avec mode zoom plein écran, badges d'attaque par rang (Valet ⚔10, Dame ⚔15, Roi ⚔20), persistance automatique des PV.
- **Images des cartes** (`assets/cards/`) — scans des 55 cartes, utilisés par le tracker et les miniatures des règles v3.

## 📋 Changelog

Voir le [CHANGELOG](CHANGELOG.md) pour l'historique complet.

## 🔗 Liens

- **App v3** (reader, thèmes, miniatures de cartes) : [`site/v3/`](site/v3/) · en ligne : https://mondary.design/pk/-Games-cards/cards3/
- **App v2** (mobile, HTMX) : [`site/v2/`](site/v2/) · en ligne : https://mondary.design/pk/site/v2/
- App v1 (archive) : [`site/v1/`](site/v1/)
- Règles des jeux : dossier [`assets/rules/`](assets/rules/)
- Règles CLM (Régicide, Yaniv, Scoundrel…) : dossier [`assets/rules/rules_clm/`](assets/rules/rules_clm/)
- Tracker Régicide : [`tools/regicide/`](tools/regicide/) · en ligne : https://mondary.design/pk/tools/regicide/
- Scripts de build : dossier [`scripts/`](scripts/)
