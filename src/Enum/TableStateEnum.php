<?php

namespace App\Enum;

enum TableStateEnum
{
    case WAITING_FOR_PLAYERS;

    case STARTING;

    case IN_PROGRESS;

    case FINISHED;
}