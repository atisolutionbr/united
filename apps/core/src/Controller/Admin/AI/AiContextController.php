<?php

namespace App\Controller\Admin\AI;

use App\Entity\AI\AiContext;
use App\Repository\AI\AiContextRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/ai/contexts')]
#[IsGranted('ROLE_ADMIN')]
class AiContextController extends AbstractController
{
    public function __construct(
        private readonly AiContextRepository $contextRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'app_admin_ai_contexts', methods: ['GET'])]
    public function index(): Response
    {
        $contexts = $this->em->getRepository(AiContext::class)->findBy([], ['name' => 'ASC']);
        return $this->render('admin/ai/contexts/index.html.twig', ['contexts' => $contexts]);
    }

    #[Route('/new', name: 'app_admin_ai_contexts_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $context = new AiContext();
        if ($request->isMethod('POST')) {
            $this->hydrateContext($context, $request);
            $this->em->persist($context);
            $this->em->flush();
            $this->addFlash('success', "Contexto '{$context->getName()}' criado.");
            return $this->redirectToRoute('app_admin_ai_contexts');
        }
        return $this->render('admin/ai/contexts/form.html.twig', ['context' => $context, 'mode' => 'new']);
    }

    #[Route('/{id}/edit', name: 'app_admin_ai_contexts_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $context = $this->em->find(AiContext::class, $id);
        if ($context === null) { throw $this->createNotFoundException(); }
        if ($request->isMethod('POST')) {
            $this->hydrateContext($context, $request);
            $this->em->flush();
            $this->addFlash('success', "Contexto '{$context->getName()}' atualizado.");
            return $this->redirectToRoute('app_admin_ai_contexts');
        }
        return $this->render('admin/ai/contexts/form.html.twig', ['context' => $context, 'mode' => 'edit']);
    }

    #[Route('/{id}/delete', name: 'app_admin_ai_contexts_delete', methods: ['POST'])]
    public function delete(int $id): Response
    {
        $context = $this->em->find(AiContext::class, $id);
        if ($context === null) { throw $this->createNotFoundException(); }
        $name = $context->getName();
        $this->em->remove($context);
        $this->em->flush();
        $this->addFlash('success', "Contexto '{$name}' removido.");
        return $this->redirectToRoute('app_admin_ai_contexts');
    }

    private function hydrateContext(AiContext $context, Request $request): void
    {
        $context->setName(trim($request->request->get('name', '')));
        $context->setSlug(trim($request->request->get('slug', '')));
        $context->setDescription($request->request->get('description') ?: null);
        $context->setAllowedForExternal($request->request->getBoolean('allowed_for_external'));
        $context->setMaxRowsContext((int) $request->request->get('max_rows_context', 100));
        $context->setIsActive($request->request->getBoolean('is_active', true));

        $sourcesRaw = array_filter(array_map('trim', explode("\n", $request->request->get('sources', ''))));
        $context->setSources(array_values($sourcesRaw));

        $tablesRaw = array_filter(array_map('trim', explode("\n", $request->request->get('warehouse_tables', ''))));
        $context->setWarehouseTables(array_values($tablesRaw));
    }
}
