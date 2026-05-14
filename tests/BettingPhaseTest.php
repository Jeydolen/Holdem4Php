<?php

namespace App\Tests;

use App\Event\PhaseState;
use App\Event\PlayerAction;

use App\Enum\PlayerBettingActionEnum;

use App\Game\Player;
use App\Game\CardPile\Deck;
use App\Game\Hand\Phase\BettingPhase;

use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

use Psr\Log\NullLogger;

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
        return BettingPhase::fromArray([
            "logger" => new NullLogger(),
            "timeout" => $timeout,
            "maxBettingAmount" => $maxBettingAmount,
        ])->withEventDispatcher($this->dispatcher);
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
        $phase->play($players, $this->deck, null);

        $this->assertContains("next_phase", $this->dispatchedActions);
    }

    public function testPlayWithOnePlayersDispatchesNextPhaseImmediately(): void
    {
        $phase = $this->makePhase();
        $players = [$this->makePlayer("p1")];
        $phase->play($players, $this->deck, null);

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
        $phase->play($players, $this->deck, null);
    }

    public function testAfterFirstPlayerActsSecondPlayerIsAsked(): void
    {
        $p1 = $this->makePlayerMock("p1");
        $p2 = $this->makePlayerMock("p2");

        $p1->expects($this->once())->method("askBet");
        $p2->expects($this->once())->method("askBet");

        $phase = $this->makePhase();
        $players = [$p1, $p2];
        $phase->play($players, $this->deck, null);

        $this->sendAction($p1, PlayerBettingActionEnum::CHECK);
    }

    public function testAllPlayersActDispatchesNextPhase(): void
    {
        $p1 = $this->makePlayerMock("p1");
        $p2 = $this->makePlayer("p2");

        $p1->expects($this->exactly(2))->method("askBet");

        $phase = $this->makePhase();
        $players = [$p1, $p2];
        $phase->play($players, $this->deck, null);

        $this->sendAction($p1, PlayerBettingActionEnum::CHECK);
        $this->sendAction($p2, PlayerBettingActionEnum::BET, 50);

        // This should not work because the phase should ask first player again

        $this->assertContains("next_phase", $this->dispatchedActions);
    }

    public function testNextPhaseIsDispatchedWhenOnlyOnePlayerRemains(): void
    {
        $p1 = $this->makePlayer("p1");
        $p2 = $this->makePlayer("p2");

        $phase = $this->makePhase();
        $players = [$p1, $p2];
        $phase->play($players, $this->deck, null);

        // p1 folds - only p2 remains
        // so <= 1 active player triggers next_phase without asking p2
        $this->sendAction($p1, PlayerBettingActionEnum::FOLD);

        // In this case precisely we should have 0: player_fold
        $this->assertEquals("player_fold", $this->dispatchedActions[0]);
        // Then 1: next_phase
        $this->assertEquals("next_phase", $this->dispatchedActions[1]);

        $this->assertEquals(2, \sizeof($this->dispatchedActions));
    }

    public function testOutOfTurnActionIsIgnored(): void
    {
        $p1 = $this->makePlayerMock("p1");
        $p2 = $this->makePlayer("p2");

        $p1->expects($this->once())->method("askBet");

        $phase = $this->makePhase();
        $players = [$p1, $p2];
        $phase->play($players, $this->deck, null);

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
        $phase->play($players, $this->deck, null);

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
        $phase->play($players, $this->deck, null);

        $this->sendAction($p1, PlayerBettingActionEnum::BET, 50);

        $this->expectException(\App\Exception\InvalidPlayerBettingActionException::class);
        $this->sendAction($p2, PlayerBettingActionEnum::CALL, 10); // below minimum
    }
}