# Changelog

Historique des versions de PKcards — application de découverte de jeux de cartes.

---

## TODO — Roadmap

Statut : `1.2026.2` (catalogue enrichi + refonte UI)

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

---

## Releases

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
