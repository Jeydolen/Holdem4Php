<?php

namespace App\Event;

use Symfony\Contracts\EventDispatcher\Event;

class PhaseState extends Event
{
    public function __construct(private string $action, private mixed $data = null)
    {
    }

    public function getAction(): string
    {
        return $this->action;
    }

    public function getEventData(): mixed
    {
        return $this->data;
    }
}