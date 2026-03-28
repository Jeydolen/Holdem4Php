<?php

namespace App\Game\Hand\Phase;

use App\Event\PhaseState;
use App\Event\PlayerAction;

use App\Game\CardPile\Deck;
use App\Game\CardPile\ICardPile;

use Psr\Log\LoggerInterface;

class DrawBoardCardPhase extends AbstractPhase
{
    public function __construct(
        protected LoggerInterface $logger,
        private ?int $timeout,
        private int $drawNumber
    ) {
    }

    public function play(array &$players, Deck &$deck, ?ICardPile $boardCardPile): void
    {
        $this->logger->info("Playing phase", ["phase" => (self::class), "draw_number" => $this->drawNumber]);

        // In a draw board card phase, we need to add card to the board.
        for ($i = 0; $i < $this->drawNumber; $i++) {
            $card = $deck->pop();
            $this->logger->debug("Card added to the board cards", ["card" => $card, "card_number" => $i]);
            $boardCardPile->pushCard($card);
        }

        $this->dispatcher->dispatch(new PhaseState("next_phase"));
    }

    public static function fromArray(array $data): self
    {
        return new self($data["logger"], $data["timeout"] ?? null, $data["drawNumber"]);
    }

    public function onPlayerAction(PlayerAction $event): void
    {
    }
}