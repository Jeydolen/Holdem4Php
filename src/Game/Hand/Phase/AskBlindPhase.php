<?php

namespace App\Game\Hand\Phase;

use App\Game\Hand\HandContext;
use App\Game\Bet\BettingManager;
use App\Game\Bet\PlayerBettingActionEnum;

use Psr\Log\LoggerInterface;

class AskBlindPhase extends AbstractPhase
{
    private BettingManager $bettingManager;

    private function __construct(
        protected LoggerInterface $logger,
        private ?int $timeout,
        private int $nbPlayersToAsk,
        private int $baseAmount,
        private int $amountModifier
    ) {
    }

    public function play(HandContext $context): void
    {
        $this->bettingManager = $context->getBettingManager();
        // We have to reset BettingManager state to be sure that player actions will be authorized
        $this->bettingManager->resetState();

        // We need to ask n players to pay a blind
        $players = $context->getPlayerCollection()->getCompetingPlayers();
        $this->logger->info("Playing phase", ["phase" => (self::class), "nb_players_to_ask" => $this->nbPlayersToAsk]);

        for ($i = 0; $i < $this->nbPlayersToAsk; $i++) {
            $player = $players[$i] ?? null;
            // No enough players
            if (empty($player)) {
                $this->logger->info("No more players can pay the blind");
                break;
            }

            // Raise except if it is the first one to bet
            $player_betting_action = PlayerBettingActionEnum::RAISE;
            $amount = $this->baseAmount * $this->amountModifier * ($i);
            if ($i === 0) {
                $player_betting_action = PlayerBettingActionEnum::BET;
                $amount = $this->baseAmount;
            }

            $this->logger->info("Player betting action", [
                "action" => $player_betting_action->value,
                "amount" => $amount,
                "player_id" => $player->getUserId()
            ]);

            $this->bettingManager->play(
                $player->getUserId(),
                $player_betting_action,
                $amount
            );

            $player->sendMessage(["action" => "ack_bet"]);
        }

        $this->endPhase();
    }

    public static function fromArray(array $data): self
    {
        $instance = new self(
            $data["logger"],
            $data["timeout"] ?? null,
            $data["nbPlayersToAsk"],
            $data["baseAmount"],
            $data["amountModifier"],
        );
        return $instance;
    }

    public function onPlayerAction(\App\Event\PlayerAction $event): void
    {
    }
}