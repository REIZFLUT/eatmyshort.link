<?php

declare(strict_types=1);

namespace App\Event\Redirect;

use App\Entity\Domain;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A request hit a short domain without a matching link.
 *
 * A plugin that sets a target turns an unknown path into a redirect of its own (typo
 * correction, catch-all, campaign landing page). Without a target the app keeps its
 * configured behaviour: forward unknown paths, otherwise 404.
 *
 * @api
 *
 * @since 1.0
 */
final class RedirectUnresolvedEvent
{
    private ?string $target = null;

    private int $statusCode = Response::HTTP_FOUND;

    public function __construct(
        private readonly Request $request,
        private readonly ?Domain $domain,
    ) {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    /** Null when the host is not a known short domain at all. */
    public function getDomain(): ?Domain
    {
        return $this->domain;
    }

    public function hasTarget(): bool
    {
        return null !== $this->target;
    }

    public function getTarget(): ?string
    {
        return $this->target;
    }

    public function setTarget(string $target, int $statusCode = Response::HTTP_FOUND): void
    {
        $this->target = $target;
        $this->statusCode = $statusCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
