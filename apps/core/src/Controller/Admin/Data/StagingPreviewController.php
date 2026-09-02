<?php

namespace App\Controller\Admin\Data;

use App\Entity\Data\RawFile;
use App\Entity\User;
use App\Repository\Data\DatasetColumnMappingRepository;
use App\Repository\Data\RawFileRepository;
use App\Service\DataPipeline\DatasetPreviewService;
use App\Service\Normalization\DataNormalizationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/data-pipeline/staging')]
#[IsGranted(User::ROLE_ADMIN)]
final class StagingPreviewController extends AbstractController
{
    #[Route('', name: 'app_admin_staging_preview', methods: ['GET'])]
    public function index(RawFileRepository $rawFileRepository): Response
    {
        $files = $rawFileRepository->findWithStaging(20);

        return $this->render('admin/data_pipeline/staging_index.html.twig', [
            'files' => $files,
            'summary' => [
                'totalStaged' => $rawFileRepository->countWithStaging(),
                'totalFailed' => $rawFileRepository->countTransformationFailed(),
            ],
        ]);
    }

    #[Route('/{id<\d+>}', name: 'app_admin_staging_show', methods: ['GET'])]
    public function show(
        RawFile $rawFile,
        DatasetPreviewService $previewService,
        DataNormalizationService $normalizationService,
        DatasetColumnMappingRepository $columnMappingRepository,
    ): Response {
        $preview = null;
        $normalizedRows = [];
        $previewError = null;

        try {
            $preview = $previewService->generatePreview($rawFile, 30);
            $mappings = $columnMappingRepository->findActiveByPackage($rawFile->getProviderPackage());

            if (!empty($mappings)) {
                foreach ($preview['rows'] as $row) {
                    $normalizedRows[] = $normalizationService->normalizeRow($row, $mappings);
                }
            }
        } catch (\Throwable $exception) {
            $previewError = $exception->getMessage();
        }

        return $this->render('admin/data_pipeline/staging_show.html.twig', [
            'rawFile' => $rawFile,
            'preview' => $preview,
            'normalizedRows' => $normalizedRows,
            'previewError' => $previewError,
        ]);
    }
}
