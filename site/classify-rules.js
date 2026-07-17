#!/usr/bin/env node
/** Add editorial difficulty and game-type metadata to rules that lack it. */
const fs = require('fs');
const path = require('path');

const dir = path.join(__dirname, '..', 'rules');

function classify(content) {
  const text = content
    .replace(/^\*\*Difficult[ée]\s*:\s*\*\*.*$/gim, '')
    .replace(/^\*\*Type\s*:\s*\*\*.*$/gim, '')
    .toLocaleLowerCase('fr');
  const sections = (content.match(/^## /gm) || []).length;
  const types = [];

  if (/lev[ée]e|atout|couleur dominante|jeu de la carte|\bplis?\b/.test(text)) types.push('Jeu de plis');
  if (/\bcombinaisons?\b|\bbrelan\b|\bpaires?\b|\bfull\b|\bsuites?\b|\bcarr[ée]s?\b|\bquintes?\b/.test(text)) types.push('Combinaisons');
  if (/\bd[ée]fausse\b|se d[ée]fausser|jeter une carte|\btalon\b/.test(text)) types.push('Défausse');
  if (/m[ée]moire|m[ée]moriser|retenir/.test(text)) types.push('Mémoire');
  if (/\bpatiences?\b|\bsolitaire\b|\bfondation\b|\btableau\b/.test(text)) types.push('Patience');
  if (/ench[èe]re|ench[ée]rir|\bcontrat\b/.test(text)) types.push('Enchères');
  if (/\bmise\b|\bparis\b|\bbanquier\b|\bhasard\b|tirage/.test(text)) types.push('Hasard');
  if (/rapidit[ée]|frapper|empiler|\badresse\b/.test(text)) types.push('Adresse');
  if (types.length === 0) types.push('Mixte');

  let difficulty = 'Moyenne';
  if (/\bpatience\b|\bsolitaire\b|\bbataille\b|\bsnap\b|m[ée]moire/.test(text) && sections <= 5) difficulty = 'Facile';
  if (sections >= 9 || /ench[èe]re|contrat|score de donne|partenariat|r[èe]gles sp[ée]ciales/.test(text)) difficulty = 'Difficile';

  return { difficulty, type: [...new Set(types)].join(', ') };
}

for (const file of fs.readdirSync(dir).filter(name => name.endsWith('.md'))) {
  const filePath = path.join(dir, file);
  const content = fs.readFileSync(filePath, 'utf8');
  if (!/^\*\*Nombre de cartes\s*:\*\*.*$/m.test(content)) continue;
  const { difficulty, type } = classify(content);
  const metadata = `\n**Difficulté :** ${difficulty}\n**Type :** ${type}`;
  const updated = content.replace(
    /^(\*\*Nombre de cartes\s*:\*\*.*)(?:\n\*\*Difficult[ée]\s*:\s*\*\*.*)?(?:\n\*\*Type\s*:\s*\*\*.*)?/m,
    `$1${metadata}`
  );
  fs.writeFileSync(filePath, updated);
}

console.log('Metadata classification complete');
