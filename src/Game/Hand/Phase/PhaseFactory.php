<?php

namespace App\Game\Hand\Phase;

use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;

class PhaseFactory
{
    public function __construct(private LoggerInterface $logger, private EventDispatcher $dispatcher)
    {
    }

    private static array $map = [
        "draw_cards" => DrawCardPhase::class,
        "betting_phase" => BettingPhase::class,
    ];

    public function create(string $type, ?int $timeout, array $data): IPhase
    {
        if (empty(self::$map[$type])) {
            throw new Exception("Unknown phase");
        }

        // There might be a better way to do this...
        $data["logger"] = $this->logger;
        $data["dispatch"] = $this->dispatcher;
        $data["timeout"] = $timeout;

        $class = self::$map[$type];
        return $class::fromArray($data);
    }
}