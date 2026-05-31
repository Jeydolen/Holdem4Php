<?php

namespace App\Entity;

use App\Repository\VariantCardsRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: VariantCardsRepository::class)]
class VariantCards
{

    #[ORM\Id]
    #[ORM\JoinColumn(name: "variant_id", referencedColumnName: "variant_id")]
    #[ORM\ManyToOne(targetEntity: Variant::class, inversedBy: 'variantCards')]
    private Variant $variant;

    #[Groups("show_card", "show_extended_card")]
    #[ORM\Id]
    #[ORM\JoinColumn(name: "card_id", referencedColumnName: "card_id")]
    #[ORM\ManyToOne(targetEntity: Card::class, inversedBy: 'variantCards')]
    private Card $card;

    public function getVariant(): Variant
    {
        return $this->variant;
    }

    public function setVariant(Variant $variant): self
    {
        $this->variant = $variant;
        return $this;
    }

    public function getCard(): Card
    {
        return $this->card;
    }

    public function setCard(Card $card): self
    {
        $this->card = $card;
        return $this;
    }
}
