<?php

namespace App\Controller\Admin\Governance;

use App\Repository\Governance\AuditLogRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/governance/audit')]
#[IsGranted('ROLE_ADMIN')]
class AuditController extends AbstractController
{
    public function __construct(
        private readonly AuditLogRepository $auditLogRepository,
    ) {}

    #[Route('', name: 'app_admin_governance_audit', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, (int)$request->query->get('page', 1));
        $limit = 30;
        $action = $request->query->get('action');
        $user = $request->query->get('user');

        if ($action) {
            $logs = $this->auditLogRepository->findByAction($action);
        } elseif ($user) {
            $logs = $this->auditLogRepository->findByUser($user, 100);
        } else {
            $logs = $this->auditLogRepository->findRecent(200);
        }

        $total = count($logs);
        $paged = array_slice($logs, ($page - 1) * $limit, $limit);

        return $this->render('admin/governance/audit.html.twig', [
            'logs' => $paged,
            'page' => $page,
            'total' => $total,
            'limit' => $limit,
            'actionFilter' => $action,
            'userFilter' => $user,
        ]);
    }
}
