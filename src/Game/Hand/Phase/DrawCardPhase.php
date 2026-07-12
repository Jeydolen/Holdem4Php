<?php

namespace App\Game\Hand\Phase;

use App\Event\PlayerAction;

use App\Game\Hand\HandContext;

use Psr\Log\LoggerInterface;

class DrawCardPhase extends AbstractPhase
{
    public function __construct(
        protected LoggerInterface $logger,
        private ?int $timeout,
        private int $drawNumber
    ) {
    }

    public function play(HandContext $context): void
    {
        $players = $context->getPlayerCollection()->getCompetingPlayers();
        $deck = $context->getDeck();

        $this->logger->info("Playing phase", ["phase" => (self::class), "draw_number" => $this->drawNumber]);

        // In a draw card phase, we need to add card to players.
        foreach ($players as $player) {
            for ($i = 0; $i < $this->drawNumber; $i++) {
                $card = $deck->pop();
                $this->logger->debug("Card added to player cards", ["player" => $player, "card" => $card, "card_number" => $i]);
                $player->pushCard($card);
            }

            // Update player client state
            $player->sendCurrentState();
        }

        $this->endPhase();
    }

    public static function fromArray(array $data): self
    {
        return new self($data["logger"], $data["timeout"] ?? null, $data["drawNumber"]);
    }

    public function onPlayerAction(PlayerAction $event): void
    {
    }
}