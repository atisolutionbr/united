<?php

namespace App\Controller\Admin\Operations;

use App\Repository\Operations\PipelineExecutionRepository;
use App\Service\Operations\PipelineService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/operations/executions')]
#[IsGranted('ROLE_ADMIN')]
class ExecutionController extends AbstractController
{
    public function __construct(
        private readonly PipelineExecutionRepository $executionRepository,
        private readonly PipelineService $pipelineService,
    ) {}

    #[Route('', name: 'app_admin_operations_executions', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = 25;

        $executions = $this->executionRepository->findRecent($limit * $page);
        $paged = array_slice($executions, ($page - 1) * $limit, $limit);

        return $this->render('admin/operations/executions/index.html.twig', [
            'executions' => $paged,
            'page' => $page,
            'total' => count($executions),
            'limit' => $limit,
        ]);
    }

    #[Route('/{id}', name: 'app_admin_operations_execution_detail', methods: ['GET'])]
    public function detail(int $id): Response
    {
        $execution = $this->executionRepository->find($id) ?? throw $this->createNotFoundException();
        // Sync status with Kestra if still running
        if (!$execution->isFinished()) {
            $this->pipelineService->syncExecutionStatus($execution);
        }
        return $this->render('admin/operations/executions/detail.html.twig', ['execution' => $execution]);
    }
}
