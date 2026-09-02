<?php

namespace App\Controller\Admin\Data;

use App\Entity\Data\RawFile;
use App\Entity\User;
use App\Repository\Data\DatasetSchemaRepository;
use App\Repository\Data\RawFileRepository;
use App\Service\DataPipeline\PipelineJobService;
use App\Service\DataPipeline\RawFileStorageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/data-providers/raw-files')]
#[IsGranted(User::ROLE_ADMIN)]
final class RawFileController extends AbstractController
{
    #[Route('', name: 'app_admin_raw_files', methods: ['GET'])]
    public function index(RawFileRepository $rawFileRepository): Response
    {
        $rawFiles = $rawFileRepository->findRecent(80);

        return $this->render('admin/data_pipeline/raw_files.html.twig', [
            'rawFiles' => $rawFiles,
            'summary' => [
                'total' => $rawFileRepository->count([]),
                'downloaded' => $rawFileRepository->count(['downloadStatus' => RawFile::STATUS_DOWNLOADED]),
                'duplicates' => $rawFileRepository->count(['downloadStatus' => RawFile::STATUS_DUPLICATE]),
                'processed' => $rawFileRepository->count(['alreadyProcessed' => true]),
                'previewable' => $rawFileRepository->countPreviewable(),
            ],
        ]);
    }

    #[Route('/{id<\d+>}/metadata', name: 'app_admin_raw_files_metadata', methods: ['GET'])]
    public function metadata(RawFile $rawFile, DatasetSchemaRepository $datasetSchemaRepository): Response
    {
        return $this->render('admin/data_pipeline/raw_file_metadata.html.twig', [
            'rawFile' => $rawFile,
            'schemas' => $datasetSchemaRepository->findByRawFileOrdered($rawFile),
        ]);
    }

    #[Route('/{id<\d+>}/download', name: 'app_admin_raw_files_download', methods: ['GET'])]
    public function download(RawFile $rawFile, RawFileStorageService $rawFileStorageService): Response
    {
        $absolutePath = $rawFileStorageService->resolveAbsolutePath($rawFile);
        if (!is_file($absolutePath)) {
            throw $this->createNotFoundException('O arquivo RAW não foi encontrado no storage local.');
        }

        $response = new BinaryFileResponse($absolutePath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $rawFile->getOriginalName());

        return $response;
    }

    #[Route('/{id<\d+>}/reprocess', name: 'app_admin_raw_files_reprocess', methods: ['POST'])]
    public function reprocess(RawFile $rawFile, PipelineJobService $pipelineJobService): Response
    {
        try {
            $result = $pipelineJobService->dispatchSchemaDiscovery($rawFile);
            $this->addFlash('success', $result['run']->getMessage() ?? 'Reprocessamento concluído.');
        } catch (\Throwable $exception) {
            $this->addFlash('danger', $exception->getMessage());
        }

        return $this->redirectToRoute('app_admin_raw_files_metadata', ['id' => $rawFile->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id<\d+>}/delete', name: 'app_admin_raw_files_delete', methods: ['POST'])]
    public function delete(RawFile $rawFile, RawFileRepository $rawFileRepository, RawFileStorageService $rawFileStorageService, EntityManagerInterface $entityManager): Response
    {
        $localPath = $rawFile->getLocalPath();
        $shouldDeletePhysicalFile = $rawFileRepository->countByLocalPath($localPath) <= 1;

        $entityManager->remove($rawFile);
        $entityManager->flush();

        if ($shouldDeletePhysicalFile) {
            $rawFileStorageService->deleteFileIfUnused($rawFile);
        }

        $this->addFlash('success', 'Registro RAW removido com sucesso.');

        return $this->redirectToRoute('app_admin_raw_files', [], Response::HTTP_SEE_OTHER);
    }
}