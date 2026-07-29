![Project icon](icon.png)

[🇫🇷 FR](README.md) · [🇬🇧 EN](README_en.md)

# PKcards

Découvrez plus de 160 jeux de cartes en swipant : parcourez, apprenez les règles et sauvegardez vos favoris.

## ✅ Fonctionnalités

- Catalogue de 160+ jeux de cartes avec règles complètes
- Interface de découverte par swipe (oui / non)
- Fiches enrichies : difficulté, type, autres noms, nombre de joueurs et de cartes
- Règle courte et version longue pour chaque jeu
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
node scripts/build.js       # génère site/data.js depuis rules/*.md
```

## 📦 Build & Package

Le catalogue est généré depuis les fichiers Markdown de `rules/`. Après toute modification des règles, régénérez les données :

```bash
node scripts/build.js
```

## 🗄️ Backend (votes & favoris)

Les votes et favoris sont persistés côté serveur par `site/api.php` dans une base **SQLite** (`site/data/rules.sqlite`, créée automatiquement au premier appel).

- **À uploader** : tout le contenu du dossier `site/` (et rien d'autre) — `index.html`, `style.css`, `app.js`, `data.js`, `api.php`, `data/.htaccess`. Le dossier `scripts/` sert uniquement à générer `data.js` en local et ne doit **pas** être uploadé.
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

- **Tracker PV Régicide** (`tools/regicide-hp.html`) — compteur de points de vie pour le jeu Régicide. Ouvrez le fichier dans un navigateur mobile. Grille de cartes avec mode zoom plein écran, badges d'attaque par rang (Valet ⚔10, Dame ⚔15, Roi ⚔20), persistance automatique des PV.
- **Images des cartes** (`tools/cards/`) — scans des cartes utilisés par le tracker.

## 📋 Changelog

Voir le [CHANGELOG](CHANGELOG.md) pour l'historique complet.

## 🔗 Liens

- Règles des jeux : dossier [`rules/`](rules/)
- Tracker Régicide : [`tools/regicide-hp.html`](tools/regicide-hp.html)
- Scripts de build : dossier [`scripts/`](scripts/)
