<?php

namespace App\Service\CardRank;

use App\Enum\CardSymbolEnum;

use App\Game\Card\Card;
use App\Game\CardPile\ICardPile;

class CardRankEvaluator
{
    /** @var int[]|null */
    private static ?array $hand_ranks = null;

    private const FILE = __DIR__ . "/HandRanks.dat";

    private const SUIT_INDEXES = [
        CardSymbolEnum::CLUB->value => 0,
        CardSymbolEnum::DIAMOND->value => 1,
        CardSymbolEnum::HEART->value => 2,
        CardSymbolEnum::SPADE->value => 3
    ];

    /**
     * Evaluates the strength of a 7-card hand using the TwoPlusTwo algorithm.
     * To determine which player wins, compare the returned weights:
     * the highest value wins.
     * @link https://en.wikipedia.org/wiki/List_of_poker_hands
     *
     * @param ICardPile $cardPile Must contain exactly 7 cards
     * @return int Hand strength (higher = better)
     */
    public function evaluate(ICardPile $cardPile): int
    {
        $cards = $cardPile->getCards();
        if (\count($cards) !== 7) {
            throw new \InvalidArgumentException("Hand must contain exactly 7 cards");
        }

        $hr = $this->loadHandRanks();

        // TwoPlusTwo expects a card index between 1 and 52:
        // index = rankIndex (0-12) * 4 + suitIndex (0-3) + 1
        $indexes = array_map(
            fn(Card $c) => $c->getRankIndex() * 4 + self::SUIT_INDEXES[$c->getSymbol()] + 1,
            $cards
        );

        $p = $hr[53 + $indexes[0]];
        $p = $hr[$p + $indexes[1]];
        $p = $hr[$p + $indexes[2]];
        $p = $hr[$p + $indexes[3]];
        $p = $hr[$p + $indexes[4]];
        $p = $hr[$p + $indexes[5]];

        return $hr[$p + $indexes[6]];
    }

    /**
     * Load HandRanks table into memory (cached after first load).
     *
     * @return array<int,int>
     */
    private function loadHandRanks(): array
    {
        if (self::$hand_ranks !== null) {
            return self::$hand_ranks;
        }

        $data = file_get_contents(self::FILE);
        if ($data === false) {
            throw new \RuntimeException("Cannot read HandRanks.dat");
        }

        // 'V*' unpacks as little-endian unsigned 32-bit integers, indexed from 1.
        // We re-index from 0 to match the TwoPlusTwo algorithm expectations.
        $table = unpack('V*', $data);
        if (!\is_array($table)) {
            throw new \RuntimeException("Cannot unpack HandRanks.dat");
        }

        self::$hand_ranks = array_values($table);

        return self::$hand_ranks;
    }

    /**
     * Generates all 21 possible 5-card combinations from a 2-card player hand
     * and a 5-card board.
     *
     * @param ICardPile $playerCardPile Must contain exactly 2 cards
     * @param ICardPile $boardCardPile  Must contain exactly 5 cards
     * @return Card[][] Array of 21 hands, each being an array of 5 Card objects
     */
    public function generateHandsFromBoardAndPlayerCards(ICardPile $playerCardPile, ICardPile $boardCardPile): array
    {
        $boardCards = $boardCardPile->getCards();
        if (\count($boardCards) !== 5) {
            throw new \InvalidArgumentException("Board must have 5 cards");
        }

        $playerCards = $playerCardPile->getCards();
        if (\count($playerCards) !== 2) {
            throw new \InvalidArgumentException("Player hand must have 2 cards");
        }

        $all = array_merge($boardCards, $playerCards); // 7 cards total
        $n = \count($all);
        $hands = [];

        for ($i = 0; $i < $n - 4; $i++) {
            for ($j = $i + 1; $j < $n - 3; $j++) {
                for ($k = $j + 1; $k < $n - 2; $k++) {
                    for ($l = $k + 1; $l < $n - 1; $l++) {
                        for ($m = $l + 1; $m < $n; $m++) {
                            $hands[] = [$all[$i], $all[$j], $all[$k], $all[$l], $all[$m]];
                        }
                    }
                }
            }
        }

        return $hands;
    }
}