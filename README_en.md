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
- Vote for your favorite games and check the "Best games" ranking
- Favorites synced across all your devices via a simple email (no password)
- Filters and color-coded categories

## 🧠 Usage

1. Swipe a card right (yes) or left (no) to discover games.
2. Open a sheet to read the short rule then the long version.
3. Add a game to your favorites with the ♥ button.

## ⚙️ Settings

- Filter the catalog via the filter button in the header.
- Favorites are linked to an email (no password) and stored server-side, so you can find them again on any device.
- Vote for a game from its sheet; the ranking is available via the 🏆 button in the header.

## 🧾 Commands

```bash
node site/build.js        # generates site/data.js from rules/*.md
```

## 📦 Build & Package

The catalog is generated from the Markdown files in `rules/`. After editing any rule, regenerate the data:

```bash
node site/build.js
```

## 🗄️ Backend (votes & favorites)

Votes and favorites are persisted server-side by `site/api.php` in a **SQLite** database (`site/data/pk.sqlite`, created automatically on first call).

- Requires **PHP hosting** (PHP 8+, `pdo_sqlite` extension). The rest of the site (`index.html`, `app.js`, `style.css`, `data.js`) stays 100% static.
- Without a PHP backend the catalog still works; only voting and favorites sync are unavailable.
- The database is protected from direct HTTP access by `site/data/.htaccess` (Apache). On **nginx**, block access to `/data/` in the server configuration.
- Endpoints: `POST vote`, `GET top`, `POST fav_add` / `fav_remove`, `GET favorites`.

## 🧪 Installation

Serve the `site/` folder with a PHP server to enable votes and favorites:

```bash
cd site
php -S localhost:8000
# then open http://localhost:8000
```

For a catalog-only preview (without votes/favorites), a static server is enough (`python3 -m http.server 8000`).

## 📋 Changelog

See [CHANGELOG](CHANGELOG.md) for full history.

## 🔗 Links

- Game rules: [`rules/`](rules/) folder
