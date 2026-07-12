<?php

namespace App\Event;

use App\Game\Player\Player;
use Symfony\Contracts\EventDispatcher\Event;

class PlayerAction extends Event
{
    public function __construct(private Player $player, private mixed $data)
    {
    }

    public function getPlayer(): Player
    {
        return $this->player;
    }

    public function getEventData(): mixed
    {
        return $this->data;
    }
}