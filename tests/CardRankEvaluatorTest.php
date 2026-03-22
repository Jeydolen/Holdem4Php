<?php

namespace App\Tests;

use App\Enum\HandRankEnum;
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
            new Card("A", "h"),
            new Card("K", "d"),
            new Card("Q", "s"),
            new Card("J", "c"),
            new Card("9", "h"),
        ]);

        $playerCards = new PlayerHoleCards(2, true, [
            new Card("2", "c"),
            new Card("7", "d"),
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
            new Card("A", "h"),
            new Card("K", "h"),
            new Card("Q", "h"),
            new Card("J", "h"),
            new Card("10", "h"),
            new Card("2", "h"),
            new Card("4", "h"),
        ]);

        $rank = $this->cardRankEvaluator->evaluate($sevenCards);
        $category = $rank >> 12;

        $this->assertSame(HandRankEnum::STRAIGHT_FLUSH->value, $category);
    }

    public function testFourOfAKind(): void
    {
        // Four 2s and three 3s — best 5-card hand is four of a kind (2222 + 3 kicker)
        $sevenCards = new BoardCards(7, true, [
            new Card("2", "c"),
            new Card("2", "d"),
            new Card("2", "h"),
            new Card("2", "s"),
            new Card("3", "c"),
            new Card("3", "d"),
            new Card("3", "h"),
        ]);

        $rank = $this->cardRankEvaluator->evaluate($sevenCards);
        $category = $rank >> 12;

        $this->assertSame(HandRankEnum::FOUR_OF_A_KIND->value, $category);
    }

    public function testFullHouse(): void
    {
        // Three Aces and two Kings — full house (AAA KK)
        $sevenCards = new BoardCards(7, true, [
            new Card("A", "c"),
            new Card("A", "d"),
            new Card("A", "h"),
            new Card("K", "c"),
            new Card("K", "d"),
            new Card("2", "c"),
            new Card("3", "c"),
        ]);

        $rank = $this->cardRankEvaluator->evaluate($sevenCards);
        $category = $rank >> 12;

        $this->assertSame(HandRankEnum::FULL_HOUSE->value, $category);
    }


    public function testFlush(): void
    {
        // Five spades but no straight
        $sevenCards = new BoardCards(7, true, [
            new Card("A", "s"),
            new Card("J", "s"),
            new Card("9", "s"),
            new Card("6", "s"),
            new Card("2", "s"),
            new Card("K", "h"),
            new Card("Q", "d"),
        ]);

        $rank = $this->cardRankEvaluator->evaluate($sevenCards);
        $category = $rank >> 12;

        $this->assertSame(HandRankEnum::FLUSH->value, $category);
    }

    public function testStraight(): void
    {
        // 5-6-7-8-9 straight, mixed suits, no flush possible
        $sevenCards = new BoardCards(7, true, [
            new Card("5", "c"),
            new Card("6", "d"),
            new Card("7", "h"),
            new Card("8", "s"),
            new Card("9", "c"),
            new Card("A", "d"),
            new Card("K", "h"),
        ]);

        $rank = $this->cardRankEvaluator->evaluate($sevenCards);
        $category = $rank >> 12;

        $this->assertSame(HandRankEnum::STRAIGHT->value, $category);
    }

    public function testThreeOfAKind(): void
    {
        // Three Kings, no pair on the board
        $sevenCards = new BoardCards(7, true, [
            new Card("K", "c"),
            new Card("K", "d"),
            new Card("K", "h"),
            new Card("2", "s"),
            new Card("4", "c"),
            new Card("6", "d"),
            new Card("8", "h"),
        ]);

        $rank = $this->cardRankEvaluator->evaluate($sevenCards);
        $category = $rank >> 12;

        $this->assertSame(HandRankEnum::THREE_OF_A_KIND->value, $category);
    }

    public function testTwoPair(): void
    {
        // Aces and Kings, no trips possible
        $sevenCards = new BoardCards(7, true, [
            new Card("A", "c"),
            new Card("A", "d"),
            new Card("K", "h"),
            new Card("K", "s"),
            new Card("2", "c"),
            new Card("4", "d"),
            new Card("6", "h"),
        ]);

        $rank = $this->cardRankEvaluator->evaluate($sevenCards);
        $category = $rank >> 12;

        $this->assertSame(HandRankEnum::TWO_PAIR->value, $category);
    }

    public function testOnePair(): void
    {
        // Pair of Aces, no other pair or better
        $sevenCards = new BoardCards(7, true, [
            new Card("A", "c"),
            new Card("A", "d"),
            new Card("2", "h"),
            new Card("4", "s"),
            new Card("6", "c"),
            new Card("8", "d"),
            new Card("10", "h"),
        ]);

        $rank = $this->cardRankEvaluator->evaluate($sevenCards);
        $category = $rank >> 12;

        $this->assertSame(HandRankEnum::ONE_PAIR->value, $category);
    }

    public function testHighCard(): void
    {
        // No pair, no flush, no straight
        $sevenCards = new BoardCards(7, true, [
            new Card("A", "c"),
            new Card("K", "d"),
            new Card("J", "h"),
            new Card("9", "s"),
            new Card("7", "c"),
            new Card("4", "d"),
            new Card("2", "h"),
        ]);

        $rank = $this->cardRankEvaluator->evaluate($sevenCards);
        $category = $rank >> 12;

        $this->assertSame(HandRankEnum::HIGH_CARD->value, $category);
    }

    public function testHigherHandBeatsLowerHand(): void
    {
        // Straight flush beats four of a kind
        $straightFlush = new BoardCards(7, true, [
            new Card("A", "h"),
            new Card("K", "h"),
            new Card("Q", "h"),
            new Card("J", "h"),
            new Card("10", "h"),
            new Card("2", "c"),
            new Card("3", "d"),
        ]);

        $fourOfAKind = new BoardCards(7, true, [
            new Card("A", "c"),
            new Card("A", "d"),
            new Card("A", "h"),
            new Card("A", "s"),
            new Card("K", "c"),
            new Card("2", "d"),
            new Card("3", "h"),
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
            new Card("A", "s"),
            new Card("J", "s"),
            new Card("9", "s"),
            new Card("6", "s"),
            new Card("2", "s"),
            new Card("K", "h"),
            new Card("Q", "d"),
        ]);

        $kingHighFlush = new BoardCards(7, true, [
            new Card("K", "s"),
            new Card("J", "s"),
            new Card("9", "s"),
            new Card("6", "s"),
            new Card("2", "s"),
            new Card("A", "h"),
            new Card("Q", "d"),
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
            new Card("A", "c"),
            new Card("A", "d"),
            new Card("A", "h"),
            new Card("K", "c"),
            new Card("K", "d"),
            new Card("2", "h"),
            new Card("3", "s"),
        ]);

        $handB = new BoardCards(7, true, [
            new Card("A", "c"),
            new Card("A", "d"),
            new Card("A", "h"),
            new Card("K", "c"),
            new Card("K", "d"),
            new Card("4", "h"),
            new Card("5", "s"),
        ]);

        $this->assertSame(
            $this->cardRankEvaluator->evaluate($handA),
            $this->cardRankEvaluator->evaluate($handB)
        );
    }
}