<?php

namespace App\Controller\Admin\Governance;

use App\Repository\AI\AiInteractionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/governance/ai')]
#[IsGranted('ROLE_ADMIN')]
class AiGovernanceController extends AbstractController
{
    public function __construct(
        private readonly AiInteractionRepository $interactionRepository,
    ) {}

    #[Route('', name: 'app_admin_governance_ai', methods: ['GET'])]
    public function index(): Response
    {
        $byProvider = $this->interactionRepository->countByProvider();
        $tokens = $this->interactionRepository->getTotalTokens();
        $externalToday = $this->interactionRepository->countExternalToday();
        $recent = $this->interactionRepository->findRecent(100);

        $externalInteractions = array_filter($recent, fn($i) => $i->isExternalProvider());
        $localInteractions = array_filter($recent, fn($i) => !$i->isExternalProvider());

        return $this->render('admin/governance/ai.html.twig', [
            'byProvider' => $byProvider,
            'totalTokens' => $tokens,
            'externalToday' => $externalToday,
            'recentInteractions' => $recent,
            'externalCount' => count($externalInteractions),
            'localCount' => count($localInteractions),
        ]);
    }
}
