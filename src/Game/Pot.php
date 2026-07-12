<?php

namespace App\Game;

use App\Game\Player\Player;

class Pot
{
    /**
     * Pot amount
     * @var int
     */
    private int $amount;

    /**
     * Players which can receive this pot
     * @var Player[]
     */
    private array $players;

    public function __construct(int $amount, array $players)
    {
        $this->amount = $amount;
        $this->players = $players;
    }

    /**
     * Get players competing for this pot
     * @return Player[]
     */
    public function getCompetingPlayers(): array
    {
        return $this->players;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }
}