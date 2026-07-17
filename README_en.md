![Project icon](icon.png)

[🇫🇷 FR](README.md) · [🇬🇧 EN](README_en.md)

# PKcards

Discover 160+ card games by swiping: browse, learn the rules and save your favorites.

## ✅ Features

- Catalog of 160+ card games with full rules
- Swipe-based discovery interface (yes / no)
- Rich game sheets: difficulty, type, other names, number of players and cards
- Short rule and long version for each game
- One-click YouTube search for video rules
- Favorites saved locally
- Filters and color-coded categories

## 🧠 Usage

1. Swipe a card right (yes) or left (no) to discover games.
2. Open a sheet to read the short rule then the long version.
3. Add a game to your favorites with the ♥ button.

## ⚙️ Settings

- Filter the catalog via the filter button in the header.
- Favorites are kept in the browser's local storage.

## 🧾 Commands

```bash
node site/build.js        # generates site/data.js from rules/*.md
```

## 📦 Build & Package

The catalog is generated from the Markdown files in `rules/`. After editing any rule, regenerate the data:

```bash
node site/build.js
```

## 🧪 Installation

Static web app, no dependencies to install. Serve the `site/` folder:

```bash
cd site
python3 -m http.server 8000
# then open http://localhost:8000
```

## 📋 Changelog

See [CHANGELOG](CHANGELOG.md) for full history.

## 🔗 Links

- Game rules: [`rules/`](rules/) folder
