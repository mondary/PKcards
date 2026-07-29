<?php
/* PKcards v2 — 1 fichier + SQLite — mobile-first, HTMX */
error_reporting(E_ERROR);

$db_path = __DIR__ . '/data.sqlite';
$db = new PDO('sqlite:' . $db_path);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ===== 1. INIT DB depuis rules_clm ===== */
function init_db($db) {
    $db->exec("CREATE TABLE IF NOT EXISTS games (
        id INTEGER PRIMARY KEY,
        slug TEXT UNIQUE, title TEXT, players TEXT, duration TEXT,
        tagline TEXT, content TEXT
    )");
    if ($db->query("SELECT COUNT(*) FROM games")->fetchColumn() > 0) return;

    $dir = __DIR__ . '/../../rules_clm';
    if (!is_dir($dir)) return;

    foreach (glob("$dir/*.md") as $f) {
        $md = file_get_contents($f);
        $slug = basename($f, '.md');
        preg_match('/^#\s+(.+)$/m', $md, $m);
        $title = trim($m[1] ?? ucfirst($slug));
        preg_match('/(\d+\s*(?:à|-|to)\s*\d+\s*joueurs?)/i', $md, $pm);
        $players = $pm[1] ?? '';
        preg_match('/(\d+\s*(?:à|-|to)\s*\d+\s*min)/i', $md, $dm);
        $duration = $dm[1] ?? '';
        $lines = explode("\n", $md);
        $tagline = '';
        for ($i = 1; $i < count($lines); $i++) {
            $l = trim($lines[$i]);
            if ($l && !str_starts_with($l, '#') && !str_starts_with($l, '---') && !str_starts_with($l, '|')) { $tagline = $l; break; }
        }
        $tagline = preg_replace('/\*\*/', '', $tagline);
        $stmt = $db->prepare("INSERT OR REPLACE INTO games (slug,title,players,duration,tagline,content) VALUES (?,?,?,?,?,?)");
        $stmt->execute([$slug,$title,$players,$duration,$tagline,$md]);
    }
}
init_db($db);

/* ===== 2. MARKDOWN → HTML (minimal) ===== */
function md2html($md) {
    $md = preg_replace('/^---+$/m', "\n<hr>\n", $md);
    $md = preg_replace('/^###\s+(.+)$/m', '<h3>$1</h3>', $md);
    $md = preg_replace('/^##\s+(.+)$/m', '<h2>$1</h2>', $md);
    $md = preg_replace('/^#\s+(.+)$/m', '<h1>$1</h1>', $md);
    $md = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $md);
    $md = preg_replace('/✅/', '<span class="ok">✅</span>', $md);
    $md = preg_replace('/❌/', '<span class="no">❌</span>', $md);
    // tables
    $lines = explode("\n", $md);
    $html = []; $inTable = false; $inUl = false; $inOl = false;
    foreach ($lines as $line) {
        $t = trim($line);
        if (preg_match('/^\|(.+)\|$/', $t, $m)) {
            if (!$inTable) { $html[] = '<table>'; $inTable = true; }
            $cells = array_map('trim', explode('|', trim($m[1], '|')));
            if (preg_match('/^[-:]+$/', implode('', $cells))) continue;
            $html[] = '<tr>' . implode('', array_map(fn($c) => "<td>$c</td>", $cells)) . '</tr>';
            continue;
        }
        if ($inTable) { $html[] = '</table>'; $inTable = false; }
        if (preg_match('/^-\s+(.+)/', $t, $m)) {
            if (!$inUl) { $html[] = '<ul>'; $inUl = true; }
            $html[] = "<li>$m[1]</li>"; continue;
        }
        if ($inUl) { $html[] = '</ul>'; $inUl = false; }
        if (preg_match('/^\d+\.\s+(.+)/', $t, $m)) {
            if (!$inOl) { $html[] = '<ol>'; $inOl = true; }
            $html[] = "<li>$m[1]</li>"; continue;
        }
        if ($inOl) { $html[] = '</ol>'; $inOl = false; }
        if (in_array($t, ['','<hr>'])) { $html[] = $t ? '<hr>' : ''; continue; }
        if (preg_match('/^<h[123]/', $t)) { $html[] = $t; continue; }
        $html[] = "<p>$t</p>";
    }
    if ($inTable) $html[] = '</table>';
    if ($inUl) $html[] = '</ul>';
    if ($inOl) $html[] = '</ol>';
    return implode("\n", $html);
}

/* ===== 3. ROUTING HTMX ===== */
$hx = $_SERVER['HTTP_HX_REQUEST'] ?? false;
$slug = $_GET['game'] ?? '';

if ($hx && $slug) {
    $g = $db->prepare("SELECT * FROM games WHERE slug = ?");
    $g->execute([$slug]);
    $g = $g->fetch(PDO::FETCH_ASSOC);
    if (!$g) { http_response_code(404); exit; }
    echo '<div class="detail" hx-target="this" hx-swap="outerHTML">';
    echo '<button class="back-btn" hx-get="?" hx-target="#app" hx-push-url="true">‹ Retour</button>';
    echo '<h1 class="detail-title">' . htmlspecialchars($g['title']) . '</h1>';
    if ($g['players']) echo '<div class="detail-meta">' . htmlspecialchars($g['players']);
    if ($g['duration']) echo ' · ' . htmlspecialchars($g['duration']);
    echo '</div>';
    echo '<div class="rules-body">' . md2html($g['content']) . '</div>';
    echo '</div>';
    exit;
}

/* ===== 4. PAGE PRINCIPALE ===== */
$games = $db->query("SELECT * FROM games ORDER BY title")->fetchAll(PDO::FETCH_ASSOC);
$base = '/site/v2';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
<meta name="theme-color" content="#0a0a14">
<title>PKcards — Règles de jeux</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'><rect width='64' height='64' rx='14' fill='%230a0a14'/><text x='32' y='44' font-size='36' text-anchor='middle'>🃏</text></svg>">
<script src="https://unpkg.com/htmx.org@1.9.12"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:#0a0a14;background-image:radial-gradient(ellipse at 50% -10%,rgba(232,196,106,.08) 0%,transparent 55%);min-height:100dvh;color:#e0e0e0;padding-top:env(safe-area-inset-top)}
:root{--gold:#e8c46a;--red:#e74c3c;--green:#2ecc71;--card:#141428;--border:rgba(255,255,255,.06)}

/* Header */
.header{position:sticky;top:0;padding:14px 16px 10px;background:linear-gradient(180deg,rgba(10,10,20,.96),rgba(10,10,20,.6) 90%,transparent);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);z-index:10;text-align:center}
.header h1{font-family:Georgia,serif;font-size:1.3rem;font-weight:400;letter-spacing:5px;text-transform:uppercase;color:var(--gold)}
.header p{font-size:.7rem;color:#666;margin-top:3px}

/* App container */
#app{max-width:600px;margin:0 auto;padding:12px 14px 60px}

/* Game list */
.glist{display:flex;flex-direction:column;gap:10px}
.gcard{background:var(--card);border:1px solid var(--border);border-radius:14px;padding:16px;cursor:pointer;transition:transform .12s,border-color .15s;text-decoration:none;color:inherit;display:block}
.gcard:hover{border-color:rgba(232,196,106,.25)}
.gcard:active{transform:scale(.98)}
.gcard-title{font-size:1.1rem;font-weight:700;color:#fff;margin-bottom:4px}
.gcard-tagline{font-size:.8rem;color:#888;line-height:1.4}
.gcard-meta{display:flex;gap:10px;margin-top:8px;font-size:.7rem}
.gcard-meta span{background:rgba(255,255,255,.05);padding:3px 8px;border-radius:8px;color:var(--gold)}

/* Detail view */
.detail{animation:slideIn .2s ease}
@keyframes slideIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
.back-btn{background:none;border:none;color:var(--gold);font-size:.9rem;cursor:pointer;padding:8px 0;margin-bottom:10px;display:flex;align-items:center;gap:4px}
.back-btn:active{opacity:.6}
.detail-title{font-family:Georgia,serif;font-size:1.6rem;color:#fff;margin-bottom:6px;line-height:1.2}
.detail-meta{font-size:.8rem;color:var(--gold);margin-bottom:16px}

/* Rules content */
.rules-body{font-size:.9rem;line-height:1.7;color:#ccc}
.rules-body h1{font-size:1.4rem;color:var(--gold);margin:20px 0 8px;font-family:Georgia,serif}
.rules-body h2{font-size:1.15rem;color:#fff;margin:24px 0 8px;padding-bottom:4px;border-bottom:1px solid var(--border)}
.rules-body h3{font-size:1rem;color:var(--gold);margin:16px 0 6px}
.rules-body p{margin:6px 0}
.rules-body strong{color:#fff}
.rules-body ul,.rules-body ol{margin:6px 0 6px 20px}
.rules-body li{margin:3px 0}
.rules-body hr{border:none;border-top:1px solid var(--border);margin:16px 0}
.rules-body table{width:100%;border-collapse:collapse;margin:10px 0;font-size:.8rem}
.rules-body td{padding:6px 10px;border:1px solid var(--border)}
.rules-body td:first-child{color:var(--gold);font-weight:600}
.rules-body .ok{color:var(--green)}
.rules-body .no{color:var(--red)}

/* Htmx loading */
.htmx-request .gcard{opacity:.5}
</style>
</head>
<body>

<div class="header">
  <h1>🃏 PKcards</h1>
  <p>Règles de jeux</p>
</div>

<div id="app">
<?php if ($slug && !$hx): ?>
  <?php
  $g = $db->prepare("SELECT * FROM games WHERE slug = ?");
  $g->execute([$slug]);
  $g = $g->fetch(PDO::FETCH_ASSOC);
  if ($g):
  ?>
  <div class="detail" hx-target="this" hx-swap="outerHTML">
    <button class="back-btn" hx-get="<?= $base ?>/" hx-target="#app" hx-push-url="<?= $base ?>/">‹ Retour</button>
    <h1 class="detail-title"><?= htmlspecialchars($g['title']) ?></h1>
    <?php if ($g['players']): ?><div class="detail-meta"><?= htmlspecialchars($g['players']) ?><?php if ($g['duration']) echo ' · ' . htmlspecialchars($g['duration']); ?></div><?php endif; ?>
    <div class="rules-body"><?= md2html($g['content']) ?></div>
  </div>
  <?php endif; ?>
<?php else: ?>
  <div class="glist">
    <?php foreach ($games as $g): ?>
    <a class="gcard" hx-get="<?= $base ?>/?game=<?= $g['slug'] ?>" hx-target="#app" hx-push-url="true">
      <div class="gcard-title"><?= htmlspecialchars($g['title']) ?></div>
      <div class="gcard-tagline"><?= htmlspecialchars($g['tagline'] ?: 'Règles complètes') ?></div>
      <div class="gcard-meta">
        <?php if ($g['players']): ?><span>👥 <?= htmlspecialchars($g['players']) ?></span><?php endif; ?>
        <?php if ($g['duration']): ?><span>⏱ <?= htmlspecialchars($g['duration']) ?></span><?php endif; ?>
      </div>
    </a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
</div>

</body>
</html>
