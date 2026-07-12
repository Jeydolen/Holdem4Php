<?php
namespace App\Game\Hand\Phase;

use App\Game\Hand\HandContext;


use Symfony\Component\EventDispatcher\EventDispatcher;

interface IPhase
{
    public function play(HandContext $context): void;

    public static function fromArray(array $data): self;

    public function withEventDispatcher(EventDispatcher $dispatcher): static;
}
