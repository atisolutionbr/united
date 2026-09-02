<?php

namespace App\Controller\Admin\Data;

use App\Entity\Data\RawFile;
use App\Entity\User;
use App\Repository\Data\DataQualityReportRepository;
use App\Repository\Data\RawFileRepository;
use App\Service\DataPipeline\DataTransformationPipelineService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/data-pipeline/quality')]
#[IsGranted(User::ROLE_ADMIN)]
final class DataQualityController extends AbstractController
{
    #[Route('', name: 'app_admin_data_quality', methods: ['GET'])]
    public function index(DataQualityReportRepository $reportRepository, RawFileRepository $rawFileRepository): Response
    {
        $reports = $reportRepository->findRecent(30);

        return $this->render('admin/data_pipeline/quality_index.html.twig', [
            'reports' => $reports,
            'summary' => [
                'totalReports' => $reportRepository->count([]),
                'withIssues' => $reportRepository->countWithIssues(),
                'averageScore' => $reportRepository->getAverageQualityScore(),
                'processableFiles' => $rawFileRepository->countPreviewable(),
            ],
        ]);
    }

    #[Route('/report/{id<\d+>}', name: 'app_admin_data_quality_report', methods: ['GET'])]
    public function show(RawFile $rawFile, DataQualityReportRepository $reportRepository): Response
    {
        $report = $reportRepository->findLatestForRawFile($rawFile);

        return $this->render('admin/data_pipeline/quality_show.html.twig', [
            'rawFile' => $rawFile,
            'report' => $report,
        ]);
    }

    #[Route('/generate/{id<\d+>}', name: 'app_admin_data_quality_generate', methods: ['POST'])]
    public function generate(RawFile $rawFile, DataTransformationPipelineService $pipelineService): Response
    {
        try {
            $report = $pipelineService->generateQualityReport($rawFile);
            $this->addFlash('success', sprintf(
                'Relatório gerado: %d linhas, %.1f%% de qualidade.',
                $report->getTotalRows(),
                $report->getQualityScore()
            ));
        } catch (\Throwable $exception) {
            $this->addFlash('danger', 'Erro ao gerar relatório: '.$exception->getMessage());
        }

        return $this->redirectToRoute('app_admin_data_quality_report', ['id' => $rawFile->getId()]);
    }

    #[Route('/transform/{id<\d+>}', name: 'app_admin_data_transform', methods: ['POST'])]
    public function transform(RawFile $rawFile, DataTransformationPipelineService $pipelineService): Response
    {
        try {
            $result = $pipelineService->executeTransformation($rawFile);
            $this->addFlash('success', $result['message']);
        } catch (\Throwable $exception) {
            $this->addFlash('danger', 'Erro na transformação: '.$exception->getMessage());
        }

        return $this->redirectToRoute('app_admin_data_quality_report', ['id' => $rawFile->getId()]);
    }
}
