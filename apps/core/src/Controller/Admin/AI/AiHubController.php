<?php

namespace App\Controller\Admin\AI;

use App\Repository\AI\AiAgentRepository;
use App\Repository\AI\AiContextRepository;
use App\Repository\AI\AiInteractionRepository;
use App\Repository\AI\AiModelRepository;
use App\Repository\AI\AiPromptRepository;
use App\Service\AI\OllamaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/ai')]
#[IsGranted('ROLE_ADMIN')]
class AiHubController extends AbstractController
{
    public function __construct(
        private readonly AiModelRepository $modelRepository,
        private readonly AiAgentRepository $agentRepository,
        private readonly AiContextRepository $contextRepository,
        private readonly AiPromptRepository $promptRepository,
        private readonly AiInteractionRepository $interactionRepository,
        private readonly OllamaService $ollamaService,
    ) {}

    #[Route('', name: 'app_admin_ai_hub', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/ai/hub.html.twig', [
            'stats' => [
                'models'       => count($this->modelRepository->findAll()),
                'models_active'=> count($this->modelRepository->findActive()),
                'agents'       => count($this->agentRepository->findAll()),
                'contexts'     => count($this->contextRepository->findAll()),
                'prompts'      => count($this->promptRepository->findAll()),
                'interactions' => count($this->interactionRepository->findRecent(500)),
            ],
            'ollama_online' => $this->ollamaService->isAvailable(),
        ]);
    }
}
