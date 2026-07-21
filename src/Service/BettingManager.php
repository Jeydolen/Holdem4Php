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
     * Actions that change the current bet amount/price, 
     * requiring a new round of questioning.
     *
     * @var PlayerBettingActionEnum[]
     */
    public const array NOTABLE_ACTIONS = [
        PlayerBettingActionEnum::BET,
        PlayerBettingActionEnum::RAISE,
        PlayerBettingActionEnum::ALL_IN
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
        // By default, if there is no previous action, you can FOLD, CHECK or BET
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

            $isAllIn = $playerBet === $maxPlayerBet;
            if (!$isAllIn) {
                if ($playerAction === PlayerBettingActionEnum::CALL && $playerBet !== $this->minimalLegalBet) {
                    throw new InvalidPlayerBettingActionException();
                } else if ($playerAction === PlayerBettingActionEnum::BET && $playerBet < $this->minimalLegalBet) {
                    throw new InvalidPlayerBettingActionException();
                }
            }
        }
    }

    /**
     * @param string $playerId
     * @param PlayerBettingActionEnum $playerAction
     * @param mixed $playerBet
     * @return bool True: if the action changed the bet amount (requires a new round), False: otherwise
     */
    public function play(string $playerId, PlayerBettingActionEnum $playerAction, ?int $playerBet): bool
    {
        $this->validatePlayerAction($playerAction, $playerBet);

        $this->dispatcher->dispatch(new PhaseState("player_betting_action", [
            "player_id" => $playerId,
            "action" => $playerAction,
            "betting_amount" => $playerBet
        ]));

        if ($playerAction === PlayerBettingActionEnum::FOLD) {
            $this->dispatcher->dispatch(new PhaseState("player_fold", ["player_id" => $playerId]));
            return false;
        }

        // Call does increment the bot but it is not a betting action per se
        if ($playerAction === PlayerBettingActionEnum::CALL) {
            $this->registerBet($playerAction, $playerBet);
        }

        if (\in_array($playerAction, static::NOTABLE_ACTIONS)) {
            $this->registerBet($playerAction, $playerBet);
            // Returns true because the bet level has changed; we need to loop back to the first player
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

    /**
     * Summary of computePots
     * @param Player[] $players
     * @param string[] $foldedPlayerIds User ids of folded players
     * @return Pot[] 
     */
    public function computePots(array $players, array $foldedPlayerIds): array
    {
        $contributors = array_filter($players, fn(Player $p) => $p->getBetTotalAmount() > 0);

        // No players did bet, no Pot to create
        if (empty($contributors)) {
            return [];
        }

        $thresholds = array_unique(array_map(fn(Player $p) => $p->getBetTotalAmount(), $contributors));
        sort($thresholds);

        $pots = [];
        $previous = 0;
        foreach ($thresholds as $threshold) {
            $thresholdAmount = $threshold - $previous;

            $potContributors = array_filter(
                $contributors,
                fn(Player $p) => $p->getBetTotalAmount() >= $threshold
            );

            $potAmount = $thresholdAmount * \count($potContributors);

            // Only players who didn't fold can receive this pot
            $eligiblePlayers = array_values(array_filter($potContributors, fn(Player $p) => !\in_array($p->getUserId(), $foldedPlayerIds)));

            $pots[] = new Pot($potAmount, $eligiblePlayers);

            $previous = $threshold;
        }

        return $pots;
    }
}