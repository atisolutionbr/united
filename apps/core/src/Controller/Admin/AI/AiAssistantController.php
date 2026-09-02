<?php

namespace App\Controller\Admin\AI;

use App\Entity\AI\AiAgent;
use App\Entity\AI\AiContext;
use App\Entity\AI\AiModel;
use App\Repository\AI\AiAgentRepository;
use App\Repository\AI\AiContextRepository;
use App\Repository\AI\AiModelRepository;
use App\Service\AI\AiAgentService;
use App\Service\AI\AiGovernanceService;
use App\Service\AI\AiProviderService;
use App\Service\AI\OllamaService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/ai')]
#[IsGranted('ROLE_ADMIN')]
class AiAssistantController extends AbstractController
{
    public function __construct(
        private readonly AiProviderService $providerService,
        private readonly AiAgentService $agentService,
        private readonly AiGovernanceService $governanceService,
        private readonly AiModelRepository $modelRepository,
        private readonly AiContextRepository $contextRepository,
        private readonly AiAgentRepository $agentRepository,
        private readonly OllamaService $ollamaService,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/assistant', name: 'app_admin_ai_assistant', methods: ['GET'])]
    public function assistant(): Response
    {
        $models = $this->modelRepository->findActive();
        $contexts = $this->contextRepository->findActive();
        $agents = $this->agentRepository->findActive();
        $ollamaAvailable = $this->ollamaService->isAvailable();
        $stats = $this->governanceService->getStats();

        return $this->render('admin/ai/assistant.html.twig', [
            'models' => $models,
            'contexts' => $contexts,
            'agents' => $agents,
            'ollama_available' => $ollamaAvailable,
            'stats' => $stats,
        ]);
    }

    #[Route('/chat', name: 'app_admin_ai_chat', methods: ['POST'])]
    public function chat(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $message = trim($data['message'] ?? '');
        $modelId = (int) ($data['model_id'] ?? 0);
        $contextId = (int) ($data['context_id'] ?? 0);
        $agentId = (int) ($data['agent_id'] ?? 0);

        if (empty($message)) {
            return $this->json(['success' => false, 'error' => 'Mensagem não pode ser vazia.'], 400);
        }

        $user = $this->getUser();
        $userIdentifier = $user?->getUserIdentifier();

        if ($agentId > 0) {
            $agent = $this->em->find(AiAgent::class, $agentId);
            if ($agent === null) {
                return $this->json(['success' => false, 'error' => 'Agente não encontrado.'], 404);
            }
            $result = $this->agentService->run($agent, $message, $userIdentifier);
        } else {
            $model = $modelId > 0
                ? $this->em->find(AiModel::class, $modelId)
                : $this->providerService->resolveDefaultModel();

            if ($model === null) {
                return $this->json(['success' => false, 'error' => 'Nenhum modelo de IA configurado. Configure em IA > Modelos.'], 503);
            }

            $context = $contextId > 0 ? $this->em->find(AiContext::class, $contextId) : null;

            $result = $this->providerService->dispatch(
                prompt: $message,
                model: $model,
                context: $context,
                userIdentifier: $userIdentifier,
            );
        }

        return $this->json($result);
    }

    #[Route('/insights', name: 'app_admin_ai_insights', methods: ['GET'])]
    public function insights(): Response
    {
        $stats = $this->governanceService->getStats();
        $models = $this->modelRepository->findActive();
        $ollamaAvailable = $this->ollamaService->isAvailable();

        return $this->render('admin/ai/insights.html.twig', [
            'stats' => $stats,
            'models' => $models,
            'ollama_available' => $ollamaAvailable,
        ]);
    }

    #[Route('/logs', name: 'app_admin_ai_logs', methods: ['GET'])]
    public function logs(Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $interactions = $this->em->getRepository(\App\Entity\AI\AiInteraction::class)
            ->createQueryBuilder('i')
            ->orderBy('i.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()->getResult();

        $total = (int) $this->em->getRepository(\App\Entity\AI\AiInteraction::class)
            ->createQueryBuilder('i')->select('COUNT(i.id)')->getQuery()->getSingleScalarResult();

        $stats = $this->governanceService->getStats();

        return $this->render('admin/ai/logs.html.twig', [
            'interactions' => $interactions,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'total_pages' => (int) ceil($total / $limit),
            'stats' => $stats,
        ]);
    }

    #[Route('/settings', name: 'app_admin_ai_settings', methods: ['GET'])]
    public function settings(): Response
    {
        $models = $this->modelRepository->findActive();
        $ollamaAvailable = $this->ollamaService->isAvailable();
        $ollamaModels = $ollamaAvailable ? $this->ollamaService->listModels() : [];
        $stats = $this->governanceService->getStats();

        return $this->render('admin/ai/settings.html.twig', [
            'models' => $models,
            'ollama_available' => $ollamaAvailable,
            'ollama_models' => $ollamaModels,
            'stats' => $stats,
        ]);
    }
}
