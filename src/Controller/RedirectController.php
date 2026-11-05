<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\HostRegistry;
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
        $domain = $this->hosts->resolveDomain($request->getHost());
        if (null === $domain || !$domain->isActive()) {
            return $this->page('redirect/unknown_domain.html.twig', Response::HTTP_NOT_FOUND);
        }

        $path = trim(rawurldecode($request->getPathInfo()), '/');
        if ('' === $path) {
            return null !== $domain->getRootRedirectUrl()
                ? $this->redirectTo($domain->getRootRedirectUrl(), Response::HTTP_FOUND)
                : $this->page('redirect/root.html.twig', Response::HTTP_OK, ['domain' => $domain]);
        }

        $link = str_contains($path, '/') ? null : $this->links->findForRedirect($domain, $path);
        if (null === $link) {
            $fallback = $domain->getNotFoundUrl();
            if (null === $fallback) {
                return $this->page('redirect/not_found.html.twig', Response::HTTP_NOT_FOUND, ['domain' => $domain]);
            }
            if ($domain->isForwardUnknownPaths()) {
                $fallback = rtrim($fallback, '/').'/'.ltrim($request->getPathInfo(), '/');
            }

            return $this->redirectTo($fallback, Response::HTTP_FOUND);
        }

        if (!$link->isActive() || $link->isExpired()) {
            return $this->page('redirect/gone.html.twig', Response::HTTP_GONE, ['domain' => $domain]);
        }

        $this->clickRecorder->schedule($request, $link);

        return $this->redirectTo(self::targetFor($link, $request), $link->getRedirectType());
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
