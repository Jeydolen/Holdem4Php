<?php

namespace App\Game\Card;


class Card
{
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
}