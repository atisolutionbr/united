<?php

namespace App\Controller\Admin\Data;

use App\Entity\Data\RawFile;
use App\Entity\User;
use App\Repository\Data\DatasetSchemaRepository;
use App\Repository\Data\RawFileRepository;
use App\Service\DataPipeline\DatasetPreviewService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/data-providers/previews')]
#[IsGranted(User::ROLE_ADMIN)]
final class DatasetPreviewController extends AbstractController
{
    #[Route('', name: 'app_admin_dataset_previews', methods: ['GET'])]
    public function index(RawFileRepository $rawFileRepository, DatasetPreviewService $datasetPreviewService, DatasetSchemaRepository $datasetSchemaRepository): Response
    {
        $rawFiles = $rawFileRepository->findPreviewableRecent(30);
        $selectedRawFile = $rawFiles[0] ?? null;
        $preview = null;
        $schemas = [];

        if (null !== $selectedRawFile) {
            $preview = $datasetPreviewService->generatePreview($selectedRawFile);
            $schemas = $datasetSchemaRepository->findByRawFileOrdered($selectedRawFile);
        }

        return $this->render('admin/data_pipeline/preview_show.html.twig', [
            'rawFiles' => $rawFiles,
            'selectedRawFile' => $selectedRawFile,
            'preview' => $preview,
            'schemas' => $schemas,
        ]);
    }

    #[Route('/{id<\d+>}', name: 'app_admin_dataset_preview_show', methods: ['GET'])]
    public function show(RawFile $rawFile, RawFileRepository $rawFileRepository, DatasetPreviewService $datasetPreviewService, DatasetSchemaRepository $datasetSchemaRepository): Response
    {
        return $this->render('admin/data_pipeline/preview_show.html.twig', [
            'rawFiles' => $rawFileRepository->findPreviewableRecent(30),
            'selectedRawFile' => $rawFile,
            'preview' => $datasetPreviewService->generatePreview($rawFile),
            'schemas' => $datasetSchemaRepository->findByRawFileOrdered($rawFile),
        ]);
    }
}