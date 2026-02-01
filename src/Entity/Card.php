<?php

namespace App\Entity;

use App\Repository\CardRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;

use App\Game\Card\Card as GameCard;

#[ORM\Entity(repositoryClass: CardRepository::class)]
class Card
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 10)]
    private ?string $rank = null;

    #[ORM\Column(length: 10)]
    private ?string $symbol = null;

    #[ORM\Column]
    private ?int $weight = null;

    #[Ignore()]
    #[ORM\ManyToOne(inversedBy: 'cards')]
    private ?TableRules $tableRules = null;

    public static function fromGameCard(GameCard $gameCard): self
    {
        $card = new self();
        $card->setRank($gameCard->getRank());
        $card->setSymbol($gameCard->getSymbol());
        $card->setWeight($gameCard->getWeight());
        return $card;
    }

    public function toGameCard(): GameCard
    {
        $gameCard = new GameCard(
            rank: $this->getRank(),
            symbol: $this->getSymbol(),
            weight: $this->getWeight(),
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

    public function getWeight(): ?int
    {
        return $this->weight;
    }

    public function setWeight(int $weight): static
    {
        $this->weight = $weight;

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
