<?php

namespace App\Controller\Admin\Operations;

use App\Repository\AI\AiInteractionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/operations/ai-metrics')]
#[IsGranted('ROLE_ADMIN')]
class AiMetricsController extends AbstractController
{
    public function __construct(
        private readonly AiInteractionRepository $interactionRepository,
    ) {}

    #[Route('', name: 'app_admin_operations_ai_metrics', methods: ['GET'])]
    public function index(): Response
    {
        $byProvider = $this->interactionRepository->countByProvider();
        $tokens = $this->interactionRepository->getTotalTokens();
        $totalRequests = array_sum($byProvider);
        $externalToday = $this->interactionRepository->countExternalToday();

        // Estimate cost based on interaction data
        $costUsd = 0.0;
        $recentInteractions = $this->interactionRepository->findRecent(500);
        foreach ($recentInteractions as $interaction) {
            $costUsd += (float)$interaction->getEstimatedCostUsd();
        }

        $recent = $this->interactionRepository->findRecent(50);

        return $this->render('admin/operations/ai_metrics.html.twig', [
            'byProvider' => $byProvider,
            'totalRequests' => $totalRequests,
            'externalToday' => $externalToday,
            'totalTokens' => $tokens,
            'estimatedCostUsd' => round($costUsd, 4),
            'recentInteractions' => $recent,
        ]);
    }
}
