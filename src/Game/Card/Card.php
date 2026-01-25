<?php

namespace App\Game\Card;


class Card
{
    private string $rank;

    private string $symbol;

    private int $weight;

    public function __construct(string $rank, string $symbol, int $weight)
    {
        $this->rank = $rank;
        $this->symbol = $symbol;
        $this->weight = $weight;
    }

    public function getRank(): string
    {
        return $this->rank;
    }

    public function getSymbol(): string
    {
        return $this->symbol;
    }

    /**
     * Return the total weight of the card, used for card comparison and order
     */
    public function getWeight(): int
    {
        return $this->weight;
    }

    /**
     * Method that compare a Card with another to know if they are equivalent (same value)
     * @param Card $card
     * @return void
     */
    public function equals(Card $card): bool
    {
        return $this->rank === $card->getRank() && $this->symbol === $card->getSymbol() && $this->weight === $card->getWeight();
    }

    public function __tostring(): string
    {
        return $this->rank . $this->symbol . "(" . $this->weight . ")";
    }
}