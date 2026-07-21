<?php

namespace App\Game\Player;

use RuntimeException;

class PlayerCollection
{
    /**
     * All players (competing and folded)
     * @var Player[]
     */
    private array $players;

    /**
     * Folded players
     * @var Player[]
     */
    private array $foldedPlayerIds = [];

    /**
     * @param Player[] $players
     */
    public function __construct(array $players)
    {
        $this->players = $players;
    }

    /**
     * @return Player[]
     */
    public function getCompetingPlayers(): array
    {
        $competing_players = array_values(array_filter($this->players, fn(Player $player) => !\in_array($player->getUserId(), $this->foldedPlayerIds)));

        return $competing_players;
    }

    public function foldPlayer(string $playerId): void
    {
        $player = array_find($this->players, fn(Player $player) => $player->getUserId() === $playerId);


        if (empty($player)) {
            throw new RuntimeException("Player Id not found in players");
        }

        $this->foldedPlayerIds[] = $playerId;
    }

    public function getFoldedPlayerIds(): array
    {
        return $this->foldedPlayerIds;
    }

    public function getAllPlayers(): array
    {
        return array_values($this->players);
    }
}