<?php

namespace App\Game\WebSocket;

use Exception;
use JsonException;

use Psr\Log\LoggerInterface;

use Symfony\Component\EventDispatcher\EventDispatcher;

use Workerman\Worker;
use Workerman\Connection\TcpConnection;

use App\DTO\DeckGenerationDTO;

use App\Enum\DeckGenerationTypeEnum;

use App\Event\PlayerAction;

use App\Game\Player;
use App\Game\Table\TableFactory;
use App\Game\Table\TableRegistry;
use App\Game\Hand\Phase\PhaseFactory;

use App\Repository\TableRulesRepository;

use Symfony\Component\Serializer\SerializerInterface;


class Server
{
    private EventDispatcher $dispatcher;

    private PhaseFactory $phaseFactory;

    public function __construct(
        private SerializerInterface $serializer,
        private TableRulesRepository $tableRulesRepository,
        private LoggerInterface $logger,
        private TableFactory $tableFactory,
        private TableRegistry $tableRegistry
    ) {
        $this->dispatcher = new EventDispatcher();
        $this->phaseFactory = new PhaseFactory($this->logger, $this->dispatcher);
    }

    public function createServer(int $port): Worker
    {
        $ws_worker = new Worker("websocket://0.0.0.0:$port");
        $this->logger->info("Worker created and listening on port", ["port" => $port]);

        // Emitted when new connection come
        $ws_worker->onConnect = function (TcpConnection $connection) {
            $this->logger->info("New connection", ["connection_status" => $connection->getStatus()]);
            $this->onConnect(new ConnectionWrapper($connection, $this->serializer));
        };

        // Emitted when data received
        $ws_worker->onMessage = function ($connection, $data) {
            $this->logger->info("New message", ["data" => $data]);
            $this->onMessage(new ConnectionWrapper($connection, $this->serializer), $data);
        };

        // Emitted when connection closed
        $ws_worker->onClose = function ($connection) {
            $this->logger->info("Connection closing", ["connection" => $connection]);
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
        $this->logger->info("Loading table rules");
        $rules = $this->tableRulesRepository->findAll();
        $this->logger->info("Table rules count", ["rules_count" => \sizeof($rules)]);

        foreach ($rules as $k => $rule) {
            $this->logger->debug("Loading new table rule", ["rule_number" => $k]);

            // Phases sorted by priority
            $phases = $rule->getPhases();

            $game_phases = [];
            // Game phase construction
            foreach ($phases as $phase) {
                $this->logger->debug("Creating phase", ["phase" => $phase]);

                $game_phase = $this->phaseFactory->create(
                    $phase->getType(),
                    $phase->getTimeout(),
                    $phase->getAdditionnalProperties()
                );

                $this->logger->info("Game phase created");
                $game_phases[] = $game_phase;
            }

            $deck_rules = new DeckGenerationDTO();
            $deck_rules->cards = $rule->getCards()->toArray();
            $deck_rules->maxSize = \sizeof($deck_rules->cards);
            $deck_rules->noDuplicate = false;
            $deck_rules->generationType = DeckGenerationTypeEnum::MANUAL;

            $this->logger->debug("Created deck rules for the table rule", ["deck_rules" => $deck_rules]);

            $table_id = uniqid("table");
            $this->tableRegistry->addTable(
                $table_id,
                $this->tableFactory->createTable(
                    $rule->getMaxPlayers(),
                    $game_phases,
                    $deck_rules
                )
            );

            $this->logger->info("Table created", ["table_id" => $table_id]);
        }

        $this->logger->info("Loaded all table rules");

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
            $this->logger->error($e);
            $connection->sendJson(["error" => $e->getMessage(), "error_type" => get_class($e)]);
        }
    }

    private function handleActions(ConnectionWrapper $connection, string $action, string $data): void
    {
        // TODO: Make a real search
        if ($action === "listTables") {
            $connection->sendJson(["tables" => $this->tableRegistry->getAllTables()]);
            return;
        }

        $data = json_decode($data, associative: true, flags: JSON_THROW_ON_ERROR);
        if (\in_array($action, ["playerJoin", "startGame", "playerGetState", "playerAction"])) {
            // TODO: Change for proper DTO
            $table_id = $data["table_id"] ?? null;
            if (empty($table_id)) {
                throw new Exception("Empty table id");
            }

            // TODO: Proper validation before join (not same player twice, ...)
            $table = $this->tableRegistry->getTable($table_id);
            if (empty($table)) {
                throw new Exception("Table does not exist");
            }

            // TODO: Proper player identification with JWT
            $user_id = $data["user_id"] ?? null;

            // TODO: Use real rules for game start
            if ($action === "startGame") {
                $table->start();
                $connection->send(json_encode(["table_started" => true]));
                return;
            }

            if ($action === "playerJoin") {
                if (empty($user_id)) {
                    throw new Exception("Undefined user");
                }

                $player = new Player($user_id, $connection, $this->logger);
                $this->dispatcher->addSubscriber($player);

                $this->logger->info("Listeners", ["listeners" => $this->dispatcher->getListeners(PlayerAction::class)]);

                $table->addPlayer($player);
                $connection->send(json_encode(["player_joined" => true]));
                return;
            }

            $player = $table->getPlayer($user_id);
            if (empty($player)) {
                throw new Exception("Player not found");
            }

            if ($action === "playerGetState") {
                $player->sendCurrentState();
                return;
            }

            if ($action === "playerAction") {
                $this->dispatcher->dispatch(new PlayerAction($player, $data));
                return;
            }
        }
    }
}