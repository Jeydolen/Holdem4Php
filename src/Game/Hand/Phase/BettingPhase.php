<?php

namespace App\Game\Hand\Phase;

use App\Game\Player;
use App\Game\CardPile\Deck;

use App\Event\PlayerAction;

use Psr\Log\LoggerInterface;

use Workerman\Timer;

use Symfony\Component\EventDispatcher\EventDispatcher;

class BettingPhase extends AbstractPhase
{
    private Player $currentPlayer;
    private int $currentMinAmount = 0;

    private function __construct(
        private EventDispatcher $dispatcher,
        private LoggerInterface $logger,
        private ?int $timeout,
        private int $maxBettingAmount
    ) {
    }

    public function play(array $players, Deck $deck): void
    {
        // We ask every player to bet, they can either call, check, raise or fold
        foreach ($players as $player) {
            $this->currentPlayer = $player;
            if (!empty($this->timeout)) {
                Timer::add($this->timeout, function () use ($player) {
                    $this->dispatcher->dispatch(new PlayerAction($player, ["player_action" => "fold"]));
                });
            }
            $player->askBet($this->maxBettingAmount, $this->currentMinAmount);
        }
    }

    public static function fromArray(array $data): self
    {
        return new self($data["dispatcher"], $data["logger"], $data["timeout"] ?? null, $data["maxBettingAmount"]);
    }

    public function onPlayerAction(PlayerAction $event): void
    {
        if ($event->getPlayer() !== $this->currentPlayer) {
            return;
        }


    }
}