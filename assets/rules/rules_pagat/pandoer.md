# Pandoer

**Difficulté :** —
**Type :** Jass/Piquet, Plis
**But :** Un jeu de Jass à 4 joueurs avec des partenariats variables et un large choix de contrats pouvant être enchéris.

---

*Cette description a été rédigée par Nick Wedd et éditée par John McLeod.*

## Introduction : joueurs, cartes et donne

Le Pandoeren est un jeu de cartes de plis pour quatre joueurs qui était populaire aux Pays-Bas. On m'a récemment dit qu'il est encore beaucoup joué à Heerlen dans le sud, mais je n'ai pas encore pu vérifier à quel point ce jeu correspond à la version plus ancienne décrite ici.

On utilise un jeu de 33 cartes, composé de A R D V 10 9 8 7 dans quatre couleurs, la 33e carte étant le 6 de cœur.

Les cartes sont distribuées dans le sens des aiguilles d'une montre : quatre à chaque joueur, puis une face visible au milieu de la table, puis quatre à chaque joueur. Aux mains suivantes, la donne tourne autour de la table.

## Les enchères

Après la donne, les enchères commencent. En commençant par le joueur de tête, chaque joueur peut faire l'une des enchères listées ci-dessous, ou passer. Certains contrats sont des contrats « à chiffre », dont le but est de prendre au moins le nombre de points de cartes enchéri ; d'autres ont des objectifs différents comme gagner ou perdre tous les plis. Après une enchère, seules les enchères supérieures sont disponibles. Une enchère supérieure est soit une enchère similaire avec plus de points (par ex. 180 surenchérit 170), soit une enchère plus bas dans la liste. Notez que les enchères plus bas dans la liste ne sont pas toujours plus difficiles à réaliser, et ne valent pas toujours plus. Si les quatre joueurs passent, les cartes sont jetées et le joueur suivant donne. Si quelqu'un enchérit, les enchères continuent autour de la table pendant autant de tours que nécessaire jusqu'à ce qu'il y ait trois passes consécutives, le dernier enchérisseur devenant alors le déclarant.

Le déclarant doit jouer le contrat nommé dans sa dernière enchère. Il ramasse la carte au milieu de la table, la met dans sa main, et défausse une carte (peut-être la même) de sa main face cachée. Il nomme ensuite la couleur d'atout (s'il y en a une), appelle un partenaire (si approprié pour le contrat), et entame le premier pli.

## Les règles du jeu

Le déclarant entame le premier pli. Le gagnant d'un pli entame le suivant.

S'il n'y a pas de couleur d'atout, un joueur doit suivre la couleur si possible. Sinon, il peut jouer n'importe quelle carte.

S'il y a une couleur d'atout, un joueur qui peut suivre doit *soit* suivre *soit* jouer un atout si possible. Un joueur qui ne peut pas suivre peut couper ou se défausser d'une autre couleur, mais si une couleur non-atout a été demandée et coupée, il est interdit à un joueur ultérieur de sous-couper, sauf s'il n'y a pas d'alternative. Le jeu du valet d'atout, appelé le *jas*, n'est jamais forcé : un joueur qui détient cette carte peut toujours jouer comme s'il ne la détenait pas (sauf au dernier pli, bien sûr).

Pour la prise de plis, les cartes des couleurs non-atout sont classées de haut en bas : A-R-D-V-10-9-8-7-(6). Lorsqu'il y a une couleur d'atout, le valet et le 9 d'atout sont promus comme les meilleures cartes : l'ordre de la couleur d'atout de haut en bas est V-9-A-R-D-10-8-7-(6).

Un pli est remporté par le plus haut atout qu'il contient ; ou s'il n'y a pas d'atout, par la plus haute carte de la couleur demandée. L'ordre des cartes est donné dans la section suivante.

## Comptage des points

Dans les contrats à chiffre, il y a trois façons de compter les points de cartes : les plis, le roem et le stuk. Elles sont décrites dans l'ordre.

### Les plis

Dans la couleur d'atout, s'il y en a une, les cartes sont classées dans cet ordre (suivi de leur valeur en points) :

**V**(20), **9**(14), **A**(11), **R**(3), **D**(2), **10**(10), **8**(0), **7**(0), **6**(0).

Dans les autres couleurs, elles sont classées (et rapportent) :

**A**(11), **R**(3), **D**(2), **V**(1), **10**(10), **9**(0), **8**(0), **7**(0), **6**(0).

Le déclarant compte les points de cartes dans les plis qu'il a remportés. Il ne compte pas la valeur de la carte qu'il a défaussée. De plus, il y a 5 points de cartes pour remporter le dernier pli. Il y a donc 146 points de cartes à gagner lors du jeu de la main.

### Roem

Une autre source de points est le *roem* : certaines combinaisons de cartes détenues dans la main d'un joueur. Par ordre croissant, les roem sont :

| Séquence de 3 cartes dans une couleur |   .....   | 20 points de cartes |
| Séquence de 4 cartes dans une couleur |   .....   | 50 |
| Séquence de 5 cartes dans une couleur |   .....   | 100 |
| 4 dames |   .....   | 100 |
| 4 rois |   .....   | 100 |
| 4 as |   .....   | 100 |
| Séquence de 6 cartes dans une couleur |   .....   | 120 |
| Séquence de 7 cartes dans une couleur |   .....   | 140 |
| Séquence de 8 cartes dans une couleur |   .....   | 160 |
| 4 valets |   .....   | 200 |

Pour former et comparer les séquences, les cartes sont classées de haut en bas dans l'ordre A-R-D-V-10-9-8-7-6 que la couleur soit atout ou non. Un roem est meilleur s'il est plus bas dans la liste ci-dessus. Pour départager les séquences de même longueur, la séquence contenant la carte la plus haute (dans l'ordre donné ici) est meilleure. Si cela ne suffit pas, la séquence dans la couleur la plus haute gagne, les couleurs étant classées pique (le plus haut), cœur, carreau, trèfle.

Immédiatement après avoir entamé le premier pli, si le contrat joué implique des points, le déclarant peut annoncer la valeur totale du roem (s'il y en a) dans sa main. Ces points, plus la valeur de tout roem dans la main du partenaire du déclarant, seront ajoutés au total des points de cartes gagnés par l'équipe du déclarant, sauf s'ils sont déniés avec succès par un adversaire. Tout adversaire du déclarant qui a participé aux enchères — c'est-à-dire qui n'a pas simplement passé à chaque occasion — et qui a un roem, peut tenter de dénier le roem du déclarant. Cela se fait en demandant au déclarant de spécifier sa meilleure instance unique de roem — la réponse pourrait être « quatre à partir de la dame » (c'est-à-dire D-V-10-9) ou « quatre dames ». Si l'adversaire a une meilleure instance de roem, il l'annonce, déniant ainsi le roem du déclarant ; dans ce cas, l'équipe du déclarant ne marque aucun point pour le roem. Si le roem du déclarant n'est pas dénié avec succès, l'équipe du déclarant ajoutera les points à ses points de cartes gagnés en plis.

Le partenaire du déclarant peut également annoncer du roem ; cela se fait dès que le partenaire est identifié. Si le déclarant a déjà annoncé du roem et qu'il n'a pas été dénié, le roem annoncé par le partenaire est ajouté aux points de l'équipe du déclarant. Si le déclarant n'a pas annoncé de roem, le partenaire peut quand même annoncer du roem lorsque les partenariats sont clairs, et cette annonce peut être déniée par tout adversaire ayant participé aux enchères qui a une meilleure instance unique de roem que le partenaire. Si non dénié, le roem est ajouté aux points de l'équipe du déclarant.

Il est aussi possible, bien qu'inhabituel, que le roem du déclarant ait été dénié, mais que le partenaire du déclarant ait une meilleure instance de roem. Dans ce cas, le partenaire l'annonce lorsque les partenariats sont clarifiés, et si les adversaires ayant enchéri ne peuvent pas la dénier, le roem du déclarant et du partenaire deviennent tous deux valides et sont ajoutés au score de points de l'équipe du déclarant.

### Stuk

Indépendamment du roem est le *stuk*, qui peut être annoncé par le déclarant ou le partenaire du déclarant détenant le roi et la dame d'atout, à tout moment avant que la deuxième de ces cartes ne soit jouée. Il ne peut pas être dénié, et vaut 20 points de cartes.

## Contrats

Les enchères possibles sont les suivantes :

| Score | Contrat | Commentaire | partenaire ? | atout ? |
| 1 | 120, 130, 140 | Appeler un as | Oui | Oui |
| 3 | Piccolo | Ne gagner que le premier pli | Non | Oui |
| 3 | Misère | Perdre tous les plis | Non | Oui |
| 2 | 150, 160 | Appeler un as | Oui | Oui |
| 2 | Kereltje | Appeler le Jas. Gagner tous les plis | Oui | Oui |
| 2 | Zwabber | Appeler un as. Gagner 4+ tous les plis | Oui | Non |
| 3 | 170, 180, 190 | Appeler un as | Oui | Oui |
| 5 | Solo-zwabber | Gagner tous les plis | Non | Non |
| 6 | Piccolo Ouvert | Gagner le premier pli, perdre les autres, exposé | Non | Oui |
| 4 | 200, 210, 220, etc. | Appeler un as | Oui | Oui |
| 6 | Misère Ouvert | Perdre tous les plis, exposé | Non | Oui |
| 9 | Stil Praatje | Perdre tous les plis, tous exposés | Non | Oui |
| 5 | Pandoer | Appeler un as. Gagner tous les plis | Oui | Oui |
| 5 | Pandoer+20, 40, etc. | comme Pandoer, avec roem/stuk | Oui | Oui |
| 9 | Praatje | comme Stil Praatje avec discussion | Non | Oui |
| 10 | Privé | Gagner tous les plis | Non | Oui |

Dans les contrats à chiffre, **120**, **130**, ... **220**, etc., le déclarant nomme l'atout et appelle l'as d'une couleur (par exemple il pourrait dire « les carreaux sont atout et l'as de pique s'y joint »). Il est permis d'appeler l'as d'atout. Si le déclarant détient les quatre as (ou en détient 3 et a défaussé le quatrième), il appelle un roi à la place. Le détenteur de la carte appelée est le partenaire du déclarant. Le partenaire ne révèle pas immédiatement son identité, mais empile les plis pour le côté du déclarant. Les partenariats sont donc connus dès que le déclarant gagne un pli et que son partenaire le ramasse, ou lorsque la carte appelée est jouée si cela se produit plus tôt. Si le déclarant et son partenaire réalisent au moins le nombre de points de cartes de l'enchère, en plis, roem et stuk, ils ont réussi, sinon ils ont échoué.

Le **Piccolo** est un contrat pour gagner le premier pli et perdre tous les autres, en jouant seul. Le **Piccolo Ouvert** est identique, mais la main du déclarant est exposée lorsqu'il joue au **deuxième** pli. Certains jouent que le but du Piccolo et du Piccolo Ouvert est de gagner **n'importe quel** pli, mais cela rend ces contrats très faciles ; une grande variété de mains contenant un valet auront alors de bonnes chances de réussir un Piccolo.

La **Misère** est un contrat pour ne remporter aucun pli. La **Misère Ouvert** est identique, mais la main du déclarant est exposée lorsqu'il joue au **deuxième** pli.

Au **Kereltje**, le déclarant nomme l'atout et le détenteur du jas est le partenaire du déclarant. Le partenaire ne l'admet pas immédiatement, mais révèle son identité en conservant les plis pour le côté du déclarant. Ils doivent gagner tous les plis ensemble.

Au **Zwabber**, il n'y a pas d'atout. Le déclarant appelle un as, ou s'il détient 4 as, un roi, comme dans les contrats à chiffre. Le détenteur de la carte appelée est son partenaire. Le partenaire ne l'admet pas immédiatement, mais révèle son identité en conservant les plis pour le côté du déclarant. Le déclarant doit gagner les quatre premiers plis lui-même. Lui et son partenaire doivent gagner tous les plis ensemble. Si le déclarant échoue à gagner les quatre premiers plis, le contrat est perdu même si l'équipe du déclarant peut gagner les 8 plis — le déclarant et le partenaire perdent tous les deux.

Au **Solo-zwabber**, il n'y a pas d'atout. Le déclarant doit gagner tous les plis.

Au **Stil Praatje**, le déclarant nomme l'atout et toutes les mains sont exposées lorsque le déclarant entame le premier pli. Il doit perdre tous les plis.

Au **Pandoer**, le déclarant nomme l'atout et appelle un as, ou s'il détient 4 as, un roi, comme dans les contrats à chiffre. Le détenteur de la carte appelée est son partenaire. Le partenaire ne l'admet pas immédiatement, mais révèle son identité en conservant les plis pour le côté du déclarant. Le déclarant et son partenaire doivent gagner tous les plis ensemble.

**Pandoer + *n*** est identique à Pandoer, sauf que le déclarant et son partenaire doivent réaliser *n* points supplémentaires provenant du roem et du stuk, qui sont annoncés et déniés de la manière habituelle.

Le **Praatje** est similaire au Stil Praatje, mais il n'est pas Stil (silencieux). Le déclarant nomme l'atout et entame le premier pli, puis toutes les cartes des quatre joueurs sont exposées. Le but du déclarant est de perdre chaque pli et à partir de ce moment, la discussion est autorisée. Les défenseurs peuvent parler de la façon dont ils planifient de faire gagner un pli au déclarant, et se dire quelles cartes jouer. Ils ne sont pas autorisés à toucher les cartes sauf celles de leur propre main, ni à prendre des notes, juste à parler (*praatje* signifie discussion). Il est parfois étonnamment difficile de se mettre d'accord sur la meilleure défense dans un praatje — la possibilité de couper tout en pouvant suivre ouvre de nombreuses possibilités — et la discussion peut durer un certain temps.

Au **Privé**, le déclarant joue seul, avec une couleur d'atout, et doit gagner tous les plis.

## Le comptage des points

Dans les contrats en partenariat, si le déclarant et son partenaire réussissent leur contrat, chacun ajoute le score de ce contrat (tel que donné dans la colonne de gauche du tableau) à son score ; s'ils échouent, chacun soustrait le score du contrat de son score. Dans les contrats sans partenariat, seul le déclarant a le score du contrat ajouté ou soustrait de son score.

## Autres sites Pandoeren

Une description d'une autre version du Pandoeren jouée en Frise-Occidentale avec des enchères et un comptage différents peut être trouvée sur ces copies d'archives des pages d'Arnaud Vink donnant les [règles de jeu](https://web.archive.org/web/20140703215838/http://www.pandoeren.nl:80/spelregels.htm) et les [contrats](https://web.archive.org/web/www.pandoeren.nl/spelletjes.htm) (en néerlandais) et cette traduction anglaise de Lukas Borst.

Voici une copie d'archive d'une description en néerlandais du [Tachtigen](https://web.archive.org/web/20070813115130/www.langen.dds.nl/tachtig1.htm), une variante du Pandoeren jouée à l'Université d'Amsterdam. Une [traduction anglaise](https://web.archive.org/web/20070812204301/www.langen.dds.nl/tachtig2.htm) est également disponible.

Voici une autre page décrivant une version à trois joueurs du [Tachtigen](http://www.beukie.org/duurt/tachtigen.htm).
