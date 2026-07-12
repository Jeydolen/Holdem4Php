<?php
namespace App\Game\Hand;

use App\Game\CardPile\BoardCards;
use App\Game\CardPile\Deck;


use App\Game\PlayerCollection;
use App\Service\BettingManager;
use App\Service\CardRank\CardRankEvaluator;

/**
 * Conteneur exposant tous les composants potentiellement utiles aux phases.
 * PokerHand le construit une fois et le passe systématiquement, sans condition.
 */
final class HandContext
{
    /**
     * @param Deck $deck
     * @param PlayerCollection $players
     * @param BettingManager $bettingManager
     * @param CardRankEvaluator $cardRankEvaluator
     */
    public function __construct(
        private readonly Deck $deck,
        private readonly BoardCards $boardCardPile,
        private readonly PlayerCollection $playerCollection,
        private readonly BettingManager $bettingManager,
        private readonly CardRankEvaluator $cardRankEvaluator,
    ) {
    }

    public function getDeck(): Deck
    {
        return $this->deck;
    }

    public function getBoardCards(): BoardCards
    {
        return $this->boardCardPile;
    }

    public function getPlayerCollection(): PlayerCollection
    {
        return $this->playerCollection;
    }

    public function getBettingManager(): BettingManager
    {
        return $this->bettingManager;
    }

    public function getCardRankEvaluator(): CardRankEvaluator
    {
        return $this->cardRankEvaluator;
    }
}