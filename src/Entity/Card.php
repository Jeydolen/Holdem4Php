<?php

namespace App\Entity;

use App\Repository\CardRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Serializer\Attribute\Ignore;

use App\Game\Card\Card as GameCard;

#[Groups("show_extended_card")]
#[ORM\Entity(repositoryClass: CardRepository::class)]
class Card
{
    #[Groups("show_card")]
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[Groups("show_card")]
    #[ORM\Column(length: 10)]
    private ?string $rank = null;

    #[Groups("show_card")]
    #[ORM\Column(length: 10)]
    private ?string $symbol = null;

    #[Ignore()]
    #[ORM\ManyToOne(inversedBy: 'cards')]
    private ?TableRules $tableRules = null;

    public static function fromGameCard(GameCard $gameCard): self
    {
        $card = new self();
        $card->setRank($gameCard->getRank());
        $card->setSymbol($gameCard->getSymbol());
        return $card;
    }

    public function toGameCard(): GameCard
    {
        $gameCard = new GameCard(
            rank: $this->getRank(),
            symbol: $this->getSymbol(),
        );

        return $gameCard;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRank(): ?string
    {
        return $this->rank;
    }

    public function setRank(string $rank): static
    {
        $this->rank = $rank;

        return $this;
    }

    public function getSymbol(): ?string
    {
        return $this->symbol;
    }

    public function setSymbol(string $symbol): static
    {
        $this->symbol = $symbol;

        return $this;
    }

    public function getTableRules(): ?TableRules
    {
        return $this->tableRules;
    }

    public function setTableRules(?TableRules $tableRules): static
    {
        $this->tableRules = $tableRules;

        return $this;
    }

}
