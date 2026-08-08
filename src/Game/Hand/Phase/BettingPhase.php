<?php

namespace App\Game\Hand\Phase;

use DateInterval;
use DateTimeImmutable;

use App\Event\PhaseState;
use App\Event\PlayerAction;

use App\Game\Player\Player;
use App\Game\Hand\HandContext;

use App\Game\Bet\BettingManager;

use App\Game\Bet\PlayerBettingActionEnum;
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

    private bool $isNewBettingRound = false;

    private function __construct(
        protected LoggerInterface $logger,
        private ?int $timeout,
        private int $maxBettingAmount
    ) {
    }

    public function play(HandContext $context): void
    {
        // IMPORTANT: Need to reset every time phase is played
        $this->currentPlayerIndex = 0;
        $this->cancelCurrentTimer();

        $players = $context->getPlayerCollection()->getCompetingPlayers();
        $this->logger->info("Playing phase", ["phase" => (self::class), "max_betting_amount" => $this->maxBettingAmount]);
        if (\count($players) <= 1) {
            $this->logger->info("Not enough players to play the phase", ["phase" => (self::class)]);

            $this->endPhase();
            return;
        }

        $this->context = $context;
        $this->bettingManager = $context->getBettingManager();
        // We have to reset BettingManager state too
        $this->bettingManager->resetState();

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
            Timer::clear($this->timerId);
            $this->timerId = null;
        }
    }

    private function nextPlayer(): void
    {
        // Cancel the timer of the current player before moving on
        $this->cancelCurrentTimer();

        $activePlayers = $this->context->getPlayerCollection()->getCompetingPlayers();
        $playerCount = \count($activePlayers);

        if ($playerCount <= 1) {
            $this->logger->debug("Not enough players to continue betting");
            $this->endPhase();
            return;
        }

        $nextIndex = $this->currentPlayerIndex + 1;
        if ($nextIndex < $playerCount) {
            $this->currentPlayerIndex = $nextIndex;
            $this->askPlayer($activePlayers[$this->currentPlayerIndex]);
        } else {
            $this->logger->debug("No more players to ask. Round complete.");
            $this->endPhase();
        }
    }

    public static function fromArray(array $data): self
    {
        $instance = new self($data["logger"], $data["timeout"] ?? null, $data["maxBettingAmount"] ?? $data["maxBuyIn"]);
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
        /**
         * @var Player[]
         */
        $competingPlayers = array_values($this->context->getPlayerCollection()->getCompetingPlayers());
        $player = $event->getPlayer();
        $currentPlayer = $competingPlayers[$this->currentPlayerIndex] ?? null;

        $this->logger->debug(
            "Waiting for player action",
            [
                "current_player_id" => $currentPlayer?->getUserId(),
                "event_player_id" => $player->getUserId(),
                "player_count" => \count($competingPlayers),
                "legal_actions" => $this->bettingManager->computeLegalActions()
            ]
        );

        // Only process action if it is the current player's turn
        if ($player->getUserId() !== ($currentPlayer?->getUserId())) {
            return;
        }

        $data = $event->getEventData();
        if (empty($data["betting_action"])) {
            return;
        }

        $event->stopPropagation();

        $player_betting_action = PlayerBettingActionEnum::tryFrom($data["betting_action"]);
        if (!$player_betting_action) {
            throw new InvalidPlayerBettingActionException();
        }

        $this->logger->info("Player betting action", [
            "action" => $player_betting_action->value,
            "amount" => $data["betting_amount"] ?? 0,
            "player_id" => $player->getUserId(),
            "player_total_bet_amount" => $player->getBetTotalAmount()
        ]);

        // play() now returns true ONLY if the bet amount changed (Raise/Bet)
        $potAmountChanged = $this->bettingManager->play(
            $player->getUserId(),
            $player_betting_action,
            $data["betting_amount"] ?? null,
            $player->getBetTotalAmount()
        );

        $player->sendMessage(["action" => "ack_bet"]);

        // We have to set the bet total amount when it is a notable action OR Call
        if ($potAmountChanged || $player_betting_action === PlayerBettingActionEnum::CALL) {
            if ($potAmountChanged) {
                $this->isNewBettingRound = true;
            }

            $amountToCall = $this->bettingManager->getMinimalLegalBet() - $player->getBetTotalAmount();

            $player->setBetTotalAmount(($player->getBetTotalAmount() ?? 0) + $amountToCall);
            // Don't forget to remove player bankroll
            $player->removeBankroll($amountToCall);
            $this->dispatcher->dispatch(new PhaseState("pot_amount", ["pot_amount" => $this->bettingManager->getPotAmount()]));
        }

        // If it's the last player in the list and they raised, we must restart the loop
        if ($this->isNewBettingRound && empty($competingPlayers[$this->currentPlayerIndex + 1])) {
            // We have to evaluate if every players did bet enough to continue
            $needNewRound = false;
            foreach ($competingPlayers as $player) {
                // We have to verify everyone is at least on minimalLegalBet OR did allIn (i.e. no bankroll left)

                // Player did allIn
                if ($player->getBankroll() === 0) {
                    continue;
                }

                if ($player->getBetTotalAmount() < $this->bettingManager->getMinimalLegalBet()) {
                    $needNewRound = true;
                }
            }

            // We don't need a new round
            if (!$needNewRound) {
                $this->nextPlayer();
                return;
            }

            $this->logger->info("Betting level changed, resetting turn to first player");
            // We set it to -1 so that nextPlayer() increments it to 0 (the first player)
            $this->currentPlayerIndex = -1;
            $this->isNewBettingRound = false;
        }

        $this->nextPlayer();
    }
}