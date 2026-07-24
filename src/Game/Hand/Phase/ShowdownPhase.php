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
        $playerCollection = $context->getPlayerCollection();
        $players = $playerCollection->getCompetingPlayers();
        $boardCardPile = $context->getBoardCards();
        $cardRankEvaluator = $context->getCardRankEvaluator();
        $bettingManager = $context->getBettingManager();

        $playerHandStrength = [];
        foreach ($players as $player) {
            $playerCards = $player->getHoleCards()->getCards();
            $evaluation = $cardRankEvaluator->evaluate(new BoardCards(7, true, [...$boardCardPile->getCards(), ...$playerCards]));
            $playerHandStrength[$player->getUserId()] = $evaluation;
        }

        $pots = $bettingManager->computePots($playerCollection->getAllPlayers(), $playerCollection->getFoldedPlayerIds());
        foreach ($pots as $pot) {
            $amount = $pot->getAmount();
            $eligiblePlayers = array_map(fn($p) => $p->getUserId(), $pot->getCompetingPlayers());
            if ($amount === 0 || empty($eligiblePlayers)) {
                continue;
            }

            $this->logger->info("Eligible players for this pot", ["players" => $eligiblePlayers, "amount" => $amount]);

            if (\count($eligiblePlayers) === 1) {
                $winnerId = $eligiblePlayers[0];
                $this->awardPot($winnerId, $amount, $playerHandStrength[$winnerId]);
                continue;
            }

            // Filter hands to only players eligible to this exact pot
            $eligibleStrengths = array_intersect_key(
                $playerHandStrength,
                array_flip($eligiblePlayers)
            );

            $highest = max($eligibleStrengths);
            $winningPlayers = array_filter($eligibleStrengths, fn($value) => $value === $highest);

            $this->awardSplitPot($winningPlayers, $amount);
        }

        $this->endPhase();
    }

    private function awardPot(string $playerId, int $amount, $handValue): void
    {
        $this->dispatcher->dispatch(new PhaseState("player_won", [
            "player_id" => $playerId,
            "hand_value" => $handValue,
            "amount" => $amount,
        ]));
    }

    /** @param array<string, mixed> $winningPlayers playerId => hand_value */
    private function awardSplitPot(array $winningPlayers, int $amount): void
    {
        $share = intdiv($amount, count($winningPlayers));
        // Le reliquat n'est distribué à personne : il reste "perdu" (récupéré par le casino/la table).

        foreach ($winningPlayers as $playerId => $handValue) {
            $this->dispatcher->dispatch(new PhaseState("player_won", [
                "player_id" => $playerId,
                "hand_value" => $handValue,
                "amount" => $share,
            ]));
        }
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