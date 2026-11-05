<?php

declare(strict_types=1);

namespace App\Mail;

use App\Domain\HostRegistry;
use App\Entity\ResetToken;
use App\Entity\User;
use App\Settings\Settings;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Invitation and password reset links. The link is always built for the primary
 * admin host over https, independent of the host the request came in on.
 */
final readonly class AccountMailer
{
    public function __construct(
        private EntityManagerInterface $em,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urls,
        private HostRegistry $hosts,
        private Settings $settings,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
        #[Autowire(env: 'MAILER_FROM')]
        private string $from,
    ) {
    }

    /**
     * Issues a fresh one-time link (older ones become invalid) and tries to mail it.
     *
     * @return array{url: string, sent: bool}
     */
    public function sendAccountLink(User $user, string $purpose): array
    {
        $this->em->getRepository(ResetToken::class)->removeAllFor($user);
        [$token, $publicToken] = ResetToken::issue(
            $user,
            $purpose,
            new \DateInterval(ResetToken::PURPOSE_INVITE === $purpose ? 'P7D' : 'PT2H'),
        );
        $this->em->persist($token);
        $this->em->flush();

        $url = $this->absoluteUrl(ResetToken::PURPOSE_INVITE === $purpose ? 'app_invite' : 'app_reset', ['token' => $publicToken]);
        $locale = $user->getLocale() ?? 'de';

        $email = new TemplatedEmail()
            ->from(Address::create($this->from))
            ->to(new Address($user->getEmail(), $user->getDisplayName()))
            ->subject($this->translator->trans('mail.'.$purpose.'.subject', ['%title%' => $this->settings->getString(Settings::TITLE)], locale: $locale))
            ->htmlTemplate('email/account_link.html.twig')
            ->textTemplate('email/account_link.txt.twig')
            ->locale($locale)
            ->context(['user' => $user, 'url' => $url, 'purpose' => $purpose, 'expires_at' => $token->getExpiresAt()]);

        try {
            $this->mailer->send($email);
            $sent = true;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Account mail could not be sent: {message}', ['message' => $e->getMessage()]);
            $sent = false;
        }

        return ['url' => $url, 'sent' => $sent];
    }

    /** @param array<string, string> $parameters */
    private function absoluteUrl(string $route, array $parameters): string
    {
        // Change the shared context in place and restore it: replacing the object would
        // detach the router from the RouterListener's per-request updates.
        $context = $this->urls->getContext();
        [$host, $scheme, $httpsPort] = [$context->getHost(), $context->getScheme(), $context->getHttpsPort()];
        $context->setHost($this->hosts->getPrimaryAdminHost())->setScheme('https')->setHttpsPort(443);
        try {
            return $this->urls->generate($route, $parameters, UrlGeneratorInterface::ABSOLUTE_URL);
        } finally {
            $context->setHost($host)->setScheme($scheme)->setHttpsPort($httpsPort);
        }
    }
}
