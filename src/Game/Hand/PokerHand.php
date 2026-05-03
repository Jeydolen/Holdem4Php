<?php

namespace App\Game\Hand;

use App\Event\PhaseState;

use App\Game\Player;
use App\Game\CardPile\Deck;
use App\Game\Hand\Phase\IPhase;
use App\Game\CardPile\ICardPile;
use App\Game\CardPile\BoardCards;

use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * A poker hand in this context is a complete round of poker
 * including the dealt cards for every player, the board, ...²
 */
class PokerHand
{
    /**
     * The players playing the hand
     * @var Player[]
     */
    private array $players;

    /**
     * The players ids of players that will not play the next phase of the hand
     * @var string[]
     */
    private array $foldedPlayerIds = [];

    /**
     * The phases to be played in the hand
     * @var IPhase[]
     */
    private array $phases;

    private Deck $deck;

    private int $phaseIndex = 0;

    private ?ICardPile $boardCardPile;

    public function __construct(
        array $players,
        array $phases,
        Deck $deck,
        private EventDispatcher $dispatcher,
        private LoggerInterface $logger
    ) {
        $this->players = $players;
        $this->phases = $phases;
        $this->deck = $deck;
        $this->boardCardPile = new BoardCards(5, true);
    }

    public function playPhase(): void
    {
        // Index out of bound (phase is indexed by 0 so we need to subtract 1 to the array)
        if ($this->phaseIndex > (\sizeof($this->phases) - 1)) {
            $this->sendPokerHandEndSignal();
            return;
        }

        // Filter folded players
        $active_players = array_values(array_filter($this->players, fn(Player $player) => !\in_array($player->getUserId(), $this->foldedPlayerIds)));
        $this->logger->info("", context: ["active_players" => $active_players, "folded_player_ids" => $this->foldedPlayerIds]);

        // If there is only one player left, he wins automatically
        if (\sizeof($active_players) <= 1) {
            $this->dispatcher->dispatch(new PhaseState("player_won", ["player_id" => $active_players[0]?->getUserId() ?? null, "hand_value" => 0]));
            $this->logger->debug("Last player won", ["players" => $active_players[0] ?? null]);
            $this->sendPokerHandEndSignal();
            return;
        }

        /** @var IPhase */
        $phase = $this->phases[$this->phaseIndex];
        $phase->play($active_players, $this->deck, $this->boardCardPile);
    }

    public function nextPhase(): void
    {
        $this->phaseIndex += 1;
    }

    public function foldPlayer(string $playerId): void
    {
        $player = array_find($this->players, fn(Player $player) => $player->getUserId() === $playerId);

        if (empty($player)) {
            throw new Exception("Player Id not found in players");
        }

        $this->foldedPlayerIds[] = $playerId;
        $this->logger->debug("Player folded", context: ["player" => $player]);
    }

    private function sendPokerHandEndSignal(): void
    {
        $this->dispatcher->dispatch(new PhaseState("no_more_phases"));
    }
}