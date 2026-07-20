<?php

namespace App\Game\Table;

use App\DTO\DeckGenerationDTO;
use App\Entity\Table as EntityTable;

use Psr\Log\LoggerInterface;

use Doctrine\ORM\EntityManagerInterface;

use Symfony\Component\EventDispatcher\EventDispatcher;

class TableFactory
{
    public function __construct(private LoggerInterface $logger, private EntityManagerInterface $em)
    {
    }

    public function createTable(int $maxPlayers, array $phases, DeckGenerationDTO $deckGenerationDTO, EntityTable $table): Table
    {
        return new Table(
            $maxPlayers,
            $phases,
            $deckGenerationDTO,
            $this->logger,
            new EventDispatcher(),
            $table,
            $this->em
        );
    }
}