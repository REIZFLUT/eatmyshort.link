<?php

declare(strict_types=1);

namespace App\Tracking;

use App\Entity\Click;
use App\Entity\Link;
use App\Link\AcceptLanguageMatcher;
use App\Settings\Settings;
use Doctrine\DBAL\ParameterType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * Records clicks after the redirect has been sent: kernel.terminate runs after
 * fastcgi_finish_request() under PHP-FPM, so tracking adds no latency and needs
 * no queue worker.
 *
 * Clicks are written through DBAL instead of the ORM: a click is an append-only row
 * and the counter update is a single UPDATE, so the identity map and unit of work
 * would only add overhead on the hot path.
 */
#[AsEventListener(event: KernelEvents::TERMINATE)]
final class ClickRecorder
{
    private const string ATTRIBUTE = '_emsl_click_link';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Settings $settings,
        private readonly VisitorHasher $visitorHasher,
        private readonly UserAgentInspector $userAgents,
        private readonly GeoIpLookup $geoIp,
        #[Autowire(service: 'limiter.redirect')]
        private readonly RateLimiterFactoryInterface $limiter,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function schedule(Request $request, Link $link): void
    {
        if (!$this->settings->getBool(Settings::TRACKING) || !$request->isMethod('GET') || self::isPrefetch($request)) {
            return;
        }

        // Rate-limit tracking per client so a script cannot grow the click table without bound.
        // Tracking must never break the redirect, so a limiter failure fails open.
        try {
            $client = $request->getClientIp() ?? 'unknown';
            if (!$this->limiter->create($client)->consume()->isAccepted()) {
                $this->logger->debug('Click tracking rate limit exceeded for {client}.', ['client' => $client]);

                return;
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Click tracking rate limiter failed: {message}', ['message' => $e->getMessage()]);
        }

        $request->attributes->set(self::ATTRIBUTE, $link->getId());
    }

    public function __invoke(TerminateEvent $event): void
    {
        $linkId = $event->getRequest()->attributes->get(self::ATTRIBUTE);
        if (!\is_int($linkId)) {
            return;
        }

        try {
            $this->record($event->getRequest(), $linkId, new \DateTimeImmutable());
        } catch (\Throwable $e) {
            $this->logger->error('Click could not be recorded: {message}', ['message' => $e->getMessage(), 'exception' => $e]);
        }
    }

    public function record(Request $request, int $linkId, \DateTimeImmutable $at): ?Click
    {
        $link = $this->em->find(Link::class, $linkId);
        if (null === $link) {
            return null;
        }

        $ip = $request->getClientIp();
        $userAgent = $request->headers->get('User-Agent');
        $agent = $this->userAgents->inspect($userAgent, array_map(
            static fn (array $values): string => (string) ($values[0] ?? ''),
            $request->headers->all(),
        ));

        if ($agent['bot']) {
            // Crawlers, link checkers and empty user agents are not visitors. Keeping them
            // out of the table keeps the statistics honest and the database small.
            return null;
        }

        $click = new Click($link, $at, $this->visitorHasher->hash($ip, $userAgent, $at))
            ->setIpPrefix(IpAnonymizer::anonymize($ip))
            ->setReferrerHost(self::referrerHost($request))
            ->setDeviceType($agent['device'])
            ->setBrowser($agent['browser'])
            ->setOs($agent['os'])
            ->setLanguage(AcceptLanguageMatcher::primaryLanguage($request->headers->get('Accept-Language')));

        if ($this->settings->getBool(Settings::TRACK_COUNTRY)) {
            $click->setCountryCode($this->geoIp->country($ip));
        }

        $this->insert($click, $linkId);

        return $click;
    }

    private function insert(Click $click, int $linkId): void
    {
        $connection = $this->em->getConnection();
        $connection->beginTransaction();

        try {
            $connection->executeStatement(
                'INSERT INTO click (clicked_at, clicked_day, visitor_hash, ip_prefix, country_code, referrer_host, device_type, browser, os, language, bot, link_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $click->getClickedAt()->format('Y-m-d H:i:s'),
                    $click->getClickedDay()->format('Y-m-d'),
                    $click->getVisitorHash(),
                    $click->getIpPrefix(),
                    $click->getCountryCode(),
                    $click->getReferrerHost(),
                    $click->getDeviceType(),
                    $click->getBrowser(),
                    $click->getOs(),
                    $click->getLanguage(),
                    $click->isBot(),
                    $linkId,
                ],
                [
                    ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING,
                    ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING,
                    ParameterType::STRING, ParameterType::STRING, ParameterType::BOOLEAN, ParameterType::INTEGER,
                ],
            );
            $connection->executeStatement(
                'UPDATE link SET click_count = click_count + 1, last_click_at = ? WHERE id = ?',
                [$click->getClickedAt()->format('Y-m-d H:i:s'), $linkId],
                [ParameterType::STRING, ParameterType::INTEGER],
            );
            $connection->commit();
        } catch (\Throwable $e) {
            $connection->rollBack();

            throw $e;
        }
    }

    private static function referrerHost(Request $request): ?string
    {
        $referrer = $request->headers->get('Referer');
        $host = null === $referrer ? null : parse_url($referrer, \PHP_URL_HOST);

        return \is_string($host) && '' !== $host ? strtolower($host) : null;
    }

    private static function isPrefetch(Request $request): bool
    {
        $purpose = strtolower((string) ($request->headers->get('Sec-Purpose') ?? $request->headers->get('Purpose')));

        return str_contains($purpose, 'prefetch') || str_contains($purpose, 'prerender');
    }
}
