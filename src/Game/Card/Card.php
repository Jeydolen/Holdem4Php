<?php

namespace App\Game\Card;

use App\Enum\CardRankEnum;


class Card
{
    public const RANK_PRIMES = [2, 3, 5, 7, 11, 13, 17, 19, 23, 29, 31, 37, 41];

    public const SUIT_BITMASKS = [
        'c' => 0x8000, // clubs
        'd' => 0x4000, // diamonds
        'h' => 0x2000, // hearts
        's' => 0x1000, // spades
    ];

    private string $rank;

    private string $symbol;

    public function __construct(string $rank, string $symbol)
    {
        $this->rank = $rank;
        $this->symbol = $symbol;
    }

    public function getRank(): string
    {
        return $this->rank;
    }

    public function getRankIndex(): int
    {
        return \array_find_key(
            CardRankEnum::cases(),
            fn(CardRankEnum $rankEnum) => $rankEnum->value === $this->rank
        );
    }
    public function getSymbol(): string
    {
        return $this->symbol;
    }

    /**
     * Method that compare a Card with another to know if they are equivalent (same value)
     * @param Card $card
     * @return void
     */
    public function equals(Card $card): bool
    {
        return $this->rank === $card->getRank() && $this->symbol === $card->getSymbol();
    }

    public function __tostring(): string
    {
        return $this->rank . $this->symbol;
    }

    public function getValue(): int
    {
        $rank_index = $this->getRankIndex();
        $uniqueBit = 1 << (16 + $rank_index);
        $rankNibble = $rank_index << 8;

        $prime = static::RANK_PRIMES[$rank_index];
        $suitBit = self::SUIT_BITMASKS[$this->symbol];

        return $uniqueBit | $suitBit | $rankNibble | $prime;
    }
}