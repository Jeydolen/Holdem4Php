<?php

namespace App\Entity;

use App\Repository\VariantRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[Groups("show_extended_variant")]
#[ORM\Entity(repositoryClass: VariantRepository::class)]
class Variant
{
    #[Groups("show_variant")]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $variant_id = null;

    #[Groups("show_variant")]
    #[ORM\Column]
    private ?int $max_players = null;

    #[Groups("show_variant")]
    #[ORM\Column(length: 255)]
    private ?string $table_type = null;

    #[Groups("show_variant")]
    #[ORM\Column(length: 100)]
    private ?string $name = null;

    /**
     * @var Collection<int, VariantCards>
     */
    #[ORM\OneToMany(targetEntity: VariantCards::class, mappedBy: 'variant', orphanRemoval: true)]
    private Collection $variantCards;

    /**
     * @var Collection<int, VariantPhases>
     */
    #[ORM\OneToMany(targetEntity: VariantPhases::class, mappedBy: 'variant', orphanRemoval: true)]
    private Collection $variantPhases;

    /**
     * @var Collection<int, Table>
     */
    #[ORM\OneToMany(targetEntity: Table::class, mappedBy: 'variant')]
    private Collection $tables;

    public function __construct()
    {
        $this->variantCards = new ArrayCollection();
        $this->variantPhases = new ArrayCollection();
        $this->tables = new ArrayCollection();
    }

    public function getVariantId(): ?int
    {
        return $this->variant_id;
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

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return Collection<int, VariantCards>
     */
    public function getVariantCards(): Collection
    {
        return $this->variantCards;
    }

    #[Groups("show_card")]
    public function getCards(): array
    {
        return $this->variantCards->map(fn(VariantCards $variantCard) => $variantCard->getCard())->toArray();
    }

    public function addVariantCard(VariantCards $variantCard): static
    {
        if (!$this->variantCards->contains($variantCard)) {
            $this->variantCards->add($variantCard);
            $variantCard->setVariant($this);
        }

        return $this;
    }

    public function removeVariantCard(VariantCards $variantCard): static
    {
        if ($this->variantCards->removeElement($variantCard)) {
            // set the owning side to null (unless already changed)
            if ($variantCard->getVariant() === $this) {
                $variantCard->setVariant(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, VariantPhases>
     */
    public function getVariantPhases(): Collection
    {
        return $this->variantPhases;
    }

    #[Groups("show_phase")]
    public function getPhases(): array
    {
        return $this->variantPhases->map(fn(VariantPhases $variantPhases) => $variantPhases->getPhase())->toArray();
    }

    public function addVariantPhase(VariantPhases $variantPhase): static
    {
        if (!$this->variantPhases->contains($variantPhase)) {
            $this->variantPhases->add($variantPhase);
            $variantPhase->setVariant($this);
        }

        return $this;
    }

    public function removeVariantPhase(VariantPhases $variantPhase): static
    {
        if ($this->variantPhases->removeElement($variantPhase)) {
            // set the owning side to null (unless already changed)
            if ($variantPhase->getVariant() === $this) {
                $variantPhase->setVariant(null);
            }
        }

        return $this;
    }


    /**
     * @return Collection<int, Table>
     */
    public function getTables(): Collection
    {
        return $this->tables;
    }

    public function addTable(Table $table): static
    {
        if (!$this->tables->contains($table)) {
            $this->tables->add($table);
            $table->setVariant($this);
        }

        return $this;
    }

    public function removeTable(Table $table): static
    {
        if ($this->tables->removeElement($table)) {
            // set the owning side to null (unless already changed)
            if ($table->getVariant() === $this) {
                $table->setVariant(null);
            }
        }

        return $this;
    }
}
