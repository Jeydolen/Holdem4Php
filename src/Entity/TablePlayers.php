<?php

namespace App\Entity;

use App\Repository\TablePlayersRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TablePlayersRepository::class)]
class TablePlayers
{
    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'tablePlayers')]
    #[ORM\JoinColumn(referencedColumnName: "user_id")]
    private ?User $user = null;

    #[ORM\Id]
    #[ORM\ManyToOne(inversedBy: 'tablePlayers')]
    #[ORM\JoinColumn(referencedColumnName: "table_id")]
    private ?Table $table = null;

    #[ORM\Column]
    private ?int $amount = null;

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getTable(): ?Table
    {
        return $this->table;
    }

    public function setTable(?Table $table): static
    {
        $this->table = $table;

        return $this;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;

        return $this;
    }
}
