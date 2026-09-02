<?php

namespace App\Controller\Admin\Governance;

use App\Entity\Governance\DataGovernanceRecord;
use App\Repository\Governance\DataGovernanceRecordRepository;
use App\Service\Governance\AuditService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[Route('/admin/governance/data')]
#[IsGranted('ROLE_ADMIN')]
class DataGovernanceController extends AbstractController
{
    public function __construct(
        private readonly DataGovernanceRecordRepository $recordRepository,
        private readonly AuditService $auditService,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('', name: 'app_admin_governance_data', methods: ['GET'])]
    public function index(): Response
    {
        $byClass = $this->recordRepository->countByClassification();
        return $this->render('admin/governance/data.html.twig', [
            'records' => $this->recordRepository->findActive(),
            'byClassification' => $byClass,
        ]);
    }

    #[Route('/new', name: 'app_admin_governance_data_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $record = new DataGovernanceRecord();
        if ($request->isMethod('POST')) {
            $this->fill($record, $request);
            $this->em->persist($record);
            $this->em->flush();
            $this->auditService->logConfigChange('DataGovernanceRecord', (string)$record->getId(), $this->getUser()?->getUserIdentifier() ?? 'system', [], [], $request);
            $this->addFlash('success', "Registro de governança '{$record->getDatasetName()}' criado.");
            return $this->redirectToRoute('app_admin_governance_data');
        }
        return $this->render('admin/governance/data_form.html.twig', [
            'record' => $record,
            'classifications' => DataGovernanceRecord::CLASSIFICATIONS,
            'sensitivityLevels' => DataGovernanceRecord::SENSITIVITY_LEVELS,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_governance_data_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request): Response
    {
        $record = $this->recordRepository->find($id) ?? throw $this->createNotFoundException();
        if ($request->isMethod('POST')) {
            $this->fill($record, $request);
            $this->em->flush();
            $this->addFlash('success', "Registro '{$record->getDatasetName()}' atualizado.");
            return $this->redirectToRoute('app_admin_governance_data');
        }
        return $this->render('admin/governance/data_form.html.twig', [
            'record' => $record,
            'classifications' => DataGovernanceRecord::CLASSIFICATIONS,
            'sensitivityLevels' => DataGovernanceRecord::SENSITIVITY_LEVELS,
        ]);
    }

    private function fill(DataGovernanceRecord $record, Request $request): void
    {
        $slugger = new AsciiSlugger();
        $name = strip_tags((string)$request->request->get('dataset_name', ''));
        $record->setDatasetName($name);
        if (!$record->getDatasetSlug()) {
            $record->setDatasetSlug(strtolower($slugger->slug($name)->toString()));
        }
        $record->setOwner(strip_tags((string)$request->request->get('owner', '')));
        $record->setSteward(strip_tags((string)$request->request->get('steward', '')));
        $record->setClassification((string)$request->request->get('classification', DataGovernanceRecord::CLASSIFICATION_PUBLIC));
        $record->setSensitivityLevel((string)$request->request->get('sensitivity_level', DataGovernanceRecord::SENSITIVITY_NONE));
        $record->setLgpdApplicable((bool)$request->request->get('lgpd_applicable'));
        $record->setLgpdBasis((string)$request->request->get('lgpd_basis', ''));
        $record->setDescription(strip_tags((string)$request->request->get('description', '')));
        $record->setIsActive((bool)$request->request->get('is_active', true));
    }
}
