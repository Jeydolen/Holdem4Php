<?php

namespace App\Game\Hand\Phase;

use App\Game\Player;
use App\Game\CardPile\Deck;
use App\Game\CardPile\ICardPile;

use App\Event\PhaseState;
use App\Event\PlayerAction;

use App\Service\BettingManager;

use App\Enum\PlayerBettingActionEnum;
use App\Exception\InvalidPlayerBettingActionException;

use DateInterval;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;

use Workerman\Timer;

use Symfony\Component\EventDispatcher\EventDispatcher;

class BettingPhase extends AbstractPhase
{
    private array $players = [];

    /** @var string[] Player IDs that have folded during this phase */
    private array $foldedPlayerIds = [];

    private ?int $timerId = null;
    private int $currentPlayerIndex = 0;
    private BettingManager $bettingManager;

    private function __construct(
        protected LoggerInterface $logger,
        private ?int $timeout,
        private int $maxBettingAmount
    ) {
    }

    public function play(array &$players, Deck &$deck, ?ICardPile $boardCardPile): void
    {
        $this->logger->info("Playing phase", ["phase" => (self::class), "max_betting_amount" => $this->maxBettingAmount]);
        if (\count($players) <= 1) {
            $this->logger->info("Not enough players to play the phase", ["phase" => (self::class)]);
            $this->dispatcher->dispatch(new PhaseState("next_phase"));
            return;
        }

        $this->players = $players;

        $this->askPlayer($this->players[$this->currentPlayerIndex]);
    }

    private function askPlayer(Player $player): void
    {
        $this->logger->info("Asking player to bet", ["player_id" => $player->getUserId()]);

        $timeoutDate = null;
        if (!empty($this->timeout)) {
            $timeoutDate = new DateTimeImmutable()->add(DateInterval::createFromDateString("+ {$this->timeout} seconds"));
            $this->timerId = Timer::add($this->timeout, function () use ($player) {
                $this->logger->info("Player did not bet, folding player", ["player_id" => $player->getUserId()]);
                $this->dispatcher->dispatch(new PhaseState("player_fold", ["player_id" => $player->getUserId()]));
                $this->nextPlayer();
            }, persistent: false);
            $this->logger->info("Timeout timer added", ["timerId" => $this->timerId, "timeout" => $this->timeout]);
        }

        $player->askBet(
            $this->maxBettingAmount,
            $this->bettingManager->computeLegalActions(),
            $this->bettingManager->getMinimalLegalBet(),
            $timeoutDate
        );
    }

    private function nextPlayer(): void
    {
        // Cancel the timer of the current player before moving on
        if (!empty($this->timerId)) {
            Timer::del($this->timerId);
            $this->timerId = null;
        }

        $activePlayers = array_filter(
            $this->players,
            fn(Player $p) => !\in_array($p->getUserId(), $this->foldedPlayerIds)
        );

        if (\count($activePlayers) <= 1) {
            $this->logger->debug("Not enough players to continue betting");
            $this->dispatcher->dispatch(new PhaseState("next_phase"));
            return;
        }

        if (!empty($this->players[$this->currentPlayerIndex + 1])) {
            $this->currentPlayerIndex += 1;
            $this->askPlayer($this->players[$this->currentPlayerIndex]);
            return;
        }

        $this->dispatcher->dispatch(new PhaseState("next_phase"));
    }

    public static function fromArray(array $data): self
    {
        $instance = new self($data["logger"], $data["timeout"] ?? null, $data["maxBettingAmount"]);
        return $instance;
    }

    public function withEventDispatcher(EventDispatcher $dispatcher): static
    {
        $this->dispatcher = $dispatcher;
        $this->bettingManager = new BettingManager($dispatcher);
        $this->dispatcher->addSubscriber($this);
        return $this;
    }

    public function onPlayerAction(PlayerAction $event): void
    {
        $this->logger->debug("Player action", [
            "class" => self::class,
            "event_player" => $event->getPlayer()->getUserId(),
            "waiting_player" => $this->players[$this->currentPlayerIndex]?->getUserId(),
            "data" => $event->getEventData()
        ]);

        if ($event->getPlayer() !== $this->players[$this->currentPlayerIndex]) {
            return;
        }

        $data = $event->getEventData();
        if (empty($data["betting_action"])) {
            return;
        }

        $player_betting_action = PlayerBettingActionEnum::tryFrom($data["betting_action"]);
        if (empty($player_betting_action)) {
            throw new InvalidPlayerBettingActionException();
        }

        $this->logger->info("Player betting action", ["data" => $data, "betting_action" => $player_betting_action]);

        if ($player_betting_action === PlayerBettingActionEnum::FOLD) {
            $this->foldedPlayerIds[] = $event->getPlayer()->getUserId();
        }

        $this->bettingManager->play($event->getPlayer()->getUserId(), $player_betting_action, $data["betting_amount"] ?? null);

        $this->nextPlayer();
    }
}