<?php

namespace App\Game\Hand\Phase;

use App\Game\CardPile\Deck;
use Psr\Log\LoggerInterface;

class BettingPhase implements IPhase
{
    private function __construct(
        private LoggerInterface $logger,
        private ?int $timeout
    ) {
    }

    public function play(array $players, Deck $deck): void
    {
        // We ask every player to bet, they can either call, check, raise or fold
        foreach ($players as $player) {
        }
    }

    public static function fromArray(array $data): self
    {
        return new self($data["logger"], $data["timeout"] ?? null);
    }
}