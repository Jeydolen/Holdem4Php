<?php
namespace App\Game\Player;

use RuntimeException;

class Bankroll
{
    private int $amount;
    public function __construct(int $amount)
    {
        $this->setAmount($amount);
    }

    public function setAmount(int $amount)
    {
        if ($amount < 0) {
            throw new RuntimeException("Bankroll can't be under 0");
        }

        $this->amount = $amount;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }
}