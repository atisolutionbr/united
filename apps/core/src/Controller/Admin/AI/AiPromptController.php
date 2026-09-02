<?php

namespace App\Controller\Admin\AI;

use App\Entity\AI\AiPrompt;
use App\Repository\AI\AiPromptRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/ai/prompts')]
#[IsGranted('ROLE_ADMIN')]
class AiPromptController extends AbstractController
{
    public function __construct(
        private readonly AiPromptRepository $promptRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'app_admin_ai_prompts', methods: ['GET'])]
    public function index(): Response
    {
        $prompts = $this->em->getRepository(AiPrompt::class)->findBy([], ['name' => 'ASC']);
        return $this->render('admin/ai/prompts/index.html.twig', ['prompts' => $prompts]);
    }

    #[Route('/new', name: 'app_admin_ai_prompts_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $prompt = new AiPrompt();
        if ($request->isMethod('POST')) {
            $this->hydratePrompt($prompt, $request);
            $this->em->persist($prompt);
            $this->em->flush();
            $this->addFlash('success', "Prompt '{$prompt->getName()}' criado.");
            return $this->redirectToRoute('app_admin_ai_prompts');
        }
        return $this->render('admin/ai/prompts/form.html.twig', ['prompt' => $prompt, 'mode' => 'new']);
    }

    #[Route('/{id}/edit', name: 'app_admin_ai_prompts_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $prompt = $this->em->find(AiPrompt::class, $id);
        if ($prompt === null) { throw $this->createNotFoundException(); }
        if ($request->isMethod('POST')) {
            $this->hydratePrompt($prompt, $request);
            $this->em->flush();
            $this->addFlash('success', "Prompt '{$prompt->getName()}' atualizado.");
            return $this->redirectToRoute('app_admin_ai_prompts');
        }
        return $this->render('admin/ai/prompts/form.html.twig', ['prompt' => $prompt, 'mode' => 'edit']);
    }

    #[Route('/{id}/delete', name: 'app_admin_ai_prompts_delete', methods: ['POST'])]
    public function delete(int $id): Response
    {
        $prompt = $this->em->find(AiPrompt::class, $id);
        if ($prompt === null) { throw $this->createNotFoundException(); }
        $name = $prompt->getName();
        $this->em->remove($prompt);
        $this->em->flush();
        $this->addFlash('success', "Prompt '{$name}' removido.");
        return $this->redirectToRoute('app_admin_ai_prompts');
    }

    private function hydratePrompt(AiPrompt $prompt, Request $request): void
    {
        $prompt->setName(trim($request->request->get('name', '')));
        $prompt->setSlug(trim($request->request->get('slug', '')));
        $prompt->setPurpose($request->request->get('purpose', AiPrompt::PURPOSE_GENERAL_ASSISTANT));
        $prompt->setPromptTemplate($request->request->get('prompt_template', ''));
        $prompt->setContextType($request->request->get('context_type') ?: null);
        $prompt->setProvider($request->request->get('provider') ?: null);
        $prompt->setVersion((int) $request->request->get('version', 1));
        $prompt->setIsActive($request->request->getBoolean('is_active', true));
    }
}
