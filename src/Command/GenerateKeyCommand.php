<?php

namespace App\Command;

use ParagonIE\Paseto\Keys\AsymmetricSecretKey;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: "app:generate-keys",
    description: "Generate key pair for paseto authentication",
)]
class GenerateKeyCommand extends Command
{
    public function __invoke(
        #[Argument("The path used to store keys")] ?string $path,
        OutputInterface $output
    ): int {
        $private_key = new AsymmetricSecretKey(sodium_crypto_sign_keypair());
        $public_key = $private_key->getPublicKey();

        $output->writeln("Private key:\n" . $private_key->encodePem());
        $output->writeln("Public key:\n" . $public_key->encodePem());

        if (!empty($path)) {
            if (!is_writable($path . "/")) {
                $output->writeln("Error: Path is not writeable ! Exiting...");
                return Command::FAILURE;
            }

            file_put_contents("$path/key.private", $private_key->encodePem());
            file_put_contents("$path/key.pub", $public_key->encodePem());
        }

        return Command::SUCCESS;
    }
}
