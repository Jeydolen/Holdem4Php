<?php

namespace App\Game\Hand\Phase;

use DateInterval;
use DateTimeImmutable;

use App\Event\PhaseState;
use App\Event\PlayerAction;

use App\Game\Player\Player;
use App\Game\Hand\HandContext;

use App\Service\BettingManager;

use App\Enum\PlayerBettingActionEnum;
use App\Exception\InvalidPlayerBettingActionException;


use Psr\Log\LoggerInterface;

use OpenSwoole\Timer;

use Symfony\Component\EventDispatcher\EventDispatcher;

class BettingPhase extends AbstractPhase
{
    private HandContext $context;

    private ?int $timerId = null;
    private int $currentPlayerIndex = 0;
    private BettingManager $bettingManager;

    private function __construct(
        protected LoggerInterface $logger,
        private ?int $timeout,
        private int $maxBettingAmount
    ) {
    }

    public function play(HandContext $context): void
    {
        $players = $context->getPlayerCollection()->getCompetingPlayers();
        $this->logger->info("Playing phase", ["phase" => (self::class), "max_betting_amount" => $this->maxBettingAmount]);
        if (\count($players) <= 1) {
            $this->logger->info("Not enough players to play the phase", ["phase" => (self::class)]);

            $this->endPhase();
            return;
        }

        $this->context = $context;
        $this->bettingManager = $context->getBettingManager();

        $this->askPlayer($players[$this->currentPlayerIndex]);
    }

    private function askPlayer(Player $player): void
    {
        $this->logger->info("Asking player to bet", ["player_id" => $player->getUserId()]);

        $timeoutDate = null;
        if (!empty($this->timeout)) {
            $timeoutDate = (new DateTimeImmutable())->add(DateInterval::createFromDateString("+ {$this->timeout} seconds"));
            $this->timerId = Timer::after($this->timeout * 1000, function () use ($player) {
                $this->logger->info("Player did not bet, folding player", ["player_id" => $player->getUserId()]);
                $this->dispatcher->dispatch(new PhaseState("player_fold", ["player_id" => $player->getUserId()]));
                $this->nextPlayer();
            });
            $this->logger->info("Timeout timer added", ["timerId" => $this->timerId, "timeout" => $this->timeout]);
        }

        $player->askBet(
            $this->maxBettingAmount,
            $this->bettingManager->computeLegalActions(),
            $this->bettingManager->getMinimalLegalBet(),
            $timeoutDate
        );
    }

    private function cancelCurrentTimer(): void
    {
        if (!empty($this->timerId)) {
            $result = Timer::clear($this->timerId);
            $this->logger->debug("Canceling timer {timer_id}, result: {result}", ["timer_id" => $this->timerId, "result" => $result]);
            $this->timerId = null;
        }
    }

    private function nextPlayer(): void
    {
        // Cancel the timer of the current player before moving on
        $this->cancelCurrentTimer();

        $activePlayers = $this->context->getPlayerCollection()->getCompetingPlayers();
        if (\count($activePlayers) <= 1) {
            $this->logger->debug("Not enough players to continue betting");
            $this->endPhase();
            return;
        }

        // There might be a bug here
        $players = $this->context->getPlayerCollection()->getAllPlayers();

        if (!empty($players[$this->currentPlayerIndex + 1])) {
            $this->currentPlayerIndex += 1;
            $this->askPlayer($players[$this->currentPlayerIndex]);
            return;
        }

        $this->endPhase();
    }

    public static function fromArray(array $data): self
    {
        $instance = new self($data["logger"], $data["timeout"] ?? null, $data["maxBettingAmount"]);
        return $instance;
    }

    public function withEventDispatcher(EventDispatcher $dispatcher): static
    {
        $this->dispatcher = $dispatcher;
        $this->dispatcher->addSubscriber($this);
        return $this;
    }

    public function onPlayerAction(PlayerAction $event): void
    {
        $players = $this->context->getPlayerCollection()->getAllPlayers();
        $player = $event->getPlayer();
        $this->logger->debug(
            "Waiting for player {player} timer {timer_id} event player {event_player}",
            [
                "player" => $players[$this->currentPlayerIndex] ?? null,
                "timer_id" => $this->timerId,
                "event_player" => $player,
            ]
        );

        if ($player !== $players[$this->currentPlayerIndex]) {
            return;
        }

        $data = $event->getEventData();
        if (empty($data["betting_action"])) {
            return;
        }

        $event->stopPropagation();

        $player_betting_action = PlayerBettingActionEnum::tryFrom($data["betting_action"]);
        if (empty($player_betting_action)) {
            throw new InvalidPlayerBettingActionException();
        }

        $this->logger->info("Player betting action", [
            "betting_action" => $player_betting_action,
            "betting_amount" => $data["betting_amount"] ?? null,
            "player_id" => $player->getUserId()
        ]);

        $this->bettingManager->play($player->getUserId(), $player_betting_action, $data["betting_amount"] ?? null);
        // Send player acknowledgement
        $player->sendMessage(["action" => "ack_bet"]);

        // Ask players again
        if (\in_array($player_betting_action, BettingManager::NOTABLE_ACTIONS)) {
            $player->setBetTotalAmount($player->getBetTotalAmount() + $data["betting_amount"]);
            $this->dispatcher->dispatch(new PhaseState("pot_amount", ["pot_amount" => $this->bettingManager->getPotAmount()]));

            if (empty($players[$this->currentPlayerIndex + 1])) {
                $this->logger->info("Player did a notable action, need to ask every player again");
                // We set it to -1 because nextPlayer will increment it
                $this->currentPlayerIndex = -1;
            }
        }

        $this->nextPlayer();
    }
}