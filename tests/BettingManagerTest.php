<?php

namespace App\Tests;

use App\Game\Bet\BettingManager;
use App\Game\Bet\PlayerBettingActionEnum;

use App\Event\PhaseState;
use App\Exception\InvalidPlayerBettingActionException;

use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;

class BettingManagerTest extends TestCase
{
    private EventDispatcher $dispatcher;

    /** @var string[] */
    private array $dispatchedActions;

    /** @var array<string, mixed>[] */
    private array $dispatchedEvents;

    public function setUp(): void
    {
        $this->dispatchedActions = [];
        $this->dispatchedEvents = [];
        $this->dispatcher = new EventDispatcher();

        $this->dispatcher->addListener(PhaseState::class, function (PhaseState $event) {
            $this->dispatchedActions[] = $event->getAction();
            $this->dispatchedEvents[] = [
                'action' => $event->getAction(),
                'data' => $event->getEventData(),
            ];
        });
    }

    private function makeManager(int $pot = 0): BettingManager
    {
        return new BettingManager($this->dispatcher, $pot);
    }

    public function testInitialPotIsZero(): void
    {
        $manager = $this->makeManager();

        $this->assertSame(0, $manager->getPotAmount());
    }

    public function testInitialPotCanBeSet(): void
    {
        $manager = $this->makeManager(pot: 200);

        $this->assertSame(200, $manager->getPotAmount());
    }

    public function testInitialMinimalLegalBetIsOne(): void
    {
        $manager = $this->makeManager();

        $this->assertSame(1, $manager->getMinimalLegalBet());
    }

    public function testLegalActionsBeforeAnyBetIncludeCheckFoldAndBet(): void
    {
        $manager = $this->makeManager();
        $actions = $manager->computeLegalActions();

        $this->assertContains(PlayerBettingActionEnum::CHECK, $actions);
        $this->assertContains(PlayerBettingActionEnum::FOLD, $actions);
        $this->assertContains(PlayerBettingActionEnum::BET, $actions);
    }

    public function testLegalActionsBeforeAnyBetDoNotIncludeCall(): void
    {
        $manager = $this->makeManager();
        $actions = $manager->computeLegalActions();

        $this->assertNotContains(PlayerBettingActionEnum::CALL, $actions);
    }

    public function testLegalActionsAfterBetIncludeCallFoldAndRaise(): void
    {
        $manager = $this->makeManager();
        $manager->play("p1", PlayerBettingActionEnum::BET, 50, null);
        $actions = $manager->computeLegalActions();

        $this->assertContains(PlayerBettingActionEnum::CALL, $actions);
        $this->assertContains(PlayerBettingActionEnum::FOLD, $actions);
        $this->assertContains(PlayerBettingActionEnum::RAISE, $actions);
    }

    public function testLegalActionsAfterBetDoNotIncludeCheck(): void
    {
        $manager = $this->makeManager();
        $manager->play("p1", PlayerBettingActionEnum::BET, 50, null);
        $actions = $manager->computeLegalActions();

        $this->assertNotContains(PlayerBettingActionEnum::CHECK, $actions);
    }

    public function testLegalActionsAfterCallDoNotIncludeCheck(): void
    {
        $manager = $this->makeManager();
        $manager->play("p1", PlayerBettingActionEnum::BET, 50, null);
        $manager->play("p2", PlayerBettingActionEnum::CALL, 50, null);
        $actions = $manager->computeLegalActions();

        $this->assertNotContains(PlayerBettingActionEnum::CHECK, $actions);
    }

    public function testCheckIsValidWhenNoBetPlaced(): void
    {
        $manager = $this->makeManager();

        $manager->play("p1", PlayerBettingActionEnum::CHECK, null, null);

        $this->assertSame(0, $manager->getPotAmount());
    }

    public function testCheckDispatchesBettingActionEvent(): void
    {
        $manager = $this->makeManager();
        $manager->play("p1", PlayerBettingActionEnum::CHECK, null, null);

        $this->assertContains("player_betting_action", $this->dispatchedActions);
    }

    public function testCheckDispatchesEventWithCorrectData(): void
    {
        $manager = $this->makeManager();
        $manager->play("p1", PlayerBettingActionEnum::CHECK, null, null);

        $event = $this->findDispatchedEvent("player_betting_action");
        $this->assertSame("p1", $event['data']['player_id']);
        $this->assertSame(PlayerBettingActionEnum::CHECK, $event['data']['action']);
    }

    public function testCheckAfterBetThrows(): void
    {
        $manager = $this->makeManager();
        $manager->play("p1", PlayerBettingActionEnum::BET, 50, null);

        $this->expectException(InvalidPlayerBettingActionException::class);
        $manager->play("p2", PlayerBettingActionEnum::CHECK, null, null);
    }

    public function testBetAddsToPot(): void
    {
        $manager = $this->makeManager();
        $manager->play("p1", PlayerBettingActionEnum::BET, 50, null);

        $this->assertSame(50, $manager->getPotAmount());
    }

    public function testBetUpdatesMinimalLegalBet(): void
    {
        $manager = $this->makeManager();
        $manager->play("p1", PlayerBettingActionEnum::BET, 75, null);

        $this->assertSame(75, $manager->getMinimalLegalBet());
    }

    public function testBetWithoutAmountThrows(): void
    {
        $manager = $this->makeManager();

        $this->expectException(InvalidPlayerBettingActionException::class);
        $manager->play("p1", PlayerBettingActionEnum::BET, null, null);
    }

    public function testBetBelowMinimalLegalBetThrows(): void
    {
        $manager = $this->makeManager();
        $manager->play("p1", PlayerBettingActionEnum::BET, 50, 0);

        $this->expectException(InvalidPlayerBettingActionException::class);
        $manager->play("p2", PlayerBettingActionEnum::BET, 10, 0);
    }

    public function testBetDispatchesBettingActionEvent(): void
    {
        $manager = $this->makeManager();
        $manager->play("p1", PlayerBettingActionEnum::BET, 50, 0);

        $this->assertContains("player_betting_action", $this->dispatchedActions);
    }

    public function testBetDispatchesEventWithCorrectData(): void
    {
        $manager = $this->makeManager();
        $manager->play("p1", PlayerBettingActionEnum::BET, 50, 0);

        $event = $this->findDispatchedEvent("player_betting_action");
        $this->assertSame("p1", $event['data']['player_id']);
        $this->assertSame(PlayerBettingActionEnum::BET, $event['data']['action']);
    }

    public function testCallAddsToPot(): void
    {
        $manager = $this->makeManager();
        $manager->play("p1", PlayerBettingActionEnum::BET, 50, 0);
        $manager->play("p2", PlayerBettingActionEnum::CALL, 50, 0);

        $this->assertSame(100, $manager->getPotAmount());
    }

    public function testCallBeforeAnyBetThrows(): void
    {
        $manager = $this->makeManager();

        $this->expectException(InvalidPlayerBettingActionException::class);
        $manager->play("p1", PlayerBettingActionEnum::CALL, 50, 50);
    }

    public function testCallBelowMinimalLegalBetThrows(): void
    {
        $manager = $this->makeManager();
        $manager->play("p1", PlayerBettingActionEnum::BET, 50, 0);

        $this->expectException(InvalidPlayerBettingActionException::class);
        $manager->play("p2", PlayerBettingActionEnum::CALL, 10, 40);
    }

    public function testCallWithoutAmountThrows(): void
    {
        $manager = $this->makeManager();
        $manager->play("p1", PlayerBettingActionEnum::BET, 50, null);

        $this->expectException(InvalidPlayerBettingActionException::class);
        $manager->play("p2", PlayerBettingActionEnum::CALL, null, null);
    }

    public function testCallDispatchesBettingActionEvent(): void
    {
        $manager = $this->makeManager();
        $manager->play("p1", PlayerBettingActionEnum::BET, 50, 0);
        $manager->play("p2", PlayerBettingActionEnum::CALL, 50, null);

        // The last dispatched event should be from the CALL
        $event = $this->findLastDispatchedEvent("player_betting_action");
        $this->assertNotNull($event);
    }

    public function testCallDispatchesEventWithCorrectData(): void
    {
        $manager = $this->makeManager();
        $manager->play("p1", PlayerBettingActionEnum::BET, 50, 0);
        $manager->play("p2", PlayerBettingActionEnum::CALL, 50, 0);

        $event = $this->findLastDispatchedEvent("player_betting_action");
        $this->assertSame("p2", $event['data']['player_id']);
        $this->assertSame(PlayerBettingActionEnum::CALL, $event['data']['action']);
    }

    public function testFoldDispatchesPlayerFoldEvent(): void
    {
        $manager = $this->makeManager();
        $manager->play("p1", PlayerBettingActionEnum::FOLD, null, null);

        $this->assertContains("player_fold", $this->dispatchedActions);
    }

    public function testFoldDispatchesEventWithCorrectPlayerId(): void
    {
        $playerId = "p42";

        $manager = $this->makeManager();
        $manager->play($playerId, PlayerBettingActionEnum::FOLD, null, null);

        $event = $this->findDispatchedEvent("player_fold");
        $this->assertSame(["player_id" => $playerId], $event['data']);
    }

    public function testFoldDoesNotChangePot(): void
    {
        $manager = $this->makeManager();
        $manager->play("p1", PlayerBettingActionEnum::FOLD, null, null);

        $this->assertSame(0, $manager->getPotAmount());
    }

    public function testFoldIsAlwaysLegal(): void
    {
        $manager = $this->makeManager();

        $manager->play("p1", PlayerBettingActionEnum::FOLD, null, null);
        $this->assertContains("player_fold", $this->dispatchedActions);
    }

    public function testPotAccumulatesAcrossMultipleBets(): void
    {
        $manager = $this->makeManager();
        $manager->play("p1", PlayerBettingActionEnum::BET, 50, 0);
        $manager->play("p2", PlayerBettingActionEnum::RAISE, 100, 0);
        $manager->play("p3", PlayerBettingActionEnum::CALL, 100, 0);
        $manager->play("p1", PlayerBettingActionEnum::CALL, 100, 50);

        $this->assertSame(300, $manager->getPotAmount());
    }

    public function testReRaiseShouldThrowWhenAmountIsntHigherThanPreviousBet(): void
    {
        $this->expectException(InvalidPlayerBettingActionException::class);

        $manager = $this->makeManager();
        $manager->play("p1", PlayerBettingActionEnum::BET, 50, 0);
        $manager->play("p2", PlayerBettingActionEnum::RAISE, 100, 0);

        // You can't raise for the same amount as previous raise
        $manager->play("p3", PlayerBettingActionEnum::RAISE, 100, 0);
    }

    public function testPotAccumulatesAcrossMultipleBets2(): void
    {
        $manager = $this->makeManager();
        $manager->play("p1", PlayerBettingActionEnum::BET, 50, 0);
        $manager->play("p2", PlayerBettingActionEnum::RAISE, 100, 0);
        $manager->play("p3", PlayerBettingActionEnum::CALL, 100, 0);

        $this->assertSame(250, $manager->getPotAmount());

        $manager->play("p1", PlayerBettingActionEnum::RAISE, 125, 50);
        $manager->play("p2", PlayerBettingActionEnum::RAISE, 150, 100);
        $manager->play("p3", PlayerBettingActionEnum::RAISE, 200, 100);

        $this->assertSame(475, $manager->getPotAmount());

        $manager->play("p1", PlayerBettingActionEnum::CALL, 200, 125);
        $manager->play("p2", PlayerBettingActionEnum::CALL, 200, 150);

        $this->assertSame(600, $manager->getPotAmount());
    }

    public function testInitialPotIsIncludedInTotal(): void
    {
        $manager = $this->makeManager(pot: 100);
        $manager->play("p1", PlayerBettingActionEnum::BET, 50, 0);

        $this->assertSame(150, $manager->getPotAmount());
    }

    /** @return array{action: string, data: mixed}|null */
    private function findDispatchedEvent(string $action): ?array
    {
        foreach ($this->dispatchedEvents as $event) {
            if ($event['action'] === $action) {
                return $event;
            }
        }
        return null;
    }

    /** @return array{action: string, data: mixed}|null */
    private function findLastDispatchedEvent(string $action): ?array
    {
        $found = null;
        foreach ($this->dispatchedEvents as $event) {
            if ($event['action'] === $action) {
                $found = $event;
            }
        }
        return $found;
    }
}