<?php

namespace App\Game\Hand\Phase;

use App\Event\PhaseState;
use App\Event\PlayerAction;
use App\Game\CardPile\Deck;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class ShuffleDeckPhase extends AbstractPhase
{
    private function __construct(
        private EventDispatcher $dispatcher,
        protected LoggerInterface $logger,
        private ?int $rounds = 1
    ) {
    }

    public function play(array &$players, Deck &$deck): void
    {
        $this->logger->info("Playing phase", ["phase" => (self::class), "rounds" => $this->rounds]);

        for ($i = 0; $i < $this->rounds; $i++) {
            $this->logger->debug("Shuffle round {{round}}", ["round" => $i]);
            $deck->shuffle();
        }

        $this->dispatcher->dispatch(new PhaseState("next_phase"));
    }

    public static function fromArray(array $data): self
    {
        return new self($data["dispatcher"], $data["logger"], $data["rounds"] ?? null);
    }

    public function onPlayerAction(PlayerAction $event): void
    {
    }
}