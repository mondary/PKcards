#!/usr/bin/env node
/**
 * build.js — Parse all markdown files from rules/ and generate site/data.js
 * Usage: node scripts/build.js  (depuis la racine du dépôt)
 */
const fs = require('fs');
const path = require('path');

const dir = path.join(__dirname, '..', 'assets', 'rules');
const files = fs.readdirSync(dir).filter(f => f.endsWith('.md')).sort();

const CATEGORY_COLORS = {
  solo:         '#a78bfa',
  duo:          '#60a5fa',
  'petit-groupe': '#34d399',
  'grand-groupe': '#fb923c',
};

function parseGame(file, content) {
  const id = path.basename(file, '.md');

  const titleMatch = content.match(/^#\s+(.+)$/m);
  const title = titleMatch ? titleMatch[1].trim() : id;

  const players = (content.match(/\*\*Nombre de joueurs\s*:?\s*\*\*\s*(.+)/i) || [])[1]?.trim() || '';
  const cards   = (content.match(/\*\*Nombre de cartes\s*:?\s*\*\*\s*(.+)/i) || [])[1]?.trim() || '';
  const goal    = (content.match(/\*\*But\s*:?\s*\*\*\s*(.+)/i) || [])[1]?.trim() || '';
  const aliases = (content.match(/\*\*Autres noms\s*:?\s*\*\*\s*(.+)/i) || [])[1]?.trim() || '';
  const difficulty = (content.match(/\*\*Difficulté\s*:?\s*\*\*\s*(.+)/i) || [])[1]?.trim() || 'Non renseignée';
  const type = (content.match(/\*\*Type\s*:?\s*\*\*\s*(.+)/i) || [])[1]?.trim() || 'Non renseigné';

  // Parse player numbers
  const pNums = players.match(/\d+/g);
  let playerMin = pNums ? parseInt(pNums[0]) : 0;
  let playerMax = pNums ? parseInt(pNums[pNums.length - 1]) : playerMin;

  // Parse card count
  const cNum = cards.match(/\d+/);
  const cardCount = cNum ? parseInt(cNum[0]) : 0;

  // Category
  let category = 'grand-groupe';
  if (playerMin >= 1 && playerMax <= 1) category = 'solo';
  else if (playerMax <= 2) category = 'duo';
  else if (playerMax <= 4) category = 'petit-groupe';

  // Sections
  const rawSections = content.split(/^## /m).slice(1);
  const sections = rawSections.map(s => {
    const lines = s.split('\n');
    return { title: lines[0].trim(), body: lines.slice(1).join('\n').trim() };
  });

  // Excerpt — skip Histoire/Adaptation, take first useful section
  let excerpt = '';
  const useful = sections.find(s =>
    !/histoire|adaptation/i.test(s.title)
  );
  if (useful) {
    excerpt = useful.body
      .replace(/\*\*/g, '')
      .replace(/^#{1,6}\s+/gm, '')
      .replace(/\[.*?\]\(.*?\)/g, '')
      .replace(/^\|.*$/gm, '')
      .replace(/^---.*$/gm, '')
      .replace(/^\s*[-•]\s+/gm, '')
      .split('\n')
      .map(l => l.trim())
      .filter(Boolean)
      .slice(0, 3)
      .join(' ');
    if (excerpt.length > 220) excerpt = excerpt.substring(0, 220) + '…';
  }

  return {
    id, title, players, cards, difficulty, type, goal, aliases,
    playerMin, playerMax, cardCount,
    category, color: CATEGORY_COLORS[category],
    sections: sections.map(s => s.title),
    excerpt,
    markdown: content,
  };
}

const games = files.map(f => parseGame(f, fs.readFileSync(path.join(dir, f), 'utf-8')));

const counts = games.reduce((acc, g) => { acc[g.category] = (acc[g.category]||0)+1; return acc; }, {});

  const output = `// Auto-generated from assets/rules/*.md — do not edit manually
// ${games.length} games — run 'node build.js' to regenerate
const GAMES = ${JSON.stringify(games, null, 2)};
const CATEGORY_INFO = ${JSON.stringify({
  solo:          { label: 'Solo',            color: CATEGORY_COLORS.solo,           count: counts.solo||0 },
  duo:           { label: 'À deux',          color: CATEGORY_COLORS.duo,            count: counts.duo||0 },
  'petit-groupe': { label: '3–4 joueurs',    color: CATEGORY_COLORS['petit-groupe'], count: counts['petit-groupe']||0 },
  'grand-groupe': { label: '5 joueurs +',    color: CATEGORY_COLORS['grand-groupe'], count: counts['grand-groupe']||0 },
}, null, 2)};`;

fs.writeFileSync(path.join(__dirname, '..', 'site', 'data.js'), output);
console.log(`✓ Generated data.js — ${games.length} games`);
console.log(`  Solo: ${counts.solo||0} | Duo: ${counts.duo||0} | 3-4: ${counts['petit-groupe']||0} | 5+: ${counts['grand-groupe']||0}`);
