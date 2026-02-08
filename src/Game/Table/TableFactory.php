<?php

namespace App\Game\Table;

use App\DTO\DeckGenerationDTO;

use Psr\Log\LoggerInterface;

use Symfony\Component\EventDispatcher\EventDispatcher;

class TableFactory
{
    public function __construct(
        private LoggerInterface $logger,
        private EventDispatcher $dispatcher,
    ) {
    }

    public function createTable(int $maxPlayers, array $phases, DeckGenerationDTO $deckGenerationDTO): Table
    {
        return new Table(
            $this->logger,
            $this->dispatcher,
            $maxPlayers,
            $phases,
            $deckGenerationDTO
        );
    }
}