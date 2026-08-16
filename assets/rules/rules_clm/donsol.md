# ☠️ Donsol

**1 joueur • Roguelike / Dungeon crawler • ~15–20 min • Jeu classique de 52 cartes**

Tu explores un donjon généré par les cartes. Tu affrontes des monstres, récupères des armes et bois des potions pour essayer d'atteindre la sortie vivant.

---

## 🎴 1. Préparation

Prends un jeu classique de **52 cartes**.

Retire :

* les **Jokers**
* les **Valets, Dames et Rois de ♥️ et ♦️**
* les **As de ♥️ et ♦️**

Il reste **44 cartes**.

Mélange-les pour constituer le **donjon**.

Tu commences avec :

❤️ **20 PV**

🗡️ **aucune arme**

---

# ♠️♣️♦️♥️ 2. Les cartes

### ♠️ ♣️ MONSTRES

Les cartes noires sont les ennemis.

**2 à 10 = valeur indiquée**
**Valet = 11**
**Dame = 12**
**Roi = 13**
**As = 14**

---

### ♦️ ARMES

Les ♦️ sont des armes.

Leur valeur représente leur puissance.

**7♦️ = arme de puissance 7**

---

### ♥️ POTIONS

Les ♥️ sont des potions.

Elles rendent autant de PV que leur valeur.

**6♥️ = +6 PV**

Tu ne peux jamais dépasser :

❤️ **20 PV**

---

# 🚪 3. Entrer dans une salle

Révèle **4 cartes**.

Elles constituent la salle actuelle du donjon.

Tu dois résoudre **3 cartes parmi les 4**, dans l'ordre de ton choix.

La quatrième reste sur la table.

Ajoute ensuite **3 nouvelles cartes** pour reconstituer une salle de quatre cartes.

---

# 🏃 4. Fuir

Tu peux décider de ne pas entrer dans une salle.

Place alors les quatre cartes **sous le donjon**, dans l'ordre de ton choix.

Révèle quatre nouvelles cartes.

⚠️ Tu ne peux pas fuir **deux salles consécutives**.

---

# ⚔️ 5. Affronter un monstre

Tu peux combattre :

**à mains nues**

ou

**avec ton arme équipée.**

### 👊 À mains nues

Tu subis toute la valeur du monstre.

Exemple :

**9♠️**

→ tu perds **9 PV**.

---

### 🗡️ Avec une arme

Soustrais la puissance de ton arme à celle du monstre.

**Dégâts = Monstre − Arme**

Minimum : **0 dégât**.

Exemple :

🗡️ **6♦️**

contre

👹 **10♣️**

→ **10 − 6 = 4 PV perdus**

---

# 🩸 6. L'arme s'émousse

Lorsqu'une arme tue un monstre, place celui-ci sur l'arme.

Elle ne pourra désormais être utilisée que contre un monstre **plus faible que le dernier monstre qu'elle a combattu**.

Exemple :

🗡️ **8♦️**

tue **12♠️**.

Elle peut ensuite combattre :

**11 ou moins.**

Si elle combat ensuite **6♣️** :

elle ne pourra désormais combattre que :

**5 ou moins.**

⚠️ Tu peux toujours choisir de combattre **à mains nues** afin de préserver ton arme.

---

# 🔄 7. Changer d'arme

Lorsque tu résous un ♦️, tu peux l'équiper.

Ton ancienne arme est abandonnée avec tous les monstres placés dessus.

La nouvelle arme repart donc sans restriction.

Une petite arme neuve peut ainsi être beaucoup plus intéressante qu'une grosse arme devenue presque inutilisable.

---

# ❤️ 8. Les potions

Lorsque tu résous un ♥️ :

récupère immédiatement sa valeur en PV.

Exemple :

❤️ **11 PV**

Tu trouves :

**7♥️**

→ tu remontes à **18 PV**.

Maximum :

❤️ **20 PV**

⚠️ Une seule potion peut réellement te soigner dans une même salle.

Les potions supplémentaires résolues dans cette salle sont perdues.

---

# 💀 9. Mourir

Si tes PV atteignent :

**0 ou moins**

☠️ **la partie s'arrête immédiatement.**

Tu es mort dans le donjon.

---

# 🏆 10. Sortir du donjon

Continue jusqu'à avoir résolu toutes les cartes.

Si tu élimines le dernier monstre et que tu possèdes encore au moins :

❤️ **1 PV**

tu as survécu au donjon.

🏆 **Victoire.**

---

# 🎯 11. Score

Tu peux également jouer pour obtenir le meilleur score possible.

Plus tu termines l'aventure avec de PV, meilleure est ta performance.

Cela transforme le jeu en petit roguelike à **high score** : survivre est déjà bien, sortir presque indemne est beaucoup plus difficile.

---

# 🧠 L'idée du jeu

Donsol repose sur une mécanique particulièrement simple :

♠️♣️ **les monstres te blessent**
♦️ **les armes absorbent une partie des dégâts**
♥️ **les potions te maintiennent en vie**

Mais chaque salle devient un petit problème d'optimisation :

**Dans quel ordre résoudre les cartes ? Est-ce que je sacrifie des PV ? Est-ce que j'use mon arme ? Est-ce que je laisse cette carte pour la prochaine salle ?**

Le hasard construit le donjon.

**Tes décisions déterminent si tu en ressors. ☠️🗡️**
