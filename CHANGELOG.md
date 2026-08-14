# Changelog

Historique des versions de PKcards — application de découverte de jeux de cartes.

---

## TODO — Roadmap

Statut : `1.2026.9` (import pagat.com + batch de pêche FR)

### Phase 1 — Catalogue & découverte
- [x] Migration du dossier `cartes-regles/` vers `rules/`
- [x] Enrichissement des règles (Difficulté, Type, Autres noms)
- [x] Refonte de la carte et du détail (alias, badges, règle courte/longue)
- [x] Bouton de recherche YouTube par jeu
- [x] Enrichissement des « Version longue » depuis le guide Marabout (69 jeux)
- [x] Enrichissement Règle courte + Version longue depuis les « Règlements officiels des jeux de cartes » (Bicycle)
- [ ] Filtres par difficulté et type
- [ ] Recherche par alias

### Phase 2 — Expérience & partage
- [x] Cartes Tinder plein écran et layout responsive (mobile → desktop)
- [x] Moteur de recherche (titre + alias, insensible aux accents)
- [x] Bouton de partage avec une URL par jeu (routing par hash `#id`)

### Phase 3 — Backend (votes & favoris)
- [x] API PHP + SQLite (`api.php`, base `data/rules.sqlite`)
- [x] Votes serveur (multi-vote autorisé) avec throttle léger anti-spam
- [x] Vue « Meilleurs jeux » (classement par votes)
- [x] Favoris liés à un email (sans mot de passe), stockés côté serveur

### Phase 4 — Outils (`tools/`)
- [x] Compteur de scores générique (`tools/score/`) : joueurs rapides, manches, totaux auto, historique éditable, photos, palmarès

### Phase 5 — Import pagat.com (en cours)
- [x] Scraping de l'index alpha (512 pages)
- [x] Conversion automatique au format fiche (méta FR, corps EN)
- [x] Détection de doublons multilingue (FR/EN/ES/alias) — `_skip.json`
- [x] Importeur v3 : `importPagat()` (catégorie `pagat`, couleurs, skip-list)
- [x] 10 fiches FR de jeux de pêche : Diloti, Pişti, Escoba, Scopone, Cuarenta, Ronda, Seep, Tablić, Chinese Ten, Cirulla
- [x] Batch Rami (1/2) : 20 fiches FR — Arlington, Banakil, Biriba, Burako, Burraco, Canastone, Caribbean, Carioca, Conquian, Crazy, Cuajo, Hand, Hand and Foot, Hoola, Indian, Kaluki, Loba, Mahjong, Okey, Okey 101
- [ ] Traduction/résumé FR des 394 fiches anglaises (par batches)

---

## Releases

### [1.2026.9] - 2026-08-14
#### Added
- Batch Rami (1/2) : 20 règles traduites en FR (corps + méta, noms et variantes conservés en « Autres noms »)
- Import intégral de pagat.com : **512 fiches anglaises** converties automatiquement au format du repo (méta FR parsable, corps markdown anglais, source citée)
- **394 nouveaux jeux** importés dans la base v3 après dédoublonnage (118 doublons détectés → enrichis comme alias sans créer de fiche supplémentaire)
- Outil de dédoublonnage : normalisation FR/EN/ES/DE/IT + `difflib` ratio ≥ 0.87 → rapport `assets/rules/pagat-duplicates.md` + skip-list `_skip.json`
-10 fiches FR complètes (règle courte + version longue + variantes + sources) pour les jeux de pêche : Le Diloti, Le Pişti, L'Escoba, Le Scopone, La Cuarenta, La Ronda, Le Seep, Le Tablić, Le Chinese Ten, La Cirulla
- `importPagat()` dans `site/v3/index.php` : catégorie `pagat`, couleur `#7eaafb`, import paramétré avec skip-list JSON

#### Fixed
- `site/v3/vault.sqlite` : 654 jeux en base (252 + 393 pagat + 10 clm), is_clm=1 correctement préservé

### [1.2026.8] - 2026-08-14
#### Added
- `tools/score/` : compteur de scores mobile-first pour remplacer papier-crayon (fichier unique `index.html`, zéro dépendance, déployable FTP tel quel)
- Liste de joueurs prête à l'emploi (Joueur 1, 2, 3 — renommables), ajout/suppression de joueurs à la volée en cours de partie
- Photo par joueur : tap sur l'avatar ouvre l'appareil photo (mobile), recadrage carré 112 px via canvas, thumbnail stocké dans la sauvegarde
- Pavé numérique permanent en bas d'écran (jamais le clavier OS) : ±, ⌫, scores négatifs ; Valider enchaîne au joueur suivant ; changer de joueur valide la saisie en cours
- Totaux cumulés en temps réel + historique « 12 + 14 » sous chaque nom, chaque valeur tapable pour correction immédiate
- Bouton undo ↩ (annule la dernière saisie, avec historique d'actions), manches scellées automatiquement quand tous ont joué
- 🏁 Fin de partie : podium + archivage ; 🏆 Palmarès : compteur de victoires cumulées par joueur (killer feature, persisté)
- Rejouer (mêmes joueurs, scores à zéro, photos conservées), layout qui tient compte du pavé permanent (pas de clavier virtuel pendant le jeu)
- Storage entièrement blindé (fonctionne en `file://` Safari, repli mémoire), specs dans `tools/score/SPEC.md`

### [1.2026.7] - 2026-07-30
#### Added
- `tools/rules-editor/` : éditeur générique multi-projets (sidebar rétractable, filtres par statut, favoris ⭐, archive 📦, dédoublonnage 🔍)
- Architecture multi-projets : table `projects` avec labels dynamiques, storage par projet, routing `?project=`
- Colonnes sources avec chapitrage (`##`), scroll synchronisé par section, surlignage vert des sections uniques
- Épinglage de sections (📌), recherche dans les colonnes, redimensionnement des colonnes
- Lien/fusion de documents par autocomplete (unifie alias + merges)
- Thème clair/sombre style Kigen (Inter + JetBrains Mono, cartes blanches, ombres subtiles)
- 4 nouvelles règles CLM : Gin Rummy, Le Kem's, Rami 500, Le 8 Américain

### [1.2026.6] - 2026-07-29
#### Added
- `site/v2/` : version mobile-first ultra simple (1 PHP + SQLite + HTMX), lectures des règles depuis `rules_clm/`
- Tracker Régicide : sections par famille (Valets / Dames / Rois jamais cassées par le responsive)
- Tracker Régicide : favicon couronne dorée, lien vers règles PDF, mémo mise en place

#### Changed
- `site/` archivé en `site/v1/`
- Tracker Régicide déplacé dans `tools/regicide/` avec cartes et règles
- Grille tracker : 2 colonnes mobile, 4 colonnes desktop par section

### [1.2026.5] - 2026-07-29
#### Added
- Règles Régicide et Yaniv dans `rules_clm/`
- Tracker Régicide déployé en ligne sur https://mondary.design/pk/tools/regicide-hp.html

#### Changed
- Grille du tracker : cartes plus grandes (padding réduit, 2 colonnes mobile)
- Liseré coloré discret pour les tiers d'attaque au lieu du badge ⚔

### [1.2026.4] - 2026-07-29
#### Added
- Tracker de points de vie pour le jeu Régicide (`tools/regicide-hp.html`) : grille de cartes responsive, mode zoom plein écran (hero header avec fade vers le noir), anneaux/barres de PV colorés, persistance localStorage, vibrations haptiques
- Images des cartes du Régicide dans `tools/cards/`
- Nouveaux dossiers de règles sources : `rules_bycicle/`, `rules_edimag100/`, `rules_fetjain32/`, `rules3/`

#### Changed
- Réorganisation des `rules2/` en sous-dossiers `jeux/` et `reussite/`
- Déplacement définitif des scripts offline vers `scripts/` (`build.js`, `add-aliases.js`, `classify-rules.js`)

### [1.2026.3] - 2026-07-18
#### Added
- Backend PHP + SQLite (`site/api.php`, base `site/data/rules.sqlite` auto-créée) pour la persistance serveur
- Votes serveur par jeu (multi-vote autorisé) avec throttle léger anti-spam (5 s entre deux votes même IP+jeu, cap 40/min/IP)
- Vue « Meilleurs jeux » (bouton 🏆 du header) affichant le classement par nombre de votes
- Bouton « Voter » dans la fiche détail
- Favoris liés à un email (sans mot de passe), stockés côté serveur et synchronisés entre appareils ; cache local pour rendu instantané et dégradation propre si le backend est absent
- Panneau de saisie d'email déclenché au premier ajout de favori
- Protection de la base par `site/data/.htaccess` (Apache) et règle `.gitignore` pour ne pas versionner le `.sqlite`

#### Changed
- Déplacement des scripts offline (`build.js`, `add-aliases.js`, `classify-rules.js`) de `site/` vers `scripts/`, pour que `site/` ne contienne que les fichiers à déployer
- `build.js` s'exécute désormais depuis la racine (`node scripts/build.js`) et écrit dans `site/v1/data.js`, afin que V1 reste autonome

### [1.2026.2] - 2026-07-17
#### Added
- Métadonnées `Difficulté`, `Type` et `Autres noms` sur les fiches de jeux
- Scripts `add-aliases.js` et `classify-rules.js` pour enrichir les règles
- Bouton de recherche YouTube et section « Règle courte / Version longue » dans le détail
- Fichiers `VERSION`, `CHANGELOG.md` et assets (`icon.png`, `image.png`)
- Cartes Tinder plein écran et mise en page responsive (colonne mobile → carte centrée sur desktop, modales centrées)
- Moteur de recherche (loupe → panneau) filtrant sur le titre et les alias, insensible aux accents
- Bouton de partage par jeu (`navigator.share` + repli copie du lien) et routing par hash (`#id`) pour une URL directe par jeu

#### Changed
- Migration du dossier `cartes-regles/` vers `rules/`
- Réécriture des fiches au format « Règle courte / Version longue »
- Enrichissement des règles détaillées de 69 jeux à partir du « Guide Marabout de tous les jeux de cartes »
- Enrichissement de ~30 fiches (Règle courte jouable + Version longue avec variantes/exemples) depuis les « Règlements officiels des jeux de cartes » (Bicycle) ; ajout des jeux Fan Tan, Michigan et Quarante-Cinq
- `build.js` parse les nouveaux champs et lit depuis `rules/`
- Compteur de jeux mis à jour (55 → 160+) dans le titre et la description

### [1.2026.1] - 2026-07-17
#### Added
- Scaffold initial du projet et catalogue des règles
