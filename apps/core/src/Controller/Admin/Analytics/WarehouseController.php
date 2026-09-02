<?php

namespace App\Controller\Admin\Analytics;

use App\Entity\Warehouse\AnalyticModel;
use App\Entity\User;
use App\Repository\Warehouse\AnalyticModelRepository;
use App\Repository\Warehouse\AnalyticsHistoryRepository;
use App\Service\Warehouse\WarehouseTransformationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/analytics/warehouse')]
#[IsGranted(User::ROLE_ADMIN)]
final class WarehouseController extends AbstractController
{
    #[Route('', name: 'app_admin_warehouse_overview', methods: ['GET'])]
    public function overview(
        AnalyticModelRepository $modelRepository,
        AnalyticsHistoryRepository $historyRepository,
        WarehouseTransformationService $warehouseService,
    ): Response {
        $warehouseStats = $warehouseService->getWarehouseStats();
        $warehouseTables = $warehouseService->getWarehouseTables();

        return $this->render('admin/analytics/warehouse/overview.html.twig', [
            'models' => $modelRepository->findActive(),
            'recentHistory' => $historyRepository->findRecent(10),
            'warehouseTables' => $warehouseTables,
            'warehouseStats' => $warehouseStats,
            'summary' => [
                'totalModels' => $modelRepository->count([]),
                'activeModels' => $modelRepository->countActive(),
                'readyModels' => $modelRepository->countReady(),
                'warehouseTables' => $warehouseStats['tables'],
                'totalRows' => $warehouseStats['totalRows'],
            ],
        ]);
    }

    #[Route('/models', name: 'app_admin_analytic_models', methods: ['GET'])]
    public function models(AnalyticModelRepository $modelRepository): Response
    {
        return $this->render('admin/analytics/warehouse/models.html.twig', [
            'models' => $modelRepository->findBy([], ['name' => 'ASC']),
            'totalModels' => $modelRepository->count([]),
            'activeModels' => $modelRepository->countActive(),
            'readyModels' => $modelRepository->countReady(),
        ]);
    }

    #[Route('/models/new', name: 'app_admin_analytic_models_new', methods: ['GET', 'POST'])]
    public function newModel(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        WarehouseTransformationService $warehouseService,
    ): Response {
        $stagingTables = array_merge(
            $warehouseService->getPublicStagingTables(),
            $warehouseService->getStagingTables(),
        );

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            $model = (new AnalyticModel())
                ->setName($data['name'] ?? '')
                ->setSlug(strtolower($slugger->slug($data['name'] ?? 'model')))
                ->setDescription($data['description'] ?? null)
                ->setSourceTable($data['source_table'] ?? '')
                ->setTargetTable($data['target_table'] ?? 'warehouse.dw_' . preg_replace('/[^a-z0-9_]/', '_', strtolower($data['name'] ?? 'model')))
                ->setDimensions(array_filter(explode(',', $data['dimensions'] ?? '')))
                ->setMetrics(array_filter(explode(',', $data['metrics'] ?? '')))
                ->setRefreshStrategy($data['refresh_strategy'] ?? AnalyticModel::REFRESH_MANUAL)
                ->setIsActive(true);

            $em->persist($model);
            $em->flush();

            $this->addFlash('success', 'Modelo analítico criado com sucesso.');
            return $this->redirectToRoute('app_admin_analytic_models');
        }

        return $this->render('admin/analytics/warehouse/model_form.html.twig', [
            'model' => null,
            'stagingTables' => $stagingTables,
            'refreshStrategies' => AnalyticModel::getRefreshStrategies(),
        ]);
    }

    #[Route('/models/{id}/edit', name: 'app_admin_analytic_models_edit', methods: ['GET', 'POST'])]
    public function editModel(
        AnalyticModel $model,
        Request $request,
        EntityManagerInterface $em,
        WarehouseTransformationService $warehouseService,
    ): Response {
        $stagingTables = array_merge(
            $warehouseService->getPublicStagingTables(),
            $warehouseService->getStagingTables(),
        );

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            $model
                ->setName($data['name'] ?? $model->getName())
                ->setDescription($data['description'] ?? null)
                ->setSourceTable($data['source_table'] ?? $model->getSourceTable())
                ->setTargetTable($data['target_table'] ?? $model->getTargetTable())
                ->setDimensions(array_filter(explode(',', $data['dimensions'] ?? '')))
                ->setMetrics(array_filter(explode(',', $data['metrics'] ?? '')))
                ->setRefreshStrategy($data['refresh_strategy'] ?? $model->getRefreshStrategy())
                ->setIsActive(isset($data['is_active']));

            $em->flush();
            $this->addFlash('success', 'Modelo analítico atualizado com sucesso.');
            return $this->redirectToRoute('app_admin_analytic_models');
        }

        return $this->render('admin/analytics/warehouse/model_form.html.twig', [
            'model' => $model,
            'stagingTables' => $stagingTables,
            'refreshStrategies' => AnalyticModel::getRefreshStrategies(),
        ]);
    }

    #[Route('/models/{id}/run', name: 'app_admin_analytic_models_run', methods: ['POST'])]
    public function runModel(
        AnalyticModel $model,
        WarehouseTransformationService $warehouseService,
    ): Response {
        $result = $warehouseService->executeTransformation($model);

        if ($result['success']) {
            $this->addFlash('success', "Transformação concluída. {$result['rows']} linhas geradas em {$model->getTargetTable()}.");
        } else {
            $this->addFlash('danger', "Falha na transformação: {$result['error']}");
        }

        return $this->redirectToRoute('app_admin_analytic_models');
    }

    #[Route('/models/{id}/delete', name: 'app_admin_analytic_models_delete', methods: ['POST'])]
    public function deleteModel(AnalyticModel $model, EntityManagerInterface $em): Response
    {
        $em->remove($model);
        $em->flush();

        $this->addFlash('success', "Modelo '{$model->getName()}' removido.");
        return $this->redirectToRoute('app_admin_analytic_models');
    }

    #[Route('/lineage', name: 'app_admin_data_lineage', methods: ['GET'])]
    public function lineage(
        AnalyticsHistoryRepository $historyRepository,
        WarehouseTransformationService $warehouseService,
        AnalyticModelRepository $modelRepository,
    ): Response {
        return $this->render('admin/analytics/lineage.html.twig', [
            'recentHistory' => $historyRepository->findRecent(20),
            'warehouseTables' => $warehouseService->getWarehouseTables(),
            'stagingTables' => $warehouseService->getPublicStagingTables(),
            'models' => $modelRepository->findActive(),
        ]);
    }
}
