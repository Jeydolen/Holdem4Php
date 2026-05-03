<?php

namespace App\Game\Hand\Phase;

use App\Event\PhaseState;
use App\Event\PlayerAction;

use App\Game\CardPile\Deck;
use App\Game\CardPile\ICardPile;

use Psr\Log\LoggerInterface;

class ShuffleDeckPhase extends AbstractPhase
{
    private function __construct(
        protected LoggerInterface $logger,
        private ?int $rounds = 1
    ) {
    }

    public function play(array &$players, Deck &$deck, ?ICardPile $boardCardPile): void
    {
        $this->logger->info("Playing phase", ["phase" => (self::class), "rounds" => $this->rounds]);

        for ($i = 0; $i < $this->rounds; $i++) {
            $this->logger->debug("Shuffle round {{round}}", ["round" => $i]);
            $deck->shuffle();
        }

        $this->endPhase();
    }

    public static function fromArray(array $data): self
    {
        return new self($data["logger"], $data["rounds"] ?? null);
    }

    public function onPlayerAction(PlayerAction $event): void
    {
    }
}