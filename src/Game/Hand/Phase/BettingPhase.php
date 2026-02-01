<?php

namespace App\Game\Hand\Phase;

use App\Game\CardPile\Deck;

class BettingPhase implements IPhase
{
    private ?int $timeout;
    private function __construct(?int $timeout)
    {
        $this->timeout = $timeout;
    }

    public function play(array $players, Deck $deck): void
    {
        // We ask every player to bet, they can either call, check, raise or fold
        foreach ($players as $player) {
        }
    }

    public static function fromArray(array $data): self
    {
        return new self($data["timeout"] ?? null);
    }
}