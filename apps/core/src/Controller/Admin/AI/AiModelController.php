<?php

namespace App\Controller\Admin\AI;

use App\Entity\AI\AiModel;
use App\Repository\AI\AiModelRepository;
use App\Service\AI\OllamaService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/ai/models')]
#[IsGranted('ROLE_ADMIN')]
class AiModelController extends AbstractController
{
    public function __construct(
        private readonly AiModelRepository $modelRepository,
        private readonly OllamaService $ollamaService,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'app_admin_ai_models', methods: ['GET'])]
    public function index(): Response
    {
        $models = $this->em->getRepository(AiModel::class)->findBy([], ['name' => 'ASC']);
        $ollamaAvailable = $this->ollamaService->isAvailable();
        $ollamaModels = $ollamaAvailable ? $this->ollamaService->listModels() : [];

        return $this->render('admin/ai/models/index.html.twig', [
            'models' => $models,
            'ollama_available' => $ollamaAvailable,
            'ollama_models' => $ollamaModels,
        ]);
    }

    #[Route('/new', name: 'app_admin_ai_models_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $model = new AiModel();
        if ($request->isMethod('POST')) {
            $this->hydrateModel($model, $request);
            $this->em->persist($model);
            $this->em->flush();
            $this->addFlash('success', "Modelo '{$model->getName()}' cadastrado com sucesso.");
            return $this->redirectToRoute('app_admin_ai_models');
        }

        return $this->render('admin/ai/models/form.html.twig', ['model' => $model, 'mode' => 'new']);
    }

    #[Route('/{id}/edit', name: 'app_admin_ai_models_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $model = $this->em->find(AiModel::class, $id);
        if ($model === null) {
            throw $this->createNotFoundException('Modelo não encontrado.');
        }

        if ($request->isMethod('POST')) {
            $this->hydrateModel($model, $request);
            $this->em->flush();
            $this->addFlash('success', "Modelo '{$model->getName()}' atualizado.");
            return $this->redirectToRoute('app_admin_ai_models');
        }

        return $this->render('admin/ai/models/form.html.twig', ['model' => $model, 'mode' => 'edit']);
    }

    #[Route('/{id}/toggle', name: 'app_admin_ai_models_toggle', methods: ['POST'])]
    public function toggle(int $id): Response
    {
        $model = $this->em->find(AiModel::class, $id);
        if ($model === null) { throw $this->createNotFoundException(); }
        $model->setIsActive(!$model->isActive());
        $this->em->flush();
        $this->addFlash('success', 'Status do modelo atualizado.');
        return $this->redirectToRoute('app_admin_ai_models');
    }

    #[Route('/{id}/set-default', name: 'app_admin_ai_models_set_default', methods: ['POST'])]
    public function setDefault(int $id): Response
    {
        $models = $this->em->getRepository(AiModel::class)->findAll();
        foreach ($models as $m) { $m->setIsDefault(false); }
        $model = $this->em->find(AiModel::class, $id);
        if ($model) { $model->setIsDefault(true); }
        $this->em->flush();
        $this->addFlash('success', 'Modelo padrão definido.');
        return $this->redirectToRoute('app_admin_ai_models');
    }

    #[Route('/{id}/delete', name: 'app_admin_ai_models_delete', methods: ['POST'])]
    public function delete(int $id): Response
    {
        $model = $this->em->find(AiModel::class, $id);
        if ($model === null) { throw $this->createNotFoundException(); }
        $name = $model->getName();
        $this->em->remove($model);
        $this->em->flush();
        $this->addFlash('success', "Modelo '{$name}' removido.");
        return $this->redirectToRoute('app_admin_ai_models');
    }

    private function hydrateModel(AiModel $model, Request $request): void
    {
        $model->setName(trim($request->request->get('name', '')));
        $model->setSlug(trim($request->request->get('slug', '')));
        $model->setProvider($request->request->get('provider', AiModel::PROVIDER_OLLAMA));
        $model->setModelName(trim($request->request->get('model_name', '')));
        $model->setEndpoint($request->request->get('endpoint') ?: null);
        $model->setDescription($request->request->get('description') ?: null);
        $model->setTemperature($request->request->get('temperature') ? (string) $request->request->get('temperature') : null);
        $model->setMaxTokens($request->request->get('max_tokens') ? (int) $request->request->get('max_tokens') : null);
        $model->setContextWindow($request->request->get('context_window') ? (int) $request->request->get('context_window') : null);
        $model->setSupportsEmbeddings($request->request->getBoolean('supports_embeddings'));
        $model->setIsDefault($request->request->getBoolean('is_default'));
        $model->setIsActive($request->request->getBoolean('is_active', true));

        $apiKey = trim($request->request->get('api_key', ''));
        if (!empty($apiKey)) {
            // Store as base64 (use proper encryption in production)
            $model->setApiKeyEncrypted(base64_encode($apiKey));
        }
    }
}
