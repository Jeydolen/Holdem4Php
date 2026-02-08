<?php

namespace App\Game\Hand\Phase;

use Exception;
use Psr\Log\LoggerInterface;

class PhaseFactory
{
    private static array $map = [
        "draw_cards" => DrawCardPhase::class,
        "betting_phase" => BettingPhase::class,
    ];

    public static function create(LoggerInterface $logger, string $type, ?int $timeout, array $data): IPhase
    {
        if (empty(self::$map[$type])) {
            throw new Exception("Unknown phase");
        }

        // There might be a better way to do this...
        $data["logger"] = $logger;
        $data["timeout"] = $timeout;

        $class = self::$map[$type];
        return $class::fromArray($data);
    }
}