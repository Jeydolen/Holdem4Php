<?php

namespace App\Game\Player;

use RuntimeException;

use DateTime;
use DateTimeInterface;

use App\Entity\User;
use App\Event\PlayerAction;

use App\Game\Card\Card;
use App\Game\CardPile\ICardPile;
use App\Game\CardPile\PlayerHoleCards;
use App\Game\WebSocket\ConnectionWrapper;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Serializer\Attribute\Groups;

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
    private User $user;

    private ICardPile $holeCards;

    private ?int $betTotalAmount = null;

    private Bankroll $bankroll;

    public function __construct(
        User $user,
        private ConnectionWrapper $connection,
        private LoggerInterface $logger
    ) {
        // For now user, is just a string for simplicity
        $this->user = $user;
        $this->bankroll = new Bankroll(0);
        $this->resetState();
    }

    /**
     * Reset player state to play another PokerHand
     * @return void
     */
    public function resetState()
    {
        $this->holeCards = new PlayerHoleCards(2, true);
        $this->betTotalAmount = null;
    }

    #[Groups(["show_basic_player"])]
    public function getUser(): User
    {
        return $this->user;
    }

    public function getUserId(): string
    {
        return $this->user->getUserId();
    }

    public function isSame(Player $player): bool
    {
        return $this->user == $player->user && $this->connection == $player->connection;
    }

    public function getHoleCards(): ICardPile
    {
        return $this->holeCards;
    }

    /**
     * Send a message to player connection
     * @param mixed $data Data to send, needs to be serializable
     * @return bool|null
     */
    public function sendMessage(mixed $data, array $context = []): bool|null
    {
        return $this->connection->sendJson($data, $context);
    }

    public function pushCard(Card $card): void
    {
        $this->holeCards->pushCard($card);
    }

    public function askBet(int $maxBettingAmount, array $legalActions, ?int $minBettingAmount = 0, ?DateTimeInterface $timeoutDate = null): void
    {
        $this->sendMessage([
            "action" => "ask_bet",
            "legal_actions" => $legalActions,
            "max_amount" => $maxBettingAmount,
            "min_amount" => $minBettingAmount,
            "timeout_date" => $timeoutDate->format(DateTime::ATOM)
        ]);
    }

    /**
     * Method called by the client to know is current state (cards, bankroll, ...)
     * @return void
     */
    public function sendCurrentState(): void
    {
        $state = $this->getPublicState();
        $state["cards"] = $this->getHoleCards()->getCards();
        $this->sendMessage($state);
    }

    public function getPublicState(): array
    {
        return [
            "user_id" => $this->getUserId(),
            "username" => $this->getUser()->getUsername(),
            "bet_total_amount" => $this->getBetTotalAmount(),
            "bankroll" => $this->getBankroll()
        ];
    }

    public function onPlayerAction(PlayerAction $event): void
    {
        if ($event->getPlayer() !== $this) {
            return;
        }

        $this->logger->info("Player action", ["event" => $event->getEventData(), "player" => $event->getPlayer()->getUserId()]);
    }

    public function setBetTotalAmount(int $amount): void
    {
        if ($amount <= 0) {
            throw new RuntimeException("Bet total amount can't be negative");
        }

        $this->betTotalAmount = $amount;
    }

    #[Groups(["show_basic_player"])]
    public function getBetTotalAmount(): ?int
    {
        return $this->betTotalAmount;
    }


    public function setBankroll(int $amount): void
    {
        $this->bankroll->setAmount($amount);
    }

    public function addBankroll(int $amount): void
    {
        $this->bankroll->setAmount($this->bankroll->getAmount() + $amount);
    }

    #[Groups(["show_basic_player"])]
    public function getBankroll(): int
    {
        return $this->bankroll->getAmount();
    }

    public function __tostring(): string
    {
        $string = "UserID: " . $this->getUserId();

        if (!empty($this->holeCards->getCards())) {
            $string .= " Cards: [";
            $string .= implode(",", array_map(fn(Card $card) => $card->__tostring(), $this->holeCards->getCards()));
            $string .= "]";
        }

        if (!empty($this->betTotalAmount)) {
            $string .= " Bet total amount: " . $this->getBetTotalAmount();
        }

        return $string;
    }
}