<?php
namespace App\Game\Hand;

use App\Event\PhaseState;

use App\Game\Player\PlayerCollection;
use App\Game\CardPile\Deck;
use App\Game\CardPile\BoardCards;

use App\Game\Hand\Phase\IPhase;

use App\Game\Bet\BettingManager;
use App\Service\CardRank\CardRankEvaluator;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * A poker hand in this context is a complete round of poker
 * including the dealt cards for every player, the board, ...²
 */
class PokerHand
{
    /**
     * The phases to be played in the hand
     * @var IPhase[]
     */
    private array $phases;

    private int $phaseIndex = 0;

    private HandContext $handContext;

    public function __construct(
        array $players,
        array $phases,
        Deck $deck,
        private EventDispatcher $dispatcher,
        private LoggerInterface $logger
    ) {
        $this->phases = $phases;

        $this->handContext = new HandContext(
            $deck,
            new BoardCards(5, true),
            new PlayerCollection($players),
            new BettingManager($dispatcher),
            new CardRankEvaluator()
        );
    }

    public function playPhase(): void
    {
        // Index out of bound (phase is indexed by 0 so we need to subtract 1 to the array)
        if ($this->phaseIndex > (\sizeof($this->phases) - 1)) {
            $this->sendPokerHandEndSignal();
            return;
        }

        // Filter folded players
        $active_players = $this->handContext->getPlayerCollection()->getCompetingPlayers();
        $folded_players = $this->handContext->getPlayerCollection()->getFoldedPlayerIds();
        $this->logger->info("", context: ["active_players" => $active_players, "folded_player_ids" => $folded_players]);

        // If there is only one player left, he wins automatically
        if (\sizeof($active_players) <= 1) {
            $this->dispatcher->dispatch(new PhaseState("player_won", [
                "player_id" => $active_players[0]?->getUserId() ?? null,
                "hand_value" => 0,
                "amount" => $this->handContext->getBettingManager()->getPotAmount()
            ]));
            $this->logger->debug("Last player won", ["players" => $active_players[0] ?? null]);
            $this->sendPokerHandEndSignal();
            return;
        }

        /** @var IPhase */
        $phase = $this->phases[$this->phaseIndex];
        $this->dispatcher->addSubscriber($phase);
        $phase->withEventDispatcher($this->dispatcher);
        $phase->play($this->handContext);
    }

    public function nextPhase(): void
    {
        $this->dispatcher->removeSubscriber($this->phases[$this->phaseIndex]);
        $this->phaseIndex += 1;
    }

    public function foldPlayer(string $playerId): void
    {
        $this->handContext->getPlayerCollection()->foldPlayer($playerId);
        $this->logger->debug("Player folded", context: ["player" => $playerId]);
    }

    private function sendPokerHandEndSignal(): void
    {
        $this->dispatcher->dispatch(new PhaseState("no_more_phases"));
    }

    public function getHandContext(): HandContext
    {
        return $this->handContext;
    }
}