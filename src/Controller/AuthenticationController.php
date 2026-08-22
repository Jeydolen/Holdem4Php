<?php

namespace App\Controller;

use App\Entity\User;

use App\DTO\RegisterDTO;

use DateInterval;
use DateTimeImmutable;

use Doctrine\ORM\EntityManagerInterface;

use ParagonIE\Paseto\Builder;
use ParagonIE\Paseto\Keys\Base\AsymmetricSecretKey;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route("/authentication")]
final class AuthenticationController extends AbstractController
{
    private AsymmetricSecretKey $privateKey;

    public function __construct(string $privateKeyPath)
    {
        $this->privateKey = AsymmetricSecretKey::importPem(file_get_contents($privateKeyPath));
    }

    #[Route("/register", methods: ["GET", "POST"])]
    public function register(
        #[MapRequestPayload()] RegisterDTO $dto,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $user = new User();

        $plainPassword = $dto->password;
        $username = $dto->username;

        $user->setUsername($username);
        $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json(["registered" => true]);
    }

    #[Route("/login", methods: ["GET", "POST"])]
    public function login(#[CurrentUser] User $user): Response
    {
        $builder = (Builder::getPublic($this->privateKey))
            ->setNotBefore(new DateTimeImmutable())
            ->setIssuedAt(new DateTimeImmutable())
            ->setSubject($user->getUserId());

        // 15 min access token
        $accessToken = (clone $builder->setJti("access-token")->setExpiration((new DateTimeImmutable())->add(new DateInterval("P15M"))))->toString();
        $refreshToken = (clone $builder->setJti("refresh-token")->setExpiration((new DateTimeImmutable())->add(new DateInterval("P31D"))))->toString();

        $response = new JsonResponse([
            "access_token" => $accessToken,
            "refresh_token" => $refreshToken
        ]);


        $response->headers->setCookie(
            Cookie::create("refresh_token", $refreshToken)
                ->withHttpOnly()
                ->withSecure()
                ->withSameSite(Cookie::SAMESITE_STRICT)
        );

        return $response;
    }

    #[Route("/refresh", methods: ["POST"])]
    public function refresh(#[CurrentUser] User $user): JsonResponse
    {
        $builder = (Builder::getPublic($this->privateKey))
            ->setNotBefore(new DateTimeImmutable())
            ->setIssuedAt(new DateTimeImmutable())
            ->setSubject($user->getUserId());

        // 15 min access token
        $accessToken = (clone $builder->setJti("access-token")->setExpiration((new DateTimeImmutable())->add(new DateInterval("P15M"))))->toString();
        $refreshToken = (clone $builder->setJti("refresh-token")->setExpiration((new DateTimeImmutable())->add(new DateInterval("P31D"))))->toString();

        $response = new JsonResponse([
            "access_token" => $accessToken,
            "refresh_token" => $refreshToken
        ]);

        // Replace cookie for compatible clients (navigator)
        $response->headers->setCookie(
            Cookie::create("refresh_token", $refreshToken)
                ->withHttpOnly()
                ->withSecure()
                ->withSameSite(Cookie::SAMESITE_STRICT)
        );

        return $response;
    }
}
