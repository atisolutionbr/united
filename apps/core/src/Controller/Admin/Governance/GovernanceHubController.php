<?php

namespace App\Controller\Admin\Governance;

use App\Repository\Governance\AuditLogRepository;
use App\Repository\Governance\CostRecordRepository;
use App\Repository\Governance\DataGovernanceRecordRepository;
use App\Repository\AI\AiInteractionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/governance')]
#[IsGranted('ROLE_ADMIN')]
class GovernanceHubController extends AbstractController
{
    public function __construct(
        private readonly DataGovernanceRecordRepository $dataRecordRepository,
        private readonly AuditLogRepository $auditLogRepository,
        private readonly CostRecordRepository $costRecordRepository,
        private readonly AiInteractionRepository $aiInteractionRepository,
    ) {}

    #[Route('', name: 'app_admin_governance_hub', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/governance/hub.html.twig', [
            'stats' => [
                'data_records'     => count($this->dataRecordRepository->findActive()),
                'audit_logs'       => count($this->auditLogRepository->findRecent(500)),
                'cost_records'     => count($this->costRecordRepository->findAll()),
                'ai_interactions'  => count($this->aiInteractionRepository->findRecent(500)),
            ],
        ]);
    }
}
