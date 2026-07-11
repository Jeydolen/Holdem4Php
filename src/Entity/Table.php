<?php

namespace App\Entity;

use App\Repository\TableRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TableRepository::class)]
#[ORM\Table(name: '`table`')]
class Table
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $table_id = null;

    #[ORM\ManyToOne(inversedBy: 'tables')]
    #[ORM\JoinColumn(nullable: false, referencedColumnName: "variant_id")]
    private ?Variant $variant = null;

    /**
     * @var Collection<int, TablePlayers>
     */
    #[ORM\OneToMany(targetEntity: TablePlayers::class, mappedBy: 'table')]
    private Collection $tablePlayers;

    public function __construct()
    {
        $this->tablePlayers = new ArrayCollection();
    }

    public function getTableId(): ?int
    {
        return $this->table_id;
    }

    public function setTableId(int $table_id): static
    {
        $this->table_id = $table_id;

        return $this;
    }

    public function getVariant(): ?Variant
    {
        return $this->variant;
    }

    public function setVariant(?Variant $variant): static
    {
        $this->variant = $variant;

        return $this;
    }

    /**
     * @return Collection<int, TablePlayers>
     */
    public function getTablePlayers(): Collection
    {
        return $this->tablePlayers;
    }

    public function addTablePlayer(TablePlayers $tablePlayer): static
    {
        if (!$this->tablePlayers->contains($tablePlayer)) {
            $this->tablePlayers->add($tablePlayer);
            $tablePlayer->setTable($this);
        }

        return $this;
    }

    public function removeTablePlayer(TablePlayers $tablePlayer): static
    {
        if ($this->tablePlayers->removeElement($tablePlayer)) {
            // set the owning side to null (unless already changed)
            if ($tablePlayer->getTable() === $this) {
                $tablePlayer->setTable(null);
            }
        }

        return $this;
    }
}
