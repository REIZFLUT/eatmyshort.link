<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\HostRegistry;
use App\Entity\Domain;
use App\Entity\Link;
use App\Link\AcceptLanguageMatcher;
use App\Repository\LinkRepository;
use App\Tracking\ClickRecorder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves every request on a short-link domain (see ShortLinkHostListener).
 */
final class RedirectController extends AbstractController
{
    public function __construct(
        private readonly HostRegistry $hosts,
        private readonly LinkRepository $links,
        private readonly ClickRecorder $clickRecorder,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $path = trim(rawurldecode($request->getPathInfo()), '/');
        if ('' === $path) {
            $domain = $this->activeDomain($request);
            if (null === $domain) {
                return $this->unknownDomain();
            }

            return null !== $domain->getRootRedirectUrl()
                ? $this->redirectTo($domain->getRootRedirectUrl(), Response::HTTP_FOUND)
                : $this->page('redirect/root.html.twig', Response::HTTP_OK, ['domain' => $domain]);
        }

        $link = str_contains($path, '/') ? null : $this->links->findForRedirectByHost($request->getHost(), $path);
        if (null !== $link) {
            $domain = $link->getDomain();
            // The link query intentionally does not filter on the domain flag: a link that sits
            // on a deactivated domain must look like an unknown host, not like a deleted link.
            if (null === $domain || !$domain->isActive()) {
                return $this->unknownDomain();
            }
            if (!$link->isActive() || $link->isExpired()) {
                return $this->page('redirect/gone.html.twig', Response::HTTP_GONE, ['domain' => $domain]);
            }

            $this->clickRecorder->schedule($request, $link);

            return $this->redirectTo(self::targetFor($link, $request), $link->getRedirectType());
        }

        $domain = $this->activeDomain($request);
        if (null === $domain) {
            return $this->unknownDomain();
        }

        $fallback = $domain->getNotFoundUrl();
        if (null === $fallback) {
            return $this->page('redirect/not_found.html.twig', Response::HTTP_NOT_FOUND, ['domain' => $domain]);
        }
        if ($domain->isForwardUnknownPaths()) {
            $fallback = rtrim($fallback, '/').'/'.ltrim($request->getPathInfo(), '/');
        }

        return $this->redirectTo($fallback, Response::HTTP_FOUND);
    }

    /** Resolved short-link domain, but only when it is active. */
    private function activeDomain(Request $request): ?Domain
    {
        $domain = $this->hosts->resolveDomain($request->getHost());

        return null !== $domain && $domain->isActive() ? $domain : null;
    }

    private function unknownDomain(): Response
    {
        return $this->page('redirect/unknown_domain.html.twig', Response::HTTP_NOT_FOUND);
    }

    public static function targetFor(Link $link, Request $request): string
    {
        $localised = $link->getLocalisedTargets();
        if ([] === $localised) {
            return $link->getTargetUrl();
        }
        $language = AcceptLanguageMatcher::bestMatch($request->headers->get('Accept-Language'), array_keys($localised));

        return null === $language ? $link->getTargetUrl() : $localised[$language];
    }

    private function redirectTo(string $url, int $status): RedirectResponse
    {
        $response = new RedirectResponse($url, $status);
        $response->headers->set('X-Robots-Tag', 'noindex');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        if (\in_array($status, [Response::HTTP_MOVED_PERMANENTLY, Response::HTTP_PERMANENTLY_REDIRECT], true)) {
            // Browsers cache permanent redirects anyway; keep it bounded so changes still arrive.
            $response->setPublic();
            $response->setMaxAge(3600);
            // The target depends on Accept-Language; without this a proxy/CDN would serve
            // every visitor the language of whichever request populated the cache first.
            $response->setVary('Accept-Language');
        } else {
            // Temporary redirects must reach us every time, or clicks go uncounted.
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    /** @param array<string, mixed> $context */
    private function page(string $template, int $status, array $context = []): Response
    {
        $response = $this->render($template, $context, new Response(status: $status));
        $response->headers->set('X-Robots-Tag', 'noindex');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
