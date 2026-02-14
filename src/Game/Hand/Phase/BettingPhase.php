<?php

namespace App\Game\Hand\Phase;

use App\Game\Player;
use App\Game\CardPile\Deck;

use App\Event\PhaseState;
use App\Event\PlayerAction;

use App\Enum\PlayerBettingActionEnum;
use App\Exception\InvalidPlayerBettingActionException;

use App\Service\BettingManager;

use DateInterval;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;

use Workerman\Timer;

use Symfony\Component\EventDispatcher\EventDispatcher;

class BettingPhase extends AbstractPhase
{
    private array $players = [];
    private ?int $timerId = null;
    private int $currentPlayerIndex = 0;
    private BettingManager $bettingManager;

    private function __construct(
        private EventDispatcher $dispatcher,
        protected LoggerInterface $logger,
        private ?int $timeout,
        private int $maxBettingAmount
    ) {
        $this->bettingManager = new BettingManager($this->dispatcher);
    }

    public function play(array &$players, Deck &$deck): void
    {
        $this->logger->info("Playing phase", ["phase" => (self::class), "max_betting_amount" => $this->maxBettingAmount]);

        $this->players = $players;
        // We ask every player to bet, they can either call, check, raise or fold
        $this->askPlayer($players[$this->currentPlayerIndex]);
    }

    private function askPlayer(Player $player)
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
        // We go to the next player and do wait for the response of the current one
        if (!empty($this->players[$this->currentPlayerIndex + 1])) {
            $this->currentPlayerIndex += 1;
            $this->askPlayer($this->players[$this->currentPlayerIndex]);
            return;
        }

        $this->dispatcher->dispatch(new PhaseState("next_phase"));
    }

    public static function fromArray(array $data): self
    {
        return new self($data["dispatcher"], $data["logger"], $data["timeout"] ?? null, $data["maxBettingAmount"]);
    }

    public function onPlayerAction(PlayerAction $event): void
    {
        if ($event->getPlayer() !== $this->players[$this->currentPlayerIndex]) {
            return;
        }

        $data = $event->getEventData();
        if (!empty($data["betting_action"])) {
            $player_betting_action = PlayerBettingActionEnum::tryFrom($data["betting_action"]);
            if (empty($player_betting_action)) {
                throw new InvalidPlayerBettingActionException();
            }

            $this->bettingManager->play($event->getPlayer()->getUserId(), $player_betting_action, $data["betting_amount"] ?? null);
        }

        if (!empty($this->timerId)) {
            Timer::del($this->timerId);
        }

        // Ask next player
        $this->nextPlayer();
    }
}