<?php

declare(strict_types=1);

namespace App\Tracking;

use App\Entity\Click;
use App\Entity\Link;
use App\Link\AcceptLanguageMatcher;
use App\Repository\LinkRepository;
use App\Settings\Settings;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Records clicks after the redirect has been sent: kernel.terminate runs after
 * fastcgi_finish_request() under PHP-FPM, so tracking adds no latency and needs
 * no queue worker.
 */
#[AsEventListener(event: KernelEvents::TERMINATE)]
final class ClickRecorder
{
    private const string ATTRIBUTE = '_emsl_click_link';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LinkRepository $links,
        private readonly Settings $settings,
        private readonly VisitorHasher $visitorHasher,
        private readonly UserAgentInspector $userAgents,
        private readonly GeoIpLookup $geoIp,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function schedule(Request $request, Link $link): void
    {
        if (!$this->settings->getBool(Settings::TRACKING) || !$request->isMethod('GET') || self::isPrefetch($request)) {
            return;
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

        $click = new Click($link, $at, $this->visitorHasher->hash($ip, $userAgent, $at))
            ->setIpPrefix(IpAnonymizer::anonymize($ip))
            ->setReferrerHost(self::referrerHost($request))
            ->setDeviceType($agent['device'])
            ->setBrowser($agent['browser'])
            ->setOs($agent['os'])
            ->setLanguage(AcceptLanguageMatcher::primaryLanguage($request->headers->get('Accept-Language')))
            ->setBot($agent['bot']);

        if (!$agent['bot'] && $this->settings->getBool(Settings::TRACK_COUNTRY)) {
            $click->setCountryCode($this->geoIp->country($ip));
        }

        $this->em->wrapInTransaction(function () use ($click, $link, $at): void {
            $this->em->persist($click);
            $this->em->flush();
            if (!$click->isBot()) {
                $this->links->incrementClicks($link, $at);
            }
        });

        return $click;
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
