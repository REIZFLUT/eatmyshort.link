<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Unauthenticated liveness probe for monitoring. It deliberately reveals nothing
 * beyond "ok"/"error", so it can stay reachable without a login.
 */
final class HealthController extends AbstractController
{
    #[Route('/healthz', name: 'app_health', methods: ['GET'])]
    public function __invoke(Connection $connection): JsonResponse
    {
        try {
            $connection->executeQuery('SELECT 1')->fetchOne();
        } catch (\Throwable) {
            return $this->json(['status' => 'error'], JsonResponse::HTTP_SERVICE_UNAVAILABLE, ['Cache-Control' => 'no-store']);
        }

        return $this->json([
            'status' => 'ok',
            'time' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DateTimeInterface::RFC3339),
        ], JsonResponse::HTTP_OK, ['Cache-Control' => 'no-store']);
    }
}
