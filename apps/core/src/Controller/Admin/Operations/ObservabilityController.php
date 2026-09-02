<?php

namespace App\Controller\Admin\Operations;

use App\Service\Observability\HealthCheckService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/operations/observability')]
#[IsGranted('ROLE_ADMIN')]
class ObservabilityController extends AbstractController
{
    public function __construct(
        private readonly HealthCheckService $healthCheckService,
    ) {}

    #[Route('', name: 'app_admin_operations_observability', methods: ['GET'])]
    public function index(): Response
    {
        $health = $this->healthCheckService->checkAll();
        $overallStatus = $this->healthCheckService->getOverallStatus($health);

        return $this->render('admin/operations/observability.html.twig', [
            'health' => $health,
            'overallStatus' => $overallStatus,
        ]);
    }
}
