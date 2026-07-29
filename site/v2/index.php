<?php
/* PKcards v2 — Bibliothèque de règles — 1 PHP + SQLite + HTMX */
error_reporting(E_ERROR);
$db = new PDO('sqlite:' . __DIR__ . '/data.sqlite');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* Import auto si vide */
if ($db->query('SELECT COUNT(*) FROM games')->fetchColumn() == 0) {
    $dir = __DIR__ . '/../../rules/rules_clm';
    if (is_dir($dir)) {
        $db->exec('CREATE TABLE IF NOT EXISTS games (id INTEGER PRIMARY KEY, slug TEXT UNIQUE, title TEXT, players TEXT, duration TEXT, tagline TEXT, type TEXT, content TEXT)');
        foreach (glob("$dir/*.md") as $f) {
            $md = file_get_contents($f); $slug = basename($f, '.md');
            preg_match('/^#\s+(.+)$/m', $md, $m); $title = trim($m[1] ?? ucfirst($slug));
            preg_match('/\*\*(.+?)\*\*/', $md, $meta); $ml = $meta[1] ?? '';
            preg_match('/(\d+\s*(?:à|-)\s*\d+\s*joueurs?)/i', $ml, $pm); $players = $pm[1] ?? '';
            preg_match('/(\d+\s*(?:à|-)\s*\d+\s*min)/i', $ml, $dm); $duration = $dm[1] ?? '';
            $lines = explode("\n", $md); $tagline = '';
            for ($i = 1; $i < count($lines); $i++) { $l = trim($lines[$i]);
                if ($l && $l[0] !== '#' && !str_starts_with($l,'---') && !str_starts_with($l,'|') && !str_starts_with($l,'**')) { $tagline = $l; break; } }
            $type=''; if(preg_match('/adresse/i',$ml))$type='Adresse'; elseif(preg_match('/coop/i',$ml))$type='Coopératif'; elseif(preg_match('/combinaisons|défausse|hasard|rapidité|plis|communication/i',$ml,$tm))$type=ucfirst($tm[0]);
            $st=$db->prepare('INSERT INTO games(slug,title,players,duration,tagline,type,content) VALUES(?,?,?,?,?,?,?)');
            $st->execute([$slug,$title,$players,$duration,$tagline,$type,$md]);
        }
    }
}

function md2html($md) {
    $md = preg_replace('/^---+$/m', "\n<hr>\n", $md);
    $md = preg_replace('/^###\s+(.+)$/m', '<h3>$1</h3>', $md);
    $md = preg_replace('/^##\s+(.+)$/m', '<h2>$1</h2>', $md);
    $md = preg_replace('/^#\s+(.+)$/m', '<h1>$1</h1>', $md);
    $md = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $md);
    $md = preg_replace('/✅/', '<span class="ok">✅</span>', $md);
    $md = preg_replace('/❌/', '<span class="no">❌</span>', $md);
    $lines = explode("\n", $md); $html = []; $inT=$inU=$inO=false;
    foreach ($lines as $line) {
        $t = trim($line);
        if (preg_match('/^\|(.+)\|$/', $t, $m)) {
            if (!$inT) { $html[]='<table>'; $inT=true; }
            $cells = array_map('trim', explode('|', trim($m[1], '|')));
            if (preg_match('/^[-:]+$/', implode('', $cells))) continue;
            $html[]='<tr>'.implode('',array_map(fn($c)=>"<td>$c</td>",$cells)).'</tr>'; continue;
        }
        if ($inT) { $html[]='</table>'; $inT=false; }
        if (preg_match('/^-\s+(.+)/',$t,$m)) { if(!$inU){$html[]='<ul>';$inU=true;} $html[]="<li>$m[1]</li>"; continue; }
        if ($inU) { $html[]='</ul>'; $inU=false; }
        if (preg_match('/^\d+\.\s+(.+)/',$t,$m)) { if(!$inO){$html[]='<ol>';$inO=true;} $html[]="<li>$m[1]</li>"; continue; }
        if ($inO) { $html[]='</ol>'; $inO=false; }
        if ($t==='' || $t==='<hr>') { $html[]= $t?'<hr>':''; continue; }
        if (preg_match('/^<h[123]/',$t)) { $html[]=$t; continue; }
        $html[]="<p>$t</p>";
    }
    if($inT)$html[]='</table>'; if($inU)$html[]='</ul>'; if($inO)$html[]='</ol>';
    return implode("\n",$html);
}

function gameIcon($slug,$title) {
    $icons = ['regicide'=>'♚','yaniv'=>'🃏','president'=>'👑','kems'=>'🤝','bataille-corse'=>'⚡','paquet-de-merde'=>'💩','pouilleux'=>'🤢'];
    return $icons[$slug] ?? '🂠';
}

$hx = $_SERVER['HTTP_HX_REQUEST'] ?? false;
$slug = $_GET['game'] ?? '';
$base = '/pk/site/v2';

/* HTMX fragment */
if ($hx && $slug) {
    $g = $db->prepare('SELECT * FROM games WHERE slug=?'); $g->execute([$slug]); $g=$g->fetch(PDO::FETCH_ASSOC);
    if (!$g) { http_response_code(404); exit; }
    echo '<div class="reader" hx-target="this" hx-swap="outerHTML">';
    echo '<div class="reader-bar">';
    echo '<button class="back" hx-get="'.$base.'/" hx-target="#app" hx-push-url="'.$base.'/">‹</button>';
    echo '<span class="reader-icon">'.gameIcon($g['slug'],$g['title']).'</span>';
    echo '</div>';
    echo '<div class="reader-content">';
    echo '<h1 class="reader-title">'.$g['title'].'</h1>';
    echo '<div class="reader-meta">';
    if($g['players'])echo '<span>👥 '.$g['players'].'</span>';
    if($g['duration'])echo '<span>⏱ '.$g['duration'].'</span>';
    if($g['type'])echo '<span>'.$g['type'].'</span>';
    echo '</div>';
    echo '<div class="rules">'.md2html($g['content']).'</div>';
    echo '</div></div>';
    exit;
}

$games = $db->query('SELECT * FROM games ORDER BY title')->fetchAll(PDO::FETCH_ASSOC);
$random = $games[array_rand($games)] ?? null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no,viewport-fit=cover">
<meta name="theme-color" content="#0a0a14">
<title>PKcards — Bibliothèque</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'><rect width='64' height='64' rx='14' fill='%230a0a14'/><text x='32' y='44' font-size='36' text-anchor='middle'>🂠</text></svg>">
<script src="https://unpkg.com/htmx.org@1.9.12"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;-webkit-user-select:none;user-select:none}
:root{--gold:#e8c46a;--bg:#0a0a14;--glass:rgba(255,255,255,.035);--border:rgba(255,255,255,.06)}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:var(--bg);background-image:radial-gradient(ellipse at 50% -5%,rgba(232,196,106,.07) 0%,transparent 55%);min-height:100dvh;color:#e0e0e0}

/* === HOME === */
.home{max-width:520px;margin:0 auto;padding:env(safe-area-inset-top) 0 env(safe-area-inset-bottom)}
.hero{text-align:center;padding:40px 20px 24px}
.hero h1{font-family:Georgia,serif;font-size:2rem;font-weight:400;color:var(--gold);letter-spacing:2px}
.hero p{font-size:.8rem;color:#555;margin-top:6px}
.pioche{display:inline-flex;align-items:center;gap:8px;margin-top:18px;padding:10px 20px;background:linear-gradient(135deg,rgba(232,196,106,.15),rgba(232,196,106,.05));border:1px solid rgba(232,196,106,.2);border-radius:24px;color:var(--gold);font-size:.85rem;font-weight:600;cursor:pointer;text-decoration:none;transition:transform .12s}
.pioche:active{transform:scale(.95)}
.pioche span{font-size:1.1rem}

.game-list{padding:8px 14px 60px;display:flex;flex-direction:column;gap:10px}
.game-tile{display:flex;align-items:center;gap:14px;padding:16px;background:var(--glass);border:1px solid var(--border);border-radius:16px;cursor:pointer;transition:transform .12s,border-color .15s;text-decoration:none;color:inherit;backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px)}
.game-tile:active{transform:scale(.98)}
.game-tile:hover{border-color:rgba(232,196,106,.2)}
.tile-icon{width:48px;height:48px;border-radius:12px;background:rgba(255,255,255,.04);display:flex;align-items:center;justify-content:center;font-size:1.6rem;flex-shrink:0}
.tile-body{flex:1;min-width:0}
.tile-title{font-size:1rem;font-weight:600;color:#fff;margin-bottom:2px}
.tile-tagline{font-size:.75rem;color:#777;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.tile-meta{display:flex;flex-direction:column;align-items:flex-end;gap:3px;flex-shrink:0}
.tile-badge{font-size:.65rem;padding:2px 8px;border-radius:8px;background:rgba(255,255,255,.04);color:var(--gold);white-space:nowrap}
.tile-badge.type{color:#888}

/* === READER === */
.reader{animation:fadeUp .25s ease;min-height:100dvh}
@keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}
.reader-bar{position:sticky;top:0;display:flex;align-items:center;gap:12px;padding:calc(12px + env(safe-area-inset-top)) 16px 12px;background:linear-gradient(180deg,rgba(10,10,20,.95),rgba(10,10,20,.7) 80%,transparent);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);z-index:10}
.back{width:36px;height:36px;border-radius:50%;border:none;background:rgba(255,255,255,.06);color:var(--gold);font-size:1.3rem;cursor:pointer}
.reader-icon{font-size:1.4rem}
.reader-content{max-width:600px;margin:0 auto;padding:0 20px 60px}
.reader-title{font-family:Georgia,serif;font-size:1.8rem;color:#fff;line-height:1.2;margin-bottom:10px}
.reader-meta{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:24px}
.reader-meta span{font-size:.75rem;padding:4px 10px;border-radius:10px;background:rgba(232,196,106,.1);color:var(--gold)}

.rules{font-size:.95rem;line-height:1.75;color:#bbb}
.rules h1{font-size:1.5rem;color:var(--gold);margin:28px 0 10px;font-family:Georgia,serif}
.rules h2{font-size:1.2rem;color:#fff;margin:28px 0 10px;padding-bottom:6px;border-bottom:1px solid var(--border)}
.rules h3{font-size:1.05rem;color:var(--gold);margin:18px 0 6px}
.rules p{margin:8px 0}
.rules strong{color:#fff}
.rules ul,.rules ol{margin:8px 0 8px 22px}
.rules li{margin:4px 0}
.rules hr{border:none;border-top:1px solid var(--border);margin:20px 0}
.rules table{width:100%;border-collapse:collapse;margin:12px 0;font-size:.85rem}
.rules td{padding:7px 10px;border:1px solid var(--border)}
.rules td:first-child{color:var(--gold);font-weight:600}
.rules .ok{color:#2ecc71}.rules .no{color:#e74c3c}

#app{min-height:100dvh}
</style>
</head>
<body>
<div id="app">

<?php if ($slug && !$hx):
  $g=$db->prepare('SELECT * FROM games WHERE slug=?'); $g->execute([$slug]); $g=$g->fetch(PDO::FETCH_ASSOC);
  if ($g): ?>
  <div class="reader" hx-target="this" hx-swap="outerHTML">
    <div class="reader-bar">
      <button class="back" hx-get="<?= $base ?>/" hx-target="#app" hx-push-url="<?= $base ?>/">‹</button>
      <span class="reader-icon"><?= gameIcon($g['slug'],$g['title']) ?></span>
    </div>
    <div class="reader-content">
      <h1 class="reader-title"><?= htmlspecialchars($g['title']) ?></h1>
      <div class="reader-meta">
        <?php if($g['players']): ?><span>👥 <?= htmlspecialchars($g['players']) ?></span><?php endif; ?>
        <?php if($g['duration']): ?><span>⏱ <?= htmlspecialchars($g['duration']) ?></span><?php endif; ?>
        <?php if($g['type']): ?><span><?= htmlspecialchars($g['type']) ?></span><?php endif; ?>
      </div>
      <div class="rules"><?= md2html($g['content']) ?></div>
    </div>
  </div>
  <?php endif; ?>
<?php else: ?>
  <div class="home">
    <div class="hero">
      <h1>PKcards</h1>
      <p>Votre bibliothèque de règles</p>
      <?php if ($random): ?>
      <a class="pioche" href="<?= $base ?>/?game=<?= $random['slug'] ?>" hx-get="<?= $base ?>/?game=<?= $random['slug'] ?>" hx-target="#app" hx-push-url="true">
        <span>🂠</span> Piocher un jeu
      </a>
      <?php endif; ?>
    </div>
    <div class="game-list">
      <?php foreach ($games as $g): ?>
      <a class="game-tile" href="<?= $base ?>/?game=<?= $g['slug'] ?>" hx-get="<?= $base ?>/?game=<?= $g['slug'] ?>" hx-target="#app" hx-push-url="true">
        <div class="tile-icon"><?= gameIcon($g['slug'],$g['title']) ?></div>
        <div class="tile-body">
          <div class="tile-title"><?= htmlspecialchars($g['title']) ?></div>
          <div class="tile-tagline"><?= htmlspecialchars(mb_strimwidth(preg_replace('/\*\*|\*/','',$g['tagline'] ?: 'Règles complètes'),0,60,'…')) ?></div>
        </div>
        <div class="tile-meta">
          <?php if($g['players']): ?><span class="tile-badge"><?= htmlspecialchars($g['players']) ?></span><?php endif; ?>
          <?php if($g['type']): ?><span class="tile-badge type"><?= htmlspecialchars($g['type']) ?></span><?php endif; ?>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

</div>
</body>
</html>
