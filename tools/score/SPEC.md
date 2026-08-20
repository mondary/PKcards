# SPEC — Compteur de scores (`tools/score/`)

Outil web générique de notation de scores pour jeux de cartes/société, destiné à
remplacer papier-crayon. Fichier unique `index.html` (HTML+CSS+JS vanilla, zéro
dépendance, zéro build), hébergé statiquement :
https://mondary.design/pk/-Games-cards/score/

Source : demande du 2026-08-14, itérée par tests utilisateurs. Ce document
décrit le produit livré en `1.2026.14`.

---

## 1. Principes

- **Mobile-first**, une seule main / pouce ; responsive (2 colonnes ≥ 600 px).
- **Aucun clavier OS pendant le jeu** : pavé numérique permanent en bas.
- Générique : aucune règle de jeu codée, juste des points (négatifs inclus).
- Fonctionne en `file://` (repli mémoire, aucun accès storage) et en `https`
  (localStorage : reprise de partie + palmarès).

## 2. Écrans (un seul écran + feuilles)

- **Liste des joueurs** : prête à l'emploi (Joueur 1, 2, 3). Tap nom =
  renommer. ✕ = retirer. « Ajouter un joueur » à la volée, même en partie.
- **Avatar** : initiales auto (« Joueur 2 » → J2) ; tap sur l'avatar = photo
  (appareil photo mobile, recadrage carré 112 px canvas, ~5 Ko stocké).
  Photo pleine hauteur de tuile, collée au bord gauche, coins haut-gauche/
  bas-gauche arrondis (14 px, épouse le rayon de sélection).
- **Pavé permanent** : barre (avatar du joueur sélectionné + valeur) et grille
  `1-9 / ⌫ / − / ↩ undo / 0 / ↪ redo / Ajouter`. Touche **−** : préfixe ou
  bascule le signe. Le bouton indique clairement « Ajouter +N » ou
  « Modifier MN ». Valider → enregistre et **avance au joueur suivant** non
  compté de la manche ; dernier joueur = manche scellée.
- **Écran « Les tables »** : l'accueil présente des cartes de tables avec leurs
  joueurs, l'état de la partie et une action « Reprendre ». On crée, ouvre,
  renomme ou supprime une table depuis cet écran (✕ toujours disponible ;
  supprimer la dernière table repart sur une « Table 1 » neuve) ; le bouton
  de table dans le compteur y ramène. Chaque table garde sa couleur (4
  palettes, stockée sur la table) et possède ses propres joueurs, manches en
  cours, photos et palmarès : on peut donc alterner entre plusieurs groupes
  sans clôturer une partie.
- **Entête de liste** : retour à la table, manche courante centrée, **Fin**
  visible (confirmation, podium, archivage palmarès + Rejouer), accès au
  palmarès et réglages.
  Les réglages regroupent ✓ Un seul marque par manche (les autres joueurs
  marquent 0 automatiquement) et ⟳ Recommencer à la manche 1.
- **🏆 Palmarès** : victoires cumulées par joueur (clé `pk.score.games`,
  60 dernières parties, effaçable).
- **Fin de partie** : Annuler conserve la partie en cours ; confirmer archive
  le classement et remet la table à la manche 1. Rejouer reste sur le compteur,
  Retour aux tables revient à la liste des groupes.

## 3. Modèle de données (localStorage `pk.score.tables.v1`)

```json
{
  "activeId": "t-…",
  "tables": [{
    "id": "t-…", "name": "Famille",
    "state": {"players": [], "rounds": [], "log": []},
    "games": []
  }]
}
```

La première ouverture migre automatiquement les clés historiques `pk.score.v3`
et `pk.score.games` dans « Table 1 ».

- `rounds[].done` : manche complète (tous les joueurs présents) → scellée ;
  l'ajout d'un joueur après une manche scellée ne la rouvre pas.
- `log` / `redo` : piles d'actions pour l'annulation et le rétablissement
  ciblés (200 max pour l'historique actif).

## 4. Comportements clés

- **Undo ↩ par joueur** : annule la dernière saisie du joueur sélectionné
  (restore la valeur précédente, dé-scelle/supprime la manche si vide).
- **Redo ↪ par joueur** : rétablit la dernière saisie annulée du joueur
  sélectionné ; toute nouvelle saisie vide la pile de redo.
- **Correction** : tap sur une valeur de l'historique → pavé en mode
  correction (pré-rempli), Valider réécrit.
- **Re-sélection d'un joueur compté** : pré-remplit sa valeur de manche
  (édition) ; ⌫ puis nouvelle valeur pour remplacer.
- Historique = une mention compacte par manche (« M1 12 », « M2 14 »,
  « M3 −10 ») sous le nom, alignée en grille sans bordures, avec les valeurs
  alignées à droite, sur autant de lignes que nécessaire — aucun score n'est
  masqué. Le bouton « Ajouter +N » est à côté de la photo et de la valeur du
  joueur sélectionné.
- **Couleur par joueur** (palette des tables, par position dans la liste) :
  la barre de sélection, l'avatar du pavé et le bouton « Ajouter » prennent
  la couleur du joueur actif.
- Manche courante affichée dans l'entête (« Manche N »).
- **Un seul marque par manche** (`state.solo`, par table, Menu) : pour les
  jeux où un seul joueur marque par donne (à 2 en alternance…). Valider le
  score d'un joueur scelle la manche et met 0 aux autres (0 stockés comme
  scores normaux → totaux, undo, correction inchangés).
- **Le plus bas gagne** (`state.low`, par table, Menu) : classement et 🏆
  inversés à la fin de partie — pour les jeux où l'on perd des points
  (Yaniv, Golf…).

## 5. Identité visuelle

Papier clair (`#faf8f4`), encre (`#1c1b18`), accent bleu (`#2050c8`).
Icône : tally (4 barres + diagonale) en SVG inline + `apple-touch-icon.png`.
Sans animation superflue ; `user-scalable=no` + `touch-action: manipulation`.

## 6. Hors périmètre (refusé ou remanié pendant les itérations)

- Touche ＋ / expressions multi-termes (retirée : inutile à l'usage).
- Mode plus haut/plus bas gagne, confettis, dégradés (retirés avec la V1
  « design AI ») ; roue/slider (rejetés d'emblée, imprécis).
- Service worker offline complet, export, sons, i18n.

## 7. Déploiement

Upload FTP du dossier vers `/www/pk/-Games-cards/score/`
(index.html + apple-touch-icon.png ; SPEC.md reste au dépôt).
