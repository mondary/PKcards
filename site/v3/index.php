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

const VERSION = '2026.08.6';

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
<meta name="theme-color" content="#f7f7f4">
<meta name="description" content="PKcards — <?= $TOTAL ?> jeux de cartes : règles, favoris, découverte.">
<title>PKcards — <?= $TOTAL ?> jeux de cartes</title>
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%2310261f'/%3E%3Ctext x='32' y='39' text-anchor='middle' font-family='Arial' font-size='23' font-weight='700' fill='%23d6ef72'%3EPK%3C/text%3E%3C/svg%3E">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Archivo:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<style>
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
:root{--paper:#f7f7f4;--surface:#fff;--soft:#eceee9;--ink:#17201c;--forest:#163c31;--acid:#d6ef72;--line:#dcdfda;--muted:#717771;--mono:'IBM Plex Mono',monospace;--sans:'Archivo',sans-serif}
html{scroll-behavior:smooth}
body{min-height:100dvh;overflow-x:hidden;background:var(--paper);color:var(--ink);font-family:var(--sans);-webkit-text-size-adjust:100%}
a{color:inherit;text-decoration:none}button,input{font:inherit}button{color:inherit;cursor:pointer}:focus-visible{outline:3px solid var(--forest);outline-offset:3px}
.eyebrow,.count-line{font:500 .68rem/1.2 var(--mono);letter-spacing:.11em;text-transform:uppercase}

.topfix{position:sticky;top:0;z-index:30;width:100%;max-width:100vw;padding:calc(14px + env(safe-area-inset-top)) clamp(18px,4vw,64px) 14px;background:rgba(247,247,244,.94);color:var(--ink)}
.topfix__inner{max-width:1440px;margin:auto}.brandrow{display:flex;align-items:center;gap:12px}.brand{font:500 .68rem var(--mono);letter-spacing:.08em}.brand b{color:var(--forest)}.brand .ver{margin-left:8px;color:#82938c;font-size:.56rem}.spacer{flex:1}
.iconbtn{position:relative;padding:8px 10px;border:0;border-radius:6px;background:transparent;color:var(--ink);font:500 .64rem var(--mono);text-transform:uppercase;transition:background .15s}.iconbtn:hover{background:var(--soft)}.dot{margin-left:4px;color:var(--forest)}

.launcher{width:100%;max-width:1440px;margin:auto;padding:clamp(56px,8vw,120px) clamp(18px,4vw,64px) 48px;color:var(--ink);display:grid;grid-template-columns:minmax(250px,.72fr) minmax(320px,1.28fr);column-gap:clamp(48px,8vw,140px);align-items:end}.launcher>*{min-width:0}
.launcher__intro{grid-row:span 3}.launcher .eyebrow{color:var(--muted)}.launcher h1{margin-top:16px;max-width:650px;font-size:clamp(3.2rem,7vw,7rem);font-weight:600;line-height:.88;letter-spacing:-.065em;text-wrap:balance}.launcher__intro>p:last-child,.top-intro{max-width:430px;margin-top:28px;color:var(--muted);font-size:.96rem;line-height:1.65;text-wrap:pretty}
.search{position:relative}.search label{display:block;margin-bottom:10px;color:var(--muted);font:500 .66rem var(--mono);text-transform:uppercase}.search input{width:100%;height:68px;padding:0 62px 0 20px;border:0;border-radius:12px;background:var(--surface);color:var(--ink);box-shadow:0 8px 30px rgba(23,32,28,.06);font-size:clamp(1rem,2vw,1.25rem);outline:none}.search input:focus{box-shadow:0 0 0 2px var(--forest)}.search input::placeholder{color:#8a8f89}.search__key{position:absolute;right:18px;bottom:24px;color:var(--muted);font:.7rem var(--mono)}
.launcher__actions{display:flex;gap:24px;align-items:center;margin-top:18px;font:500 .7rem var(--mono);text-transform:uppercase}.random{padding:12px 16px;border:0;border-radius:7px;background:var(--forest);color:#fff;font:inherit;text-transform:inherit}.launcher__actions a{color:var(--muted)}.launcher__actions a:hover{color:var(--ink)}
.chips{grid-column:2;display:flex;gap:8px;margin-top:28px;overflow-x:auto;scrollbar-width:none}.chips::-webkit-scrollbar{display:none}.chip{flex:none;padding:9px 12px;border:0;border-radius:7px;background:var(--soft);color:var(--muted);font:500 .64rem var(--mono);text-transform:uppercase}.chip span{margin-left:5px;color:#969b95}.chip--active{background:var(--forest);color:#fff}.chip--active span{color:#b9c8c2}

.section-head,.list,.families{max-width:1440px;margin:auto}.section-head{display:flex;justify-content:space-between;padding:46px clamp(18px,4vw,64px) 18px}.section-head .eyebrow{color:var(--ink)}.count-line{color:var(--muted);font-variant-numeric:tabular-nums}
.list{padding:0 clamp(10px,3.4vw,52px) 96px}.game{display:grid;grid-template-columns:56px minmax(0,1fr) auto;align-items:center;min-height:92px;padding:0 12px;border-radius:10px;transition:background .15s}.game:hover{background:var(--soft)}.game__index{font:.65rem var(--mono);color:#9a9f99;font-variant-numeric:tabular-nums}.game__main{min-width:0;padding:16px 12px}.game__title{display:block;font-size:clamp(1.05rem,2vw,1.4rem);font-weight:600;letter-spacing:-.025em}.game__sub,.game__meta{display:block;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.game__sub{margin-top:5px;color:var(--muted);font:.7rem var(--mono)}.game__meta{margin-top:7px;color:var(--muted);font-size:.7rem}.game__fav{min-width:48px;min-height:44px;border:0;border-radius:6px;background:transparent;color:var(--muted);font:500 .62rem var(--mono);text-transform:uppercase}.game__fav:hover,.game__fav.on{background:var(--surface);color:var(--forest)}
.families{padding:28px clamp(18px,4vw,64px) 120px}.families__title{margin-bottom:38px;font-size:1.5rem}.families__grid{display:grid;grid-template-columns:repeat(3,1fr);gap:48px}.family{min-height:150px}.family__name{font:.68rem var(--mono);text-transform:uppercase}.family__members{display:flex;flex-direction:column;margin-top:18px}.family__link{padding:6px 0;color:var(--muted);font-size:.88rem}.family__link:hover{color:var(--ink)}
.empty{padding:72px 18px;text-align:center;color:var(--muted)}.empty h2{margin-bottom:8px;color:var(--ink)}

.reader{display:grid;grid-template-columns:minmax(380px,42vw) minmax(0,1fr);min-height:100dvh;background:var(--paper)}.bar{position:fixed;z-index:20;top:0;left:0;width:42vw;display:flex;justify-content:space-between;align-items:center;padding:calc(18px + env(safe-area-inset-top)) 24px 16px;color:#fff;font:.64rem var(--mono);text-transform:uppercase}.bar__back{padding:9px 11px;border:0;border-radius:6px;background:rgba(16,38,31,.72)}
.reader-hero{position:sticky;top:0;height:100dvh;overflow:hidden;display:flex;flex-direction:column;justify-content:flex-end;padding:100px clamp(24px,4vw,64px) 44px;background:var(--forest);color:#fff;isolation:isolate}.reader-hero::after{content:'';position:absolute;inset:0;z-index:-1;background:rgba(9,24,19,.5)}.reader-hero__image{position:absolute;inset:0;z-index:-2;width:100%;height:100%;object-fit:cover;filter:saturate(.7) contrast(1.08)}.reader-hero__eyebrow{color:#dce7e2;font:500 .65rem var(--mono);text-transform:uppercase}.reader-hero h1{margin:14px 0 18px;font-size:clamp(2.6rem,5.8vw,6.2rem);line-height:.9;letter-spacing:-.06em;text-wrap:balance}.reader-hero__alts{display:flex;flex-wrap:wrap;gap:14px;margin-bottom:28px}.reader-hero__alt{color:#dce7e2;font:.62rem var(--mono)}.reader-hero__meta{display:flex;gap:36px}.reader-hero__meta span{font:.72rem var(--mono)}.reader-hero__meta small{display:block;margin-bottom:6px;color:#b7c3be;font-size:.55rem;text-transform:uppercase}
.reader-body{min-width:0}.reader-body-inner{max-width:780px;margin:auto;padding:clamp(90px,10vw,150px) clamp(24px,6vw,92px) 100px}.reader-summary{padding-bottom:22px}.reader-summary::before{content:'Règle express';display:block;margin-bottom:18px;color:var(--muted);font:500 .65rem var(--mono);text-transform:uppercase}.reader-summary__text{font-size:clamp(1.3rem,2.3vw,2rem);font-weight:600;line-height:1.3;letter-spacing:-.03em;text-wrap:pretty}.raction{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin:22px 0}.rbtn{min-height:48px;border:0;border-radius:7px;background:var(--soft);font:500 .68rem var(--mono);text-transform:uppercase}.rbtn--like{background:var(--forest);color:#fff}
.reader__youtube{display:block;padding:16px;border:0;border-radius:8px;background:#eef1ed;color:var(--forest);font-size:.84rem;font-weight:600}.yt-alts{display:flex;flex-wrap:wrap;gap:14px;margin:12px 0 30px}.yt-alt{color:var(--muted);font:.65rem var(--mono)}
.rules-title,.related__title{margin:48px 0 18px;font:500 .65rem var(--mono);letter-spacing:.12em;text-transform:uppercase}.rules{font-size:.96rem;line-height:1.72}.rules h1,.rules h2,.rules h3{line-height:1.15;letter-spacing:-.025em}.rules h1{margin:40px 0 12px;font-size:1.7rem}.rules h2{margin:34px 0 10px;font-size:1.35rem;padding-bottom:8px}.rules h3{margin:24px 0 8px;font-size:1.05rem}.rules p{margin:9px 0}.rules ul,.rules ol{margin:10px 0 10px 22px}.rules li{margin:5px 0}.rules strong{font-weight:700}.rules hr{margin:30px 0;border:0;border-top:1px solid var(--line)}.rules table{width:100%;margin:18px 0;border-collapse:collapse;font-size:.82rem}.rules td{padding:9px;border:1px solid var(--line)}.rules img{max-width:100%;margin:12px 0}.rules blockquote{margin:18px 0;padding:14px 16px;border-radius:8px;background:var(--soft)}.rules a{text-decoration:underline}
.related{margin-top:60px}.related__grid{display:grid;grid-template-columns:repeat(2,1fr);gap:10px}.related__card{display:flex;flex-direction:column;gap:5px;padding:18px;border-radius:9px;background:var(--soft)}.related__card:hover{background:#e4e8e2}.related__rel{font:.55rem var(--mono);text-transform:uppercase;color:var(--muted)}.related__name{font-weight:600}.related__note{font-size:.68rem;color:var(--muted)}

#toast{position:fixed;z-index:60;left:50%;bottom:24px;transform:translateX(-50%);padding:10px 16px;border-radius:7px;background:var(--forest);color:#fff;font-size:.78rem;opacity:0;pointer-events:none;transition:opacity .15s}#toast.show{opacity:1}.sheet{position:fixed;inset:0;z-index:50;display:flex;align-items:flex-end;background:rgba(9,24,19,.45);opacity:0;visibility:hidden;transition:opacity .15s}.sheet.open{opacity:1;visibility:visible}.sheet__panel{width:min(100%,680px);max-height:85dvh;margin:auto;padding:28px 24px calc(28px + env(safe-area-inset-bottom));overflow:auto;border-radius:18px 18px 0 0;background:var(--paper);transform:translateY(20px);transition:transform .2s}.sheet.open .sheet__panel{transform:none}.sheet__grab{width:40px;height:4px;border-radius:4px;background:var(--line);margin:0 auto 20px}.sheet__title{margin-bottom:16px;font-size:1.3rem;font-weight:700}.note{margin-bottom:10px;color:var(--muted);font-size:.75rem;line-height:1.5}.field{display:flex;gap:8px;margin-bottom:18px}.field input{min-width:0;flex:1;height:46px;padding:0 12px;border:0;border-radius:7px;background:var(--surface)}.btn{padding:0 18px;border:0;border-radius:7px;background:var(--forest);color:#fff;font-weight:700}.fav-row{display:flex;align-items:center;padding:14px 0}.fav-row__t{flex:1}.fav-row__t b,.fav-row__t small{display:block}.fav-row__t small{margin-top:3px;color:var(--muted)}

@media(max-width:760px){
  .brandrow{min-width:0}.brand{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.58rem}.brand .ver{display:none}.iconbtn{min-height:44px;flex:none;padding:7px;font-size:.58rem}
  .launcher{display:block;padding-top:42px}.launcher h1{font-size:clamp(3.3rem,17vw,5.4rem)}.launcher__intro>p:last-child{margin:18px 0 34px}.search input{height:60px}.chips{margin:22px -18px 0;padding:0 18px}.chip,.random{min-height:44px}.launcher__actions{justify-content:space-between}
  .section-head{padding-top:26px}.game{grid-template-columns:40px minmax(0,1fr) 44px;min-height:92px}.game__main{padding-left:6px}.game__title{font-size:1.1rem}.game__fav{font-size:.55rem}.families__grid{grid-template-columns:1fr}
  .reader{display:block;width:100%;max-width:100vw}.bar{width:100%;padding:calc(14px + env(safe-area-inset-top)) 16px 12px}.reader-hero{position:relative;height:62dvh;min-height:430px;padding:90px 18px 24px}.reader-hero h1{font-size:clamp(2.8rem,14vw,4.8rem)}.reader-body-inner{width:100%;padding:48px 18px 80px}.reader-summary__text{font-size:1.35rem}.reader-hero__meta span{font-size:.65rem}.raction,.rbtn{min-width:0}.rbtn{padding:8px}.rules{overflow-wrap:anywhere}.rules table{display:block;overflow-x:auto}.related__grid{grid-template-columns:1fr}
  body.searching .launcher__intro{display:none}body.searching .launcher{padding-top:24px}
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
    $heroPhoto = $g['image'] ? '?img=' . urlencode($g['image']) : hero_photo($g);
    // Navigation clavier : prev/next.
    $_all = Vault::db()->query("SELECT slug FROM games ORDER BY sort")->fetchAll(PDO::FETCH_COLUMN);
    $_i = array_search($g['slug'], $_all);
    $prevSlug = $_all[($_i - 1 + count($_all)) % count($_all)];
    $nextSlug = $_all[($_i + 1) % count($_all)];
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
  <div class="reader" data-prev="<?= e($prevSlug) ?>" data-next="<?= e($nextSlug) ?>">
    <div class="bar">
      <a class="bar__back" href="<?= e(qs_home()) ?>">Retour</a>
      <span><?= e($g['type'] ?: 'Jeu de cartes') ?> / v<?= VERSION ?></span>
    </div>
    <section class="reader-hero" style="--hero-c:<?= e($g['color'] ?: '#ca9ee6') ?>" aria-labelledby="gameTitle">
      <img class="reader-hero__image" src="<?= e($heroPhoto) ?>" alt="Photographie de cartes pour <?= e($g['title']) ?>" referrerpolicy="no-referrer">
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
  $games = $isTop ? Vault::games(['top' => true, 'limit' => 100]) : Vault::games([]);
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
  // Familles depuis game_links.
  $families = [];
  $_fl = Vault::db()->query("SELECT note, slug AS s FROM game_links UNION SELECT note, related AS s FROM game_links ORDER BY note, s");
  foreach ($_fl->fetchAll(PDO::FETCH_ASSOC) as $r) $families[$r['note']][] = $r['s'];
  foreach ($families as $note => &$slugs) { $slugs = array_unique($slugs); sort($slugs); }
  unset($slugs);
?>
  <header class="topfix">
    <div class="topfix__inner">
      <div class="brandrow">
        <a class="brand" href="<?= e(qs_home()) ?>" aria-label="Retour à l'accueil"><b>PK</b> / GUIDE DE TABLE <span class="ver">v<?= VERSION ?></span></a>
        <span class="spacer"></span>
        <a class="iconbtn" href="<?= e(qs_home(['top' => 1])) ?>">Top</a>
        <button class="iconbtn" id="favOpen">Favoris <span class="dot" id="favDot" hidden>0</span></button>
      </div>
    </div>
  </header>

  <main>
    <section class="launcher" aria-labelledby="heroTitle">
      <div class="launcher__intro">
        <p class="eyebrow"><?= $TOTAL ?> règles vérifiées / accès immédiat</p>
        <h1 id="heroTitle">On joue<br>à quoi ?</h1>
        <p>Nom officiel, surnom local ou type de jeu : une frappe suffit.</p>
      </div>
      <?php if (!$isTop): ?>
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
      <?php else: ?>
      <p class="top-intro">Les règles les plus utiles selon les joueurs.</p>
      <?php endif; ?>
    </section>
    <div class="section-head"><p class="eyebrow"><?= $isTop ? 'Classement' : 'Répertoire' ?></p><p class="count-line" id="countLine" data-ver="v<?= VERSION ?>"><?= count($games) ?> jeux · v<?= VERSION ?></p></div>

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
    b.classList.toggle('on', favs.has(b.dataset.fav));
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
// builder for the home-count line was inlined above
