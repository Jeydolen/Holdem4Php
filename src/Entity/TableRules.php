<?php

namespace App\Entity;

use App\Repository\TableRulesRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[Groups("show_extended_rule")]
#[ORM\Entity(repositoryClass: TableRulesRepository::class)]
class TableRules
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $max_players = null;

    #[ORM\Column(length: 255)]
    private ?string $table_type = null;

    /**
     * @var Collection<int, Phase>
     */
    #[ORM\OneToMany(targetEntity: Phase::class, mappedBy: 'table_rules', cascade: ["remove"])]
    private Collection $phases;

    /**
     * @var Collection<int, Card>
     */
    #[ORM\OneToMany(targetEntity: Card::class, mappedBy: 'tableRules', cascade: ["remove"])]
    private Collection $cards;

    public function __construct()
    {
        $this->phases = new ArrayCollection();
        $this->cards = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMaxPlayers(): ?int
    {
        return $this->max_players;
    }

    public function setMaxPlayers(int $max_players): static
    {
        $this->max_players = $max_players;

        return $this;
    }

    public function getTableType(): ?string
    {
        return $this->table_type;
    }

    public function setTableType(string $table_type): static
    {
        $this->table_type = $table_type;

        return $this;
    }

    /**
     * @return Collection<int, Phase>
     */
    public function getPhases(): Collection
    {
        return $this->phases;
    }

    public function addPhase(Phase $phase): static
    {
        if (!$this->phases->contains($phase)) {
            $this->phases->add($phase);
            $phase->setTableRules($this);
        }

        return $this;
    }

    public function removePhase(Phase $phase): static
    {
        if ($this->phases->removeElement($phase)) {
            // set the owning side to null (unless already changed)
            if ($phase->getTableRules() === $this) {
                $phase->setTableRules(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Card>
     */
    public function getCards(): Collection
    {
        return $this->cards;
    }

    public function addCard(Card $card): static
    {
        if (!$this->cards->contains($card)) {
            $this->cards->add($card);
            $card->setTableRules($this);
        }

        return $this;
    }

    public function removeCard(Card $card): static
    {
        if ($this->cards->removeElement($card)) {
            // set the owning side to null (unless already changed)
            if ($card->getTableRules() === $this) {
                $card->setTableRules(null);
            }
        }

        return $this;
    }
}
