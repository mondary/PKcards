# Changelog

Historique des versions de PKcards — application de découverte de jeux de cartes.

---

## TODO — Roadmap

Statut : `1.2026.5` (tracker régicide en ligne + règles clm)

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

---

## Releases

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
- `build.js` s'exécute désormais depuis la racine (`node scripts/build.js`) et écrit dans `site/data.js`

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
