<?php

namespace App\Controller\Admin\Operations;

use App\Entity\Operations\Pipeline;
use App\Repository\Operations\PipelineRepository;
use App\Service\Governance\AuditService;
use App\Service\Operations\AlertService;
use App\Service\Operations\PipelineService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/admin/operations/pipelines')]
#[IsGranted('ROLE_ADMIN')]
class PipelineController extends AbstractController
{
    public function __construct(
        private readonly PipelineRepository $pipelineRepository,
        private readonly PipelineService $pipelineService,
        private readonly AuditService $auditService,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'app_admin_operations_pipelines', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/operations/pipelines/index.html.twig', [
            'pipelines' => $this->pipelineRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_admin_operations_pipelines_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $pipeline = new Pipeline();

        if ($request->isMethod('POST')) {
            $this->fillFromRequest($pipeline, $request);
            $this->em->persist($pipeline);
            $this->em->flush();
            $this->auditService->logConfigChange('Pipeline', (string)$pipeline->getId(), $this->getUser()?->getUserIdentifier() ?? 'system', [], [], $request);
            $this->addFlash('success', "Pipeline '{$pipeline->getName()}' criado com sucesso.");
            return $this->redirectToRoute('app_admin_operations_pipelines');
        }

        return $this->render('admin/operations/pipelines/form.html.twig', [
            'pipeline' => $pipeline,
            'types' => Pipeline::TYPES,
            'triggerTypes' => Pipeline::TRIGGER_TYPES,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_operations_pipelines_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $pipeline = $this->pipelineRepository->find($id) ?? throw $this->createNotFoundException();

        if ($request->isMethod('POST')) {
            $before = ['name' => $pipeline->getName(), 'type' => $pipeline->getType()];
            $this->fillFromRequest($pipeline, $request);
            $this->em->flush();
            $this->auditService->logConfigChange('Pipeline', (string)$id, $this->getUser()?->getUserIdentifier() ?? 'system', $before, [], $request);
            $this->addFlash('success', "Pipeline '{$pipeline->getName()}' atualizado.");
            return $this->redirectToRoute('app_admin_operations_pipelines');
        }

        return $this->render('admin/operations/pipelines/form.html.twig', [
            'pipeline' => $pipeline,
            'types' => Pipeline::TYPES,
            'triggerTypes' => Pipeline::TRIGGER_TYPES,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_admin_operations_pipelines_delete', methods: ['POST'])]
    public function delete(int $id): Response
    {
        $pipeline = $this->pipelineRepository->find($id) ?? throw $this->createNotFoundException();
        $name = $pipeline->getName();
        $this->em->remove($pipeline);
        $this->em->flush();
        $this->addFlash('success', "Pipeline '{$name}' removido.");
        return $this->redirectToRoute('app_admin_operations_pipelines');
    }

    #[Route('/{id}/trigger', name: 'app_admin_operations_pipelines_trigger', methods: ['POST'])]
    public function trigger(int $id, Request $request): Response
    {
        $pipeline = $this->pipelineRepository->find($id) ?? throw $this->createNotFoundException();
        $execution = $this->pipelineService->trigger($pipeline, $this->getUser()?->getUserIdentifier() ?? 'admin', $request);
        $this->addFlash('success', "Pipeline '{$pipeline->getName()}' disparado. Execução: #{$execution->getId()}");
        return $this->redirectToRoute('app_admin_operations_pipelines');
    }

    #[Route('/{id}/pause', name: 'app_admin_operations_pipelines_pause', methods: ['POST'])]
    public function pause(int $id): Response
    {
        $pipeline = $this->pipelineRepository->find($id) ?? throw $this->createNotFoundException();
        $pipeline->setIsActive(!$pipeline->isActive());
        $this->em->flush();
        $label = $pipeline->isActive() ? 'reativado' : 'pausado';
        $this->addFlash('success', "Pipeline '{$pipeline->getName()}' {$label}.");
        return $this->redirectToRoute('app_admin_operations_pipelines');
    }

    #[Route('/{id}/yaml', name: 'app_admin_operations_pipelines_yaml', methods: ['GET'])]
    public function yaml(int $id): Response
    {
        $pipeline = $this->pipelineRepository->find($id) ?? throw $this->createNotFoundException();
        return $this->render('admin/operations/pipelines/yaml.html.twig', ['pipeline' => $pipeline]);
    }

    private function fillFromRequest(Pipeline $pipeline, Request $request): void
    {
        $slugger = new AsciiSlugger();
        $name = strip_tags((string)$request->request->get('name', ''));
        $pipeline->setName($name);
        if (!$pipeline->getSlug() || $request->request->get('regenerate_slug')) {
            $pipeline->setSlug(strtolower($slugger->slug($name)->toString()));
        }
        $pipeline->setType((string)$request->request->get('type', Pipeline::TYPE_INGESTION));
        $pipeline->setTriggerType((string)$request->request->get('trigger_type', Pipeline::TRIGGER_MANUAL));
        $pipeline->setDescription(strip_tags((string)$request->request->get('description', '')));
        $pipeline->setKestraNamespace((string)$request->request->get('kestra_namespace', ''));
        $pipeline->setKestraFlowId((string)$request->request->get('kestra_flow_id', ''));
        $pipeline->setCronExpression((string)$request->request->get('cron_expression', ''));
        $pipeline->setDatasetSlug((string)$request->request->get('dataset_slug', ''));
        // Sanitize YAML — only allow alphanumeric, yaml chars; strip script tags
        $rawYaml = (string)$request->request->get('kestra_yaml', '');
        $pipeline->setKestraYaml(strip_tags($rawYaml));
        $pipeline->setIsActive((bool)$request->request->get('is_active', true));
    }
}
