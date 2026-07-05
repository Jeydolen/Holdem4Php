<?php

namespace App\Controller;

use App\Entity\User;

use App\DTO\RegisterDTO;
use App\Form\RegistrationFormType;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

use ParagonIE\Paseto\Builder;
use ParagonIE\Paseto\Keys\Base\AsymmetricSecretKey;

use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;

use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

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
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $client_type = $request->headers->get("X-Client-Type", "web");
        $user = new User();

        $registered = false;
        if ($client_type === "web") {
            $form = $this->createForm(RegistrationFormType::class, $user);
            $form->handleRequest($request);

            if ($form->isSubmitted() && $form->isValid()) {
                /** @var string $plainPassword */
                $plainPassword = $form->get('plainPassword')->getData();
                $username = $form->get('username')->getData();
                $registered = true;
            } else {
                return $this->render('registration/register.html.twig', ['registrationForm' => $form]);
            }

        } else {
            $dto = $serializer->deserialize($request->getContent(), RegisterDTO::class, "json");
            $plainPassword = $dto->password;
            $username = $dto->username;
            $violations = $validator->validate($dto);
            $registered = $violations->count() > 0;
        }

        $user->setUsername($username);
        $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

        $entityManager->persist($user);
        $entityManager->flush();

        if ($registered && $client_type === "web") {
            return $this->redirectToRoute('app_authentication_login');
        }

        return $this->json(["registered" => $registered]);
    }

    #[Route("/login", methods: ["GET", "POST"])]
    public function login(#[CurrentUser] ?User $user, Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        $client_type = $request->headers->get("X-Client-Type", "web");
        if ($client_type === "web" && empty($user)) {
            // get the login error if there is one
            $error = $authenticationUtils->getLastAuthenticationError();

            // last username entered by the user
            $lastUsername = $authenticationUtils->getLastUsername();

            return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
        } else if (empty($user)) {
            // If not web user, just throw an exception
            throw new BadCredentialsException();
        }

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


        if ($client_type === "web") {
            $response = new RedirectResponse("/client/index.php");
            $response->headers->setCookie(
                Cookie::create("refresh_token", $refreshToken)
                    ->withHttpOnly()
                    ->withSecure()
                    ->withSameSite(Cookie::SAMESITE_STRICT)
            );
        }

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
