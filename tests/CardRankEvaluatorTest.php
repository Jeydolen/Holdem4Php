<?php

namespace App\Tests;

use App\Enum\HandRankEnum;
use App\Enum\CardRankEnum;
use App\Enum\CardSymbolEnum;

use App\Game\Card\Card;
use App\Game\CardPile\BoardCards;
use App\Game\CardPile\PlayerHoleCards;

use App\Service\CardRank\CardRankEvaluator;

use PHPUnit\Framework\TestCase;

class CardRankEvaluatorTest extends TestCase
{
    private CardRankEvaluator $cardRankEvaluator;

    public function setUp(): void
    {
        $this->cardRankEvaluator = new CardRankEvaluator();
    }

    public function testGenerateHands(): void
    {
        $boardCards = new BoardCards(5, true, [
            new Card(CardRankEnum::tryFrom("A"), CardSymbolEnum::HEART),
            new Card(CardRankEnum::tryFrom("K"), CardSymbolEnum::DIAMOND),
            new Card(CardRankEnum::tryFrom("Q"), CardSymbolEnum::SPADE),
            new Card(CardRankEnum::tryFrom("J"), CardSymbolEnum::CLUB),
            new Card(CardRankEnum::tryFrom("9"), CardSymbolEnum::HEART),
        ]);

        $playerCards = new PlayerHoleCards(2, true, [
            new Card(CardRankEnum::tryFrom("2"), CardSymbolEnum::CLUB),
            new Card(CardRankEnum::tryFrom("7"), CardSymbolEnum::DIAMOND),
        ]);

        $hands = $this->cardRankEvaluator->generateHandsFromBoardAndPlayerCards($playerCards, $boardCards);

        $this->assertCount(21, $hands);

        foreach ($hands as $hand) {
            $this->assertCount(5, $hand);
        }
    }

    public function testRoyalStraightFlush(): void
    {
        // A-K-Q-J-10 of hearts — best possible straight flush
        $sevenCards = new BoardCards(7, true, [
            new Card(CardRankEnum::tryFrom("A"), CardSymbolEnum::HEART),
            new Card(CardRankEnum::tryFrom("K"), CardSymbolEnum::HEART),
            new Card(CardRankEnum::tryFrom("Q"), CardSymbolEnum::HEART),
            new Card(CardRankEnum::tryFrom("J"), CardSymbolEnum::HEART),
            new Card(CardRankEnum::tryFrom("10"), CardSymbolEnum::HEART),
            new Card(CardRankEnum::tryFrom("2"), CardSymbolEnum::HEART),
            new Card(CardRankEnum::tryFrom("4"), CardSymbolEnum::HEART),
        ]);

        $rank = $this->cardRankEvaluator->evaluate($sevenCards);
        $category = $rank >> 12;

        $this->assertSame(HandRankEnum::STRAIGHT_FLUSH->value, $category);
    }

    public function testFourOfAKind(): void
    {
        // Four 2s and three 3s — best 5-card hand is four of a kind (2222 + 3 kicker)
        $sevenCards = new BoardCards(7, true, [
            new Card(CardRankEnum::tryFrom("2"), CardSymbolEnum::CLUB),
            new Card(CardRankEnum::tryFrom("2"), CardSymbolEnum::DIAMOND),
            new Card(CardRankEnum::tryFrom("2"), CardSymbolEnum::HEART),
            new Card(CardRankEnum::tryFrom("2"), CardSymbolEnum::SPADE),
            new Card(CardRankEnum::tryFrom("3"), CardSymbolEnum::CLUB),
            new Card(CardRankEnum::tryFrom("3"), CardSymbolEnum::DIAMOND),
            new Card(CardRankEnum::tryFrom("3"), CardSymbolEnum::HEART),
        ]);

        $rank = $this->cardRankEvaluator->evaluate($sevenCards);
        $category = $rank >> 12;

        $this->assertSame(HandRankEnum::FOUR_OF_A_KIND->value, $category);
    }

    public function testFullHouse(): void
    {
        // Three Aces and two Kings — full house (AAA KK)
        $sevenCards = new BoardCards(7, true, [
            new Card(CardRankEnum::tryFrom("A"), CardSymbolEnum::CLUB),
            new Card(CardRankEnum::tryFrom("A"), CardSymbolEnum::DIAMOND),
            new Card(CardRankEnum::tryFrom("A"), CardSymbolEnum::HEART),
            new Card(CardRankEnum::tryFrom("K"), CardSymbolEnum::CLUB),
            new Card(CardRankEnum::tryFrom("K"), CardSymbolEnum::DIAMOND),
            new Card(CardRankEnum::tryFrom("2"), CardSymbolEnum::CLUB),
            new Card(CardRankEnum::tryFrom("3"), CardSymbolEnum::CLUB),
        ]);

        $rank = $this->cardRankEvaluator->evaluate($sevenCards);
        $category = $rank >> 12;

        $this->assertSame(HandRankEnum::FULL_HOUSE->value, $category);
    }


    public function testFlush(): void
    {
        // Five spades but no straight
        $sevenCards = new BoardCards(7, true, [
            new Card(CardRankEnum::tryFrom("A"), CardSymbolEnum::SPADE),
            new Card(CardRankEnum::tryFrom("J"), CardSymbolEnum::SPADE),
            new Card(CardRankEnum::tryFrom("9"), CardSymbolEnum::SPADE),
            new Card(CardRankEnum::tryFrom("6"), CardSymbolEnum::SPADE),
            new Card(CardRankEnum::tryFrom("2"), CardSymbolEnum::SPADE),
            new Card(CardRankEnum::tryFrom("K"), CardSymbolEnum::HEART),
            new Card(CardRankEnum::tryFrom("Q"), CardSymbolEnum::DIAMOND),
        ]);

        $rank = $this->cardRankEvaluator->evaluate($sevenCards);
        $category = $rank >> 12;

        $this->assertSame(HandRankEnum::FLUSH->value, $category);
    }

    public function testStraight(): void
    {
        // 5-6-7-8-9 straight, mixed suits, no flush possible
        $sevenCards = new BoardCards(7, true, [
            new Card(CardRankEnum::tryFrom("5"), CardSymbolEnum::CLUB),
            new Card(CardRankEnum::tryFrom("6"), CardSymbolEnum::DIAMOND),
            new Card(CardRankEnum::tryFrom("7"), CardSymbolEnum::HEART),
            new Card(CardRankEnum::tryFrom("8"), CardSymbolEnum::SPADE),
            new Card(CardRankEnum::tryFrom("9"), CardSymbolEnum::CLUB),
            new Card(CardRankEnum::tryFrom("A"), CardSymbolEnum::DIAMOND),
            new Card(CardRankEnum::tryFrom("K"), CardSymbolEnum::HEART),
        ]);

        $rank = $this->cardRankEvaluator->evaluate($sevenCards);
        $category = $rank >> 12;

        $this->assertSame(HandRankEnum::STRAIGHT->value, $category);
    }

    public function testThreeOfAKind(): void
    {
        // Three Kings, no pair on the board
        $sevenCards = new BoardCards(7, true, [
            new Card(CardRankEnum::tryFrom("K"), CardSymbolEnum::CLUB),
            new Card(CardRankEnum::tryFrom("K"), CardSymbolEnum::DIAMOND),
            new Card(CardRankEnum::tryFrom("K"), CardSymbolEnum::HEART),
            new Card(CardRankEnum::tryFrom("2"), CardSymbolEnum::SPADE),
            new Card(CardRankEnum::tryFrom("4"), CardSymbolEnum::CLUB),
            new Card(CardRankEnum::tryFrom("6"), CardSymbolEnum::DIAMOND),
            new Card(CardRankEnum::tryFrom("8"), CardSymbolEnum::HEART),
        ]);

        $rank = $this->cardRankEvaluator->evaluate($sevenCards);
        $category = $rank >> 12;

        $this->assertSame(HandRankEnum::THREE_OF_A_KIND->value, $category);
    }

    public function testTwoPair(): void
    {
        // Aces and Kings, no trips possible
        $sevenCards = new BoardCards(7, true, [
            new Card(CardRankEnum::tryFrom("A"), CardSymbolEnum::CLUB),
            new Card(CardRankEnum::tryFrom("A"), CardSymbolEnum::DIAMOND),
            new Card(CardRankEnum::tryFrom("K"), CardSymbolEnum::HEART),
            new Card(CardRankEnum::tryFrom("K"), CardSymbolEnum::SPADE),
            new Card(CardRankEnum::tryFrom("2"), CardSymbolEnum::CLUB),
            new Card(CardRankEnum::tryFrom("4"), CardSymbolEnum::DIAMOND),
            new Card(CardRankEnum::tryFrom("6"), CardSymbolEnum::HEART),
        ]);

        $rank = $this->cardRankEvaluator->evaluate($sevenCards);
        $category = $rank >> 12;

        $this->assertSame(HandRankEnum::TWO_PAIR->value, $category);
    }

    public function testOnePair(): void
    {
        // Pair of Aces, no other pair or better
        $sevenCards = new BoardCards(7, true, [
            new Card(CardRankEnum::tryFrom("A"), CardSymbolEnum::CLUB),
            new Card(CardRankEnum::tryFrom("A"), CardSymbolEnum::DIAMOND),
            new Card(CardRankEnum::tryFrom("2"), CardSymbolEnum::HEART),
            new Card(CardRankEnum::tryFrom("4"), CardSymbolEnum::SPADE),
            new Card(CardRankEnum::tryFrom("6"), CardSymbolEnum::CLUB),
            new Card(CardRankEnum::tryFrom("8"), CardSymbolEnum::DIAMOND),
            new Card(CardRankEnum::tryFrom("10"), CardSymbolEnum::HEART),
        ]);

        $rank = $this->cardRankEvaluator->evaluate($sevenCards);
        $category = $rank >> 12;

        $this->assertSame(HandRankEnum::ONE_PAIR->value, $category);
    }

    public function testHighCard(): void
    {
        // No pair, no flush, no straight
        $sevenCards = new BoardCards(7, true, [
            new Card(CardRankEnum::tryFrom("A"), CardSymbolEnum::CLUB),
            new Card(CardRankEnum::tryFrom("K"), CardSymbolEnum::DIAMOND),
            new Card(CardRankEnum::tryFrom("J"), CardSymbolEnum::HEART),
            new Card(CardRankEnum::tryFrom("9"), CardSymbolEnum::SPADE),
            new Card(CardRankEnum::tryFrom("7"), CardSymbolEnum::CLUB),
            new Card(CardRankEnum::tryFrom("4"), CardSymbolEnum::DIAMOND),
            new Card(CardRankEnum::tryFrom("2"), CardSymbolEnum::HEART),
        ]);

        $rank = $this->cardRankEvaluator->evaluate($sevenCards);
        $category = $rank >> 12;

        $this->assertSame(HandRankEnum::HIGH_CARD->value, $category);
    }

    public function testHigherHandBeatsLowerHand(): void
    {
        // Straight flush beats four of a kind
        $straightFlush = new BoardCards(7, true, [
            new Card(CardRankEnum::tryFrom("A"), CardSymbolEnum::HEART),
            new Card(CardRankEnum::tryFrom("K"), CardSymbolEnum::HEART),
            new Card(CardRankEnum::tryFrom("Q"), CardSymbolEnum::HEART),
            new Card(CardRankEnum::tryFrom("J"), CardSymbolEnum::HEART),
            new Card(CardRankEnum::tryFrom("10"), CardSymbolEnum::HEART),
            new Card(CardRankEnum::tryFrom("2"), CardSymbolEnum::CLUB),
            new Card(CardRankEnum::tryFrom("3"), CardSymbolEnum::DIAMOND),
        ]);

        $fourOfAKind = new BoardCards(7, true, [
            new Card(CardRankEnum::tryFrom("A"), CardSymbolEnum::CLUB),
            new Card(CardRankEnum::tryFrom("A"), CardSymbolEnum::DIAMOND),
            new Card(CardRankEnum::tryFrom("A"), CardSymbolEnum::HEART),
            new Card(CardRankEnum::tryFrom("A"), CardSymbolEnum::SPADE),
            new Card(CardRankEnum::tryFrom("K"), CardSymbolEnum::CLUB),
            new Card(CardRankEnum::tryFrom("2"), CardSymbolEnum::DIAMOND),
            new Card(CardRankEnum::tryFrom("3"), CardSymbolEnum::HEART),
        ]);

        $this->assertGreaterThan(
            $this->cardRankEvaluator->evaluate($fourOfAKind),
            $this->cardRankEvaluator->evaluate($straightFlush)
        );
    }

    public function testBetterHandWithinSameCategoryWins(): void
    {
        // Ace-high flush beats King-high flush
        $aceHighFlush = new BoardCards(7, true, [
            new Card(CardRankEnum::tryFrom("A"), CardSymbolEnum::SPADE),
            new Card(CardRankEnum::tryFrom("J"), CardSymbolEnum::SPADE),
            new Card(CardRankEnum::tryFrom("9"), CardSymbolEnum::SPADE),
            new Card(CardRankEnum::tryFrom("6"), CardSymbolEnum::SPADE),
            new Card(CardRankEnum::tryFrom("2"), CardSymbolEnum::SPADE),
            new Card(CardRankEnum::tryFrom("K"), CardSymbolEnum::HEART),
            new Card(CardRankEnum::tryFrom("Q"), CardSymbolEnum::DIAMOND),
        ]);

        $kingHighFlush = new BoardCards(7, true, [
            new Card(CardRankEnum::tryFrom("K"), CardSymbolEnum::SPADE),
            new Card(CardRankEnum::tryFrom("J"), CardSymbolEnum::SPADE),
            new Card(CardRankEnum::tryFrom("9"), CardSymbolEnum::SPADE),
            new Card(CardRankEnum::tryFrom("6"), CardSymbolEnum::SPADE),
            new Card(CardRankEnum::tryFrom("2"), CardSymbolEnum::SPADE),
            new Card(CardRankEnum::tryFrom("A"), CardSymbolEnum::HEART),
            new Card(CardRankEnum::tryFrom("Q"), CardSymbolEnum::DIAMOND),
        ]);

        $this->assertGreaterThan(
            $this->cardRankEvaluator->evaluate($kingHighFlush),
            $this->cardRankEvaluator->evaluate($aceHighFlush)
        );
    }

    public function testEquivalentHandsHaveSameRank(): void
    {
        // Two boards with the same best 5-card hand (same ranks, different irrelevant cards)
        $handA = new BoardCards(7, true, [
            new Card(CardRankEnum::tryFrom("A"), CardSymbolEnum::CLUB),
            new Card(CardRankEnum::tryFrom("A"), CardSymbolEnum::DIAMOND),
            new Card(CardRankEnum::tryFrom("A"), CardSymbolEnum::HEART),
            new Card(CardRankEnum::tryFrom("K"), CardSymbolEnum::CLUB),
            new Card(CardRankEnum::tryFrom("K"), CardSymbolEnum::DIAMOND),
            new Card(CardRankEnum::tryFrom("2"), CardSymbolEnum::HEART),
            new Card(CardRankEnum::tryFrom("3"), CardSymbolEnum::SPADE),
        ]);

        $handB = new BoardCards(7, true, [
            new Card(CardRankEnum::tryFrom("A"), CardSymbolEnum::CLUB),
            new Card(CardRankEnum::tryFrom("A"), CardSymbolEnum::DIAMOND),
            new Card(CardRankEnum::tryFrom("A"), CardSymbolEnum::HEART),
            new Card(CardRankEnum::tryFrom("K"), CardSymbolEnum::CLUB),
            new Card(CardRankEnum::tryFrom("K"), CardSymbolEnum::DIAMOND),
            new Card(CardRankEnum::tryFrom("4"), CardSymbolEnum::HEART),
            new Card(CardRankEnum::tryFrom("5"), CardSymbolEnum::SPADE),
        ]);

        $this->assertSame(
            $this->cardRankEvaluator->evaluate($handA),
            $this->cardRankEvaluator->evaluate($handB)
        );
    }
}