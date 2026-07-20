<?php

namespace App\Game\Table;

use Psr\Log\LoggerInterface;


class TableRegistry
{
    /** @var Table[] */
    private array $tables = [];

    public function __construct(private LoggerInterface $logger)
    {
    }

    public function addTable(string $key, Table $table): void
    {
        $this->tables[$key] = $table;
    }

    public function removeTable(string $key): void
    {
        unset($this->tables[$key]);
    }

    public function getTable(string $key): ?Table
    {
        return $this->tables[$key] ?? null;
    }

    /**
     * @return Table[]
     */
    public function getAllTables(): array
    {
        return $this->tables;
    }
}