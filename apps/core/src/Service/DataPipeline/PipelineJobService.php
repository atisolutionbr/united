<?php

namespace App\Service\DataPipeline;

use App\Entity\Data\RawFile;
use App\Entity\DatasetResource;
use App\Entity\IngestionRun;
use App\Repository\Data\RawFileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class PipelineJobService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RawFileStorageService $rawFileStorageService,
        private readonly DatasetPreviewService $datasetPreviewService,
        private readonly RawFileRepository $rawFileRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{run: IngestionRun, rawFile: RawFile, duplicate: bool, message: string}
     */
    public function dispatchDownload(DatasetResource $resource): array
    {
        $run = $this->createRun($resource, sprintf('Download do resource %s iniciado.', $resource->getResourceId()));

        try {
            $run->addLog([
                'event' => 'download_iniciado',
                'resource_id' => $resource->getResourceId(),
                'url' => $resource->getUrl(),
            ]);

            $result = $this->rawFileStorageService->download($resource);
            $rawFile = $result['rawFile'];

            $run
                ->setStatus(IngestionRun::STATUS_SUCCESS)
                ->setFinishedAt(new \DateTimeImmutable())
                ->setMessage($result['message'])
                ->addLog([
                    'event' => $result['duplicate'] ? 'download_duplicado' : 'download_concluido',
                    'raw_file_id' => $rawFile->getId(),
                    'local_path' => $rawFile->getLocalPath(),
                    'hash' => $result['hash'],
                    'bytes' => $result['bytes'],
                ])
            ;

            $this->entityManager->flush();

            return [
                'run' => $run,
                'rawFile' => $rawFile,
                'duplicate' => $result['duplicate'],
                'message' => $result['message'],
            ];
        } catch (\Throwable $exception) {
            $this->markRunAsFailed($run, $exception, 'falha_download');

            throw $exception;
        }
    }

    /**
     * @return array{run: IngestionRun, preview: array<string, mixed>}
     */
    public function dispatchPreview(RawFile $rawFile): array
    {
        $run = $this->createRun($rawFile->getDatasetResource(), sprintf('Parser do arquivo RAW %d iniciado.', $rawFile->getId()));

        try {
            $run->addLog([
                'event' => 'parser_iniciado',
                'raw_file_id' => $rawFile->getId(),
                'path' => $rawFile->getLocalPath(),
            ]);

            $preview = $this->datasetPreviewService->generatePreview($rawFile);
            $status = $preview['previewAvailable'] ? IngestionRun::STATUS_SUCCESS : IngestionRun::STATUS_WARNING;
            $message = $preview['previewAvailable']
                ? sprintf('Preview gerado com %d linha(s) e %d coluna(s).', $preview['totalRows'], $preview['totalColumns'])
                : (string) $preview['previewMessage'];

            $run
                ->setStatus($status)
                ->setFinishedAt(new \DateTimeImmutable())
                ->setMessage($message)
                ->addLog([
                    'event' => $preview['previewAvailable'] ? 'parser_concluido' : 'falha_parser',
                    'raw_file_id' => $rawFile->getId(),
                    'total_rows' => $preview['totalRows'],
                    'total_columns' => $preview['totalColumns'],
                    'preview_available' => $preview['previewAvailable'],
                ])
            ;

            $this->entityManager->flush();

            return [
                'run' => $run,
                'preview' => $preview,
            ];
        } catch (\Throwable $exception) {
            $this->markRunAsFailed($run, $exception, 'falha_parser');

            throw $exception;
        }
    }

    /**
     * @return array{run: IngestionRun, schemaCount: int, preview: array<string, mixed>}
     */
    public function dispatchSchemaDiscovery(RawFile $rawFile): array
    {
        $run = $this->createRun($rawFile->getDatasetResource(), sprintf('Descoberta de schema do arquivo RAW %d iniciada.', $rawFile->getId()));

        try {
            $preview = $this->datasetPreviewService->generatePreview($rawFile);
            $schemaCount = $this->datasetPreviewService->syncSchema($rawFile, $preview);
            $rawFile->setAlreadyProcessed($preview['previewAvailable']);

            $run
                ->setStatus($preview['previewAvailable'] ? IngestionRun::STATUS_SUCCESS : IngestionRun::STATUS_WARNING)
                ->setFinishedAt(new \DateTimeImmutable())
                ->setMessage($preview['previewAvailable']
                    ? sprintf('%d coluna(s) catalogadas no schema.', $schemaCount)
                    : (string) $preview['previewMessage'])
                ->addLog([
                    'event' => $preview['previewAvailable'] ? 'schema_detectado' : 'falha_parser',
                    'raw_file_id' => $rawFile->getId(),
                    'schema_count' => $schemaCount,
                ])
            ;

            $this->entityManager->flush();

            return [
                'run' => $run,
                'schemaCount' => $schemaCount,
                'preview' => $preview,
            ];
        } catch (\Throwable $exception) {
            $this->markRunAsFailed($run, $exception, 'falha_parser');

            throw $exception;
        }
    }

    /**
     * @return array{run: IngestionRun, rawFile: RawFile, preview: array<string, mixed>, schemaCount: int, message: string}
     */
    public function executePipeline(DatasetResource $resource): array
    {
        $run = $this->createRun($resource, sprintf('Pipeline do resource %s iniciada.', $resource->getResourceId()));

        try {
            $run->addLog([
                'event' => 'download_iniciado',
                'resource_id' => $resource->getResourceId(),
            ]);

            $download = $this->rawFileStorageService->download($resource);
            $rawFile = $download['rawFile'];

            $run->addLog([
                'event' => $download['duplicate'] ? 'download_duplicado' : 'download_concluido',
                'raw_file_id' => $rawFile->getId(),
                'local_path' => $rawFile->getLocalPath(),
                'hash' => $download['hash'],
            ]);
            $run->addLog([
                'event' => 'parser_iniciado',
                'raw_file_id' => $rawFile->getId(),
            ]);

            $preview = $this->datasetPreviewService->generatePreview($rawFile);
            if ($preview['previewAvailable']) {
                $run->addLog([
                    'event' => 'parser_concluido',
                    'raw_file_id' => $rawFile->getId(),
                    'total_rows' => $preview['totalRows'],
                    'total_columns' => $preview['totalColumns'],
                ]);
                $run->addLog([
                    'event' => 'schema_iniciado',
                    'raw_file_id' => $rawFile->getId(),
                ]);
            }

            $schemaCount = $this->datasetPreviewService->syncSchema($rawFile, $preview);
            $rawFile->setAlreadyProcessed($preview['previewAvailable']);

            $status = $preview['previewAvailable'] ? IngestionRun::STATUS_SUCCESS : IngestionRun::STATUS_WARNING;
            $message = $preview['previewAvailable']
                ? sprintf('Pipeline concluída: arquivo RAW salvo, preview gerado e %d coluna(s) catalogadas.', $schemaCount)
                : sprintf('Arquivo RAW salvo, mas o preview ainda não está disponível para %s.', $preview['fileType']);

            $run
                ->setStatus($status)
                ->setFinishedAt(new \DateTimeImmutable())
                ->setMessage($message)
                ->addLog([
                    'event' => $preview['previewAvailable'] ? 'schema_detectado' : 'falha_parser',
                    'raw_file_id' => $rawFile->getId(),
                    'schema_count' => $schemaCount,
                ])
            ;

            $this->entityManager->flush();

            return [
                'run' => $run,
                'rawFile' => $rawFile,
                'preview' => $preview,
                'schemaCount' => $schemaCount,
                'message' => $message,
            ];
        } catch (\Throwable $exception) {
            $this->markRunAsFailed($run, $exception, 'falha_pipeline');

            throw $exception;
        }
    }

    public function findLatestRawFileForResource(DatasetResource $resource): ?RawFile
    {
        return $this->rawFileRepository->findLatestForResource($resource);
    }

    private function createRun(DatasetResource $resource, string $message): IngestionRun
    {
        $run = (new IngestionRun())
            ->setDataProvider($resource->getProviderPackage()->getDataProvider())
            ->setProviderPackage($resource->getProviderPackage())
            ->setDatasetResource($resource)
            ->setStatus(IngestionRun::STATUS_RUNNING)
            ->setStartedAt(new \DateTimeImmutable())
            ->setMessage($message)
            ->setLogs([])
        ;

        $this->entityManager->persist($run);

        return $run;
    }

    private function markRunAsFailed(IngestionRun $run, \Throwable $exception, string $event): void
    {
        $run
            ->setStatus(IngestionRun::STATUS_FAILED)
            ->setFinishedAt(new \DateTimeImmutable())
            ->setMessage($exception->getMessage())
            ->addLog([
                'event' => $event,
                'message' => $exception->getMessage(),
                'type' => $exception::class,
            ])
        ;

        $this->logger->error('Falha na pipeline operacional de dados.', [
            'message' => $exception->getMessage(),
            'exception' => $exception,
        ]);

        $this->entityManager->flush();
    }
}