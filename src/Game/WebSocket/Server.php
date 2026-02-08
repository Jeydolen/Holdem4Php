<?php

namespace App\Game\WebSocket;

use Exception;
use JsonException;

use Workerman\Worker;
use Workerman\Connection\TcpConnection;

use App\DTO\TableRulesDTO;
use App\DTO\DeckGenerationDTO;

use App\Enum\DeckGenerationTypeEnum;

use App\Game\Player;
use App\Game\Table\Table;
use App\Game\Hand\Phase\PhaseFactory;

use App\Repository\TableRulesRepository;

use Symfony\Component\Serializer\SerializerInterface;


class Server
{

    public function __construct(
        private SerializerInterface $serializer,
        private TableRulesRepository $tableRulesRepository
    ) {
    }

    /** @var Table[] */
    private array $tables = [];

    public function createServer(int $port): Worker
    {
        $ws_worker = new Worker("websocket://0.0.0.0:$port");

        // Emitted when new connection come
        $ws_worker->onConnect = function (TcpConnection $connection) {
            $this->onConnect(new ConnectionWrapper($connection, $this->serializer));
        };

        // Emitted when data received
        $ws_worker->onMessage = function ($connection, $data) {
            $this->onMessage(new ConnectionWrapper($connection, $this->serializer), $data);
        };

        // Emitted when connection closed
        $ws_worker->onClose = function ($connection) {
            $conn = new ConnectionWrapper($connection, $this->serializer);
            $conn->sendJson(["connected" => false]);
        };

        // Run worker
        return $ws_worker;
    }

    /**
     * Method that create a new table for each game rules stored in db
     * @return int Number of tables created
     */
    public function loadTables(): int
    {
        $rules = $this->tableRulesRepository->findAll();
        foreach ($rules as $rule) {
            // Phases sorted by priority
            $phases = $rule->getPhases();

            $game_phases = [];
            // Game phase construction
            foreach ($phases as $phase) {
                $game_phase = PhaseFactory::create($phase->getType(), $phase->getTimeout(), $phase->getAdditionnalProperties());
                $game_phases[] = $game_phase;
            }

            $deck_rules = new DeckGenerationDTO();
            $deck_rules->cards = $rule->getCards()->toArray();
            $deck_rules->maxSize = \sizeof($deck_rules->cards);
            $deck_rules->noDuplicate = false;
            $deck_rules->generationType = DeckGenerationTypeEnum::MANUAL;

            $table_id = uniqid("table");
            $this->tables[$table_id] = new Table(
                $rule->getMaxPlayers(),
                $game_phases,
                $deck_rules
            );
        }

        return \sizeof($rules);
    }

    private function onConnect(ConnectionWrapper $connection): void
    {
        $connection->sendJson(["connected" => true]);
    }

    private function onMessage(ConnectionWrapper $connection, mixed $data): void
    {
        try {
            // TODO: Proper authentication mecanism
            try {
                $json = json_decode($data, associative: true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new Exception("Malformed data sent, please use valid JSON !", 0, $e);
            }


            if (empty($json["action"])) {
                throw new Exception("No action defined !");
            }

            $this->handleActions($connection, $json["action"], $data);
        } catch (Exception $e) {
            $connection->sendJson(["error" => $e->getMessage(), "error_type" => get_class($e)]);
        }
    }

    private function handleActions(ConnectionWrapper $connection, string $action, string $data): void
    {
        // TODO: Make a real search
        if ($action === "listTables") {
            $connection->sendJson(["tables" => $this->tables]);
            return;
        }


        $data = json_decode($data, associative: true, flags: JSON_THROW_ON_ERROR);
        if (in_array($action, ["playerJoin", "startGame", "playerGetState"])) {
            // TODO: Change for proper DTO
            $table_id = $data["table_id"] ?? null;
            if (empty($table_id)) {
                throw new Exception("Empty table id");
            }

            // TODO: Proper validation before join (not same player twice, ...)
            $table = $this->tables[$table_id] ?? null;
            if (empty($table)) {
                throw new Exception("Table does not exist");
            }

            // TODO: Proper player identification with JWT
            $user_id = $data["user_id"] ?? null;

            if ($action === "startGame") {
                $table->start();
                $connection->send(json_encode(["table_started" => true]));
                return;
            }

            if ($action === "playerJoin") {
                if (empty($user_id)) {
                    throw new Exception("Undefined user");
                }

                $table->addPlayer(new Player($user_id, $connection));
                $connection->send(json_encode(["player_joined" => true]));
                return;
            }

            if ($action === "playerGetState") {
                $player = $table->getPlayer($user_id);
                if (empty($player)) {
                    throw new Exception("Player not found");
                }

                $player->sendCurrentState();
                return;
            }
        }
    }
}