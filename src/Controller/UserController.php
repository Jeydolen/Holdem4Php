<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route("/user")]
class UserController extends AbstractController
{
    public function __construct(private UserRepository $userRepository)
    {
    }

    #[Route("/get_informations", methods: ["GET"])]
    public function getInformations(#[CurrentUser()] User $user): JsonResponse
    {
        return $this->json(["user" => $user], context: ["groups" => ["show_full_user", "show_bankroll"]]);
    }
}