<?php

namespace App\Game\Hand\Phase;

use App\Game\Player;
use App\Game\CardPile\Deck;

use App\Event\PhaseState;
use App\Event\PlayerAction;
use Psr\Log\LoggerInterface;

class ShowdownPhase extends AbstractPhase
{
    private function __construct(
        protected LoggerInterface $logger,
        private ?int $timeout,
    ) {
    }

    public function onPlayerAction(PlayerAction $event): void
    {
        // We dont use any player action on this phase
    }

    public function play(array &$players, Deck &$deck): void
    {
        $this->dispatcher->dispatch(new PhaseState("next_phase"));
}

        $this->endPhase();
    }

    public static function fromArray(array $data): self
    {
        $instance = new self($data["logger"], $data["timeout"] ?? null);
        return $instance;
    }
}