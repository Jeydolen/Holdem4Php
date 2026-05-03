<?php

namespace App\Game\Hand\Phase;

use App\Event\PhaseState;
use App\Event\PlayerAction;

use App\Game\CardPile\Deck;
use App\Game\CardPile\ICardPile;

use Psr\Log\LoggerInterface;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;


abstract class AbstractPhase implements IPhase, EventSubscriberInterface
{
    protected EventDispatcher $dispatcher;
    protected LoggerInterface $logger;

    public static function getSubscribedEvents(): array
    {
        return [PlayerAction::class => "onPlayerAction"];
    }

    abstract public function play(array &$players, Deck &$deck, ?ICardPile $boardCardPile): void;

    abstract public function onPlayerAction(PlayerAction $event): void;

    public function withEventDispatcher(EventDispatcher $dispatcher): static
    {
        $this->dispatcher = $dispatcher;
        return $this;
    }

    protected function endPhase(): void
    {
        $this->logger->debug("Ending phase", context: ["calling_class" => get_called_class()]);
        $this->dispatcher->dispatch(new PhaseState("next_phase"));
    }
}