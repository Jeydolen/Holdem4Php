<?php

namespace App\Controller;

use App\Entity\Bankroll;

use App\Repository\UserRepository;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route("/admin/game")]
final class GameController extends AbstractController
{


    #[Route("/add_bankroll/{uuid}/{amount}", methods: ["GET"])]
    public function addBankroll(UserRepository $userRepository, string $uuid, int $amount): JsonResponse
    {
        $user = $userRepository->findOneBy(["user_id" => $uuid]);
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

        $new_amount = $bankroll->getAmount() + $amount;
        $bankroll->setAmount($new_amount);

        $this->em->flush();
        return $this->json(["new_amount" => $new_amount]);
    }
}
