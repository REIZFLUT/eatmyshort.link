<?php

declare(strict_types=1);

namespace App\Security;

use App\Repository\ApiTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Resolves "Authorization: Bearer emsl_…" to its user.
 */
final readonly class ApiTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private ApiTokenRepository $tokens,
        private EntityManagerInterface $em,
    ) {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $token = $this->tokens->findOneByPlainToken($accessToken);
        if (null === $token || $token->isExpired() || !$token->getUser()->isActive()) {
            throw new BadCredentialsException('Invalid API token.');
        }

        $lastUsed = $token->getLastUsedAt();
        // Writing on every request would turn each API read into a write transaction.
        // One write per minute is precise enough for the token's "last used" display.
        if (null === $lastUsed || $lastUsed < new \DateTimeImmutable('-60 seconds')) {
            $token->markUsed();
            $this->em->flush();
        }
        $user = $token->getUser();

        return new UserBadge($user->getUserIdentifier(), static fn () => $user);
    }
}
