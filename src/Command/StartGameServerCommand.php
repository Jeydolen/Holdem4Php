<?php

namespace App\Command;

use App\Game\WebSocket\Server;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Container;

#[AsCommand(
    name: "StartGameServer",
    description: "Start the websocket server",
)]
class StartGameServerCommand extends Command
{
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

        $output->writeln(\sprintf("Game server started on port: %d", $port));
        $this->server->createServer($port);

        return Command::SUCCESS;
    }
}
