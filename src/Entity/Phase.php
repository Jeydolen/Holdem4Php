<?php

namespace App\Entity;

use App\Repository\PhaseRepository;
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
    private ?int $id = null;

    #[Groups("show_phase")]
    #[ORM\Column]
    private ?int $priority = null;

    #[Groups("show_phase")]
    #[ORM\Column]
    private array $additionnal_properties = [];

    #[Groups("show_phase")]
    #[ORM\Column(length: 255)]
    private ?string $type = null;

    #[ORM\ManyToOne(inversedBy: 'phases')]
    private ?TableRules $table_rules = null;

    #[Groups("show_phase")]
    #[ORM\Column(nullable: true)]
    private ?int $timeout = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getTableRules(): ?TableRules
    {
        return $this->table_rules;
    }

    public function setTableRules(?TableRules $table_rules): static
    {
        $this->table_rules = $table_rules;

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
}
