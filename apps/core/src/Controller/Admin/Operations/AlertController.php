<?php

namespace App\Controller\Admin\Operations;

use App\Repository\Operations\AlertRepository;
use App\Service\Operations\AlertService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/operations/alerts')]
#[IsGranted('ROLE_ADMIN')]
class AlertController extends AbstractController
{
    public function __construct(
        private readonly AlertRepository $alertRepository,
        private readonly AlertService $alertService,
    ) {}

    #[Route('', name: 'app_admin_operations_alerts', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/operations/alerts/index.html.twig', [
            'alerts' => $this->alertRepository->findRecent(50),
            'activeCritical' => $this->alertRepository->countCritical(),
            'activeTotal' => $this->alertRepository->countActive(),
        ]);
    }

    #[Route('/{id}/acknowledge', name: 'app_admin_operations_alerts_acknowledge', methods: ['POST'])]
    public function acknowledge(int $id): Response
    {
        $user = $this->getUser()?->getUserIdentifier() ?? 'admin';
        $ok = $this->alertService->acknowledge($id, $user);
        $this->addFlash($ok ? 'success' : 'warning', $ok ? 'Alerta reconhecido.' : 'Alerta não encontrado.');
        return $this->redirectToRoute('app_admin_operations_alerts');
    }

    #[Route('/{id}/resolve', name: 'app_admin_operations_alerts_resolve', methods: ['POST'])]
    public function resolve(int $id): Response
    {
        $ok = $this->alertService->resolve($id);
        $this->addFlash($ok ? 'success' : 'warning', $ok ? 'Alerta resolvido.' : 'Alerta não encontrado.');
        return $this->redirectToRoute('app_admin_operations_alerts');
    }
}
