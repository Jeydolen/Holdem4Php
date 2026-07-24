<?php

namespace App\Game\Table;

use App\DTO\DeckGenerationDTO;

use App\Entity\Stake;
use App\Entity\TablePlayers;
use App\Entity\Table as EntityTable;

use App\Enum\TableStateEnum;
use App\Enum\TableTypeEnum;

use App\Event\PhaseState;

use App\Game\Player\Player;
use App\Game\Hand\PokerHand;
use App\Game\Hand\Phase\IPhase;
use App\Game\CardPile\DeckFactory;

use App\Game\Position\PositionManager;

use App\Game\Table\Exception\TableException;
use App\Game\Table\Exception\TableFullException;
use App\Game\Table\Exception\PlayerAlreadyInGameException;
use App\Game\Table\Exception\InsufficientBankrollException;

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

    private PokerHand $currentHand;

    public readonly int $maxPlayers;

    /**
     * @var IPhase[]
     */
    public readonly array $phases;

    private DeckFactory $deckFactory;

    private TableStateEnum $tableState;

    private int $startingTimerId;

    private Stake $stake;

    private EventDispatcher $dispatcher;

    private PositionManager $positionManager;

    /**
     * @param int $maxPlayers
     * @param IPhase[] $phases
     */
    public function __construct(
        int $maxPlayers,
        array $phases,
        DeckGenerationDTO $deckGenerationDTO,
        private LoggerInterface $logger,
        private EntityTable $entityTable,
        private EntityManagerInterface $em
    ) {
        $this->maxPlayers = $maxPlayers;
        $this->phases = $phases;
        $this->stake = $entityTable->getStake();

        $this->positionManager = new PositionManager([], $this->logger);

        $this->updateTableState(TableStateEnum::WAITING_FOR_PLAYERS);

        $this->deckFactory = new DeckFactory($deckGenerationDTO);
        $this->resetDispatcher();
    }

    private function resetDispatcher(): void
    {
        $this->dispatcher = new EventDispatcher();
        $this->dispatcher->addSubscriber($this);

        foreach ($this->positionManager->getPlayers() as $player) {
            $this->dispatcher->addSubscriber($player);
        }
    }

    private function updateTableState(TableStateEnum $newTableState): void
    {
        $this->logger->info("Updating table state", ["old_table_state" => $this->tableState?->name ?? null, "new_table_state" => $newTableState->name]);
        $this->tableState = $newTableState;

        $this->entityTable->setTableStatus($newTableState->name);
        $this->em->flush();
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
        foreach ($this->positionManager->getPlayers(false) as $player) {
            $player->sendMessage($data);
        }
    }

    /**
     * Check if player can connect to the table
     * @param Player $player
     * @throws PlayerAlreadyInGameException
     * @throws TableFullException
     * @throws TableException
     * @return true|Player Player when already connected (from another connection) or true when it is a new player
     */
    private function canPlayerConnect(Player $player, int $playerBuyIn): true|Player
    {
        $currentPlayer = $this->positionManager->getPlayer($player->getUserId());
        if (!empty($currentPlayer)) {
            $this->logger->debug(
                "This player is already connected to the table",
                ["player_id" => $currentPlayer->getUserId(), "new_player_id" => $player->getUserId()]
            );

            if ($currentPlayer->isSame($player)) {
                throw new PlayerAlreadyInGameException();
            }

            // If the connection is different, we need to replace the previous player instance
            // (this is in the case of a reconnection)
            $this->logger->debug("The player is connecting with another connection, removing previous player instance", ["previous_player_id" => $currentPlayer->getUserId()]);
            $this->removePlayer($currentPlayer, true);

            return $currentPlayer;
        }

        if ($this->positionManager->getPlayerCount() >= $this->maxPlayers) {
            throw new TableFullException();
        }

        // Can't connect to running table except if player joined before
        if ($this->tableState !== TableStateEnum::WAITING_FOR_PLAYERS) {
            throw new TableException("Game is already running");
        }

        // Check bankroll
        if (($player->getUser()->getBankroll()?->getAmount() ?? 0) < $playerBuyIn) {
            throw new InsufficientBankrollException("Player has not enough bankroll to join table");
        }

        // Check min / max buy in
        if ($playerBuyIn < $this->stake->getMinBuyIn() || $playerBuyIn > $this->stake->getMaxBuyIn()) {
            throw new TableException("Player buy in is outside authorized range !");
        }

        return true;
    }

    public function addPlayer(Player $player, int $playerBuyIn): void
    {
        $player_connect = $this->canPlayerConnect($player, $playerBuyIn);
        if (empty($player_connect)) {
            $this->logger->info("Player cannot connect to table", ["player_id" => $player->getUserId()]);
            return;
        }

        // Don't add new player if reconnect
        if ($player_connect === true) {
            $this->logger->info("New connection, we need to set bankroll");
            $player->setBankroll($playerBuyIn);
            $bankroll = $player->getUser()->getBankroll();
            $bankroll->setAmount($bankroll->getAmount() - $playerBuyIn);

            $table_player = new TablePlayers();
            $table_player->setTable($this->getEntityTable());
            $table_player->setUser($player->getUser());
            $table_player->setAmount($playerBuyIn);
            $this->entityTable->addTablePlayer($table_player);
            $this->em->flush();
        }

        $this->logger->info("Player joined", ["player_id" => $player->getUserId()]);
        $this->positionManager->addPlayer($player);
        $this->dispatcher->addSubscriber($player);

        $this->evaluateTableStatus();

        // We have to broadcast the event AFTER the persistance bc it won't contain player bankroll otherwise
        $this->broadcastJson([
            "table_state" => "new_player",
            "player_id" => $player->getUserId(),
            "player" => $player->getPublicState(),
            "player_count" => $this->positionManager->getPlayerCount()
        ]);
        $this->sendAuthoritativeTableState($player);
    }

    /**
     * Remove a player from the table
     * @param Player $player The player to remove
     * @param bool $reconnect When this flag is set, no broadcast is sent because the player is connecting from another connection
     * @return void
     */
    public function removePlayer(Player $player, bool $reconnect): void
    {
        $this->positionManager->removePlayer($player);
        $player_count = $this->positionManager->getPlayerCount();

        $event_message = [
            "table_state" => "remove_player",
            "player_id" => $player->getUserId(),
            "player_count" => $player_count
        ];

        $this->logger->info("Player removed", $event_message);

        if ($reconnect) {
            // This works to disconnect the previous player without disconnecting the new one
            $player->sendMessage($event_message);
            return;
        }

        $this->dispatcher->removeSubscriber($player);

        $player->sendMessage($event_message);
        $this->broadcastJson($event_message);

        $this->evaluateTableStatus();

        // If the game is not in a waiting state, the player won't be refunded his table bankroll
        if ($this->tableState === TableStateEnum::WAITING_FOR_PLAYERS) {
            $this->logger->info("Player disconnecting in waiting state, refunding bankroll...", ["table_bankroll" => $player->getBankroll()]);
            $bankroll = $player->getUser()->getBankroll();
            $bankroll->setAmount($bankroll->getAmount() + $player->getBankroll());
        }

        $table_player = $this->entityTable->getTablePlayers()->findFirst(fn($k, $v) => $v->getUser()->getUserId()->toString() === $player->getUserId());
        $this->entityTable->removeTablePlayer($table_player);

        $this->em->flush();
    }

    public function getPlayer(string $userId): ?Player
    {
        return $this->positionManager->getPlayer($userId);
    }

    public function newHand()
    {
        // We reset the dispatcher to remove references to previous phases
        $this->resetDispatcher();

        // Save previous hand in db for the history
        // $this->currentHand;
        $players = $this->positionManager->getPlayers();
        foreach ($players as $player) {
            $player->resetState();
        }

        unset($this->currentHand);
        $this->currentHand = new PokerHand($players, $this->phases, $this->deckFactory->newDeck(), $this->dispatcher, $this->logger);
        $this->logger->info("New hand");
        $this->broadcastJson(["table_state" => "new_hand"]);
    }

    private function createStartTimer()
    {
        $this->startingTimerId = Timer::after(($this->entityTable->getVariant()->getStartingTimer() ?? 5) * 1000, fn() => $this->evaluateTableStatus());
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
        $player_count = $this->positionManager->getPlayerCount();
        $can_start = $player_count >= ($this->entityTable->getVariant()->getMinPlayerThreshold() ?? 2);
        $this->logger->info("Evaluating if game can start", [
            "can_start" => $can_start,
            "threshold" => $this->entityTable->getVariant()->getMinPlayerThreshold() ?? 2,
            "player_count" => $player_count
        ]);

        return $can_start;
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
        foreach ($this->positionManager->getPlayers() as $p) {
            $p->sendMessage(["action" => "table_close"]);
            $this->removePlayer($p, false);
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
                // $this->evaluateTableStatus();
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

        if ($event->getAction() === "player_won") {
            // We have to award player his chips
            $user_id = $event->getEventData()["player_id"];
            $amount = $event->getEventData()["amount"];

            // Be careful set is not the same as add Bankroll
            $this->getPlayer($user_id)?->addBankroll($amount);
        }

        $this->broadcastJson(["table_state" => "table_update", "data" => $event->getEventData(), "action" => $event->getAction()]);
        $this->broadcastTableState();
    }

    public function broadcastTableState()
    {
        foreach ($this->positionManager->getPlayers(false) as $player) {
            $this->sendAuthoritativeTableState($player);
            $player->sendCurrentState();
        }
    }

    public function sendAuthoritativeTableState(Player $player): void
    {
        $players = array_map(fn($p) => $p->getPublicState(), $this->positionManager->getPlayers());
        // No hand, no data to send
        if (empty($this->currentHand) || $this->tableState !== TableStateEnum::IN_PROGRESS) {
            $player->sendMessage([
                "event" => "table_state_authoritative",
                "hand_started" => false,
                "data" => ["players" => $players]
            ]);
            return;
        }
        $hand_context = $this->currentHand->getHandContext();

        $data = [
            "hand_started" => true,
            // Send current hand players
            "players" => \array_map(fn(Player $p) => $p->getPublicState(), $hand_context->getPlayerCollection()->getAllPlayers()),
            "board_cards" => $hand_context->getBoardCards()->getCards(),
            "folded_player_ids" => $hand_context->getPlayerCollection()->getFoldedPlayerIds(),
            "pot_amount" => $hand_context->getBettingManager()->getPotAmount(),
        ];

        $player->sendMessage(["event" => "table_state_authoritative", "data" => $data]);
    }
}