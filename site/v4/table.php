<!doctype html>
<html lang="fr">
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?= $slug ? e($game['title']) . ' — PKcards' : 'PKcards — Bibliothèque de règles' ?></title>
<style>
@import url('https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700&family=DM+Mono:wght@400;500&family=Outfit:wght@400;500;600;700&display=swap');
:root{--bg:#0d1a2c;--panel:#132940;--paper:#f8f2e6;--ink:#0b1828;--text:#f8f2e6;--muted:#9fb1c0;--line:#365068;--accent:#f05b58;--gold:#f3bc45;--display:'Barlow Condensed',sans-serif;--body:'Outfit',sans-serif;--mono:'DM Mono',monospace}
*{box-sizing:border-box}html{background:var(--bg)}body{margin:0;min-height:100dvh;background:var(--bg);color:var(--text);font-family:var(--body)}a{color:inherit}.shell{width:min(1180px,100%);margin:auto;padding:calc(20px + env(safe-area-inset-top)) clamp(18px,4vw,48px) calc(60px + env(safe-area-inset-bottom))}.top{display:flex;align-items:center;justify-content:space-between;padding-bottom:18px;border-bottom:1px solid var(--line)}.brand{font:500 .75rem var(--mono);text-decoration:none;text-transform:uppercase}.brand b{color:var(--accent);font:700 1.2rem var(--display)}.library-count{color:var(--muted);font:.65rem var(--mono)}
.hero{padding:clamp(46px,8vw,92px) 0 28px}.eyebrow{margin:0 0 12px;color:var(--gold);font:.65rem var(--mono);text-transform:uppercase}.query{display:block;width:100%;border:0;border-bottom:3px solid var(--accent);padding:0 0 10px;background:transparent;color:var(--text);outline:none;font:700 clamp(3.7rem,10vw,8rem)/.82 var(--display);text-transform:uppercase}.query::placeholder{color:var(--text);opacity:1}.query:focus::placeholder{color:var(--muted)}.instruction{margin:13px 0 0;color:var(--muted);font:.68rem var(--mono)}
.filterbar{display:grid;grid-template-columns:minmax(180px,1fr) auto auto;gap:12px;padding:18px 0;border-bottom:1px solid var(--line)}.filter-group{display:flex;align-items:center;gap:7px;min-width:0}.filter-label{flex:0 0 auto;color:var(--muted);font:.6rem var(--mono);text-transform:uppercase}.select,.chip{min-height:38px;border:1px solid var(--line);border-radius:999px;background:transparent;color:var(--text);font:.68rem var(--mono)}.select{width:min(280px,100%);padding:0 34px 0 12px}.select option{background:var(--panel)}.chips{display:flex;gap:6px}.chip{padding:0 12px;cursor:pointer}.chip.on{border-color:var(--accent);background:var(--accent);color:var(--ink)}.chip:focus-visible,.select:focus-visible,.game-card:focus-visible{outline:3px solid var(--gold);outline-offset:2px}
.result-head{display:flex;justify-content:space-between;align-items:center;padding:22px 0 12px}.result-count{font:.68rem var(--mono);color:var(--muted)}.clear{border:0;background:none;color:var(--accent);font:.68rem var(--mono);cursor:pointer}.clear[hidden]{display:none}.grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.game-card{position:relative;display:flex;min-height:174px;flex-direction:column;justify-content:space-between;overflow:hidden;border:1px solid var(--line);border-radius:12px;padding:15px;background:var(--panel);color:var(--text);text-decoration:none}.game-card:hover,.game-card.selected{border-color:var(--accent)}.game-card.selected{outline:2px solid var(--accent);outline-offset:2px}.game-family{position:relative;z-index:1;max-width:75%;color:var(--gold);font:.58rem var(--mono);text-transform:uppercase}.game-title{position:relative;z-index:1;max-width:84%;margin:22px 0 8px;font:700 clamp(1.75rem,3vw,2.45rem)/.82 var(--display);text-transform:uppercase;text-wrap:balance}.game-meta{position:relative;z-index:1;color:var(--muted);font:.59rem var(--mono)}.game-face{position:absolute;right:-22px;bottom:-35px;width:105px;opacity:.24;transform:rotate(-9deg);pointer-events:none}.empty{grid-column:1/-1;border:1px solid var(--line);border-radius:12px;padding:38px;text-align:center;color:var(--muted)}
.reader{width:min(820px,100%);margin:auto;padding-top:28px}.reader-tools{display:flex;justify-content:space-between;align-items:center}.back,.search-link{color:var(--muted);font:.67rem var(--mono);text-decoration:none}.reader .eyebrow{margin-top:48px}.reader-title{margin:0;font:700 clamp(4rem,12vw,7.5rem)/.78 var(--display);text-transform:uppercase;text-wrap:balance}.names,.versions{display:flex;gap:7px;overflow:auto;padding:4px 0;scrollbar-width:none}.names{margin:22px 0}.name,.version{flex:0 0 auto;border:1px solid var(--line);border-radius:999px;padding:7px 10px;font:.62rem var(--mono)}.version{text-decoration:none}.version.on{border-color:var(--gold);background:var(--gold);color:var(--ink)}.rule-note{margin:22px 0 0;color:var(--muted);font:.62rem var(--mono)}.rule-card{margin-top:12px;border-radius:18px;background:var(--paper);color:var(--ink);padding:clamp(24px,5vw,54px)}.rule-card p{font-size:1.05rem;line-height:1.65;text-wrap:pretty}.rule-card h2{margin:38px 0 12px;font:700 2.2rem/.9 var(--display);text-transform:uppercase;text-wrap:balance}.rule-card h3{font:700 1.5rem var(--display)}.rule-card a{color:#075d6e}
@media(max-width:900px){.grid{grid-template-columns:repeat(3,minmax(0,1fr))}.filterbar{grid-template-columns:1fr}.filter-group{overflow:auto;scrollbar-width:none}}
@media(max-width:650px){.grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:8px}.game-card{min-height:154px;padding:12px}.game-title{font-size:1.72rem}.game-face{width:88px}.query{font-size:clamp(3.3rem,17vw,5.4rem)}.filter-label{display:none}.chips{overflow:auto}.library-count{display:none}}
@media(prefers-reduced-motion:reduce){*{scroll-behavior:auto!important}}
</style>
<body>
<main class="shell">
  <header class="top">
    <a class="brand" href="?"><b>PK</b> / cartes & règles</a>
    <span class="library-count"><?= $total ?> jeux · <?= $totalVersions ?> règles</span>
  </header>

<?php if (!$slug): ?>
  <section class="library" id="library">
    <div class="hero">
      <p class="eyebrow">Bibliothèque complète</p>
      <input class="query" id="query" type="search" placeholder="À quoi joue-t-on ?" autocomplete="off" spellcheck="false" aria-label="Rechercher un jeu">
      <p class="instruction">Tape n'importe où · flèches pour naviguer · entrée pour ouvrir · échap pour effacer</p>
    </div>

    <div class="filterbar" aria-label="Filtres">
      <label class="filter-group"><span class="filter-label">Famille</span><select class="select" id="family"><option value="">Toutes les familles</option><?php foreach ($families as $f): ?><option value="<?= e($f['family']) ?>"><?= e($f['family']) ?> (<?= $f['n'] ?>)</option><?php endforeach; ?></select></label>
      <div class="filter-group"><span class="filter-label">Joueurs</span><div class="chips" id="players"><button class="chip on" data-value="">Tous</button><button class="chip" data-value="1">Solo</button><button class="chip" data-value="2">2</button><button class="chip" data-value="3-4">3–4</button><button class="chip" data-value="5+">5+</button></div></div>
      <div class="filter-group"><span class="filter-label">Niveau</span><div class="chips" id="difficulty"><button class="chip on" data-value="">Tous</button><button class="chip" data-value="Facile">Facile</button><button class="chip" data-value="Moyenne">Moyen</button><button class="chip" data-value="Difficile">Difficile</button></div></div>
    </div>

    <div class="result-head"><span class="result-count" id="count"></span><button class="clear" id="clear" hidden>Tout effacer</button></div>
    <div class="grid" id="grid"></div>
  </section>
<?php else: ?>
  <section class="reader">
    <div class="reader-tools"><a class="back" href="?">← Bibliothèque</a><a class="search-link" href="?" id="searchLink">⌕ Rechercher</a></div>
    <p class="eyebrow">Une fiche · plusieurs traditions</p>
    <h1 class="reader-title"><?= e($game['title']) ?></h1>
    <div class="names"><?php foreach ($names as $name): ?><span class="name"><?= e($name) ?></span><?php endforeach; ?></div>
    <nav class="versions" aria-label="Versions de la règle"><?php foreach ($versions as $v): ?><a class="version <?= $current && $v['id'] == $current['id'] ? 'on' : '' ?>" href="?game=<?= e($slug) ?>&version=<?= $v['id'] ?>"><?= e($v['source']) ?> · <?= e($v['language']) ?><?= $v['status'] === 'primary' ? ' ★' : '' ?></a><?php endforeach; ?></nav>
    <?php if ($current): ?><p class="rule-note">Version <?= e($current['status']) ?> · source <?= e($current['source']) ?></p><article class="rule-card"><?= html(Library::content($current['markdown_path'])) ?></article><?php else: ?><article class="rule-card"><p>Cette fiche n'a pas encore de règle liée.</p></article><?php endif; ?>
  </section>
<?php endif; ?>
</main>

<script>
const CAT = <?= json_encode($catalog, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
const HOME = <?= $slug ? 'false' : 'true' ?>;
const STORAGE = 'pkcards-library-v4';
const norm = value => String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

if (HOME) {
  const query = document.getElementById('query');
  const family = document.getElementById('family');
  const grid = document.getElementById('grid');
  const count = document.getElementById('count');
  const clear = document.getElementById('clear');
  const faces = ['01-coeur.png','V-pique.png','D-carreau.png','R-pique.png','07-trefle.png','D-coeur.png','10-pique.png','V-coeur.png'];
  let state = { q:'', family:'', players:'', difficulty:'', selected:-1 };
  try { state = { ...state, ...JSON.parse(sessionStorage.getItem(STORAGE) || '{}') }; } catch (_) {}
  CAT.forEach(game => game.haystack = norm([game.t, ...game.n].join(' ')));

  const escapeHtml = value => String(value ?? '').replace(/[&<>"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[char]));
  const matchesPlayers = game => {
    if (!state.players) return true;
    if (state.players === '1') return game.mi <= 1 && game.ma >= 1;
    if (state.players === '2') return game.mi <= 2 && game.ma >= 2;
    if (state.players === '3-4') return game.mi <= 4 && game.ma >= 3;
    return game.ma >= 5;
  };
  const save = () => sessionStorage.setItem(STORAGE, JSON.stringify({ ...state, selected:-1, scroll:scrollY }));
  const filtered = () => {
    const needle = norm(state.q.trim());
    return CAT.filter(game => (!needle || game.haystack.includes(needle)) && (!state.family || game.f.includes(state.family)) && matchesPlayers(game) && (!state.difficulty || game.d === state.difficulty));
  };
  const paintSelection = () => {
    const cards = [...grid.querySelectorAll('.game-card')];
    cards.forEach((card, index) => card.classList.toggle('selected', index === state.selected));
    cards[state.selected]?.scrollIntoView({ block:'nearest' });
  };
  const render = () => {
    const games = filtered();
    state.selected = Math.min(state.selected, games.length - 1);
    if (state.q && games.length && state.selected < 0) state.selected = 0;
    count.textContent = `${games.length} jeu${games.length > 1 ? 'x' : ''} sur ${CAT.length}`;
    clear.hidden = !state.q && !state.family && !state.players && !state.difficulty;
    document.getElementById('library').classList.toggle('is-filtering', !clear.hidden);
    grid.innerHTML = games.length ? games.map((game, index) => `<a class="game-card" href="?game=${encodeURIComponent(game.slug)}" data-index="${index}"><span class="game-family">${escapeHtml(game.f.slice(0,2).join(' · ') || game.ty || 'Jeu de cartes')}</span><strong class="game-title">${escapeHtml(game.t)}</strong><span class="game-meta">${escapeHtml(game.p || 'Joueurs à préciser')} · ${game.v} règle${game.v > 1 ? 's' : ''}</span><img class="game-face" src="?card=${faces[index % faces.length]}" alt="" aria-hidden="true" loading="lazy"></a>`).join('') : '<p class="empty">Aucun jeu ne correspond. Efface un filtre pour élargir la sélection.</p>';
    paintSelection();
    save();
  };
  const setChip = (group, value) => {
    state[group] = value;
    document.querySelectorAll(`#${group} .chip`).forEach(chip => chip.classList.toggle('on', chip.dataset.value === value));
    state.selected = -1;
    render();
  };

  query.value = state.q;
  family.value = state.family;
  setChip('players', state.players);
  setChip('difficulty', state.difficulty);
  query.addEventListener('input', () => { state.q = query.value; state.selected = -1; render(); });
  family.addEventListener('change', () => { state.family = family.value; state.selected = -1; render(); });
  document.getElementById('players').addEventListener('click', event => { const chip = event.target.closest('.chip'); if (chip) setChip('players', chip.dataset.value); });
  document.getElementById('difficulty').addEventListener('click', event => { const chip = event.target.closest('.chip'); if (chip) setChip('difficulty', chip.dataset.value); });
  clear.addEventListener('click', () => { state = { q:'', family:'', players:'', difficulty:'', selected:-1 }; query.value=''; family.value=''; setChip('players',''); setChip('difficulty',''); query.focus(); });
  grid.addEventListener('click', save);

  document.addEventListener('keydown', event => {
    const typing = event.target === query;
    if (event.key === 'Escape') {
      if (state.q) { state.q=''; query.value=''; state.selected=-1; render(); }
      else if (state.family || state.players || state.difficulty) clear.click();
      else query.blur();
      return;
    }
    if (['INPUT','SELECT','TEXTAREA'].includes(event.target.tagName) && !typing) return;
    const cards = [...grid.querySelectorAll('.game-card')];
    if (event.key.startsWith('Arrow') && cards.length) {
      event.preventDefault();
      const columns = getComputedStyle(grid).gridTemplateColumns.split(' ').length;
      if (state.selected < 0) state.selected = 0;
      else if (event.key === 'ArrowRight') state.selected = Math.min(cards.length-1,state.selected+1);
      else if (event.key === 'ArrowLeft') state.selected = Math.max(0,state.selected-1);
      else if (event.key === 'ArrowDown') state.selected = Math.min(cards.length-1,state.selected+columns);
      else state.selected = Math.max(0,state.selected-columns);
      paintSelection();
    } else if (event.key === 'Enter' && state.selected >= 0) {
      event.preventDefault(); cards[state.selected]?.click();
    } else if (!typing && !event.metaKey && !event.ctrlKey && !event.altKey && event.key.length === 1) {
      event.preventDefault(); query.focus(); state.q += event.key; query.value = state.q; query.setSelectionRange(query.value.length,query.value.length); state.selected=-1; render();
    }
  });
  render();
  if (state.scroll) requestAnimationFrame(() => scrollTo(0,state.scroll));
  if (sessionStorage.getItem('pkcards-focus-search')) { sessionStorage.removeItem('pkcards-focus-search'); query.focus(); }
} else {
  document.getElementById('searchLink')?.addEventListener('click', () => sessionStorage.setItem('pkcards-focus-search','1'));
  document.addEventListener('keydown', event => {
    if (!event.metaKey && !event.ctrlKey && !event.altKey && event.key.length === 1 && !['INPUT','TEXTAREA'].includes(event.target.tagName)) {
      sessionStorage.setItem(STORAGE, JSON.stringify({q:event.key,family:'',players:'',difficulty:'',selected:-1}));
      location.href = '?';
    }
  });
}
</script>
</body>
</html>
