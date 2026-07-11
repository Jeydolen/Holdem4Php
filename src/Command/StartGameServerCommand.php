<?php

namespace App\Command;

use App\Game\WebSocket\Server;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: "app:start-game-server",
    description: "Start the websocket server",
)]
class StartGameServerCommand extends Command implements SignalableCommandInterface
{

    private \OpenSwoole\WebSocket\Server $worker;

    public function __construct(private readonly Server $server)
    {
        parent::__construct();
    }

    public function __invoke(
        #[Argument("The websocket server port")] ?int $port,
        OutputInterface $output
    ): int {
        if (empty($port)) {
            $port = 1234;
        }

        pcntl_async_signals(true);
        pcntl_signal(SIGTERM, fn() => $this->server->close());

        $output->writeln(\sprintf("Game server started on port: %d", $port));
        $this->worker = $this->server->createServer($port);

        $tables_count = $this->server->loadTables();
        $output->writeln(\sprintf("Number of tables loaded: %d", $tables_count));
        $this->worker->start();

        return Command::SUCCESS;
    }
}
