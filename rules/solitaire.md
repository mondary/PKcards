# Solitaire

**Nombre de joueurs :** 1
**Nombre de cartes :** 52
**Difficulté :** Facile
**Type :** Combinaisons, Patience, Hasard
**But :** Reconstituer 4 piles de cartes (une par couleur) en suites croissantes de l'As au Roi
**Autres noms :** Solitaire, Patience, Klondike

---

## Mise en place

Former **7 colonnes** avec les cartes mélangées :

| Colonne | Cartes visibles | Cartes cachées |
|---------|-----------------|----------------|
| 1 | 1 | 0 |
| 2 | 1 | 1 |
| 3 | 1 | 2 |
| 4 | 1 | 3 |
| 5 | 1 | 4 |
| 6 | 1 | 5 |
| 7 | 1 | 6 |

- Les 4 emplacements vides à gauche = **piles de Fondation**
- Les cartes restantes = **pioche**

## Déroulement

### Déplacer une carte vers les piles de Fondation

- Si un **As** est visible, glissez-le sur l'emplacement libre de la bonne couleur.
- Puis placez le 2, le 3, etc. jusqu'au Roi (suites croissantes).
- À chaque carte retirée, retournez celle qui était dessous.

### Déplacer une carte d'une colonne vers une autre

Deux conditions :
1. Créer une **suite décroissante**
2. **Alterner les couleurs** (rouge sur noir, noir sur rouge)

> **Roi sur colonne vide :** Seul un Roi peut remplir une colonne vide.

> **Déplacement de groupe :** On peut déplacer plusieurs cartes si l'alternance rouge/noir est respectée et qu'elles forment une suite décroissante.

### Piocher une carte

Quand il n'y a plus de coup légal, piochez dans la pioche (1, 2 ou 3 cartes selon la variante). Les cartes piochées sont posées sur la table. Jouez celles que vous pouvez.

### Déplacement de Fondation vers colonne

Il est possible de reprendre une carte d'une pile de Fondation pour la placer sur une colonne.

## Fin du jeu

**Victoire :** Les 4 piles de Fondation sont complètes (As jusqu'au Roi, une par couleur).

**Défaite :** Plus aucun coup possible alors qu'il manque des cartes dans les Fondations.
