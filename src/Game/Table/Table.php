<?php

namespace App\Game\Table;

use App\DTO\DeckGenerationDTO;

use App\Event\PhaseState;
use App\Game\Player;
use App\Game\Hand\PokerHand;
use App\Game\Hand\Phase\IPhase;
use App\Game\CardPile\DeckFactory;

use App\Game\Table\Exception\TableFullException;
use App\Game\Table\Exception\PlayerAlreadyInGameException;

use Psr\Log\LoggerInterface;

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

        // Table has the responsability to provide the event dispatcher to the phases 
        foreach ($phases as $phase) {
            $phase->withEventDispatcher($dispatcher);
        }

        $this->deckFactory = new DeckFactory($deckGenerationDTO);
        $this->dispatcher->addSubscriber($this);
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

        // TODO: Different condition depending on table type (tournament, cash game, ...)

        $this->logger->info("Player joined", ["player_id" => $player->getUserId()]);
        $this->players[] = $player;
        $this->dispatcher->addSubscriber($player);

        $this->broadcastJson(["table_state" => "new_player", "player_id" => $player->getUserId(), "player_count" => \sizeof($this->players)]);
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

        if (!$reconnect) {
            $player->sendMessage($event_message);
            $this->broadcastJson($event_message);
        } else {
            // This works to disconnect the previous player without disconnecting the new one
            $player->sendMessage($event_message);
        }
    }

    public function getPlayer(string $userId): ?Player
    {
        $player = array_find($this->players, fn(Player $value): bool => $value->getUserId() === $userId);
        return $player;
    }

    public function newHand()
    {
        // Save previous hand in db for the history
        // $this->current_hand;
        foreach ($this->players as $player) {
            $player->resetState();
        }

        $this->current_hand = new PokerHand($this->players, $this->phases, $this->deckFactory->newDeck(), $this->dispatcher, $this->logger);
        $this->logger->info("New hand");
        $this->broadcastJson(["table_state" => "new_hand"]);
    }

    public function start()
    {
        $this->logger->info("Starting new hand");

        $this->newHand();
        $this->current_hand->playPhase();
    }

    public function nextPhase()
    {
        $this->logger->info("Next phase");
        $this->current_hand->nextPhase();
        $this->current_hand->playPhase();
    }

    public function onPhaseStateUpdate(PhaseState $event): void
    {
        if ($event->getAction() === "no_more_phases") {
            $this->logger->info("No more phases in this hand. Waiting for next hand...");
            $this->broadcastJson(["table_state" => "table_update", "action" => $event->getAction()]);
            return;
        }

        if ($event->getAction() === "next_phase") {
            $this->nextPhase();
        }


        if ($event->getAction() === "player_fold") {
            $this->logger->info("Table received player fold instruction", context: ["data" => $event->getEventData()]);
            $this->current_hand->foldPlayer($event->getEventData()["player_id"]);
        }

        $this->broadcastJson(["table_state" => "table_update", "data" => $event->getEventData(), "action" => $event->getAction()]);
    }
}