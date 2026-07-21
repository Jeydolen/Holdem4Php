<?php

namespace App\Tests;

use App\Event\PhaseState;
use App\Event\PlayerAction;

use App\Enum\PlayerBettingActionEnum;

use App\Game\Player\Player;
use App\Game\Player\PlayerCollection;

use App\Game\CardPile\Deck;
use App\Game\CardPile\BoardCards;

use App\Game\Hand\HandContext;
use App\Game\Hand\Phase\BettingPhase;

use App\Service\BettingManager;
use App\Service\CardRank\CardRankEvaluator;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\MockObject\MockObject;

use Symfony\Component\EventDispatcher\EventDispatcher;

class BettingPhaseTest extends TestCase
{
    private EventDispatcher $dispatcher;
    /** @var string[] */
    private array $dispatchedActions;

    private Deck&Stub $deck;

    public function setUp(): void
    {
        $this->dispatchedActions = [];
        $this->dispatcher = new EventDispatcher();
        $this->deck = $this->createStub(Deck::class);

        // Collect every PhaseState action dispatched during the test
        $this->dispatcher->addListener(PhaseState::class, function (PhaseState $event) {
            $this->dispatchedActions[] = $event->getAction();
        });
    }

    private function makePhase(int $maxBettingAmount = 100, ?int $timeout = null): BettingPhase
    {
        $logger = new Logger("test");
        if (getenv("DEBUG_LOG")) {
            $logger->pushHandler(new StreamHandler("php://stdout"));
        }

        return BettingPhase::fromArray([
            "logger" => $logger,
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
        $p1 = $this->makePlayerMock("p1");
        $p2 = $this->makePlayer("p2");

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
}