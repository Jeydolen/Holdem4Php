<?php

namespace App\Game\Hand\Phase;

use App\Game\CardPile\Deck;

class DrawCardPhase implements IPhase
{
    private ?int $timeout;
    private int $drawNumber;
    public function __construct(?int $timeout, int $drawNumber)
    {
        $this->timeout = $timeout;
        $this->drawNumber = $drawNumber;
    }

    public function play(array $players, Deck $deck): void
    {
        // In a draw card phase, we need to add card to players.
        foreach ($players as $player) {
            for ($i = 0; $i < $this->drawNumber; $i++) {
                $card = $deck->pop();
                $player->pushCard($card);
            }
        }
    }

    public static function fromArray(array $data): self
    {
        return new self($data["timeout"] ?? null, $data["drawNumber"]);
    }
}