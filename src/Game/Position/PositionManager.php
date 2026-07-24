<?php

namespace App\Game\Position;

use App\Game\Player\Player;
use App\Game\Table\Exception\PlayerAlreadyInGameException;
use App\Game\Table\Exception\TableException;
use Psr\Log\LoggerInterface;

class PositionManager
{
    /**
     * Hashmap of players and position
     * This array is indexed by userId, positions are 0 indexed
     * With 0 as button and sb in table < 3 players
     * For table with > 2 players 0 is button, 1 is SB
     * @var array<string, array<int, Player>>
     */
    private array $players;

    private int $buttonPosition = 0;

    /**
     * PlayerManager constructor
     * @param Player[] $base_players
     */
    public function __construct(
        private int $maxSeatCount,
        array $base_players,
        private LoggerInterface $logger
    ) {
        $this->players = [];
        foreach (array_values($base_players) as $i => $player) {
            $this->players[$player->getUserId()] = [$i, $player];
        }
    }

    public function getPlayer(string $userId): ?Player
    {
        return isset($this->players[$userId]) ? $this->players[$userId][1] : null;
    }

    public function addPlayer(Player $player): void
    {
        if (isset($this->players[$player->getUserId()])) {
            throw new PlayerAlreadyInGameException();
        }

        // When we add a player we should try to replace emptiness in the postions
        // We can't just put everyone on the back off the table
        // e.g. On a table of 6 players if player on 4th position exits we should put
        // the next player that joins on this space instead of the 7th position
        $current_position = -1;
        foreach ($this->players as $player_with_position) {
            [$position] = $player_with_position;

            // If the current position is empty, we can take this space
            // e.g. In a table of 4 ppl we have: [[0, P_0], [1, P_1], [2, P_2], [3, P_3]]
            // P_0 quits the table: [[1, P_1], [2, P_2], [3, P_3]]
            // So current_position + 1 = 0 and position = 1 so the first seat is empty, we can take it
            if ($current_position + 1 !== $position) {
                $this->players[$player->getUserId()] = [$current_position + 1, $player];
                // Return is intentional bc the player is successfully added to the table
                return;
            }

            $current_position++;
        }

        // If no player exits: we exit the loop without adding anything, we can add the player to the end of the table
        $this->players[$player->getUserId()] = [$current_position + 1, $player];
    }

    public function removePlayer(Player $player): void
    {
        unset($this->players[$player->getUserId()]);
    }

    /**
     * Return players in a list ordered by their position
     * @return void
     */
    public function getPlayers(bool $ordered = true): array
    {
        if ($ordered) {
            $players = $this->players;
            uasort($players, function ($a, $b) {
                $pa = ($a[0] - $this->buttonPosition + $this->maxSeatCount) % $this->maxSeatCount;
                $pb = ($b[0] - $this->buttonPosition + $this->maxSeatCount) % $this->maxSeatCount;

                // https://stackoverflow.com/questions/47466358/what-is-the-spaceship-three-way-comparison-operator-in-c
                return $pa <=> $pb;
            });
            return \array_values(\array_map(fn($p) => $p[1], $players));
        }

        return array_map(fn($a) => $a[1], $this->players);
    }

    public function getRawPlayers(): array
    {
        return $this->players;
    }

    public function nextOccupiedSeat(int $seat): int
    {
        $current = $seat;

        do {
            $current = ($current + 1) % $this->maxSeatCount;

            foreach ($this->players as [$position]) {
                if ($position === $current) {
                    return $current;
                }
            }
        } while ($current !== $seat);

        throw new TableException('No players on table');
    }

    public function shiftButton(int $shift): void
    {
        for ($i = 0; $i < $shift; $i++) {
            $this->buttonPosition = $this->nextOccupiedSeat($this->buttonPosition);
        }
    }

    public function getPlayerCount(): int
    {
        return \count($this->players);
    }
}