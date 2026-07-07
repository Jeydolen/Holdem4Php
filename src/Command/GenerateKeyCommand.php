<?php

namespace App\Command;

use ParagonIE\Paseto\Keys\AsymmetricSecretKey;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: "app:generate-keys",
    description: "Generate key pair for paseto authentication",
)]
class GenerateKeyCommand extends Command
{
    public function __invoke(OutputInterface $output): int
    {
        $private_key = new AsymmetricSecretKey(sodium_crypto_sign_keypair());
        $public_key = $private_key->getPublicKey();

        $output->writeln("Private key:\n" . $private_key->encodePem());
        $output->writeln("Public key:\n" . $public_key->encodePem());

        return Command::SUCCESS;
    }
}
