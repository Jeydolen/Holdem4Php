<?php

namespace App\Game\Table;

use App\DTO\DeckGenerationDTO;

use App\Entity\TablePlayers;
use App\Entity\Table as EntityTable;

use App\Enum\TableStateEnum;
use App\Enum\TableTypeEnum;

use App\Event\PhaseState;

use App\Game\Player\Player;
use App\Game\Hand\PokerHand;
use App\Game\Hand\Phase\IPhase;
use App\Game\CardPile\DeckFactory;

use App\Game\Table\Exception\TableException;
use App\Game\Table\Exception\TableFullException;
use App\Game\Table\Exception\PlayerAlreadyInGameException;

use OpenSwoole\Timer;

use Psr\Log\LoggerInterface;

use Doctrine\ORM\EntityManagerInterface;

use Symfony\Contracts\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class Table implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [PhaseState::class => 'onPhaseStateUpdate'];
    }

    /**
     * @var Player[]
     */
    private array $players = [];
    private PokerHand $currentHand;

    public readonly int $maxPlayers;

    /**
     * @var IPhase[]
     */
    public readonly array $phases;

    private DeckFactory $deckFactory;

    private TableStateEnum $tableState;

    private int $startingTimerId;

    /**
     * @param int $maxPlayers
     * @param IPhase[] $phases
     */
    public function __construct(
        int $maxPlayers,
        array $phases,
        DeckGenerationDTO $deckGenerationDTO,
        private LoggerInterface $logger,
        private EventDispatcher $dispatcher,
        private EntityTable $entityTable,
        private EntityManagerInterface $em
    ) {
        $this->maxPlayers = $maxPlayers;
        $this->phases = $phases;

        $this->updateTableState(TableStateEnum::WAITING_FOR_PLAYERS);

        // Table has the responsability to provide the event dispatcher to the phases 
        foreach ($phases as $phase) {
            $phase->withEventDispatcher($dispatcher);
        }

        $this->deckFactory = new DeckFactory($deckGenerationDTO);
        $this->dispatcher->addSubscriber($this);
    }

    private function updateTableState(TableStateEnum $newTableState): void
    {
        $this->logger->info("Updating table state", ["old_table_state" => $this->tableState?->name ?? null, "new_table_state" => $newTableState->name]);
        $this->tableState = $newTableState;
    }

    public function getEntityTable(): EntityTable
    {
        return $this->entityTable;
    }

    public function dispatchEvent(Event $event)
    {
        $this->dispatcher->dispatch($event);
    }

    public function broadcastJson(mixed $data): void
    {
        foreach ($this->players as $player) {
            $player->sendMessage($data);
        }
    }

    public function addPlayer(Player $player): void
    {
        $currentPlayer = $this->getPlayer($player->getUserId());
        if (!empty($currentPlayer)) {
            $this->logger->debug(
                "This player is already connected to the table",
                ["player_id" => $currentPlayer->getUserId(), "new_player_id" => $player->getUserId()]
            );

            if ($currentPlayer->isSame($player)) {
                throw new PlayerAlreadyInGameException();
            } else {
                // If the connection is different, we need to replace the previous player instance
                // (this is in the case of a reconnection)
                $this->logger->debug("The player is connecting with another connection, removing previous player instance", ["previous_player_id" => $currentPlayer->getUserId()]);
                $this->removePlayer($currentPlayer, true);
            }
        }

        if (\sizeof($this->players) >= $this->maxPlayers) {
            throw new TableFullException();
        }

        if (\in_array($this->tableState, [TableStateEnum::IN_PROGRESS, TableStateEnum::FINISHED])) {
            throw new TableException("Game is already running");
        }

        // TODO: Different condition depending on table type (tournament, cash game, ...)

        $this->logger->info("Player joined", ["player_id" => $player->getUserId()]);
        $this->players[] = $player;
        $this->dispatcher->addSubscriber($player);

        $this->broadcastJson(["table_state" => "new_player", "player_id" => $player->getUserId(), "player_count" => \sizeof($this->players)]);

        $this->evaluateTableStatus();

        $table_player = new TablePlayers();
        $table_player->setTable($this->getEntityTable());
        $table_player->setUser($player->getUser());
        $table_player->setAmount(0);
        $this->entityTable->addTablePlayer($table_player);
        $this->em->flush();
    }

    /**
     * Remove a player from the table
     * @param Player $player The player to remove
     * @param bool $reconnect When this flag is set, no broadcast is sent because the player is connecting from another connection
     * @return void
     */
    public function removePlayer(Player $player, bool $reconnect): void
    {
        $this->players = array_filter($this->players, fn(Player $value): bool => $value->getUserId() !== $player->getUserId());
        $this->logger->info("Player removed", ["player_id" => $player->getUserId(), "player_count" => \sizeof($this->players)]);

        $event_message = ["table_state" => "remove_player", "player_id" => $player->getUserId(), "player_count" => \sizeof($this->players)];
        if ($reconnect) {
            // This works to disconnect the previous player without disconnecting the new one
            $player->sendMessage($event_message);
            return;
        }

        $player->sendMessage($event_message);
        $this->broadcastJson($event_message);

        $this->evaluateTableStatus();

        // If the game is not in a waiting state, the player won't be refunded his table bankroll

        $table_player = $this->entityTable->getTablePlayers()->findFirst(fn($k, $v) => $v->getUser()->getUserId()->toString() === $player->getUserId());
        $this->entityTable->removeTablePlayer($table_player);
        $this->em->flush();
    }

    public function getPlayer(string $userId): ?Player
    {
        $player = array_find($this->players, fn(Player $value): bool => $value->getUserId() === $userId);
        return $player;
    }

    public function newHand()
    {
        // Save previous hand in db for the history
        // $this->currentHand;
        foreach ($this->players as $player) {
            $player->resetState();
        }

        $this->currentHand = new PokerHand($this->players, $this->phases, $this->deckFactory->newDeck(), $this->dispatcher, $this->logger);
        $this->logger->info("New hand");
        $this->broadcastJson(["table_state" => "new_hand"]);
    }

    private function createStartTimer()
    {
        $this->startingTimerId = Timer::after(($this->entityTable->getVariant()->getStartingTimer() ?? 5) * 1000, function () {
            $this->evaluateTableStatus();
        });
    }

    private function evaluateTableStatus(): void
    {
        // Table state changes depending on game mode
        if ($this->tableState === TableStateEnum::WAITING_FOR_PLAYERS && $this->canStart()) {
            $this->updateTableState(TableStateEnum::STARTING);
            $this->createStartTimer();
            return;
        }

        // Unfortunately, a player disconnected while the game is starting
        if ($this->tableState === TableStateEnum::STARTING && !$this->canStart()) {
            // Go back to WAITING state
            Timer::clear($this->startingTimerId);
            $this->updateTableState(TableStateEnum::WAITING_FOR_PLAYERS);
            return;
        }

        // Table is in starting state and we still have enough players, we can play
        if ($this->tableState === TableStateEnum::STARTING && $this->canStart()) {
            $this->updateTableState(TableStateEnum::IN_PROGRESS);
            $this->start();
            return;
        }
    }

    public function canStart(): bool
    {
        $canStart = \count($this->players) >= ($this->entityTable->getVariant()->getMinPlayerThreshold() ?? 2);
        $this->logger->info("Evaluating if game can start", [
            "can_start" => $canStart,
            "threshold" => $this->entityTable->getVariant()->getMinPlayerThreshold() ?? 2,
            "player_count" => \count($this->players)
        ]);

        return $canStart;
    }

    public function start()
    {
        $this->logger->info("Starting new hand");

        $this->newHand();
        $this->currentHand->playPhase();
    }

    public function closeTable(): void
    {
        $this->logger->info("Closing table...");
        // Telling every player that table is closing
        foreach ($this->players as $p) {
            $p->sendMessage(["action" => "table_close"]);
        }

        if (!empty($this->startingTimerId)) {
            Timer::clear($this->startingTimerId);
            $this->tableState = TableStateEnum::FINISHED;
        }
    }

    public function nextPhase()
    {
        $this->logger->info("Next phase");
        $this->currentHand->nextPhase();
        $this->currentHand->playPhase();
    }

    public function onPhaseStateUpdate(PhaseState $event): void
    {
        if ($event->getAction() === "no_more_phases") {
            $this->logger->info("No more phases in this hand. Waiting for next hand...");
            $this->broadcastJson(["table_state" => "table_update", "action" => $event->getAction()]);

            // Hand is finished, if it is a CASH_GAME we can stay in waiting_for_player state
            if ($this->entityTable->getVariant()->getTableType() === TableTypeEnum::CASH_GAME->value) {
                $this->updateTableState(TableStateEnum::WAITING_FOR_PLAYERS);
                // If we don't evaluate status, nothing happens until a player join / quit
                $this->evaluateTableStatus();
            } else {
                $this->updateTableState(TableStateEnum::FINISHED);
            }
            return;
        }

        if ($event->getAction() === "next_phase") {
            $this->nextPhase();
        }


        if ($event->getAction() === "player_fold") {
            $this->logger->info("Table received player fold instruction", context: ["data" => $event->getEventData()]);
            $this->currentHand->foldPlayer($event->getEventData()["player_id"]);
        }

        $this->broadcastJson(["table_state" => "table_update", "data" => $event->getEventData(), "action" => $event->getAction()]);
    }
}