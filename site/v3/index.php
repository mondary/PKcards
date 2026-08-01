<?php
/* ==================================================================
   PKcards v3 — Monolithe autoportant
   ------------------------------------------------------------------
   2 fichiers : index.php (tout le code) + vault.sqlite (toutes les données)
   Zéro config : `new PDO('sqlite:'.__DIR__.'/vault.sqlite')`
   Zéro dépendance : PHP + SQLite, ni CDN, ni build, ni .htaccess requis.
   ------------------------------------------------------------------
   index.php contient : la classe Vault, le routeur, les templates,
   le CSS, le JS, l'API, les contrôleurs.
   vault.sqlite contient : jeux, markdown, catégories, votes, favoris,
   et tout blob arbitraire via le store KV (Vault::read/write/json/image).
   ================================================================== */

declare(strict_types=1);
error_reporting(E_ERROR | E_PARSE);

const VERSION = '2026.08.24';

/* ============================================================
   VAULT — mini-lib d'accès. Le coeur de l'archi.
   La plupart du code ne touche pas SQLite directement : il parle au Vault.
   ============================================================ */
class Vault {

  static ?PDO $pdo = null;
  const PATH = __DIR__ . '/vault.sqlite';

  /** PDO singleton. Crée le schéma + seed à la première ouverture. */
  static function db(): PDO {
    if (self::$pdo !== null) return self::$pdo;
    $pdo = new PDO('sqlite:' . self::PATH, null, null, [
      PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->sqliteCreateFunction('sort_key', static function ($value) {
      $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower((string)$value)) ?: (string)$value;
      return trim(preg_replace('/[^a-z0-9]+/', ' ', $ascii));
    }, 1);
    $pdo->exec('PRAGMA journal_mode=WAL');
    $pdo->exec('PRAGMA foreign_keys=ON');
    $pdo->exec('CREATE TABLE IF NOT EXISTS games(
      slug TEXT PRIMARY KEY, title TEXT, players TEXT, cards TEXT,
      difficulty TEXT, type TEXT, goal TEXT, category TEXT, color TEXT,
      aliases TEXT, excerpt TEXT, playerMin INTEGER, playerMax INTEGER, sort INTEGER,
      is_clm INTEGER NOT NULL DEFAULT 0, is_mistigri INTEGER NOT NULL DEFAULT 0, image TEXT)');
    $columns = array_column($pdo->query('PRAGMA table_info(games)')->fetchAll(), 'name');
    if (!in_array('is_clm', $columns, true)) $pdo->exec('ALTER TABLE games ADD COLUMN is_clm INTEGER NOT NULL DEFAULT 0');
    if (!in_array('is_mistigri', $columns, true)) $pdo->exec('ALTER TABLE games ADD COLUMN is_mistigri INTEGER NOT NULL DEFAULT 0');
    if (!in_array('image', $columns, true)) $pdo->exec('ALTER TABLE games ADD COLUMN image TEXT');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_games_cat ON games(category)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS game_names(slug TEXT NOT NULL,name TEXT NOT NULL,PRIMARY KEY(slug,name))');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_gamenames_name ON game_names(name)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS game_families(slug TEXT NOT NULL,family TEXT NOT NULL,PRIMARY KEY(slug,family))');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_gamefamilies_family ON game_families(family)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS game_sources(slug TEXT NOT NULL,url TEXT NOT NULL,PRIMARY KEY(slug,url))');
    $pdo->exec('CREATE TABLE IF NOT EXISTS kv(
      path TEXT PRIMARY KEY, mime TEXT, body BLOB, updated_at INTEGER)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS votes(game_id TEXT PRIMARY KEY, count INTEGER NOT NULL DEFAULT 0)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS vote_log(id INTEGER PRIMARY KEY AUTOINCREMENT, game_id TEXT, ip TEXT, created_at INTEGER)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_vl ON vote_log(ip, created_at)');
    $pdo->exec('CREATE TABLE IF NOT EXISTS favorites(email TEXT, game_id TEXT, created_at INTEGER, PRIMARY KEY(email, game_id))');
    self::$pdo = $pdo;
     self::seed();
     self::importClm();
     self::importMistigri();
     self::syncCatalog();
     return $pdo;
  }

  /** Seed one-shot si base vide : lit ../v1/data.js (présent en dev only).
   *  Le markdown de chaque jeu va dans le KV (/games/<slug>.md), l'index
   *  structuré va dans la table games. Les catégories → /config/categories.json. */
  static function seed(): void {
    $db = self::$pdo;
    if ((int)$db->query('SELECT COUNT(*) FROM games')->fetchColumn() > 0) return;
    $src = __DIR__ . '/../v1/data.js';
    if (!is_file($src)) return;
    $f = file_get_contents($src);
    $s = strpos($f, '['); $e = strpos($f, '];', $s);
    $games = json_decode(substr($f, $s, $e - $s + 1), true) ?: [];
    $cs = strpos($f, 'CATEGORY_INFO'); $cs = strpos($f, '{', $cs); $ce = strpos($f, '};', $cs);
    $cats = json_decode(substr($f, $cs, $ce - $cs + 1), true) ?: [];

    $ins = $db->prepare('INSERT INTO games(slug,title,players,cards,difficulty,type,goal,category,color,excerpt,playerMin,playerMax,sort,is_clm) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $kv  = $db->prepare('INSERT INTO kv(path,mime,body,updated_at) VALUES(?,?,?,?)');
    $gn  = $db->prepare('INSERT OR IGNORE INTO game_names(slug,name) VALUES(?,?)');
    $i = 0;
    foreach ($games as $g) {
      $slug = is_string($g['id'] ?? null) && $g['id'] !== '' ? $g['id'] : self::slug($g['title'] ?? 'game-'.$i);
      $ins->execute([
        $slug, $g['title'] ?? '', $g['players'] ?? '', $g['cards'] ?? '',
        $g['difficulty'] ?? '', $g['type'] ?? '', $g['goal'] ?? '',
        $g['category'] ?? '', $g['color'] ?? '#e8c46a',
        mb_strimwidth((string)($g['excerpt'] ?? ''), 0, 160, '…', 'UTF-8'),
        (int)($g['playerMin'] ?? 0), (int)($g['playerMax'] ?? 0), $i, 0,
      ]);
      foreach (array_filter(array_merge([$g['title'] ?? ''], preg_split('/[,\/]/', $g['aliases'] ?? ''))) as $n) {
        $n = trim(preg_replace('/^\p{So}+\s*/u', '', $n));
        if ($n !== '') $gn->execute([$slug, $n]);
      }
      $kv->execute(['/games/' . $slug . '.md', 'text/markdown', (string)($g['markdown'] ?? ''), time()]);
      $i++;
    }
    $kv->execute(['/config/categories.json', 'application/json', json_encode($cats), time()]);
  }

  /** Importe les règles éditoriales CLM une seule fois et les marque comme favoris natifs. */
  static function importClm(): void {
    $dir = __DIR__ . '/../../assets/rules/rules_clm';
    if (!is_dir($dir)) return;
    $db = self::$pdo;
    $insert = $db->prepare('INSERT OR IGNORE INTO games
      (slug,title,players,cards,difficulty,type,goal,category,color,excerpt,playerMin,playerMax,sort,is_clm)
      VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $update = $db->prepare('UPDATE games SET title=?,players=?,cards=?,difficulty=?,type=?,category=?,color=?,excerpt=?,playerMin=?,playerMax=?,is_clm=1 WHERE slug=?');
    $gn = $db->prepare('INSERT OR IGNORE INTO game_names(slug,name) VALUES(?,?)');
    $retired = self::retiredSlugs();
    $sort = (int)$db->query('SELECT COALESCE(MAX(sort), -1) + 1 FROM games')->fetchColumn();
    foreach (glob($dir . '/*.md') ?: [] as $file) {
      $slug = pathinfo($file, PATHINFO_FILENAME);
      if (isset($retired[$slug])) continue;
      $md = file_get_contents($file) ?: '';
      if (!preg_match('/^#\s+(.+)$/m', $md, $titleMatch)) continue;
      $title = trim(preg_replace('/^\p{So}+\s*/u', '', $titleMatch[1]));
      $title = trim(preg_replace('/\s*\([^)]*\)/', '', $title));
      preg_match('/\*\*([^*]*(?:joueur|joueurs)[^*]*)\*\*/iu', $md, $playersMatch);
      preg_match('/\*\*([^*]*cartes[^*]*)\*\*/iu', $md, $cardsMatch);
      $players = trim($playersMatch[1] ?? '');
      $cards = trim($cardsMatch[1] ?? '');
      preg_match('/^(?:.*?)(\d+)\s*(?:à|-|–)\s*(\d+)\s*joueurs?/iu', $players, $range);
      $min = (int)($range[1] ?? 0); $max = (int)($range[2] ?? 0);
      if (!$min && preg_match('/^(\d+)\s*joueurs?/iu', $players, $single)) $min = $max = (int)$single[1];
      $plain = trim(preg_replace('/\s+/', ' ', strip_tags(preg_replace('/[#*_>`|-]+/', ' ', $md))));
      $values = [$title, $players, $cards, '', 'Règle maison', 'clm', '#ca9ee6', mb_strimwidth($plain, 0, 160, '…', 'UTF-8'), $min, $max, $slug];
      $update->execute($values);
      if (!$update->rowCount()) $insert->execute([$slug, $title, $players, $cards, '', 'Règle maison', 'clm', '#ca9ee6', $values[7], $min, $max, $sort++, 1]);
      $gn->execute([$slug, $title]);
      self::write('/games/' . $slug . '.md', $md, 'text/markdown');
    }
  }

  /** Importe les règles Mistigri (jeuxdecartes1.e-monsite.com) avec leur image. */
  static function importMistigri(): void {
    $dir = __DIR__ . '/../../assets/rules/rules_mistigri';
    if (!is_dir($dir)) return;
    $db = self::$pdo;
    $insert = $db->prepare('INSERT OR IGNORE INTO games
      (slug,title,players,cards,difficulty,type,goal,category,color,excerpt,playerMin,playerMax,sort,is_clm,is_mistigri,image)
      VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $update = $db->prepare('UPDATE games SET title=?,players=?,cards=?,difficulty=?,type=?,goal=?,category=?,color=?,excerpt=?,playerMin=?,playerMax=?,is_mistigri=1,image=? WHERE slug=?');
    $gn = $db->prepare('INSERT OR IGNORE INTO game_names(slug,name) VALUES(?,?)');
    $retired = self::retiredSlugs();
    $sort = (int)$db->query('SELECT COALESCE(MAX(sort), -1) + 1 FROM games')->fetchColumn();
    $mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
    foreach (glob($dir . '/*.md') ?: [] as $file) {
      $slug = pathinfo($file, PATHINFO_FILENAME);
      if (isset($retired[$slug])) continue;
      $md = file_get_contents($file) ?: '';
      if (!preg_match('/^#\s+(.+)$/m', $md, $titleMatch)) continue;
      $title = trim(preg_replace('/^\p{So}+\s*/u', '', $titleMatch[1]));
      $title = trim(preg_replace('/\s*\([^)]*\)/', '', $title));
      $info = [];
      if (preg_match_all('/♦\s*\*\*([^*]+?)\*\*\s*:\s*(.*)$/mu', $md, $m, PREG_SET_ORDER))
        foreach ($m as $mm) $info[trim($mm[1])] = trim($mm[2]);
      $players = $info['Nombre de joueurs'] ?? '';
      $cards = $info['Matériel'] ?? '';
      $goal = $info['Objectif'] ?? '';
      preg_match('/(\d+)\s*(?:à|–|et)\s*(\d+)/iu', $players, $rng);
      $min = (int)($rng[1] ?? 0); $max = (int)($rng[2] ?? 0);
      if (!$min && preg_match('/(\d+)\s*joueurs?/iu', $players, $one)) $min = $max = (int)$one[1];
      $image = '';
      if (preg_match('/^!\[[^\]]*\]\(images\/([^)]+)\)/mu', $md, $img)) {
        $imgPath = $dir . '/images/' . $img[1];
        if (is_file($imgPath)) {
          $image = '/images/' . $img[1];
          $ext = strtolower(pathinfo($imgPath, PATHINFO_EXTENSION));
          self::write($image, file_get_contents($imgPath) ?: '', $mimes[$ext] ?? 'image/jpeg');
        }
        $md = preg_replace('/\]\(images\/([^)]+)\)/', '](?img=/images/$1)', $md);
      }
      $plain = trim(preg_replace('/\s+/', ' ', strip_tags(preg_replace('/[#*_>`|-]+/', ' ', $md))));
      $excerpt = mb_strimwidth($plain, 0, 160, '…', 'UTF-8');
      $values = [$title, $players, $cards, '', '', $goal, 'mistigri', '#a6e3a1', $excerpt, $min, $max, $image, $slug];
      $update->execute($values);
      if (!$update->rowCount()) $insert->execute([$slug, $title, $players, $cards, '', '', $goal, 'mistigri', '#a6e3a1', $excerpt, $min, $max, $sort++, 0, 1, $image]);
      $gn->execute([$slug, $title]);
      self::write('/games/' . $slug . '.md', $md, 'text/markdown');
    }
  }

  static function catalog(): array {
    static $catalog;
    if ($catalog === null) {
      $file = __DIR__ . '/catalog.json';
      $catalog = is_file($file) ? (json_decode(file_get_contents($file) ?: '', true) ?: []) : [];
    }
    return $catalog;
  }

  static function retiredSlugs(): array {
    $applied = self::$pdo->query("SELECT 1 FROM kv WHERE path='/meta/catalog-version'")->fetchColumn();
    return $applied ? array_fill_keys(array_keys(self::catalog()['duplicates'] ?? []), true) : [];
  }

  /** Réconcilie une fois les noms et doublons sans écraser votes ni favoris. */
  static function syncCatalog(): void {
    $file = __DIR__ . '/catalog.json';
    if (!is_file($file)) return;
    $hash = hash_file('sha256', $file) ?: '';
    $db = self::$pdo;
    $marker = $db->query("SELECT body FROM kv WHERE path='/meta/catalog-version'")->fetchColumn();
    if ($marker === $hash) return;
    $catalog = self::catalog();

    $db->beginTransaction();
    try {
      $copyContent = $db->prepare("INSERT INTO kv(path,mime,body,updated_at)
        SELECT ?,mime,body,? FROM kv WHERE path=?
        ON CONFLICT(path) DO UPDATE SET mime=excluded.mime,body=excluded.body,updated_at=excluded.updated_at");
      foreach ($catalog['content_sources'] ?? [] as $canonical => $source)
        $copyContent->execute(['/games/' . $canonical . '.md', time(), '/games/' . $source . '.md']);

      $restore = $db->prepare('INSERT INTO games
        (slug,title,players,cards,difficulty,type,goal,category,color,aliases,excerpt,playerMin,playerMax,sort,is_clm,is_mistigri,image)
        VALUES(:slug,:title,:players,:cards,:difficulty,:type,:goal,:category,:color,NULL,:excerpt,:playerMin,:playerMax,
          (SELECT COALESCE(MAX(sort),-1)+1 FROM games),:is_clm,:is_mistigri,:image)
        ON CONFLICT(slug) DO UPDATE SET title=excluded.title,players=excluded.players,cards=excluded.cards,
          difficulty=excluded.difficulty,type=excluded.type,goal=excluded.goal,category=excluded.category,color=excluded.color,
          excerpt=excluded.excerpt,playerMin=excluded.playerMin,playerMax=excluded.playerMax,
          is_clm=excluded.is_clm,is_mistigri=excluded.is_mistigri,image=excluded.image');
      foreach ($catalog['restored_variants'] ?? [] as $slug => $variant) {
        $restore->execute(['slug' => $slug, ...array_diff_key($variant, ['source' => true])]);
      }

      $exists = $db->prepare('SELECT 1 FROM games WHERE slug=?');
      $vote = $db->prepare('INSERT INTO votes(game_id,count) SELECT ?,count FROM votes WHERE game_id=? ON CONFLICT(game_id) DO UPDATE SET count=count+excluded.count');
      $favorite = $db->prepare('INSERT OR IGNORE INTO favorites(email,game_id,created_at) SELECT email,?,created_at FROM favorites WHERE game_id=?');
      $links = $db->prepare('SELECT slug,related,rel,note FROM game_links WHERE slug=? OR related=?');
      $insertLink = $db->prepare('INSERT OR IGNORE INTO game_links(slug,related,rel,note) VALUES(?,?,?,?)');
      foreach ($catalog['duplicates'] ?? [] as $duplicate => $canonical) {
        $exists->execute([$duplicate]);
        if (!$exists->fetchColumn()) continue;
        $vote->execute([$canonical, $duplicate]);
        $favorite->execute([$canonical, $duplicate]);
        $db->prepare('UPDATE vote_log SET game_id=? WHERE game_id=?')->execute([$canonical, $duplicate]);
        $links->execute([$duplicate, $duplicate]);
        $rows = $links->fetchAll();
        $db->prepare('DELETE FROM game_links WHERE slug=? OR related=?')->execute([$duplicate, $duplicate]);
        foreach ($rows as $row) {
          $from = $row['slug'] === $duplicate ? $canonical : $row['slug'];
          $to = $row['related'] === $duplicate ? $canonical : $row['related'];
          if ($from !== $to) $insertLink->execute([$from, $to, $row['rel'], $row['note']]);
        }
        $db->prepare('DELETE FROM favorites WHERE game_id=?')->execute([$duplicate]);
        $db->prepare('DELETE FROM votes WHERE game_id=?')->execute([$duplicate]);
        $db->prepare('DELETE FROM game_names WHERE slug=?')->execute([$duplicate]);
        $db->prepare('DELETE FROM games WHERE slug=?')->execute([$duplicate]);
      }

      $gameExists = $db->prepare('SELECT 1 FROM games WHERE slug=?');
      $deleteNames = $db->prepare('DELETE FROM game_names WHERE slug=?');
      $insertName = $db->prepare('INSERT INTO game_names(slug,name) VALUES(?,?)');
      foreach ($catalog['games'] ?? [] as $slug => $names) {
        $gameExists->execute([$slug]);
        if (!$gameExists->fetchColumn()) continue;
        $deleteNames->execute([$slug]);
        foreach ($names as $name) $insertName->execute([$slug, $name]);
      }
      $db->exec('DELETE FROM game_families');
      $insertFamily = $db->prepare('INSERT INTO game_families(slug,family) VALUES(?,?)');
      foreach ($catalog['families'] ?? [] as $family => $slugs)
        foreach ($slugs as $slug) $insertFamily->execute([$slug, $family]);
      $db->exec('DELETE FROM game_sources');
      $insertSource = $db->prepare('INSERT INTO game_sources(slug,url) VALUES(?,?)');
      foreach ($catalog['sources'] ?? [] as $slug => $urls)
        foreach ($urls as $url) $insertSource->execute([$slug, $url]);
      $db->prepare("INSERT INTO kv(path,mime,body,updated_at) VALUES('/meta/catalog-version','text/plain',?,?)
        ON CONFLICT(path) DO UPDATE SET body=excluded.body,updated_at=excluded.updated_at")->execute([$hash, time()]);
      $db->commit();
    } catch (Throwable $e) {
      $db->rollBack();
      throw $e;
    }
  }

  /* ---- Store KV générique (la vision : markdown, json, images, blobs) ---- */
  static function read(string $path): ?string {
    $st = self::db()->prepare('SELECT body FROM kv WHERE path=?');
    $st->execute([$path]);
    $r = $st->fetchColumn();
    return $r === false ? null : (string)$r;
  }
  static function write(string $path, string $body, string $mime = 'text/plain'): void {
    $st = self::db()->prepare('INSERT INTO kv(path,mime,body,updated_at) VALUES(?,?,?,?)
      ON CONFLICT(path) DO UPDATE SET body=excluded.body, mime=excluded.mime, updated_at=excluded.updated_at');
    $st->execute([$path, $mime, $body, time()]);
  }
  static function json(string $path) {
    $b = self::read($path);
    return $b === null ? null : json_decode($b, true);
  }
  static function image(string $path): ?array {
    $st = self::db()->prepare('SELECT mime, body FROM kv WHERE path=?');
    $st->execute([$path]);
    $r = $st->fetch();
    return $r ?: null;
  }

  /* ---- Données applicatives ---- */
  /** Liste de jeux filtrable/triable. opts: q, cat, top, limit. */
  static function games(array $o = []): array {
    $top = !empty($o['top']);
    if ($top) {
      $sql = 'SELECT g.*, COALESCE(v.count, 0) AS votes
              FROM games g LEFT JOIN votes v ON v.game_id = g.slug
              ORDER BY votes DESC, sort_key(g.title) ASC';
      $st = self::db()->prepare($sql . (isset($o['limit']) ? ' LIMIT ' . (int)$o['limit'] : ''));
      $st->execute([]);
      return $st->fetchAll();
    }
    $w = []; $a = [];
    if (!empty($o['cat'])) { $w[] = 'category=?'; $a[] = $o['cat']; }
    if (!empty($o['family'])) { $w[] = 'EXISTS(SELECT 1 FROM game_families gf WHERE gf.slug=games.slug AND gf.family=?)'; $a[] = $o['family']; }
    if (!empty($o['q'])) {
      $w[] = '(title LIKE ? OR EXISTS(SELECT 1 FROM game_names gn WHERE gn.slug=games.slug AND gn.name LIKE ?) OR type LIKE ? OR excerpt LIKE ?)';
      $q = '%' . $o['q'] . '%'; array_push($a, $q, $q, $q, $q);
    }
    $sql = 'SELECT *, (SELECT count FROM votes WHERE game_id=slug) AS votes FROM games';
    if ($w) $sql .= ' WHERE ' . implode(' AND ', $w);
    $sql .= ' ORDER BY sort_key(title) ASC';
    if (isset($o['limit'])) $sql .= ' LIMIT ' . (int)$o['limit'];
    $st = self::db()->prepare($sql); $st->execute($a);
    return $st->fetchAll();
  }

  static function game(string $slug): ?array {
    $st = self::db()->prepare('SELECT *, (SELECT count FROM votes WHERE game_id=slug) AS votes FROM games WHERE slug=?');
    $st->execute([$slug]);
    $g = $st->fetch();
    if (!$g && isset(self::catalog()['duplicates'][$slug])) {
      $st->execute([self::catalog()['duplicates'][$slug]]);
      $g = $st->fetch();
    }
    return $g ?: null;
  }

  /** Vote +1 (throttle par IP). Retourne le nouveau total. */
  static function vote(string $slug, string $ip, int $now): array {
    $db = self::db();
    $st = $db->prepare('SELECT MAX(created_at) FROM vote_log WHERE ip=? AND game_id=?');
    $st->execute([$ip, $slug]);
    if (($last = (int)$st->fetchColumn()) && ($now - $last) < 5)
      return ['error' => 429, 'msg' => 'too_soon'];
    $st = $db->prepare('SELECT COUNT(*) FROM vote_log WHERE ip=? AND created_at>?');
    $st->execute([$ip, $now - 60]);
    if ((int)$st->fetchColumn() >= 40)
      return ['error' => 429, 'msg' => 'rate_limited'];

    $db->beginTransaction();
    $db->prepare('INSERT INTO votes(game_id,count) VALUES(?,1)
                  ON CONFLICT(game_id) DO UPDATE SET count=count+1')->execute([$slug]);
    $db->prepare('INSERT INTO vote_log(game_id,ip,created_at) VALUES(?,?,?)')->execute([$slug, $ip, $now]);
    $db->commit();
    $st = $db->prepare('SELECT count FROM votes WHERE game_id=?'); $st->execute([$slug]);
    return ['count' => (int)$st->fetchColumn()];
  }

  static function favToggle(string $email, string $slug, bool $add): array {
    $db = self::db();
    if ($add) $db->prepare('INSERT OR IGNORE INTO favorites(email,game_id,created_at) VALUES(?,?,?)')->execute([$email, $slug, time()]);
    else      $db->prepare('DELETE FROM favorites WHERE email=? AND game_id=?')->execute([$email, $slug]);
    $st = $db->prepare('SELECT game_id FROM favorites WHERE email=? ORDER BY created_at DESC');
    $st->execute([$email]);
    return array_column($st->fetchAll(), 'game_id');
  }
  static function favs(string $email): array {
    $st = self::db()->prepare('SELECT game_id FROM favorites WHERE email=? ORDER BY created_at DESC');
    $st->execute([$email]);
    return array_column($st->fetchAll(), 'game_id');
  }

  static function slug(string $s): string {
    $s = strtolower(preg_replace('/[^a-z0-9]+/i', '-', trim(remove_accents($s))), '');
    return trim($s, '-') ?: 'game';
  }
}

function remove_accents(string $s): string {
  if (function_exists('translit')) return $s;
  return strtr(utf8_decode($s), utf8_decode('àáâãäçèéêëìíîïñòóôõöùúûüýÿÀÁÂÃÄÇÈÉÊËÌÍÎÏÑÒÓÔÕÖÙÚÛÜÝ'),
                                  'aaaaaceeeeiiiinooooouuuuyyAAAAACEEEEIIIINOOOOOUUUUY');
}

/* ============================================================
   HELPERS
   ============================================================ */
function e($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/** Compacte le nb de joueurs en badge : « 2–6 j », « 4 j », « 3+ j ». */
function player_short(array $g): string {
  $min = (int)($g['playerMin'] ?? 0); $max = (int)($g['playerMax'] ?? 0);
  if ($max && $max < $min) $max = 0;            // donnée source incohérente
  if (!$min && !$max) return '';
  if ($min && $max) return $min === $max ? $min . ' j' : $min . '–' . ($max >= 20 ? '20+' : $max) . ' j';
  return $min ? $min . '+ j' : '≤' . $max . ' j';
}

/** Photos Wikimedia Commons, choisies par famille et stables pour chaque jeu. */
function hero_photo(array $g, int $width = 1280): string {
  $photos = [
    'general' => [
      'https://upload.wikimedia.org/wikipedia/commons/e/e9/Ace_playing_cards.jpg',
      'https://upload.wikimedia.org/wikipedia/commons/2/26/Playing_card_deck%2C_side_view-92656.jpg',
      'https://upload.wikimedia.org/wikipedia/commons/8/87/Valentin_de_Boulogne_-_Soldiers_Playing_Cards_and_Dice_%28The_Cheats%29.jpg',
    ],
    'poker' => [
      'https://upload.wikimedia.org/wikipedia/commons/2/26/Poker_closeup.jpg',
      'https://upload.wikimedia.org/wikipedia/commons/4/40/Two_poker_cards_and_poker_chips_20170611.jpg',
      'https://upload.wikimedia.org/wikipedia/commons/7/7c/Poker_cards_52.jpg',
    ],
    'solo' => [
      'https://upload.wikimedia.org/wikipedia/commons/e/e5/Carpet_patience_2.jpg',
      'https://upload.wikimedia.org/wikipedia/commons/4/47/Spider_Solitaire_Card_Game.jpg',
      'https://upload.wikimedia.org/wikipedia/commons/f/fd/Solitaire_%28356533432%29.jpg',
    ],
    'tarot' => [
      'https://upload.wikimedia.org/wikipedia/commons/3/37/Tarot_cards_-_3_card_spread.jpg',
      'https://upload.wikimedia.org/wikipedia/commons/8/83/Tarot_cards_-_3_card_spread_with_candles.jpg',
      'https://upload.wikimedia.org/wikipedia/commons/3/34/Tarot_cards_-_Celtic_cross_spread.jpg',
    ],
  ];
  $type = mb_strtolower((string)($g['type'] ?? ''));
  $set = str_contains($type, 'patience') ? 'solo' : (str_contains($type, 'tarot') ? 'tarot' : (str_contains($type, 'hasard') || str_contains($type, 'enchères') ? 'poker' : 'general'));
  $images = $photos[$set];
  $url = $images[abs(crc32((string)$g['slug'])) % count($images)];
  $path = (string)parse_url($url, PHP_URL_PATH);
  return 'https://commons.wikimedia.org/wiki/Special:Redirect/file/' . basename($path) . '?width=' . $width;
}

function game_photo(array $g, bool $thumbnail = false, bool $inline = false): string {
  $generated = Vault::catalog()['images'][$g['slug']] ?? '';
  if ($generated !== '') {
    if ($inline) return 'data:image/webp;base64,' . base64_encode(file_get_contents(__DIR__ . '/' . $generated) ?: '');
    return '?visual=' . urlencode((string)$g['slug']);
  }
  $image = (string)($g['image'] ?? '');
  if (str_starts_with($image, 'http')) return $image;
  return $image !== '' ? '?img=' . urlencode($image) : hero_photo($g, $thumbnail ? 640 : 1280);
}

/** Markdown → HTML (taille raisonnable : titres, gras, listes, tables, hr, emoji ok/no). */
function md2html(string $md): string {
  $md = preg_replace('/^---+\s*$/m', "\n<hr>\n", $md);
  $md = preg_replace('/^####\s+(.+)$/m', '<h4>$1</h4>', $md);
  $md = preg_replace('/^###\s+(.+)$/m', '<h3>$1</h3>', $md);
  $md = preg_replace('/^##\s+(.+)$/m', '<h2>$1</h2>', $md);
  $md = preg_replace('/^#\s+(.+)$/m', '<h1>$1</h1>', $md);
  $md = preg_replace('/\*\*\*(.+?)\*\*\*/', '<strong><em>$1</em></strong>', $md);
  $md = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $md);
  $md = preg_replace('/!\[([^\]]*)\]\(([^)\s]+)\)/', '<img src="$2" alt="$1" loading="lazy">', $md);
  $md = preg_replace('/\[([^\]]+)\]\(([^)\s]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $md);
  $md = preg_replace('/^>\s*(.+)$/m', '<blockquote>$1</blockquote>', $md);
  $md = preg_replace('/✅/', '<span class="ok">✅</span>', $md);
  $md = preg_replace('/❌/', '<span class="no">❌</span>', $md);
  $lines = explode("\n", $md);
  $html = []; $inT = $inU = $inO = false;
  foreach ($lines as $line) {
    $t = trim($line);
    if (preg_match('/^\|(.+)\|$/', $t, $m)) {
      if (!$inT) { $html[] = '<table>'; $inT = true; }
      $cells = array_map('trim', explode('|', trim($m[1], '|')));
      if (preg_match('/^[-:]+$/', implode('', $cells))) continue;
      $html[] = '<tr>' . implode('', array_map(fn($c) => "<td>$c</td>", $cells)) . '</tr>';
      continue;
    }
    if ($inT) { $html[] = '</table>'; $inT = false; }
    if (preg_match('/^-\s+(.+)/', $t, $m)) { if (!$inU) { $html[] = '<ul>'; $inU = true; } $html[] = "<li>$m[1]</li>"; continue; }
    if ($inU) { $html[] = '</ul>'; $inU = false; }
    if (preg_match('/^\d+\.\s+(.+)/', $t, $m)) { if (!$inO) { $html[] = '<ol>'; $inO = true; } $html[] = "<li>$m[1]</li>"; continue; }
    if ($inO) { $html[] = '</ol>'; $inO = false; }
    if ($t === '') continue;
    if ($t === '<hr>') { $html[] = '<hr>'; continue; }
    if (preg_match('/^<h[1-4]/', $t) || preg_match('/^<blockquote/', $t)) { $html[] = $t; continue; }
    $html[] = "<p>$t</p>";
  }
  if ($inT) $html[] = '</table>';
  if ($inU) $html[] = '</ul>';
  if ($inO) $html[] = '</ol>';
  return implode("\n", $html);
}

function json_out($d, int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store');
  echo json_encode($d, JSON_UNESCAPED_UNICODE);
  exit;
}

/* ============================================================
   ROUTEUR
   ============================================================ */
Vault::db();
$ip   = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$now  = time();

// --- API JSON (?api=...) ---
if (!empty($_GET['api'])) {
  switch ($_GET['api']) {
    case 'vote':
      if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_out(['ok' => false, 'error' => 'method'], 405);
      $slug = $_POST['game'] ?? (json_decode(file_get_contents('php://input') ?: '', true)['game'] ?? '');
      if (!preg_match('/^[a-z0-9-]{1,80}$/', (string)$slug)) json_out(['ok' => false, 'error' => 'bad_game'], 400);
      $r = Vault::vote($slug, $ip, $now);
      if (isset($r['error'])) json_out(['ok' => false, 'error' => $r['msg']], $r['error']);
      json_out(['ok' => true, 'count' => $r['count']]);
    case 'top':
      json_out(array_map(fn($g) => ['game_id' => $g['slug'], 'count' => (int)$g['votes'], 'title' => $g['title']], Vault::games(['top' => true, 'limit' => 100])));
    case 'fav_toggle':
      if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') json_out(['ok' => false, 'error' => 'method'], 405);
      $body = json_decode(file_get_contents('php://input') ?: '', true) ?: [];
      $email = strtolower(trim((string)($body['email'] ?? '')));
      $slug  = (string)($body['game'] ?? '');
      $add   = (bool)($body['add'] ?? false);
      if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[a-z0-9-]{1,80}$/', $slug))
        json_out(['ok' => false, 'error' => 'bad_input'], 400);
      json_out(['ok' => true, 'favorites' => Vault::favToggle($email, $slug, $add)]);
    case 'favorites':
      $email = strtolower(trim((string)($_GET['email'] ?? '')));
      if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_out(['ok' => false, 'error' => 'bad_email'], 400);
      json_out(Vault::favs($email));
    default:
      json_out(['ok' => false, 'error' => 'unknown'], 404);
  }
}

// --- Miniatures générées par lot pour éviter les limites HTTP/2 de l'hébergeur ---
if (isset($_GET['visuals'])) {
  $catalogImages = Vault::catalog()['images'] ?? [];
  $images = [];
  foreach (array_slice(array_unique(explode(',', (string)$_GET['visuals'])), 0, 24) as $slug) {
    $path = $catalogImages[$slug] ?? '';
    $file = $path !== '' ? __DIR__ . '/' . $path : '';
    if ($file !== '' && is_file($file)) $images[$slug] = 'data:image/webp;base64,' . base64_encode(file_get_contents($file) ?: '');
  }
  json_out($images);
}

// --- Visuel généré local (?visual=slug) ---
if (isset($_GET['visual'])) {
  $slug = (string)$_GET['visual'];
  $path = Vault::catalog()['images'][$slug] ?? '';
  $file = $path !== '' ? __DIR__ . '/' . $path : '';
  if ($file === '' || !is_file($file)) { http_response_code(404); exit; }
  header('Content-Type: image/webp');
  header('Cache-Control: public, max-age=31536000, immutable');
  readfile($file);
  exit;
}

// --- Image depuis le KV (?img=/path) ---
if (isset($_GET['img'])) {
  $p = '/' . ltrim((string)$_GET['img'], '/');
  $img = Vault::image($p);
  if (!$img) { http_response_code(404); exit; }
  header('Content-Type: ' . ($img['mime'] ?: 'application/octet-stream'));
  header('Cache-Control: public, max-age=86400');
  echo $img['body'];
  exit;
}

// --- Choix de la vue ---
$slug = isset($_GET['game']) ? (string)$_GET['game'] : '';
$q    = trim((string)($_GET['q'] ?? ''));
$cat  = (string)($_GET['cat'] ?? '');
$family = trim((string)($_GET['family'] ?? ''));
$view = ($slug !== '' && !isset($_GET['q'])) ? 'reader' : 'home';
if (isset($_GET['top'])) $view = 'top';

/* ============================================================
   VUES
   ============================================================ */
$CATEGORIES = Vault::json('/config/categories.json') ?: [];
$TOTAL = (int)Vault::db()->query('SELECT COUNT(*) FROM games')->fetchColumn();
function chip_active(string $a, string $b): string { return $a === $b ? 'chip--active' : ''; }
?>
<!DOCTYPE html>
<html lang="fr" data-theme="cat">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#151515">
<meta name="description" content="PKcards — <?= $TOTAL ?> jeux de cartes : règles, favoris, découverte.">
<title>PKcards — <?= $TOTAL ?> jeux de cartes</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='12' fill='%23151515'/%3E%3Cpath d='M8 43 55 20l2 11L10 53Z' fill='%23ff3d9a'/%3E%3Ctext x='32' y='39' text-anchor='middle' font-family='Arial' font-size='24' font-weight='900' fill='white'%3EPK%3C/text%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Anton&family=Cherry+Bomb+One&family=DM+Mono:wght@400;500&family=IBM+Plex+Mono:wght@400;500;600&family=Manrope:wght@300;400;500;600&family=Newsreader:opsz,wght@6..72,400;6..72,500&family=Outfit:wght@400;500;600;700&family=Paytone+One&family=Permanent+Marker&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
<script>try{document.documentElement.dataset.theme=localStorage.getItem('pk_theme')||'cat'}catch(e){}</script>
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--paper:#efede7;--surface:#fff;--soft:#deddd7;--ink:#151515;--pink:#ff3d9a;--blue:#3558ff;--yellow:#f5df22;--mint:#56e6a5;--line:#cfcdc6;--muted:#686762;--display:'Anton',Impact,sans-serif;--marker:'Permanent Marker',cursive;--mono:'Space Grotesk',sans-serif;--sans:'Space Grotesk',sans-serif}
html{scroll-behavior:smooth}
body{min-height:100dvh;overflow-x:hidden;background-color:var(--paper);background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='80' height='80'%3E%3Ccircle cx='7' cy='14' r='.8' fill='%23151515' opacity='.08'/%3E%3Ccircle cx='56' cy='48' r='.7' fill='%23151515' opacity='.06'/%3E%3Cpath d='M15 67h18' stroke='%23151515' stroke-width='.5' opacity='.05'/%3E%3C/svg%3E");color:var(--ink);font-family:var(--sans);-webkit-text-size-adjust:100%}
a{color:inherit;text-decoration:none}button,input{font:inherit}button{color:inherit;cursor:pointer}:focus-visible{outline:3px solid var(--blue);outline-offset:3px}
.eyebrow,.count-line{font:500 .68rem/1.2 var(--mono);letter-spacing:.11em;text-transform:uppercase}

.topfix{position:sticky;top:0;z-index:30;width:100%;max-width:100vw;padding:calc(13px + env(safe-area-inset-top)) clamp(18px,4vw,64px) 13px;background:var(--ink);color:#fff}
.topfix__inner{max-width:1440px;margin:auto}.brandrow{display:flex;align-items:center;gap:12px}.brand{font:600 .76rem var(--mono)}.brand b{color:var(--pink);font-size:1rem}.brand .ver{margin-left:8px;color:#aaa;font-size:.62rem}.spacer{flex:1}
.iconbtn{position:relative;padding:8px 10px;border:0;border-radius:4px;background:transparent;color:#fff;font:600 .72rem var(--mono);text-transform:uppercase;transition:background .15s,color .15s}.iconbtn:hover{background:var(--yellow);color:var(--ink)}.dot{margin-left:4px;color:var(--yellow)}
.theme-switch{display:flex;gap:3px}.theme-switch button{min-height:34px;padding:0 8px;border:0;border-radius:3px;background:rgba(255,255,255,.08);color:inherit;font:700 .58rem var(--mono)}.theme-switch button[aria-pressed="true"]{background:var(--pink);color:var(--ink)}.theme-select{display:none}

.launcher{position:relative;width:100%;max-width:1440px;margin:auto;padding:clamp(56px,8vw,118px) clamp(18px,4vw,64px) 56px;color:var(--ink);display:grid;grid-template-columns:minmax(250px,.72fr) minmax(320px,1.28fr);column-gap:clamp(48px,8vw,140px);align-items:end}.launcher>*{min-width:0}.launcher::after{content:'CHOISIS. JOUE.';position:absolute;left:33%;top:18%;padding:4px 10px;background:var(--yellow);font:clamp(1rem,2vw,1.7rem) var(--marker);transform:rotate(-6deg)}.launcher--family{min-height:68vh;overflow:hidden;isolation:isolate}.launcher--family>*:not(.launcher__family-image){position:relative;z-index:1}.launcher__family-image{position:absolute;z-index:0;inset:0 0 0 auto;width:68%;height:100%;object-fit:cover;opacity:.68;-webkit-mask-image:linear-gradient(90deg,transparent 0,#000 38%);mask-image:linear-gradient(90deg,transparent 0,#000 38%)}
.launcher__intro{grid-row:span 3}.launcher .eyebrow{color:var(--blue);font-weight:700}.launcher h1{margin-top:16px;max-width:650px;font:400 clamp(4.2rem,9vw,9rem)/.78 var(--display);text-transform:uppercase;text-shadow:5px 5px 0 var(--pink),10px 10px 0 var(--blue);text-wrap:balance}.launcher__intro>p:last-child,.top-intro{max-width:430px;margin-top:38px;color:var(--muted);font-size:.96rem;line-height:1.65;text-wrap:pretty}
.search{position:relative}.search label{display:block;margin-bottom:10px;color:var(--ink);font:700 .66rem var(--mono);text-transform:uppercase}.search input{width:100%;height:70px;padding:0 62px 0 20px;border:3px solid var(--ink);border-radius:3px;background:var(--surface);color:var(--ink);box-shadow:8px 8px 0 var(--blue);font-size:clamp(1rem,2vw,1.25rem);outline:none}.search input:focus{box-shadow:8px 8px 0 var(--pink)}.search input::placeholder{color:#83817c}.search__key{position:absolute;right:18px;bottom:24px;color:var(--muted);font:.7rem var(--mono)}
.launcher__actions{display:flex;gap:24px;align-items:center;margin-top:24px;font:700 .7rem var(--mono);text-transform:uppercase}.random{padding:13px 17px;border:3px solid var(--ink);border-radius:3px;background:var(--pink);color:var(--ink);box-shadow:4px 4px 0 var(--ink);font:inherit;text-transform:inherit}.random:active{transform:translate(2px,2px);box-shadow:2px 2px 0 var(--ink)}.launcher__actions a{color:var(--ink);text-decoration:underline;text-decoration-thickness:3px;text-decoration-color:var(--blue)}
.chips{grid-column:2;display:flex;gap:8px;margin-top:32px;overflow-x:auto;scrollbar-width:none}.chips::-webkit-scrollbar{display:none}.chip{flex:none;padding:9px 12px;border:0;border-radius:3px;background:var(--soft);color:var(--ink);font:700 .64rem var(--mono);text-transform:uppercase}.chip:nth-child(2){background:#ffb5d5}.chip:nth-child(3){background:#aebcff}.chip:nth-child(4){background:#9ff0c8}.chip:nth-child(5){background:#f5e984}.chip span{margin-left:5px;opacity:.65}.chip--active{background:var(--ink)!important;color:#fff}.chip--active span{color:#fff}

.section-head,.list,.families{max-width:2400px;margin:auto}.section-head{display:flex;justify-content:space-between;padding:54px clamp(18px,4vw,64px) 18px}.section-head .eyebrow{display:inline-block;padding:4px 8px;background:var(--yellow);color:var(--ink);font-weight:700;transform:rotate(-1deg)}.count-line{color:var(--muted);font-variant-numeric:tabular-nums}
.list{display:grid;grid-template-columns:repeat(auto-fit,minmax(360px,1fr));gap:16px;padding:0 clamp(18px,4vw,64px) 110px}.game{position:relative;display:grid;grid-template-columns:minmax(0,1fr) auto;align-content:start;min-width:0;min-height:310px;padding:0 0 16px;border:1px solid var(--line);border-radius:18px;overflow:hidden;background:var(--surface);content-visibility:auto;contain-intrinsic-size:auto 310px;transition:transform .15s,box-shadow .15s}.game__image{grid-column:1/-1;width:100%;height:184px;border-radius:17px 17px 0 0;object-fit:cover;background:var(--soft)}.game:nth-child(n):hover{color:var(--ink);transform:translateY(-2px);box-shadow:0 12px 28px rgba(0,0,0,.1)}.game__index{position:absolute;top:12px;left:12px;padding:6px 8px;border-radius:8px;background:var(--surface);color:var(--blue);font:700 .7rem var(--mono);font-variant-numeric:tabular-nums}.game__main{grid-column:1;min-width:0;padding:18px 10px 4px 18px}.game__title{display:block;font-size:clamp(1.35rem,2.2vw,1.75rem);font-weight:700}.game__sub,.game__meta{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.game__sub{margin-top:6px;color:var(--muted);font:.78rem var(--mono)}.game__meta{margin-top:8px;color:var(--muted);font-size:.78rem}.game:hover .game__sub,.game:hover .game__meta{color:var(--muted)}.game__fav{grid-column:2;align-self:end;min-width:46px;min-height:40px;margin:14px 12px 0 0;border:0;border-radius:9px;background:var(--soft);color:inherit;font:700 .68rem var(--mono);text-transform:uppercase}.game__fav:hover,.game__fav.on{background:var(--ink);color:var(--yellow)}.lazy-image{opacity:0;transition:opacity .15s}.lazy-image.is-loaded{opacity:1}
.game--favorite{border:2px solid var(--yellow)!important;box-shadow:0 0 0 4px color-mix(in srgb,var(--yellow) 42%,transparent),0 14px 30px rgba(0,0,0,.14)!important}.game--favorite::after{content:'★ FAVORI';position:absolute;z-index:2;top:12px;right:12px;padding:7px 10px;border-radius:999px;background:var(--yellow);color:#151515;font:700 .62rem var(--mono)}.game--favorite .game__fav{background:var(--yellow)!important;color:#151515!important}
.families{padding:28px clamp(18px,4vw,64px) 120px}.families__title{margin-bottom:38px;font:2.4rem var(--display);text-transform:uppercase}.families__grid{display:grid;grid-template-columns:repeat(12,1fr);grid-auto-flow:dense;gap:14px}.family{position:relative;grid-column:span 4;min-width:0;min-height:260px;overflow:hidden;border-radius:4px;background:#111;color:#fff}.family:nth-child(8n+1),.family:nth-child(8n+2){grid-column:span 6}.family__image{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}.family__content{position:absolute;inset:auto 0 0;display:flex;align-items:end;justify-content:space-between;gap:20px;padding:70px 20px 20px;background:linear-gradient(transparent,rgba(0,0,0,.88))}.family__visual-title{font:400 clamp(1.8rem,3vw,3rem)/.9 var(--display);text-transform:uppercase}.family__visual-title::after{content:' →'}.family__count{font:600 .62rem var(--mono);text-align:right;text-transform:uppercase}
.empty{padding:72px 18px;text-align:center;color:var(--muted)}.empty h2{margin-bottom:8px;color:var(--ink)}

.reader{display:grid;grid-template-columns:minmax(380px,42vw) minmax(0,1fr);min-height:100dvh;background:var(--paper)}.bar{position:fixed;z-index:20;top:0;left:0;width:42vw;display:flex;justify-content:space-between;align-items:center;padding:calc(18px + env(safe-area-inset-top)) 24px 16px;color:#fff;font:.64rem var(--mono);text-transform:uppercase}.bar__back{padding:9px 11px;border:0;border-radius:6px;background:rgba(16,38,31,.72)}
.reader-hero{position:sticky;top:0;height:100dvh;overflow:hidden;display:flex;flex-direction:column;justify-content:flex-end;padding:100px clamp(24px,4vw,64px) 44px;background:var(--ink);color:#fff;isolation:isolate}.reader-hero::after{content:'';position:absolute;inset:0;z-index:-1;background:rgba(10,10,10,.46)}.reader-hero__image{position:absolute;inset:0;z-index:-2;width:100%;height:100%;object-fit:cover;filter:saturate(.9) contrast(1.12)}.reader-hero__eyebrow{display:inline-block;width:max-content;padding:3px 7px;background:var(--yellow);color:var(--ink);font:700 .65rem var(--mono);text-transform:uppercase;transform:rotate(-2deg)}.reader-hero h1{margin:16px 0 20px;font:400 clamp(3.4rem,7vw,7.4rem)/.82 var(--display);text-transform:uppercase;text-shadow:4px 4px 0 var(--pink),8px 8px 0 var(--blue);text-wrap:balance}.reader-hero__alts{display:flex;flex-wrap:wrap;gap:16px;margin-bottom:30px}.reader-hero__alt{color:#fff;font:600 .62rem var(--mono);text-decoration:underline;text-decoration-color:var(--pink);text-decoration-thickness:3px}.reader-hero__meta{display:flex;gap:36px}.reader-hero__meta span{font:.72rem var(--mono)}.reader-hero__meta small{display:block;margin-bottom:6px;color:#ddd;font-size:.55rem;text-transform:uppercase}
.reader-body{min-width:0}.reader-body-inner{max-width:780px;margin:auto;padding:clamp(90px,10vw,150px) clamp(24px,6vw,92px) 100px}.reader-summary{padding-bottom:22px}.reader-summary::before{content:'Règle express';display:inline-block;margin-bottom:20px;padding:4px 8px;background:var(--pink);color:var(--ink);font:700 .65rem var(--mono);text-transform:uppercase;transform:rotate(-1deg)}.reader-summary__text{font-size:clamp(1.3rem,2.3vw,2rem);font-weight:700;line-height:1.25;text-wrap:pretty}.raction{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:24px 0}.rbtn{min-height:50px;border:0;border-radius:3px;background:var(--blue);color:#fff;font:700 .68rem var(--mono);text-transform:uppercase}.rbtn--like{background:var(--pink);color:var(--ink)}
.reader__youtube{display:block;padding:16px;border:0;border-radius:3px;background:var(--yellow);color:var(--ink);font-size:.84rem;font-weight:700}.yt-alts{display:flex;flex-wrap:wrap;gap:14px;margin:12px 0 30px}.yt-alt{color:var(--blue);font:600 .65rem var(--mono)}
.rules-title,.related__title{margin:52px 0 20px;font:700 .76rem var(--mono);text-transform:uppercase}.rules{font-size:1.08rem;line-height:1.75}.rules h1,.rules h2,.rules h3{line-height:1.15}.rules h1{margin:42px 0 14px;font-size:2rem}.rules h2{margin:36px 0 12px;font-size:1.6rem;padding-bottom:8px}.rules h3{margin:26px 0 9px;font-size:1.25rem}.rules p{margin:10px 0}.rules ul,.rules ol{margin:11px 0 11px 24px}.rules li{margin:6px 0}.rules strong{font-weight:700}.rules hr{margin:32px 0;border:0;border-top:1px solid var(--line)}.rules table{width:100%;margin:20px 0;border-collapse:collapse;font-size:.92rem}.rules td{padding:10px;border:1px solid var(--line)}.rules img{max-width:100%;margin:14px 0}.rules blockquote{margin:20px 0;padding:16px 18px;border-radius:8px;background:var(--soft)}.rules a{text-decoration:underline}
.related{margin-top:60px}.related__grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}.related__card{display:flex;flex-direction:column;gap:5px;padding:18px;border-radius:3px;background:#ffbad8}.related__card:nth-child(even){background:#b8c3ff}.related__card:hover{transform:rotate(-1deg)}.related__rel{font:700 .55rem var(--mono);text-transform:uppercase;color:var(--ink)}.related__name{font-weight:700}.related__note{font-size:.68rem;color:var(--muted)}
.reader-sources{margin-top:52px}.source-list{display:flex;flex-wrap:wrap;gap:8px}.source-list a{padding:9px 11px;border:1px solid var(--line);border-radius:3px;color:var(--muted);font:600 .65rem var(--mono)}.source-list a:hover{border-color:var(--blue);color:var(--blue)}

#toast{position:fixed;z-index:60;left:50%;bottom:24px;transform:translateX(-50%);padding:10px 16px;border-radius:3px;background:var(--ink);color:#fff;font-size:.78rem;opacity:0;pointer-events:none;transition:opacity .15s}#toast.show{opacity:1}.sheet{position:fixed;inset:0;z-index:50;display:flex;align-items:flex-end;background:rgba(0,0,0,.55);opacity:0;visibility:hidden;transition:opacity .15s}.sheet.open{opacity:1;visibility:visible}.sheet__panel{width:min(100%,680px);max-height:85dvh;margin:auto;padding:28px 24px calc(28px + env(safe-area-inset-bottom));overflow:auto;border-radius:12px 12px 0 0;background:var(--paper);transform:translateY(20px);transition:transform .2s}.sheet.open .sheet__panel{transform:none}.sheet__grab{width:40px;height:4px;border-radius:4px;background:var(--pink);margin:0 auto 20px}.sheet__title{margin-bottom:16px;font:2rem var(--display);text-transform:uppercase}.note{margin-bottom:10px;color:var(--muted);font-size:.75rem;line-height:1.5}.field{display:flex;gap:8px;margin-bottom:18px}.field input{min-width:0;flex:1;height:46px;padding:0 12px;border:2px solid var(--ink);border-radius:3px;background:var(--surface)}.btn{padding:0 18px;border:0;border-radius:3px;background:var(--blue);color:#fff;font-weight:700}.fav-row{display:flex;align-items:center;padding:14px 0}.fav-row__t{flex:1}.fav-row__t b,.fav-row__t small{display:block}.fav-row__t small{margin-top:3px;color:var(--muted)}

@media(max-width:760px){
  .brandrow{min-width:0;gap:6px}.brand{flex:0 0 28px;min-width:0;overflow:hidden;white-space:nowrap;font-size:0}.brand b{font-size:1rem}.brand .ver{display:none}.iconbtn{min-height:44px;flex:none;padding:7px;font-size:.62rem}.theme-switch{display:none}.theme-select{display:block;min-height:40px;max-width:92px;padding:0 24px 0 8px;border:1px solid rgba(255,255,255,.25);border-radius:4px;background:var(--ink);color:#fff;font:700 .58rem var(--mono)}
  .launcher{display:block;width:100%;max-width:100vw;padding-top:54px}.launcher::after{left:auto;right:18px;top:28px;font-size:.9rem}.launcher h1{font-size:clamp(4.3rem,21vw,6.2rem);text-shadow:3px 3px 0 var(--pink),6px 6px 0 var(--blue)}.launcher__intro>p:last-child{margin:30px 0 34px}.search input{height:62px;box-shadow:5px 5px 0 var(--blue)}.chips{margin:26px -18px 0;padding:0 18px}.chip,.random{min-height:44px}.launcher__actions{justify-content:flex-start;flex-wrap:wrap;gap:12px 20px}
  .section-head{padding-top:30px}.list{grid-template-columns:1fr;gap:12px;padding-inline:14px}.game{grid-template-columns:minmax(0,1fr) auto;min-height:286px;padding:0 0 14px}.game__image{height:170px}.game__index{display:block}.game__main{padding:16px 8px 2px 16px}.game__title{font-size:1.35rem}.game__sub{font-size:.76rem}.game__meta{font-size:.74rem}.game__fav{margin-right:10px;font-size:.62rem}.families__grid{grid-template-columns:1fr}.family,.family:nth-child(n){grid-column:1/-1;min-height:230px}.launcher--family{min-height:560px}.launcher__family-image{inset:auto 0 0;width:100%;height:56%;opacity:.55;-webkit-mask-image:linear-gradient(0deg,#000 45%,transparent);mask-image:linear-gradient(0deg,#000 45%,transparent)}
  .reader{display:block;width:100%;max-width:100vw}.bar{width:100%;padding:calc(10px + env(safe-area-inset-top)) 12px 10px}.bar>span{display:none}.reader-hero{position:relative;height:62dvh;min-height:430px;padding:90px 18px 24px}.reader-hero h1{font-size:clamp(3.4rem,17vw,5.6rem)}.reader-body-inner{width:100%;padding:48px 18px 80px}.reader-summary__text{font-size:1.5rem}.reader-hero__meta span{font-size:.72rem}.raction,.rbtn{min-width:0}.rbtn{padding:8px}.rules{overflow-wrap:anywhere;font-size:1.05rem}.rules table{display:block;overflow-x:auto}.related__grid{grid-template-columns:1fr}
  body.searching .launcher__intro{display:none}body.searching .launcher{padding-top:24px}
}

/* CATPPUCCIN FRAPPE */
html[data-theme="cat"]{--paper:#303446;--surface:#414559;--soft:#3b3f54;--ink:#c6d0f5;--pink:#ca9ee6;--blue:#8caaee;--yellow:#e5c890;--mint:#a6d189;--line:#51576d;--muted:#a5adce;--display:'Outfit',sans-serif;--mono:'DM Mono',monospace;--sans:'Outfit',sans-serif}
html[data-theme="cat"] body{background:#303446;color:var(--ink)}
html[data-theme="cat"] .topfix{background:rgba(41,44,60,.97);color:var(--ink);border-bottom:1px solid #414559}
html[data-theme="cat"] .brand b{color:var(--pink)}html[data-theme="cat"] .brand .ver{color:#737994}
html[data-theme="cat"] .iconbtn{border-radius:9px;color:var(--muted)}html[data-theme="cat"] .iconbtn:hover{background:#414559;color:var(--pink)}
html[data-theme="cat"] .theme-switch button{border-radius:7px;background:#363a4f;color:#838ba7}html[data-theme="cat"] .theme-switch button[aria-pressed="true"]{background:var(--pink);color:#303446}
html[data-theme="cat"] .launcher::after{display:none}html[data-theme="cat"] .launcher .eyebrow{color:var(--pink)}html[data-theme="cat"] .launcher h1{font:700 clamp(3.8rem,8vw,8rem)/.86 var(--display);text-transform:none;text-shadow:none;letter-spacing:-.055em}html[data-theme="cat"] .launcher__intro>p:last-child,html[data-theme="cat"] .top-intro{color:var(--muted)}
html[data-theme="cat"] .search label{color:var(--blue)}html[data-theme="cat"] .search input{border:1px solid #51576d;border-radius:16px;background:#292c3c;color:var(--ink);box-shadow:none}html[data-theme="cat"] .search input:focus{border-color:var(--pink);box-shadow:0 0 0 3px rgba(202,158,230,.14)}html[data-theme="cat"] .search input::placeholder{color:#737994}
html[data-theme="cat"] .random{border:0;border-radius:11px;background:var(--pink);color:#303446;box-shadow:none}html[data-theme="cat"] .random:active{transform:scale(.98);box-shadow:none}html[data-theme="cat"] .launcher__actions a{color:var(--blue);text-decoration:none}
html[data-theme="cat"] .chip,html[data-theme="cat"] .chip:nth-child(n){border-radius:9px;background:#414559;color:var(--muted)}html[data-theme="cat"] .chip--active,html[data-theme="cat"] .chip--active:nth-child(n){background:var(--pink)!important;color:#303446}
html[data-theme="cat"] .section-head .eyebrow{padding:0;background:none;color:var(--blue);transform:none}html[data-theme="cat"] .count-line{color:#838ba7}
html[data-theme="cat"] .game{border-radius:12px}html[data-theme="cat"] .game:nth-child(n):hover{background:#414559;color:var(--ink)}html[data-theme="cat"] .game__index{color:var(--pink)}html[data-theme="cat"] .game__sub{color:var(--blue)}html[data-theme="cat"] .game__meta{color:var(--muted)}html[data-theme="cat"] .game__fav:hover,html[data-theme="cat"] .game__fav.on{background:#51576d;color:var(--pink)}
html[data-theme="cat"] .families__title{font-family:var(--display);text-transform:none}html[data-theme="cat"] .family .family__name{padding:0;background:none;color:var(--pink);transform:none}html[data-theme="cat"] .family:nth-child(2) .family__name{background:none;color:var(--blue);transform:none}html[data-theme="cat"] .family:nth-child(3) .family__name{background:none;color:var(--mint)}html[data-theme="cat"] .family__link{color:var(--muted)}
html[data-theme="cat"] .reader{background:var(--paper)}html[data-theme="cat"] .bar__back{border-radius:9px;background:rgba(41,44,60,.9);color:var(--pink)}html[data-theme="cat"] .reader-hero{background:#292c3c}html[data-theme="cat"] .reader-hero::after{background:rgba(35,38,52,.48)}html[data-theme="cat"] .reader-hero__image{filter:saturate(.78) contrast(1.05)}html[data-theme="cat"] .reader-hero__eyebrow{padding:0;background:none;color:var(--pink);transform:none}html[data-theme="cat"] .reader-hero h1{font:700 clamp(3.2rem,6vw,6.8rem)/.88 var(--display);text-transform:none;text-shadow:none;letter-spacing:-.05em}html[data-theme="cat"] .reader-hero__alt{color:#fff;text-decoration-color:var(--pink)}
html[data-theme="cat"] .reader-summary::before{border-radius:7px;background:#414559;color:var(--pink);transform:none}html[data-theme="cat"] .rbtn{border-radius:10px;background:var(--blue);color:#303446}html[data-theme="cat"] .rbtn--like{background:var(--pink)}html[data-theme="cat"] .reader__youtube{border-radius:10px;background:#414559;color:#ef9f76}html[data-theme="cat"] .yt-alt{color:var(--blue)}html[data-theme="cat"] .rules blockquote{background:#414559}html[data-theme="cat"] .related__card,html[data-theme="cat"] .related__card:nth-child(even){border-radius:10px;background:#414559;color:var(--ink)}html[data-theme="cat"] .related__rel{color:var(--pink)}html[data-theme="cat"] .related__note{color:var(--muted)}
html[data-theme="cat"] .sheet__panel{background:#303446;color:var(--ink)}html[data-theme="cat"] .field input{border:1px solid #51576d;background:#292c3c;color:var(--ink)}html[data-theme="cat"] .btn{background:var(--pink);color:#303446}

/* ASCII TERMINAL */
html[data-theme="ascii"]{--paper:#11130f;--surface:#181b15;--soft:#1d2119;--ink:#e8eadf;--pink:#c7ff45;--blue:#87a7ff;--yellow:#ffd166;--mint:#7ce7b2;--line:#48503e;--muted:#949b88;--display:'IBM Plex Mono',monospace;--marker:'IBM Plex Mono',monospace;--mono:'IBM Plex Mono',monospace;--sans:'IBM Plex Mono',monospace}
html[data-theme="ascii"] body{background-color:var(--paper);background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='24' height='24'%3E%3Cpath d='M24 0H0v24' fill='none' stroke='%2348503e' stroke-width='.45' opacity='.35'/%3E%3C/svg%3E");color:var(--ink)}
html[data-theme="ascii"] .topfix{background:#0b0d0a;color:var(--ink);border-bottom:1px dashed var(--line)}html[data-theme="ascii"] .brand{font-size:0}html[data-theme="ascii"] .brand::before{content:'PK://DB';color:var(--pink);font:600 .76rem var(--mono)}html[data-theme="ascii"] .brand>*{display:none}
html[data-theme="ascii"] .iconbtn{border-radius:0;color:var(--ink)}html[data-theme="ascii"] .iconbtn::before{content:'['}html[data-theme="ascii"] .iconbtn::after{content:']'}html[data-theme="ascii"] .iconbtn:hover{background:var(--pink);color:#11130f}
html[data-theme="ascii"] .theme-switch{gap:0;border:1px solid var(--line)}html[data-theme="ascii"] .theme-switch button{border-radius:0;background:transparent;color:var(--muted)}html[data-theme="ascii"] .theme-switch button[aria-pressed="true"]{background:var(--pink);color:#11130f}
html[data-theme="ascii"] .launcher::after{content:'┌─ SESSION READY ─────────┐\A│ SELECT A GAME TO BEGIN │\A└────────────────────────┘';display:block;left:auto;right:5%;top:13%;padding:0;background:none;color:var(--muted);white-space:pre;font:500 .75rem/1.5 var(--mono);transform:none}
html[data-theme="ascii"] .launcher .eyebrow{color:var(--pink)}html[data-theme="ascii"] .launcher h1{font:600 clamp(3rem,6.5vw,6.5rem)/.95 var(--display);text-transform:uppercase;text-shadow:none;letter-spacing:-.06em}html[data-theme="ascii"] .launcher h1::before{content:'> ';color:var(--pink)}html[data-theme="ascii"] .launcher__intro>p:last-child{color:var(--muted)}
html[data-theme="ascii"] .search label{color:var(--pink)}html[data-theme="ascii"] .search label::before{content:'> '}html[data-theme="ascii"] .search input{border:1px solid var(--line);border-radius:0;background:rgba(17,19,15,.85);color:var(--ink);box-shadow:none;caret-color:var(--pink)}html[data-theme="ascii"] .search input:focus{border-color:var(--pink);box-shadow:none}html[data-theme="ascii"] .search input::placeholder{color:#67705f}
html[data-theme="ascii"] .random{border:1px solid var(--pink);border-radius:0;background:transparent;color:var(--pink);box-shadow:none}html[data-theme="ascii"] .random::before{content:'[ '}html[data-theme="ascii"] .random::after{content:' ]'}html[data-theme="ascii"] .random:active{transform:none;box-shadow:none}html[data-theme="ascii"] .launcher__actions a{color:var(--blue);text-decoration:none}
html[data-theme="ascii"] .chip,html[data-theme="ascii"] .chip:nth-child(n){border:1px solid var(--line);border-radius:0;background:transparent;color:var(--muted)}html[data-theme="ascii"] .chip::before{content:'['}html[data-theme="ascii"] .chip::after{content:']'}html[data-theme="ascii"] .chip--active,html[data-theme="ascii"] .chip--active:nth-child(n){border-color:var(--pink);background:transparent!important;color:var(--pink)}
html[data-theme="ascii"] .section-head .eyebrow{padding:0;background:none;color:var(--pink);transform:none}html[data-theme="ascii"] .section-head .eyebrow::before{content:'[ '}html[data-theme="ascii"] .section-head .eyebrow::after{content:' ]'}html[data-theme="ascii"] .count-line{color:var(--muted)}
html[data-theme="ascii"] .game{border-bottom:1px dashed var(--line);border-radius:0}html[data-theme="ascii"] .game:nth-child(n):hover{background:var(--soft);color:var(--ink)}html[data-theme="ascii"] .game__index{color:var(--pink)}html[data-theme="ascii"] .game__index::before{content:'>'}html[data-theme="ascii"] .game__title{font-family:var(--mono);font-size:1.18rem}html[data-theme="ascii"] .game__sub,html[data-theme="ascii"] .game__meta{color:var(--muted)}html[data-theme="ascii"] .game__fav::before{content:'['}html[data-theme="ascii"] .game__fav::after{content:']'}html[data-theme="ascii"] .game__fav:hover,html[data-theme="ascii"] .game__fav.on{background:var(--pink);color:#11130f}
html[data-theme="ascii"] .families__title{font:600 1.7rem var(--mono)}html[data-theme="ascii"] .family .family__name,html[data-theme="ascii"] .family:nth-child(n) .family__name{padding:0;background:none;color:var(--pink);transform:none}html[data-theme="ascii"] .family__name::before{content:'├─ '}html[data-theme="ascii"] .family__link{color:var(--muted)}html[data-theme="ascii"] .family__link::before{content:'│  ';color:var(--line)}
html[data-theme="ascii"] .reader{background:var(--paper)}html[data-theme="ascii"] .bar{font-family:var(--mono)}html[data-theme="ascii"] .bar__back{border:1px solid var(--line);border-radius:0;background:#11130f;color:var(--pink)}html[data-theme="ascii"] .bar__back::before{content:'< '}
html[data-theme="ascii"] .reader-hero{background:#0b0d0a}html[data-theme="ascii"] .reader-hero::after{background:rgba(0,0,0,.68)}html[data-theme="ascii"] .reader-hero__image{filter:grayscale(1) contrast(1.35)}html[data-theme="ascii"] .reader-hero__eyebrow{padding:0;background:none;color:var(--pink);transform:none}html[data-theme="ascii"] .reader-hero__eyebrow::before{content:'[GAME_TYPE: '}html[data-theme="ascii"] .reader-hero__eyebrow::after{content:']'}html[data-theme="ascii"] .reader-hero h1{font:600 clamp(2.8rem,6vw,6rem)/.92 var(--mono);text-transform:uppercase;text-shadow:none;letter-spacing:-.06em}html[data-theme="ascii"] .reader-hero h1::before{content:'> ';color:var(--pink)}html[data-theme="ascii"] .reader-hero__alt{color:var(--blue);text-decoration:none}
html[data-theme="ascii"] .reader-summary::before{padding:0;background:none;color:var(--pink);transform:none}html[data-theme="ascii"] .reader-summary::before{content:'[QUICK_RULE]'}html[data-theme="ascii"] .reader-summary__text{font-family:var(--mono)}html[data-theme="ascii"] .rbtn{border:1px solid var(--line);border-radius:0;background:transparent;color:var(--blue)}html[data-theme="ascii"] .rbtn--like{border-color:var(--pink);color:var(--pink)}html[data-theme="ascii"] .reader__youtube{border:1px solid var(--yellow);border-radius:0;background:transparent;color:var(--yellow)}html[data-theme="ascii"] .rules blockquote{border-left:2px solid var(--pink);border-radius:0;background:var(--soft)}
html[data-theme="ascii"] .related__card,html[data-theme="ascii"] .related__card:nth-child(even){border:1px dashed var(--line);border-radius:0;background:transparent;color:var(--ink)}html[data-theme="ascii"] .related__rel{color:var(--pink)}html[data-theme="ascii"] .sheet__panel{border-radius:0;background:var(--paper);color:var(--ink)}html[data-theme="ascii"] .sheet__title{font-family:var(--mono)}html[data-theme="ascii"] .field input{border:1px solid var(--line);border-radius:0;background:var(--surface);color:var(--ink)}html[data-theme="ascii"] .btn{border-radius:0;background:var(--pink);color:#11130f}

/* AURA — halos organiques inspirés de Shpavda */
html[data-theme="aura"]{--paper:#0d0713;--surface:#17101d;--soft:#21162b;--ink:#fff8ee;--pink:#df3cff;--blue:#7257ff;--yellow:#f1bb62;--mint:#e98fff;--line:#3c2949;--muted:#bbaac2;--display:'Paytone One',sans-serif;--marker:'Paytone One',sans-serif;--mono:'Manrope',sans-serif;--sans:'Manrope',sans-serif}
html[data-theme="aura"] body{background-color:var(--paper);background-image:radial-gradient(circle at 78% 12%,rgba(168,49,255,.3),transparent 22rem),radial-gradient(circle at 16% 40%,rgba(235,52,184,.18),transparent 26rem),radial-gradient(circle at 70% 82%,rgba(226,156,72,.16),transparent 25rem);color:var(--ink)}
html[data-theme="aura"] .topfix{background:rgba(13,7,19,.88);border-bottom:1px solid rgba(255,255,255,.1);color:var(--ink);backdrop-filter:blur(12px)}html[data-theme="aura"] .brand b{color:var(--pink)}html[data-theme="aura"] .brand .ver{color:var(--muted)}
html[data-theme="aura"] .iconbtn{border-radius:999px;color:var(--ink)}html[data-theme="aura"] .iconbtn:hover{background:var(--ink);color:var(--paper)}html[data-theme="aura"] .theme-switch button{border-radius:999px;background:transparent;color:var(--muted)}html[data-theme="aura"] .theme-switch button[aria-pressed="true"]{background:var(--ink);color:var(--paper)}
html[data-theme="aura"] .launcher{min-height:72vh;align-items:center}html[data-theme="aura"] .launcher::after{content:'✦';left:auto;right:7%;top:11%;padding:0;background:none;color:var(--yellow);font:4rem var(--display);transform:none}html[data-theme="aura"] .launcher .eyebrow{color:var(--pink)}html[data-theme="aura"] .launcher h1{font:400 clamp(4.4rem,9.6vw,9.6rem)/.83 var(--display);text-transform:none;letter-spacing:-.055em;text-shadow:none}html[data-theme="aura"] .launcher__intro>p:last-child,html[data-theme="aura"] .top-intro{color:var(--muted)}
html[data-theme="aura"] .search label{color:var(--muted)}html[data-theme="aura"] .search input{border:1px solid var(--line);border-radius:999px;background:rgba(255,255,255,.06);color:var(--ink);box-shadow:0 0 0 8px rgba(187,55,255,.06)}html[data-theme="aura"] .search input:focus{border-color:var(--pink);box-shadow:0 0 0 8px rgba(223,60,255,.11)}html[data-theme="aura"] .search input::placeholder{color:#947f9e}
html[data-theme="aura"] .random{border:0;border-radius:999px;background:var(--ink);color:var(--paper);box-shadow:none}html[data-theme="aura"] .random:active{transform:scale(.98);box-shadow:none}html[data-theme="aura"] .launcher__actions a{color:var(--ink);text-decoration-color:var(--pink)}html[data-theme="aura"] .chip,html[data-theme="aura"] .chip:nth-child(n){border:1px solid var(--line);border-radius:999px;background:transparent;color:var(--muted)}html[data-theme="aura"] .chip--active,html[data-theme="aura"] .chip--active:nth-child(n){border-color:var(--ink);background:var(--ink)!important;color:var(--paper)}
html[data-theme="aura"] .section-head .eyebrow{padding:0;background:none;color:var(--pink);transform:none}html[data-theme="aura"] .game{border-bottom:1px solid var(--line);border-radius:0}html[data-theme="aura"] .game:nth-child(n):hover{background:rgba(223,60,255,.12);color:var(--ink)}html[data-theme="aura"] .game__index{color:var(--pink)}html[data-theme="aura"] .game__sub,html[data-theme="aura"] .game__meta{color:var(--muted)}html[data-theme="aura"] .game__fav:hover,html[data-theme="aura"] .game__fav.on{border-radius:999px;background:var(--ink);color:var(--paper)}html[data-theme="aura"] .families__title{text-transform:none}html[data-theme="aura"] .family .family__name,html[data-theme="aura"] .family:nth-child(n) .family__name{border:1px solid var(--line);border-radius:999px;background:transparent;color:var(--ink);transform:none}html[data-theme="aura"] .family__link{color:var(--muted)}
html[data-theme="aura"] .reader{background:var(--paper)}html[data-theme="aura"] .bar__back{border-radius:999px;background:rgba(13,7,19,.76)}html[data-theme="aura"] .reader-hero{background:#160b1e}html[data-theme="aura"] .reader-hero::after{background:linear-gradient(0deg,rgba(13,7,19,.94),rgba(75,18,88,.22))}html[data-theme="aura"] .reader-hero__image{filter:saturate(.7) contrast(1.1)}html[data-theme="aura"] .reader-hero__eyebrow{border-radius:999px;background:var(--ink);transform:none}html[data-theme="aura"] .reader-hero h1{font:400 clamp(3.3rem,6.5vw,7rem)/.86 var(--display);text-transform:none;letter-spacing:-.05em;text-shadow:none}html[data-theme="aura"] .reader-summary::before{border-radius:999px;background:var(--pink);transform:none}html[data-theme="aura"] .rbtn,html[data-theme="aura"] .reader__youtube,html[data-theme="aura"] .related__card,html[data-theme="aura"] .source-list a{border:1px solid var(--line);border-radius:999px;background:transparent;color:var(--ink)}html[data-theme="aura"] .rbtn--like{background:var(--ink);color:var(--paper)}html[data-theme="aura"] .related__card{border-radius:18px}html[data-theme="aura"] .rules blockquote{background:var(--soft)}html[data-theme="aura"] .sheet__panel{background:var(--paper);color:var(--ink)}html[data-theme="aura"] .field input{border-color:var(--line);background:var(--surface);color:var(--ink)}

/* DETAILS — galerie éditoriale calme */
html[data-theme="details"]{--paper:#f2f0e9;--surface:#fbfaf6;--soft:#e5e2d9;--ink:#151513;--pink:#dfec61;--blue:#3957d8;--yellow:#dfec61;--mint:#b7d8ca;--line:#cbc8be;--muted:#6f6d67;--display:'Newsreader',serif;--marker:'Newsreader',serif;--mono:'Manrope',sans-serif;--sans:'Manrope',sans-serif}
html[data-theme="details"] body{background:var(--paper);color:var(--ink)}html[data-theme="details"] .topfix{background:rgba(242,240,233,.94);border-bottom:1px solid var(--line);color:var(--ink);backdrop-filter:blur(10px)}html[data-theme="details"] .brand b{color:var(--ink)}html[data-theme="details"] .brand .ver{color:var(--muted)}html[data-theme="details"] .iconbtn{color:var(--ink)}html[data-theme="details"] .iconbtn:hover{border-radius:999px;background:var(--ink);color:var(--paper)}html[data-theme="details"] .theme-switch{gap:0;border:1px solid var(--line);border-radius:999px;padding:3px}html[data-theme="details"] .theme-switch button{border-radius:999px;background:transparent;color:var(--muted)}html[data-theme="details"] .theme-switch button[aria-pressed="true"]{background:var(--ink);color:var(--paper)}
html[data-theme="details"] .launcher{min-height:72vh;align-items:center;border-bottom:1px solid var(--line)}html[data-theme="details"] .launcher::after{content:'ÉDITION 2026';left:auto;right:4vw;top:72px;padding:0;background:none;color:var(--muted);font:500 .62rem var(--mono);letter-spacing:.16em;transform:none}html[data-theme="details"] .launcher .eyebrow{color:var(--muted)}html[data-theme="details"] .launcher h1{font:400 clamp(4.5rem,10vw,10.5rem)/.76 var(--display);text-transform:none;letter-spacing:-.06em;text-shadow:none}html[data-theme="details"] .launcher__intro>p:last-child,html[data-theme="details"] .top-intro{color:var(--muted)}
html[data-theme="details"] .search label{color:var(--muted)}html[data-theme="details"] .search input{border:1px solid var(--line);border-radius:999px;background:var(--surface);color:var(--ink);box-shadow:none}html[data-theme="details"] .search input:focus{border-color:var(--ink);box-shadow:0 0 0 1px var(--ink)}html[data-theme="details"] .random{border:0;border-radius:999px;background:var(--ink);color:var(--paper);box-shadow:none}html[data-theme="details"] .random:active{transform:scale(.98);box-shadow:none}html[data-theme="details"] .launcher__actions a{color:var(--ink);text-decoration-color:var(--ink)}html[data-theme="details"] .chip,html[data-theme="details"] .chip:nth-child(n){border:1px solid var(--line);border-radius:999px;background:transparent;color:var(--muted)}html[data-theme="details"] .chip--active,html[data-theme="details"] .chip--active:nth-child(n){background:var(--ink)!important;color:var(--paper)}
html[data-theme="details"] .section-head .eyebrow{padding:0;background:none;transform:none}html[data-theme="details"] .list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1px;padding:1px;background:var(--line)}html[data-theme="details"] .game{min-height:156px;border-radius:0;background:var(--paper)}html[data-theme="details"] .game:nth-child(n):hover{background:var(--surface);color:var(--ink)}html[data-theme="details"] .game__index,html[data-theme="details"] .game__sub,html[data-theme="details"] .game__meta{color:var(--muted)}html[data-theme="details"] .game__title{font:500 clamp(1.55rem,2.3vw,2.2rem) var(--display)}html[data-theme="details"] .game__fav:hover,html[data-theme="details"] .game__fav.on{border-radius:999px;background:var(--ink);color:var(--paper)}html[data-theme="details"] .families__title{text-transform:none}html[data-theme="details"] .family .family__name,html[data-theme="details"] .family:nth-child(n) .family__name{padding:0 0 7px;border-bottom:1px solid var(--ink);background:none;color:var(--ink);transform:none}html[data-theme="details"] .family__link{color:var(--muted)}
html[data-theme="details"] .reader{background:var(--paper)}html[data-theme="details"] .bar__back{border-radius:999px;background:rgba(242,240,233,.9);color:var(--ink)}html[data-theme="details"] .reader-hero{background:#2b2a27}html[data-theme="details"] .reader-hero::after{background:linear-gradient(0deg,rgba(21,21,19,.75),transparent)}html[data-theme="details"] .reader-hero__image{filter:grayscale(1) contrast(1.05)}html[data-theme="details"] .reader-hero__eyebrow{border-radius:999px;background:var(--paper);transform:none}html[data-theme="details"] .reader-hero h1{font:400 clamp(3.5rem,7vw,7.7rem)/.82 var(--display);text-transform:none;letter-spacing:-.055em;text-shadow:none}html[data-theme="details"] .reader-summary::before{padding:0;background:none;color:var(--muted);transform:none}html[data-theme="details"] .reader-summary__text{font-family:var(--display);font-weight:400}html[data-theme="details"] .rbtn,html[data-theme="details"] .reader__youtube{border:1px solid var(--ink);border-radius:999px;background:transparent;color:var(--ink)}html[data-theme="details"] .rbtn--like{background:var(--ink);color:var(--paper)}html[data-theme="details"] .related__card,html[data-theme="details"] .related__card:nth-child(even){border:1px solid var(--line);background:var(--surface);color:var(--ink)}html[data-theme="details"] .rules blockquote{background:var(--soft)}html[data-theme="details"] .sheet__panel{background:var(--paper);color:var(--ink)}

/* BYNAR — noir orbital et bleu électrique */
html[data-theme="bynar"]{--paper:#050505;--surface:#0e0e0e;--soft:#171717;--ink:#f2f2ed;--pink:#315cff;--blue:#315cff;--yellow:#d7ff38;--mint:#82a1ff;--line:#343434;--muted:#989898;--display:'Manrope',sans-serif;--marker:'Manrope',sans-serif;--mono:'DM Mono',monospace;--sans:'Manrope',sans-serif}
html[data-theme="bynar"] body{background-color:var(--paper);background-image:radial-gradient(circle at 1px 1px,rgba(255,255,255,.13) 1px,transparent 0);background-size:19px 19px;color:var(--ink)}html[data-theme="bynar"] .topfix{background:rgba(5,5,5,.94);border-bottom:1px solid var(--line);color:var(--ink)}html[data-theme="bynar"] .brand b{color:var(--blue)}html[data-theme="bynar"] .brand .ver{color:var(--muted)}html[data-theme="bynar"] .iconbtn{color:var(--ink)}html[data-theme="bynar"] .iconbtn:hover{background:var(--blue);color:#fff}html[data-theme="bynar"] .theme-switch button{border-radius:0;background:transparent;color:var(--muted)}html[data-theme="bynar"] .theme-switch button[aria-pressed="true"]{background:var(--blue);color:#fff}
html[data-theme="bynar"] .launcher{min-height:82vh;align-items:center;isolation:isolate}html[data-theme="bynar"] .launcher::before{content:'';position:absolute;z-index:-1;right:2%;top:7%;width:min(46vw,620px);aspect-ratio:1;border:1px solid rgba(255,255,255,.35);border-radius:50%;background:repeating-radial-gradient(circle,transparent 0 31px,rgba(255,255,255,.09) 32px 33px),linear-gradient(105deg,transparent 49.8%,rgba(255,255,255,.28) 50%,transparent 50.2%)}html[data-theme="bynar"] .launcher::after{content:'SYSTEM / CARD INDEX';left:auto;right:4vw;top:auto;bottom:42px;padding:0;background:none;color:var(--muted);font:500 .62rem var(--mono);letter-spacing:.14em;transform:none}html[data-theme="bynar"] .launcher .eyebrow{color:var(--blue)}html[data-theme="bynar"] .launcher h1{font:300 clamp(4rem,9.5vw,10rem)/.82 var(--display);letter-spacing:-.07em;text-shadow:none}html[data-theme="bynar"] .launcher__intro>p:last-child,html[data-theme="bynar"] .top-intro{color:var(--muted)}
html[data-theme="bynar"] .search label{color:var(--muted)}html[data-theme="bynar"] .search input{border:0;border-bottom:1px solid var(--ink);border-radius:0;background:rgba(5,5,5,.64);color:var(--ink);box-shadow:none}html[data-theme="bynar"] .search input:focus{border-color:var(--blue);box-shadow:0 1px 0 var(--blue)}html[data-theme="bynar"] .search input::placeholder{color:#777}html[data-theme="bynar"] .random{border:1px solid var(--blue);border-radius:0;background:var(--blue);color:#fff;box-shadow:none}html[data-theme="bynar"] .random:active{transform:none;box-shadow:none}html[data-theme="bynar"] .launcher__actions a{color:var(--ink);text-decoration-color:var(--blue)}html[data-theme="bynar"] .chip,html[data-theme="bynar"] .chip:nth-child(n){border:1px solid var(--line);border-radius:0;background:#080808;color:var(--muted)}html[data-theme="bynar"] .chip--active,html[data-theme="bynar"] .chip--active:nth-child(n){border-color:var(--blue);background:var(--blue)!important;color:#fff}
html[data-theme="bynar"] .section-head .eyebrow{padding:0;background:none;color:var(--blue);transform:none}html[data-theme="bynar"] .list{background:var(--blue);padding-top:20px;padding-bottom:100px}html[data-theme="bynar"] .game{border-bottom:1px solid rgba(255,255,255,.22);border-radius:0;color:#fff}html[data-theme="bynar"] .game:nth-child(n):hover{background:#fff;color:#050505}html[data-theme="bynar"] .game__index{color:inherit}html[data-theme="bynar"] .game__sub,html[data-theme="bynar"] .game__meta{color:rgba(255,255,255,.72)}html[data-theme="bynar"] .game:hover .game__sub,html[data-theme="bynar"] .game:hover .game__meta{color:#555}html[data-theme="bynar"] .game__title{font-weight:400}html[data-theme="bynar"] .game__fav:hover,html[data-theme="bynar"] .game__fav.on{background:#050505;color:#fff}html[data-theme="bynar"] .families__title{font-weight:300}html[data-theme="bynar"] .family .family__name,html[data-theme="bynar"] .family:nth-child(n) .family__name{padding:0;background:none;color:var(--ink);transform:none}html[data-theme="bynar"] .family__link{color:var(--muted)}
html[data-theme="bynar"] .reader{background:var(--paper)}html[data-theme="bynar"] .bar__back{border:1px solid rgba(255,255,255,.35);border-radius:0;background:#050505}html[data-theme="bynar"] .reader-hero{background:var(--blue)}html[data-theme="bynar"] .reader-hero::after{background:linear-gradient(0deg,rgba(5,5,5,.78),rgba(49,92,255,.28))}html[data-theme="bynar"] .reader-hero__image{filter:grayscale(1) contrast(1.25);mix-blend-mode:luminosity}html[data-theme="bynar"] .reader-hero__eyebrow{padding:0;background:none;color:#fff;transform:none}html[data-theme="bynar"] .reader-hero h1{font:300 clamp(3rem,6.5vw,7rem)/.86 var(--display);letter-spacing:-.065em;text-shadow:none}html[data-theme="bynar"] .reader-summary::before{padding:0;background:none;color:var(--blue);transform:none}html[data-theme="bynar"] .reader-summary__text{font-weight:300}html[data-theme="bynar"] .rbtn,html[data-theme="bynar"] .reader__youtube{border:1px solid var(--line);border-radius:0;background:transparent;color:var(--ink)}html[data-theme="bynar"] .rbtn--like{border-color:var(--blue);background:var(--blue);color:#fff}html[data-theme="bynar"] .rules blockquote{border-left:2px solid var(--blue);border-radius:0;background:var(--soft)}html[data-theme="bynar"] .related__card,html[data-theme="bynar"] .related__card:nth-child(even){border:1px solid var(--line);border-radius:0;background:var(--surface);color:var(--ink)}html[data-theme="bynar"] .source-list a{border-radius:0}html[data-theme="bynar"] .sheet__panel{border-radius:0;background:var(--paper);color:var(--ink)}html[data-theme="bynar"] .field input{border-color:var(--line);background:var(--surface);color:var(--ink)}html[data-theme="bynar"] .btn{border-radius:0;background:var(--blue)}

/* APPICA — composants souples, palette adaptée au système */
html[data-theme="appica"]{color-scheme:light;--paper:#f5f7fb;--surface:#fff;--soft:#edf1f6;--ink:#111827;--pink:#bddcff;--blue:#1769e0;--yellow:#dcecff;--mint:#c8f1e1;--line:#dce2ea;--muted:#667085;--display:'Manrope',sans-serif;--marker:'Manrope',sans-serif;--mono:'Manrope',sans-serif;--sans:'Manrope',sans-serif}
html[data-theme="appica"] body{background:var(--paper);color:var(--ink)}html[data-theme="appica"] .topfix{background:rgba(255,255,255,.9);border-bottom:1px solid var(--line);color:var(--ink);backdrop-filter:blur(12px)}html[data-theme="appica"] .brand b{color:var(--blue)}html[data-theme="appica"] .brand .ver{color:var(--muted)}html[data-theme="appica"] .iconbtn{border-radius:10px;color:var(--ink)}html[data-theme="appica"] .iconbtn:hover{background:var(--soft);color:var(--blue)}html[data-theme="appica"] .theme-switch{gap:2px;padding:3px;border:1px solid var(--line);border-radius:12px;background:var(--soft)}html[data-theme="appica"] .theme-switch button{border-radius:8px;background:transparent;color:var(--muted)}html[data-theme="appica"] .theme-switch button[aria-pressed="true"]{background:var(--surface);color:var(--ink);box-shadow:0 1px 3px rgba(16,24,40,.12)}
html[data-theme="appica"] .launcher{min-height:70vh;align-items:center}html[data-theme="appica"] .launcher::after{content:'ACCESSIBLE BY DEFAULT';left:auto;right:4vw;top:82px;padding:7px 10px;border:1px solid var(--line);border-radius:9px;background:var(--surface);color:var(--muted);font:600 .58rem var(--mono);letter-spacing:.08em;transform:none;box-shadow:0 6px 18px rgba(16,24,40,.06)}html[data-theme="appica"] .launcher .eyebrow{color:var(--blue)}html[data-theme="appica"] .launcher h1{font:600 clamp(3.8rem,8vw,8rem)/.86 var(--display);text-transform:none;letter-spacing:-.065em;text-shadow:none}html[data-theme="appica"] .launcher__intro>p:last-child,html[data-theme="appica"] .top-intro{color:var(--muted)}
html[data-theme="appica"] .search label{color:var(--muted)}html[data-theme="appica"] .search input{border:1px solid var(--line);border-radius:16px;background:var(--surface);color:var(--ink);box-shadow:0 10px 28px rgba(16,24,40,.08)}html[data-theme="appica"] .search input:focus{border-color:var(--blue);box-shadow:0 0 0 4px rgba(23,105,224,.12)}html[data-theme="appica"] .random{border:0;border-radius:11px;background:var(--ink);color:var(--surface);box-shadow:0 6px 16px rgba(16,24,40,.12)}html[data-theme="appica"] .random:active{transform:scale(.98);box-shadow:none}html[data-theme="appica"] .launcher__actions a{color:var(--blue);text-decoration:none}html[data-theme="appica"] .chip,html[data-theme="appica"] .chip:nth-child(n){border:1px solid var(--line);border-radius:9px;background:var(--surface);color:var(--muted)}html[data-theme="appica"] .chip--active,html[data-theme="appica"] .chip--active:nth-child(n){border-color:var(--ink);background:var(--ink)!important;color:var(--surface)}
html[data-theme="appica"] .section-head .eyebrow{padding:0;background:none;color:var(--blue);transform:none}html[data-theme="appica"] .list{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;padding-bottom:100px}html[data-theme="appica"] .game{position:relative;grid-template-columns:minmax(0,1fr) auto;align-content:start;min-height:280px;padding:0 0 14px;border:1px solid var(--line);border-radius:18px;background:var(--surface);box-shadow:0 5px 16px rgba(16,24,40,.04)}html[data-theme="appica"] .game__image{grid-column:1/-1;width:100%;height:164px;border-radius:17px 17px 0 0}html[data-theme="appica"] .game:nth-child(n):hover{border-color:#b8c5d6;background:var(--surface);color:var(--ink);transform:translateY(-2px);box-shadow:0 14px 30px rgba(16,24,40,.09)}html[data-theme="appica"] .game__index{position:absolute;top:12px;left:12px;padding:5px 7px;border-radius:7px;background:var(--surface);color:var(--blue)}html[data-theme="appica"] .game__main{grid-column:1}html[data-theme="appica"] .game__sub,html[data-theme="appica"] .game__meta{color:var(--muted)}html[data-theme="appica"] .game__fav{grid-column:2;width:max-content;min-height:34px;margin:12px 12px 0 0;padding:0 10px;border-radius:8px;background:var(--soft);color:var(--muted)}html[data-theme="appica"] .game__fav:hover,html[data-theme="appica"] .game__fav.on{background:var(--ink);color:var(--surface)}html[data-theme="appica"] .families__title{text-transform:none}html[data-theme="appica"] .families__grid{gap:14px}html[data-theme="appica"] .family{padding:0;border:1px solid var(--line);border-radius:16px;background:var(--surface)}
html[data-theme="appica"] .reader{background:var(--paper)}html[data-theme="appica"] .bar__back{border:1px solid rgba(255,255,255,.28);border-radius:10px;background:rgba(17,24,39,.72)}html[data-theme="appica"] .reader-hero{background:#172033}html[data-theme="appica"] .reader-hero::after{background:linear-gradient(0deg,rgba(17,24,39,.86),rgba(17,24,39,.18))}html[data-theme="appica"] .reader-hero__eyebrow{border-radius:8px;background:var(--surface);transform:none}html[data-theme="appica"] .reader-hero h1{font:600 clamp(3.2rem,6.5vw,7rem)/.86 var(--display);text-transform:none;letter-spacing:-.06em;text-shadow:none}html[data-theme="appica"] .reader-summary::before{border-radius:8px;background:var(--yellow);color:var(--blue);transform:none}html[data-theme="appica"] .rbtn,html[data-theme="appica"] .reader__youtube{border:1px solid var(--line);border-radius:11px;background:var(--surface);color:var(--ink);box-shadow:0 4px 12px rgba(16,24,40,.05)}html[data-theme="appica"] .rbtn--like{border-color:var(--ink);background:var(--ink);color:var(--surface)}html[data-theme="appica"] .rules blockquote{background:var(--soft)}html[data-theme="appica"] .related__card,html[data-theme="appica"] .related__card:nth-child(even){border:1px solid var(--line);border-radius:14px;background:var(--surface);color:var(--ink)}html[data-theme="appica"] .source-list a{border-radius:9px;background:var(--surface)}html[data-theme="appica"] .sheet__panel{background:var(--paper);color:var(--ink)}html[data-theme="appica"] .field input{border-color:var(--line);border-radius:10px;background:var(--surface);color:var(--ink)}html[data-theme="appica"] .btn{border-radius:10px;background:var(--ink);color:var(--surface)}

/* EDGE — blanc ample et halos pastel inspirés d’EdgeDrop */
html[data-theme="edge"]{--paper:#faf9f7;--surface:#fff;--soft:#f0eef4;--ink:#202023;--pink:#ff9fd5;--blue:#91baff;--yellow:#ffd98a;--mint:#9ce4d1;--line:#e2dfe5;--muted:#757079;--display:'Cherry Bomb One',sans-serif;--marker:'Cherry Bomb One',sans-serif;--mono:'Manrope',sans-serif;--sans:'Manrope',sans-serif}
html[data-theme="edge"] body{background-color:var(--paper);background-image:radial-gradient(circle at 8% 8%,rgba(255,159,213,.4),transparent 24rem),radial-gradient(circle at 88% 12%,rgba(145,186,255,.4),transparent 25rem),radial-gradient(circle at 44% 44%,rgba(255,217,138,.25),transparent 27rem);background-attachment:fixed;color:var(--ink)}
html[data-theme="edge"] .topfix{background:rgba(250,249,247,.82);border-bottom:1px solid rgba(32,32,35,.08);color:var(--ink);backdrop-filter:blur(14px)}html[data-theme="edge"] .brand b{color:#7965ff}html[data-theme="edge"] .brand .ver{color:var(--muted)}html[data-theme="edge"] .iconbtn{border-radius:999px;color:var(--ink)}html[data-theme="edge"] .iconbtn:hover{background:var(--ink);color:#fff}html[data-theme="edge"] .theme-switch{padding:3px;border-radius:999px;background:rgba(255,255,255,.7)}html[data-theme="edge"] .theme-switch button{border-radius:999px;background:transparent;color:var(--muted)}html[data-theme="edge"] .theme-switch button[aria-pressed="true"]{background:var(--ink);color:#fff}
html[data-theme="edge"] .launcher{min-height:74vh;align-items:center}html[data-theme="edge"] .launcher::after{content:'100% JEU';left:auto;right:5%;top:13%;padding:10px 16px;border-radius:999px;background:#fff;color:var(--ink);font:500 .65rem var(--mono);transform:rotate(5deg);box-shadow:0 12px 32px rgba(99,73,116,.12)}html[data-theme="edge"] .launcher .eyebrow{color:#7965ff}html[data-theme="edge"] .launcher h1{font:400 clamp(4.5rem,9.5vw,10rem)/.75 var(--display);text-transform:none;letter-spacing:-.04em;text-shadow:none}html[data-theme="edge"] .launcher__intro>p:last-child,html[data-theme="edge"] .top-intro{color:var(--muted)}
html[data-theme="edge"] .search label{color:var(--muted)}html[data-theme="edge"] .search input{border:0;border-radius:999px;background:rgba(255,255,255,.86);color:var(--ink);box-shadow:0 18px 50px rgba(112,82,132,.13)}html[data-theme="edge"] .search input:focus{box-shadow:0 0 0 4px rgba(121,101,255,.18),0 18px 50px rgba(112,82,132,.13)}html[data-theme="edge"] .random{border:0;border-radius:999px;background:var(--ink);color:#fff;box-shadow:none}html[data-theme="edge"] .random:active{transform:scale(.98);box-shadow:none}html[data-theme="edge"] .launcher__actions a{color:var(--ink);text-decoration-color:#7965ff}html[data-theme="edge"] .chip,html[data-theme="edge"] .chip:nth-child(n){border:0;border-radius:999px;background:rgba(255,255,255,.72);color:var(--muted)}html[data-theme="edge"] .chip--active,html[data-theme="edge"] .chip--active:nth-child(n){background:var(--ink)!important;color:#fff}
html[data-theme="edge"] .section-head .eyebrow{padding:0;background:none;color:#7965ff;transform:none}html[data-theme="edge"] .list{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:16px;padding-bottom:110px}html[data-theme="edge"] .game{grid-template-columns:170px 38px minmax(0,1fr) auto;min-height:176px;border:1px solid rgba(255,255,255,.8);border-radius:28px;background:rgba(255,255,255,.72);box-shadow:0 12px 36px rgba(81,59,97,.08)}html[data-theme="edge"] .game__image{height:154px;border-radius:21px}html[data-theme="edge"] .game:nth-child(n):hover{background:#fff;color:var(--ink);transform:translateY(-2px)}html[data-theme="edge"] .game__index{color:#7965ff}html[data-theme="edge"] .game__sub,html[data-theme="edge"] .game__meta{color:var(--muted)}html[data-theme="edge"] .game__title{font-weight:700}html[data-theme="edge"] .game__fav:hover,html[data-theme="edge"] .game__fav.on{border-radius:999px;background:var(--ink);color:#fff}html[data-theme="edge"] .families__title{text-transform:none}html[data-theme="edge"] .family{border-radius:28px}html[data-theme="edge"] .family__image{filter:saturate(.8)}
html[data-theme="edge"] .reader{background:var(--paper)}html[data-theme="edge"] .bar__back{border-radius:999px;background:rgba(255,255,255,.88);color:var(--ink)}html[data-theme="edge"] .reader-hero{background:#f1e7ef}html[data-theme="edge"] .reader-hero::after{background:linear-gradient(0deg,rgba(32,32,35,.72),transparent)}html[data-theme="edge"] .reader-hero__image{filter:saturate(.75) contrast(.96)}html[data-theme="edge"] .reader-hero__eyebrow{border-radius:999px;background:#fff;transform:none}html[data-theme="edge"] .reader-hero h1{font:400 clamp(3.5rem,7vw,7.7rem)/.78 var(--display);text-transform:none;letter-spacing:-.03em;text-shadow:none}html[data-theme="edge"] .reader-summary::before{border-radius:999px;background:var(--pink);transform:none}html[data-theme="edge"] .rbtn,html[data-theme="edge"] .reader__youtube{border:0;border-radius:999px;background:#fff;color:var(--ink);box-shadow:0 10px 30px rgba(81,59,97,.08)}html[data-theme="edge"] .rbtn--like{background:var(--ink);color:#fff}html[data-theme="edge"] .rules blockquote{background:rgba(255,255,255,.72)}html[data-theme="edge"] .related__card,html[data-theme="edge"] .related__card:nth-child(even){border:0;border-radius:22px;background:#fff;color:var(--ink)}html[data-theme="edge"] .source-list a{border-radius:999px;background:#fff}html[data-theme="edge"] .sheet__panel{background:var(--paper);color:var(--ink)}

/* Cartes image-first : finitions propres à chaque direction */
html[data-theme="cat"] .game{border:1px solid #51576d;border-radius:16px;background:#292c3c}html[data-theme="cat"] .game__image{border-radius:15px 15px 0 0}html[data-theme="cat"] .game__index{background:#303446}
html[data-theme="ascii"] .game{min-height:310px;border:1px dashed var(--line);border-radius:0;background:rgba(17,19,15,.82)}html[data-theme="ascii"] .game__image{border-radius:0;filter:grayscale(1) contrast(1.1)}html[data-theme="ascii"] .game__index{border:1px solid var(--line);border-radius:0;background:var(--paper)}
html[data-theme="graffiti"] .game{border:3px solid var(--ink);border-radius:10px;background:var(--surface);box-shadow:6px 6px 0 var(--blue)}html[data-theme="graffiti"] .game__image{border-radius:7px 7px 0 0}html[data-theme="graffiti"] .game__index{border:2px solid var(--ink);border-radius:5px;background:var(--yellow);color:var(--ink)}
html[data-theme="aura"] .game{min-height:320px;border:1px solid var(--line);border-radius:24px;background:rgba(23,16,29,.84)}html[data-theme="aura"] .game__image{border-radius:23px 23px 0 0}html[data-theme="aura"] .game__index{border-radius:999px;background:var(--ink);color:var(--paper)}
html[data-theme="details"] .game{min-height:336px;border:1px solid var(--line);border-radius:18px;background:var(--surface)}html[data-theme="details"] .game__image{height:210px;border-radius:17px 17px 0 0}html[data-theme="details"] .game__index{background:var(--paper);color:var(--ink)}
html[data-theme="bynar"] .game{min-height:320px;border:1px solid rgba(255,255,255,.26);border-radius:16px;background:#101010;color:#fff}html[data-theme="bynar"] .game__image{border-radius:15px 15px 0 0;filter:grayscale(.25) contrast(1.08)}html[data-theme="bynar"] .game__index{border-radius:0;background:var(--blue);color:#fff}
html[data-theme="edge"] .game{grid-template-columns:minmax(0,1fr) auto;min-height:336px;padding:0 0 16px}html[data-theme="edge"] .game__image{grid-column:1/-1;width:100%;height:210px;border-radius:27px 27px 0 0}html[data-theme="edge"] .game__index{background:#fff}
html[data-theme="ascii"] .game--favorite::after{content:'[★ FAVORI]';border-radius:0}html[data-theme="graffiti"] .game--favorite::after{border:2px solid var(--ink);border-radius:5px;transform:rotate(2deg)}html[data-theme="details"] .game--favorite::after{background:var(--ink);color:var(--paper)}html[data-theme="bynar"] .game--favorite::after{border-radius:0;background:var(--yellow)}
@media(min-width:1101px){
  html[data-theme="details"] .list,html[data-theme="edge"] .list{grid-template-columns:repeat(auto-fit,minmax(420px,1fr))}
  html[data-theme="appica"] .list{grid-template-columns:repeat(auto-fit,minmax(360px,1fr))}
}
@media(prefers-color-scheme:dark){
  html[data-theme="appica"]{color-scheme:dark;--paper:#0b1120;--surface:#111827;--soft:#1d2738;--ink:#f4f7fb;--pink:#163f72;--blue:#75b5ff;--yellow:#162f4f;--mint:#153b36;--line:#2b3749;--muted:#9aa7b8}
  html[data-theme="appica"] .topfix{background:rgba(11,17,32,.92)}html[data-theme="appica"] .theme-switch button[aria-pressed="true"]{box-shadow:none}html[data-theme="appica"] .search input,html[data-theme="appica"] .game,html[data-theme="appica"] .family{box-shadow:none}html[data-theme="appica"] .game:nth-child(n):hover{border-color:#52647d;box-shadow:0 14px 30px rgba(0,0,0,.2)}
}
@media(max-width:1100px) and (min-width:761px){.theme-switch{display:none}.theme-select{display:block;min-height:38px;padding:0 28px 0 10px;border:1px solid rgba(127,127,127,.35);border-radius:8px;background:var(--ink);color:var(--paper);font:700 .58rem var(--mono)}.list{grid-template-columns:repeat(2,minmax(0,1fr))}}

@media(max-width:760px){
  html[data-theme="cat"] .brand{flex:0 0 28px;font-size:0}html[data-theme="cat"] .brand b{font-size:1rem}
  html[data-theme="cat"] .launcher h1{font-size:clamp(3.6rem,17vw,5.3rem);text-shadow:none}
  html[data-theme="cat"] .search input{box-shadow:none}
  html[data-theme="ascii"] .brand{flex:0 0 42px}
  html[data-theme="ascii"] .launcher::after{display:none}
  html[data-theme="ascii"] .launcher h1{font-size:clamp(2.8rem,14vw,4.2rem);text-shadow:none}
  html[data-theme="ascii"] .search input{box-shadow:none}
  html[data-theme="aura"] .launcher,html[data-theme="details"] .launcher,html[data-theme="bynar"] .launcher,html[data-theme="edge"] .launcher{min-height:auto}html[data-theme="aura"] .launcher::after,html[data-theme="details"] .launcher::after,html[data-theme="bynar"] .launcher::after,html[data-theme="bynar"] .launcher::before,html[data-theme="edge"] .launcher::after{display:none}
  html[data-theme="aura"] .launcher h1{font-size:clamp(3.7rem,17vw,5.4rem)}html[data-theme="details"] .launcher h1{font-size:clamp(4rem,19vw,6rem)}html[data-theme="details"] .list{grid-template-columns:1fr}html[data-theme="bynar"] .launcher h1{font-size:clamp(3.3rem,15vw,5rem)}
  html[data-theme="appica"] .launcher{min-height:auto}html[data-theme="appica"] .launcher::after{display:none}html[data-theme="appica"] .launcher h1{font-size:clamp(3.5rem,16vw,5.1rem)}html[data-theme="appica"] .list{grid-template-columns:1fr;gap:10px}html[data-theme="appica"] .game{grid-template-columns:minmax(0,1fr) auto;min-height:270px}html[data-theme="appica"] .game__image{height:155px}html[data-theme="appica"] .game__index{display:block}
  html[data-theme="edge"] .launcher h1{font-size:clamp(4rem,18vw,5.8rem)}html[data-theme="edge"] .list{grid-template-columns:1fr;gap:12px}html[data-theme="edge"] .game{grid-template-columns:minmax(0,1fr) auto;min-height:310px;border-radius:22px}html[data-theme="edge"] .game__image{height:186px;border-radius:21px 21px 0 0}html[data-theme="edge"] .game__index{display:block}
}
@media(prefers-reduced-motion:reduce){*,*::before,*::after{scroll-behavior:auto!important;transition-duration:.01ms!important}}
</style>
</head>
<body>

<?php if ($view === 'reader'):
  $g = Vault::game($slug);
  if (!$g) { http_response_code(404); $view = 'notfound'; $g = null; }
  if ($g):
    $md = Vault::read('/games/' . $g['slug'] . '.md') ?: '';
    // Tous les noms du jeu depuis game_names (source unique). Sert pour YouTube + affichage.
    $_ns = Vault::db()->prepare("SELECT name FROM game_names WHERE slug=? ORDER BY (lower(name)=lower(?)) DESC, name");
    $_ns->execute([$g['slug'], $g['title']]);
    $yNames = $_ns->fetchAll(PDO::FETCH_COLUMN);
    $_yt = fn(string $n): string => 'https://www.youtube.com/results?search_query=' . rawurlencode('règles du jeu ' . $n);
    $altNames = array_values(array_filter($yNames, fn($n) => mb_strtolower($n) !== mb_strtolower($g['title'])));
    $heroPhoto = game_photo($g, false, true);
    // Navigation clavier : prev/next.
    $_all = Vault::db()->query("SELECT slug FROM games ORDER BY sort_key(title)")->fetchAll(PDO::FETCH_COLUMN);
    $_i = array_search($g['slug'], $_all);
    $prevSlug = $_all[($_i - 1 + count($_all)) % count($_all)];
    $nextSlug = $_all[($_i + 1) % count($_all)];
    // retirer le H1 du markdown (déjà affiché en titre)
    $md = preg_replace('/^#\s+.+\n?/m', '', $md, 1);
    // Jeux liés depuis game_links (bidirectionnel).
    $_rl = Vault::db()->prepare("SELECT DISTINCT rel, note, related AS rslug FROM game_links WHERE slug=? UNION SELECT DISTINCT rel, note, slug AS rslug FROM game_links WHERE related=? ORDER BY rel, rslug");
    $_rl->execute([$g['slug'], $g['slug']]);
    $related = $_rl->fetchAll(PDO::FETCH_ASSOC);
    $_ss = Vault::db()->prepare('SELECT url FROM game_sources WHERE slug=? ORDER BY url');
    $_ss->execute([$g['slug']]);
    $sources = $_ss->fetchAll(PDO::FETCH_COLUMN);
    if ($related) {
      $slugs = array_column($related, 'rslug');
      $titles = Vault::db()->prepare("SELECT slug, title FROM games WHERE slug IN (" . implode(',', array_fill(0, count($slugs), '?')) . ")");
      $titles->execute($slugs);
      $titleMap = $titles->fetchAll(PDO::FETCH_KEY_PAIR);
    } else { $titleMap = []; }
?>
  <div class="reader" data-prev="<?= e($prevSlug) ?>" data-next="<?= e($nextSlug) ?>">
    <div class="bar">
      <a class="bar__back" href="<?= e(qs_home()) ?>">Retour</a>
      <div class="theme-switch" role="group" aria-label="Choisir le thème">
        <button type="button" data-theme-set="cat">CAT</button><button type="button" data-theme-set="ascii">ASCII</button><button type="button" data-theme-set="graffiti">GRAFF</button><button type="button" data-theme-set="aura">AURA</button><button type="button" data-theme-set="details">DETAILS</button><button type="button" data-theme-set="bynar">BYNAR</button><button type="button" data-theme-set="appica">APPICA</button><button type="button" data-theme-set="edge">EDGE</button>
      </div>
      <select class="theme-select" data-theme-select aria-label="Choisir le thème"><option value="cat">CAT</option><option value="ascii">ASCII</option><option value="graffiti">GRAFF</option><option value="aura">AURA</option><option value="details">DETAILS</option><option value="bynar">BYNAR</option><option value="appica">APPICA</option><option value="edge">EDGE</option></select>
      <span><?= e($g['type'] ?: 'Jeu de cartes') ?> / v<?= VERSION ?></span>
    </div>
    <section class="reader-hero" style="--hero-c:<?= e($g['color'] ?: '#ca9ee6') ?>" aria-labelledby="gameTitle">
      <img class="reader-hero__image" src="<?= e($heroPhoto) ?>" data-fallback="<?= e(hero_photo($g)) ?>" alt="Illustration de cartes pour <?= e($g['title']) ?>" decoding="async" fetchpriority="high" referrerpolicy="no-referrer">
      <div class="reader-hero__eyebrow"><?= e($g['category'] ?: 'jeu de cartes') ?><?php if ((int)($g['is_mistigri'] ?? 0)): ?> / MISTIGRI<?php endif; ?></div>
      <h1 id="gameTitle"><?= e($g['title']) ?></h1>
      <?php if ($altNames): ?>
      <div class="reader-hero__alts">
        <?php foreach ($altNames as $_a): ?>
        <a class="reader-hero__alt" href="https://www.youtube.com/results?search_query=<?= e(rawurlencode('règles du jeu '.$_a)) ?>" target="_blank" rel="noopener noreferrer"><?= e($_a) ?></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <div class="reader-hero__meta">
        <?php if ($g['players']): ?><span><small>Joueurs</small><?= e($g['players']) ?></span><?php endif; ?>
        <?php if ($g['cards']): ?><span><small>Cartes</small><?= e($g['cards']) ?></span><?php endif; ?>
        <?php if ($g['difficulty']): ?><span><small>Niveau</small><?= e($g['difficulty']) ?></span><?php endif; ?>
      </div>
    </section>
    <div class="reader-body">
    <div class="reader-body-inner">
    <?php
      $_summary = trim((string)$g['goal']);
      if (!$_summary) { foreach (explode("\n", $md) as $_l) { $_l = trim($_l); if ($_l !== '' && $_l[0] !== '#' && $_l[0] !== '*' && $_l[0] !== '|' && $_l[0] !== '-' && $_l[0] !== '>') { $_summary = $_l; break; } } }
    ?>
    <?php if ($_summary): ?>
    <div class="reader-summary">
      <p class="reader-summary__text"><?= e($_summary) ?></p>
    </div>
    <?php endif; ?>
    <div class="raction">
      <button class="rbtn rbtn--like" id="likeBtn" data-slug="<?= e($g['slug']) ?>">Règle utile <span id="likeCount"><?= (int)$g['votes'] ?></span></button>
      <button class="rbtn rbtn--fav" id="favBtn" data-fav="<?= e($g['slug']) ?>">Ajouter aux favoris</button>
    </div>
    <?php if ($yNames): $_main = array_shift($yNames); ?>
    <a class="reader__youtube" href="<?= e($_yt($_main)) ?>" target="_blank" rel="noopener noreferrer">Voir la règle « <?= e($_main) ?> » sur YouTube</a>
    <?php if ($yNames): ?><div class="yt-alts"><?php foreach ($yNames as $_n): ?><a class="yt-alt" href="<?= e($_yt($_n)) ?>" target="_blank" rel="noopener noreferrer"><?= e($_n) ?></a><?php endforeach; ?></div><?php endif; ?>
    <?php endif; ?>
    <h2 class="rules-title">Règles détaillées</h2>
    <div class="rules"><?= md2html($md) ?></div>
    <?php if ($sources): ?>
    <div class="reader-sources">
      <h2 class="related__title">Sources</h2>
      <div class="source-list"><?php foreach ($sources as $_source): ?><a href="<?= e($_source) ?>" target="_blank" rel="noopener noreferrer"><?= e(preg_replace('/^www\./', '', (string)parse_url($_source, PHP_URL_HOST))) ?></a><?php endforeach; ?></div>
    </div>
    <?php endif; ?>
    <?php if ($related): ?>
    <div class="related">
      <h2 class="related__title">Jeux liés</h2>
      <div class="related__grid">
        <?php foreach ($related as $r): ?>
        <a class="related__card" href="?game=<?= e($r['rslug']) ?>">
          <span class="related__rel"><?= e($r['rel']) ?></span>
          <span class="related__name"><?= e($titleMap[$r['rslug']] ?? $r['rslug']) ?></span>
          <?php if ($r['note']): ?><span class="related__note"><?= e($r['note']) ?></span><?php endif; ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    </div>
    </div>
  </div>
<?php endif;
  if ($view === 'notfound'): ?>
    <div class="empty"><h2>Jeu introuvable</h2><p><a class="chip chip--active" href="<?= e(qs_home()) ?>">Retour</a></p></div>
<?php endif;

else:
  // ----- HOME / TOP -----
  $isTop = ($view === 'top');
  $isFamily = $family !== '';
  $games = $isTop ? Vault::games(['top' => true, 'limit' => 100]) : Vault::games($isFamily ? ['family' => $family] : []);
  // Map slug -> tous les noms (game_names) pour la recherche et l'affichage.
  $namesMap = [];
  $altNamesMap = [];
  foreach (Vault::db()->query("SELECT slug, name FROM game_names ORDER BY (name=''), slug")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $namesMap[$r['slug']][] = $r['name'];
  }
  foreach ($namesMap as $slug => $names) {
    $namesMap[$slug] = implode(' ', array_map('mb_strtolower', $names)); // pour data-names (recherche)
    $altNamesMap[$slug] = $names; // pour affichage (casse originale)
  }
  // Taxonomie mécanique : un jeu peut appartenir à plusieurs familles.
  $families = [];
  $_fl = Vault::db()->query('SELECT gf.family, gf.slug FROM game_families gf JOIN games g ON g.slug=gf.slug ORDER BY gf.family,sort_key(g.title)');
  foreach ($_fl->fetchAll(PDO::FETCH_ASSOC) as $r) $families[$r['family']][] = $r['slug'];
  uasort($families, fn($a, $b) => count($b) <=> count($a));
  $gameMap = array_column($games, null, 'slug');
  $familyCover = null;
  if ($isFamily && $games) {
    $familyCover = $games[0];
    foreach ($games as $_game) if (!empty($_game['image'])) { $familyCover = $_game; break; }
  }
?>
  <header class="topfix">
    <div class="topfix__inner">
      <div class="brandrow">
        <a class="brand" href="<?= e(qs_home()) ?>" aria-label="Retour à l'accueil"><b>PK</b> / GUIDE DE TABLE <span class="ver">v<?= VERSION ?></span></a>
        <span class="spacer"></span>
        <div class="theme-switch" role="group" aria-label="Choisir le thème">
          <button type="button" data-theme-set="cat">CAT</button><button type="button" data-theme-set="ascii">ASCII</button><button type="button" data-theme-set="graffiti">GRAFF</button><button type="button" data-theme-set="aura">AURA</button><button type="button" data-theme-set="details">DETAILS</button><button type="button" data-theme-set="bynar">BYNAR</button><button type="button" data-theme-set="appica">APPICA</button><button type="button" data-theme-set="edge">EDGE</button>
        </div>
        <select class="theme-select" data-theme-select aria-label="Choisir le thème"><option value="cat">CAT</option><option value="ascii">ASCII</option><option value="graffiti">GRAFF</option><option value="aura">AURA</option><option value="details">DETAILS</option><option value="bynar">BYNAR</option><option value="appica">APPICA</option><option value="edge">EDGE</option></select>
        <a class="iconbtn" href="<?= e(qs_home(['top' => 1])) ?>">Top</a>
        <button class="iconbtn" id="favOpen">Favoris <span class="dot" id="favDot" hidden>0</span></button>
      </div>
    </div>
  </header>

  <main>
    <section class="launcher<?= $isFamily ? ' launcher--family' : '' ?>" aria-labelledby="heroTitle">
      <?php if ($familyCover): ?><img class="launcher__family-image" src="<?= e(game_photo($familyCover, false, true)) ?>" data-fallback="<?= e(hero_photo($familyCover)) ?>" alt="" decoding="async" fetchpriority="high" referrerpolicy="no-referrer"><?php endif; ?>
      <div class="launcher__intro">
        <p class="eyebrow"><?= $isFamily ? count($games).' jeux dans cette famille' : $TOTAL.' règles vérifiées / accès immédiat' ?></p>
        <h1 id="heroTitle"><?= $isFamily ? e($family) : 'On joue<br>à quoi ?' ?></h1>
        <p><?= $isFamily ? 'Une sélection réunie par mécanique de jeu.' : 'Nom officiel, surnom local ou type de jeu : une frappe suffit.' ?></p>
      </div>
      <?php if (!$isTop && !$isFamily): ?>
      <div class="search">
        <label for="searchInput">Trouver une règle</label>
        <input type="search" id="searchInput" placeholder="Yaniv, Main Verte, Speed…" autocomplete="off" autocapitalize="off" spellcheck="false">
        <span class="search__key">⌘ K</span>
      </div>
      <div class="launcher__actions">
        <button class="random" id="randomGame">Choisir au hasard</button>
        <?php if ($families): ?><a href="#families">Voir les familles</a><?php endif; ?>
      </div>
      <div class="chips" id="chips">
        <button class="chip chip--active" data-cat="">Tous</button>
        <?php foreach ($CATEGORIES as $key => $info): ?>
          <button class="chip" data-cat="<?= e($key) ?>"><?= e($info['label'] ?? $key) ?> <span><?= (int)($info['count'] ?? 0) ?></span></button>
        <?php endforeach; ?>
      </div>
      <?php elseif ($isTop): ?>
      <p class="top-intro">Les règles les plus utiles selon les joueurs.</p>
      <?php else: ?>
      <p class="top-intro"><a href="#list">Voir les jeux</a> · <a href="?">Toutes les familles</a></p>
      <?php endif; ?>
    </section>
    <div class="section-head"><p class="eyebrow"><?= $isTop ? 'Classement' : ($isFamily ? 'Famille · '.e($family) : 'Répertoire') ?></p><p class="count-line" id="countLine" data-ver="v<?= VERSION ?>"><?= count($games) ?> jeux · v<?= VERSION ?></p></div>

  <div class="list" id="list">
    <?php foreach ($games as $index => $g):
      $pshort = player_short($g); ?>
      <a class="game"
         href="<?= e(qs_game($g['slug'])) ?>"
         data-title="<?= e(mb_strtolower((string)$g['title'])) ?>"
         data-names="<?= e(mb_strtolower((string)($namesMap[$g['slug']] ?? ''))) ?>"
         data-type="<?= e(mb_strtolower((string)$g['type'])) ?>"
         data-cat="<?= e($g['category']) ?>"
         data-clm="<?= (int)($g['is_clm'] ?? 0) ?>">
        <img class="game__image lazy-image" data-src="<?= e(game_photo($g, true)) ?>" data-fallback="<?= e(hero_photo($g, 640)) ?>" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer">
        <span class="game__index"><?= str_pad((string)($index + 1), 3, '0', STR_PAD_LEFT) ?></span>
        <span class="game__main">
          <span class="game__title"><?= e($g['title']) ?></span>
          <?php
            $gAlts = array_values(array_filter($altNamesMap[$g['slug']] ?? [], fn($n) => mb_strtolower($n) !== mb_strtolower($g['title'])));
            if ($gAlts): ?>
          <span class="game__sub"><?= e(implode(' · ', $gAlts)) ?></span>
          <?php endif; ?>
          <span class="game__meta"><?php if ($pshort): ?><?= e($pshort) ?><?php endif; ?><?php if ($pshort && $g['difficulty']): ?> / <?php endif; ?><?php if ($g['difficulty']): ?><?= e($g['difficulty']) ?><?php endif; ?><?php if ((int)$g['votes'] > 0): ?> / <?= (int)$g['votes'] ?> votes<?php endif; ?></span>
        </span>
        <button class="game__fav" data-fav="<?= e($g['slug']) ?>" aria-label="Ajouter aux favoris">Fav</button>
      </a>
    <?php endforeach; ?>
    <div class="empty" id="emptyState" hidden><h2>Aucun jeu trouvé</h2><p>Essayez un autre nom ou une autre famille.</p></div>
  </div>
  <?php if ($families && !$isFamily && !$isTop): ?>
  <section class="families" id="families">
    <div class="families__head"><h2 class="families__title">Familles</h2></div>
    <div class="families__grid">
      <?php foreach ($families as $family => $slugs):
        $cover = $gameMap[$slugs[0]] ?? null;
        foreach ($slugs as $_slug) if (!empty($gameMap[$_slug]['image'])) { $cover = $gameMap[$_slug]; break; }
      ?>
      <a class="family" href="?family=<?= e(rawurlencode($family)) ?>">
        <?php if ($cover): ?><img class="family__image lazy-image" data-src="<?= e(game_photo($cover, true)) ?>" data-fallback="<?= e(hero_photo($cover, 640)) ?>" alt="" loading="lazy" decoding="async" referrerpolicy="no-referrer"><?php endif; ?>
        <span class="family__content"><span class="family__visual-title"><?= e($family) ?></span><span class="family__count"><?= count($slugs) ?> jeux · <?= e($cover['title'] ?? '') ?></span></span>
      </a>
      <?php endforeach; ?>
    </div>
  </section>
  <?php endif; ?>
  </main>
<?php endif; ?>

<!-- FAV SHEET -->
<div class="sheet" id="favSheet">
  <div class="sheet__panel">
    <div class="sheet__grab"></div>
    <div class="sheet__title">Mes favoris</div>
    <p class="note">Votre email synchronise vos favoris entre appareils. Aucun mot de passe.</p>
    <div class="field">
      <input type="email" id="emailField" placeholder="votre@email.com" autocomplete="email">
      <button class="btn" id="emailSave">OK</button>
    </div>
    <div id="favList"></div>
  </div>
</div>

<div id="toast"></div>

<script>
const api = (action, opts={}) => fetch('?api='+action, opts).then(r=>r.json());
const post = (action, body) => api(action, {method:'POST', headers:{'Content-Type':'application/json'}, body:JSON.stringify(body)});
const toast = (m) => { const t=document.getElementById('toast'); t.textContent=m; t.classList.add('show'); clearTimeout(toast._); toast._=setTimeout(()=>t.classList.remove('show'),1600); };

// ---- FAVORIS ----
const LS_EMAIL='pk_email', LS_FAVS='pk_favs';
let email = localStorage.getItem(LS_EMAIL) || '';
let favs = new Set(JSON.parse(localStorage.getItem(LS_FAVS)||'[]'));
let pendingFav = '';
const clmSlugs = () => [...document.querySelectorAll('[data-clm="1"]')].map(el=>el.dataset.fav || el.querySelector('[data-fav]')?.dataset.fav).filter(Boolean);

function syncFavUI(){
  document.querySelectorAll('[data-fav]').forEach(b=>{
    const on=favs.has(b.dataset.fav);
    b.classList.toggle('on',on);
    b.setAttribute('aria-pressed',on?'true':'false');
    b.closest('.game')?.classList.toggle('game--favorite',on);
  });
  const dot=document.getElementById('favDot');
  if(dot){ if(favs.size){dot.hidden=false; dot.textContent=favs.size;} else dot.hidden=true; }
}
async function loadFavs(){
  if(!email) return;
  const r = await api('favorites&email='+encodeURIComponent(email));
  if(Array.isArray(r)){ favs=new Set([...r,...clmSlugs()]); localStorage.setItem(LS_FAVS, JSON.stringify([...favs])); syncFavUI(); }
}
async function toggleFav(slug){
  if(!email){ pendingFav=slug; openFavSheet(); return; }
  const add = !favs.has(slug);
  favs[add?'add':'delete'](slug); syncFavUI();
  const r = await post('fav_toggle',{email, game:slug, add});
  if(r&&r.ok){ favs=new Set([...r.favorites,...clmSlugs()]); localStorage.setItem(LS_FAVS,JSON.stringify([...favs])); syncFavUI(); }
  else toast('Erreur favori');
}

document.addEventListener('click', e=>{
  const h = e.target.closest('[data-fav]');
  if(h){ e.preventDefault(); e.stopPropagation(); toggleFav(h.dataset.fav); }
});

// fav sheet
const sheet=document.getElementById('favSheet');
function openFavSheet(){ sheet.classList.add('open'); document.getElementById('emailField').value=email; renderFavList(); }
function closeFavSheet(){ sheet.classList.remove('open'); }
sheet.addEventListener('click', e=>{ if(e.target===sheet) closeFavSheet(); });
document.getElementById('favOpen')?.addEventListener('click', openFavSheet);
document.getElementById('emailSave').addEventListener('click', async ()=>{
  email = document.getElementById('emailField').value.trim().toLowerCase();
  if(!email) return;
  localStorage.setItem(LS_EMAIL,email);
  if(pendingFav){
    const r=await post('fav_toggle',{email,game:pendingFav,add:true});
    if(r&&r.ok) favs=new Set([...r.favorites,...clmSlugs()]);
    pendingFav='';
  }
  await loadFavs();
  closeFavSheet();
  syncFavUI();
  toast('Favori enregistré');
});
async function renderFavList(){
  const box=document.getElementById('favList'); box.innerHTML='';
  if(!email){ box.innerHTML='<p class="note">Entrez votre email.</p>'; return; }
  if(!favs.size){ box.innerHTML='<p class="note">Aucun favori pour le moment.</p>'; return; }
  const titles = <?= json_encode(array_column(Vault::games(), 'title', 'slug'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  [...favs].forEach(s=>{
    const d=document.createElement('a'); d.className='fav-row'; d.href='?game='+encodeURIComponent(s);
    d.innerHTML='<div class="fav-row__t"><b>'+ (titles[s]||s) +'</b><small>Ouvrir la règle</small></div><span>Favori</span>';
    box.appendChild(d);
  });
}

// ---- VOTE (reader) ----
const lb=document.getElementById('likeBtn');
if(lb){ lb.addEventListener('click', async ()=>{
  const slug=lb.dataset.slug;
  const r=await post('vote',{game:slug});
  if(r&&r.ok){ document.getElementById('likeCount').textContent=r.count; lb.style.transform='scale(.94)'; setTimeout(()=>lb.style.transform='',120); toast('Merci, '+r.count+' votes'); }
  else toast(r&&r.error==='too_soon'?'Trop vite !':'Vote bloqué');
});}

// ---- LIVE FILTER (home : recherche + chips instantanés) ----
const listEl = document.getElementById('list');
const searchInputEl = document.getElementById('searchInput');
const chipsEl = document.getElementById('chips');
const countLineEl = document.getElementById('countLine');
const emptyState = document.getElementById('emptyState');
let activeCat = '';
const normalizeSearch = s => s.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase();
function applyFilter(){
  if(!listEl) return;
  const q = searchInputEl ? normalizeSearch(searchInputEl.value.trim()) : '';
  let shown = 0;
  listEl.querySelectorAll('.game').forEach(el=>{
    const okQ = !q || normalizeSearch(el.dataset.names).includes(q) || normalizeSearch(el.dataset.type).includes(q);
    const okC = !activeCat || el.dataset.cat === activeCat;
    const show = okQ && okC;
    el.style.display = show ? '' : 'none';
    if(show) shown++;
  });
  if(countLineEl) countLineEl.textContent = shown + (shown > 1 ? ' jeux' : ' jeu') + ' · ' + countLineEl.dataset.ver;
  if(emptyState) emptyState.hidden = shown !== 0;
}
if(searchInputEl){
  searchInputEl.addEventListener('input', applyFilter);
  searchInputEl.addEventListener('focus', ()=>{
    document.body.classList.add('searching');
    window.scrollTo({top:0, behavior:'smooth'});
  });
  searchInputEl.addEventListener('blur', ()=>{
    if(!searchInputEl.value.trim()) document.body.classList.remove('searching');
  });
}
if(chipsEl) chipsEl.addEventListener('click', e=>{
  const c = e.target.closest('.chip'); if(!c) return;
  activeCat = c.dataset.cat || '';
  chipsEl.querySelectorAll('.chip').forEach(x => x.classList.toggle('chip--active', x === c));
  applyFilter();
});

document.getElementById('randomGame')?.addEventListener('click', ()=>{
  const choices=[...listEl.querySelectorAll('.game')].filter(el=>el.style.display!=='none');
  if(choices.length) location.href=choices[Math.floor(Math.random()*choices.length)].href;
});
searchInputEl?.addEventListener('keydown', e=>{
  if(e.key==='Enter'){
    const first=[...listEl.querySelectorAll('.game')].find(el=>el.style.display!=='none');
    if(first) location.href=first.href;
  }
});

// Recherche par frappe : le catalogue reste directement explorable au clavier.
document.addEventListener('keydown', e=>{
  const reader=document.querySelector('.reader');
  if(reader && !e.target.matches('input,textarea,button,a')){
    if(e.key==='ArrowLeft') location.href='?game='+encodeURIComponent(reader.dataset.prev);
    if(e.key==='ArrowRight') location.href='?game='+encodeURIComponent(reader.dataset.next);
    if(e.key==='Escape') location.href='?';
    return;
  }
  if(!searchInputEl || e.target.matches('input,textarea,button,a')) return;
  if((e.metaKey || e.ctrlKey) && e.key.toLowerCase()==='k'){ e.preventDefault(); searchInputEl.focus(); return; }
  if(e.key.length===1 && !e.metaKey && !e.ctrlKey && !e.altKey){ e.preventDefault(); searchInputEl.focus(); searchInputEl.value=e.key; applyFilter(); }
});

// ---- INIT ----
const useImageFallback=img=>{
  const fallback=img.dataset.fallback;
  if(fallback){delete img.dataset.fallback;img.src=fallback;}
  else img.classList.add('is-loaded');
};
const watchImageErrors=(img,checkExisting=true)=>{
  img.addEventListener('error',()=>useImageFallback(img));
  if(checkExisting&&img.complete&&!img.naturalWidth) useImageFallback(img);
};
const loadImage=img=>{
  img.addEventListener('load',()=>img.classList.add('is-loaded'),{once:true});
  watchImageErrors(img,false);
  img.src=img.dataset.src;
  delete img.dataset.src;
};
document.querySelectorAll('img[data-fallback]:not([data-src])').forEach(watchImageErrors);
const lazyImages=document.querySelectorAll('img[data-src]');
const imageQueue=[];
let imageBatchTimer;
const loadImageBatch=async()=>{
  imageBatchTimer=undefined;
  const batch=imageQueue.splice(0,24);
  const slugs=batch.map(img=>new URL(img.dataset.src,location.href).searchParams.get('visual')).filter(Boolean);
  try{
    const response=await fetch('?visuals='+encodeURIComponent([...new Set(slugs)].join(',')));
    if(!response.ok)throw new Error('visuals');
    const images=await response.json();
    batch.forEach(img=>{
      const slug=new URL(img.dataset.src,location.href).searchParams.get('visual');
      if(!images[slug]){loadImage(img);return;}
      img.addEventListener('load',()=>img.classList.add('is-loaded'),{once:true});
      img.src=images[slug];
      delete img.dataset.src;
    });
  }catch(error){batch.forEach(loadImage);}
  if(imageQueue.length)imageBatchTimer=setTimeout(loadImageBatch,80);
};
const queueImage=img=>{imageQueue.push(img);if(!imageBatchTimer)imageBatchTimer=setTimeout(loadImageBatch,40);};
if('IntersectionObserver' in window){
  const imageObserver=new IntersectionObserver((entries,observer)=>entries.forEach(entry=>{
    if(entry.isIntersecting){queueImage(entry.target);observer.unobserve(entry.target);}
  }),{rootMargin:'320px 0px'});
  lazyImages.forEach(img=>imageObserver.observe(img));
}else lazyImages.forEach(queueImage);

const currentTheme=()=>document.documentElement.dataset.theme || 'cat';
function syncThemeUI(){
  document.querySelectorAll('[data-theme-set]').forEach(b=>b.setAttribute('aria-pressed', b.dataset.themeSet===currentTheme() ? 'true' : 'false'));
  document.querySelectorAll('[data-theme-select]').forEach(s=>s.value=currentTheme());
  const appicaColor=matchMedia('(prefers-color-scheme: dark)').matches?'#0b1120':'#f5f7fb';
  document.querySelector('meta[name="theme-color"]').content={cat:'#303446',ascii:'#11130f',graffiti:'#151515',aura:'#0d0713',details:'#f2f0e9',bynar:'#050505',appica:appicaColor,edge:'#faf9f7'}[currentTheme()] || '#303446';
}
document.querySelectorAll('[data-theme-set]').forEach(b=>b.addEventListener('click',()=>{
  document.documentElement.dataset.theme=b.dataset.themeSet;
  try{localStorage.setItem('pk_theme',b.dataset.themeSet)}catch(e){}
  syncThemeUI();
}));
document.querySelectorAll('[data-theme-select]').forEach(s=>s.addEventListener('change',()=>{
  document.documentElement.dataset.theme=s.value;
  try{localStorage.setItem('pk_theme',s.value)}catch(e){}
  syncThemeUI();
}));
matchMedia('(prefers-color-scheme: dark)').addEventListener('change',syncThemeUI);
document.querySelectorAll('[data-clm="1"]').forEach(el=>{ const slug=el.dataset.fav || el.querySelector('[data-fav]')?.dataset.fav; if(slug) favs.add(slug); });
syncThemeUI();
syncFavUI();
applyFilter();
if(email) loadFavs();
</script>
</body>
</html>
<?php
/* ============================================================
   HELPERS DE VUES (query strings relatifs → portables)
   ============================================================ */
function qs_home(array $extra = []): string {
  $p = array_merge(['q' => '', 'cat' => '', 'top' => null], $extra);
  $parts = [];
  if (!empty($p['top'])) $parts['top'] = '1';
  if (!empty($p['cat'])) $parts['cat'] = $p['cat'];
  return $parts ? '?' . http_build_query($parts) : '?';
}
function qs_game(string $slug, array $extra = []): string {
  $parts = array_merge(['game' => $slug], $extra);
  return '?' . http_build_query($parts);
}
// builder for the home-count line was inlined above
