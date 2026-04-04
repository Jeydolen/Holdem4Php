<?php

namespace App\Service;

use App\Event\PhaseState;
use App\Enum\PlayerBettingActionEnum;
use App\Exception\InvalidPlayerBettingActionException;

use Symfony\Component\EventDispatcher\EventDispatcher;

class BettingManager
{
    public const array NOTABLE_ACTIONS = [
        PlayerBettingActionEnum::BET,
        PlayerBettingActionEnum::CALL,
    ];

    private ?PlayerBettingActionEnum $previousNotableAction = null;

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

    /**
     * Returns the legal actions for the current player based on previous actions.
     *
     * @return PlayerBettingActionEnum[]
     */
    public function computeLegalActions(): array
    {
        // By default, if there is no previous action, you can FOLD, CHECK or BET (and ALL_IN which is a type of BET)
        if (empty($this->previousNotableAction)) {
            return [PlayerBettingActionEnum::FOLD, PlayerBettingActionEnum::BET, PlayerBettingActionEnum::CHECK];
        }

        // A bet is already on the table: check is no longer allowed
        return [PlayerBettingActionEnum::FOLD, PlayerBettingActionEnum::BET, PlayerBettingActionEnum::CALL];
    }

    /**
     * @throws InvalidPlayerBettingActionException
     */
    public function validatePlayerAction(PlayerBettingActionEnum $playerAction, ?int $playerBet = null): void
    {
        if (!\in_array($playerAction, $this->computeLegalActions())) {
            throw new InvalidPlayerBettingActionException();
        }

        if (\in_array($playerAction, [PlayerBettingActionEnum::BET, PlayerBettingActionEnum::CALL])) {
            if (empty($playerBet) || $playerBet < $this->minimalLegalBet) {
                throw new InvalidPlayerBettingActionException();
            }
        }
    }

    public function play(string $playerId, PlayerBettingActionEnum $playerAction, ?int $playerBet): void
    {
        $this->validatePlayerAction($playerAction, $playerBet);

        if (\in_array($playerAction, [PlayerBettingActionEnum::BET, PlayerBettingActionEnum::CALL])) {
            $this->pot += $playerBet;
            $this->minimalLegalBet = $playerBet;
            $this->previousNotableAction = $playerAction;
        }

        if ($playerAction === PlayerBettingActionEnum::FOLD) {
            $this->dispatcher->dispatch(new PhaseState("player_fold", ["player_id" => $playerId]));
            return;
        }

        // Advertising others what the player did
        $this->dispatcher->dispatch(new PhaseState("player_betting_action", ["player_id" => $playerId, "action" => $playerAction]));
    }
}