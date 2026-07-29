/* ================================================================
   PKcards — App logic
   Swipe through card games, Tinder-style
   ================================================================ */

// --- State ---
const S = {
  games: [],
  filters: { players: new Set(), cards: new Set(), types: new Set() },
  favFilters: { players: new Set(), cards: new Set(), types: new Set() },
  currentIndex: 0,
  history: [],
  email: localStorage.getItem('pk-email') || null,
  favorites: new Set(JSON.parse(localStorage.getItem('pk-fav-cache') || '[]')),
  votes: {},          // game_id -> count (best-effort, depuis le serveur)
  animating: false,
  currentDetailId: null,
};

// --- Filter facets ---
const PLAYER_BUCKETS = [
  { key: 'solo', label: 'Solo',       min: 1, max: 1 },
  { key: '2',    label: '2 joueurs',  min: 2, max: 2 },
  { key: '3-4',  label: '3–4',        min: 3, max: 4 },
  { key: '5+',   label: '5 et +',     min: 5, max: 99 },
];
const CARD_BUCKETS = [
  { key: '32',     label: '32 cartes' },
  { key: '52',     label: '52 cartes' },
  { key: '2decks', label: '2 jeux (104+)' },
  { key: 'tarot',  label: 'Tarot (78)' },
  { key: 'autre',  label: 'Autre' },
];

// Which deck "tokens" a game's card string matches (a game can match several)
function deckTokens(cards) {
  const c = cards || '';
  const t = new Set();
  if (/104|108|deux jeux|2\s*(?:jeux|×|x)\s*(?:de\s*)?5[24]/i.test(c)) { t.add('2decks'); return t; }
  if (/78|tarot/i.test(c)) { t.add('tarot'); return t; }
  if (/\b32\b/.test(c)) t.add('32');
  if (/\b52\b/.test(c)) t.add('52');
  if (!t.size) t.add('autre');
  return t;
}

// All distinct type tags across the catalogue (types are comma-separated)
function allTypeTags() {
  const s = new Set();
  S.games.forEach(g => {
    if (g.type && g.type !== 'Non renseigné') {
      g.type.split(',').forEach(t => { const v = t.trim(); if (v) s.add(v); });
    }
  });
  return [...s].sort((a, b) => a.localeCompare(b, 'fr'));
}

function activeFilterCount() {
  const f = S.filters;
  return f.players.size + f.cards.size + f.types.size;
}

function filterSig() {
  const f = S.filters;
  return [...f.players].sort().join(',') + '|' + [...f.cards].sort().join(',') + '|' + [...f.types].sort().join(',');
}

function gameMatchesFilters(g, filters = S.filters) {
  const f = filters;
  if (f.players.size) {
    const ok = [...f.players].some(k => {
      const b = PLAYER_BUCKETS.find(x => x.key === k);
      return b && g.playerMin <= b.max && g.playerMax >= b.min;
    });
    if (!ok) return false;
  }
  if (f.cards.size) {
    const toks = deckTokens(g.cards);
    if (![...f.cards].some(k => toks.has(k))) return false;
  }
  if (f.types.size) {
    const tags = (g.type || '').split(',').map(s => s.trim());
    if (![...f.types].some(k => tags.includes(k))) return false;
  }
  return true;
}

// Which facet options actually appear in a given set of games
function facetOptions(games) {
  const players = PLAYER_BUCKETS.filter(b => games.some(g => g.playerMin <= b.max && g.playerMax >= b.min));
  const cards = CARD_BUCKETS.filter(b => games.some(g => deckTokens(g.cards).has(b.key)));
  const typeSet = new Set();
  games.forEach(g => {
    if (g.type && g.type !== 'Non renseigné') g.type.split(',').forEach(t => { const v = t.trim(); if (v) typeSet.add(v); });
  });
  const types = [...typeSet].sort((a, b) => a.localeCompare(b, 'fr'));
  return { players, cards, types };
}

// --- Pointer tracking ---
const P = { active: false, card: null, x0: 0, y0: 0, dx: 0, dy: 0 };

// --- DOM refs ---
const $ = id => document.getElementById(id);
const stack = $('cardStack');
const stage = $('stage');

// --- Backend API (PHP + SQLite) ---
const API = './api.php';

async function apiGet(action, params = {}) {
  const qs = new URLSearchParams({ action, ...params }).toString();
  const res = await fetch(`${API}?${qs}`, { headers: { Accept: 'application/json' } });
  if (!res.ok) throw Object.assign(new Error('http'), { status: res.status });
  return res.json();
}

async function apiPost(action, body = {}) {
  const res = await fetch(`${API}?action=${encodeURIComponent(action)}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  });
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw Object.assign(new Error(data.error || 'http'), { status: res.status, data });
  return data;
}

// --- Init ---
function init() {
  S.games = GAMES;
  buildFilterList();
  updateFilterLabel();
  renderStack();

  // Controls
  $('passBtn').onclick   = () => swipeFromButton(-1);
  $('likeBtn').onclick   = () => swipeFromButton(1);
  $('infoBtn').onclick   = () => openDetail(currentGame()?.id);
  $('rewindBtn').onclick = rewind;
  $('restartBtn').onclick = restart;
  $('viewFavBtn').onclick = openFavorites;

  // Header
  $('filterBtn').onclick = openFilter;
  $('favBtn').onclick    = openFavorites;
  $('searchBtn').onclick = openSearch;
  $('topBtn').onclick    = openTop;

  // Search
  $('searchInput').addEventListener('input', e => renderSearchResults(e.target.value));

  // Email sheet (identité favoris)
  $('emailSaveBtn').onclick = submitEmail;
  $('emailInput').addEventListener('keydown', e => { if (e.key === 'Enter') submitEmail(); });

  // Sheets
  document.querySelectorAll('[data-close]').forEach(el => {
    el.onclick = () => closeAllSheets();
  });

  // Keyboard
  document.addEventListener('keydown', e => {
    if (anySheetOpen()) return;
    if (e.key === 'ArrowLeft') swipeFromButton(-1);
    if (e.key === 'ArrowRight') swipeFromButton(1);
    if (e.key === 'ArrowUp' || e.key === 'i') openDetail(currentGame()?.id);
  });

  updateFavCount();
  syncFavorites();

  // Deep-link routing: open a game from #<id> on load, and react to hash changes
  window.addEventListener('hashchange', handleHashRoute);
  handleHashRoute();
}

// --- Hash routing (#<gameId>) ---
function handleHashRoute() {
  const id = decodeURIComponent(location.hash.replace(/^#/, '')).trim();
  if (id && S.games.some(g => g.id === id)) {
    if (!$('detailSheet').hidden && S.currentDetailId === id) return;
    openDetail(id, true);
  } else if (!id && !$('detailSheet').hidden) {
    closeAllSheets(true);
  }
}

// --- Filtering ---
function getFiltered() {
  if (activeFilterCount() === 0) return S.games;
  return S.games.filter(gameMatchesFilters);
}

function updateFilterLabel() {
  const n = activeFilterCount();
  $('filterLabel').textContent = n === 0 ? 'Tous' : `Filtres (${n})`;
}

function toggleFacet(group, key) {
  const set = S.filters[group];
  set.has(key) ? set.delete(key) : set.add(key);
  S.currentIndex = 0;
  S.history = [];
  updateFilterLabel();
  buildFilterList();
  renderStack();
}

function resetFilters() {
  S.filters.players.clear();
  S.filters.cards.clear();
  S.filters.types.clear();
  S.currentIndex = 0;
  S.history = [];
  updateFilterLabel();
  buildFilterList();
  renderStack();
}

function chipRow(group, buckets) {
  return buckets.map(b => {
    const active = S.filters[group].has(b.key);
    return `<button class="facet-chip ${active ? 'facet-chip--on' : ''}" data-group="${group}" data-key="${b.key}">${escapeHtml(b.label)}</button>`;
  }).join('');
}

function buildFilterList() {
  const list = $('filterList');
  const typeButtons = allTypeTags().map(t =>
    `<button class="facet-chip ${S.filters.types.has(t) ? 'facet-chip--on' : ''}" data-group="types" data-key="${escapeHtml(t)}">${escapeHtml(t)}</button>`
  ).join('');

  const count = getFiltered().length;

  list.innerHTML = `
    <div class="facet-group">
      <div class="facet-group__label">Nombre de joueurs</div>
      <div class="facet-chips">${chipRow('players', PLAYER_BUCKETS)}</div>
    </div>
    <div class="facet-group">
      <div class="facet-group__label">Jeu de cartes</div>
      <div class="facet-chips">${chipRow('cards', CARD_BUCKETS)}</div>
    </div>
    <div class="facet-group">
      <div class="facet-group__label">Type de jeu</div>
      <div class="facet-chips">${typeButtons}</div>
    </div>
    <div class="facet-actions">
      <button class="btn btn--ghost" id="filterReset">Réinitialiser</button>
      <button class="btn btn--primary" id="filterApply">Voir ${count} jeu${count > 1 ? 'x' : ''}</button>
    </div>
  `;

  list.querySelectorAll('.facet-chip').forEach(btn => {
    btn.onclick = () => toggleFacet(btn.dataset.group, btn.dataset.key);
  });
  $('filterReset').onclick = resetFilters;
  $('filterApply').onclick = () => closeAllSheets();
}

// --- Current game ---
function currentGame() {
  const list = getFiltered();
  return list[S.currentIndex];
}

// --- Render card stack ---
function renderStack() {
  const list = getFiltered();
  const total = list.length;

  $('totalIdx').textContent = total;
  $('curIdx').textContent = Math.min(S.currentIndex + 1, total);
  $('progressBar').style.width = `${total ? (Math.min(S.currentIndex, total) / total) * 100 : 0}%`;

  // Empty state
  if (S.currentIndex >= total) {
    stack.innerHTML = '';
    $('emptyState').hidden = false;
    $('dock').style.opacity = '.3';
    $('dock').style.pointerEvents = 'none';
    $('likedCount').textContent = S.favorites.size;
    return;
  }
  $('emptyState').hidden = true;
  $('dock').style.opacity = '';
  $('dock').style.pointerEvents = '';

  stack.innerHTML = '';
  const visible = list.slice(S.currentIndex, S.currentIndex + 3);

  visible.reverse().forEach((game, i) => {
    const depth = visible.length - 1 - i;
    const card = createCard(game, depth);
    if (depth === 0) card.classList.add('card--enter');
    stack.appendChild(card);
  });

  setupSwipe(stack.querySelector('.card[data-depth="0"]'));
}

// --- Formatting utils ---
function cleanAliases(aliases, title) {
  if (!aliases) return '';
  const parts = aliases.split(',').map(s => s.trim()).filter(Boolean);
  const seen = new Set([normalize(title)]);
  const kept = [];
  for (const p of parts) {
    const n = normalize(p);
    if (!n || seen.has(n)) continue;
    seen.add(n);
    kept.push(p);
  }
  return kept.join(', ');
}

function formatPlayers(p) {
  if (!p) return '';
  if (/joueur/i.test(p)) return p;
  const unit = /^1(\D|$)/.test(p.trim()) ? 'joueur' : 'joueurs';
  return `${p} ${unit}`;
}

function formatCards(c) {
  if (!c) return '';
  return /carte/i.test(c) ? c : `${c} cartes`;
}

const SUIT_MAP = [
  { re: /(c(?:œu|oeu)rs?)/gi,  sym: '♥', cls: 'suit--red' },
  { re: /(carreaux?)/gi,       sym: '♦', cls: 'suit--red' },
  { re: /(tr[èe]fles?)/gi,     sym: '♣', cls: 'suit--black' },
  { re: /(piques?)/gi,         sym: '♠', cls: 'suit--black' },
];

function colorizeSuits(html) {
  let out = html;
  for (const { re, sym, cls } of SUIT_MAP) {
    out = out.replace(re, `$1\u202F<span class="suit ${cls}">${sym}</span>`);
  }
  return out;
}

// --- Create a card element ---
function createCard(game, depth) {
  const card = document.createElement('div');
  card.className = 'card';
  card.dataset.depth = depth;
  card.dataset.gameId = game.id;
  card.style.setProperty('--card-color', game.color || 'var(--accent)');

  const headings = gameSections(game.markdown);
  const sections = (headings.length ? headings : game.sections).slice(0, 7);
  const sectionsHTML = sections.map(s =>
    `<div class="card__section-item">${escapeHtml(s)}</div>`
  ).join('');

  const teaser = (game.excerpt || '').trim();

  card.innerHTML = `
    <div class="card__accent"></div>
    <div class="card__stamp card__stamp--like">J'aime</div>
    <div class="card__stamp card__stamp--pass">Non</div>
    <div class="card__body">
      <h2 class="card__title">${escapeHtml(game.title)}</h2>
      ${cleanAliases(game.aliases, game.title) ? `<div class="card__aliases">${escapeHtml(cleanAliases(game.aliases, game.title))}</div>` : ''}
      <div class="card__badges">
        <span class="badge badge--players">
          <span class="badge__ico">👥</span>${escapeHtml(formatPlayers(game.players))}
        </span>
        <span class="badge badge--cards"><span class="badge__ico">🂠</span>${escapeHtml(formatCards(game.cards))}</span>
        ${game.difficulty !== 'Non renseignée' ? `<span class="badge">${escapeHtml(game.difficulty)}</span>` : ''}
        ${game.type !== 'Non renseigné' ? `<span class="badge">${escapeHtml(game.type)}</span>` : ''}
      </div>
      <div class="card__goal">
        <div class="card__goal-label">But du jeu</div>
        <div class="card__goal-text">${escapeHtml(game.goal)}</div>
      </div>
      ${teaser ? `
        <div class="card__teaser">
          <div class="card__teaser-label">En bref</div>
          <p class="card__teaser-text">${escapeHtml(teaser)}</p>
        </div>
      ` : ''}
      ${sectionsHTML ? `
        <div>
          <div class="card__sections-label" style="margin-bottom:8px">Au programme</div>
          <div class="card__sections">${sectionsHTML}</div>
        </div>
      ` : ''}
      <div class="card__hint">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
        Toucher pour les règles complètes
      </div>
    </div>
  `;
  return card;
}

// --- Swipe (Pointer Events) ---
function setupSwipe(card) {
  if (!card) return;

  card.addEventListener('pointerdown', onPointerDown);
}

function onPointerDown(e) {
  if (S.animating) return;
  const card = e.currentTarget;
  if (card.dataset.depth !== '0') return;

  P.active = true;
  P.card = card;
  P.x0 = e.clientX;
  P.y0 = e.clientY;
  P.dx = 0;
  P.dy = 0;

  card.style.transition = 'none';
  card.setPointerCapture(e.pointerId);

  card.addEventListener('pointermove', onPointerMove);
  card.addEventListener('pointerup', onPointerUp);
  card.addEventListener('pointercancel', onPointerUp);
}

function onPointerMove(e) {
  if (!P.active) return;
  P.dx = e.clientX - P.x0;
  P.dy = e.clientY - P.y0;

  const rot = P.dx * 0.04;
  P.card.style.transform = `translate(${P.dx}px, ${P.dy}px) rotate(${rot}deg)`;

  // Stamps
  const likeStamp = P.card.querySelector('.card__stamp--like');
  const passStamp = P.card.querySelector('.card__stamp--pass');
  const t = 100;
  if (P.dx > 0) {
    likeStamp.style.opacity = Math.min(1, P.dx / t);
    passStamp.style.opacity = 0;
  } else {
    passStamp.style.opacity = Math.min(1, -P.dx / t);
    likeStamp.style.opacity = 0;
  }
}

function onPointerUp(e) {
  if (!P.active) return;
  P.active = false;
  const card = P.card;
  card.removeEventListener('pointermove', onPointerMove);
  card.removeEventListener('pointerup', onPointerUp);
  card.removeEventListener('pointercancel', onPointerUp);

  const dist = Math.hypot(P.dx, P.dy);
  const threshold = 110;

  // Tap detection
  if (dist < 8) {
    snapBack(card);
    openDetail(card.dataset.gameId);
    return;
  }

  if (Math.abs(P.dx) > threshold) {
    const dir = P.dx > 0 ? 1 : -1;
    flyAway(card, dir);
  } else {
    snapBack(card);
  }
}

function snapBack(card) {
  card.style.transition = '';
  card.style.transform = '';
  card.querySelector('.card__stamp--like').style.opacity = '';
  card.querySelector('.card__stamp--pass').style.opacity = '';
}

function flyAway(card, dir) {
  S.animating = true;
  const flyX = dir * (window.innerWidth + 100);
  const flyY = P.dy * 2;

  card.style.transition = 'transform .45s ease-out, opacity .45s ease-out';
  card.style.transform = `translate(${flyX}px, ${flyY}px) rotate(${dir * 35}deg)`;
  card.style.opacity = '0';

  if (dir > 0) toggleFavorite(card.dataset.gameId, true);

  if (navigator.vibrate) navigator.vibrate(20);

  setTimeout(() => {
    S.animating = false;
    S.history.push({ id: card.dataset.gameId, dir, sig: filterSig() });
    S.currentIndex++;
    renderStack();
  }, 430);
}

// --- Button-triggered swipe ---
function swipeFromButton(dir) {
  if (S.animating) return;
  const card = stack.querySelector('.card[data-depth="0"]');
  if (!card) return;

  P.dx = dir * 200;
  P.dy = 0;
  flyAway(card, dir);
}

// --- Rewind ---
function rewind() {
  if (S.history.length === 0 || S.animating) return;
  const last = S.history.pop();
  if (last.sig !== filterSig()) return;
  S.currentIndex = Math.max(0, S.currentIndex - 1);
  renderStack();
}

// --- Restart ---
function restart() {
  S.currentIndex = 0;
  S.history = [];
  renderStack();
}

// --- Favorites (local-first, sync serveur optionnelle via email) ---
function cacheFavorites() {
  localStorage.setItem('pk-fav-cache', JSON.stringify([...S.favorites]));
}

async function syncFavorites() {
  if (!S.email) return;
  try {
    // Pousse les favoris locaux non encore connus du serveur, puis récupère la liste fusionnée
    const local = [...S.favorites];
    let list = await apiGet('favorites', { email: S.email });
    const remote = new Set(list);
    const toPush = local.filter(id => !remote.has(id));
    for (const id of toPush) {
      try { list = (await apiPost('fav_add', { email: S.email, game: id })).favorites; } catch { /* ignore */ }
    }
    S.favorites = new Set(list);
    cacheFavorites();
    updateFavCount();
  } catch { /* garde le cache local en cas d'indisponibilité */ }
}

// Bascule un favori : effet immédiat en local, puis synchro serveur si email connu.
function toggleFavorite(id, add) {
  const shouldAdd = add !== undefined ? add : !S.favorites.has(id);
  shouldAdd ? S.favorites.add(id) : S.favorites.delete(id);
  cacheFavorites();
  updateFavCount();
  refreshDetailFav(id);
  if (S.email) pushFavorite(id, shouldAdd);
}

async function pushFavorite(id, shouldAdd) {
  try {
    const data = await apiPost(shouldAdd ? 'fav_add' : 'fav_remove', { email: S.email, game: id });
    S.favorites = new Set(data.favorites);
    cacheFavorites();
    updateFavCount();
    refreshDetailFav(id);
  } catch { /* on garde l'état local, resynchro au prochain chargement */ }
}

function refreshDetailFav(id) {
  if (S.currentDetailId === id && !$('detailSheet').hidden) openDetail(id, true);
}

function updateFavCount() {
  $('favCount').textContent = S.favorites.size;
}

// --- Email (identité des favoris, sans mot de passe) ---
let emailCallback = null;

function requireEmail(cb) {
  emailCallback = cb || null;
  $('emailInput').value = S.email || '';
  $('emailSheet').hidden = false;
  document.body.style.overflow = 'hidden';
  setTimeout(() => $('emailInput').focus(), 60);
}

async function submitEmail() {
  const val = ($('emailInput').value || '').trim().toLowerCase();
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) { showToast('Email invalide'); return; }
  S.email = val;
  localStorage.setItem('pk-email', val);
  $('emailSheet').hidden = true;
  document.body.style.overflow = '';
  await syncFavorites();
  const cb = emailCallback; emailCallback = null;
  if (cb) await cb();
}

// --- Votes ---
async function voteGame(id) {
  try {
    const data = await apiPost('vote', { game: id });
    S.votes[id] = data.count;
    showToast('Vote enregistré ✓');
    const btn = $('detailVoteBtn');
    if (btn && S.currentDetailId === id) {
      const c = btn.querySelector('.vote-count');
      if (c) c.textContent = data.count;
    }
  } catch (e) {
    if (e.status === 429) showToast('Doucement ! Réessayez dans un instant');
    else showToast('Serveur indisponible');
  }
}

// --- Top jeux (classement par votes) ---
async function openTop() {
  $('topSheet').hidden = false;
  document.body.style.overflow = 'hidden';
  const box = $('topList');
  box.innerHTML = '<div class="fav-empty"><p>Chargement…</p></div>';
  try {
    const rows = await apiGet('top', { limit: '100' });
    rows.forEach(r => { S.votes[r.game_id] = r.count; });
    renderTop(rows);
  } catch {
    box.innerHTML = '<div class="fav-empty"><div class="fav-empty__icon">📡</div><p>Classement indisponible (serveur PHP requis)</p></div>';
  }
}

function renderTop(rows) {
  const box = $('topList');
  const items = rows
    .map(r => ({ game: S.games.find(g => g.id === r.game_id), count: r.count }))
    .filter(x => x.game);
  if (!items.length) {
    box.innerHTML = '<div class="fav-empty"><div class="fav-empty__icon">🏆</div><p>Aucun vote pour le moment. Soyez le premier à voter !</p></div>';
    return;
  }
  box.innerHTML = items.map((x, i) => `
    <button class="top-item" style="--card-color:${x.game.color || 'var(--accent)'}" data-id="${x.game.id}">
      <span class="top-item__rank">${i + 1}</span>
      <span class="top-item__info">
        <span class="top-item__name">${escapeHtml(x.game.title)}</span>
        <span class="top-item__meta">${escapeHtml(x.game.players)} · ${escapeHtml(x.game.cards)}</span>
      </span>
      <span class="top-item__votes">${x.count} ${x.count > 1 ? 'votes' : 'vote'}</span>
    </button>
  `).join('');
  box.querySelectorAll('.top-item').forEach(btn => {
    btn.onclick = () => { $('topSheet').hidden = true; openDetail(btn.dataset.id); };
  });
}

// --- Detail sheet ---
function openDetail(id, fromHash) {
  const game = S.games.find(g => g.id === id);
  if (!game) return;

  S.currentDetailId = id;
  if (!fromHash && location.hash !== '#' + id) {
    location.hash = id;
  }

  const isFav = S.favorites.has(id);

  const ytQuery = encodeURIComponent(game.title + ' règles jeu de cartes');
  const ytUrl = `https://www.youtube.com/results?search_query=${ytQuery}`;

  const aliasList = cleanAliases(game.aliases, game.title);
  const aliasesHTML = aliasList
    ? `<div class="detail-aliases">
         <span class="detail-aliases__label">Aussi appelé :</span>
         ${escapeHtml(aliasList)}
       </div>`
    : '';

  $('detailBody').innerHTML = `
    <div class="detail-header" style="--card-color:${game.color || 'var(--accent)'}">
      <h1>${escapeHtml(game.title)}</h1>
      ${aliasesHTML}
      <div class="detail-badges">
        <span class="badge badge--players" style="--card-color:${game.color}">
          <span class="badge__ico">👥</span>${escapeHtml(formatPlayers(game.players))}
        </span>
        <span class="badge badge--cards"><span class="badge__ico">🂠</span>${escapeHtml(formatCards(game.cards))}</span>
        <button class="badge ${isFav ? 'badge--is-fav' : ''}" id="detailFavBtn"
          style="cursor:pointer;background:${isFav ? 'var(--like)' : 'var(--surface-2)'};
                 color:${isFav ? '#fff' : 'var(--text-2)'};border:none;font-family:inherit">
          ${isFav ? '♥ Favori' : '♡ Ajouter'}
        </button>
        <button class="badge badge--share" id="detailShareBtn"
          style="cursor:pointer;border:none;font-family:inherit"
          aria-label="Partager ce jeu">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.6 13.5l6.8 4M15.4 6.5l-6.8 4"/></svg>
          Partager
        </button>
        <button class="badge badge--vote" id="detailVoteBtn"
          style="cursor:pointer;border:none;font-family:inherit"
          aria-label="Voter pour ce jeu">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l2.9 6.3 6.9.7-5.1 4.6 1.4 6.8L12 17.8 5.9 20.4l1.4-6.8L2.2 9l6.9-.7z"/></svg>
          Voter <span class="vote-count">${S.votes[id] || 0}</span>
        </button>
      </div>
      <div class="detail-summary">
        <div class="detail-summary__label">Règle courte</div>
        <div class="detail-summary__goal">${escapeHtml(game.goal)}</div>
      </div>
      <a href="${ytUrl}" target="_blank" rel="noopener" class="detail-yt-btn">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.5 12 3.5 12 3.5s-7.5 0-9.4.6A3 3 0 0 0 .5 6.2C0 8.1 0 12 0 12s0 3.9.5 5.8a3 3 0 0 0 2.1 2.1c1.9.6 9.4.6 9.4.6s7.5 0 9.4-.6a3 3 0 0 0 2.1-2.1c.5-1.9.5-5.8.5-5.8s0-3.9-.5-5.8zM9.5 15.5v-7l6 3.5-6 3.5z"/></svg>
        Voir les règles sur YouTube
      </a>
    </div>
    <div class="detail-long-label">Version longue</div>
    <div class="markdown">${colorizeSuits(marked.parse(stripMeta(game.markdown)))}</div>
  `;

  $('detailFavBtn').onclick = () => { toggleFavorite(id); };

  $('detailShareBtn').onclick = () => shareGame(game);

  $('detailVoteBtn').onclick = () => voteGame(id);

  $('detailSheet').hidden = false;
  document.body.style.overflow = 'hidden';
}

// --- Share ---
async function shareGame(game) {
  const url = `${location.origin}${location.pathname}#${game.id}`;
  const shareData = {
    title: `${game.title} — PKcards`,
    text: `Règles du jeu de cartes « ${game.title} » sur PKcards`,
    url,
  };
  if (navigator.share) {
    try { await navigator.share(shareData); return; }
    catch (e) { if (e && e.name === 'AbortError') return; }
  }
  try {
    await navigator.clipboard.writeText(url);
    showToast('Lien copié ✓');
  } catch {
    showToast(url);
  }
}

// --- Toast ---
let toastTimer = null;
function showToast(msg) {
  const el = $('toast');
  el.textContent = msg;
  el.hidden = false;
  el.classList.add('toast--show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => {
    el.classList.remove('toast--show');
    setTimeout(() => { el.hidden = true; }, 250);
  }, 2200);
}

// --- Search ---
function normalize(str) {
  return (str || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
}

function openSearch() {
  renderSearchResults('');
  $('searchSheet').hidden = false;
  document.body.style.overflow = 'hidden';
  setTimeout(() => $('searchInput').focus(), 60);
}

function renderSearchResults(query) {
  const q = normalize(query);
  const all = [...S.games].sort((a, b) => a.title.localeCompare(b.title, 'fr'));
  const list = q
    ? all.filter(g => normalize(g.title).includes(q) || normalize(g.aliases).includes(q))
    : all;
  const box = $('searchResults');

  if (list.length === 0) {
    box.innerHTML = `<div class="fav-empty"><div class="fav-empty__icon">🔍</div><p>Aucun jeu trouvé pour « ${escapeHtml(query)} »</p></div>`;
    return;
  }

  const countLabel = q
    ? `${list.length} résultat${list.length > 1 ? 's' : ''}`
    : `${list.length} jeux au catalogue`;

  const itemsHTML = list.slice(0, 200).map(g => `
    <button class="search-item" style="--card-color:${g.color || 'var(--accent)'}" data-id="${g.id}">
      <span class="search-item__name">${escapeHtml(g.title)}</span>
      <span class="search-item__meta">👥 ${escapeHtml(formatPlayers(g.players))} · 🂠 ${escapeHtml(formatCards(g.cards))}${g.type && g.type !== 'Non renseigné' ? ` · ${escapeHtml(g.type)}` : ''}</span>
    </button>
  `).join('');

  box.innerHTML = `<div class="search-count">${countLabel}</div>${itemsHTML}`;

  box.querySelectorAll('.search-item').forEach(btn => {
    btn.onclick = () => { $('searchSheet').hidden = true; openDetail(btn.dataset.id); };
  });
}

// --- Favorites sheet ---
function favFacetCount() {
  const f = S.favFilters;
  return f.players.size + f.cards.size + f.types.size;
}

function toggleFavFacet(group, key) {
  const set = S.favFilters[group];
  set.has(key) ? set.delete(key) : set.add(key);
  openFavorites();
}

function resetFavFilters() {
  S.favFilters.players.clear();
  S.favFilters.cards.clear();
  S.favFilters.types.clear();
  openFavorites();
}

function favFilterBar(allFavs) {
  if (allFavs.length < 2) return '';
  const opts = facetOptions(allFavs);
  const groups = [];
  const grp = (label, group, buckets, labelFn) => {
    if (buckets.length < 2) return;
    const chips = buckets.map(b => {
      const key = labelFn ? b : b.key;
      const text = labelFn ? b : b.label;
      const on = S.favFilters[group].has(key);
      return `<button class="facet-chip ${on ? 'facet-chip--on' : ''}" data-favgroup="${group}" data-key="${escapeHtml(key)}">${escapeHtml(text)}</button>`;
    }).join('');
    groups.push(`<div class="facet-chips facet-chips--fav">${chips}</div>`);
  };
  grp('Joueurs', 'players', opts.players, false);
  grp('Cartes', 'cards', opts.cards, false);
  grp('Type', 'types', opts.types, true);
  if (!groups.length) return '';
  const resetBtn = favFacetCount() ? `<button class="fav-sync__link" id="favFilterReset">réinitialiser</button>` : '';
  return `<div class="fav-filter">${groups.join('')}${resetBtn ? `<div class="fav-filter__reset">${resetBtn}</div>` : ''}</div>`;
}

function openFavorites() {
  const allFavs = [...S.favorites]
    .map(id => S.games.find(g => g.id === id))
    .filter(Boolean);

  const favs = allFavs.filter(g => gameMatchesFilters(g, S.favFilters));

  const syncHTML = S.email
    ? `<div class="fav-sync">☁ Synchronisés avec <b>${escapeHtml(S.email)}</b> · <button class="fav-sync__link" id="favSyncChange">changer</button></div>`
    : `<div class="fav-sync"><button class="fav-sync__link" id="favSyncSet">☁ Synchroniser mes favoris sur tous mes appareils</button></div>`;

  const grid = $('favGrid');

  if (allFavs.length === 0) {
    grid.innerHTML = `${syncHTML}
      <div class="fav-empty">
        <div class="fav-empty__icon">🃏</div>
        <p>Swipe à droite (♥) ou touche « Ajouter » pour garder un jeu ici</p>
      </div>
    `;
  } else {
    const filterHTML = favFilterBar(allFavs);
    const listHTML = favs.length === 0
      ? `<div class="fav-empty"><div class="fav-empty__icon">🔍</div><p>Aucun favori ne correspond à ce filtre</p></div>`
      : favs.map(g => `
        <div class="fav-card" style="--card-color:${g.color || 'var(--accent)'}" data-id="${g.id}">
          <div class="fav-card__icon">🃏</div>
          <div class="fav-card__info">
            <div class="fav-card__name">${escapeHtml(g.title)}</div>
            <div class="fav-card__meta">${escapeHtml(formatPlayers(g.players))} · ${escapeHtml(formatCards(g.cards))}</div>
          </div>
          <button class="fav-card__remove" data-remove="${g.id}" aria-label="Retirer">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M6 6l12 12M18 6L6 18"/></svg>
          </button>
        </div>
      `).join('');
    grid.innerHTML = syncHTML + filterHTML + listHTML;

    grid.querySelectorAll('.fav-card').forEach(el => {
      el.onclick = e => {
        if (e.target.closest('[data-remove]')) return;
        openDetail(el.dataset.id);
      };
    });
    grid.querySelectorAll('[data-remove]').forEach(btn => {
      btn.onclick = e => {
        e.stopPropagation();
        toggleFavorite(btn.dataset.remove);
        openFavorites();
      };
    });
    grid.querySelectorAll('[data-favgroup]').forEach(btn => {
      btn.onclick = () => toggleFavFacet(btn.dataset.favgroup, btn.dataset.key);
    });
    const favResetBtn = $('favFilterReset');
    if (favResetBtn) favResetBtn.onclick = resetFavFilters;
  }

  const setBtn = $('favSyncSet');
  if (setBtn) setBtn.onclick = () => requireEmail(() => openFavorites());
  const changeBtn = $('favSyncChange');
  if (changeBtn) changeBtn.onclick = () => requireEmail(() => openFavorites());

  $('favSheet').hidden = false;
  document.body.style.overflow = 'hidden';
}

// --- Filter sheet ---
function openFilter() {
  buildFilterList();
  $('filterSheet').hidden = false;
  document.body.style.overflow = 'hidden';
}

// --- Sheet management ---
function closeAllSheets(fromHash) {
  const detailWasOpen = !$('detailSheet').hidden;
  $('detailSheet').hidden = true;
  $('favSheet').hidden = true;
  $('filterSheet').hidden = true;
  $('searchSheet').hidden = true;
  $('topSheet').hidden = true;
  $('emailSheet').hidden = true;
  emailCallback = null;
  document.body.style.overflow = '';
  S.currentDetailId = null;
  if (detailWasOpen && !fromHash && location.hash) {
    history.replaceState(null, '', location.pathname + location.search);
  }
}

function anySheetOpen() {
  return !$('detailSheet').hidden || !$('favSheet').hidden || !$('filterSheet').hidden
      || !$('searchSheet').hidden || !$('topSheet').hidden || !$('emailSheet').hidden;
}

// Close on Escape
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') closeAllSheets();
});

// --- Utils ---
function escapeHtml(s) {
  if (!s) return '';
  return s
    .replace(/\*\*(.+?)\*\*/g, '$1')
    .replace(/\*(.+?)\*/g, '$1')
    .replace(/`(.+?)`/g, '$1')
    .replace(/&/g,'&amp;')
    .replace(/</g,'&lt;')
    .replace(/>/g,'&gt;')
    .replace(/"/g,'&quot;');
}

function stripMeta(md) {
  const firstSection = md.indexOf('## ');
  if (firstSection > 0) return md.substring(firstSection);
  return md;
}

// Isolate the "Règle courte" portion of a game's markdown
function shortRuleSection(md) {
  if (!md) return '';
  const start = md.indexOf('## Règle courte');
  if (start < 0) return '';
  let end = md.indexOf('## Version longue', start);
  if (end < 0) end = md.length;
  return md.slice(start, end);
}

const SECTION_BLOCKLIST = /^(règle courte|version longue|histoire|historique|but|but du jeu|conseils?|introduction|préambule)$/i;

// Real section titles for the card preview. Prefers the short-rule H3
// sub-headings; falls back to the document's H2 headings.
function gameSections(md) {
  if (!md) return [];
  let heads = [];
  const seg = shortRuleSection(md);
  if (seg) {
    const re = /^###\s+(.+?)\s*$/gm;
    let m;
    while ((m = re.exec(seg))) heads.push(m[1]);
  }
  if (!heads.length) {
    const re = /^##\s+(.+?)\s*$/gm;
    let m;
    while ((m = re.exec(md))) heads.push(m[1]);
  }
  return heads
    .map(h => h.replace(/\s*\(.*?\)\s*$/, '').trim())
    .filter(h => h && !SECTION_BLOCKLIST.test(h));
}

// --- Boot ---
init();
