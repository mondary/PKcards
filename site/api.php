<?php
/* ================================================================
   PKcards — API (votes + favoris) sur SQLite
   Endpoints (routés par ?action=) :
     POST vote         game            -> { ok, count }        (429 si throttlé)
     GET  top          limit=100       -> [{ game_id, count }]
     POST fav_add      email, game     -> { ok, favorites:[..] }
     POST fav_remove   email, game     -> { ok, favorites:[..] }
     GET  favorites    email           -> [game_id, ..]
   ================================================================ */

declare(strict_types=1);

$DB_PATH = __DIR__ . '/data/pk.sqlite';

// Throttle votes
const VOTE_MIN_INTERVAL = 5;   // secondes entre 2 votes même IP+jeu
const VOTE_MAX_PER_MIN  = 40;  // votes max par IP par minute

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function fail(int $code, string $msg): void {
  http_response_code($code);
  echo json_encode(['ok' => false, 'error' => $msg]);
  exit;
}

function ok(array $data = []): void {
  echo json_encode(['ok' => true] + $data);
  exit;
}

function valid_game(?string $g): bool {
  return is_string($g) && preg_match('/^[a-z0-9-]{1,64}$/', $g) === 1;
}

function clean_email(?string $e): ?string {
  if (!is_string($e)) return null;
  $e = strtolower(trim($e));
  if (strlen($e) > 190) return null;
  return filter_var($e, FILTER_VALIDATE_EMAIL) ? $e : null;
}

// --- DB ---
$dir = dirname($DB_PATH);
if (!is_dir($dir)) @mkdir($dir, 0775, true);

try {
  $db = new PDO('sqlite:' . $DB_PATH, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  $db->exec('PRAGMA journal_mode=WAL;');
  $db->exec('CREATE TABLE IF NOT EXISTS votes (
      game_id TEXT PRIMARY KEY,
      count   INTEGER NOT NULL DEFAULT 0
    )');
  $db->exec('CREATE TABLE IF NOT EXISTS vote_log (
      id         INTEGER PRIMARY KEY AUTOINCREMENT,
      game_id    TEXT NOT NULL,
      ip         TEXT NOT NULL,
      created_at INTEGER NOT NULL
    )');
  $db->exec('CREATE INDEX IF NOT EXISTS idx_votelog_ip ON vote_log(ip, created_at)');
  $db->exec('CREATE TABLE IF NOT EXISTS favorites (
      email      TEXT NOT NULL,
      game_id    TEXT NOT NULL,
      created_at INTEGER NOT NULL,
      PRIMARY KEY (email, game_id)
    )');
} catch (Throwable $e) {
  fail(500, 'db_init_failed');
}

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$ip     = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$now    = time();

// Corps : accepte JSON ou form-urlencoded
function body_param(string $key): ?string {
  static $json = null;
  if ($json === null) {
    $raw = file_get_contents('php://input') ?: '';
    $decoded = json_decode($raw, true);
    $json = is_array($decoded) ? $decoded : [];
  }
  if (array_key_exists($key, $json)) return is_string($json[$key]) ? $json[$key] : null;
  if (isset($_POST[$key]) && is_string($_POST[$key])) return $_POST[$key];
  return null;
}

switch ($action) {

  case 'vote': {
    if ($method !== 'POST') fail(405, 'method_not_allowed');
    $game = body_param('game');
    if (!valid_game($game)) fail(400, 'invalid_game');

    // Throttle : dernier vote même IP+jeu
    $st = $db->prepare('SELECT MAX(created_at) FROM vote_log WHERE ip = ? AND game_id = ?');
    $st->execute([$ip, $game]);
    $last = (int)($st->fetchColumn() ?: 0);
    if ($last && ($now - $last) < VOTE_MIN_INTERVAL) fail(429, 'too_soon');

    // Throttle : cap par IP par minute
    $st = $db->prepare('SELECT COUNT(*) FROM vote_log WHERE ip = ? AND created_at > ?');
    $st->execute([$ip, $now - 60]);
    if ((int)$st->fetchColumn() >= VOTE_MAX_PER_MIN) fail(429, 'rate_limited');

    $db->beginTransaction();
    $st = $db->prepare('INSERT INTO votes (game_id, count) VALUES (?, 1)
                        ON CONFLICT(game_id) DO UPDATE SET count = count + 1');
    $st->execute([$game]);
    $st = $db->prepare('INSERT INTO vote_log (game_id, ip, created_at) VALUES (?, ?, ?)');
    $st->execute([$game, $ip, $now]);
    $db->commit();

    $st = $db->prepare('SELECT count FROM votes WHERE game_id = ?');
    $st->execute([$game]);
    ok(['count' => (int)$st->fetchColumn()]);
  }

  case 'top': {
    $limit = (int)($_GET['limit'] ?? 100);
    if ($limit < 1)   $limit = 1;
    if ($limit > 500) $limit = 500;
    $st = $db->prepare('SELECT game_id, count FROM votes WHERE count > 0
                        ORDER BY count DESC, game_id ASC LIMIT ?');
    $st->bindValue(1, $limit, PDO::PARAM_INT);
    $st->execute();
    $rows = array_map(fn($r) => ['game_id' => $r['game_id'], 'count' => (int)$r['count']], $st->fetchAll());
    echo json_encode($rows);
    exit;
  }

  case 'fav_add':
  case 'fav_remove': {
    if ($method !== 'POST') fail(405, 'method_not_allowed');
    $email = clean_email(body_param('email'));
    $game  = body_param('game');
    if (!$email) fail(400, 'invalid_email');
    if (!valid_game($game)) fail(400, 'invalid_game');

    if ($action === 'fav_add') {
      $st = $db->prepare('INSERT OR IGNORE INTO favorites (email, game_id, created_at) VALUES (?, ?, ?)');
      $st->execute([$email, $game, $now]);
    } else {
      $st = $db->prepare('DELETE FROM favorites WHERE email = ? AND game_id = ?');
      $st->execute([$email, $game]);
    }

    $st = $db->prepare('SELECT game_id FROM favorites WHERE email = ? ORDER BY created_at DESC');
    $st->execute([$email]);
    ok(['favorites' => array_column($st->fetchAll(), 'game_id')]);
  }

  case 'favorites': {
    $email = clean_email($_GET['email'] ?? null);
    if (!$email) fail(400, 'invalid_email');
    $st = $db->prepare('SELECT game_id FROM favorites WHERE email = ? ORDER BY created_at DESC');
    $st->execute([$email]);
    echo json_encode(array_column($st->fetchAll(), 'game_id'));
    exit;
  }

  default:
    fail(404, 'unknown_action');
}
