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
- Favoris sauvegardés localement
- Filtres et catégories par couleur

## 🧠 Utilisation

1. Swipez une carte vers la droite (oui) ou la gauche (non) pour découvrir les jeux.
2. Ouvrez une fiche pour lire la règle courte puis la version longue.
3. Ajoutez un jeu à vos favoris avec le bouton ♥.

## ⚙️ Réglages

- Filtrez le catalogue via le bouton de filtre du header.
- Les favoris sont conservés dans le stockage local du navigateur.

## 🧾 Commandes

```bash
node site/build.js        # génère site/data.js depuis rules/*.md
```

## 📦 Build & Package

Le catalogue est généré depuis les fichiers Markdown de `rules/`. Après toute modification des règles, régénérez les données :

```bash
node site/build.js
```

## 🧪 Installation

Application web statique, aucune dépendance à installer. Servez le dossier `site/` :

```bash
cd site
python3 -m http.server 8000
# puis ouvrez http://localhost:8000
```

## 📋 Changelog

Voir le [CHANGELOG](CHANGELOG.md) pour l'historique complet.

## 🔗 Liens

- Règles des jeux : dossier [`rules/`](rules/)
