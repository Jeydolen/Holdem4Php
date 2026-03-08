<?php

namespace App\Game\CardPile;

use App\Game\Card\Card;

interface ICardPile
{
    /**
     * Summary of __construct
     * @param int $maxSize Maximum size for the deck, put -1 if the size is infinite
     * @param bool $noDuplicate Flag that throw a CardAlreadyInPileException if card is already in the deck
     */
    public function __construct(int $maxSize, bool $noDuplicate);

    /**
     * Method that push a card on top of the card pile
     * @param Card $card The card to push
     */
    public function pushCard(Card $card): void;

    /**
     * Method that checks if a Card is inside the deck.
     * @param Card $card
     * @return void
     */
    public function contains(Card $card): bool;

    /**
     * @return Card[]
     */
    public function getCards(): array;
}