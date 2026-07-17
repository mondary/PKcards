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
- [ ] Filtres par difficulté et type
- [ ] Recherche par alias

---

## Releases

### [1.2026.2] - 2026-07-17
#### Added
- Métadonnées `Difficulté`, `Type` et `Autres noms` sur les fiches de jeux
- Scripts `add-aliases.js` et `classify-rules.js` pour enrichir les règles
- Bouton de recherche YouTube et section « Règle courte / Version longue » dans le détail
- Fichiers `VERSION`, `CHANGELOG.md` et assets (`icon.png`, `image.png`)

#### Changed
- Migration du dossier `cartes-regles/` vers `rules/`
- `build.js` parse les nouveaux champs et lit depuis `rules/`
- Compteur de jeux mis à jour (55 → 160+) dans le titre et la description

### [1.2026.1] - 2026-07-17
#### Added
- Scaffold initial du projet et catalogue des règles
