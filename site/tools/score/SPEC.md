# SPEC — Compteur de scores (`site/tools/score/`)

Outil web générique de notation de scores pour jeux de cartes/société, destiné à
remplacer papier-crayon. Fichier unique `index.html` (HTML+CSS+JS vanilla, zéro
dépendance, zéro build), hébergé statiquement :
https://mondary.design/pk/-Games-cards/score/

Source : demande du 2026-08-14, itérée par tests utilisateurs. Ce document
décrit le produit livré en `1.2026.8`.

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
- **Pavé permanent** : barre (joueur sélectionné + valeur) et grille
  `1-9 / ⌫ / − / ↩ undo / 0 / Valider (large)`. Touche **−** : préfixe ou
  bascule le signe. Valider → enregistre et **avance au joueur suivant** non
  compté de la manche ; dernier joueur = manche scellée.
- **Menu** (dans l'entête de liste) : 🏁 Terminer la partie (podium +
  archivage palmarès + Rejouer) · ⟳ Recommencer à la manche 1 · Fermer.
- **🏆 Palmarès** : victoires cumulées par joueur (clé `pk.score.games`,
  60 dernières parties, effaçable).

## 3. Modèle de données (localStorage `pk.score.v3`)

```json
{
  "players": [{"id": "…", "name": "Clément", "photo": "data:image/jpeg…"}],
  "rounds":  [{"scores": {"id": 12}, "done": true}],
  "log":     [{"pid": "…", "ridx": 0, "prev": null}]
}
```

- `rounds[].done` : manche complète (tous les joueurs présents) → scellée ;
  l'ajout d'un joueur après une manche scellée ne la rouvre pas.
- `log` : pile d'actions pour l'undo ciblé (200 max).

## 4. Comportements clés

- **Undo ↩ par joueur** : annule la dernière saisie du joueur sélectionné
  (restore la valeur précédente, dé-scelle/supprime la manche si vide).
- **Correction** : tap sur une valeur de l'historique → pavé en mode
  correction (pré-rempli), Valider réécrit.
- **Re-sélection d'un joueur compté** : pré-remplit sa valeur de manche
  (édition) ; ⌫ puis nouvelle valeur pour remplacer.
- Historique = chips « 12 + 14 − 10 » sous le nom, retour à la ligne
  automatique (jamais de scroll horizontal).
- Manche courante affichée dans l'entête (« Manche N »).

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
