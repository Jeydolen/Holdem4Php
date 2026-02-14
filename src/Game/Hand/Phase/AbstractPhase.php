<?php

namespace App\Game\Hand\Phase;

use App\Game\CardPile\Deck;
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

    abstract public function play(array &$players, Deck &$deck): void;

    abstract public function onPlayerAction(PlayerAction $event): void;
}