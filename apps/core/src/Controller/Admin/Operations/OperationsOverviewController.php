<?php

namespace App\Controller\Admin\Operations;

use App\Repository\AI\AiInteractionRepository;
use App\Repository\Operations\AlertRepository;
use App\Repository\Operations\PipelineExecutionRepository;
use App\Repository\Operations\PipelineRepository;
use App\Service\Observability\HealthCheckService;
use App\Service\Operations\AlertService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/operations')]
#[IsGranted('ROLE_ADMIN')]
class OperationsOverviewController extends AbstractController
{
    public function __construct(
        private readonly PipelineRepository $pipelineRepository,
        private readonly PipelineExecutionRepository $executionRepository,
        private readonly AlertRepository $alertRepository,
        private readonly AiInteractionRepository $aiInteractionRepository,
        private readonly HealthCheckService $healthCheckService,
    ) {}

    #[Route('/overview', name: 'app_admin_operations_overview', methods: ['GET'])]
    public function index(): Response
    {
        $health = $this->healthCheckService->checkAll();

        $summary = [
            'pipelines_total' => count($this->pipelineRepository->findAll()),
            'pipelines_active' => count($this->pipelineRepository->findActive()),
            'failed_today' => $this->executionRepository->countFailedToday(),
            'alerts_critical' => $this->alertRepository->countCritical(),
            'alerts_active' => $this->alertRepository->countActive(),
        ];

        $recentExecutions = $this->executionRepository->findRecent(10);
        $recentAlerts = $this->alertRepository->findRecent(5);

        // AI usage today
        $aiStats = [
            'total_requests' => $this->aiInteractionRepository->countByProvider(),
            'external_today' => $this->aiInteractionRepository->countExternalToday(),
        ];

        return $this->render('admin/operations/overview.html.twig', [
            'health' => $health,
            'summary' => $summary,
            'recentExecutions' => $recentExecutions,
            'recentAlerts' => $recentAlerts,
            'aiStats' => $aiStats,
            'overallHealth' => $this->healthCheckService->getOverallStatus($health),
        ]);
    }
}
