<?php

namespace App\Game;

use App\Game\CardPile\ICardPile;

class Player
{
    /**
     * A user is the real account behind a player, we need to make this distinction
     * because a player is connected to a single table but a user might not
     * @var mixed
     */
    private mixed $user;

    private ICardPile $hole_cards;
}