<?php

declare(strict_types=1);

namespace App\Event\Api;

use Symfony\Component\HttpFoundation\Request;

/**
 * An API response payload was built and is about to be serialised to JSON.
 *
 * Add fields, never remove them: clients of a published API rely on what is there.
 * Callers may not dispatch this event for error responses - it is for successful,
 * resource-shaped payloads only.
 *
 * @api
 *
 * @since 1.0
 */
final class ApiResponseEvent
{
    /**
     * @param string               $operation e.g. "links.list", "links.show"
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private readonly string $operation,
        private readonly Request $request,
        private array $payload,
    ) {
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    /** @return array<string, mixed> */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /** @param array<string, mixed> $payload */
    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    public function setField(string $key, mixed $value): void
    {
        $this->payload[$key] = $value;
    }

    public function hasField(string $key): bool
    {
        return \array_key_exists($key, $this->payload);
    }
}
