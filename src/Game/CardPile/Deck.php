<?php

namespace App\Game\CardPile;

use App\Game\Card\Card;

use App\Game\CardPile\Exception\EmptyCardPileException;

class Deck extends AbstractCardPile
{
    public function pop(): Card
    {
        $result = array_pop($this->cards);

        if ($result === null) {
            throw new EmptyCardPileException();
        }

        return $result;
    }

    public function getCards(): array
    {
        return $this->cards;
    }

    public function shuffle(): array
    {
        // We can't use php shuffle method because it is not cryptographically safe
        // https://en.wikipedia.org/wiki/Fisher%E2%80%93Yates_shuffle
        $count = \count($this->cards);
        for ($i = $count - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$this->cards[$i], $this->cards[$j]] = [$this->cards[$j], $this->cards[$i]];
        }

        return $this->cards;
    }
}