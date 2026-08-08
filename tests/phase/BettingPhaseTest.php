<?php

namespace App\Tests\Phase;

use App\Entity\User;

use App\Event\PhaseState;
use App\Event\PlayerAction;

use App\Game\Bet\BettingManager;
use App\Game\Bet\PlayerBettingActionEnum;

use App\Game\Player\Player;
use App\Game\Player\PlayerCollection;

use App\Game\CardPile\Deck;
use App\Game\CardPile\BoardCards;

use App\Game\Hand\HandContext;
use App\Game\Hand\Phase\BettingPhase;

use App\Game\WebSocket\ConnectionWrapper;

use App\Service\CardRank\CardRankEvaluator;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\MockObject\MockObject;

use Psr\Log\LoggerInterface;

use Symfony\Component\EventDispatcher\EventDispatcher;

class BettingPhaseTest extends TestCase
{
    private EventDispatcher $dispatcher;
    /** @var string[] */
    private array $dispatchedActions;

    private Deck&Stub $deck;

    private LoggerInterface $logger;

    public function setUp(): void
    {
        $this->dispatchedActions = [];
        $this->dispatcher = new EventDispatcher();
        $this->deck = $this->createStub(Deck::class);

        // Collect every PhaseState action dispatched during the test
        $this->dispatcher->addListener(PhaseState::class, function (PhaseState $event) {
            $this->dispatchedActions[] = $event->getAction();
        });

        $logger = new Logger("test");
        if (getenv("DEBUG_LOG")) {
            $logger->pushHandler(new StreamHandler("php://stdout"));
        }

        $this->logger = $logger;
    }

    private function makePhase(int $maxBettingAmount = 100, ?int $timeout = null): BettingPhase
    {

        return BettingPhase::fromArray([
            "logger" => $this->logger,
            "timeout" => $timeout,
            "maxBettingAmount" => $maxBettingAmount,
        ])->withEventDispatcher($this->dispatcher);
    }

    private function makeHandContext(array $players)
    {
        $context = new HandContext(
            $this->deck,
            new BoardCards(5, true),
            new PlayerCollection($players),
            new BettingManager($this->dispatcher),
            new CardRankEvaluator()
        );

        // We have to emulate Table comportement
        $this->dispatcher->addListener(PhaseState::class, function (PhaseState $event) use ($context) {
            if ($event->getAction() === "player_fold") {
                $context->getPlayerCollection()->foldPlayer($event->getEventData()["player_id"]);
            }
        });

        return $context;
    }

    private function makePlayer(string $id): Player&Stub
    {
        $player = $this->createStub(Player::class);
        $player->method("getUserId")->willReturn($id);
        $player->method("askBet");
        return $player;
    }

    private function makeRealPlayer(string $id): Player&MockObject
    {
        $player = $this->createPartialMock(Player::class, ["getUserId", "getPublicState", "sendMessage", "askBet"]);
        $player->__construct($this->createStub(User::class), $this->createStub(ConnectionWrapper::class), $this->logger);
        $player->method("getUserId")->willReturn($id);
        $player->setBankroll(1000);

        // resetState instanciate hole_cards
        $player->resetState();
        return $player;
    }

    private function makePlayerMock(string $id): Player&MockObject
    {
        $player = $this->createMock(Player::class);
        $player->method("getUserId")->willReturn($id);
        return $player;
    }

    private function sendAction(Player $player, PlayerBettingActionEnum $action, ?int $amount = null): void
    {
        $data = ["betting_action" => $action->value];
        if ($amount !== null) {
            $data["betting_amount"] = $amount;
        }
        $this->dispatcher->dispatch(new PlayerAction($player, $data));
    }

    public function testPlayWithNoPlayersDispatchesNextPhaseImmediately(): void
    {
        $phase = $this->makePhase();
        $players = [];
        $context = $this->makeHandContext($players);
        $phase->play($context);

        $this->assertContains("next_phase", $this->dispatchedActions);
    }

    public function testPlayWithOnePlayersDispatchesNextPhaseImmediately(): void
    {
        $phase = $this->makePhase();
        $players = [$this->makePlayer("p1")];
        $context = $this->makeHandContext($players);
        $phase->play($context);

        $this->assertContains("next_phase", $this->dispatchedActions);
    }

    public function testAskBetIsCalledOnFirstPlayer(): void
    {
        $p1 = $this->makePlayerMock("p1");
        $p2 = $this->makePlayerMock("p2");

        $p1->expects($this->once())->method("askBet");
        $p2->expects($this->never())->method("askBet");

        $phase = $this->makePhase();
        $players = [$p1, $p2];
        $context = $this->makeHandContext($players);
        $phase->play($context);
    }

    public function testAfterFirstPlayerActsSecondPlayerIsAsked(): void
    {
        $p1 = $this->makePlayerMock("p1");
        $p2 = $this->makePlayerMock("p2");

        $p1->expects($this->once())->method("askBet");
        $p2->expects($this->once())->method("askBet");

        $phase = $this->makePhase();
        $players = [$p1, $p2];
        $context = $this->makeHandContext($players);
        $phase->play($context);

        $this->sendAction($p1, PlayerBettingActionEnum::CHECK);
    }

    public function testAllPlayersActDispatchesNextPhase(): void
    {
        $p1 = $this->makeRealPlayer("p1");
        $p2 = $this->makeRealPlayer("p2");

        $p1->expects($this->exactly(2))->method("askBet");

        $phase = $this->makePhase();
        $players = [$p1, $p2];
        $context = $this->makeHandContext($players);
        $phase->play($context);

        $this->sendAction($p1, PlayerBettingActionEnum::CHECK);
        $this->sendAction($p2, PlayerBettingActionEnum::BET, 50);
    }

    public function testNextPhaseIsDispatchedWhenOnlyOnePlayerRemains(): void
    {
        $p1 = $this->makePlayer("p1");
        $p2 = $this->makePlayer("p2");

        $phase = $this->makePhase();
        $players = [$p1, $p2];
        $context = $this->makeHandContext($players);
        $phase->play($context);

        // p1 folds - only p2 remains
        // so <= 1 active player triggers next_phase without asking p2
        $this->sendAction($p1, PlayerBettingActionEnum::FOLD);

        // In this case precisely we should have 0: player_betting_action 1: player_fold
        $this->assertEquals("player_betting_action", $this->dispatchedActions[0]);
        $this->assertEquals("player_fold", $this->dispatchedActions[1]);
        // Then 2: next_phase
        $this->assertEquals("next_phase", $this->dispatchedActions[2]);

        $this->assertEquals(3, \sizeof($this->dispatchedActions));
    }

    public function testOutOfTurnActionIsIgnored(): void
    {
        $p1 = $this->makePlayerMock("p1");
        $p2 = $this->makePlayer("p2");

        $p1->expects($this->once())->method("askBet");

        $phase = $this->makePhase();
        $players = [$p1, $p2];
        $context = $this->makeHandContext($players);
        $phase->play($context);

        // p2 tries to act before it is their turn
        $this->sendAction($p2, PlayerBettingActionEnum::CHECK);

        // next_phase must NOT have been dispatched yet
        $this->assertNotContains("next_phase", $this->dispatchedActions);
    }

    public function testSecondPlayerCannotCheckAfterBet(): void
    {
        $p1 = $this->makePlayer("p1");
        $p2 = $this->makePlayer("p2");

        $phase = $this->makePhase();
        $players = [$p1, $p2];
        $context = $this->makeHandContext($players);
        $phase->play($context);

        $this->sendAction($p1, PlayerBettingActionEnum::BET, 50);

        $this->expectException(\App\Exception\InvalidPlayerBettingActionException::class);
        $this->sendAction($p2, PlayerBettingActionEnum::CHECK);
    }

    public function testSecondPlayerMustCallAtLeastMinimalBet(): void
    {
        $p1 = $this->makePlayer("p1");
        $p2 = $this->makePlayer("p2");

        $phase = $this->makePhase();
        $players = [$p1, $p2];
        $context = $this->makeHandContext($players);
        $phase->play($context);

        $this->sendAction($p1, PlayerBettingActionEnum::BET, 50);

        $this->expectException(\App\Exception\InvalidPlayerBettingActionException::class);
        $this->sendAction($p2, PlayerBettingActionEnum::CALL, 10); // below minimum
    }

    public function testPhaseResetCorrectlyWhenCalledASecondTime(): void
    {
        $p1 = $this->makePlayer("p1");
        $p2 = $this->makePlayer("p2");

        $phase = $this->makePhase();
        $players = [$p1, $p2];
        $context = $this->makeHandContext($players);
        $phase->play($context);

        $this->sendAction($p1, PlayerBettingActionEnum::BET, 50);
        $this->sendAction($p2, PlayerBettingActionEnum::CALL, 50);

        $this->assertContains("next_phase", $this->dispatchedActions);
        // Everything is ok here
        // We can loop over again

        $this->dispatchedActions = [];
        $phase->play($context);

        $this->sendAction($p1, PlayerBettingActionEnum::BET, 50);
        $this->sendAction($p2, PlayerBettingActionEnum::CALL, 50);

        $this->assertContains("next_phase", $this->dispatchedActions);
    }


    public function testFullBettingPhase(): void
    {
        $p1 = $this->makeRealPlayer("p1");
        $p2 = $this->makeRealPlayer("p2");
        $p3 = $this->makeRealPlayer("p3");

        // Every player should be asked bet 3 times
        $p1->expects($this->exactly(3))->method("askBet");
        $p2->expects($this->exactly(3))->method("askBet");
        $p3->expects($this->exactly(3))->method("askBet");


        $phase = $this->makePhase();
        $players = [$p1, $p2, $p3];
        $context = $this->makeHandContext($players);
        $phase->play($context);

        $this->sendAction($p1, PlayerBettingActionEnum::BET, 50);
        $this->sendAction($p2, PlayerBettingActionEnum::RAISE, 100);
        $this->sendAction($p3, PlayerBettingActionEnum::CALL, 100);

        $this->assertSame(250, $context->getBettingManager()->getPotAmount());

        $this->sendAction($p1, PlayerBettingActionEnum::RAISE, 125);
        $this->sendAction($p2, PlayerBettingActionEnum::RAISE, 150);
        $this->sendAction($p3, PlayerBettingActionEnum::RAISE, 200);

        $this->assertSame(475, $context->getBettingManager()->getPotAmount());

        $this->sendAction($p1, PlayerBettingActionEnum::CALL, 200);
        $this->sendAction($p2, PlayerBettingActionEnum::CALL, 200);

        $this->assertSame(600, $context->getBettingManager()->getPotAmount());

        $this->expectException(\App\Exception\InvalidPlayerBettingActionException::class);
        $this->sendAction($p3, PlayerBettingActionEnum::CHECK);
    }
}