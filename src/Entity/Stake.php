<?php

namespace App\Entity;

use App\Repository\StakeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: StakeRepository::class)]
class Stake
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'Stake')]
    #[ORM\JoinColumn(nullable: false, name: "variant_id", referencedColumnName: "variant_id")]
    private ?Variant $variant = null;

    #[Groups(["show_stake", "show_table_stake"])]
    #[ORM\Column]
    private ?int $min_buy_in = null;

    #[Groups(["show_stake", "show_table_stake"])]
    #[ORM\Column]
    private ?int $max_buy_in = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getMinBuyIn(): ?int
    {
        return $this->min_buy_in;
    }

    public function setMinBuyIn(int $min_buy_in): static
    {
        $this->min_buy_in = $min_buy_in;

        return $this;
    }

    public function getMaxBuyIn(): ?int
    {
        return $this->max_buy_in;
    }

    public function setMaxBuyIn(int $max_buy_in): static
    {
        $this->max_buy_in = $max_buy_in;

        return $this;
    }
}
