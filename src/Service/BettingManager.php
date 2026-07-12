<?php

namespace App\Service;

use App\Event\PhaseState;

use App\Game\Pot;
use App\Game\Player\Player;

use App\Enum\PlayerBettingActionEnum;
use App\Exception\InvalidPlayerBettingActionException;

use Symfony\Component\EventDispatcher\EventDispatcher;

class BettingManager
{
    /**
     * @var PlayerBettingActionEnum[]
     */
    public const array NOTABLE_ACTIONS = [
        PlayerBettingActionEnum::BET,
        PlayerBettingActionEnum::CALL,
        PlayerBettingActionEnum::RAISE,
        PlayerBettingActionEnum::ALL_IN
    ];

    private ?PlayerBettingActionEnum $previousNotableAction = null;

    private int $minimalLegalBet = 0;

    public function __construct(private EventDispatcher $dispatcher, private ?int $pot = 0)
    {
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
     * Validate player action to not accept illegal actions or empty bet
     * @throws InvalidPlayerBettingActionException
     */
    public function validatePlayerAction(PlayerBettingActionEnum $playerAction, ?int $playerBet = null): void
    {
        if (!\in_array($playerAction, $this->computeLegalActions())) {
            throw new InvalidPlayerBettingActionException();
        }

        // TODO: Implement player all in
        $maxPlayerBet = null;

        if (\in_array($playerAction, [PlayerBettingActionEnum::BET, PlayerBettingActionEnum::CALL])) {
            if (empty($playerBet)) {
                throw new InvalidPlayerBettingActionException();
            }

            // We need to check only if its not an all in
            $isAllIn = $playerBet === $maxPlayerBet;
            if (!$isAllIn) {
                // In case of call, it needs to be the same amount or all in
                if ($playerAction === PlayerBettingActionEnum::CALL && $playerBet !== $this->minimalLegalBet) {
                    throw new InvalidPlayerBettingActionException();
                } else if ($playerAction === PlayerBettingActionEnum::BET && $playerBet < $this->minimalLegalBet) {
                    // When betting, it needs to be at least the last betting amount or all in
                    throw new InvalidPlayerBettingActionException();
                }
            }
        }
    }

    /**
     * Using player action to determine what we should be doint after
     * @param string $playerId
     * @param PlayerBettingActionEnum $playerAction
     * @param mixed $playerBet
     * @return bool False: when there is nothing more to do (eg: player is folding) True: when we need to ask players again (eg: betting)
     */
    public function play(string $playerId, PlayerBettingActionEnum $playerAction, ?int $playerBet): bool
    {
        $this->validatePlayerAction($playerAction, $playerBet);

        // Advertising others what the player did
        $this->dispatcher->dispatch(new PhaseState("player_betting_action", ["player_id" => $playerId, "action" => $playerAction]));

        if ($playerAction === PlayerBettingActionEnum::FOLD) {
            $this->dispatcher->dispatch(new PhaseState("player_fold", ["player_id" => $playerId]));
            return false;
        }

        if (\in_array($playerAction, static::NOTABLE_ACTIONS)) {
            $this->registerBet($playerAction, $playerBet);
            // All active players need to be asked if they want to call the new bet
            return true;
        }

        return false;
    }

    private function registerBet(PlayerBettingActionEnum $playerAction, int $playerBet): void
    {
        $this->pot += $playerBet;
        $this->minimalLegalBet = $playerBet;
        $this->previousNotableAction = $playerAction;
    }
}