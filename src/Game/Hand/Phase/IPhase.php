<?php

namespace App\Game\Hand\Phase;

use App\Game\Player;
use App\Game\CardPile\Deck;
use Symfony\Component\EventDispatcher\EventDispatcher;

interface IPhase
{
    /**
     * @param Player[] $players
     * @param Deck $deck
     */
    public function play(array &$players, Deck &$deck): void;

    public static function fromArray(array $data): self;

    public function withEventDispatcher(EventDispatcher $dispatcher): static;
}
