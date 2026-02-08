<?php

namespace App\Game\Table;

use App\DTO\DeckGenerationDTO;

use App\Game\Player;
use App\Game\Hand\PokerHand;
use App\Game\Hand\Phase\IPhase;
use App\Game\CardPile\DeckFactory;

use App\Game\Table\TableFullException;

class Table
{
    /**
     * @var Player[]
     */
    private array $players = [];
    private PokerHand $current_hand;

    public readonly int $maxPlayers;
    public readonly array $phases;

    private DeckFactory $deckFactory;

    /**
     * @param int $maxPlayers
     * @param IPhase[] $phases
     */
    public function __construct(int $maxPlayers, array $phases, DeckGenerationDTO $deckGenerationDTO)
    {
        $this->maxPlayers = $maxPlayers;
        $this->phases = $phases;
        $this->deckFactory = new DeckFactory($deckGenerationDTO);
    }

    public function broadcastJson(mixed $data): void
    {
        foreach ($this->players as $player) {
            $player->sendMessage($data);
        }
    }

    public function addPlayer(Player $player): void
    {
        $alreadyInside = $this->getPlayer($player->getUserId());
        if (!empty($alreadyInside)) {
            throw new PlayerAlreadyInGameException();
        }

        if (\sizeof($this->players) >= $this->maxPlayers) {
            throw new TableFullException();
        }

        // TODO: Different condition depending on table type (tournament, cash game, ...)

        $this->players[] = $player;
        $this->broadcastJson(["table_state" => "new_player", "player_id" => $player->getUserId()]);
    }

    public function removePlayer(Player $player): void
    {
        $this->players = array_filter($this->players, fn(Player $value): bool => $value->getUserId() === $player->getUserId());
    }

    public function getPlayer(string $userId): ?Player
    {
        $player = array_find($this->players, fn(Player $value) => $value->getUserId() === $userId);
        return $player;
    }

    public function newHand()
    {
        // Save previous hand in db for the history
        // $this->current_hand;
        $this->current_hand = new PokerHand($this->players, $this->phases, $this->deckFactory->newDeck());
        $this->broadcastJson(["table_state" => "new_hand"]);
    }

    public function start()
    {
        $this->newHand();
        $this->nextPhase();
    }

    public function nextPhase()
    {
        $this->current_hand->playPhase(function (mixed $data): void {
            $this->update($data);
        });
    }

    private function update(mixed $data): void
    {
        if (is_array($data) && !empty($data["state"]) && $data["state"] === "next_phase") {
            $this->nextPhase();
        }

        $this->broadcastJson(["table_state" => "table_update", "data" => $data]);
    }
}