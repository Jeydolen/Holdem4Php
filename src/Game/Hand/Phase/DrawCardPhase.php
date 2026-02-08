<?php

namespace App\Game\Hand\Phase;

use App\Game\CardPile\Deck;
use Psr\Log\LoggerInterface;

class DrawCardPhase implements IPhase
{
    public function __construct(
        private LoggerInterface $logger,
        private ?int $timeout,
        private int $drawNumber
    ) {
    }

    public function play(array $players, Deck $deck): void
    {
        $this->logger->info("Playing phase", ["phase" => (self::class), "draw_number" => $this->drawNumber]);

        // In a draw card phase, we need to add card to players.
        foreach ($players as $player) {
            for ($i = 0; $i < $this->drawNumber; $i++) {
                $card = $deck->pop();
                $this->logger->debug("Card added to player cards", ["player" => $player, "card" => $card, "card_number" => $i]);
                $player->pushCard($card);
            }
        }

        $this->logger->info("End phase", ["phase" => (self::class)]);
    }

    public static function fromArray(array $data): self
    {
        return new self($data["logger"], $data["timeout"] ?? null, $data["drawNumber"]);
    }
}