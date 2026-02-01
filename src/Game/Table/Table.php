<?php

namespace App\Game\Table;

use App\DTO\DeckGenerationDTO;

use App\Game\Player;
use App\Game\Hand\PokerHand;
use App\Game\Hand\Phase\IPhase;
use App\Game\CardPile\DeckFactory;

use App\Game\Table\TableFullException;

class Table
{
    private array $players = [];
    private PokerHand $current_hand;

    public readonly int $maxPlayers;
    public readonly array $phases;

    private DeckFactory $deckFactory;

    /**
     * @param int $maxPlayers
     * @param IPhase[] $phases
     */
    public function __construct(int $maxPlayers, array $phases, DeckGenerationDTO $deckGenerationDTO)
    {
        $this->maxPlayers = $maxPlayers;
        $this->phases = $phases;
        $this->deckFactory = new DeckFactory($deckGenerationDTO);
    }

    public function addPlayer(Player $player): void
    {
        $alreadyInside = array_find($this->players, fn(Player $value) => $value->isSame($player));
        if ($alreadyInside) {
            throw new PlayerAlreadyInGameException();
        }

        if (\sizeof($this->players) >= $this->maxPlayers) {
            throw new TableFullException();
        }

        $this->players[] = $player;
    }

    public function removePlayer(Player $player): void
    {
        $this->players = array_filter($this->players, fn(Player $value): bool => $value->isSame($player));
    }

    public function newHand()
    {
        // Save previous hand in db for the history
        // $this->current_hand;
        $this->current_hand = new PokerHand($this->players, $this->phases, $this->deckFactory->newDeck());
    }

    public function start()
    {
        $this->newHand();
        $this->current_hand->playPhase();
    }

    private function update()
    {

    }
}