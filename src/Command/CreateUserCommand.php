<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use ParagonIE\Paseto\Keys\AsymmetricSecretKey;

use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: "app:create-user",
    description: "Create new Holdem4Php user",
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $userPasswordHasher,
    ) {
        parent::__construct();
    }

    public function __invoke(
        #[Argument("The displayed name for the user. Also used for authentication.")] string $username,
        #[Argument("The user password")] string $password,
        #[Argument("Whether the user is granted the admin role")] ?bool $is_admin = false,
        InputInterface $input,
        OutputInterface $output
    ): int {
        $io = new SymfonyStyle($input, $output);

        $io->title("User creation");
        $io->info(["Creating a new user with username:\n", $username]);

        $user_already_exist = $this->userRepository->findOneBy(["username" => $username]);

        if (!empty($user_already_exist)) {
            $io->error("Error: This username is already taken!");
            return Command::FAILURE;
        }

        $user = new User();
        $user->setUsername($username);
        $user->setPassword($this->userPasswordHasher->hashPassword($user, $password));

        if ($is_admin) {
            $user->setRoles(["ROLE_ADMIN"]);
        }

        $this->em->persist($user);
        $this->em->flush();

        $io->success("User created successfully !");
        return Command::SUCCESS;
    }
}
