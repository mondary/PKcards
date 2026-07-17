#!/usr/bin/env node
/**
 * add-aliases.js — Add "Autres noms" to all game markdown files
 */
const fs = require('fs');
const path = require('path');

const dir = path.join(__dirname, '..', 'rules');

// Master alias mapping: id → aliases string
const ALIASES = {
  // --- 500 / Cinq-Cent family ---
  '500': 'Cinq-Cent, Five Hundred',
  'cinq-cents': 'Cinq-Cent, Briscan, Brisque',
  'cinq-cents-bordelais': 'Cinq-Cent bordelais',
  'cinq-cents-lyonnais': 'Cinq-Cent lyonnais, Mille',
  'cinq-rois': 'Cinq-Rois',

  // --- 8 American / Crazy Eights ---
  '8-americain': '8 Américain, Crazy Eights, Mau Mau, Switch, Uno (ancêtre)',
  'huit': 'Le Huit, 8 Américain (variant québécoise)',

  // --- Bataille family ---
  'bataille': 'La Bataille, War',
  'bataille-corse': 'Bataille Corse, Egyptian Rat Screw',
  'bataille-corse-tarot': 'Bataille Corse Tarot',
  'bataille-norvegienne': 'Bataille Norvégienne, Norwegian War',

  // --- Belote ---
  'belote': 'Belote, Belotte, Belote bridgée',

  // --- Bésigue / Bézigue ---
  'bezique': 'Bésigue, Bézi, Bézigue, Bisi, Bizique',
  'pinochle': 'Pinochle, Bésigue américain, Pinocle',
  'bezique-chinois': 'Bésigue chinois, Bézique chinois',

  // --- Boston ---
  'boston': 'Boston, Boston de Lorient, Boston de Nantes',

  // --- Bridge ---
  'bridge': 'Bridge-Contrat, Contract Bridge',

  // --- Canasta ---
  'canasta': 'Canasta',
  'canasta-chinoise': 'Canasta Chinoise',

  // --- Casino / Scopa ---
  'casino': 'Casino, Cassino',
  'scopa': 'Scopa, Escoba (Espagne)',

  // --- Charlemagne ---
  'charlemagne': 'Charlemagne, La Partie',

  // --- Crapette ---
  'crapette': 'Crapette, Crapette vitesse',
  'crapette-tarot': 'Crapette Tarot',

  // --- Cribbage ---
  'cribbage': 'Cribbage, Crib, Le Crib',

  // --- Dame de Pique / Hearts ---
  'dame-de-pique': 'Dame de Pique, Hearts, Le Cœur, Black Lady',
  'coeurs': 'Les Cœurs, Hearts, Chasse-Cœur (variante)',
  'chasse-coeur': 'Chasse-Cœur, Black Lady, Hearts',

  // --- Dominos (cartes) ---
  'dominos': 'Dominos (jeu de cartes), Fan Tan, Sevens, Parlement',

  // --- Ecarté ---
  'ecarte': 'Écarté',

  // --- Familles / Go Fish ---
  'familles': 'Les Familles, Go Fish, Pêche (Québec)',
  'pige-dans-le-lac': 'Pige dans le lac, Go Fish, Fish',

  // --- Fouine / Klaberjass ---
  'fouine': 'La Fouine, Klaberjass, Klabrias',
  'kalabriasz': 'Kalabriasz, Klaberjass, Kalabrias',

  // --- Golf ---
  'golf': 'Golf, Le Golf (jeu de cartes)',

  // --- Kem's / Kems ---
  'kems': 'Kem\'s, Kem, Jeu des Mots',
  'kems-duplicate': 'Kem\'s Duplicate',

  // --- Kilo ---
  'kilo': 'Le Kilo (de merde), Le Kilo',
  'kilo-de-plomb': 'Kilo de Plomb',

  // --- Manille ---
  'manille': 'Manille, Manille coinchée, Manille muette',

  // --- Mariage ---
  'mariage': 'Mariage, Brisque, Briscan',
  'mariage-chinois': 'Mariage Chinois, Memory, Concentration',

  // --- Menteur / Liar's Dice ---
  'menteur': 'Le Menteur, La Marmite, Liar\'s Dice (variant), Cheat',
  'poker-menteur': 'Poker Menteur, Liar\'s Poker',

  // --- Mouche ---
  'mouche': 'La Mouche, Mistigri, Pamphile, Lanturlu, Bête',

  // --- Nain Jaune ---
  'nain-jaune': 'Nain Jaune, Lindor',

  // --- Patiences / Solitaire ---
  'patiences': 'Patience, Solitaire, Réussite',
  'solitaire': 'Solitaire, Patience, Klondike',
  'reussite': 'Réussite, Patience, Solitaire',

  // --- Piquet ---
  'piquet': 'Piquet, Piquet ordinaire',
  'piquet-normand': 'Piquet Normand',
  'piquet-voleur': 'Piquet Voleur',

  // --- Poker ---
  'poker': 'Poker, Draw Poker, Poker fermé',
  'brag': 'Brag, Three Card Brag',
  'ecarte-poker': '',

  // --- Pouilleux / Old Maid ---
  'pouilleux': 'Pouilleux, Vieux Garçon, Old Maid, Le Dernier Valet, La Pouilleuse',
  'pouilleux-variant': '',

  // --- President ---
  'president': 'Président, Trou du Cul, Asshole, Scum, Le Pouilleux (variante)',
  'trou-du-cul': 'Trou du Cul, Président, Asshole, Pute, Le Pallmall',

  // --- Rami family ---
  'rami': 'Rami, Rummy, Rum',
  'gin-rami': 'Gin Rami, Gin Rummy, Gin',
  'rummy-500': 'Rummy 500, Rami 500, Pinochle Rummy',
  'rummy-baltimore': 'Rummy Baltimore, Baltimore',
  'floune': 'La Floune (Québec)',

  // --- Skyjo ---
  'skyjo': 'Skyjo',

  // --- Snap family ---
  'snap': 'Snap, Slapjack',
  'snip-snap-snorem': 'Snip Snap Snorem',

  // --- Tarot ---
  'tarot': 'Tarot, Tarot français, Jeu de Tarot',

  // --- Vingt-et-Un / Blackjack ---
  'vingt-et-un': 'Vingt-et-Un, Blackjack, 21, Pontoon',
  'trente-et-un': 'Trente-et-Un, Trente-et-Un (banking)',
  'black-jack-banquier': 'Black Jack (avec banquier), Blackjack 21',
  'black-jack-pot': 'Black Jack (avec pot), Blackjack 21',

  // --- Whist ---
  'whist': 'Whist, Whist simple',

  // --- Aluette / La Vache ---
  'aluette': 'Aluette, La Vache, Vache',

  // --- Baccara ---
  'baccara': 'Baccara, Baccarat, Chemin de Fer, Punto Banco',

  // --- Brelan ---
  'brelan': 'Le Brelan, Berlan',

  // --- Bouillotte ---
  'bouillotte': 'La Bouillotte',

  // --- Chasse à l'As ---
  'chasse-a-las': 'Chasse à l\'As, Ace Chase, Acey-Deucey',

  // --- Commerce ---
  'commerce': 'Le Commerce',

  // --- Conférence ---
  'conference': 'La Conférence',

  // --- Coucou ---
  'coucou': 'Le Coucou, Le Pauvre Hère, Le Cocu, Le Malheureux',

  // --- Crapette (Québec) ---
  'banque-russe': 'Banque Russe, Russian Bank, Crapette Russe',
  'solitaire-a-deux': 'Solitaire à Deux, Double Solitaire',

  // --- Ecarté variants ---
  'ecarte-trois': '',

  // --- Enflé ---
  'enfle': 'L\'Enflé, Schwimmen (Allemagne), Vingt-et-Un (variante)',

  // --- Jeux d'argent / Casino ---
  'bassette': 'La Bassette, Bassette (jeu)',
  'banque': 'La Banque, Banco',
  'pharaon': 'Le Pharaon, Faro, Pharaon (jeu)',
  'lansquenet': 'Le Lansquenet, Lansquenet',
  'macao': 'Le Macao, Macao (jeu)',
  'trente-et-quarante': 'Trente-et-Quarante, Trente et Quarante',
  'petits-paquets': 'Les Petits Paquets',
  'trentaine': '',

  // --- Jeux québécois ---
  'allo-jack': 'Allô Jack ! (Québec), Snap (variant)',
  'beigne': 'Le Beigne, Up and Down, Oh Hell, Nomination Whist',
  'carte-svp': 'Carte, s\'il vous plaît ! (Québec), Card Please (variant)',
  'chat': 'Le Chat (Québec), Spite and Malice (variant)',
  'mitaine': 'La Mitaine (Québec)',
  'paquet-voleur': 'Le Paquet Voleur (Québec), Egyptian Ratscrew (variant)',
  'petite-memoire': 'La Petite Mémoire (Québec), Golf (variant)',
  'quatre-vingt-dix-neuf': '99, Quatre-Vingt-Dix-Neuf',
  'salade': 'La Salade (Québec), Mixed Hearts (variant)',
  'mille': 'Le Mille (Québec), Canasta (variant)',
  'neuf-a-deux': 'Neuf à Deux (Québec), Nine to Zero',
  'neuf-a-trois': 'Neuf à Trois (Québec)',
  'neuf-a-quatre': 'Neuf à Quatre (Québec), Oh Hell (variant)',
  'pique': 'Pique, Spades, Atout Pique',
  'carotte': 'La Carotte (Québec)',
  'elimination': 'L\'Élimination (Québec), High-Low (variant)',
  'chien-rouge': 'Le Chien Rouge, Red Dog, Acey-Deucey',
  'in-between': 'In-Between, Acey-Deucey, Between the Sheets',
  'poule-3-cartes': 'Poule à 3 Cartes, Three Card Brag (variant)',
  'poule-5-cartes': 'Poule à 5 Cartes, Five Card Brag (variant)',
  'quatre-quatorze': 'Quatre-Quatorze, 4-14 (Québec)',
  'sept-vingt-sept': 'Sept-Vingt-Sept, 7-27 (Québec)',
  'stud-5-cartes': 'Stud à 5 Cartes, Five Card Stud',
  'stud-7-cartes': 'Stud à 7 Cartes, Seven Card Stud',
  'jeu-du-roi': 'Le Jeu du Roi, The King',

  // --- Gerver book games ---
  'ambigu': 'L\'Ambigu',
  'plus-d-atouts': 'Le Plus-d\'Atouts',
  'bog': 'Le Bog, Boga',
  'brusquembille': 'La Brusquembille, Bruxambille',
  'couillon': 'Le Couillon',
  'ferme': 'La Ferme',
  'florentin': 'Le Florentin',
  'hoc': 'Le Hoc, Mazarin (variante)',
  'imperiale': 'L\'Impériale',
  'loterie': 'La Loterie',
  'marjolet': 'Le Marjolet',
  'may-i': 'May I?, May I (jeu)',
  'motz': 'Le Motz',
  'papillon': 'Le Papillon',
  'polignac': 'Le Polignac, Jeu des Valets',
  'poque': 'Le Poque',
  'quit': 'Le Quit',
  'rams': 'Le Rams, Rems',
  'reversi': 'Le Reversi, Reversis',
  'sans-coeur': 'Le Sans-Cœur',
  'sept-et-demi': 'Le Sept-et-Demi, Sette e Mezzo (Italie)',
  'sixte': 'La Sixte',
  'tontine': 'La Tontine',
  'treize': 'Le Jeu du Treize, Le Treize',
  'treize-quebecois': 'Le Treize (Québec)',
  'triomphe': 'La Triomphe, Trump (jeu)',
  'whip': 'Le Whip',
  'commere-accom modez-moi': 'Commère, accommodez-moi',

  // --- Other existing games ---
  'ascenseur': 'L\'Ascenseur, Lift (jeu)',
  'barbu': 'Le Barbu, Le Roi Barb',
  'bonjour-monsieur': 'Bonjour Monsieur',
  'bouchon': 'Le Bouchon',
  'chkobba': 'Chkobba, Escoba, Scopa (Tunisie)',
  'dourak': 'Durak, Дурак (Russie), Le Fou',
  'euchre': 'Euchre, Eucre',
  'flip': 'Flip',
  'golf-cards': '',
  'jeu-de-loo': 'Jeu de Loo, Loo, Lanterloo',
  'jeu-de-valets': 'Jeu de Valets',
  'mafia': 'Mafia, Werewolf (variant), Loup-Garou (variant)',
  'papesse-jeanne': 'La Papesses, Jeu de la Papesses',
  'politaine': 'La Politaine',
  'preference': 'Le Préférence, Preferans (Russie)',
  'shithead': 'Shithead, Palace, Karma, Shed',
  'skat': 'Skat',
  'all-fives': 'All Fives, Cinch',
  'all-fours': 'All Fours, Seven Up, Old Sledge',
  'preference': 'Le Préférence, Preferans',
  'charlemagne': 'Charlemagne, La Partie',
  
  // --- Children's book games ---
  'animaux': 'Les Animaux, Animal Sounds',
  'chateau-de-cartes': 'Le Château de Cartes, Card Castle',
  'vive-la-joie': 'Vive la Joie, Old Maid (variant)',
  'frappe-le-valet': 'Frappe le Valet, Slapjack, Snap (variant)',
  'baudet': 'Le Baudet, Le Petit Cheval',
  'pot': 'Le Pot',
  'puits': 'Le Puits, The Well',
  'roi-et-son-epee': 'Le Roi et son Épée',
  'carte-la-plus-basse': 'La Carte la Plus Basse, Low Card',
  'kilo-de-plomb': 'Kilo de Plomb',
  'kems': 'Kem\'s, Kem, Jeu des Mots',
  'cinq-rois': 'Cinq-Rois',
  'flip': 'Flip, Lapsit (variant)',
};

// Process files
const files = fs.readdirSync(dir).filter(f => f.endsWith('.md'));
let updated = 0;

for (const file of files) {
  const id = path.basename(file, '.md');
  const aliases = ALIASES[id];
  
  if (!aliases) continue;
  
  const fpath = path.join(dir, file);
  let content = fs.readFileSync(fpath, 'utf8');
  
  // Skip if already has Autres noms
  if (content.includes('**Autres noms')) continue;
  
  // Find the **But :** line and add after it
  const butMatch = content.match(/^(\*\*But\s*:?\s*\*\*.+)$/m);
  if (!butMatch) {
    console.log(`  ⚠ No "But" line in ${file}`);
    continue;
  }
  
  const butLine = butMatch[1];
  const aliasesLine = `**Autres noms :** ${aliases}`;
  
  content = content.replace(butLine, butLine + '\n' + aliasesLine);
  fs.writeFileSync(fpath, content);
  updated++;
}

console.log(`✓ Added aliases to ${updated} files`);
