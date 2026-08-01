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

const VERSION = '2026.08.2';

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
    $sort = (int)$db->query('SELECT COALESCE(MAX(sort), -1) + 1 FROM games')->fetchColumn();
    foreach (glob($dir . '/*.md') ?: [] as $file) {
      $slug = pathinfo($file, PATHINFO_FILENAME);
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
      $values = [$title, $players, $cards, 'CLM', 'Règle maison', 'clm', '#ca9ee6', mb_strimwidth($plain, 0, 160, '…', 'UTF-8'), $min, $max, $slug];
      $update->execute($values);
      if (!$update->rowCount()) $insert->execute([$slug, $title, $players, $cards, 'CLM', 'Règle maison', 'clm', '#ca9ee6', $values[7], $min, $max, $sort++, 1]);
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
    $sort = (int)$db->query('SELECT COALESCE(MAX(sort), -1) + 1 FROM games')->fetchColumn();
    $mimes = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
    foreach (glob($dir . '/*.md') ?: [] as $file) {
      $slug = pathinfo($file, PATHINFO_FILENAME);
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
      $sql = 'SELECT g.*, v.count AS votes
              FROM games g JOIN votes v ON v.game_id = g.slug
              WHERE v.count > 0 ORDER BY v.count DESC, g.title ASC';
      $st = self::db()->prepare($sql . (isset($o['limit']) ? ' LIMIT ' . (int)$o['limit'] : ''));
      $st->execute([]);
      return $st->fetchAll();
    }
    $w = []; $a = [];
    if (!empty($o['cat'])) { $w[] = 'category=?'; $a[] = $o['cat']; }
    if (!empty($o['q'])) {
      $w[] = '(title LIKE ? OR EXISTS(SELECT 1 FROM game_names gn WHERE gn.slug=games.slug AND gn.name LIKE ?) OR type LIKE ? OR excerpt LIKE ?)';
      $q = '%' . $o['q'] . '%'; array_push($a, $q, $q, $q, $q);
    }
    $sql = 'SELECT *, (SELECT count FROM votes WHERE game_id=slug) AS votes FROM games';
    if ($w) $sql .= ' WHERE ' . implode(' AND ', $w);
    $sql .= ' ORDER BY sort ASC';
    if (isset($o['limit'])) $sql .= ' LIMIT ' . (int)$o['limit'];
    $st = self::db()->prepare($sql); $st->execute($a);
    return $st->fetchAll();
  }

  static function game(string $slug): ?array {
    $st = self::db()->prepare('SELECT *, (SELECT count FROM votes WHERE game_id=slug) AS votes FROM games WHERE slug=?');
    $st->execute([$slug]);
    $g = $st->fetch();
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
function hero_photo(array $g): string {
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
  return $images[abs(crc32((string)$g['slug'])) % count($images)];
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
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
<meta name="theme-color" content="#0a0a14">
<meta name="description" content="PKcards — <?= $TOTAL ?> jeux de cartes : règles, favoris, découverte.">
<title>PKcards — <?= $TOTAL ?> jeux de cartes</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='16' fill='%23292c3c'/%3E%3Crect x='13' y='9' width='38' height='48' rx='8' fill='%23f5eee5' transform='rotate(-8 32 33)'/%3E%3Cpath d='M32 42c-2.8-4.2-9-7.2-9-12.2 0-3.1 2.1-5.3 4.8-5.3 2 0 3.5 1.1 4.2 2.7.7-1.6 2.2-2.7 4.2-2.7 2.7 0 4.8 2.2 4.8 5.3 0 5-6.2 8-9 12.2Z' fill='%23e78284'/%3E%3Ccircle cx='22' cy='18' r='2' fill='%23ca9ee6'/%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
:root{--gold:#e8c46a;--bg:#0a0a14;--card:rgba(255,255,255,.035);--border:rgba(255,255,255,.07);--muted:#7a7a8c;--red:#e74c3c;--green:#2ecc71}
html,body{min-height:100%}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:var(--bg);background-image:radial-gradient(ellipse at 50% -10%,rgba(232,196,106,.08) 0%,transparent 55%);color:#e6e6ec;min-height:100dvh;padding-top:env(safe-area-inset-top);padding-bottom:env(safe-area-inset-bottom);-webkit-text-size-adjust:100%}
a{color:inherit;text-decoration:none}
button{font-family:inherit;cursor:pointer;border:none;background:none;color:inherit}

/* TOPFIX sticky : brand + recherche + filtres restent accrochés en haut */
.topfix{position:sticky;top:0;z-index:30;background:rgba(10,10,20,.86);backdrop-filter:blur(16px) saturate(1.2);-webkit-backdrop-filter:blur(16px) saturate(1.2);padding:calc(8px + env(safe-area-inset-top)) 14px 10px}
.topfix__inner{max-width:680px;margin:0 auto}
.brandrow{display:flex;align-items:center;gap:10px}
.brand{font-family:Georgia,serif;font-size:1.1rem;color:var(--gold);letter-spacing:.5px;white-space:nowrap;display:flex;align-items:baseline;gap:8px}
.brand b{color:#fff;font-weight:600}
.brand .suits{color:var(--gold);opacity:.45;letter-spacing:3px;font-size:.8rem}
.spacer{flex:1}
.iconbtn{width:40px;height:40px;border-radius:50%;background:rgba(255,255,255,.06);display:flex;align-items:center;justify-content:center;color:#d0d0da;position:relative;font-size:1.05rem;flex-shrink:0}
.iconbtn:active{transform:scale(.9)}
.iconbtn .dot{position:absolute;top:1px;right:1px;background:var(--red);color:#fff;font-size:.56rem;font-weight:700;min-width:18px;height:18px;border-radius:9px;display:flex;align-items:center;justify-content:center;padding:0 4px;border:2px solid var(--bg);box-sizing:content-box}

/* SEARCH — dans le topfix, donc sticky */
.search{margin-top:10px}
.search input{width:100%;background:rgba(0,0,0,.32);border:1px solid var(--border);color:#fff;border-radius:14px;padding:13px 16px;font-size:1rem;outline:none;transition:border-color .15s,background .15s}
.search input:focus{border-color:rgba(232,196,106,.5);background:rgba(0,0,0,.45)}
.search input::placeholder{color:#56566a}

/* CHIPS — dans le topfix */
.chips{margin-top:10px;display:flex;gap:7px;overflow-x:auto;scrollbar-width:none;padding:1px}
.chips::-webkit-scrollbar{display:none}
.chip{flex-shrink:0;padding:7px 14px;border-radius:20px;background:rgba(255,255,255,.05);border:1px solid var(--border);font-size:.78rem;color:#a8a8b6;white-space:nowrap;transition:background .15s,color .15s,border-color .15s}
.chip:active{transform:scale(.95)}
.chip--active{background:var(--gold);border-color:var(--gold);color:#1a1a24;font-weight:600}

/* LISTE */
.list{max-width:680px;margin:0 auto;padding:12px 14px 90px;display:flex;flex-direction:column;gap:8px}
.game{display:flex;align-items:center;gap:13px;padding:12px 13px;background:var(--card);border:1px solid var(--border);border-radius:15px;transition:transform .12s,background .15s,border-color .15s;position:relative}
.game:active{transform:scale(.99);background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.12)}
.game__mono{width:42px;height:42px;border-radius:11px;flex-shrink:0;display:flex;align-items:center;justify-content:center;font-family:Georgia,serif;font-size:1.15rem;font-weight:600;color:var(--c,var(--gold));background:color-mix(in srgb,var(--c,#e8c46a) 15%,transparent);border:1px solid color-mix(in srgb,var(--c,#e8c46a) 32%,transparent)}
.game__thumb{width:42px;height:42px;border-radius:11px;flex-shrink:0;object-fit:cover;background:rgba(255,255,255,.04);border:1px solid var(--border)}
.game__main{flex:1;min-width:0}
.game__title{font-size:1rem;font-weight:600;color:#f2f2f8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.25}
.game__tags{display:flex;flex-wrap:wrap;gap:5px;margin-top:6px}
.tag{font-size:.66rem;padding:2px 8px;border-radius:7px;background:rgba(255,255,255,.05);color:#9696a4;white-space:nowrap;line-height:1.6}
.tag--p{color:var(--gold);background:rgba(232,196,106,.1)}
.tag--d{color:#e09a6a;background:rgba(224,154,106,.1)}
.tag--v{color:var(--gold);background:rgba(232,196,106,.14);font-weight:600}
.game__fav{width:44px;height:44px;display:flex;align-items:center;justify-content:center;color:#48485a;font-size:1.15rem;flex-shrink:0;border-radius:50%}
.game__fav:active{transform:scale(.82)}
.game__fav.on{color:var(--red)}

.families{max-width:680px;margin:0 auto;padding:10px 14px 110px}.families__title{font-family:ui-monospace,monospace;font-size:.72rem;letter-spacing:.14em;text-transform:uppercase;color:var(--muted);margin-bottom:14px}.families__grid{display:flex;flex-direction:column;gap:10px}.family{padding:14px 16px;background:var(--card);border:1px solid var(--border);border-radius:14px}.family__name{font-family:ui-monospace,monospace;font-size:.62rem;letter-spacing:.14em;text-transform:uppercase;color:var(--gold);margin-bottom:8px}.family__members{display:flex;flex-wrap:wrap;gap:6px}.family__link{padding:4px 10px;border-radius:8px;background:rgba(255,255,255,.04);border:1px solid var(--border);color:var(--text);font-size:.78rem;transition:border-color .15s,color .15s}.family__link:hover{border-color:var(--gold);color:var(--gold)}

.count-line{text-align:center;font-size:.72rem;color:#52525e;padding:6px 0 2px;letter-spacing:.3px}
.empty{text-align:center;padding:60px 24px;color:var(--muted)}
.empty__big{font-size:2.6rem;margin-bottom:12px;opacity:.45}
.empty h2{font-size:1.05rem;color:#c0c0cc;font-weight:600;margin-bottom:4px}

/* READER */
.reader{width:100%;margin:0 auto;padding:0 0 70px}
.bar{position:sticky;top:0;z-index:15;display:flex;align-items:center;gap:12px;width:70%;max-width:1080px;margin:0 auto;padding:calc(10px + env(safe-area-inset-top)) 0 10px;background:linear-gradient(180deg,var(--bg) 80%,transparent);backdrop-filter:blur(6px)}
.bar__back{width:36px;height:36px;border-radius:50%;background:rgba(255,255,255,.06);color:var(--gold);font-size:1.3rem;display:flex;align-items:center;justify-content:center}
.bar__back:active{transform:scale(.9)}
.rtitle{font-family:Georgia,serif;font-size:1.85rem;color:#fff;line-height:1.15;margin:6px 0 12px}
.rmeta{display:flex;flex-wrap:wrap;gap:7px;margin-bottom:22px}
.rmeta span{font-size:.74rem;padding:4px 11px;border-radius:10px;background:rgba(232,196,106,.1);color:var(--gold)}
.rmeta span.m{background:rgba(255,255,255,.05);color:#aaa}
.raction{display:flex;gap:10px;margin-bottom:22px}
.rbtn{flex:1;height:48px;border-radius:13px;font-weight:700;font-size:.92rem;display:flex;align-items:center;justify-content:center;gap:7px;transition:transform .1s}
.rbtn:active{transform:scale(.96)}
.rbtn--like{background:linear-gradient(135deg,#3a2a14,#5a3f1a);color:var(--gold);border:1px solid rgba(232,196,106,.25)}
.rbtn--fav{background:rgba(255,255,255,.05);border:1px solid var(--border);color:#bbb}

.rules{font-size:.96rem;line-height:1.75;color:#c2c2cc}
.rules h1{font-family:Georgia,serif;font-size:1.45rem;color:var(--gold);margin:28px 0 10px}
.rules h2{font-size:1.15rem;color:#fff;margin:26px 0 9px;padding-bottom:6px;border-bottom:1px solid var(--border)}
.rules h3{font-size:1.02rem;color:var(--gold);margin:17px 0 5px}
.rules h4{font-size:.95rem;color:#fff;margin:14px 0 4px}
.rules p{margin:8px 0}
.rules strong{color:#fff}
.rules ul,.rules ol{margin:8px 0 8px 22px}
.rules li{margin:4px 0}
.rules hr{border:none;border-top:1px solid var(--border);margin:22px 0}
.rules table{width:100%;border-collapse:collapse;margin:12px 0;font-size:.86rem}
.rules td{padding:7px 10px;border:1px solid var(--border)}
.rules td:first-child{color:var(--gold);font-weight:600}
.rules .ok{color:var(--green)}.rules .no{color:var(--red)}
.rules img{max-width:100%;border-radius:12px;margin:2px 0 10px}
.rules blockquote{margin:12px 0;padding:8px 14px;border-left:3px solid var(--gold);background:rgba(255,255,255,.03);color:var(--muted);font-size:.85rem}
.rules blockquote a{color:var(--gold)}
.related{margin-top:32px;padding-top:24px;border-top:1px solid var(--border)}.related__title{font-family:ui-monospace,monospace;font-size:.72rem;letter-spacing:.14em;text-transform:uppercase;color:var(--muted);margin-bottom:14px}.related__grid{display:flex;flex-wrap:wrap;gap:8px}.related__card{display:flex;flex-direction:column;gap:2px;padding:10px 14px;border:1px solid var(--border);border-radius:10px;background:var(--card);min-width:140px;transition:border-color .15s,color .15s}.related__card:hover{border-color:var(--gold);color:var(--gold)}.related__rel{font-family:ui-monospace,monospace;font-size:.58rem;letter-spacing:.12em;text-transform:uppercase;color:var(--gold)}.related__name{font-size:.88rem;font-weight:600;color:#f2f2f8}.related__note{font-size:.68rem;color:var(--muted)}

/* TOAST + FAV SHEET */
#toast{position:fixed;left:50%;bottom:calc(20px + env(safe-area-inset-bottom));transform:translateX(-50%);background:rgba(20,20,32,.96);border:1px solid var(--border);color:#fff;padding:10px 18px;border-radius:12px;font-size:.85rem;z-index:60;opacity:0;transition:opacity .2s;pointer-events:none}
#toast.show{opacity:1}
.sheet{position:fixed;inset:0;z-index:50;background:rgba(0,0,0,.6);display:flex;align-items:flex-end;opacity:0;visibility:hidden;transition:opacity .2s,visibility .2s}
.sheet.open{opacity:1;visibility:visible}
.sheet__panel{width:100%;max-width:680px;margin:0 auto;background:#12121e;border-top-left-radius:22px;border-top-right-radius:22px;padding:14px 16px calc(20px + env(safe-area-inset-bottom));max-height:85dvh;overflow:auto;transform:translateY(20px);transition:transform .25s}
.sheet.open .sheet__panel{transform:translateY(0)}
.sheet__grab{width:38px;height:4px;border-radius:3px;background:#333;margin:0 auto 12px}
.sheet__title{font-size:1.05rem;color:#fff;margin-bottom:14px}
.fav-row{display:flex;align-items:center;gap:10px;padding:12px;background:var(--card);border:1px solid var(--border);border-radius:12px;margin-bottom:8px}
.fav-row__t{flex:1}.fav-row__t b{display:block;color:#fff;font-size:.92rem}.fav-row__t small{color:var(--muted);font-size:.72rem}
.field{display:flex;gap:8px;margin-bottom:14px}
.field input{flex:1;background:rgba(0,0,0,.3);border:1px solid var(--border);color:#fff;border-radius:10px;padding:11px;font-size:.9rem;outline:none}
.btn{height:44px;border-radius:11px;background:linear-gradient(135deg,#c9a84c,#e8c46a);color:#1a1a24;font-weight:700;padding:0 18px}
.note{font-size:.74rem;color:var(--muted);margin-bottom:10px;line-height:1.5}
 @media(min-width:560px){.tile__ic{width:52px;height:52px;font-size:1.7rem}}

 /* v3 visual pass: Catppuccin Frappé */
 :root{--gold:#ca9ee6;--bg:#303446;--bg-deep:#292c3c;--card:#383c50;--card-2:#41465a;--border:rgba(198,208,245,.12);--text:#c6d0f5;--muted:#a5adce;--red:#e78284;--green:#a6d189;--blue:#8caaee;--peach:#ef9f76}
 body{font-family:ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:var(--bg);color:var(--text);background-image:radial-gradient(ellipse at 75% 0%,rgba(202,158,230,.16),transparent 42%),linear-gradient(var(--bg),var(--bg-deep))}
 .topfix{background:rgba(48,52,70,.82);padding:calc(14px + env(safe-area-inset-top)) clamp(18px,4vw,56px) 14px;border-bottom:1px solid var(--border)}
 .topfix__inner{max-width:1440px}
 .brand{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.78rem;color:var(--gold);letter-spacing:.15em;text-transform:uppercase}
 .brand b{color:var(--text)}.brand .suits{color:var(--gold);opacity:.7;font-size:.7rem}
 .iconbtn{border-radius:12px;background:var(--card);border:1px solid var(--border);color:var(--text);transition:transform .18s ease,border-color .18s ease,color .18s ease}
 .iconbtn:hover{color:var(--gold);border-color:var(--gold);transform:translateY(-2px)}
 .search input{background:var(--bg-deep);border-color:var(--border);color:var(--text);border-radius:12px;box-shadow:inset 0 1px rgba(255,255,255,.03)}
 .search input:focus{border-color:var(--gold);background:#232638;box-shadow:0 0 0 3px rgba(202,158,230,.14)}
  .search input::placeholder{color:#838baa}
  body.searching .hero{display:none}
 .chip{border-radius:9px;background:rgba(255,255,255,.035);border-color:var(--border);color:var(--muted)}
 .chip--active{background:var(--gold);border-color:var(--gold);color:#232638}
 .hero{max-width:1440px;margin:0 auto;min-height:min(580px,72dvh);padding:clamp(70px,10vw,150px) clamp(18px,7vw,120px) clamp(46px,7vw,86px);display:flex;align-items:flex-end;position:relative;overflow:hidden;border-bottom:1px solid var(--border)}
 .hero__copy{position:relative;z-index:2;max-width:760px}.eyebrow{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.67rem;line-height:1.3;letter-spacing:.16em;text-transform:uppercase;color:var(--gold)}.eyebrow span{color:var(--muted);padding:0 .35rem}
 .hero h1{margin-top:18px;font-family:Georgia,serif;font-weight:400;font-size:clamp(3.8rem,10vw,9.5rem);line-height:.82;letter-spacing:-.065em;color:var(--text);text-wrap:balance}.hero h1 i{color:var(--gold);font-weight:400}
 .hero__intro{max-width:500px;margin-top:28px;color:var(--muted);font-size:clamp(.92rem,1.4vw,1.05rem);line-height:1.65;text-wrap:pretty}.hero__meta{display:flex;gap:22px;margin-top:32px;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.66rem;letter-spacing:.06em;color:var(--muted);text-transform:uppercase}.hero__meta b{color:var(--text);font-weight:600}
  .hero__mark{position:absolute;right:8vw;top:16%;display:grid;grid-template-columns:repeat(2,1fr);gap:10px;transform:rotate(12deg);font-family:Georgia,serif;font-size:clamp(5rem,13vw,12rem);line-height:.72;color:var(--gold);opacity:.16;z-index:1}.hero__mark span:nth-child(2),.hero__mark span:nth-child(3){color:var(--peach)}
  .hero__art{position:absolute;right:clamp(7vw,13vw,190px);top:12%;width:clamp(220px,27vw,370px);height:clamp(280px,35vw,470px);transform:rotate(8deg);z-index:1;pointer-events:none}.hero__card{position:absolute;width:clamp(145px,16vw,215px);aspect-ratio:2/3;display:flex;flex-direction:column;justify-content:space-between;padding:18px;border:1px solid rgba(255,255,255,.34);border-radius:18px;background:#f5eee5;color:#262538;box-shadow:18px 24px 35px rgba(17,18,29,.28);font-family:Georgia,serif;font-size:clamp(2rem,4vw,4rem);line-height:.8}.hero__card small{font-family:ui-monospace,monospace;font-size:.58rem;letter-spacing:.12em;line-height:1.1;text-transform:uppercase}.hero__card--back{right:0;top:0;transform:rotate(15deg);background:#474d6b;color:#f5eee5;border-color:rgba(255,255,255,.18);justify-content:center;align-items:center}.hero__card--back::before{content:'♠ ♥ ♦ ♣';display:block;max-width:90px;font-size:1.4rem;line-height:1.7;letter-spacing:.15em;text-align:center}.hero__card--front{left:0;bottom:0;transform:rotate(-13deg)}.hero__card--front b{font-size:clamp(4rem,8vw,7rem);font-weight:400;align-self:center;margin:auto}.hero__card--front span:last-child{align-self:flex-end;transform:rotate(180deg)}
 .hero__fade{position:absolute;inset:0;background:linear-gradient(90deg,rgba(48,52,70,0) 35%,rgba(48,52,70,.28) 64%,var(--bg) 100%),linear-gradient(0deg,var(--bg) 0%,transparent 26%);pointer-events:none}
 .section-head{max-width:1440px;margin:0 auto;padding:34px clamp(18px,7vw,120px) 10px;display:flex;align-items:center;justify-content:space-between}
 .list{max-width:1440px;margin:0 auto;padding:10px clamp(18px,7vw,120px) 110px;display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:10px}
  .game{padding:17px 15px;background:var(--card);border-color:var(--border);border-radius:14px;transition:transform .18s ease,background .18s ease,border-color .18s ease;min-height:102px;overflow:hidden}.game::before{content:'';position:absolute;inset:0 0 auto;height:3px;background:var(--c,var(--gold));opacity:.72}.game:hover{transform:translateY(-3px);background:var(--card-2);border-color:rgba(202,158,230,.45)}.game:active{transform:scale(.99);background:var(--card-2);border-color:var(--gold)}
  .game__mono{width:46px;height:46px;border-radius:12px;color:var(--c,var(--gold));background:color-mix(in srgb,var(--c,#ca9ee6) 15%,transparent);border-color:color-mix(in srgb,var(--c,#ca9ee6) 32%,transparent);font-size:1.35rem}.game__title{color:var(--text);font-weight:650}.tag{background:rgba(198,208,245,.07);color:var(--muted)}.tag--p{color:var(--blue);background:rgba(140,170,238,.12)}.tag--d{color:var(--peach);background:rgba(239,159,118,.12)}.tag--v{color:var(--gold);background:rgba(202,158,230,.14)}
 .game__fav{color:#737b9c;border-radius:12px;transition:color .18s,background .18s}.game__fav:hover{color:var(--red);background:rgba(231,130,132,.1)}
  .game__thumb{width:46px;height:46px;border-radius:12px}
  .tag--clm,.tag--mistigri{color:var(--green);background:rgba(166,209,137,.12);font-weight:700}
   .reader__youtube{display:flex;align-items:center;justify-content:center;gap:8px;margin:0 0 24px;padding:13px 16px;border:1px solid rgba(239,159,118,.35);border-radius:12px;background:rgba(239,159,118,.1);color:var(--peach);font-size:.86rem;font-weight:700;transition:transform .18s ease,background .18s ease,border-color .18s ease}.reader__youtube:hover{transform:translateY(-2px);background:rgba(239,159,118,.16);border-color:var(--peach)}
   .yt-alts{display:flex;flex-wrap:wrap;gap:7px;margin:-12px 0 24px}.yt-alt{padding:5px 12px;border:1px solid var(--border);border-radius:50px;background:var(--card);color:var(--muted);font-size:.76rem;transition:color .15s,border-color .15s,background .15s}.yt-alt:hover{color:var(--peach);border-color:var(--peach);background:rgba(239,159,118,.08)}
   .reader-hero{--hero-c:var(--gold);position:relative;height:100dvh;min-height:420px;margin:0 calc(50% - 50vw);padding:clamp(96px,18vh,150px) 0 26px;overflow:hidden;background:#1d2030;isolation:isolate;display:flex;flex-direction:column;justify-content:end;align-items:center;z-index:1}.reader-hero::after{content:'';position:absolute;inset:0;z-index:-1;background:linear-gradient(0deg,rgba(22,24,36,.62),transparent 38%),linear-gradient(90deg,rgba(22,24,36,.5) 0%,rgba(22,24,36,.18) 50%,rgba(22,24,36,0) 100%)}.reader-hero__image{position:absolute;inset:0;z-index:-2;width:100%;height:100%;object-fit:cover;object-position:center;filter:saturate(1.05) contrast(1.08) brightness(1.05)}.reader-hero__eyebrow{width:70%;max-width:1080px;font-family:'DM Mono',ui-monospace,SFMono-Regular,Menlo,monospace;color:var(--hero-c);font-size:.68rem;letter-spacing:.18em;text-transform:uppercase}.reader-hero h1{width:70%;max-width:1080px;margin:13px 0 20px;color:#f5eee5;font-family:'Cormorant Garamond',Georgia,serif;font-size:clamp(3rem,9vw,7rem);font-weight:500;letter-spacing:-.02em;line-height:.9;text-wrap:balance;text-shadow:0 3px 24px rgba(0,0,0,.28)}.reader-hero__meta{width:70%;max-width:1080px;display:flex;flex-wrap:wrap;gap:7px;font-family:'DM Mono',ui-monospace,monospace}.reader-hero__alts{width:70%;max-width:1080px;display:flex;flex-wrap:wrap;gap:8px;margin:0 0 16px}.reader-hero__alt{padding:5px 14px;border:1px solid rgba(245,238,229,.35);border-radius:50px;background:rgba(245,238,229,.08);color:#f5eee5;font-family:'DM Mono',ui-monospace,monospace;font-size:.72rem;letter-spacing:.06em;backdrop-filter:blur(6px);cursor:pointer;transition:background .15s,border-color .15s}.reader-hero__alt:hover{background:rgba(245,238,229,.16);border-color:#f5eee5}.reader-hero__meta span{padding:5px 10px;border:1px solid rgba(245,238,229,.28);border-radius:8px;background:rgba(22,24,36,.4);color:#f5eee5;font-size:.7rem;letter-spacing:.04em;backdrop-filter:blur(6px)}
   .reader-body{--fade-h:220px;position:relative;z-index:2;margin:-180px 0 0;padding:var(--fade-h) 0 70px;background:transparent}.reader-body::before{content:'';position:absolute;z-index:0;top:0;left:0;right:0;height:var(--fade-h);background:linear-gradient(180deg,transparent,var(--bg));pointer-events:none}.reader-body::after{content:'';position:absolute;z-index:0;top:var(--fade-h);right:0;bottom:0;left:0;background:var(--bg);pointer-events:none}.reader-body-inner{position:relative;z-index:1;width:70%;max-width:1080px;margin:0 auto}
 .count-line{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:.65rem;color:var(--muted);letter-spacing:.1em;text-transform:uppercase}
 :focus-visible{outline:2px solid var(--gold);outline-offset:3px}
   @media(max-width:700px){.bar{width:100%;padding-left:16px;padding-right:16px}.reader-hero__eyebrow,.reader-hero h1,.reader-hero__meta{width:100%}.hero{min-height:520px;padding-top:86px}.hero__mark{right:-18px;top:18%;font-size:7rem}.hero__art{right:-22px;top:17%;transform:scale(.78) rotate(8deg);transform-origin:top right;opacity:.72}.hero__fade{background:linear-gradient(180deg,rgba(48,52,70,0) 25%,var(--bg) 78%),linear-gradient(0deg,var(--bg) 0%,transparent 45%)}.hero__meta{flex-wrap:wrap;gap:10px 16px}.list{grid-template-columns:repeat(auto-fill,minmax(220px,1fr))}.reader-hero{min-height:380px;padding:92px 16px 26px}.reader-hero__image{object-position:62% center}.reader-body{--fade-h:160px;margin-top:-120px}.reader-body-inner{width:100%;padding:0 16px}}
 @media(prefers-reduced-motion:reduce){*,*::before,*::after{scroll-behavior:auto!important;transition-duration:.01ms!important;animation-duration:.01ms!important}}
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
    $heroPhoto = $g['image'] ? '?img=' . urlencode($g['image']) : hero_photo($g);
    // retirer le H1 du markdown (déjà affiché en titre)
    $md = preg_replace('/^#\s+.+\n?/m', '', $md, 1);
    // Jeux liés depuis game_links (bidirectionnel).
    $_rl = Vault::db()->prepare("SELECT DISTINCT rel, note, related AS rslug FROM game_links WHERE slug=? UNION SELECT DISTINCT rel, note, slug AS rslug FROM game_links WHERE related=? ORDER BY rel, rslug");
    $_rl->execute([$g['slug'], $g['slug']]);
    $related = $_rl->fetchAll(PDO::FETCH_ASSOC);
    if ($related) {
      $slugs = array_column($related, 'rslug');
      $titles = Vault::db()->prepare("SELECT slug, title FROM games WHERE slug IN (" . implode(',', array_fill(0, count($slugs), '?')) . ")");
      $titles->execute($slugs);
      $titleMap = $titles->fetchAll(PDO::FETCH_KEY_PAIR);
    } else { $titleMap = []; }
?>
  <div class="reader">
    <div class="bar">
      <a class="bar__back" href="<?= e(qs_home()) ?>" aria-label="Retour">‹</a>
      <span style="color:#777;font-size:.8rem"><?= e($g['type'] ?: 'Jeu de cartes') ?></span>
    </div>
    <section class="reader-hero" style="--hero-c:<?= e($g['color'] ?: '#ca9ee6') ?>" aria-labelledby="gameTitle">
      <img class="reader-hero__image" src="<?= e($heroPhoto) ?>" alt="Photographie de cartes pour <?= e($g['title']) ?>" referrerpolicy="no-referrer">
      <div class="reader-hero__eyebrow"><?= e($g['category'] ?: 'jeu de cartes') ?><?php if ((int)($g['is_mistigri'] ?? 0)): ?> · <span style="color:var(--green)">MISTIGRI</span><?php endif; ?></div>
      <h1 id="gameTitle"><?= e($g['title']) ?></h1>
      <?php if ($altNames): ?>
      <div class="reader-hero__alts">
        <?php foreach ($altNames as $_a): ?>
        <a class="reader-hero__alt" href="https://www.youtube.com/results?search_query=<?= e(rawurlencode('règles du jeu '.$_a)) ?>" target="_blank" rel="noopener noreferrer"><?= e($_a) ?></a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <div class="reader-hero__meta">
        <?php if ($g['players']): ?><span>👥 <?= e($g['players']) ?></span><?php endif; ?>
        <?php if ($g['cards']): ?><span>🂠 <?= e($g['cards']) ?></span><?php endif; ?>
        <?php if ($g['difficulty']): ?><span><?= e($g['difficulty']) ?></span><?php endif; ?>
        <?php if ($g['goal']): ?><span>🎯 <?= e($g['goal']) ?></span><?php endif; ?>
      </div>
    </section>
    <div class="reader-body">
    <div class="reader-body-inner">
    <div class="raction">
      <button class="rbtn rbtn--like" id="likeBtn" data-slug="<?= e($g['slug']) ?>">♥ J'aime <span id="likeCount"><?= (int)$g['votes'] ?></span></button>
      <button class="rbtn rbtn--fav" id="favBtn" data-slug="<?= e($g['slug']) ?>">★ Favori</button>
    </div>
    <?php if ($yNames): $_main = array_shift($yNames); ?>
    <a class="reader__youtube" href="<?= e($_yt($_main)) ?>" target="_blank" rel="noopener noreferrer">▶ Règles du jeu « <?= e($_main) ?> » sur YouTube</a>
    <?php if ($yNames): ?><div class="yt-alts"><?php foreach ($yNames as $_n): ?><a class="yt-alt" href="<?= e($_yt($_n)) ?>" target="_blank" rel="noopener noreferrer"><?= e($_n) ?></a><?php endforeach; ?></div><?php endif; ?>
    <?php endif; ?>
    <div class="rules"><?= md2html($md) ?></div>
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
    <div class="empty"><div class="empty__big">🃏</div><h2>Jeu introuvable</h2><p><a class="chip chip--active" href="<?= e(qs_home()) ?>">← Retour</a></p></div>
<?php endif;

else:
  // ----- HOME / TOP -----
  $isTop = ($view === 'top');
  $games = $isTop ? Vault::games(['top' => true, 'limit' => 100]) : Vault::games([]);
  // Map slug -> tous les noms (game_names) pour la recherche et l'affichage.
  $namesMap = [];
  foreach (Vault::db()->query("SELECT slug, group_concat(lower(name),' ') AS names FROM game_names GROUP BY slug")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $namesMap[$r['slug']] = $r['names'];
  }
  // Familles depuis game_links.
  $families = [];
  $_fl = Vault::db()->query("SELECT note, slug AS s FROM game_links UNION SELECT note, related AS s FROM game_links ORDER BY note, s");
  foreach ($_fl->fetchAll(PDO::FETCH_ASSOC) as $r) $families[$r['note']][] = $r['s'];
  foreach ($families as $note => &$slugs) { $slugs = array_unique($slugs); sort($slugs); }
  unset($slugs);
?>
  <div class="topfix">
    <div class="topfix__inner">
      <div class="brandrow">
        <a class="brand" href="<?= e(qs_home()) ?>" aria-label="Retour à l'accueil">PK<b>cards</b><span class="suits">♠♥♦♣</span></a>
        <span class="spacer"></span>
        <a class="iconbtn" href="<?= e(qs_home(['top' => 1])) ?>" title="Meilleurs jeux" aria-label="Top">🏆</a>
        <button class="iconbtn" id="favOpen" title="Favoris" aria-label="Favoris">♥<span class="dot" id="favDot" hidden>0</span></button>
      </div>
      <?php if (!$isTop): ?>
      <div class="search">
        <input type="search" id="searchInput" placeholder="Rechercher un jeu, un type…" autocomplete="off" autocapitalize="off" spellcheck="false">
      </div>
      <div class="chips" id="chips">
        <button class="chip chip--active" data-cat="">Tous</button>
        <?php foreach ($CATEGORIES as $key => $info): ?>
          <button class="chip" data-cat="<?= e($key) ?>"><?= e($info['label'] ?? $key) ?> · <?= (int)($info['count'] ?? 0) ?></button>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <main>
    <section class="hero" aria-labelledby="heroTitle">
      <div class="hero__copy">
        <p class="eyebrow">bibliothèque de table <span>·</span> v3</p>
        <h1 id="heroTitle">Les cartes<br><i>restent en main.</i></h1>
        <p class="hero__intro">Explorez les règles, retrouvez un jeu en une frappe et gardez vos classiques à portée de table.</p>
        <div class="hero__meta"><span><b><?= $TOTAL ?></b> jeux indexés</span><span><b>⌘ K</b> recherche rapide</span></div>
      </div>
      <div class="hero__mark" aria-hidden="true"><span>♠</span><span>♥</span><span>♦</span><span>♣</span></div>
      <div class="hero__art" aria-hidden="true">
        <div class="hero__card hero__card--back"><small>PKcards<br>jeu libre</small></div>
        <div class="hero__card hero__card--front"><small>jeu de table</small><b>♥</b><span>♥</span></div>
      </div>
      <div class="hero__fade" aria-hidden="true"></div>
    </section>
    <div class="section-head"><p class="eyebrow">le catalogue</p><p class="count-line" id="countLine" data-ver="v<?= VERSION ?>"><?= count($games) ?> jeux · v<?= VERSION ?></p></div>

  <div class="list" id="list">
    <?php foreach ($games as $g):
      $c = $g['color'] ?: '#e8c46a';
      $pshort = player_short($g);
      $init = mb_strtoupper(mb_substr(preg_replace('/^[«"\']/','',trim($g['title'])), 0, 1, 'UTF-8'), 'UTF-8'); ?>
      <a class="game" style="--c:<?= e($c) ?>"
         href="<?= e(qs_game($g['slug'])) ?>"
         data-title="<?= e(mb_strtolower((string)$g['title'])) ?>"
         data-names="<?= e(mb_strtolower((string)($namesMap[$g['slug']] ?? ''))) ?>"
         data-type="<?= e(mb_strtolower((string)$g['type'])) ?>"
         data-cat="<?= e($g['category']) ?>"
         data-clm="<?= (int)($g['is_clm'] ?? 0) ?>">
        <?php if ($g['image']): ?><img class="game__thumb" src="?img=<?= e(urlencode($g['image'])) ?>" alt="" loading="lazy"><?php else: ?>
        <span class="game__mono"><?= e($init ?: '🂠') ?></span>
        <?php endif; ?>
        <span class="game__main">
          <span class="game__title"><?= e($g['title']) ?></span>
          <span class="game__tags">
            <?php if ((int)$g['votes'] > 0): ?><span class="tag tag--v">♥ <?= (int)$g['votes'] ?></span><?php endif; ?>
            <?php if ($pshort): ?><span class="tag tag--p">👥 <?= e($pshort) ?></span><?php endif; ?>
             <?php if ($g['type']): ?><span class="tag"><?= e($g['type']) ?></span><?php endif; ?>
             <?php if ((int)($g['is_clm'] ?? 0)): ?><span class="tag tag--clm">CLM</span><?php endif; ?>
            <?php if ((int)($g['is_mistigri'] ?? 0)): ?><span class="tag tag--mistigri">Mistigri</span><?php endif; ?>
             <?php if ($g['difficulty']): ?><span class="tag tag--d"><?= e($g['difficulty']) ?></span><?php endif; ?>
          </span>
        </span>
        <button class="game__fav" data-fav="<?= e($g['slug']) ?>" aria-label="Favori">♥</button>
      </a>
    <?php endforeach; ?>
    <div class="empty" id="emptyState" hidden><div class="empty__big">🔍</div><h2>Aucun jeu</h2><p>Essayez une autre recherche.</p></div>
  </div>
  <?php if ($families): ?>
  <section class="families" id="families">
    <div class="families__head"><h2 class="families__title">Familles</h2></div>
    <div class="families__grid">
      <?php foreach ($families as $note => $slugs): ?>
      <div class="family">
        <h3 class="family__name"><?= e($note) ?></h3>
        <div class="family__members">
          <?php foreach ($slugs as $s): $gt = ''; foreach ($games as $gg) { if ($gg['slug']===$s) { $gt=$gg['title']; break; } } ?>
          <a class="family__link" href="?game=<?= e($s) ?>"><?= e($gt ?: $s) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
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
    <div class="sheet__title">♥ Mes favoris</div>
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
    b.classList.toggle('on', favs.has(b.dataset.fav));
  });
  const dot=document.getElementById('favDot');
  if(favs.size){dot.hidden=false; dot.textContent=favs.size;} else dot.hidden=true;
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
document.getElementById('favOpen').addEventListener('click', openFavSheet);
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
    d.innerHTML='<div class="fav-row__t"><b>'+ (titles[s]||s) +'</b><small>Ouvrir la règle →</small></div><span class="heart on">♥</span>';
    box.appendChild(d);
  });
}

// ---- VOTE (reader) ----
const lb=document.getElementById('likeBtn');
if(lb){ lb.addEventListener('click', async ()=>{
  const slug=lb.dataset.slug;
  const r=await post('vote',{game:slug});
  if(r&&r.ok){ document.getElementById('likeCount').textContent=r.count; lb.style.transform='scale(.94)'; setTimeout(()=>lb.style.transform='',120); toast('Merci ! ♥ '+r.count); }
  else toast(r&&r.error==='too_soon'?'Trop vite !':'Vote bloqué');
});}

// ---- LIVE FILTER (home : recherche + chips instantanés) ----
const listEl = document.getElementById('list');
const searchInputEl = document.getElementById('searchInput');
const chipsEl = document.getElementById('chips');
const countLineEl = document.getElementById('countLine');
const emptyState = document.getElementById('emptyState');
let activeCat = '';
function applyFilter(){
  if(!listEl) return;
  const q = searchInputEl ? searchInputEl.value.trim().toLowerCase() : '';
  let shown = 0;
  listEl.querySelectorAll('.game').forEach(el=>{
    const okQ = !q || el.dataset.names.includes(q) || el.dataset.type.includes(q);
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

// Recherche par frappe : le catalogue reste directement explorable au clavier.
document.addEventListener('keydown', e=>{
  if(!searchInputEl || e.target.matches('input,textarea,button,a')) return;
  if((e.metaKey || e.ctrlKey) && e.key.toLowerCase()==='k'){ e.preventDefault(); searchInputEl.focus(); return; }
  if(e.key.length===1 && !e.metaKey && !e.ctrlKey && !e.altKey){ e.preventDefault(); searchInputEl.focus(); searchInputEl.value=e.key; applyFilter(); }
});

// ---- INIT ----
document.querySelectorAll('[data-clm="1"]').forEach(el=>{ const slug=el.dataset.fav || el.querySelector('[data-fav]')?.dataset.fav; if(slug) favs.add(slug); });
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
function game_glyph(string $slug): string {
  $map = ['regicide'=>'♚','yaniv'=>'🃏','president'=>'👑','kems'=>'🤝','bataille-corse'=>'⚡',
          'paquet-de-merde'=>'💩','pouilleux'=>'🤢','tarot'=>'🃏','belote'=>'♠','rummy'=>'🔄',
          'gin-rummy'=>'🍸','barbu'=>'🧔','poker'=>'🎲','blackjack'=>'🂡','uno'=>'1️⃣'];
  foreach ($map as $k=>$v) if (str_contains($slug, $k)) return $v;
  return '🂠';
}
// builder for the home-count line was inlined above
