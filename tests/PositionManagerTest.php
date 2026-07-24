<?php

namespace App\Tests;

use App\Game\Player\Player;
use App\Game\Position\PositionManager;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\Stub;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PositionManagerTest extends TestCase
{
    private PositionManager $positionManager;

    private LoggerInterface $logger;

    public function setUp(): void
    {
        $logger = new Logger("test");
        if (getenv("DEBUG_LOG")) {
            $logger->pushHandler(new StreamHandler("php://stdout"));
        }

        $this->logger = $logger;
        $this->positionManager = $this->makeManager([]);
    }


    private function makeManager(array $players = []): PositionManager
    {
        return new PositionManager(1000, $players, $this->logger);
    }

    private function makePlayer(string $id): Player&Stub
    {
        $player = $this->createStub(Player::class);
        $player->method("getUserId")->willReturn($id);
        $player->method("askBet");
        return $player;
    }

    public function testConstructorWorksCorrectly(): void
    {
        $p1 = $this->makePlayer("p1");
        $p2 = $this->makePlayer("p2");

        $this->positionManager = new PositionManager(1000, [$p1, $p2], $this->logger);
        // We should have ["p1" => [0, "p1"], "p2" => [1, "p2"]]
        $this->assertArraysHaveEqualValues([
            $p1->getUserId() => [0, $p1],
            $p2->getUserId() => [1, $p2],
        ], $this->positionManager->getRawPlayers());
    }


    public function testAddPlayerWorksCorrectly(): void
    {
        $p1 = $this->makePlayer("p1");

        $this->positionManager->addPlayer($p1);
        // We should have ["p1" => [0, "p1"]]
        $this->assertArraysHaveEqualValues([$p1->getUserId() => [0, $p1]], $this->positionManager->getRawPlayers());
    }

    public function testAddPlayerWorksCorrectlyWithALotOfPlayers(): void
    {
        $limit = 1000;
        for ($i = 0; $i < $limit; $i++) {
            $player = $this->makePlayer("p" . $i);
            $this->positionManager->addPlayer($player);
        }

        $players = $this->positionManager->getRawPlayers();
        $this->assertCount(1000, $players);
    }

    public function testRemovePlayerWorksCorrectly(): void
    {
        $p1 = $this->makePlayer("p1");
        $p2 = $this->makePlayer("p2");

        $this->positionManager = new PositionManager(10, [$p1, $p2], $this->logger);

        $this->positionManager->removePlayer($p1);

        $this->assertArraysHaveEqualValues([
            $p2->getUserId() => [1, $p2],
        ], $this->positionManager->getRawPlayers());
    }

    public function testRemovePlayerWorksCorrectlyWithALotOfPlayers(): void
    {
        $limit = 1000;
        $players = [];
        for ($i = 0; $i < $limit; $i++) {
            $player = $this->makePlayer("p" . $i);
            $this->positionManager->addPlayer($player);
            $players[] = $player;
        }

        $raw_players = $this->positionManager->getRawPlayers();
        $this->assertCount(1000, $raw_players);

        // We remove half players
        foreach ($players as $i => $player) {
            if ($i % 2 === 0) {
                continue;
            }
            $this->positionManager->removePlayer($player);
        }
        $this->assertCount(500, $this->positionManager->getRawPlayers());
    }

    public function testShiftButtonShiftPlayersCorrectly(): void
    {
        $p1 = $this->makePlayer("p1");
        $p2 = $this->makePlayer("p2");

        $this->positionManager = new PositionManager(10, [$p1, $p2], $this->logger);
        $this->positionManager->shiftButton(1);
        $this->assertArraysHaveIdenticalValuesIgnoringOrder([
            $p2->getUserId(),
            $p1->getUserId(),
        ], array_map(fn($p) => $p->getUserId(), $this->positionManager->getPlayers(true)));
    }

    public function testGetPlayersReturnPlayersOrderedByPosition(): void
    {
        $p1 = $this->makePlayer("p1");
        $p2 = $this->makePlayer("p2");

        $this->positionManager = new PositionManager(2, [$p1, $p2], $this->logger);

        $this->assertArraysAreIdentical(["p1", "p2"], array_map(fn($p) => $p->getUserId(), $this->positionManager->getPlayers()));

        // Position should be inverted here
        $this->positionManager->shiftButton(1);

        $this->assertArraysAreIdentical(["p2", "p1"], array_map(fn($p) => $p->getUserId(), $this->positionManager->getPlayers()));
    }

    public function testGetPlayersReturnPlayersCorrectlyWithNotAllPositionsFilled(): void
    {
        $limit = 10;
        $players = [];
        for ($i = 0; $i < $limit; $i++) {
            $player = $this->makePlayer("p" . $i);
            $players[] = $player;
        }
        $this->positionManager = new PositionManager($limit, $players, $this->logger);

        // We remove half players
        $expected_result = [];
        foreach ($players as $i => $player) {
            if ($i % 2 === 0) {
                $expected_result[] = $player->getUserId();
                continue;
            }
            $this->positionManager->removePlayer($player);
        }

        $this->assertArraysAreIdentical($expected_result, array_map(fn($p) => $p->getUserId(), $this->positionManager->getPlayers()));
        $this->positionManager->shiftButton(2);
        // p4 and p6 should be the 2 first and p2 should be the last one
        $ordered_players = array_map(fn($p) => $p->getUserId(), $this->positionManager->getPlayers());
        // dd($ordered_players, $expected_result);
        $this->assertEquals("p4", $ordered_players[0]);
        $this->assertEquals("p6", $ordered_players[1]);
        $this->assertEquals("p2", $ordered_players[4]);
    }
}