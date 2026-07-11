<?php

namespace App\Entity;

use App\Repository\BankrollRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BankrollRepository::class)]
class Bankroll
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $bankroll_id = null;

    #[ORM\Column]
    private ?int $amount = null;

    #[ORM\OneToOne(inversedBy: 'bankroll', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false, referencedColumnName: "user_id")]
    private ?User $user = null;

    public function getBankrollId(): ?int
    {
        return $this->bankroll_id;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(int $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }
}
