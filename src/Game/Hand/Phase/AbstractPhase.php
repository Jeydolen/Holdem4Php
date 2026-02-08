<?php

namespace App\Game\Hand\Phase;

use App\Event\PlayerAction;
use Psr\Log\LoggerInterface;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class AbstractPhase implements IPhase, EventSubscriberInterface
{
    protected LoggerInterface $logger;

    public static function getSubscribedEvents(): array
    {
        return [PlayerAction::class => "onPlayerAction"];
    }

    abstract public function onPlayerAction(PlayerAction $event): void;
}