<?php

namespace App\Game\WebSocket;

use Exception;
use JsonException;

use Psr\Log\LoggerInterface;


use OpenSwoole\Table;
use OpenSwoole\Timer;
use OpenSwoole\WebSocket\Frame;
use OpenSwoole\WebSocket\Server as WebSocketServer;


use App\DTO\DeckGenerationDTO;

use App\Enum\DeckGenerationTypeEnum;

use App\Event\PlayerAction;

use App\Game\Player;
use App\Game\Table\TableFactory;
use App\Game\Table\TableRegistry;
use App\Game\Hand\Phase\PhaseFactory;

use App\Repository\UserRepository;
use App\Repository\VariantRepository;

use ParagonIE\Paseto\Parser;
use ParagonIE\Paseto\Rules\ValidAt;
use ParagonIE\Paseto\Rules\IdentifiedBy;
use ParagonIE\Paseto\ProtocolCollection;
use ParagonIE\Paseto\Exception\PasetoException;
use ParagonIE\Paseto\Keys\Base\AsymmetricPublicKey;

use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class Server
{
    private PhaseFactory $phaseFactory;

    private TableFactory $tableFactory;

    private AsymmetricPublicKey $publicKey;

    private Table $swooleTable;

    private WebSocketServer $webSocketServer;

    public function __construct(
        string $publicKeyPath,
        private SerializerInterface $serializer,
        private VariantRepository $variantRepository,
        private UserRepository $userRepository,
        private LoggerInterface $logger,
        private TableRegistry $tableRegistry
    ) {
        $this->publicKey = AsymmetricPublicKey::importPem(file_get_contents($publicKeyPath));
        $this->phaseFactory = new PhaseFactory($this->logger);
        $this->tableFactory = new TableFactory($this->logger);

        $this->swooleTable = new Table(1024);
        $this->swooleTable->column("auth_timer_id", Table::TYPE_INT);
        $this->swooleTable->column("uid", Table::TYPE_STRING, 36);
        $this->swooleTable->create();
    }

    public function createServer(int $port): WebSocketServer
    {
        $server = new WebSocketServer("0.0.0.0", $port);
        $this->logger->info("WebSocket server created and listening on port", ["port" => $port]);

        $server->on('start', function (WebSocketServer $server) {
            \OpenSwoole\Process::signal(SIGINT, function () {
                $this->logger->info("SIGINT closing server...");
                $this->closeServer();
            });

            $this->logger->info("Master PID: %d", [$server->master_pid]);
        });

        $server->on("open", function (WebSocketServer $server, \OpenSwoole\Http\Request $request) {
            $this->logger->info("New connection", ["connection_status" => $request->fd]);
            $this->onConnect($server, $request->fd);
        });

        $server->on("message", function (WebSocketServer $server, Frame $frame) {
            $this->logger->debug("New message", ["data" => $frame->data]);
            $this->onMessage($server, $frame->fd, $frame->data);
        });

        // Emitted when connection closed
        $server->on("close", function (WebSocketServer $server, int $fd) {
            $this->logger->info("Connection closing", ["connection" => $fd]);
            $this->onClose($server, $fd);
        });

        $this->webSocketServer = $server;

        // Run worker
        return $server;
    }

    public function closeServer(): void
    {
        $this->logger->info("Received close instruction, exiting server....");
        $this->webSocketServer->shutdown();
    }

    /**
     * Method that create a new table for each game rules stored in db
     * @return int Number of tables created
     */
    public function loadTables(): int
    {
        $this->logger->info("Loading table rules");
        $rules = $this->variantRepository->findAll();
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
            $deck_rules->cards = $rule->getCards();
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

    private function onConnect(WebSocketServer $server, int $fd): void
    {
        $server->push($fd, $this->serializer->serialize(["connected" => true, "action" => "need_auth"], "json"));
        $timer_id = Timer::after(10 * 1000, function () use ($server, $fd) {
            $this->logger->info("Client did not authenticate, closing connection...");
            $server->disconnect($fd, reason: "Client did not authenticate");
        });
        $this->swooleTable->set($fd, ["auth_timer_id" => $timer_id]);
    }

    private function onClose(WebSocketServer $server, int $fd): void
    {
        $this->swooleTable->del($fd);
        $server->push($fd, $this->serializer->serialize(["connected" => false], "json"));
    }

    private function onMessage(WebSocketServer $server, int $fd, mixed $data): void
    {
        try {
            try {
                $json = json_decode($data, associative: true, flags: JSON_THROW_ON_ERROR);
            } catch (JsonException $e) {
                throw new Exception("Malformed data sent, please use valid JSON !", 0, $e);
            }

            if (empty($json["action"])) {
                throw new Exception("No action defined !");
            }

            if ($json["action"] === "login") {
                $this->handleLogin($server, $fd, $json);
                return;
            }

            $uid = $this->swooleTable->get($fd, "uid");
            if (empty($uid)) {
                return;
            }

            $connection = new ConnectionWrapper($server, $fd, $this->serializer);
            $connection->setUser($this->userRepository->findOneBy(["user_id" => $uid]));

            $this->handleActions($connection, $json["action"], $json);
        } catch (Exception $e) {
            $this->logger->error($e);
            $connection->sendJson(["error" => $e->getMessage(), "error_type" => \get_class($e)]);
        }
    }

    private function handleLogin(WebSocketServer $server, int $fd, array $data): void
    {
        if (empty($data["token"])) {
            throw new BadCredentialsException();
        }

        try {
            $paseto = Parser::getPublic($this->publicKey, ProtocolCollection::v4())
                ->addRule(new ValidAt())
                ->addRule(new IdentifiedBy("access-token"))
                ->parse($data["token"]);
        } catch (PasetoException $ex) {
            $this->logger->info("Invalid token", ["token" => $data["token"], "exception" => $ex]);
            throw new BadCredentialsException();
        }

        $user = $this->userRepository->findOneBy(["user_id" => $paseto->getSubject()]);
        if (empty($user)) {
            // Should not be possible but we never know
            throw new BadCredentialsException();
        }

        $this->logger->info("User authenticated successfully");
        Timer::clear($this->swooleTable->get($fd, "auth_timer_id"));

        $server->push($fd, $this->serializer->serialize(["authenticated" => true], "json"));
        $this->swooleTable->set($fd, ["uid" => $paseto->getSubject()]);
    }

    private function handleActions(ConnectionWrapper $connection, string $action, array $data): void
    {
        if (empty($connection->getUser())) {
            throw new BadCredentialsException("User is not authenticated");
        }

        // TODO: Make a real search
        if ($action === "listTables") {
            $connection->sendJson(["tables" => $this->tableRegistry->getAllTables()]);
            return;
        }

        if (\in_array($action, ["playerJoin", "playerQuit", "startGame", "playerGetState", "playerAction"])) {
            // TODO: Change for proper DTO
            $table_id = $data["table_id"] ?? null;
            if (empty($table_id)) {
                throw new Exception("Empty table id");
            }

            $table = $this->tableRegistry->getTable($table_id);
            if (empty($table)) {
                throw new Exception("Table does not exist");
            }

            // TODO: Use real rules for game start
            if ($action === "startGame") {
                $table->start();
                $connection->send(json_encode(["table_started" => true]));
                return;
            }

            if ($action === "playerJoin" || $action === "playerQuit") {
                $player = new Player($connection->getUser(), $connection, $this->logger);

                if ($action === "playerJoin") {
                    $table->addPlayer($player);
                } else if ($action === "playerQuit") {
                    $table->removePlayer($player, false);
                }

                return;
            }

            $player = $table->getPlayer($connection->getUser()->getUserId());
            if (empty($player)) {
                throw new Exception("Player not found");
            }

            if ($action === "playerGetState") {
                $player->sendCurrentState();
                return;
            }

            if ($action === "playerAction") {
                $table->dispatchEvent(new PlayerAction($player, $data));
                return;
            }
        }
    }
}