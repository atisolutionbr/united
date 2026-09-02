<?php

namespace App\Controller\Admin\Operations;

use App\Repository\Governance\AuditLogRepository;
use App\Repository\Operations\PipelineExecutionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/operations/logs')]
#[IsGranted('ROLE_ADMIN')]
class LogsController extends AbstractController
{
    public function __construct(
        private readonly AuditLogRepository $auditLogRepository,
        private readonly PipelineExecutionRepository $executionRepository,
    ) {}

    #[Route('', name: 'app_admin_operations_logs', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = 30;

        $auditLogs = $this->auditLogRepository->findRecent($limit);
        $pipelineLogs = $this->executionRepository->findRecent(20);

        return $this->render('admin/operations/logs.html.twig', [
            'auditLogs' => $auditLogs,
            'pipelineLogs' => $pipelineLogs,
            'page' => $page,
        ]);
    }
}
