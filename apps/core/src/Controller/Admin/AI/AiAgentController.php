<?php

namespace App\Controller\Admin\AI;

use App\Entity\AI\AiAgent;
use App\Repository\AI\AiAgentRepository;
use App\Repository\AI\AiContextRepository;
use App\Repository\AI\AiModelRepository;
use App\Repository\AI\AiPromptRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/ai/agents')]
#[IsGranted('ROLE_ADMIN')]
class AiAgentController extends AbstractController
{
    public function __construct(
        private readonly AiAgentRepository $agentRepository,
        private readonly AiModelRepository $modelRepository,
        private readonly AiContextRepository $contextRepository,
        private readonly AiPromptRepository $promptRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'app_admin_ai_agents', methods: ['GET'])]
    public function index(): Response
    {
        $agents = $this->em->getRepository(AiAgent::class)->findBy([], ['name' => 'ASC']);
        return $this->render('admin/ai/agents/index.html.twig', ['agents' => $agents]);
    }

    #[Route('/new', name: 'app_admin_ai_agents_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $agent = new AiAgent();
        if ($request->isMethod('POST')) {
            $this->hydrateAgent($agent, $request);
            $this->em->persist($agent);
            $this->em->flush();
            $this->addFlash('success', "Agente '{$agent->getName()}' criado.");
            return $this->redirectToRoute('app_admin_ai_agents');
        }
        return $this->render('admin/ai/agents/form.html.twig', [
            'agent' => $agent, 'mode' => 'new',
            'models' => $this->modelRepository->findActive(),
            'contexts' => $this->contextRepository->findActive(),
            'prompts' => $this->promptRepository->findActive(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_ai_agents_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $agent = $this->em->find(AiAgent::class, $id);
        if ($agent === null) { throw $this->createNotFoundException(); }
        if ($request->isMethod('POST')) {
            $this->hydrateAgent($agent, $request);
            $this->em->flush();
            $this->addFlash('success', "Agente '{$agent->getName()}' atualizado.");
            return $this->redirectToRoute('app_admin_ai_agents');
        }
        return $this->render('admin/ai/agents/form.html.twig', [
            'agent' => $agent, 'mode' => 'edit',
            'models' => $this->modelRepository->findActive(),
            'contexts' => $this->contextRepository->findActive(),
            'prompts' => $this->promptRepository->findActive(),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_ai_agents_delete', methods: ['POST'])]
    public function delete(int $id): Response
    {
        $agent = $this->em->find(AiAgent::class, $id);
        if ($agent === null) { throw $this->createNotFoundException(); }
        $name = $agent->getName();
        $this->em->remove($agent);
        $this->em->flush();
        $this->addFlash('success', "Agente '{$name}' removido.");
        return $this->redirectToRoute('app_admin_ai_agents');
    }

    private function hydrateAgent(AiAgent $agent, Request $request): void
    {
        $agent->setName(trim($request->request->get('name', '')));
        $agent->setSlug(trim($request->request->get('slug', '')));
        $agent->setDescription($request->request->get('description') ?: null);
        $agent->setAgentType($request->request->get('agent_type', AiAgent::TYPE_PUBLIC_DATA));

        $tools = array_filter(array_map('trim', explode("\n", $request->request->get('tools', ''))));
        $agent->setTools(array_values($tools));

        $modelId = (int) $request->request->get('default_model_id', 0);
        $agent->setDefaultModel($modelId > 0 ? $this->em->find(\App\Entity\AI\AiModel::class, $modelId) : null);

        $contextId = (int) $request->request->get('default_context_id', 0);
        $agent->setDefaultContext($contextId > 0 ? $this->em->find(\App\Entity\AI\AiContext::class, $contextId) : null);

        $promptId = (int) $request->request->get('prompt_id', 0);
        $agent->setPrompt($promptId > 0 ? $this->em->find(\App\Entity\AI\AiPrompt::class, $promptId) : null);

        $agent->setIsActive($request->request->getBoolean('is_active', true));
    }
}
