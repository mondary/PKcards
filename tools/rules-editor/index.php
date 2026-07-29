<?php
/* PKcards Rules Editor — comparer, fusionner, créer la règle définitive */
error_reporting(E_ERROR);
$db_path = __DIR__ . '/data.sqlite';
$db = new PDO('sqlite:' . $db_path);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE IF NOT EXISTS masters (slug TEXT PRIMARY KEY, content TEXT, updated TEXT)');

$action = $_GET['action'] ?? '';
$slug = $_GET['game'] ?? '';
$hx = $_SERVER['HTTP_HX_REQUEST'] ?? false;

/* === SAVE === */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $s = $_POST['slug'] ?? ''; $c = $_POST['content'] ?? '';
    $db->prepare('INSERT OR REPLACE INTO masters (slug,content,updated) VALUES (?,?,datetime("now"))')->execute([$s,$c]);
    // Also write to rules_clm/ if it exists
    $clm = __DIR__ . '/../../rules/rules_clm/' . $s . '.md';
    @file_put_contents($clm, $c);
    echo json_encode(['ok' => true, 'path' => $clm]);
    exit;
}

/* === GAME DETAIL (HTMX) === */
if ($hx && $slug) {
    $versions = $db->prepare('SELECT * FROM versions WHERE slug = ? ORDER BY wordcount DESC');
    $versions->execute([$slug]);
    $versions = $versions->fetchAll(PDO::FETCH_ASSOC);
    $master = $db->prepare('SELECT * FROM masters WHERE slug = ?');
    $master->execute([$slug]);
    $master = $master->fetch(PDO::FETCH_ASSOC);
    $editorContent = $master ? $master['content'] : ($versions[0]['content'] ?? '');
    ?>
    <div class="editor-view" data-slug="<?= htmlspecialchars($slug) ?>">
      <script id="versions-data" type="application/json"><?php
        $vdata = [];
        foreach ($versions as $v) {
          $vdata[] = ['content' => $v['content'], 'sections' => json_decode($v['sections_json'], true)];
        }
        echo json_encode($vdata, JSON_UNESCAPED_UNICODE);
      ?></script>
      <div class="editor-header">
        <button class="back-btn" onclick="showList()">‹ Retour</button>
        <h2><?= htmlspecialchars($versions[0]['title'] ?? $slug) ?></h2>
        <span class="vcount"><?= count($versions) ?> version<?= count($versions)>1?'s':'' ?></span>
        <button class="save-btn" onclick="saveMaster()">💾 Sauver en CLM</button>
      </div>
      <div class="editor-layout">
        <div class="versions-panel" id="versions-panel">
          <?php foreach ($versions as $i => $v): ?>
          <div class="version-block" data-vid="<?= $i ?>">
            <div class="version-head" onclick="toggleVersion(<?= $i ?>)">
              <span class="source-tag src-<?= htmlspecialchars($v['source']) ?>"><?= htmlspecialchars($v['source']) ?></span>
              <span class="vtitle"><?= htmlspecialchars($v['title']) ?></span>
              <span class="wc"><?= $v['wordcount'] ?> mots</span>
              <span class="toggle">▸</span>
            </div>
            <div class="version-body" id="vbody-<?= $i ?>" style="display:none">
              <?php foreach (json_decode($v['sections_json'], true) as $si => $sec): ?>
              <div class="section-row">
                <button class="import-btn" onclick="importSection(<?= $i ?>,<?= $si ?>)">→ <?= htmlspecialchars(mb_strimwidth($sec['heading'],0,40,'…')) ?></button>
              </div>
              <?php endforeach; ?>
              <button class="import-full" onclick="importFull(<?= $i ?>)">→ Importer toute la version</button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="editor-panel">
          <textarea id="md-editor" spellcheck="false"><?= htmlspecialchars($editorContent) ?></textarea>
        </div>
        <div class="preview-panel" id="preview-panel"></div>
      </div>
    </div>
    <?php
    exit;
}

/* === DEFAULT: GAME LIST === */
$search = $_GET['q'] ?? '';
$multiOnly = isset($_GET['multi']);
$sql = "SELECT slug, MAX(title) as title, COUNT(*) as n, GROUP_CONCAT(DISTINCT source) as sources, MAX(wordcount) as maxwc
        FROM versions";
$where = []; $params = [];
if ($search) { $where[] = "slug LIKE ?"; $params[] = "%$search%"; }
if ($multiOnly) $where[] = "COUNT(*) > 1";
if ($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " GROUP BY slug ORDER BY n DESC, title";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$games = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Rules Editor — PKcards</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'><rect width='64' height='64' rx='14' fill='%230a0a14'/><text x='32' y='44' font-size='32' text-anchor='middle'>✏️</text></svg>">
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--gold:#e8c46a;--bg:#0a0a14;--panel:#12121f;--border:rgba(255,255,255,.06);--glass:rgba(255,255,255,.03)}
body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;background:var(--bg);color:#ccc;min-height:100dvh}

/* Top bar */
.topbar{position:sticky;top:0;z-index:50;display:flex;align-items:center;gap:16px;padding:10px 16px;background:rgba(10,10,20,.92);backdrop-filter:blur(8px);border-bottom:1px solid var(--border)}
.topbar h1{font-size:1rem;color:var(--gold);white-space:nowrap}
.topbar .search{flex:1;max-width:300px;padding:6px 12px;background:var(--glass);border:1px solid var(--border);border-radius:8px;color:#ccc;font-size:.85rem}
.topbar .search:focus{outline:none;border-color:rgba(232,196,106,.3)}
.topbar .count{font-size:.75rem;color:#555;white-space:nowrap}

/* Game list */
.game-list{max-width:700px;margin:0 auto;padding:12px 14px 60px}
.game-row{display:flex;align-items:center;gap:12px;padding:12px 14px;background:var(--glass);border:1px solid var(--border);border-radius:10px;margin-bottom:6px;cursor:pointer;transition:border-color .15s}
.game-row:hover{border-color:rgba(232,196,106,.2)}
.game-row .gname{flex:1;font-weight:600;color:#ddd;font-size:.9rem}
.game-row .gn{background:rgba(232,196,106,.1);color:var(--gold);padding:2px 8px;border-radius:8px;font-size:.7rem;font-weight:600}
.game-row .gsources{font-size:.7rem;color:#555}
.game-row.has-master::after{content:'✓';color:#2ecc71;font-size:.9rem}
.source-badge{display:inline-block;padding:1px 6px;border-radius:5px;font-size:.6rem;background:rgba(255,255,255,.05);color:#888;margin:0 2px}

/* Editor */
.editor-view{position:fixed;inset:0;z-index:100;background:var(--bg);display:flex;flex-direction:column}
.editor-header{display:flex;align-items:center;gap:12px;padding:10px 16px;border-bottom:1px solid var(--border);flex-shrink:0}
.editor-header h2{flex:1;font-size:1.1rem;color:#fff}
.editor-header .vcount{font-size:.75rem;color:#666}
.back-btn{padding:6px 12px;background:var(--glass);border:1px solid var(--border);border-radius:8px;color:var(--gold);cursor:pointer;font-size:.85rem}
.save-btn{padding:6px 14px;background:rgba(46,204,113,.15);border:1px solid rgba(46,204,113,.3);border-radius:8px;color:#2ecc71;cursor:pointer;font-size:.85rem;font-weight:600}
.save-btn:active{transform:scale(.95)}

.editor-layout{flex:1;display:grid;grid-template-columns:280px 1fr 1fr;overflow:hidden;min-height:0}

/* Versions panel */
.versions-panel{overflow-y:auto;border-right:1px solid var(--border);padding:8px}
.version-block{margin-bottom:4px;border:1px solid var(--border);border-radius:8px;overflow:hidden;background:var(--panel)}
.version-head{display:flex;align-items:center;gap:8px;padding:8px 10px;cursor:pointer;font-size:.8rem}
.version-head:hover{background:rgba(255,255,255,.02)}
.source-tag{padding:2px 8px;border-radius:6px;font-size:.65rem;font-weight:600;text-transform:uppercase;white-space:nowrap}
.src-clm{background:rgba(46,204,113,.15);color:#2ecc71}
.src-bycicle{background:rgba(231,76,60,.15);color:#e74c3c}
.src-edimag100{background:rgba(232,196,106,.15);color:var(--gold)}
.src-fetjain32{background:rgba(155,89,182,.15);color:#bb8fce}
.src-rules2{background:rgba(52,152,219,.15);color:#5dade2}
.src-rules3{background:rgba(230,126,34,.15);color:#f5b041}
.src-original{background:rgba(255,255,255,.05);color:#888}
.vtitle{flex:1;color:#aaa;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.wc{font-size:.65rem;color:#555;white-space:nowrap}
.toggle{color:#555;transition:transform .15s}
.version-block.open .toggle{transform:rotate(90deg)}
.version-body{padding:6px 10px;border-top:1px solid var(--border)}
.section-row{margin:2px 0}
.import-btn{display:block;width:100%;text-align:left;padding:5px 8px;background:none;border:1px solid transparent;border-radius:6px;color:#888;font-size:.75rem;cursor:pointer}
.import-btn:hover{background:rgba(232,196,106,.06);border-color:rgba(232,196,106,.15);color:#ccc}
.import-full{display:block;width:100%;margin-top:6px;padding:6px;background:rgba(232,196,106,.08);border:1px solid rgba(232,196,106,.15);border-radius:6px;color:var(--gold);font-size:.75rem;font-weight:600;cursor:pointer}

/* Editor panel */
.editor-panel{display:flex;flex-direction:column;overflow:hidden}
#md-editor{flex:1;width:100%;resize:none;padding:16px;background:var(--panel);border:none;border-right:1px solid var(--border);color:#ddd;font-family:'SF Mono',Menlo,monospace;font-size:.85rem;line-height:1.6;outline:none}

/* Preview panel */
.preview-panel{overflow-y:auto;padding:16px}
.preview-panel h1{font-size:1.4rem;color:var(--gold);margin:16px 0 8px}
.preview-panel h2{font-size:1.1rem;color:#fff;margin:20px 0 8px;padding-bottom:4px;border-bottom:1px solid var(--border)}
.preview-panel h3{font-size:1rem;color:var(--gold);margin:14px 0 6px}
.preview-panel p{margin:6px 0;font-size:.85rem;line-height:1.6;color:#bbb}
.preview-panel strong{color:#fff}
.preview-panel ul,.preview-panel ol{margin:6px 0 6px 20px;font-size:.85rem;line-height:1.6}
.preview-panel li{margin:3px 0;color:#bbb}
.preview-panel hr{border:none;border-top:1px solid var(--border);margin:14px 0}
.preview-panel table{width:100%;border-collapse:collapse;margin:10px 0;font-size:.8rem}
.preview-panel td{padding:5px 8px;border:1px solid var(--border);color:#999}
.preview-panel td:first-child{color:var(--gold)}

.filter-bar{display:flex;align-items:center;gap:10px;padding:8px 14px;max-width:700px;margin:0 auto}
.filter-bar label{font-size:.75rem;color:#666;cursor:pointer;display:flex;align-items:center;gap:4px}

@media(max-width:900px){
  .editor-layout{grid-template-columns:1fr;grid-template-rows:auto 1fr}
  .versions-panel{max-height:200px;border-right:none;border-bottom:1px solid var(--border)}
  .preview-panel{display:none}
}
</style>
</head>
<body>

<div class="topbar">
  <h1>✏️ Rules Editor</h1>
  <input class="search" type="text" placeholder="🔍 Rechercher un jeu..." value="<?= htmlspecialchars($search) ?>"
    oninput="clearTimeout(window._st);window._st=setTimeout(()=>loadGames(this.value),300)">
  <span class="count"><?= count($games) ?> jeux</span>
</div>

<div class="filter-bar">
  <label><input type="checkbox" <?= $multiOnly?'checked':'' ?> onchange="loadGames(document.querySelector('.search').value, this.checked)"> Versions multiples uniquement</label>
</div>

<div id="app">
  <div class="game-list" id="game-list">
    <?php foreach ($games as $g):
      $hasMaster = $db->query("SELECT 1 FROM masters WHERE slug='{$g['slug']}'")->fetchColumn();
    ?>
    <div class="game-row <?= $hasMaster?'has-master':'' ?>" onclick="openGame('<?= htmlspecialchars($g['slug']) ?>')">
      <span class="gname"><?= htmlspecialchars($g['title']) ?></span>
      <?php foreach (explode(',', $g['sources']) as $src): ?>
      <span class="source-badge src-<?= htmlspecialchars($src) ?>"><?= htmlspecialchars($src) ?></span>
      <?php endforeach; ?>
      <span class="gn"><?= $g['n'] ?></span>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<script>
const versionsData = {};

function loadGames(q, multi) {
  const url = '?q=' + encodeURIComponent(q || '') + (multi ? '&multi=1' : '');
  window.location.href = url;
}

function openGame(slug) {
  fetch('?game=' + slug, { headers: { 'HX-Request': 'true' } })
    .then(r => r.text())
    .then(html => {
      document.getElementById('app').innerHTML = html;
      // Parse embedded version data
      const dataEl = document.getElementById('versions-data');
      if (dataEl) {
        try { versionsData[slug] = JSON.parse(dataEl.textContent); } catch(e) {}
      }
      updatePreview();
    });
}

function showList() {
  window.location.reload();
}

function toggleVersion(id) {
  const block = document.querySelector(`[data-vid="${id}"]`);
  const body = document.getElementById('vbody-' + id);
  if (body.style.display === 'none') {
    body.style.display = 'block';
    block.classList.add('open');
  } else {
    body.style.display = 'none';
    block.classList.remove('open');
  }
}

function importSection(vid, sid) {
  const slug = document.querySelector('.editor-view').dataset.slug;
  const data = versionsData[slug];
  if (!data || !data[vid] || !data[vid].sections[sid]) return;
  const editor = document.getElementById('md-editor');
  const section = data[vid].sections[sid].content;
  const pos = editor.selectionStart;
  const before = editor.value.substring(0, pos);
  const after = editor.value.substring(editor.selectionEnd);
  const insert = (before && !before.endsWith('\n\n') ? '\n\n' : '') + section + '\n';
  editor.value = before + insert + after;
  editor.focus();
  editor.setSelectionRange(pos + insert.length, pos + insert.length);
  updatePreview();
}

function importFull(vid) {
  const slug = document.querySelector('.editor-view').dataset.slug;
  const data = versionsData[slug];
  if (!data || !data[vid]) return;
  if (!confirm('Remplacer tout le contenu par cette version ?')) return;
  document.getElementById('md-editor').value = data[vid].content;
  updatePreview();
}

function updatePreview() {
  const md = document.getElementById('md-editor');
  if (!md) return;
  const preview = document.getElementById('preview-panel');
  preview.innerHTML = marked.parse(md.value);
}

function saveMaster() {
  const slug = document.querySelector('.editor-view').dataset.slug;
  const content = document.getElementById('md-editor').value;
  fetch('?', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=save&slug=' + encodeURIComponent(slug) + '&content=' + encodeURIComponent(content)
  }).then(r => r.json()).then(r => {
    alert(r.ok ? '✅ Sauvé dans rules_clm/' + slug + '.md' : '❌ Erreur');
  });
}

// Live preview on edit
document.addEventListener('input', e => {
  if (e.target.id === 'md-editor') updatePreview();
});
</script>
</body>
</html>
