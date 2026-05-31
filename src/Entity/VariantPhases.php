<?php

namespace App\Entity;

use App\Repository\VariantPhasesRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: VariantPhasesRepository::class)]
class VariantPhases
{
    #[ORM\Id]
    #[ORM\JoinColumn(name: "variant_id", referencedColumnName: "variant_id")]
    #[ORM\ManyToOne(targetEntity: Variant::class, inversedBy: 'variantPhases')]
    private Variant $variant;

    #[Groups("show_phase", "show_extended_phase")]
    #[ORM\Id]
    #[ORM\JoinColumn(name: "phase_id", referencedColumnName: "phase_id")]
    #[ORM\ManyToOne(targetEntity: Phase::class, inversedBy: 'variantPhases')]
    private Phase $phase;

    public function getVariant(): Variant
    {
        return $this->variant;
    }

    public function setVariant(Variant $variant): self
    {
        $this->variant = $variant;
        return $this;
    }

    public function getPhase(): Phase
    {
        return $this->phase;
    }

    public function setPhase(Phase $phase): self
    {
        $this->phase = $phase;
        return $this;
    }
}
