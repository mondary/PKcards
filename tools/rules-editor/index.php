<?php
error_reporting(E_ERROR);
$db_path = __DIR__ . '/data.sqlite';
$db = new PDO('sqlite:' . $db_path);
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$db->exec('CREATE TABLE IF NOT EXISTS projects (id TEXT PRIMARY KEY, label TEXT NOT NULL, doc_label TEXT DEFAULT "Document", source_label TEXT DEFAULT "Source", storage_path TEXT DEFAULT "assets/docs")');
$db->exec("INSERT OR IGNORE INTO projects(id,label,doc_label,source_label) VALUES('regles','Règles','Règle','Source')");
$db->exec("INSERT OR IGNORE INTO projects(id,label,doc_label,source_label) VALUES('tarot','Tarot','Tirage','Interprétation')");
foreach(['versions','rules','aliases','merges'] as $t){
  try{$db->exec("ALTER TABLE $t ADD COLUMN project TEXT DEFAULT 'regles'");}catch(Exception$e){}
}
$db->exec("UPDATE versions SET project='regles' WHERE project IS NULL OR project=''");
$db->exec("UPDATE rules SET project='regles' WHERE project IS NULL OR project=''");
$db->exec("UPDATE aliases SET project='regles' WHERE project IS NULL OR project=''");
$db->exec("UPDATE merges SET project='regles' WHERE project IS NULL OR project=''");
$db->exec('CREATE TABLE IF NOT EXISTS doc_meta (slug TEXT, project TEXT, starred INTEGER DEFAULT 0, archived INTEGER DEFAULT 0, PRIMARY KEY(slug,project))');

function projectConfig($db,$id){
  $r=$db->prepare('SELECT * FROM projects WHERE id=?');$r->execute([$id]);$p=$r->fetch(PDO::FETCH_ASSOC);
  return $p?:['id'=>'regles','label'=>'Projet','doc_label'=>'Document','source_label'=>'Source','storage_path'=>'assets/docs'];
}

$action=$_GET['action']??'';$project=$_GET['project']??'regles';$slug=$_GET['game']??'';$hx=$_SERVER['HTTP_HX_REQUEST']??false;
$prj=projectConfig($db,$project);$pjid=$prj['id'];$sto=$prj['storage_path'].'/'.$pjid;

function resolveSlug($db,$p,$s){$r=$db->prepare('SELECT merged_into FROM merges WHERE slug=? AND project=?');$r->execute([$s,$p]);$row=$r->fetch(PDO::FETCH_ASSOC);return $row?$row['merged_into']:$s;}
function allSlugs($db,$p,$s){$out=[$s];$r=$db->prepare('SELECT slug FROM merges WHERE merged_into=? AND project=?');$r->execute([$s,$p]);while($row=$r->fetch(PDO::FETCH_ASSOC))$out[]=$row['slug'];return $out;}

if($action==='add_alias'&&$_SERVER['REQUEST_METHOD']==='POST'){
  $a=trim($_POST['alias']??'');$s=trim($_POST['slug']??'');$pj=$_POST['project']??$pjid;
  if($a&&$s){try{$db->prepare('INSERT INTO aliases(alias,slug,project)VALUES(?,?,?)')->execute([$a,$s,$pj]);echo json_encode(['ok'=>true]);}catch(Exception$e){echo json_encode(['ok'=>false,'error'=>'Existe déjà']);}}
  else echo json_encode(['ok'=>false]);exit;
}
if($action==='remove_alias'&&$_SERVER['REQUEST_METHOD']==='POST'){
  $db->prepare('DELETE FROM aliases WHERE alias=?')->execute([trim($_POST['alias']??'')]);echo json_encode(['ok'=>true]);exit;
}
if($action==='merge'&&$_SERVER['REQUEST_METHOD']==='POST'){
  $f=trim($_POST['from']??'');$i=trim($_POST['into']??'');$pj=$_POST['project']??$pjid;
  if($f&&$i&&$f!==$i){$db->prepare('INSERT OR REPLACE INTO merges(slug,merged_into,project)VALUES(?,?,?)')->execute([$f,$i,$pj]);echo json_encode(['ok'=>true]);}
  else echo json_encode(['ok'=>false]);exit;
}
if($action==='unmerge'&&$_SERVER['REQUEST_METHOD']==='POST'){
  $db->prepare('DELETE FROM merges WHERE slug=? AND project=?')->execute([trim($_POST['slug']??''),$_POST['project']??$pjid]);echo json_encode(['ok'=>true]);exit;
}

if($action==='meta'&&$_SERVER['REQUEST_METHOD']==='POST'){
  $s=trim($_POST['slug']??'');$pj=$_POST['project']??$pjid;$col=$_POST['col']??'starred';$val=(int)($_POST['val']??1);
  if($s&&in_array($col,['starred','archived'])){
    $db->prepare('INSERT OR IGNORE INTO doc_meta(slug,project,starred,archived)VALUES(?,?,0,0)')->execute([$s,$pj]);
    $db->prepare("UPDATE doc_meta SET $col=? WHERE slug=? AND project=?")->execute([$val,$s,$pj]);
    echo json_encode(['ok'=>true]);
  }else echo json_encode(['ok'=>false]);
  exit;
}

if($action==='suggest'){
  header('Content-Type: application/json');
  $q=trim($_GET['q']??'');$exc=trim($_GET['exclude']??'');
  if(strlen($q)<2){echo '[]';exit;}
  $excluded=[$pjid,'%'.$q.'%'];
  $st=$db->prepare("SELECT DISTINCT v.slug,v.title FROM versions v WHERE v.project=? AND v.slug LIKE ? AND v.slug NOT IN(SELECT merged_into FROM merges WHERE project=? UNION SELECT slug FROM merges WHERE merged_into=? AND project=?)");
  $st->execute([$pjid,'%'.$q.'%',$pjid,$exc,$pjid]);
  echo json_encode($st->fetchAll(PDO::FETCH_ASSOC));exit;
}

if($action==='save'&&$_SERVER['REQUEST_METHOD']==='POST'){
  $s=$_POST['slug']??'';$c=$_POST['content']??'';$pj=$_POST['project']??$pjid;
  $title=$s;if(preg_match('/^#\s+(.+)/m',$c,$m))$title=trim($m[1]);
  $db->prepare('INSERT OR REPLACE INTO rules(slug,title,content,updated_at,project)VALUES(?,?,?,datetime("now"),?)')->execute([$s,$title,$c,$pj]);
  $mdDir=__DIR__.'/../../'.$sto;
  if(!is_dir($mdDir))@mkdir($mdDir,0755,true);
  @file_put_contents($mdDir.'/'.$s.'.md',$c);
  echo json_encode(['ok'=>true]);exit;
}

if($action==='export'&&$slug){
  $path=__DIR__.'/../../'.$sto.'/'.$slug.'.md';
  if(file_exists($path)){header('Content-Type: text/markdown; charset=utf-8');header('Content-Disposition: attachment; filename="'.$slug.'.md"');readfile($path);}
  else echo 'Fichier introuvable';
  exit;
}

if($action==='dedup'&&$hx){
  $docs=$db->prepare("SELECT slug,title,GROUP_CONCAT(DISTINCT source)sources,COUNT(*)n,MAX(wordcount)wc FROM versions WHERE project=? GROUP BY slug ORDER BY title");
  $docs->execute([$pjid]);$docs=$docs->fetchAll(PDO::FETCH_ASSOC);
  $rules=$db->prepare("SELECT slug FROM rules WHERE project=?");$rules->execute([$pjid]);$rules=$rules->fetchAll(PDO::FETCH_COLUMN);
  $merges=$db->prepare("SELECT slug,merged_into FROM merges WHERE project=?");$merges->execute([$pjid]);$mergedMap=[];while($m=$merges->fetch(PDO::FETCH_ASSOC))$mergedMap[$m['slug']]=$m['merged_into'];
  // build pairs
  $pairs=[];for($i=0;$i<count($docs);$i++)for($j=$i+1;$j<count($docs);$j++){
    $a=$docs[$i];$b=$docs[$j];
    $same=similar_text($a['title'],$b['title'],$pct);
    if($pct>65)$pairs[]=['a'=>$a,'b'=>$b,'pct'=>round($pct)];
  }
  usort($pairs,fn($x,$y)=>$y['pct']-$x['pct']);
  $pairs=array_slice($pairs,0,100);
  ?>
  <div class="dedup">
    <div class="top"><button class="b" onclick="showList()">←</button><h1>🔍 Dédoublonnage — <?=htmlspecialchars($prj['label'])?></h1><span class="sp"></span><span class="src-n"><?=count($docs)?> documents, <?=count($pairs)?> similarités</span></div>
    <?php if(!$pairs):?><p style="padding:20px;color:#555">Aucune similitude détectée.</p><?php endif;?>
    <?php foreach($pairs as $pk=>$p):$im=isset($mergedMap[$p['a']['slug']])||isset($mergedMap[$p['b']['slug']]);?>
    <div class="dp">
      <div class="dp-h"><span class="dp-p"><?=$p['pct']?>%</span> similaires</div>
      <div class="dp-cols">
        <div class="dp-col <?=in_array($p['a']['slug'],$rules)?'has':''?> <?=isset($mergedMap[$p['a']['slug']])?'merged':''?>">
          <div class="dp-t"><?=htmlspecialchars($p['a']['title'])?></div>
          <div class="dp-m"><?=htmlspecialchars($p['a']['slug'])?> · <?=$p['a']['n']?> sources · <?=$p['a']['wc']?> mots</div>
          <div class="dp-s"><?php foreach(explode(',',$p['a']['sources']) as $src):?><span class="sb"><?=htmlspecialchars($src)?></span><?php endforeach;?></div>
          <div class="dp-btns">
            <button class="b" onclick="openGame('<?=$p['a']['slug']?>')">✏️ Ouvrir</button>
            <?php if(!isset($mergedMap[$p['a']['slug']])):?><button class="b" onclick="mergeInto('<?=$p['a']['slug']?>','<?=$p['b']['slug']?>')">⤻ Fusionner → <?=htmlspecialchars($p['b']['title'])?></button><?php endif;?>
          </div>
        </div>
        <div class="dp-col <?=in_array($p['b']['slug'],$rules)?>'has':''?> <?=isset($mergedMap[$p['b']['slug']])?'merged':''?>">
          <div class="dp-t"><?=htmlspecialchars($p['b']['title'])?></div>
          <div class="dp-m"><?=htmlspecialchars($p['b']['slug'])?> · <?=$p['b']['n']?> sources · <?=$p['b']['wc']?> mots</div>
          <div class="dp-s"><?php foreach(explode(',',$p['b']['sources']) as $src):?><span class="sb"><?=htmlspecialchars($src)?></span><?php endforeach;?></div>
          <div class="dp-btns">
            <button class="b" onclick="openGame('<?=$p['b']['slug']?>')">✏️ Ouvrir</button>
            <?php if(!isset($mergedMap[$p['b']['slug']])):?><button class="b" onclick="mergeInto('<?=$p['b']['slug']?>','<?=$p['a']['slug']?>')">⤻ Fusionner → <?=htmlspecialchars($p['a']['title'])?></button><?php endif;?>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach;?>
  </div>
  <script>
  function mergeInto(f,t){
    fetch('?action=merge',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'from='+encodeURIComponent(f)+'&into='+encodeURIComponent(t)+'&project=<?=$pjid?>'})
      .then(r=>r.json()).then(r=>{if(r.ok)location.reload();});
  }
  </script>
  <?php exit;
}

function parseSections($txt){
  $secs=[];$curH='__top__';$curL=1;$curB='';
  foreach(explode("\n",$txt) as $line){
    if(preg_match('/^(#{1,3})\s+(.+)/',$line,$m)){if($curB!=='')$secs[]=[$curH,$curB];$curL=strlen($m[1]);$curH=trim($m[2]);$curB='';}
    else $curB.=$line."\n";
  }
  if($curB!=='')$secs[]=[$curH,$curB];
  return $secs;
}

if($hx&&$slug){
  $can=resolveSlug($db,$pjid,$slug);$slugs=allSlugs($db,$pjid,$can);
  $ph=implode(',',array_fill(0,count($slugs),'?'));
  $qs=$slugs;$qs[]=$pjid;
  $versions=$db->prepare("SELECT source,title,content,wordcount FROM versions WHERE slug IN($ph) AND project=? ORDER BY source");
  $versions->execute($qs);$versions=$versions->fetchAll(PDO::FETCH_ASSOC);
  $rule=$db->prepare('SELECT content FROM rules WHERE slug=? AND project=?');$rule->execute([$can,$pjid]);$rule=$rule->fetch(PDO::FETCH_ASSOC);
  $editorContent=$rule?$rule['content']:($versions[0]['content']??'');
  $title=$versions[0]['title']??$can;
  $mergeSt=$db->prepare('SELECT slug FROM merges WHERE merged_into=? AND project=?');$mergeSt->execute([$can,$pjid]);$merged=$mergeSt->fetchAll(PDO::FETCH_COLUMN);
  $fpath=$sto.'/'.$can.'.md';
  $dl=$prj['doc_label'];$sl=$prj['source_label'];
  // section parsing
  $srcSections=[];$allHeadings=[];
  foreach($versions as $vi=>$v){
    $secs=parseSections($v['content']);$srcSections[$vi]=$secs;
    foreach($secs as $s)if($s[0]!=='__top__')$allHeadings[$s[0]]=($allHeadings[$s[0]]??0)+1;
  }
?>
<div class="ed" data-slug="<?=htmlspecialchars($can)?>" data-project="<?=$pjid?>">
  <div class="top">
    <h1><?=htmlspecialchars($title)?></h1>
    <span class="src-n"><?=count($versions)?> <?=mb_strtolower($sl)?><?=count($versions)>1?'s':''?></span>
    <span class="sp"></span>
    <span class="fp">📁 <?=htmlspecialchars($fpath)?></span>
    <button class="b" onclick="exportRule()">📤</button>
    <button class="b" onclick="togglePreview()">👁️</button>
    <button class="b bs" onclick="saveRule()">💾</button>
  </div>
  <div class="sub">
    <span class="ml">🔗 Liens</span>
    <span id="links"><?php foreach($merged as $m):?><span class="t lt"><?=htmlspecialchars($m)?><span class="td" onclick="unlink('<?=htmlspecialchars($m,ENT_QUOTES)?>')">×</span></span><?php endforeach;?></span>
    <div class="acw">
      <input id="linkInput" class="link-input" placeholder="+ lier un document..." autocomplete="off" oninput="suggestLinks(this.value)" onkeydown="if(event.key==='Enter'){event.preventDefault();acceptSuggestion();}" onblur="setTimeout(()=>document.getElementById('linkList').style.display='none',200)">
      <div id="linkList" class="acl"></div>
    </div>
    <span class="ms"></span>
    <input id="colSearch" class="mi" placeholder="🔍 chercher dans les colonnes" style="width:160px" oninput="searchInCols(this.value)">
  </div>
  <div class="scroll-x">
    <div class="c cm" id="col-m">
      <div class="ph">📝 Ta <?=mb_strtolower($dl)?> finale</div>
      <textarea id="editor" spellcheck="false"><?=htmlspecialchars($editorContent)?></textarea>
      <div class="rh" data-col="col-m"></div>
    </div>
    <?php foreach($versions as $vi=>$v):?>
    <div class="c cs" id="col-<?=$vi?>">
      <div class="ph">📄 <?=htmlspecialchars($v['source'])?> — <?=htmlspecialchars($v['title'])?> <span class="sw"><?=$v['wordcount']?> mots</span></div>
      <div class="cv" id="sc-<?=$vi?>" data-raw="<?=htmlspecialchars($v['content'],ENT_QUOTES)?>">
        <?php foreach($srcSections[$vi] as $s):$isH=$s[0]!=='__top__';$uniq=$isH&&($allHeadings[$s[0]]??0)===1;?>
        <div class="sbk <?=$uniq?'su':''?>">
          <?php if($isH):?><div class="sh" data-sh="<?=htmlspecialchars($s[0])?>"><?=htmlspecialchars($s[0])?><span class="sp"></span><span class="pbtn" onclick="event.stopPropagation();togglePin(this)">📌</span></div><?php endif;?>
          <div class="scp"><?=htmlspecialchars($s[1])?></div>
        </div>
        <?php endforeach;?>
      </div>
      <div class="cf"><button class="cb" onclick="loadSource(<?=$vi?>)">📝 Éditer cette <?=mb_strtolower($sl)?></button></div>
      <div class="rh" data-col="col-<?=$vi?>"></div>
    </div>
    <?php endforeach;?>
  </div>
  <div class="drawer" id="drawer">
    <div class="drawer-h">
      <span>👁️ Aperçu</span>
      <span class="sp"></span>
      <button class="b" onclick="togglePreview()">✕</button>
    </div>
    <div class="drawer-b" id="pv"></div>
  </div>
  <div class="overlay" id="overlay" onclick="togglePreview()"></div>
</div>
<?php exit; }

$q=$_GET['q']??'';$multi=isset($_GET['multi']);
$sql="SELECT v.slug,MAX(v.title)title,COUNT(*)n,GROUP_CONCAT(DISTINCT v.source)sources,
      COALESCE(dm.starred,0)starred,COALESCE(dm.archived,0)archived,
      CASE WHEN r.slug IS NOT NULL THEN 1 ELSE 0 END finalized
      FROM versions v
      LEFT JOIN doc_meta dm ON dm.slug=v.slug AND dm.project=v.project
      LEFT JOIN rules r ON r.slug=v.slug AND r.project=v.project
      WHERE v.project=?";
$p=[$pjid];
if($q){$sql.=" AND (v.slug LIKE ? OR v.slug IN(SELECT merged_into FROM merges WHERE slug LIKE ? AND project=?))";$p[]="%$q%";$p[]="%$q%";$p[]=$pjid;}
$sql.=" GROUP BY v.slug";if($multi)$sql.=" HAVING n>1";
$sql.=" ORDER BY dm.starred DESC, finalized DESC, title";
$st=$db->prepare($sql);$st->execute($p);$docs=$st->fetchAll(PDO::FETCH_ASSOC);
$mm=[];$mr=$db->prepare('SELECT slug,merged_into FROM merges WHERE project=?');$mr->execute([$pjid]);while($r=$mr->fetch(PDO::FETCH_ASSOC))$mm[$r['slug']]=$r['merged_into'];
$cntTodo=$cntDone=$cntStar=$cntArch=0;
foreach($docs as $d){if($d['archived'])$cntArch++;elseif($d['starred'])$cntStar++;elseif($d['finalized'])$cntDone++;else $cntTodo++;}
$allPrjs=$db->query('SELECT id,label FROM projects ORDER BY label')->fetchAll(PDO::FETCH_ASSOC);
$projectCounts=[];
foreach($allPrjs as $pj){$c=$db->prepare("SELECT COUNT(DISTINCT slug)FROM versions WHERE project=?");$c->execute([$pj['id']]);$projectCounts[$pj['id']]=$c->fetchColumn();}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Editor — <?=htmlspecialchars($prj['label'])?></title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'><rect width='64' height='64' rx='14' fill='%23f7f7f8'/><text x='32' y='44' font-size='32' text-anchor='middle'>✏️</text></svg>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{
  --bg-page:#f7f7f8;--card-bg:#ffffff;--border:rgba(0,0,0,.08);
  --text-1:#161718;--text-2:#737373;--text-3:#8e8e93;
  --accent:#3b82f6;--accent-hover:#2563eb;--gs:#10b981;
  --r4:4px;--r8:8px;--r12:12px;--r16:16px;
  --shadow-sm:inset .75px .75px 1px #fff,inset -.75px -.75px 1px rgba(0,0,0,.08),0 2px 4px rgba(0,0,0,.04);
  --shadow-lg:0 10px 25px -5px rgba(0,0,0,.1),0 8px 10px -6px rgba(0,0,0,.1);
  --input-bg:#f2f3f5;--badge-bg:#e5e7eb;
}
[data-theme="dark"]{
  --bg-page:#0b0c0e;--card-bg:#141517;--border:rgba(255,255,255,.1);
  --text-1:#f3f4f6;--text-2:#9ca3af;--text-3:#6b7280;
  --accent:#60a5fa;--accent-hover:#93c5fd;--gs:#34d399;
  --shadow-sm:inset .75px .75px 1px rgba(255,255,255,.08),inset -.75px -.75px 1px rgba(0,0,0,.4),0 2px 4px rgba(0,0,0,.2);
  --shadow-lg:0 10px 25px -5px rgba(0,0,0,.5),0 8px 10px -6px rgba(0,0,0,.5);
  --input-bg:#1c1e22;--badge-bg:#282a30;
}
body{font-family:'Inter',-apple-system,BlinkMacSystemFont,sans-serif;background:var(--bg-page);color:var(--text-1);height:100dvh;overflow:hidden;-webkit-font-smoothing:antialiased}
.layout{display:flex;height:100dvh}

/* SIDEBAR */
.sidebar{width:280px;flex-shrink:0;display:flex;flex-direction:column;background:var(--card-bg);border-right:1px solid var(--border);transition:width .2s;overflow:hidden}
.sidebar.collapsed{width:0}
.sb-top{display:flex;align-items:center;gap:8px;padding:12px 14px;border-bottom:1px solid var(--border)}
.sb-top .psel{flex:1;padding:6px 10px;background:var(--input-bg);border:1px solid var(--border);border-radius:var(--r8);color:var(--text-1);font-size:.8rem;font-family:inherit;cursor:pointer;outline:none;font-weight:500}
.sb-search{padding:8px 14px}
.sb-search input{width:100%;padding:7px 12px;background:var(--input-bg);border:1px solid var(--border);border-radius:var(--r8);color:var(--text-1);font-size:.8rem;font-family:inherit;outline:none}
.sb-search input:focus{border-color:var(--accent)}
.sb-filters{display:flex;gap:4px;padding:0 14px 8px;flex-wrap:wrap}
.flt{padding:4px 10px;background:transparent;border:1px solid var(--border);border-radius:99px;color:var(--text-2);cursor:pointer;font-size:.68rem;font-weight:600;transition:all .15s;font-family:inherit}
.flt:hover{color:var(--text-1);border-color:rgba(0,0,0,.15)}
.flt.active{background:var(--accent);border-color:var(--accent);color:#fff}
.flt .fc{font-size:.6rem;opacity:.7}
.sb-list{flex:1;overflow-y:auto;padding:0 8px 20px}
.sb-group{font-size:.62rem;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px;font-weight:600;padding:10px 8px 4px;position:sticky;top:0;background:var(--card-bg);z-index:2}
.sb-item{display:flex;align-items:center;gap:6px;padding:7px 8px;border-radius:var(--r8);cursor:pointer;transition:all .12s;margin-bottom:1px}
.sb-item:hover{background:var(--input-bg)}
.sb-item.active{background:rgba(59,130,246,.1)}
.sb-item .si-star{cursor:pointer;font-size:.7rem;opacity:.2;flex-shrink:0;transition:opacity .15s}
.sb-item .si-star:hover{opacity:.5}
.sb-item .si-star.on{opacity:1}
.sb-item .si-name{flex:1;font-size:.78rem;font-weight:500;color:var(--text-1);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sb-item.done .si-name{color:var(--text-2)}
.sb-item.archived{display:none}
.sb-item.archived.show{display:flex;opacity:.4}
.sb-item .si-count{font-size:.6rem;color:var(--text-3);background:var(--badge-bg);padding:1px 6px;border-radius:99px;flex-shrink:0;font-weight:600}
.sb-item .si-arch{cursor:pointer;font-size:.64rem;opacity:.2;flex-shrink:0;transition:opacity .15s}
.sb-item .si-arch:hover{opacity:.6}

/* MAIN */
.main{flex:1;display:flex;flex-direction:column;min-width:0}
.main-top{display:flex;align-items:center;gap:10px;padding:8px 14px;border-bottom:1px solid var(--border);flex-shrink:0;background:var(--card-bg)}
.main-top h1{font-size:.85rem;color:var(--text-1);white-space:nowrap;font-weight:600}
.main-top .sp{flex:1}
.main-top .dedup-link{font-size:.72rem;color:var(--accent);cursor:pointer;font-weight:500}
.main-top .dedup-link:hover{color:var(--accent-hover)}
.editor-area{flex:1;overflow:hidden;position:relative;display:flex;flex-direction:column}
.placeholder{display:flex;align-items:center;justify-content:center;height:100%;color:var(--text-3);font-size:.9rem}
.ed{flex:1;display:flex;flex-direction:column;min-height:0;width:100%}

/* EDITOR TOP BAR */
.top{display:flex;align-items:center;gap:8px;padding:8px 14px;border-bottom:1px solid var(--border);flex-shrink:0;background:var(--card-bg)}
.top h1{font-size:.95rem;color:var(--text-1);font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.src-n{font-size:.68rem;color:var(--text-3);white-space:nowrap}
.sp{flex:1}
.fp{font-size:.62rem;color:var(--text-3);font-family:'JetBrains Mono',monospace;white-space:nowrap}
.b{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:6px 12px;background:var(--card-bg);border:1px solid var(--border);border-radius:var(--r8);color:var(--text-1);cursor:pointer;font-size:.76rem;font-weight:500;font-family:inherit;box-shadow:var(--shadow-sm);transition:all .15s;user-select:none}
.b:hover{transform:translateY(-1px);border-color:rgba(0,0,0,.15)}
.b:active{transform:translateY(0)}
.bs{background:var(--text-1);color:var(--bg-page);border:none;font-weight:600}
.sub{display:flex;align-items:center;gap:6px;padding:4px 14px;border-bottom:1px solid var(--border);flex-shrink:0;font-size:.74rem;flex-wrap:wrap;background:var(--card-bg)}
.ml{color:var(--text-3);font-size:.62rem;text-transform:uppercase;font-weight:600;letter-spacing:.3px}
.t{display:inline-flex;align-items:center;gap:2px;padding:2px 8px;border-radius:99px;font-size:.68rem;font-weight:500}
.lt{background:rgba(59,130,246,.1);color:var(--accent)}
.td{cursor:pointer;opacity:.4;margin-left:3px;font-size:.72rem}.td:hover{opacity:1}
.mi{padding:4px 8px;background:var(--input-bg);border:1px solid var(--border);border-radius:var(--r8);color:var(--text-1);font-size:.72rem;font-family:inherit;outline:none}
.ms{width:1px;height:14px;background:var(--border)}
.acw{position:relative;display:inline-block}
.link-input{width:200px;padding:5px 12px;background:var(--input-bg);border:1px dashed var(--border);border-radius:var(--r8);color:var(--text-1);font-size:.74rem;font-family:inherit;outline:none;transition:border-color .15s}
.link-input:focus{border-style:solid;border-color:var(--accent)}
.link-input::placeholder{color:var(--text-3)}
.acl{position:absolute;top:100%;left:0;right:0;background:var(--card-bg);border:1px solid var(--border);border-radius:var(--r8);box-shadow:var(--shadow-lg);max-height:220px;overflow-y:auto;display:none;z-index:50;margin-top:2px}
.acl.show{display:block}
.acl-item{padding:6px 10px;cursor:pointer;font-size:.74rem;color:var(--text-1);transition:background .1s}
.acl-item:hover{background:var(--input-bg)}

/* SCROLL COLUMNS */
.scroll-x{flex:1;display:flex;overflow-x:auto;overflow-y:hidden;min-height:0;padding:8px;gap:8px}
.scroll-x::-webkit-scrollbar{height:6px}
.scroll-x::-webkit-scrollbar-track{background:transparent}
.scroll-x::-webkit-scrollbar-thumb{background:var(--border);border-radius:3px}
.c{flex-shrink:0;display:flex;flex-direction:column;background:var(--card-bg);border:1px solid var(--border);border-radius:var(--r12);box-shadow:var(--shadow-sm);position:relative;min-width:200px;overflow:hidden}
.cm{min-width:450px;width:550px;position:sticky;left:0;z-index:5;border:2px solid var(--accent);box-shadow:var(--shadow-lg)}
.cm .ph{color:var(--accent);background:rgba(59,130,246,.05)}
.cs{width:450px;transition:transform .2s,box-shadow .2s}
.cs:hover{box-shadow:var(--shadow-lg)}
.cs.active{border-color:var(--accent)}
.cs.active .ph{color:var(--accent)}
.ph{font-size:.66rem;color:var(--text-3);padding:8px 12px;text-transform:uppercase;letter-spacing:.4px;flex-shrink:0;font-weight:600;border-bottom:1px solid var(--border)}
.sw{font-size:.6rem;color:var(--text-3);margin-left:8px;text-transform:none;letter-spacing:0;font-weight:400}
#editor{flex:1;width:100%;min-height:100px;resize:none;padding:14px;background:transparent;border:none;color:var(--text-1);font-family:'JetBrains Mono',monospace;font-size:.85rem;line-height:1.7;outline:none}
.cv{flex:1;overflow-y:auto;padding:0}
.sbk{padding:8px 12px;border-bottom:1px solid var(--border)}
.su{border-left:3px solid var(--gs);background:rgba(16,185,129,.04)}
.sh{font-size:.64rem;color:var(--accent);text-transform:uppercase;letter-spacing:.4px;padding:4px 0 4px;position:sticky;top:0;background:var(--card-bg);z-index:2;border-bottom:1px solid var(--border);cursor:pointer;display:flex;align-items:center;gap:4px;font-weight:600;transition:background .1s}
.sh:hover{background:var(--input-bg)}
.sh.hl{background:rgba(59,130,246,.1)}
.sh.pinned{background:rgba(59,130,246,.06);border-left:3px solid var(--accent);padding-left:5px}
.sh .pbtn{cursor:pointer;font-size:.7rem;opacity:.3;flex-shrink:0;margin-left:auto}
.sh:hover .pbtn{opacity:1}
mark{background:rgba(250,204,21,.3);color:inherit;border-radius:3px;padding:0 2px}
.scp{font-size:.76rem;color:var(--text-2);line-height:1.55;white-space:pre-wrap;font-family:'JetBrains Mono',monospace;padding:4px 0}
.cf{border-top:1px solid var(--border);padding:6px 12px;flex-shrink:0}
.cb{padding:5px 12px;background:rgba(59,130,246,.08);border:1px solid rgba(59,130,246,.15);border-radius:var(--r8);color:var(--accent);cursor:pointer;font-size:.7rem;font-weight:600;font-family:inherit;transition:all .15s}
.cb:hover{background:rgba(59,130,246,.12);transform:translateY(-1px)}
.rh{position:absolute;top:0;right:-4px;bottom:0;width:8px;cursor:col-resize;z-index:10;background:transparent;transition:background .1s}
.rh:hover,.rh.active{background:rgba(59,130,246,.15)}

/* DRAWER */
.overlay{position:fixed;inset:0;z-index:200;background:rgba(0,0,0,.4);backdrop-filter:blur(8px);display:none}
.overlay.show{display:block}
.drawer{position:fixed;bottom:0;left:0;right:0;z-index:201;background:var(--card-bg);border:1px solid var(--border);border-radius:var(--r16) var(--r16) 0 0;max-height:60vh;display:none;flex-direction:column;box-shadow:var(--shadow-lg)}
.drawer.show{display:flex}
.drawer-h{display:flex;align-items:center;gap:10px;padding:12px 16px;border-bottom:1px solid var(--border);font-size:.82rem;color:var(--text-1);flex-shrink:0;font-weight:600}
.drawer-b{overflow-y:auto;padding:12px 16px;font-size:.82rem;line-height:1.6;color:var(--text-2);flex:1}
.drawer-b h1{font-size:1.15rem;color:var(--text-1);margin:8px 0 4px;font-weight:700}
.drawer-b h2{font-size:.92rem;color:var(--text-1);margin:10px 0 4px;padding-bottom:3px;border-bottom:1px solid var(--border);font-weight:600}
.drawer-b p{margin:3px 0}.drawer-b strong{color:var(--text-1)}
.drawer-b ul,ol{margin:3px 0 3px 18px}

/* DEDUP */
.dedup{padding:12px 14px;max-width:1000px;margin:0 auto;height:100%;overflow-y:auto}
.dp{background:var(--card-bg);border:1px solid var(--border);border-radius:var(--r12);box-shadow:var(--shadow-sm);margin-bottom:8px;overflow:hidden}
.dp-h{font-size:.72rem;color:var(--text-3);padding:6px 10px;background:var(--input-bg);border-bottom:1px solid var(--border);font-weight:600}
.dp-p{color:var(--accent)}
.dp-cols{display:flex;gap:0}
.dp-col{flex:1;padding:10px;border-right:1px solid var(--border)}
.dp-col:last-child{border-right:none}
.dp-col.has{background:rgba(16,185,129,.04)}
.dp-col.merged{opacity:.4}
.dp-t{font-size:.82rem;color:var(--text-1);font-weight:600;margin-bottom:3px}
.dp-m{font-size:.64rem;color:var(--text-3);margin-bottom:5px}
.dp-s{display:flex;gap:4px;flex-wrap:wrap;margin-bottom:6px}
.dp-btns{display:flex;gap:6px;flex-wrap:wrap}
.sb-tag{font-size:.58rem;background:var(--badge-bg);color:var(--text-2);padding:1px 6px;border-radius:99px;font-weight:600}
</style>
</head>
<body>

<div class="layout">
  <div class="sidebar" id="sidebar">
    <div class="sb-top">
      <select class="psel" onchange="switchProject(this.value)">
        <?php foreach($allPrjs as $pj):?>
        <option value="<?=$pj['id']?>" <?=$pj['id']===$pjid?'selected':''?>><?=htmlspecialchars($pj['label'])?> (<?=$projectCounts[$pj['id']]??0?>)</option>
        <?php endforeach;?>
      </select>
    </div>
    <div class="sb-search">
      <input id="sbSearch" placeholder="🔍 Rechercher..." value="<?=htmlspecialchars($q)?>" oninput="filterSidebar()">
    </div>
    <div class="sb-filters">
      <button class="flt active" data-f="all" onclick="setFilter('all')">Tous</button>
      <button class="flt" data-f="multi" onclick="setFilter('multi')">🔀 Multi</button>
      <button class="flt" data-f="star" onclick="setFilter('star')">⭐ <span class="fc"><?=$cntStar?></span></button>
      <button class="flt" data-f="done" onclick="setFilter('done')">✓ <span class="fc"><?=$cntDone?></span></button>
      <button class="flt" data-f="todo" onclick="setFilter('todo')">📝 <span class="fc"><?=$cntTodo?></span></button>
      <button class="flt" data-f="arch" onclick="setFilter('arch')">📦 <span class="fc"><?=$cntArch?></span></button>
    </div>
    <div class="sb-list" id="sbList">
    <?php
    $groups=['star'=>['⭐ Favoris'],'done'=>['✓ Finalisées'],'todo'=>['📝 À traiter'],'arch'=>['📦 Archives']];
    foreach($groups as $gk=>$gv):
      $items=array_filter($docs,fn($d)=>match($gk){
        'star'=>$d['starred']&&!$d['archived'],
        'done'=>!$d['starred']&&!$d['archived']&&$d['finalized'],
        'todo'=>!$d['starred']&&!$d['archived']&&!$d['finalized'],
        'arch'=>$d['archived'],
      });
      if(!$items)continue;
    ?>
    <div class="sb-group" data-group="<?=$gk?>"><?=$gv[0]?> (<?=count($items)?>)</div>
    <?php foreach($items as $d):$im=isset($mm[$d['slug']]);?>
    <div class="sb-item <?=$d['finalized']?'done':''?> <?=$d['archived']?'archived':''?>" data-slug="<?=htmlspecialchars($d['slug'])?>" data-star="<?=$d['starred']?>" data-arch="<?=$d['archived']?>" data-done="<?=$d['finalized']?>" data-n="<?=$d['n']?>" onclick="openGame('<?=htmlspecialchars($d['slug'])?>')">
      <span class="si-star <?=$d['starred']?'on':''?>" onclick="event.stopPropagation();toggleMeta('<?=htmlspecialchars($d['slug'])?>','starred',this)">⭐</span>
      <span class="si-name"><?=htmlspecialchars($d['title'])?></span>
      <?php if($im):?><span class="sb-tag" style="color:var(--gold)">⤻</span><?php endif;?>
      <span class="si-count"><?=$d['n']?></span>
      <span class="si-arch" onclick="event.stopPropagation();toggleMeta('<?=htmlspecialchars($d['slug'])?>','archived',this)">📦</span>
    </div>
    <?php endforeach;?>
    <?php endforeach;?>
    </div>
  </div>
  <div class="main">
    <div class="main-top">
      <button class="b" onclick="toggleSidebar()">☰</button>
      <h1>✏️ <?=htmlspecialchars($prj['label'])?></h1>
      <span class="sp"></span>
      <span class="dedup-link" onclick="openDedup()">🔍 Dédoublonnage</span>
      <button class="b" onclick="toggleTheme()" id="themeBtn" style="padding:6px 10px">🌙</button>
    </div>
    <div class="editor-area" id="editorArea">
      <div class="placeholder">← Sélectionnez un document dans la sidebar</div>
    </div>
  </div>
</div>

<script>
const PJ='<?=$pjid?>';
let _filter='all';
function toggleTheme(){
  const cur=document.body.getAttribute('data-theme');
  const next=cur==='dark'?'light':'dark';
  document.body.setAttribute('data-theme',next);
  document.getElementById('themeBtn').textContent=next==='dark'?'☀️':'🌙';
  localStorage.setItem('editorTheme',next);
}
if(localStorage.getItem('editorTheme')==='dark'){document.body.setAttribute('data-theme','dark');document.addEventListener('DOMContentLoaded',()=>{const b=document.getElementById('themeBtn');if(b)b.textContent='☀️';});}
function switchProject(p){window.location.href='?project='+p;}
function loadMulti(m){const q=document.getElementById('sbSearch').value;window.location.href='?project='+PJ+'&q='+encodeURIComponent(q)+(m?'&multi=1':'');}
function toggleSidebar(){document.getElementById('sidebar').classList.toggle('collapsed');}
function setFilter(f){
  _filter=f;
  document.querySelectorAll('.flt').forEach(b=>b.classList.toggle('active',b.dataset.f===f));
  filterSidebar();
}
function filterSidebar(){
  const q=document.getElementById('sbSearch').value.toLowerCase();
  document.querySelectorAll('.sb-item').forEach(item=>{
    const name=item.querySelector('.si-name').textContent.toLowerCase();
    const slug=item.dataset.slug.toLowerCase();
    const star=item.dataset.star==='1';
    const arch=item.dataset.arch==='1';
    const done=item.dataset.done==='1';
    const matchText=!q||name.includes(q)||slug.includes(q);
    let matchFilter=true;
    if(_filter==='star')matchFilter=star;
    else if(_filter==='done')matchFilter=done&&!star&&!arch;
    else if(_filter==='todo')matchFilter=!done&&!star&&!arch;
    else if(_filter==='arch')matchFilter=arch;
    else if(_filter==='multi')matchFilter=parseInt(item.dataset.n)>1&&!arch;
    else matchFilter=!arch; // 'all' hides archived
    item.style.display=(matchText&&matchFilter)?'':'none';
  });
  document.querySelectorAll('.sb-group').forEach(g=>{
    const items=g.parentElement.querySelectorAll('.sb-item[data-slug]');
    let hasVisible=false;
    g.parentElement.querySelectorAll('.sb-item').forEach(item=>{
      if(item.style.display!=='none'&&item.dataset.slug)hasVisible=true;
    });
    g.style.display=hasVisible?'':'none';
  });
  // show archived group when filtering for archive
  if(_filter==='arch'){document.querySelectorAll('.sb-item.archived').forEach(i=>i.classList.add('show'));}
  else{document.querySelectorAll('.sb-item.archived').forEach(i=>i.classList.remove('show'));}
}
function toggleMeta(slug,col,el){
  const item=el.closest('.sb-item');
  const cur=item.dataset[col==='starred'?'star':'arch']==='1';
  const val=cur?0:1;
  fetch('?action=meta',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'slug='+encodeURIComponent(slug)+'&col='+col+'&val='+val+'&project='+PJ})
    .then(r=>r.json()).then(r=>{
      if(!r.ok)return;
      item.dataset[col==='starred'?'star':'arch']=val;
      if(col==='starred')el.classList.toggle('on',!!val);
      if(col==='archived'){item.classList.toggle('archived',!!val);filterSidebar();}
      else if(col==='starred'&&!val){/* might need to regroup */}
    });
}
function openGame(slug){
  document.querySelectorAll('.sb-item').forEach(i=>i.classList.remove('active'));
  const item=document.querySelector(`.sb-item[data-slug="${slug}"]`);
  if(item)item.classList.add('active');
  fetch('?project='+PJ+'&game='+slug,{headers:{'HX-Request':'true'}})
    .then(r=>r.text()).then(html=>{
      document.getElementById('editorArea').innerHTML=html;
      initEditor();
    });
}
function openDedup(){
  fetch('?project='+PJ+'&action=dedup',{headers:{'HX-Request':'true'}})
    .then(r=>r.text()).then(html=>{document.getElementById('editorArea').innerHTML=html;});
}
function initEditor(){
  const ed=document.getElementById('editor');
  if(!ed)return;
  ed.addEventListener('input',updatePreview);
  updatePreview();
  restoreActiveSource();
  initResizers();
  initSyncScroll();
  restorePins();
}
function initSyncScroll(){
  const cvs=[...document.querySelectorAll('.cv')];
  if(cvs.length<2)return;
  const heads=cvs.map(cv=>[...cv.querySelectorAll('.sh')]);
  const sync=e=>{
    const src=e.target;const si=cvs.indexOf(src);
    if(si<0)return;
    let idx=-1;let best=Infinity;
    heads[si].forEach((h,i)=>{
      const d=Math.abs(h.getBoundingClientRect().top-src.getBoundingClientRect().top);
      if(d<best){best=d;idx=i;}
    });
    if(idx<0)return;
    cvs.forEach((o,i)=>{
      if(i===si)return;
      o.removeEventListener('scroll',sync);
      if(heads[i][idx])heads[i][idx].scrollIntoView({block:'start'});
      else o.scrollTop=0;
      o.addEventListener('scroll',sync);
    });
  };
  cvs.forEach(cv=>cv.addEventListener('scroll',sync,{passive:true}));
  document.querySelectorAll('.sh').forEach(h=>{
    h.addEventListener('click',function(e){
      if(e.target.closest('.pbtn'))return;
      const txt=this.dataset.sh;if(!txt)return;
      const cv=this.closest('.cv');if(!cv)return;
      document.querySelectorAll('.cv').forEach(o=>{
        if(o===cv)return;
        const target=o.querySelector(`[data-sh="${CSS.escape(txt)}"]`);
        if(target){target.scrollIntoView({block:'start'});o.querySelectorAll('.sh').forEach(sh=>sh.classList.remove('hl'));target.classList.add('hl');}
      });
      this.classList.add('hl');
    });
  });
}
function togglePin(el){
  const sh=el.closest('.sh');if(!sh)return;
  const key='pin-'+sh.dataset.sh;
  const pinned=sessionStorage.getItem(key)==='1';
  if(pinned){sessionStorage.removeItem(key);sh.classList.remove('pinned');el.textContent='📌';}
  else{sessionStorage.setItem(key,'1');sh.classList.add('pinned');el.textContent='📍';}
}
function restorePins(){
  document.querySelectorAll('.sh').forEach(sh=>{
    if(sessionStorage.getItem('pin-'+sh.dataset.sh)==='1'){sh.classList.add('pinned');const p=sh.querySelector('.pbtn');if(p)p.textContent='📍';}
  });
}
function searchInCols(q){
  const cps=[...document.querySelectorAll('.scp')];
  cps.forEach(cp=>{cp.innerHTML=cp.dataset.raw||cp.textContent;if(!cp.dataset.raw)cp.dataset.raw=cp.textContent;});
  if(!q)return;
  const re=new RegExp(q.replace(/[.*+?^${}()|[\]\\]/g,'\\$&'),'gi');
  cps.forEach(cp=>{cp.innerHTML=cp.dataset.raw.replace(re,m=>`<mark>${m}</mark>`);});
}
let _suggestSel=-1,_suggestData=[];
function suggestLinks(q){
  const list=document.getElementById('linkList');
  if(q.length<2){list.classList.remove('show');list.innerHTML='';_suggestData=[];return;}
  fetch('?action=suggest&q='+encodeURIComponent(q)+'&exclude='+encodeURIComponent(document.querySelector('.ed').dataset.slug)+'&project='+PJ)
    .then(r=>r.json()).then(data=>{
      _suggestData=data;_suggestSel=-1;
      list.innerHTML=data.map((d,i)=>`<div class="acl-item" onclick="pickSuggestion(${i})">${esc(d.title)} <span style="color:#555;font-size:.6rem">${esc(d.slug)}</span></div>`).join('');
      list.classList.toggle('show',data.length>0);
    });
}
function pickSuggestion(i){
  if(!_suggestData[i])return;
  const slug=document.querySelector('.ed').dataset.slug;
  const from=_suggestData[i].slug;
  fetch('?action=merge',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'from='+encodeURIComponent(from)+'&into='+encodeURIComponent(slug)+'&project='+PJ})
    .then(r=>r.json()).then(r=>{if(r.ok)openGame(slug);}); // reload editor
  document.getElementById('linkInput').value='';
  document.getElementById('linkList').classList.remove('show');
}
function acceptSuggestion(){if(_suggestSel>=0)pickSuggestion(_suggestSel);else if(_suggestData[0])pickSuggestion(0);}
function unlink(slug){
  const into=document.querySelector('.ed').dataset.slug;
  fetch('?action=unmerge',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'slug='+encodeURIComponent(slug)+'&project='+PJ})
    .then(r=>r.json()).then(r=>{if(r.ok)openGame(into);});
}
function esc(s){return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function initResizers(){
  let drag=null;
  document.querySelectorAll('.rh').forEach(h=>{
    h.addEventListener('mousedown',e=>{drag=h.dataset.col;h.classList.add('active');e.preventDefault();});
  });
  document.addEventListener('mousemove',e=>{
    if(!drag)return;
    const col=document.getElementById(drag);if(!col)return;
    let w=e.clientX-col.getBoundingClientRect().left;
    if(w<200)w=200;if(w>1200)w=1200;
    col.style.width=w+'px';
  });
  document.addEventListener('mouseup',()=>{
    if(drag){const h=document.querySelector('.rh.active');if(h)h.classList.remove('active');drag=null;}
  });
}
function restoreActiveSource(){const i=sessionStorage.getItem('activeSrc');if(i!==null)loadSource(+i,true);}
function loadSource(i,silent){
  const ed=document.getElementById('editor');
  const el=document.getElementById('sc-'+i);
  const sbks=el?.querySelectorAll('.sbk');
  if(!sbks||!sbks.length)return;
  let txt='';
  sbks.forEach(b=>{
    const h=b.querySelector('.sh');const c=b.querySelector('.scp');
    if(h)txt+='## '+h.textContent+'\n';
    if(c)txt+=c.textContent+'\n';
  });
  ed.value=txt.trim();updatePreview();
  document.querySelectorAll('.cs').forEach(c=>c.classList.remove('active'));
  const col=el?.closest('.cs');if(col)col.classList.add('active');
  if(!silent)sessionStorage.setItem('activeSrc',i);
}
function updatePreview(){const ed=document.getElementById('editor');const pv=document.getElementById('pv');if(ed&&pv)pv.innerHTML=marked.parse(ed.value||'*vide*');}
function togglePreview(){document.getElementById('drawer').classList.toggle('show');document.getElementById('overlay').classList.toggle('show');updatePreview();}
function saveRule(){
  const slug=document.querySelector('.ed').dataset.slug;const pj=document.querySelector('.ed').dataset.project;
  const content=document.getElementById('editor').value;
  fetch('?action=save',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
    body:'slug='+encodeURIComponent(slug)+'&content='+encodeURIComponent(content)+'&project='+pj})
    .then(r=>r.json()).then(r=>{
      const b=document.querySelector('.bs');b.textContent=r.ok?'✅ OK':'❌';setTimeout(()=>b.textContent='💾',1500);
      if(r.ok){const item=document.querySelector(`.sb-item[data-slug="${slug}"]`);if(item){item.dataset.done='1';item.classList.add('done');}}
    });
}
function exportRule(){const slug=document.querySelector('.ed').dataset.slug;window.location='?action=export&game='+slug;}
document.addEventListener('DOMContentLoaded',()=>{if(document.querySelector('.ed'))initEditor();});
</script>
</body>
</html>
