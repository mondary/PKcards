# Australian Patience

**Nombre de cartes :** 52
**Difficulté :** —
**Type :** Patience, Solitaire
**But :** Un jeu de patience (solitaire) quelque peu plus exigeant que le Klondike ordinaire, qui peut aussi se jouer comme un jeu compétitif entre deux joueurs.

---

## Introduction

Australian Patience est fondamentalement un jeu de patience, mais il peut aussi se jouer de manière compétitive entre deux joueurs. Il vient d'Australie, où certains joueurs le préfèrent comme une alternative plus exigeante aux jeux de patience/solitaire plus connus comme le [Klondike](https://en.wikipedia.org/wiki/Klondike_%28solitaire%29).

Le but est de construire toutes les cartes par couleur sur quatre tas de fondation commençant par les As. Les déplacements dans le tableau sont similaires à ceux du [Scorpion](https://en.wikipedia.org/wiki/Scorpion_(solitaire)), en construisant vers le bas par couleur et en déplaçant toute colonne de cartes dont la carte du dessus se place sur la colonne sur laquelle elle est déplacée.

*Je tiens à remercier Michael Doer de m'avoir fait découvrir ce jeu.*

## Jeu en solitaire

Le Australian Patience à un joueur se joue avec un jeu international standard de 52 cartes, chaque couleur se classant du plus haut au plus bas R-D-V-10-9-8-7-6-5-4-3-2-A. Le joueur mélange les cartes et distribue un tableau de 28 cartes, composé de 7 colonnes de 4 cartes, toutes face visible. Les cartes de chaque colonne sont chevauchées de sorte que les trois premières cartes distribuées à la colonne sont partiellement **cachées**, laissant une extrémité de la carte visible pour que la couleur et la hauteur puissent être lues, et seule la quatrième carte de la colonne est entièrement **exposée**. Les 24 cartes restantes sont empilées faces cachées comme pioche. À côté de la pioche, il y a de la place pour un tas de défausse face visible, qui commence vide, et pour quatre tas de fondation face visibles, qui commencent également vides. Un exemple de mise en place initiale est montré ci-dessous.

![mise en page Australian Patience](../images/patience/australian.png)

Les déplacements possibles sont :

- Retourner la carte du dessus du tas de pioche et la placer face visible sur le tas de défausse.

- Déplacer n'importe quelle carte **exposée** du tableau ou la carte du dessus du tas de défausse vers un tas de fondation où elle se place. La première carte de chaque tas de fondation doit être un As, sur lequel les autres cartes de la même couleur sont placées en séquence ascendante.

- Déplacer **n'importe quelle** carte du tableau ou la carte du **dessus** du tas de défausse sur une carte **exposée** du tableau qui est la carte immédiatement supérieure de la même couleur, en la couvrant partiellement de manière à étendre la colonne. Si une carte cachée du tableau est déplacée, toutes les cartes qui la recouvrent doivent également être déplacées en groupe. Ce n'est que la carte la plus profondément enterrée du groupe et la carte exposée sur laquelle elle est déplacée qui doivent être adjacentes en couleur — le reste du groupe déplacé peut contenir n'importe quel nombre de cartes sans rapport.

- Déplacer n'importe quel Roi du tableau ou sur le dessus du tas de défausse vers une colonne vide. Si un Roi caché est déplacé, toutes les cartes qui le recouvrent doivent être déplacées en groupe.

Ainsi, certains déplacements possibles dans l'illustration ci-dessus sont :

- déplacer l'As de cœur vers un tas de fondation,

- déplacer le ![pique](../images/internat/spade.gif)9-![pique](../images/internat/spade.gif)A-![carreau](../images/internat/diamond.gif)10 comme un groupe sur le ![pique](../images/internat/spade.gif)10,

- déplacer le ![cœur](../images/internat/heart.gif)V-![trèfle](../images/internat/club.gif)6 comme un groupe sur le ![cœur](../images/internat/heart.gif)D,

- déplacer le ![pique](../images/internat/spade.gif)6-![pique](../images/internat/spade.gif)10-![pique](../images/internat/spade.gif)9-![pique](../images/internat/spade.gif)A-![carreau](../images/internat/diamond.gif)10 comme un groupe sur le ![pique](../images/internat/spade.gif)7.

Pour déplacer le ![trèfle](../images/internat/club.gif)A vers une fondation, il faudra l'exposer en déplaçant le ![carreau](../images/internat/diamond.gif)V sur le ![carreau](../images/internat/diamond.gif)D, ce qui n'est possible que lorsque le ![trèfle](../images/internat/club.gif)R aura été déplacé vers une colonne vide. Cela peut être réalisé comme suit :

- Déplacer toute la colonne ![trèfle](../images/internat/club.gif)A-![carreau](../images/internat/diamond.gif)V-![trèfle](../images/internat/club.gif)V-![carreau](../images/internat/diamond.gif)3 sur le ![trèfle](../images/internat/club.gif)2.

- Déplacer le groupe ![trèfle](../images/internat/club.gif)R-![trèfle](../images/internat/club.gif)4-![carreau](../images/internat/diamond.gif)8 dans la colonne de gauche maintenant vide.

- Déplacer le groupe ![carreau](../images/internat/diamond.gif)V-![trèfle](../images/internat/club.gif)V-![carreau](../images/internat/diamond.gif)3 sur le ![carreau](../images/internat/diamond.gif)D.

- Maintenant, le ![trèfle](../images/internat/club.gif)A est exposé et peut être déplacé vers une fondation, exposant le ![trèfle](../images/internat/club.gif)2 qui peut être déplacé sur le ![trèfle](../images/internat/club.gif)A. Le ![trèfle](../images/internat/club.gif)3 peut aussi être ajouté au tas de fondation de trèfle car il a été exposé par le déplacement du ![pique](../images/internat/spade.gif)6 et de son groupe.

Le ![trèfle](../images/internat/club.gif)4 n'est pas accessible pour le moment pour être ajouté au tas de fondation de trèfle car il n'y a pas de ![carreau](../images/internat/diamond.gif)9 dans la mise en place sur lequel le ![carreau](../images/internat/diamond.gif)8 peut être déplacé. De plus, la fondation de pique ne peut pas encore commencer car le ![pique](../images/internat/spade.gif)A est caché par le ![carreau](../images/internat/diamond.gif)10, qui ne peut pas être déplacé sur le ![carreau](../images/internat/diamond.gif)V tant qu'il n'y a pas de moyen de déplacer le ![trèfle](../images/internat/club.gif)V.

En utilisant le type de déplacement 1 ci-dessus, la carte du dessus de la pioche peut être exposée et placée sur le tas de défausse à tout moment. C'est une bonne idée de le faire dès le début du jeu, et chaque fois que le tas de défausse est vide, car la carte sur le tas de défausse peut augmenter le choix de déplacements. Si la carte du dessus du tas de défausse n'est pas utile, d'autres cartes peuvent être retournées et placées dessus. Cependant, cela doit être fait avec prudence. Dans Australian Patience, contrairement à beaucoup d'autres patiences, le joueur n'est **pas** autorisé à retourner le tas de défausse pour en faire une nouvelle pioche et la parcourir à nouveau. Ainsi, les cartes enfouies dans le tas de défausse ne peuvent être utilisées que lorsque les cartes au-dessus d'elles ont été déplacées vers le tableau ou les tas de fondation. Si la pioche est vide et qu'aucun autre déplacement n'est possible, le jeu est terminé.

Le joueur gagne si toutes les cartes sont déplacées vers les tas de fondation de sorte que chacun est couronné par un Roi. S'il reste des cartes dans le tas de défausse ou le tableau, le joueur a perdu.

## Jeu à deux joueurs au tour par tour

Deux jeux standard de 52 cartes sont nécessaires, un pour chaque joueur. Les jeux doivent avoir des dos différents, mais devraient si possible être de taille à peu près similaire. Le premier joueur est choisi par n'importe quelle méthode pratique. Par exemple, chacun peut tirer une carte d'un jeu mélangé et celui qui tire la carte la plus haute commence.

Les deux jeux sont mélangés séparément — pour éviter toute suspicion de mélange insuffisant, il est probablement préférable que chaque joueur mélange le jeu de l'autre joueur. Chaque joueur distribue ensuite depuis son propre jeu son propre tableau de 28 cartes, en empilant le reste face cachée pour former sa pioche de 24 cartes, avec de la place pour son tas de défausse à côté. Entre les deux tableaux, il devrait y avoir de la place pour huit tas de fondation, tous disponibles pour les deux joueurs.

Un tour consiste à effectuer autant de déplacements de types 2, 3 et 4 que souhaité — c'est-à-dire déplacer des cartes dans le tableau, du tas de défausse vers le tableau ou du tas de défausse ou du tableau vers un tas de fondation. Chaque joueur ne peut se déplacer que dans son propre tableau et tas de défausse, mais il peut jouer sur n'importe lequel des huit tas de fondation — il y en aura deux pour chaque couleur — y compris en ajoutant à un tas de fondation commencé par l'autre joueur.

Lorsque le joueur n'a plus de déplacements de type 2, 3 ou 4 qu'il souhaite effectuer, il retourne la carte du dessus de sa pioche et la place face visible sur son tas de défausse (type de déplacement 1). Cela termine son tour et c'est au tour de l'autre joueur.

Lorsque les joueurs n'ont plus de cartes dans leur pioche, le jeu continue comme avant, mais maintenant les joueurs passent simplement pour terminer leur tour. Lorsque les deux joueurs passent successivement sans faire d'autre déplacement, le jeu est terminé. Dans ce jeu compétitif, il est rare que les huit tas de fondation soient tous complétés — normalement les joueurs auront encore des cartes injouables dans leurs tas de défausse ou tableaux à la fin du jeu.

Les cartes des tas de fondation sont alors retournées et triées selon leur dos. Le joueur qui a contribué le plus de cartes aux tas de fondation est le gagnant.

## Jeu à deux joueurs simultané

Dans la version simultanée, la mise en place est la même que pour la version au tour par tour décrite ci-dessus, mais les deux joueurs commencent à jouer en même temps et essaient de terminer le jeu le plus rapidement possible.

Comme dans le jeu au tour par tour, chaque joueur a l'utilisation exclusive de son propre tableau, de sa pioche et de son tas de défausse, mais ils peuvent tous deux jouer sur n'importe lequel des huit tas de fondation. Si les deux joueurs essaient de jouer des cartes équivalentes sur le même tas de fondation en même temps, celui qui y arrive en premier laisse sa carte sur le tas, et l'autre joueur doit reprendre sa carte et la remettre où elle était dans sa mise en place. Il pourra plus tard avoir l'opportunité de la jouer sur l'autre tas de fondation de la même couleur.

Le jeu continue jusqu'à ce qu'aucun des deux joueurs ne soit capable ni disposé à faire d'autre déplacement. Les cartes appartenant à chaque joueur dans les tas de fondation sont alors comptées et celui qui a contribué le plus de cartes gagne.

## Logiciel et jeux en ligne

Sur SolitaireNetwork, la version solo de l'[Australian Patience](https://www.solitairenetwork.com/solitaire/australian-patience-solitaire-game.html) peut être jouée en ligne dans un navigateur web.

L'Australian Patience en solo peut aussi être jouée sur [Solitaire Paradise](https://www.solitaireparadise.com/games_list/australian-patience.html).
