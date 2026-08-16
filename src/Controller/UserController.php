<?php

namespace App\Controller;

use App\DTO\SearchUserDTO;
use App\DTO\SetBankrollDTO;
use App\Entity\Bankroll;
use App\Entity\User;
use App\Repository\UserRepository;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class UserController extends AbstractController
{
    public function __construct(private UserRepository $userRepository, private EntityManagerInterface $em)
    {
    }

    #[Route("/user/get_informations", methods: ["GET"])]
    public function getInformations(#[CurrentUser()] User $user): JsonResponse
    {
        return $this->json(["user" => $user], context: ["groups" => ["show_full_user", "show_bankroll"]]);
    }

    #[Route("/admin/user/{uuid}/set_bankroll", methods: ["POST"])]
    public function addBankroll(string $uuid, #[MapRequestPayload()] SetBankrollDTO $dto): JsonResponse
    {
        $user = $this->userRepository->findOneBy(["user_id" => $uuid]);
        if (empty($user)) {
            throw $this->createNotFoundException("User not found");
        }

        $bankroll = $user->getBankroll();
        if (empty($bankroll)) {
            $bankroll = new Bankroll();
            $bankroll->setAmount(0);
            $bankroll->setUser($user);
            $this->em->persist($bankroll);
        }

        $bankroll->setAmount($dto->amount);

        $this->em->flush();
        return $this->json(["ok" => true, "new_amount" => $dto->amount]);
    }

    #[Route("/admin/user/get_all", methods: ["POST"])]
    public function getAllUsers(#[MapRequestPayload()] SearchUserDTO $dto): JsonResponse
    {
        $paginator = $this->userRepository->fetchUsersWithLimit($dto->page, $dto->limit);
        return $this->json(
            ["users" => $paginator->getIterator(), "total_count" => $paginator->count()],
            context: ["groups" => ["show_full_user", "show_bankroll"]]
        );
    }

    #[Route("/admin/user/{uuid}/delete", methods: ["DELETE"])]
    public function deleteUser(string $uuid): JsonResponse
    {
        try {
            $this->em->beginTransaction();

            $user = $this->userRepository->findOneBy(["user_id" => $uuid]);

            if (empty($user)) {
                throw $this->createNotFoundException();
            }

            $this->em->remove($user);

            $this->em->flush();
            $this->em->commit();
        } catch (Exception $e) {
            $this->em->rollback();
            throw $e;
        }

        return $this->json(["removed" => true, "user_id" => $uuid]);
    }
}