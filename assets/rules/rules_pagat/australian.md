# Australian Patience

**Nombre de cartes :** 52
**Difficulté :** —
**Type :** Patience, Solitaire
**But :** A solitaire card game that is somewhat more challenging than ordinary Klondike, and can also be played as a competitive game between two players.

---

## Introduction

Australian Patience is basically a solitaire game, but can also be played competitively between two players. It comes from Australia, where some players prefer it as a more challenging alternative to better-known patience/solitaire games such as [Klondike](https://en.wikipedia.org/wiki/Klondike_%28solitaire%29).

The aim is to build all the cards by suit onto four foundation piles beginning with the Aces. The moves are within the tableau similar to those in [Scorpion](https://en.wikipedia.org/wiki/Scorpion_(solitaire)), building downwards in suit and moving any column of cards whose top card fits onto the column it is moved to. 

*I would like to thank Michael Doer for introducing me to this game.*

## Solitaire Game

One-player Australian Patience is played with a standard international pack of 52 cards, each suit ranking from high to low K-Q-J-10-9-8-7-6-5-4-3-2-A. The player shuffles the cards and deals a tableau of 28 cards, consisting of 7 columns of 4 cards, all face up. Cards in each column are overlapped so that the first three cards dealt to the column are partly **covered**, leaving one end the card visible so that the suit and rank can be read, and only the fourth card of the column is fully **exposed**. The remaining 24 cards are stacked face down as a draw pile. Beside the draw pile there is space for a face up waste pile, which begins empty, and for four face up foundation piles, which also begin empty. An example initial layout is shown below.

![australian patience layout](../images/patience/australian.png)

The possible moves are:

- To turn the top card of the stock pile and place it face up on top of the waste pile. 

- To move any **exposed** card from the tableau or the top card of the waste pile to a foundation pile where it fits. The first card of each foundation pile must be an Ace, upon which the other cards of the same suit are placed in ascending sequence.

- To move **any** card in the tableau or the **top** card of the waste pile onto an **exposed** card in the tableau which is the next higher card of the same suit, partly covering it so as to extend the column. If a covered card from the tableau is moved, all the cards covering it must also be moved as a group. It is only the deepest buried card of the group and the exposed card that it is moved onto that must be adjacent in suit - the remainder of the moved group can contain any number of unrelated cards.

- To move any King in the tableau or on top of the waste pile to an empty column. If a covered King is moved, all the cards covering it must be moved as a group.

So some possible moves in the above illustration are:

- to move the Ace of hearts to a foundation pile,

- to move the ![spade](../images/internat/spade.gif)9-![spade](../images/internat/spade.gif)A-![diamond](../images/internat/diamond.gif)10 as a group onto the ![spade](../images/internat/spade.gif)10,

- to move the ![heart](../images/internat/heart.gif)J-![club](../images/internat/club.gif)6 as a group onto the ![heart](../images/internat/heart.gif)Q,

- to move the ![spade](../images/internat/spade.gif)6-![spade](../images/internat/spade.gif)10-![spade](../images/internat/spade.gif)9-![spade](../images/internat/spade.gif)A-![diamond](../images/internat/diamond.gif)10 as a group onto the ![spade](../images/internat/spade.gif)7.

In order to move the ![club](../images/internat/club.gif)A to a foundation it will be necessary to expose it by moving the ![diamond](../images/internat/diamond.gif)J onto the ![diamond](../images/internat/diamond.gif)Q, which is only possible when the ![club](../images/internat/club.gif)K has been moved away into an empty column. This could be achieved as follows: 

- Move the whole column ![club](../images/internat/club.gif)A-![diamond](../images/internat/diamond.gif)J-![club](../images/internat/club.gif)J-![diamond](../images/internat/diamond.gif)3 onto the ![club](../images/internat/club.gif)2.

- Move the group ![club](../images/internat/club.gif)K-![club](../images/internat/club.gif)4-![diamond](../images/internat/diamond.gif)8 into the now empty left-hand column 

- Move the group ![diamond](../images/internat/diamond.gif)J-![club](../images/internat/club.gif)J-![diamond](../images/internat/diamond.gif)3 onto the ![diamond](../images/internat/diamond.gif)Q

- Now the ![club](../images/internat/club.gif)A is exposed and can be moved to a foundation, exposing the ![club](../images/internat/club.gif)2 which can be moved onto the ![club](../images/internat/club.gif)A. The ![club](../images/internat/club.gif)3 can also be added to the club foundation pile as it was exposed by moving the ![spade](../images/internat/spade.gif)6 and its group. 

The ![club](../images/internat/club.gif)4 is not accessible at the moment to add to the club foundation pile because there is no ![diamond](../images/internat/diamond.gif)9 in the layout onto which the ![diamond](../images/internat/diamond.gif)8 can be moved. Also the spade foundation cannot be started yet because the ![spade](../images/internat/spade.gif)A is covered by the ![diamond](../images/internat/diamond.gif)10, which cannot be moved onto the ![diamond](../images/internat/diamond.gif)J until there is a way to move the ![club](../images/internat/club.gif)J.

Using move type 1 above, the top card of the draw pile can be exposed and placed on the waste pile at any time. It is a good idea to do this one right at the start of the game, and whenever the waste pile is empty, as the card on the waste pile may increase the choice of moves. If the card on top of the waste pile is not useful, further cards may be turned and placed on top of it. However, this must be done with caution. In Australian Patience, unlike many other solitaires, the player is **not** allowed to turn over the waste pile to make a new draw pile and run through it again. So any cards buried in the waste pile can only be used all when the cards on top of them have been moved to the tableau or foundations piles. If the draw pile is empty and no more moves are possible, the game is over.

The player wins if all the cards are moved to the foundation piles so that each is topped with a King. If any cards remain in the waste pile or tableau the player has lost. 

## Two-Player Turn-Based Game

Two standard 52-card packs are needed, one for each player. The packs must have different backs from each other, but should if possible be about the same size. The first player is chosen by any convenient method. For example each can draw a cards from a shuffled deck and whoever draws the higher card starts. 

The two decks are shuffled separately - to avoid any suspicion of inadequate shuffling it is probably a good idea for each player to shuffle the other player's deck. Each player then deals from their own deck their own tableau of 28 cards, stacking the remainder face down to form their 24-card draw pile, with space for their waste pile beside it. Between the two tableaux there should be space for eight foundation piles, all of which are available to both players.

A turn consists of making any number of moves of types 2, 3 and 4 as described above - that is moving cards around the tableau, from the waste pile to the tableau or from the waste pile or tableau to a foundation pile. Each player may only move in their own tableau and waste pile, but they may play to any of the eight foundation piles - there will be two for each suit - including adding to a foundation pile that was started by the other player.

When the player has no more moves of type 2, 3 or 4 that they wish to make, they turn the top card of their draw pile and place it face up on their waste pile (move type 1). This ends their turn and it is the other player's turn to play.

When the players have no cards left in their draw piles, the game continues as before, but now players simply pass to end their turn. When both players pass in succession without making any further moves, the game is over. In this competitive game it is rare for all eight foundation piles to be completed - normally the players will have some unplayable cards left in their waste piles or tableaux at the end of the game.

Now the cards in the foundation piles are turned over and sorted according to their backs. The player who has contributed most cards to the foundation piles is the winner. 

## Two-Player Simultaneous Game

In the simultaneous version, the setup is the same as for the turn-based version described above, but both players start playing at the same time and try to finish the game as fast as possible. 

As in the turn-based game each player has exclusive use of their own tableau, draw pile and waste pile, but they can both play to any of the eight foundation piles. If both players try to play equivalent cards to the same foundation pile at the same time, whoever gets there first leaves their card on the pile, and the other player must take their card back and replace it where it was in their layout. They may later get an opportunity to play it on the other foundation pile of the same suit.

The game continues until neither player is able and willing to make any further moves. The cards belonging to each player in the foundation piles are then counted and whoever has contributed more cards to them wins. 

## Software and Online Games

On SolitaireNetwork, the solo version of [Australian Patience](https://www.solitairenetwork.com/solitaire/australian-patience-solitaire-game.html) can be online in a web browser.

Solo Australian Patience can also be played at [Solitaire Paradise](https://www.solitaireparadise.com/games_list/australian-patience.html).

Home Page > Classified Index > Layout Group > Competitive Patience > Australian
