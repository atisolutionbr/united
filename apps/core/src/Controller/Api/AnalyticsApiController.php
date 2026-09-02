<?php

namespace App\Controller\Api;

use App\Service\Warehouse\AnalyticsService;
use App\Repository\Warehouse\AnalyticModelRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/analytics')]
final class AnalyticsApiController extends AbstractController
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
    ) {}

    #[Route('/turismo/agencias', name: 'api_analytics_turismo_agencias', methods: ['GET'])]
    public function agencias(Request $request): JsonResponse
    {
        try {
            $indicators = $this->analyticsService->getExecutiveIndicators();
            $ranking = $this->analyticsService->getRankingByEstado();

            return $this->json([
                'status' => 'ok',
                'data' => [
                    'indicators' => $indicators,
                    'ranking_by_estado' => $ranking,
                ],
                'meta' => [
                    'generated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339),
                    'source' => 'warehouse.dw_turismo_agencias',
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->json(['status' => 'error', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/turismo/municipios', name: 'api_analytics_turismo_municipios', methods: ['GET'])]
    public function municipios(): JsonResponse
    {
        try {
            $ranking = $this->analyticsService->getRankingByEstado();

            return $this->json([
                'status' => 'ok',
                'data' => $ranking,
                'meta' => [
                    'generated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339),
                    'source' => 'warehouse.dw_turismo_agencias',
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->json(['status' => 'error', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/indicadores', name: 'api_analytics_indicadores', methods: ['GET'])]
    public function indicadores(): JsonResponse
    {
        try {
            $indicators = $this->analyticsService->getExecutiveIndicators();

            return $this->json([
                'status' => 'ok',
                'data' => $indicators,
                'meta' => [
                    'generated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339),
                    'layer' => 'warehouse',
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->json(['status' => 'error', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/ranking', name: 'api_analytics_ranking', methods: ['GET'])]
    public function ranking(): JsonResponse
    {
        try {
            $ranking = $this->analyticsService->getRankingByEstado();
            $series = $this->analyticsService->getSeriesTemporalMensal();

            return $this->json([
                'status' => 'ok',
                'data' => [
                    'ranking_estados' => $ranking,
                    'series_mensal' => $series,
                ],
                'meta' => [
                    'generated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->json(['status' => 'error', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/lineage', name: 'api_analytics_lineage', methods: ['GET'])]
    public function lineage(): JsonResponse
    {
        try {
            $lineage = $this->analyticsService->getDataLineage();

            return $this->json([
                'status' => 'ok',
                'data' => $lineage,
                'meta' => [
                    'generated_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->json(['status' => 'error', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/models', name: 'api_analytics_models', methods: ['GET'])]
    public function models(AnalyticModelRepository $modelRepository): JsonResponse
    {
        $models = $modelRepository->findActive();
        $data = array_map(fn($m) => [
            'id' => $m->getId(),
            'name' => $m->getName(),
            'slug' => $m->getSlug(),
            'source_table' => $m->getSourceTable(),
            'target_table' => $m->getTargetTable(),
            'dimensions' => $m->getDimensions(),
            'metrics' => $m->getMetrics(),
            'last_refresh_status' => $m->getLastRefreshStatus(),
            'row_count' => $m->getRowCount(),
            'last_refreshed_at' => $m->getLastRefreshedAt()?->format(\DateTimeInterface::RFC3339),
        ], $models);

        return $this->json([
            'status' => 'ok',
            'data' => $data,
            'meta' => ['count' => count($data)],
        ]);
    }
}
