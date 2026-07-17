/* ================================================================
   PKcards — App logic
   Swipe through card games, Tinder-style
   ================================================================ */

// --- State ---
const S = {
  games: [],
  filter: 'all',
  currentIndex: 0,
  history: [],
  favorites: new Set(JSON.parse(localStorage.getItem('pk-fav') || '[]')),
  animating: false,
};

// --- Pointer tracking ---
const P = { active: false, card: null, x0: 0, y0: 0, dx: 0, dy: 0 };

// --- DOM refs ---
const $ = id => document.getElementById(id);
const stack = $('cardStack');
const stage = $('stage');

// --- Init ---
function init() {
  S.games = GAMES;
  buildFilterList();
  applyFilter('all');
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

  // Sheets
  document.querySelectorAll('[data-close]').forEach(el => {
    el.onclick = closeAllSheets;
  });

  // Keyboard
  document.addEventListener('keydown', e => {
    if (anySheetOpen()) return;
    if (e.key === 'ArrowLeft') swipeFromButton(-1);
    if (e.key === 'ArrowRight') swipeFromButton(1);
    if (e.key === 'ArrowUp' || e.key === 'i') openDetail(currentGame()?.id);
  });

  updateFavCount();
}

// --- Filtering ---
function getFiltered() {
  if (S.filter === 'all') return S.games;
  return S.games.filter(g => g.category === S.filter);
}

function applyFilter(cat) {
  S.filter = cat;
  S.currentIndex = 0;
  S.history = [];
  const label = cat === 'all' ? 'Tous' : CATEGORY_INFO[cat]?.label || cat;
  $('filterLabel').textContent = label;
  renderStack();
  closeAllSheets();
}

function buildFilterList() {
  const list = $('filterList');
  const cats = [
    { key: 'all', label: 'Tous les jeux', color: 'var(--accent)', count: S.games.length },
    ...Object.entries(CATEGORY_INFO).map(([key, info]) => ({
      key, label: info.label, color: info.color, count: info.count
    }))
  ];
  list.innerHTML = cats.map(c => `
    <button class="filter-item ${c.key === S.filter ? 'filter-item--active' : ''}" data-cat="${c.key}">
      <span class="filter-item__color" style="background:${c.color}"></span>
      <span class="filter-item__label">${c.label}</span>
      <span class="filter-item__count">${c.count}</span>
    </button>
  `).join('');
  list.querySelectorAll('.filter-item').forEach(btn => {
    btn.onclick = () => applyFilter(btn.dataset.cat);
  });
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

// --- Create a card element ---
function createCard(game, depth) {
  const card = document.createElement('div');
  card.className = 'card';
  card.dataset.depth = depth;
  card.dataset.gameId = game.id;
  card.style.setProperty('--card-color', game.color || 'var(--accent)');

  const sectionsHTML = game.sections.slice(0, 5).map(s =>
    `<div class="card__section-item">${escapeHtml(s)}</div>`
  ).join('');

  card.innerHTML = `
    <div class="card__accent"></div>
    <div class="card__stamp card__stamp--like">J'aime</div>
    <div class="card__stamp card__stamp--pass">Non</div>
    <div class="card__body">
      <h2 class="card__title">${escapeHtml(game.title)}</h2>
      <div class="card__badges">
        <span class="badge badge--players">
          <span class="badge__dot"></span>
          ${escapeHtml(game.players)}
        </span>
        <span class="badge badge--cards">${escapeHtml(game.cards)}</span>
      </div>
      <div class="card__goal">
        <div class="card__goal-label">But du jeu</div>
        <div class="card__goal-text">${escapeHtml(game.goal)}</div>
      </div>
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
    S.history.push({ id: card.dataset.gameId, dir, filter: S.filter });
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
  if (last.filter !== S.filter) return;
  S.currentIndex = Math.max(0, S.currentIndex - 1);
  renderStack();
}

// --- Restart ---
function restart() {
  S.currentIndex = 0;
  S.history = [];
  renderStack();
}

// --- Favorites ---
function toggleFavorite(id, add) {
  if (add !== undefined) {
    if (add) S.favorites.add(id);
    else S.favorites.delete(id);
  } else {
    S.favorites.has(id) ? S.favorites.delete(id) : S.favorites.add(id);
  }
  localStorage.setItem('pk-fav', JSON.stringify([...S.favorites]));
  updateFavCount();
}

function updateFavCount() {
  $('favCount').textContent = S.favorites.size;
}

// --- Detail sheet ---
function openDetail(id) {
  const game = S.games.find(g => g.id === id);
  if (!game) return;

  const isFav = S.favorites.has(id);

  $('detailBody').innerHTML = `
    <div class="detail-header" style="--card-color:${game.color || 'var(--accent)'}">
      <h1>${escapeHtml(game.title)}</h1>
      <div class="detail-badges">
        <span class="badge badge--players" style="--card-color:${game.color}">
          <span class="badge__dot"></span>${escapeHtml(game.players)}
        </span>
        <span class="badge badge--cards">${escapeHtml(game.cards)}</span>
        <button class="badge ${isFav ? 'badge--is-fav' : ''}" id="detailFavBtn"
          style="cursor:pointer;background:${isFav ? 'var(--like)' : 'var(--surface-2)'};
                 color:${isFav ? '#fff' : 'var(--text-2)'};border:none;font-family:inherit">
          ${isFav ? '♥ Favori' : '♡ Ajouter'}
        </button>
      </div>
    </div>
    <div class="markdown">${marked.parse(stripMeta(game.markdown))}</div>
  `;

  $('detailFavBtn').onclick = () => {
    toggleFavorite(id);
    openDetail(id);
  };

  $('detailSheet').hidden = false;
  document.body.style.overflow = 'hidden';
}

// --- Favorites sheet ---
function openFavorites() {
  const favs = [...S.favorites]
    .map(id => S.games.find(g => g.id === id))
    .filter(Boolean);

  const grid = $('favGrid');
  if (favs.length === 0) {
    grid.innerHTML = `
      <div class="fav-empty">
        <div class="fav-empty__icon">🃏</div>
        <p>Swipe à droite (♥) pour ajouter des jeux à vos favoris</p>
      </div>
    `;
  } else {
    grid.innerHTML = favs.map(g => `
      <div class="fav-card" style="--card-color:${g.color || 'var(--accent)'}" data-id="${g.id}">
        <div class="fav-card__icon">🃏</div>
        <div class="fav-card__info">
          <div class="fav-card__name">${escapeHtml(g.title)}</div>
          <div class="fav-card__meta">${escapeHtml(g.players)} · ${escapeHtml(g.cards)}</div>
        </div>
        <button class="fav-card__remove" data-remove="${g.id}" aria-label="Retirer">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M6 6l12 12M18 6L6 18"/></svg>
        </button>
      </div>
    `).join('');

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
  }

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
function closeAllSheets() {
  $('detailSheet').hidden = true;
  $('favSheet').hidden = true;
  $('filterSheet').hidden = true;
  document.body.style.overflow = '';
}

function anySheetOpen() {
  return !$('detailSheet').hidden || !$('favSheet').hidden || !$('filterSheet').hidden;
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

// --- Boot ---
init();
