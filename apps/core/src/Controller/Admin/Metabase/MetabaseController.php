<?php

namespace App\Controller\Admin\Metabase;

use App\Entity\User;
use App\Entity\Warehouse\MetabaseConfig;
use App\Entity\Warehouse\MetabaseDashboard;
use App\Repository\Warehouse\MetabaseConfigRepository;
use App\Repository\Warehouse\MetabaseDashboardRepository;
use App\Service\Warehouse\MetabaseService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/integrations/metabase')]
#[IsGranted(User::ROLE_ADMIN)]
final class MetabaseController extends AbstractController
{
    #[Route('', name: 'app_admin_metabase_config', methods: ['GET', 'POST'])]
    public function config(
        Request $request,
        MetabaseConfigRepository $configRepository,
        EntityManagerInterface $em,
    ): Response {
        $config = $configRepository->findActive() ?? new MetabaseConfig();

        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            $config
                ->setName($data['name'] ?? 'Metabase Principal')
                ->setBaseUrl($data['base_url'] ?? '')
                ->setDatabaseName($data['database_name'] ?? null)
                ->setUsername($data['username'] ?? null)
                ->setSecretKey($data['secret_key'] ?? null)
                ->setNotes($data['notes'] ?? null)
                ->setIsActive(true);

            // Only update password if provided
            if (!empty($data['password'])) {
                $config->setPasswordEncrypted(base64_encode($data['password']));
            }

            if (!$config->getId()) {
                $em->persist($config);
            }
            $em->flush();

            $this->addFlash('success', 'Configuração Metabase salva com sucesso.');
            return $this->redirectToRoute('app_admin_metabase_config');
        }

        return $this->render('admin/metabase/config.html.twig', [
            'config' => $config,
        ]);
    }

    #[Route('/test-connection', name: 'app_admin_metabase_test_connection', methods: ['POST'])]
    public function testConnection(
        MetabaseConfigRepository $configRepository,
        MetabaseService $metabaseService,
    ): Response {
        $config = $configRepository->findActive();

        if (!$config) {
            $this->addFlash('danger', 'Nenhuma configuração encontrada. Salve as configurações primeiro.');
            return $this->redirectToRoute('app_admin_metabase_config');
        }

        $result = $metabaseService->testConnection($config);

        if ($result['success']) {
            $this->addFlash('success', 'Conexão estabelecida com sucesso com o Metabase.');
        } else {
            $this->addFlash('danger', 'Falha na conexão: ' . $result['message']);
        }

        return $this->redirectToRoute('app_admin_metabase_config');
    }

    #[Route('/sync', name: 'app_admin_metabase_sync', methods: ['POST'])]
    public function sync(
        MetabaseConfigRepository $configRepository,
        MetabaseService $metabaseService,
    ): Response {
        $config = $configRepository->findActive();

        if (!$config) {
            $this->addFlash('danger', 'Configure o Metabase antes de sincronizar.');
            return $this->redirectToRoute('app_admin_metabase_config');
        }

        $result = $metabaseService->syncDashboards($config);

        if ($result['success']) {
            $this->addFlash('success', $result['message'] ?? 'Sincronização iniciada.');
        } else {
            $this->addFlash('warning', $result['error'] ?? 'Sincronização não concluída.');
        }

        return $this->redirectToRoute('app_admin_metabase_dashboards');
    }

    #[Route('/dashboards', name: 'app_admin_metabase_dashboards', methods: ['GET'])]
    public function dashboards(
        MetabaseDashboardRepository $dashboardRepository,
        MetabaseConfigRepository $configRepository,
    ): Response {
        return $this->render('admin/metabase/dashboards.html.twig', [
            'dashboards' => $dashboardRepository->findAllOrdered(),
            'config' => $configRepository->findActive(),
            'summary' => [
                'total' => $dashboardRepository->count([]),
                'active' => $dashboardRepository->countActive(),
                'embeddable' => count($dashboardRepository->findEmbeddable()),
            ],
        ]);
    }

    #[Route('/dashboards/new', name: 'app_admin_metabase_dashboards_new', methods: ['GET', 'POST'])]
    public function newDashboard(
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            $dashboard = (new MetabaseDashboard())
                ->setName($data['name'] ?? '')
                ->setDescription($data['description'] ?? null)
                ->setMetabaseId(!empty($data['metabase_id']) ? (int) $data['metabase_id'] : null)
                ->setEmbedUrl($data['embed_url'] ?? null)
                ->setPublicUuid($data['public_uuid'] ?? null)
                ->setType($data['type'] ?? MetabaseDashboard::TYPE_DASHBOARD)
                ->setDataset($data['dataset'] ?? null)
                ->setOrigin($data['origin'] ?? null)
                ->setAllowEmbed(isset($data['allow_embed']))
                ->setIsActive(true);

            $em->persist($dashboard);
            $em->flush();

            $this->addFlash('success', 'Dashboard registrado com sucesso.');
            return $this->redirectToRoute('app_admin_metabase_dashboards');
        }

        return $this->render('admin/metabase/dashboard_form.html.twig', [
            'dashboard' => null,
            'types' => [MetabaseDashboard::TYPE_DASHBOARD, MetabaseDashboard::TYPE_QUESTION],
        ]);
    }

    #[Route('/dashboards/{id}/edit', name: 'app_admin_metabase_dashboards_edit', methods: ['GET', 'POST'])]
    public function editDashboard(
        MetabaseDashboard $dashboard,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        if ($request->isMethod('POST')) {
            $data = $request->request->all();

            $dashboard
                ->setName($data['name'] ?? $dashboard->getName())
                ->setDescription($data['description'] ?? null)
                ->setMetabaseId(!empty($data['metabase_id']) ? (int) $data['metabase_id'] : null)
                ->setEmbedUrl($data['embed_url'] ?? null)
                ->setPublicUuid($data['public_uuid'] ?? null)
                ->setType($data['type'] ?? MetabaseDashboard::TYPE_DASHBOARD)
                ->setDataset($data['dataset'] ?? null)
                ->setOrigin($data['origin'] ?? null)
                ->setAllowEmbed(isset($data['allow_embed']))
                ->setIsActive(isset($data['is_active']));

            $em->flush();
            $this->addFlash('success', 'Dashboard atualizado.');
            return $this->redirectToRoute('app_admin_metabase_dashboards');
        }

        return $this->render('admin/metabase/dashboard_form.html.twig', [
            'dashboard' => $dashboard,
            'types' => [MetabaseDashboard::TYPE_DASHBOARD, MetabaseDashboard::TYPE_QUESTION],
        ]);
    }

    #[Route('/dashboards/{id}/embed', name: 'app_admin_metabase_dashboards_embed', methods: ['GET'])]
    public function embedDashboard(
        MetabaseDashboard $dashboard,
        MetabaseConfigRepository $configRepository,
        MetabaseService $metabaseService,
    ): Response {
        $config = $configRepository->findActive();
        $embedUrl = null;

        if ($config && $dashboard->getPublicUuid()) {
            $embedUrl = $metabaseService->buildEmbedUrl($config->getBaseUrl(), $dashboard->getPublicUuid());
        } elseif ($dashboard->getEmbedUrl()) {
            $embedUrl = $dashboard->getEmbedUrl();
        }

        return $this->render('admin/metabase/embed.html.twig', [
            'dashboard' => $dashboard,
            'embedUrl' => $embedUrl,
            'config' => $config,
        ]);
    }

    #[Route('/dashboards/{id}/delete', name: 'app_admin_metabase_dashboards_delete', methods: ['POST'])]
    public function deleteDashboard(MetabaseDashboard $dashboard, EntityManagerInterface $em): Response
    {
        $em->remove($dashboard);
        $em->flush();

        $this->addFlash('success', "Dashboard '{$dashboard->getName()}' removido.");
        return $this->redirectToRoute('app_admin_metabase_dashboards');
    }
}
