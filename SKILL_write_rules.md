# SKILL_write_rules

## Purpose

Write complete, reliable French rules for one card game. The short version is a quick reference; the long version preserves the useful explanatory material from the source books.

## Source Fidelity

- Reuse the supplied source text as the primary authority.
- Preserve precise procedures, card values, dealing patterns, scoring, penalties, tips, special rules and variants.
- Do not invent rules, strategy, aliases or historical claims.
- If sources disagree, label the rule as a variant and identify the source or region when known.
- Do not merge games that only share a name. Use distinct files for regional variants.
- Remove magic tricks, sleight-of-hand instructions and unrelated historical material from game rules.

## Required File Format

Every file belongs in `rules/` and uses this structure:

```markdown
# Nom du jeu

**Nombre de joueurs :** ...
**Nombre de cartes :** ...
**Difficulté :** Facile | Moyenne | Difficile
**Type :** Jeu de plis | Combinaisons | Défausse | Mémoire | Patience | Hasard | Enchères | Adresse | Mixte
**But :** ...
**Autres noms :** ...

---

## Règle courte

Quick setup, turn order, legal moves and win condition. Keep this section usable at the table in under two minutes.

## Version longue

The complete rule, including:

- matériel and exact deck composition;
- player roles, teams and seating;
- dealer selection, shuffle, cut and dealing order;
- card ranking, card values and trump rules;
- setup and layout diagrams in text when needed;
- complete turn-by-turn procedure;
- mandatory moves and priority rules;
- scoring, payments and end-of-round conditions;
- illegal moves, penalties and redeals;
- practical tips explicitly supported by the source;
- all documented variants, clearly labelled.

## Conseils

Only source-supported advice. Omit this section when the source gives no advice.

## Règles spéciales

List exceptional cards, declarations, penalties and edge cases.

## Variantes

Describe each documented variant separately, with its player count, cards, altered rules and scoring.
```

## Metadata Rules

- `Nombre de joueurs` must state the normal range and special team configuration.
- `Nombre de cartes` must state the deck size and additions/removals.
- `Difficulté` is editorial metadata: `Facile`, `Moyenne` or `Difficile`.
- `Type` may contain multiple comma-separated types.
- `Autres noms` lists only documented names, regional names or established equivalents.
- The short and long sections must never contradict each other.

## Web Data Contract

The Markdown parser must expose `title`, `aliases`, `players`, `cards`, `difficulty`, `type`, `goal` and the complete Markdown. The web detail view must show the short rule first, then the complete long rule, and provide a YouTube search link using:

`<nom du jeu> règles jeu de cartes`
