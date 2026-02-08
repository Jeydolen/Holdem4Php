<?php

namespace App\Game;

use App\Game\Card\Card;
use App\Game\CardPile\ICardPile;
use App\Game\CardPile\PlayerHoleCards;
use App\Game\WebSocket\ConnectionWrapper;
use Psr\Log\LoggerInterface;

class Player
{
    /**
     * A user is the real account behind a player, we need to make this distinction
     * because a player is connected to a single table but a user might not
     * @var mixed
     */
    private mixed $user;

    private ICardPile $hole_cards;

    private ConnectionWrapper $connection;

    public function __construct(string $user, ConnectionWrapper $connection, LoggerInterface $logger)
    {
        // For now user, is just a string for simplicity
        $this->user = $user;
        $this->connection = $connection;
        $this->hole_cards = new PlayerHoleCards(2, true);
    }

    public function getUserId(): string
    {
        // TODO: Adapt when user is a real object
        return $this->user;
    }


    public function getHoleCards(): ICardPile
    {
        return $this->hole_cards;
    }

    public function sendMessage(mixed $data): bool|null
    {
        return $this->connection->sendJson($data);
    }

    public function pushCard(Card $card): void
    {
        $this->hole_cards->pushCard($card);
        $this->sendMessage(["card" => $card]);
    }

    public function bet(): void
    {
        $this->sendMessage(["action" => "bet"]);
    }

    /**
     * Method called by the client to know is current state (cards, bankroll, ...)
     * @return void
     */
    public function sendCurrentState(): void
    {
        $this->sendMessage(["cards" => $this->getHoleCards()]);
    }
}