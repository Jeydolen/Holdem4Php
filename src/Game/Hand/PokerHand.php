<?php

namespace App\Game\Hand;

use App\Event\PhaseState;

use App\Game\Player;
use App\Game\CardPile\Deck;
use App\Game\Hand\Phase\IPhase;
use App\Game\CardPile\ICardPile;
use App\Game\CardPile\BoardCards;

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
     * @var array<Player>
     */
    private array $players;

    /**
     * The players that will not play the next phase of the hand
     * @var array<Player>
     */
    private array $folded_players;

    /**
     * The phases to be played in the hand
     * @var array
     */
    private array $phases;

    private Deck $deck;

    private int $phase_index = 0;

    private ?ICardPile $boardCardPile;

    public function __construct(array $players, array $phases, Deck $deck, private EventDispatcher $dispatcher, private LoggerInterface $logger)
    {
        $this->players = $players;
        $this->phases = $phases;
        $this->deck = $deck;
        $this->boardCardPile = new BoardCards(5, true);
    }

    public function playPhase(): void
    {
        // Index out of bound (phase is indexed by 0 so we need to subtract 1 to the array)
        if ($this->phase_index > (\sizeof($this->phases) - 1)) {
            $this->sendPokerHandEndSignal();
            return;
        }

        // If there is only one player left, he wins automatically
        if (\sizeof($this->players) <= 1) {
            $this->dispatcher->dispatch(new PhaseState("player_won", ["player_id" => $this->players[0]?->getUserId() ?? null, "hand_value" => 0]));
            $this->logger->debug("Last player won", ["players" => $this->players[0] ?? null]);
            $this->sendPokerHandEndSignal();
            return;
        }

        /**
         * @var IPhase
         */
        $phase = $this->phases[$this->phase_index];
        $phase->play($this->players, $this->deck, $this->boardCardPile);
    }

    public function nextPhase(): void
    {
        $this->phase_index += 1;
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