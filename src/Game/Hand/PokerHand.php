<?php

namespace App\Game\Hand;

use App\Game\Player;
use App\Game\CardPile\Deck;
use App\Game\Hand\Phase\IPhase;

/**
 * A poker hand in this context is a complete round of poker
 * including the dealt cards for every player, the board, ...²
 */
class PokerHand
{
    /**
     * The players playing the hand
     * @var array<Player>
     */
    private array $players;

    /**
     * The players that will not play the next phase of the hand
     * @var array<Player>
     */
    private array $folded_players;

    /**
     * The phases to be played in the hand
     * @var array
     */
    private array $phases;

    private Deck $deck;

    private int $phase_index = 0;

    public function __construct(array $players, array $phases, Deck $deck)
    {
        $this->players = $players;
        $this->phases = $phases;
        $this->deck = $deck;
    }

    public function playPhase(callable $fn): void
    {
        // Index out of bound (phase is indexed by 0 so we need to subtract 1 to the array)
        if ($this->phase_index > (\sizeof($this->phases) - 1)) {
            $fn(["state" => "no_more_phases"]);
            return;
        }

        /**
         * @var IPhase
         */
        $phase = $this->phases[$this->phase_index];
        $phase->play($this->players, $this->deck);

        $this->phase_index += 1;
        $fn(["state" => "next_phase"]);
    }
}