<?php

namespace App\Tests\Phase;


use App\Event\PhaseState;

use App\Game\Bet\BettingManager;

use App\Game\CardPile\Deck;
use App\Game\CardPile\BoardCards;

use App\Game\Hand\HandContext;
use App\Game\Hand\Phase\AskBlindPhase;

use App\Game\Player\Player;
use App\Game\Player\PlayerCollection;

use App\Service\CardRank\CardRankEvaluator;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

use Psr\Log\LoggerInterface;

use Symfony\Component\EventDispatcher\EventDispatcher;

class AskBlindPhaseTest extends TestCase
{
    private EventDispatcher $dispatcher;

    /** @var string[] */
    private array $dispatchedActions;

    private LoggerInterface $logger;

    public function setUp(): void
    {
        $this->dispatchedActions = [];
        $this->dispatcher = new EventDispatcher();

        // Collect every PhaseState action dispatched during the test
        $this->dispatcher->addListener(PhaseState::class, function (PhaseState $event) {
            $this->dispatchedActions[] = $event->getAction();
        });
    }

    private function makePhase(int $nbPlayersToAsk = 2): AskBlindPhase
    {
        $logger = new Logger("test");
        if (getenv("DEBUG_LOG")) {
            $logger->pushHandler(new StreamHandler("php://stdout"));
        }
        $this->logger = $logger;

        return AskBlindPhase::fromArray([
            "logger" => $logger,
            "nbPlayersToAsk" => $nbPlayersToAsk,
            "baseAmount" => 1,
            "amountModifier" => 2,
        ])->withEventDispatcher($this->dispatcher);
    }


    private function makeHandContext(array $players)
    {
        $context = new HandContext(
            new Deck(52, true, null),
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


    public function testPlayWithNoPlayersDispatchesNextPhaseImmediately(): void
    {
        $phase = $this->makePhase();
        $players = [];
        $context = $this->makeHandContext($players);
        $phase->play($context);

        $this->assertContains("next_phase", $this->dispatchedActions);
    }

    public function testAskBlindPhasePlayWorksCorrectly(): void
    {
        $phase = $this->makePhase(1);
        $players = [$this->makePlayer("p1")];
        $context = $this->makeHandContext($players);
        $phase->play($context);

        $this->assertEquals($context->getBettingManager()->getPotAmount(), 1);

        $this->assertContains("next_phase", $this->dispatchedActions);
        $this->assertEquals(1, \sizeof($this->dispatchedActions));
    }

    public function testAskBlindGoesToNextPhaseWhenThereIsLessPlayersThanNbPlayersToAsk(): void
    {
        // Make phases need to ask 10 players
        $phase = $this->makePhase(10);

        $players = [$this->makePlayer("p1")];
        $context = $this->makeHandContext($players);
        $phase->play($context);

        $this->assertEquals($context->getBettingManager()->getPotAmount(), 1);

        $this->assertContains("next_phase", $this->dispatchedActions);
        $this->assertEquals(1, \sizeof($this->dispatchedActions));
    }

    public function testAskBlindAmountModifierWorksCorrectly(): void
    {
        // Make phases need to ask 10 players
        $phase = $this->makePhase(10);

        $players = [$this->makePlayer("p1"), $this->makePlayer("p2")];
        $context = $this->makeHandContext($players);
        $phase->play($context);

        // Pot should be 3 because: baseAmount + (baseAmount * 1 * modifier)
        $this->assertEquals($context->getBettingManager()->getPotAmount(), 3);

        $players = [$this->makePlayer("p1"), $this->makePlayer("p2"), $this->makePlayer("p3")];
        $context = $this->makeHandContext($players);
        $phase->play($context);

        // 3 players so: 1 + 2 + 4
        $this->assertEquals($context->getBettingManager()->getPotAmount(), 7);
    }
}