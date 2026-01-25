<?php

namespace App\Game\CardPile;

use App\Game\Card\Card;

use App\Game\CardPile\Exception\InvalidCardException;
use App\Game\CardPile\Exception\CardPileOverflowException;
use App\Game\CardPile\Exception\CardAlreadyInPileException;

abstract class AbstractCardPile implements ICardPile
{
    /**
     * Array of cards
     * @var Card[]
     */
    protected array $cards = [];

    public readonly bool $noDuplicate;

    public readonly int $maxSize;

    public function __construct(int $maxSize, bool $noDuplicate, ?array $cards = null)
    {
        $this->maxSize = $maxSize;
        $this->noDuplicate = $noDuplicate;

        if (!empty($cards)) {
            if (!array_all($cards, fn(mixed $card): bool => $card instanceof Card)) {
                throw new InvalidCardException();
            }

            $this->cards = $cards;
        }
    }

    public function pushCard(Card $card): void
    {
        if (\sizeof($this->cards) === $this->maxSize) {
            throw new CardPileOverflowException();
        }

        if ($this->noDuplicate && $this->contains($card)) {
            throw new CardAlreadyInPileException();
        }

        $this->cards[] = $card;
    }

    public function contains(Card $card): bool
    {
        return array_find($this->cards, fn(Card $value): bool => $value->equals($card)) !== null;
    }
}