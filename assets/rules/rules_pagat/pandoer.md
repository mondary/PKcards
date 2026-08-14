# Pandoer

**Difficulté :** —
**Type :** Jass/Piquet, Plis
**But :** A 4-player Jass game with variable partnerships and a wide choice of contracts that can be bid.

---

*This description was written by Nick Wedd and edited by John McLeod.*

## Introduction: players, cards and deal

Pandoeren is a trick-taking card game for four players which used to be popular in the Netherlands. I have recently been told that it is still much played in Heerlen in the south, but I have not yet been able to check how closely that game corresponds to the older version described here. 

A 33-card pack is used, consisting of A K Q J 10 9 8 7 in four suits, with the 33rd card being the 6 of hearts.

The cards are dealt clockwise, with four to each player, then one face-up in the middle of the table, then four to each player. On subsequent hands, the deal will rotate around the table.

## The bidding

After the deal the bidding begins. Starting with forehand, each player may make any of the bids listed below, or may pass. Some of the contracts are "number" contracts, in which the object tis to take at least the number of card points bid; others have different objectives such as winning or losing all the tricks. After a bid has been made, only higher bids are available. A higher bid is either a similar bid with more points (e.g. 180 outbids 170) or a bid from further down the list. Note that bids lower down the list are not always more difficult to make, and are not always worth more. If all four players pass, the cards are thrown in and the next player deals. If someone bids, the bidding continues round the table for as many circuits as necessary until there have been three consecutive passes, when the final bidder becomes declarer.

The declarer must play the contract named in her last bid. She picks up the card in the middle of the table, puts it in her hand, and discards a card (maybe the same one) from her hand face-down. She then names the trump suit (if any), calls a partner (if appropriate to the contract), and leads to the first trick.

## The rules of play

The declarer leads to the first trick. The winner of a trick leads to the next one.

If there is no trump suit, a player must follow suit if possible. Otherwise she may play any card.

If there is a trump suit, a player who can follow suit must *either* follow suit *or* play a trump if possible. A player who cannot follow suit may trump or discard from another suit, but if a non-trump suit has been led and trumped, it is illegal for a later player to undertrump, unless there is no alternative. The play of the jack of trumps, known as the *jas*, is never forced: a player who holds this card can always play as if she did not hold it (except on the last trick, of course).

For trick-taking purposes, cards in non-trump suits rank from high to low: A-K-Q-J-10-9-8-7-(6). When there is a trump suit, the trump jack and nine are promoted to be the best cards: the rank of the trump suit from high to low is J-9-A-K-Q-10-8-7-(6).

A trick is won by the highest trump in it; or if there is no trump, by the highest card in the suit led. The ranking of the cards is given in the next section.

## Counting points

In the number contracts, there are three ways of scoring card-points: tricks, roem, and stuk. These are described in order.

### Tricks

In the trump suit, if there is one, the cards rank in this order (followed by their card-point values):

**J**(20), **9**(14), **A**(11), **K**(3), **Q**(2), **10**(10), **8**(0), **7**(0), **6**(0).

In other suits, they rank (and score):

**A**(11), **K**(3), **Q**(2), **J**(1), **10**(10), **9**(0), **8**(0), **7**(0), **6**(0).

Declarer counts up the card-points in the tricks which she has won. She does not count the value of the card which she discarded. In addition, there are 5 card-points for winning the last trick. Thus there are 146 card-points to be won in the play of the hand.

### Roem

Another source of points is *roem*: certain combinations of cards held in a player's hand. In ascending order, the roem are:

| Sequence of 3 cards in suit |   .....   | 20 card-points |
| Sequence of 4 cards in suit |   .....   | 50 |
| Sequence of 5 cards in suit |   .....   | 100 |
| 4 queens |   .....   | 100 |
| 4 kings |   .....   | 100 |
| 4 aces |   .....   | 100 |
| Sequence of 6 cards in suit |   .....   | 120 |
| Sequence of 7 cards in suit |   .....   | 140 |
| Sequence of 8 cards in suit |   .....   | 160 |
| 4 jacks |   .....   | 200 |

For the purpose of forming and comparing sequences, the cards rank from high to low in the order A-K-Q-J-10-9-8-7-6 whether or not the suit is trumps. Roem is better if it is further down the above list. To resolve ties between sequences of the same length, the sequence containing the higher card (in the order given here) is better. If this does not break the tie, the sequence in the higher suit wins, the suits ranking spades (highest), hearts, diamonds, clubs.

Immediately after leading to the first trick, if the contract being played is one involving points, the declarer may announce the total value of roem (if any) in her hand. These points, plus the value of any roem in declarer's partner's hand, will be added to the total card points won by the declarer's team unless it is successfully denied by an opponent. Any opponent of the declarer who has taken part in the bidding - that is, not just said pass at every opportunity - and who has roem, can attempt to deny the declarer's roem. This is done by asking declarer to specify her highest single instance of roem - the answer might be "four from the queen" (i.e. Q-J-10-9) or "four queens". If the opponent has a higher instance of roem, she announces what it is, so denying the declarer's roem; the declarer's team does not score any points for roem in this case. If the declarer's roem is not successfully denied, the declarer's team will add the points for it to the card points they win in tricks.

The declarer's partner can also announce roem; this is done as soon as it becomes clear who the partner is. If the declarer has already announced roem and it was not denied, the partner's announced roem is added to declarer's team's points. If the declarer did not announce roem, partner can still announce roem when the partnerships become clear, and this announcement can be denied by any opponent who has taken part in the bidding who has a higher single instance of roem than the partner. If not denied, the roem is added to the declarer's team's points.

It is also possible, though unusual, that the declarer's roem was denied, but that declarer's partner has a better instance of roem. In that case the partner announces it when the partnerships are clarified, and if the opponents who have bid cannot deny it, both the declarer's and the partner's roem become valid and are added to declarer's team's point score.

### Stuk

Independent of roem is *stuk*, which may be announced by the declarer or the declarer's partner holding the king and queen of trumps, at any time before the second of these cards are played. It cannot be denied, and is worth 20 card-points. 

## Contracts

The possible bids are as follows:

| Score | Contract | Comment | partner? | trumps? |
| 1 | 120, 130, 140 | Call an ace | Yes | Yes |
| 3 | Piccolo | Win first trick only | No | Yes |
| 3 | Misère | Lose all tricks | No | Yes |
| 2 | 150, 160 | Call an ace | Yes | Yes |
| 2 | Kereltje | Call the Jas. Win all tricks | Yes | Yes |
| 2 | Zwabber | Call an ace. Win 4+all tricks | Yes | No |
| 3 | 170, 180, 190 | Call an ace | Yes | Yes |
| 5 | Solo-zwabber | Win all tricks | No | No |
| 6 | Piccolo Ouvert | Win first trick, lose the others, exposed | No | Yes |
| 4 | 200, 210, 220, etc. | Call an ace | Yes | Yes |
| 6 | Misère Ouvert | Lose all tricks, exposed | No | Yes |
| 9 | Stil Praatje | Lose all tricks, all exposed | No | Yes |
| 5 | Pandoer | Call an ace. Win all tricks | Yes | Yes |
| 5 | Pandoer+20, 40, etc. | as Pandoer, with roem/stuk | Yes | Yes |
| 9 | Praatje | as Stil Praatje with talking | No | Yes |
| 10 | Privé | Win all tricks | No | Yes |

In the number contracts, **120**, **130**,.. **220**, etc., declarer names trumps and calls the ace of a suit (for example she might say "diamonds are trumps and the ace of spades goes along"). It is permissible to call the ace of trumps. If the declarer holds all four aces (or holds 3 and discarded the fourth) she calls a king instead. The holder of the called card is the declarer's partner. The partner does not reveal her identity immediately, but stacks the tricks for declarer's side. Therefore the partnerships are known as soon as the declarer wins a trick and her partner picks it up, or when the called card is played if this is sooner. If declarer and her partner make at least the bid number of card-points, in tricks, roem and stuk, they have succeeded, otherwise they have failed.

**Piccolo** is a contract to win the first trick and lose all the others, playing alone. **Piccolo Ouvert** is the same, but declarer's hand is exposed as she plays to the **second** trick. Some people play the object of Piccolo and Piccolo Ouvert as being to win **any** one trick, but this makes these contracts very easy; a wide range of hands containing a jack will then stand a good chance of making Piccolo.

**Misère** is a contract to win no trick. **Misère Ouvert** is the same, but declarer's hand is exposed as she plays to the **second** trick.

In **Kereltje**, the declarer names trumps and the holder of the jas is declarer's partner. The partner does not admit to this immediately, but reveals her identity by keeping the tricks for declarer's side. They must win all the tricks between them.

In **Zwabber** there are no trumps. Declarer calls an ace, or if holding 4 aces a king, as in the number contracts. The holder of called card is her partner. The partner does not admit to this immediately, but reveals her identity by keeping the tricks for declarer's side. Declarer must win the first four tricks herself. She and her partner must win all the tricks between them. If the declarer fails to win the first four tricks, the contract is lost even if the declarer's team can win all 8 tricks - both the declarer and the partner lose.

In **Solo-zwabber** there are no trumps. Declarer must win all the tricks. 

In **Stil Praatje**, the declarer names trumps and all the hands are exposed as declarer leads to the first trick. She must lose all the tricks.

In **Pandoer**, declarer names trumps and calls an ace, or if holding 4 aces a king, as in the number contracts. The holder of called card is her partner. The partner does not admit to this immediately, but reveals her identity by keeping the tricks for declarer's side. Declarer and her partner must win all the tricks between them.

**Pandoer + *n*** is the same as Pandoer, except that Declarer and her partner must achieve an additional *n* points from roem and stuk, which is announced and denied in the usual way.

**Praatje** is similar to Stil Praatje, but is not Stil (silent). The declarer names trumps and leads to the first trick and then all the cards of the four players are exposed. Declarer's aim is to lose every trick and from this pioint on, discussion is allowed. The defenders can talk about how they plan to make declarer win a trick, and tell each other which cards to play. They are not allowed to touch the cards except to cards from their own hand, nor to make notes, just to talk (*praatje* means talk or chat). It is sometimes surprisingly difficult to agree on the best defence in a praatje - the ability to trump while able to follow suit opens up many possibilities - and so the discussion can go on for some time.

In **Priv&eacute** declarer plays alone, with a trump suit, and must win all the tricks.

## The Scoring

In partnership contracts, if declarer and her partner succeed in their contract, they each add the score for that contract (as given in the left hand column of the table) to their scores; if they fail, they each subtract the score for the contract from their scores. In non-partnership contracts, declarer alone has the score for the contract added to or subtracted from her score. 

## Other Pandoeren WWW Sites

A description of another version of Pandoeren played in West Friesland with different bids and scoring can be found on these archive copies of Arnaud Vink's pages giving [rules of play](https://web.archive.org/web/20140703215838/http://www.pandoeren.nl:80/spelregels.htm) and [contracts](https://web.archive.org/web/www.pandoeren.nl/spelletjes.htm) (in Dutch) and this English translation by Lukas Borst.

Here is an archive copy of a description in Dutch of [Tachtigen](https://web.archive.org/web/20070813115130/www.langen.dds.nl/tachtig1.htm), a variant of Pandoeren played at the University of Amsterdam. An [English translation](https://web.archive.org/web/20070812204301/www.langen.dds.nl/tachtig2.htm) is also available.

Here is another page, describing a three-player version of [Tachtigen](http://www.beukie.org/duurt/tachtigen.htm).

Home Page > Classified Index > Trick Taking Games > Ace-Ten Games > Marriage Group > Jass group > Pandoeren
