<?php

namespace App\Game\Hand\Phase;

use Exception;

use App\Entity\Stake;
use App\Entity\Variant;

use Psr\Log\LoggerInterface;

class PhaseFactory
{
    public function __construct(private LoggerInterface $logger)
    {
    }

    private static array $map = [
        "draw_cards" => DrawCardPhase::class,
        "betting_phase" => BettingPhase::class,
        "shuffle_deck_phase" => ShuffleDeckPhase::class,
        "draw_board_card_phase" => DrawBoardCardPhase::class,
        "showdown_phase" => ShowdownPhase::class,
        "ask_blind_phase" => AskBlindPhase::class,
    ];

    public function create(string $type, ?int $timeout, array $data, Variant $variant, Stake $stake): IPhase
    {
        if (empty(self::$map[$type])) {
            throw new Exception("Unknown phase: $type");
        }

        // There might be a better way to do this...
        $data["logger"] = $this->logger;
        $data["timeout"] = $timeout;
        $data["minBuyIn"] = $stake->getMinBuyIn();
        $data["maxBuyIn"] = $stake->getMaxBuyIn();

        $class = self::$map[$type];
        return $class::fromArray($data);
    }
}