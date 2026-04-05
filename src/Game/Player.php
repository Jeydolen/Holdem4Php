<?php

namespace App\Game;

use App\Event\PlayerAction;

use App\Game\Card\Card;
use App\Game\CardPile\ICardPile;
use App\Game\CardPile\PlayerHoleCards;
use App\Game\WebSocket\ConnectionWrapper;

use DateTime;
use DateTimeInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class Player implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [PlayerAction::class => 'onPlayerAction'];
    }

    /**
     * A user is the real account behind a player, we need to make this distinction
     * because a player is connected to a single table but a user might not
     * @var mixed
     */
    private mixed $user;

    private ICardPile $hole_cards;

    private bool $folded;

    public function __construct(
        string $user,
        private ConnectionWrapper $connection,
        private LoggerInterface $logger
    ) {
        // For now user, is just a string for simplicity
        $this->user = $user;
        $this->resetState();
    }

    /**
     * Reset player state to play another PokerHand
     * @return void
     */
    public function resetState()
    {
        $this->hole_cards = new PlayerHoleCards(2, true);
        $this->folded = false;
    }

    public function getUserId(): string
    {
        // TODO: Adapt when user is a real object
        return $this->user;
    }

    public function isSame(Player $player): bool
    {
        return $this->user == $player->user && $this->connection == $player->connection;
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
    }

    public function askBet(int $maxBettingAmount, array $legalActions, ?int $minBettingAmount = 0, ?DateTimeInterface $timeoutDate = null): void
    {
        $this->sendMessage([
            "action" => "ask_bet",
            "legal_actions" => $legalActions,
            "max_amount" => $maxBettingAmount,
            "min_amount" => $minBettingAmount,
            "timeout_date" => $timeoutDate->format(DateTime::ISO8601)
        ]);
    }

    /**
     * Method called by the client to know is current state (cards, bankroll, ...)
     * @return void
     */
    public function sendCurrentState(): void
    {
        $this->sendMessage(["cards" => $this->getHoleCards()->getCards()]);
    }

    public function onPlayerAction(PlayerAction $event): void
    {
        if ($event->getPlayer() !== $this) {
            return;
        }

        $this->logger->info("Player action", ["event" => $event->getEventData(), "player" => $event->getPlayer()->getUserId()]);

        if (!empty($event->getEventData()["betting_action"])) {
            $betting_action = $event->getEventData()["betting_action"];
            if ($betting_action === "fold") {
                $this->folded = true;
            }
        }
    }

    public function __tostring(): string
    {
        $string = "UserID: " . $this->getUserId();

        if (!empty($this->hole_cards->getCards())) {
            $string .= " Cards: [";
            $string .= implode(",", array_map(fn(Card $card) => $card->__tostring(), $this->hole_cards->getCards()));
            $string .= "]";
        }

        return $string;
    }
}