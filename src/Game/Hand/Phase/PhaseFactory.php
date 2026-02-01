<?php

namespace App\Game\Hand\Phase;

use Exception;

class PhaseFactory
{
    private static array $map = [
        "draw_cards" => DrawCardPhase::class,
        "betting_phase" => BettingPhase::class,
    ];

    public static function create(string $type, ?int $timeout, array $data): IPhase
    {
        if (empty(self::$map[$type])) {
            throw new Exception("Unknown phase");
        }

        $data["timeout"] = $timeout;

        $class = self::$map[$type];
        return $class::fromArray($data);
    }
}