<?php

namespace App\Entity;

use App\Repository\TableRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

#[ORM\Entity(repositoryClass: TableRepository::class)]
#[ORM\Table(name: '`table`')]
class Table
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $table_id = null;

    #[Groups("show_table")]
    #[ORM\ManyToOne(inversedBy: 'tables')]
    #[ORM\JoinColumn(nullable: false, referencedColumnName: "variant_id")]
    private ?Variant $variant = null;

    /**
     * @var Collection<int, TablePlayers>
     */
    #[ORM\OneToMany(targetEntity: TablePlayers::class, mappedBy: 'table', cascade: ["remove", "persist"], orphanRemoval: true)]
    private Collection $tablePlayers;

    #[Groups("show_table")]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[Groups("show_table")]
    #[ORM\Column(length: 13)]
    private ?string $instance_table_id = null;

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

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;

        return $this;
    }

    #[Groups("show_table")]
    #[SerializedName("current_player_count")]
    public function getCurrentPlayerCount(): int
    {
        return $this->tablePlayers->count();
    }

    public function getInstanceTableId(): ?string
    {
        return $this->instance_table_id;
    }

    public function setInstanceTableId(string $instance_table_id): static
    {
        $this->instance_table_id = $instance_table_id;

        return $this;
    }
}
