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
        return new PositionManager($players, $this->logger);
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

        $this->positionManager = new PositionManager([$p1, $p2], $this->logger);
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

        $this->positionManager = new PositionManager([$p1, $p2], $this->logger);

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

    public function testShiftPositionShiftPlayersCorrectly(): void
    {
        $p1 = $this->makePlayer("p1");
        $p2 = $this->makePlayer("p2");

        $this->positionManager = new PositionManager([$p1, $p2], $this->logger);

        $this->positionManager->shiftPositions(1);
        $this->assertArraysHaveIdenticalValuesIgnoringOrder([
            $p2->getUserId() => [0, $p2],
            $p1->getUserId() => [1, $p1],
        ], $this->positionManager->getRawPlayers());
    }

    public function testShiftPositionShiftPlayersCorrectlyWithALotOfPlayers(): void
    {
        $shift = 2;
        $limit = 1000;
        $players = [];
        for ($i = 0; $i < $limit; $i++) {
            $player = $this->makePlayer("p" . $i);
            $this->positionManager->addPlayer($player);
            $players[] = $player;
        }

        $raw_players = $this->positionManager->getRawPlayers();
        $this->assertCount(1000, $raw_players);

        // If we shift 2 positions, all postions should be n + 2 except for the 2 last
        // which should be 0 and 1
        $this->positionManager->shiftPositions($shift);
        $raw_players = array_values($this->positionManager->getRawPlayers());
        foreach ($raw_players as $i => $position_per_player) {
            $expected_position = $i + $shift;
            if ($expected_position >= \count($raw_players)) {
                $expected_position = $expected_position - \count($players);
            }
            $this->assertArraysHaveIdenticalValuesIgnoringOrder([$expected_position, $players[$i]], $raw_players[$i]);
        }
    }
}