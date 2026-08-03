<?php

namespace App\Tests\Phase;

use App\Entity\User;

use App\Enum\CardRankEnum;
use App\Enum\CardSymbolEnum;

use App\Event\PhaseState;

use App\Game\Bet\BettingManager;
use App\Game\Card\Card;
use App\Game\CardPile\BoardCards;
use App\Game\CardPile\Deck;

use App\Game\Hand\HandContext;
use App\Game\Hand\Phase\DrawCardPhase;

use App\Game\Player\Player;
use App\Game\Player\PlayerCollection;

use App\Game\WebSocket\ConnectionWrapper;

use App\Service\CardRank\CardRankEvaluator;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

use Psr\Log\LoggerInterface;

use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Uid\Uuid;

class DrawCardPhaseTest extends TestCase
{
    private EventDispatcher $dispatcher;
    /** @var string[] */
    private array $dispatchedActions;

    private Deck $deck;

    private LoggerInterface $logger;

    public function setUp(): void
    {
        $this->dispatchedActions = [];
        $this->dispatcher = new EventDispatcher();

        // Collect every PhaseState action dispatched during the test
        $this->dispatcher->addListener(PhaseState::class, function (PhaseState $event) {
            $this->dispatchedActions[] = $event->getAction();
        });

        // Simple deck only containing spades in order
        $cards = [];
        foreach (CardRankEnum::cases() as $card_rank) {
            $cards[] = new Card($card_rank, CardSymbolEnum::SPADE);
        }

        $this->deck = new Deck(52, true, $cards);
    }

    private function makePhase(int $drawNumber = 2): DrawCardPhase
    {
        $logger = new Logger("test");
        if (getenv("DEBUG_LOG")) {
            $logger->pushHandler(new StreamHandler("php://stdout"));
        }
        $this->logger = $logger;

        return DrawCardPhase::fromArray([
            "logger" => $logger,
            "drawNumber" => $drawNumber,
        ])->withEventDispatcher($this->dispatcher);
    }


    private function makeHandContext(array $players)
    {
        $context = new HandContext(
            $this->deck,
            new BoardCards(5, true),
            new PlayerCollection($players),
            new BettingManager(new EventDispatcher()),
            new CardRankEvaluator()
        );

        return $context;
    }

    private function makePlayer(string $id): Player&MockObject
    {
        $player = $this->createPartialMock(Player::class, ["getUserId", "getPublicState", "sendMessage"]);
        $player->method("getUserId")->willReturn($id);

        // resetState instanciate hole_cards
        $player->resetState();
        return $player;
    }


    public function testDrawCardPhasePlayWorksCorrectly(): void
    {
        $phase = $this->makePhase();
        $players = [$this->makePlayer("p1")];
        $context = $this->makeHandContext($players);
        $phase->play($context);

        // Player should have 2 cards in his pocket cards
        $this->assertArraysAreIdentical(
            ["A", "K"],
            array_map(fn($c) => $c->getRank(), $players[0]->getHoleCards()->getCards())
        );

        // There was 13 cards - 2 = 11 cards
        $this->assertEquals(11, \sizeof($context->getDeck()->getCards()));

        $this->assertContains("next_phase", $this->dispatchedActions);
    }

}