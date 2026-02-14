<?php

namespace App\Service;

use App\Enum\PlayerBettingActionEnum;
use App\Event\PhaseState;
use App\Exception\InvalidPlayerBettingActionException;

use Symfony\Component\EventDispatcher\EventDispatcher;

class BettingManager
{
    public const array NOTABLE_ACTIONS = [
        PlayerBettingActionEnum::BET
    ];

    /**
     * A notable action (an action that changes what the next player can do; ie. any kind of bet)
     * @var 
     */
    private ?PlayerBettingActionEnum $previousNotableAction = null;

    /**
     * Array of all legal actions for a player depending of previous actions
     * @var PlayerBettingActionEnum[]
     */
    private array $legalActions;

    private int $minimalLegalBet = 0;

    public function __construct(
        private EventDispatcher $dispatcher,
        private ?int $pot = 0
    ) {
    }

    public function getPotAmount(): int
    {
        return $this->pot;
    }

    public function getMinimalLegalBet(): int
    {
        return $this->minimalLegalBet;
    }

    public function computeLegalActions(): array
    {
        // By default, if there is no previous action, you can FOLD, CHECK or BET (and ALL_IN which is a type of BET)
        if (empty($this->previousNotableAction)) {
            return [PlayerBettingActionEnum::FOLD, PlayerBettingActionEnum::BET, PlayerBettingActionEnum::CHECK];
        }

        // When the previous action is a bet (or all_in)
        // The player can FOLD, BET (raise in that case), CALL and ALL_IN
        // if ($this->previousNotableAction === PlayerBettingActionEnum::BET) {
        return [PlayerBettingActionEnum::FOLD, PlayerBettingActionEnum::BET, PlayerBettingActionEnum::CALL];
        // }
    }

    public function validatePlayerAction(PlayerBettingActionEnum $playerAction, ?int $playerBet = null): true
    {
        $this->legalActions = $this->computeLegalActions();
        if (!\in_array($playerAction, $this->legalActions)) {
            throw new InvalidPlayerBettingActionException();
        }

        if (\in_array($playerAction, [PlayerBettingActionEnum::BET, PlayerBettingActionEnum::CALL])) {
            if (empty($playerBet) || $playerBet < $this->minimalLegalBet) {
                throw new InvalidPlayerBettingActionException();
            }

            $this->minimalLegalBet = $playerBet;
        }

        return true;
    }

    public function play(string $playerId, PlayerBettingActionEnum $playerAction, ?int $playerBet)
    {
        $this->validatePlayerAction($playerAction, $playerBet);

        if (\in_array($playerAction, self::NOTABLE_ACTIONS)) {
            $this->previousNotableAction = $playerAction;
        }

        if (\in_array($playerAction, [PlayerBettingActionEnum::BET, PlayerBettingActionEnum::CALL])) {
            $this->pot += $playerBet;
        }

        if ($playerAction === PlayerBettingActionEnum::FOLD) {
            $this->dispatcher->dispatch(new PhaseState("player_fold", ["player_id" => $playerId]));
        }

        // For the CHECK action, there is nothing to do
    }
}