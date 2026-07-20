<?php

namespace App\Security;

use App\Repository\UserRepository;
use ParagonIE\Paseto\Exception\PasetoException;
use ParagonIE\Paseto\Keys\Base\AsymmetricPublicKey;
use ParagonIE\Paseto\Parser;
use ParagonIE\Paseto\ProtocolCollection;
use ParagonIE\Paseto\Rules\IdentifiedBy;
use ParagonIE\Paseto\Rules\ValidAt;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * @see https://symfony.com/doc/current/security/custom_authenticator.html
 */
class PasetoAuthenticator extends AbstractAuthenticator
{
    private string $publicKey;

    public function __construct(
        string $publicKeyPath,
        private UserRepository $userRepository,
        private LoggerInterface $logger
    ) {
        $this->publicKey = file_get_contents($publicKeyPath);
    }

    /**
     * Called on every request to decide if this authenticator should be
     * used for the request. Returning `false` will cause this authenticator
     * to be skipped.
     */
    public function supports(Request $request): ?bool
    {
        return $request->headers->has('Authorization') && \sizeof(explode("Bearer", $request->headers->get("Authorization"), 2)) === 2;
    }

    public function authenticate(Request $request): Passport
    {
        $token = trim(explode("Bearer", $request->headers->get("Authorization"), 2)[1]);

        $public = AsymmetricPublicKey::importPem($this->publicKey);

        try {
            $paseto = Parser::getPublic($public, ProtocolCollection::v4())
                ->addRule(new ValidAt())
                ->addRule(new IdentifiedBy("access-token"))
                ->parse($token);
        } catch (PasetoException $ex) {
            $this->logger->info("Invalid token", ["token" => $token, "exception" => $ex]);
            throw new BadCredentialsException();
        }

        return new SelfValidatingPassport(
            new UserBadge($paseto->getSubject(), fn(string $uuid): ?UserInterface => $this->userRepository->findOneBy(["user_id" => $uuid])),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        // on success, let the request continue
        return null;
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $data = [
            // you may want to customize or obfuscate the message first
            'message' => strtr($exception->getMessageKey(), $exception->getMessageData()),

            // or to translate this message
            // $this->translator->trans($exception->getMessageKey(), $exception->getMessageData())
        ];

        return new JsonResponse($data, Response::HTTP_UNAUTHORIZED);
    }

    // public function start(Request $request, ?AuthenticationException $authException = null): Response
    // {
    //     /*
    //      * If you would like this class to control what happens when an anonymous user accesses a
    //      * protected page (e.g. redirect to /login), uncomment this method and make this class
    //      * implement Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface.
    //      *
    //      * For more details, see https://symfony.com/doc/current/security/experimental_authenticators.html#configuring-the-authentication-entry-point
    //      */
    // }
}
