<?php

namespace App\Game\WebSocket;

use Exception;
use JsonException;

use App\DTO\TableRulesDTO;

use App\Game\Player;
use App\Game\Table\Table;
use Symfony\Component\Serializer\SerializerInterface;

use Workerman\Worker;
use Workerman\Connection\TcpConnection;

class Server
{

    public function __construct(private SerializerInterface $serializer)
    {
    }

    /** @var Table[] */
    private array $tables = [];

    public function createServer(int $port)
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
        $ws_worker->onClose = function ($connection) {};

        // Run worker
        Worker::runAll();
    }

    private function onConnect(ConnectionWrapper $connection): void
    {
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

            $this->handleActions($connection, $json["action"], $json);
        } catch (Exception $e) {
            $connection->sendJson(["error" => $e->getMessage()]);
        }
    }

    private function handleActions(ConnectionWrapper $connection, string $action, array $data): void
    {
        if ($action === "createTable") {
            // TODO: Proper role mecanism to create new tables 

            /** @var TableRulesDTO */
            $table_rules = $this->serializer->deserialize($data, TableRulesDTO::class, "json");

            return $this->createNewTable($connection, $table_rules);

        } else if ($action === "playerJoin") {
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
            if (empty($user_id)) {
                throw new Exception("Undefined user");
            }

            $table->addPlayer(new Player($user_id, $connection));
            $connection->send(json_encode(["player_joined" => true]));
        } else if ($action === "startGame") {
            $table_id = $data["table_id"] ?? null;
            if (empty($table_id)) {
                throw new Exception("Empty table id");
            }

            // TODO: Proper validation before join (not same player twice, ...)
            $table = $this->tables[$table_id] ?? null;
            if (empty($table)) {
                throw new Exception("Table does not exist");
            }

            $table->start();
            $connection->send(json_encode(["table_started" => true]));
        }
    }

    private function createNewTable(ConnectionWrapper $connection, TableRulesDTO $table_rules)
    {
        $table_id = uniqid("table");
        $this->tables[$table_id] = new Table(
            $table_rules->maxPlayers,
            $table_rules->phases,
            $table_rules->deckRules
        );

        $connection->sendJson(["table_id" => $table_id]);
    }
}