# Calculat (Calculet)

**Nombre de joueurs :** 1
**Nombre de cartes :** 32 (7, 8, 9, 10, V, D, R, As de chaque couleur)
**Difficulté :** Moyenne
**Type :** Patience, Solitaire
**But :** Vider toutes les piles en jouant les cartes qui se rapportent à 7
**Autres noms :** Calculet, Calculator

---

## Règle courte

Le Calculat est un solitaire très proche de l'**Accordéon**, mais avec un paquet réduit de **32 cartes** (du 7 à l'As). Les 32 cartes sont distribuées en **4 piles de 8 cartes** face visible. On joue avec la **carte supérieure** de chaque pile. Une carte peut être déposée sur une autre si :
- Elles sont de la **même couleur** et la différence est **7** (ex. : 9♠ sur 2♠),
- Elles sont de **couleur différente** et la différence est **1** (ex. : 10♥ sur V♠),
- L'une est une **figure** (V, D, R) et l'autre aussi, quelle que soit la couleur.

L'As peut être joué sur n'importe quelle carte d'une autre couleur (différence de 1), et réciproquement. La partie est gagnée si toutes les cartes finissent empilées sur une seule pile.

## Version longue

### Mise en place
Prenez un paquet de 32 cartes (du 7 à l'As). Distribuez-les en **4 piles de 8 cartes** face visible. Retirez la carte supérieure de chaque pile pour les jouer.

### Règles de déplacement
Une carte **A** peut être déposée sur une carte **B** si :
1. **Même couleur, différence de 7** : ex. 9♠ sur 2♠, ou 3♥ sur 10♥.
2. **Couleur différente, différence de 1** : ex. 8♦ sur 7♣, ou D♥ sur R♠.
3. **Les deux sont des figures** (Valet, Dame, Roi) : déplacement autorisé quelle que soit la couleur.

### Déplacements autorisés (tableau)
| Carte A | Carte B | Couleur | Condition |
|---------|---------|---------|-----------|
| As | 2 ou 10 | Différente | Diff = 1 |
| 2 | As ou 9 | Différente (As) / Même (9) | Diff 1 / Diff 7 |
| 3 | 10 ou 4 | Différente (10) / Même (4) | Diff 7 / Diff 1 |
| 4 | 3 ou 11 (As) | Même / Différente | Diff 1 / Diff 7 |
| ... | ... | ... | ... |
| Figure | Figure | N'importe | Toujours |

### Fin de partie
La partie est gagnée si toutes les cartes finissent sur une **seule pile**. On peut déplacer une pile entière sur une autre si la carte supérieure respecte les règles de déplacement.

## Conseils
- **Calculez les différences mentalement** : entraînez-vous à repérer rapidement les paires qui totalisent 7 ou 1.
- **Priorisez les figures** : elles peuvent se déplacer entre elles sans condition de couleur, ce qui débloque des cartes.
- **Surveillez les as** : ils sont très polyvalents (déplacement sur n'importe quelle carte d'une autre couleur).
- Si une pile est bloquée, cherchez à libérer la carte qui la débloque en jouant d'abord sur les autres piles.
- **N'importe quel déplacement est préférable à aucun** : même un déplacement non optimal ouvre des possibilités.

## Variantes

### Accordéon
Variante avec 52 cartes et déplacements sur voisine ou 3 cases. Voir `solitaire-accordeon.md`.

### Calculat inversé
Les figures valent 1 au lieu de 10. Les différences sont recalculées en conséquence, ce qui rend le jeu plus difficile.

### Calculat à 2 paquets
On utilise 64 cartes (deux paquets de 32). Les piles font 16 cartes. Le jeu est beaucoup plus long et complexe.
