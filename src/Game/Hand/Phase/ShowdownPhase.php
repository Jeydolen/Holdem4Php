<?php
namespace App\Game\Hand\Phase;

use App\Event\PhaseState;
use App\Event\PlayerAction;

use App\Game\Hand\HandContext;
use App\Game\CardPile\BoardCards;

use Psr\Log\LoggerInterface;

class ShowdownPhase extends AbstractPhase
{
    private function __construct(protected LoggerInterface $logger)
    {
    }

    public function play(HandContext $context): void
    {
        $players = $context->getPlayerCollection()->getCompetingPlayers();
        $boardCardPile = $context->getBoardCards();
        $cardRankEvaluator = $context->getCardRankEvaluator();

        $playerHandStrength = [];
        $highest = 0;
        foreach ($players as $player) {
            $playerCards = $player->getHoleCards()->getCards();
            $evaluation = $cardRankEvaluator->evaluate(new BoardCards(7, true, [...$boardCardPile->getCards(), ...$playerCards]));
            $playerHandStrength[$player->getUserId()] = $evaluation;

            if ($evaluation > $highest) {
                $highest = $evaluation;
            }
        }

        // Find players from highest hand
        $winningPlayers = array_filter($playerHandStrength, fn($value) => $value === $highest);

        foreach ($winningPlayers as $playerId => $value) {
            $this->dispatcher->dispatch(new PhaseState("player_won", ["player_id" => $playerId, "hand_value" => $value]));
        }

        $this->endPhase();
    }

    public static function fromArray(array $data): self
    {
        $instance = new self($data["logger"]);
        return $instance;
    }

    public function onPlayerAction(PlayerAction $event): void
    {
        // We dont use any player action on this phase
    }
}