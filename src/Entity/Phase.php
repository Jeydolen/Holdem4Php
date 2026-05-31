<?php

namespace App\Entity;

use App\Repository\PhaseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[Groups("show_extended_phase")]
#[ORM\Entity(repositoryClass: PhaseRepository::class)]
class Phase
{
    #[Groups("show_phase")]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $phase_id = null;

    #[Groups("show_phase")]
    #[ORM\Column]
    private ?int $priority = null;

    #[Groups("show_phase")]
    #[ORM\Column]
    private array $additionnal_properties = [];

    #[Groups("show_phase")]
    #[ORM\Column(length: 255)]
    private ?string $type = null;

    #[Groups("show_phase")]
    #[ORM\Column(nullable: true)]
    private ?int $timeout = null;

    /**
     * @var Collection<int, VariantPhases>
     */
    #[ORM\OneToMany(targetEntity: VariantPhases::class, mappedBy: 'phase', orphanRemoval: true)]
    private Collection $variantPhases;

    public function __construct()
    {
        $this->variantPhases = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->phase_id;
    }

    public function getPriority(): ?int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    public function getAdditionnalProperties(): array
    {
        return $this->additionnal_properties;
    }

    public function setAdditionnalProperties(array $additionnal_properties): static
    {
        $this->additionnal_properties = $additionnal_properties;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getTimeout(): ?int
    {
        return $this->timeout;
    }

    public function setTimeout(?int $timeout): static
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * @return Collection<int, VariantPhases>
     */
    public function getVariantPhases(): Collection
    {
        return $this->variantPhases;
    }

    public function addVariantPhase(VariantPhases $variantPhase): static
    {
        if (!$this->variantPhases->contains($variantPhase)) {
            $this->variantPhases->add($variantPhase);
            $variantPhase->setPhase($this);
        }

        return $this;
    }

    public function removeVariantPhase(VariantPhases $variantPhase): static
    {
        if ($this->variantPhases->removeElement($variantPhase)) {
            // set the owning side to null (unless already changed)
            if ($variantPhase->getPhase() === $this) {
                $variantPhase->setPhase(null);
            }
        }

        return $this;
    }
}
