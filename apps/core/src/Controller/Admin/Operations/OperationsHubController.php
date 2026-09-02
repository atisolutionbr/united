<?php

namespace App\Controller\Admin\Operations;

use App\Repository\Operations\AlertRepository;
use App\Repository\Operations\PipelineRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/operations')]
#[IsGranted('ROLE_ADMIN')]
class OperationsHubController extends AbstractController
{
    public function __construct(
        private readonly PipelineRepository $pipelineRepository,
        private readonly AlertRepository $alertRepository,
    ) {}

    #[Route('', name: 'app_admin_operations_hub', methods: ['GET'])]
    public function index(): Response
    {
        $stats = [
            'pipelines_active' => count($this->pipelineRepository->findActive()),
            'alerts_critical' => $this->alertRepository->countCritical(),
        ];

        return $this->render('admin/operations/hub.html.twig', [
            'stats' => $stats,
        ]);
    }
}
