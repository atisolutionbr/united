<?php

namespace App\Controller\Admin\Governance;

use App\Service\Governance\CostTrackingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/governance/costs')]
#[IsGranted('ROLE_ADMIN')]
class CostController extends AbstractController
{
    public function __construct(
        private readonly CostTrackingService $costTrackingService,
    ) {}

    #[Route('', name: 'app_admin_governance_costs', methods: ['GET'])]
    public function index(): Response
    {
        $data = $this->costTrackingService->getDashboardData();

        return $this->render('admin/governance/costs.html.twig', [
            'totalThisMonthUsd' => $data['total_this_month_usd'],
            'byService' => $data['by_service'],
            'dailySeries' => $data['daily_series'],
        ]);
    }
}
