<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class HealthController
{
    #[Route('/health', name: 'app_health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'service' => 'plataforma360-core',
            'timezone' => date_default_timezone_get(),
            'checkedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ]);
    }
}