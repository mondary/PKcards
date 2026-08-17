<?php
declare(strict_types=1);

/* PKcards v4 — une fiche canonique, plusieurs noms et plusieurs règles. */
final class Library {
  private static ?PDO $db = null;
  private const DB = __DIR__ . '/vault.sqlite';

  static function db(): PDO {
    if (self::$db) return self::$db;
    $db = new PDO('sqlite:' . self::DB, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA foreign_keys=ON');
    $db->exec('CREATE TABLE IF NOT EXISTS games(slug TEXT PRIMARY KEY,title TEXT NOT NULL,players TEXT,cards TEXT,category TEXT,color TEXT,image TEXT)');
    $db->exec('CREATE TABLE IF NOT EXISTS game_names(game_slug TEXT NOT NULL,name TEXT NOT NULL,language TEXT NOT NULL DEFAULT "fr",role TEXT NOT NULL DEFAULT "alias",version_id INTEGER, PRIMARY KEY(game_slug,name,language,role))');
    $db->exec('CREATE TABLE IF NOT EXISTS game_versions(id INTEGER PRIMARY KEY AUTOINCREMENT,game_slug TEXT,source TEXT NOT NULL,source_slug TEXT NOT NULL,language TEXT NOT NULL,title TEXT NOT NULL,markdown_path TEXT NOT NULL,status TEXT NOT NULL DEFAULT "review",variant_note TEXT, UNIQUE(source,source_slug))');
    $db->exec('CREATE INDEX IF NOT EXISTS idx_versions_game ON game_versions(game_slug,status)');
    $db->exec('CREATE TABLE IF NOT EXISTS game_version_links(version_id INTEGER NOT NULL,kind TEXT NOT NULL,language TEXT,label TEXT NOT NULL,url TEXT NOT NULL,PRIMARY KEY(version_id,url))');
    $db->exec('CREATE TABLE IF NOT EXISTS content(path TEXT PRIMARY KEY,body TEXT NOT NULL)');
    self::$db = $db;
    self::import();
    self::reconcileVersions();
    return $db;
  }

  private static function key(string $value): string {
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_strtolower($value)) ?: $value;
    return preg_replace('/[^a-z0-9]+/', '', $value);
  }

  private static function import(): void {
    $db = self::$db;
    if ($db->query("SELECT 1 FROM games LIMIT 1")->fetchColumn()) return;
    $v3 = realpath(__DIR__ . '/../v3/vault.sqlite');
    if (!$v3) throw new RuntimeException('La bibliothèque V3 est introuvable.');
    $db->exec("ATTACH DATABASE '" . str_replace("'", "''", $v3) . "' AS v3");
    $db->exec('INSERT INTO games(slug,title,players,cards,category,color,image) SELECT slug,title,players,cards,category,color,image FROM v3.games');
    $copyName = $db->prepare('INSERT OR IGNORE INTO game_names(game_slug,name,language,role) VALUES(?,?,?,?)');
    foreach ($db->query('SELECT slug,title FROM games') as $g) $copyName->execute([$g['slug'], $g['title'], 'fr', 'title']);
    foreach ($db->query('SELECT slug,name FROM v3.game_names') as $n) $copyName->execute([$n['slug'], $n['name'], 'fr', 'alias']);
    $db->exec('DETACH DATABASE v3');

    $roots = [
      'clm' => 'rules_clm', 'pagat' => 'rules_pagat', 'mistigri' => 'rules_mistigri',
      'original' => 'rules_original', 'bicycle' => 'rules_bycicle', 'docs' => 'rules_docs',
      'edimag' => 'rules_edimag100', 'fetjain' => 'rules_fetjain32', 'garraud' => 'rules_garraud',
    ];
    $addVersion = $db->prepare('INSERT OR IGNORE INTO game_versions(game_slug,source,source_slug,language,title,markdown_path,status,variant_note) VALUES(?,?,?,?,?,?,?,?)');
    $addName = $db->prepare('INSERT OR IGNORE INTO game_names(game_slug,name,language,role,version_id) VALUES(?,?,?,?,?)');
    foreach ($roots as $source => $folder) foreach (glob(__DIR__ . '/../../assets/rules/' . $folder . '/*.md') ?: [] as $file) {
      $sourceSlug = pathinfo($file, PATHINFO_FILENAME);
      if ($sourceSlug[0] === '_') continue;
      $md = file_get_contents($file) ?: '';
      if (!preg_match('/^#\s+(.+)$/m', $md, $m)) continue;
      $title = trim(preg_replace('/\s*\([^)]*\)/', '', preg_replace('/^\p{So}+\s*/u', '', $m[1])));
      $exists = $db->prepare('SELECT 1 FROM games WHERE slug=?'); $exists->execute([$sourceSlug]);
      $gameSlug = $exists->fetchColumn() ? $sourceSlug : null;
      $status = $gameSlug ? 'alternative' : 'review';
      $path = '/sources/' . $source . '/' . $sourceSlug . '.md';
      $addVersion->execute([$gameSlug, $source, $sourceSlug, 'fr', $title, $path, $status, $gameSlug ? null : 'Rapprochement à vérifier']);
      $id = (int)$db->query('SELECT id FROM game_versions WHERE source=' . $db->quote($source) . ' AND source_slug=' . $db->quote($sourceSlug))->fetchColumn();
      if ($gameSlug) {
        $addName->execute([$gameSlug, $title, 'fr', 'title-source', $id]);
        if (preg_match('/^\*\*Autres noms\s*:\*\*\s*(.+)$/miu', $md, $aliases)) foreach (preg_split('/[,\/]/u', $aliases[1]) as $name) {
          $name = trim($name); if ($name && $name !== '—') $addName->execute([$gameSlug, $name, 'und', 'alias', $id]);
        }
      }
      $put = $db->prepare('INSERT INTO content(path,body) VALUES(?,?) ON CONFLICT(path) DO UPDATE SET body=excluded.body');
      $put->execute([$path, $md]);
    }
    $priority = "CASE source WHEN 'clm' THEN 90 WHEN 'mistigri' THEN 80 WHEN 'original' THEN 70 WHEN 'pagat' THEN 60 ELSE 40 END";
    $db->exec("UPDATE game_versions SET status='primary' WHERE id IN (SELECT id FROM (SELECT id,ROW_NUMBER() OVER (PARTITION BY game_slug ORDER BY $priority DESC,id) AS r FROM game_versions WHERE game_slug IS NOT NULL) WHERE r=1)");
  }

  /** Lie uniquement les versions dont le titre ou un alias donne un seul jeu. */
  private static function reconcileVersions(): void {
    $db = self::$db;
    $index = [];
    foreach ($db->query('SELECT game_slug,name FROM game_names') as $name) $index[self::key($name['name'])][$name['game_slug']] = true;
    $skipFile = __DIR__ . '/../../assets/rules/rules_pagat/_skip.json';
    $pagatLabels = is_file($skipFile) ? (json_decode((string)file_get_contents($skipFile), true) ?: []) : [];
    $find = function(array $keys) use ($index): ?string {
      $candidates = [];
      foreach ($keys as $key) foreach ($index[self::key($key)] ?? [] as $slug => $_) $candidates[$slug] = true;
      return count($candidates) === 1 ? (string)array_key_first($candidates) : null;
    };
    $link = $db->prepare("UPDATE game_versions SET game_slug=?,status='alternative',variant_note=NULL WHERE id=?");
    $addName = $db->prepare('INSERT OR IGNORE INTO game_names(game_slug,name,language,role,version_id) VALUES(?,?,?,?,?)');
    foreach ($db->query("SELECT * FROM game_versions WHERE status='review'") as $version) {
      $keys = [$version['title'], $version['source_slug']];
      if ($version['source'] === 'pagat' && isset($pagatLabels[$version['source_slug']])) $keys[] = $pagatLabels[$version['source_slug']];
      $slug = $find($keys);
      if (!$slug) continue;
      $link->execute([$slug, $version['id']]);
      $addName->execute([$slug, $version['title'], $version['language'], 'title-source', $version['id']]);
      $md = self::content($version['markdown_path']);
      if (preg_match('/^\*\*Autres noms\s*:\*\*\s*(.+)$/miu', $md, $aliases)) foreach (preg_split('/[,\/]/u', $aliases[1]) as $name) {
        $name = trim($name); if ($name && $name !== '—') $addName->execute([$slug, $name, 'und', 'alias', $version['id']]);
      }
    }
    $priority = "CASE source WHEN 'clm' THEN 90 WHEN 'mistigri' THEN 80 WHEN 'original' THEN 70 WHEN 'pagat' THEN 60 ELSE 40 END";
    $db->exec("UPDATE game_versions SET status='alternative' WHERE game_slug IS NOT NULL");
    $db->exec("UPDATE game_versions SET status='primary' WHERE id IN (SELECT id FROM (SELECT id,ROW_NUMBER() OVER (PARTITION BY game_slug ORDER BY $priority DESC,id) AS r FROM game_versions WHERE game_slug IS NOT NULL) WHERE r=1)");
  }

  static function content(string $path): string { $s = self::db()->prepare('SELECT body FROM content WHERE path=?'); $s->execute([$path]); return (string)$s->fetchColumn(); }
}

function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
function html(string $md): string {
  $md = preg_replace('/^#\s+.*\n?/m', '', $md, 1);
  $md = e($md);
  $md = preg_replace('/^###\s+(.+)$/m', '<h3>$1</h3>', $md);
  $md = preg_replace('/^##\s+(.+)$/m', '<h2>$1</h2>', $md);
  $md = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $md);
  $md = preg_replace('/\[(.+?)\]\((https?:[^)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $md);
  return implode("\n", array_map(fn($p) => trim($p) === '' || str_starts_with(trim($p), '<h') ? $p : '<p>' . $p . '</p>', preg_split('/\n{2,}/', $md)));
}

if (isset($_GET['card'])) {
  $name = basename((string)$_GET['card']);
  $file = __DIR__ . '/../../assets/cards/' . $name;
  if (!is_file($file) || pathinfo($file, PATHINFO_EXTENSION) !== 'png') { http_response_code(404); exit; }
  header('Content-Type: image/png'); header('Cache-Control: public, max-age=86400'); readfile($file); exit;
}

$db = Library::db();
$slug = (string)($_GET['game'] ?? ''); $version = (int)($_GET['version'] ?? 0); $q = trim((string)($_GET['q'] ?? ''));
if ($slug) {
  $g = $db->prepare('SELECT * FROM games WHERE slug=?'); $g->execute([$slug]); $game = $g->fetch();
  if (!$game) { http_response_code(404); exit('Jeu introuvable'); }
  $games = [];
  $versions = $db->prepare('SELECT * FROM game_versions WHERE game_slug=? ORDER BY status="primary" DESC, source, title'); $versions->execute([$slug]); $versions = $versions->fetchAll();
  $current = null; foreach ($versions as $v) if ($v['id'] === $version || (!$version && $v['status'] === 'primary')) { $current = $v; break; } $current ??= $versions[0] ?? null;
  $names = $db->prepare('SELECT DISTINCT name,language,role FROM game_names WHERE game_slug=? ORDER BY role="title" DESC,name'); $names->execute([$slug]); $names = $names->fetchAll();
} else {
  $s = $db->prepare('SELECT g.*,COUNT(v.id) AS version_count FROM games g LEFT JOIN game_versions v ON v.game_slug=g.slug WHERE g.title LIKE ? OR EXISTS(SELECT 1 FROM game_names n WHERE n.game_slug=g.slug AND n.name LIKE ?) GROUP BY g.slug ORDER BY g.title LIMIT 150'); $s->execute(['%'.$q.'%','%'.$q.'%']); $games = $s->fetchAll();
  $deal = $db->query('SELECT g.*,COUNT(v.id) AS version_count FROM games g LEFT JOIN game_versions v ON v.game_slug=g.slug GROUP BY g.slug ORDER BY RANDOM() LIMIT 3')->fetchAll();
}
require __DIR__ . '/table.php';
exit;
?>
<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>PKcards V4</title>
<style>
@import url('https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Fraunces:opsz,wght@9..144,500;9..144,700&family=Instrument+Sans:wght@400;500;600;700&display=swap');
:root{--ink:#1d2520;--paper:#e9eee7;--card:#f9fbf7;--line:#b9c6bb;--green:#1e6c4b;--coral:#d75d43;--mono:'DM Mono',monospace;--body:'Instrument Sans',sans-serif;--display:'Fraunces',serif}*{box-sizing:border-box}body{margin:0;background:var(--paper);color:var(--ink);font-family:var(--body)}a{color:inherit}.wrap{max-width:1180px;margin:auto;padding:28px clamp(18px,4vw,56px) 80px}.mast{display:flex;justify-content:space-between;gap:20px;align-items:baseline;border-bottom:1px solid var(--ink);padding-bottom:18px}.mark{font:700 1rem var(--mono);letter-spacing:-.08em}.mark b{color:var(--coral)}.tag{font:.7rem var(--mono);text-transform:uppercase;color:var(--green)}h1{font:700 clamp(3rem,9vw,7rem)/.9 var(--display);letter-spacing:-.07em;max-width:760px;margin:72px 0 24px}.lede{max-width:620px;font-size:1.15rem;line-height:1.5}.search{display:flex;gap:10px;margin:45px 0 25px}.search input{width:min(560px,100%);border:1px solid var(--ink);background:var(--card);padding:15px;font:inherit}.search button{border:0;background:var(--green);color:#fff;padding:0 20px;font-weight:700}.grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(245px,1fr));gap:12px}.game{display:block;background:var(--card);border:1px solid var(--line);padding:20px;min-height:155px;text-decoration:none}.game:hover{border-color:var(--green);box-shadow:6px 6px 0 var(--green)}.game h2{font:700 1.6rem/1 var(--display);margin:28px 0 8px}.meta,.label{font:.68rem var(--mono);text-transform:uppercase;letter-spacing:.04em}.meta{color:var(--green)}.reader{max-width:860px}.back{font:.72rem var(--mono)}.names{display:flex;gap:7px;flex-wrap:wrap;margin:18px 0 34px}.names span,.version{border:1px solid var(--line);padding:7px 9px;font:.7rem var(--mono);background:var(--card)}.versions{display:flex;gap:7px;flex-wrap:wrap;margin:30px 0}.version{cursor:pointer;text-decoration:none}.version.on{background:var(--green);border-color:var(--green);color:#fff}.rule{background:var(--card);border-top:4px solid var(--coral);padding:clamp(22px,5vw,56px);font-size:1.08rem;line-height:1.7}.rule h2{font:700 2rem/1 var(--display);margin:35px 0 12px}.rule h3{font:700 1.35rem var(--display)}.rule a{color:var(--green)}.note{padding:14px;border-left:3px solid var(--coral);background:#f6dfd7}.empty{padding:50px 0;color:#526056}@media(max-width:600px){h1{margin-top:48px}.wrap{padding-top:18px}.search{display:block}.search button{height:48px;margin-top:8px}}
</style><body><main class="wrap">
<header class="mast"><a class="mark" href="?"><b>PK</b>cards / V4</a><span class="tag">bibliothèque de règles</span></header>
<?php if (!$slug): ?><h1>Un jeu. Toutes ses façons de se nommer et de se jouer.</h1><p class="lede">V4 ne mélange plus les doublons dans le catalogue : chaque fiche relie ses noms, ses variantes et ses textes de règles.</p><form class="search"><input name="q" value="<?=e($q)?>" placeholder="Rechercher Belote, Bela, Klaberjass…"><button>Rechercher</button></form><p class="label"><?=count($games)?> fiches affichées</p><section class="grid"><?php foreach($games as $g): ?><a class="game" href="?game=<?=e($g['slug'])?>"><span class="meta"><?=e($g['category'] ?: 'jeu')?> · <?=$g['version_count']?> version<?= $g['version_count'] > 1 ? 's' : '' ?></span><h2><?=e($g['title'])?></h2><span><?=e($g['players'] ?: 'Joueurs à préciser')?></span></a><?php endforeach; ?></section>
<?php else: ?><section class="reader"><p><a class="back" href="?">← Retour au catalogue</a></p><p class="tag"><?=e($game['category'] ?: 'jeu de cartes')?> · fiche canonique</p><h1><?=e($game['title'])?></h1><div class="names"><?php foreach($names as $n): ?><span><?=e($n['name'])?> <small>[<?=e($n['language'])?>]</small></span><?php endforeach;?></div><p class="label">Versions rédigées</p><nav class="versions"><?php foreach($versions as $v): ?><a class="version <?= $current && $v['id']==$current['id'] ? 'on':'' ?>" href="?game=<?=e($slug)?>&version=<?=$v['id']?>"><?=e($v['source'])?> · <?=e($v['language'])?><?= $v['status']==='primary' ? ' · principale':'' ?></a><?php endforeach;?></nav><?php if(!$current): ?><p class="note">Aucune version liée pour le moment : rapprochement à vérifier.</p><?php else: ?><p class="note">Version <?=e($current['status'])?> · source <?=e($current['source'])?><?= $current['variant_note'] ? ' — '.e($current['variant_note']) : '' ?></p><article class="rule"><?=html(Library::content($current['markdown_path']))?></article><?php endif;?></section><?php endif;?></main></body></html>
