<?php

namespace App\Game\Table;

use App\DTO\DeckGenerationDTO;

use App\Game\Player;
use App\Game\Hand\PokerHand;
use App\Game\Hand\Phase\IPhase;
use App\Game\CardPile\DeckFactory;

use App\Game\Table\Exception\TableFullException;
use App\Game\Table\Exception\PlayerAlreadyInGameException;

use Psr\Log\LoggerInterface;

use Symfony\Component\EventDispatcher\EventDispatcher;

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
    public function __construct(
        private LoggerInterface $logger,
        private EventDispatcher $dispatcher,
        int $maxPlayers,
        array $phases,
        DeckGenerationDTO $deckGenerationDTO
    ) {
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

        $this->logger->info("Player joined", ["player_id" => $player->getUserId()]);
        $this->players[] = $player;
        $this->broadcastJson(["table_state" => "new_player", "player_id" => $player->getUserId()]);
    }

    public function removePlayer(Player $player): void
    {
        $this->players = array_filter($this->players, fn(Player $value): bool => $value->getUserId() === $player->getUserId());
        $this->logger->info("Player removed", ["player_id" => $player->getUserId()]);
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
        $this->logger->info("New hand");
        $this->broadcastJson(["table_state" => "new_hand"]);
    }

    public function start()
    {
        $this->logger->info("Starting new hand");

        $this->newHand();
        $this->nextPhase();
    }

    public function nextPhase()
    {
        $this->logger->info("Next phase");
        $this->current_hand->playPhase(function (mixed $data): void {
            $this->update($data);
        });
    }

    private function update(mixed $data): void
    {
        if (\is_array($data) && !empty($data["state"])) {
            if ($data["state"] === "next_phase") {
                $this->nextPhase();
                return;
            }

            if ($data["state"] === "no_more_phases") {
                $this->logger->info("No more phases in this hand. Waiting for next hand...");
                return;
            }
        }


        $this->broadcastJson(["table_state" => "table_update", "data" => $data]);
    }
}