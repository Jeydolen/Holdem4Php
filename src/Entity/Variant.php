<?php

namespace App\Entity;

use App\Repository\VariantRepository;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\SerializedName;

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

    #[Groups("show_variant")]
    #[ORM\Column(length: 255)]
    private ?string $betting_type = null;

    #[ORM\Column(nullable: true, options: ["default" => 30])]
    private ?int $starting_timer = null;

    #[ORM\Column(nullable: true)]
    private ?int $min_player_threshold = null;

    /**
     * @var Collection<int, Stake>
     */
    #[ORM\OneToMany(targetEntity: Stake::class, mappedBy: 'variant', orphanRemoval: true)]
    private Collection $stakes;

    public function __construct()
    {
        $this->variantCards = new ArrayCollection();
        $this->variantPhases = new ArrayCollection();
        $this->tables = new ArrayCollection();
        $this->stakes = new ArrayCollection();
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

    /**
     * @return Phase[]
     */
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

    public function getBettingType(): ?string
    {
        return $this->betting_type;
    }

    public function setBettingType(string $betting_type): static
    {
        $this->betting_type = $betting_type;

        return $this;
    }

    public function getStartingTimer(): ?int
    {
        return $this->starting_timer;
    }

    public function setStartingTimer(?int $starting_timer): static
    {
        $this->starting_timer = $starting_timer;

        return $this;
    }

    public function getMinPlayerThreshold(): ?int
    {
        return $this->min_player_threshold;
    }

    public function setMinPlayerThreshold(?int $min_player_threshold): static
    {
        $this->min_player_threshold = $min_player_threshold;

        return $this;
    }

    /**
     * @return Collection<int, Stake>
     */
    #[Groups("show_stake")]
    #[SerializedName("stakes")]
    public function getStakes(): Collection
    {
        return $this->stakes;
    }

    public function addVariantStake(Stake $stake): static
    {
        if (!$this->stakes->contains($stake)) {
            $this->stakes->add($stake);
            $stake->setVariant($this);
        }

        return $this;
    }

    public function removeVariantStake(Stake $stake): static
    {
        if ($this->stakes->removeElement($stake)) {
            // set the owning side to null (unless already changed)
            if ($stake->getVariant() === $this) {
                $stake->setVariant(null);
            }
        }

        return $this;
    }
}
