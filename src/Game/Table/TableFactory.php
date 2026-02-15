<?php

namespace App\Game\Table;

use App\DTO\DeckGenerationDTO;

use Psr\Log\LoggerInterface;

use Symfony\Component\EventDispatcher\EventDispatcher;

class TableFactory
{
    public function __construct(private LoggerInterface $logger, )
    {
    }

    public function createTable(int $maxPlayers, array $phases, DeckGenerationDTO $deckGenerationDTO): Table
    {
        return new Table(
            $this->logger,
            new EventDispatcher(),
            $maxPlayers,
            $phases,
            $deckGenerationDTO
        );
    }
}