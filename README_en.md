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
node scripts/build.js       # generates site/data.js from rules/*.md
```

## 📦 Build & Package

The catalog is generated from the Markdown files in `rules/`. After editing any rule, regenerate the data:

```bash
node scripts/build.js
```

## 🗄️ Backend (votes & favorites)

Votes and favorites are persisted server-side by `site/api.php` in a **SQLite** database (`site/data/rules.sqlite`, created automatically on first call).

- **What to upload**: the entire contents of the `site/` folder (and nothing else) — `index.html`, `style.css`, `app.js`, `data.js`, `api.php`, `data/.htaccess`. The `scripts/` folder is only for generating `data.js` locally and must **not** be uploaded.
- Requires **PHP hosting** (PHP 8+, `pdo_sqlite` extension). The rest of the site (`index.html`, `app.js`, `style.css`, `data.js`) stays 100% static.
- The `site/data/` folder must be **writable by PHP** (chmod 755/775) so the database can be created there.
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

## 🛠️ Tools

- **Régicide HP Tracker** (`tools/regicide-hp.html`) — hit point tracker for the Régicide card game. Open the file in a mobile browser. Card grid with fullscreen zoom mode, attack badges per rank (Jack ⚔10, Queen ⚔15, King ⚔20), automatic HP persistence.
- **Card images** (`tools/cards/`) — card scans used by the tracker.

## 📋 Changelog

See [CHANGELOG](CHANGELOG.md) for full history.

## 🔗 Links

- Game rules: [`rules/`](rules/) folder
- CLM rules (Régicide, Yaniv): [`rules_clm/`](rules_clm/) folder
- Régicide tracker: [`tools/regicide-hp.html`](tools/regicide-hp.html) (live: https://mondary.design/pk/tools/regicide-hp.html)
- Build scripts: [`scripts/`](scripts/) folder
