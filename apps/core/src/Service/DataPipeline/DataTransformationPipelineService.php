<?php

namespace App\Service\DataPipeline;

use App\Entity\Data\DataQualityReport;
use App\Entity\Data\RawFile;
use App\Entity\IngestionRun;
use App\Repository\Data\DatasetColumnMappingRepository;
use App\Repository\Data\DataQualityReportRepository;
use App\Service\Normalization\DataNormalizationService;
use App\Service\Validation\DataValidationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class DataTransformationPipelineService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DatasetPreviewService $datasetPreviewService,
        private readonly DataNormalizationService $normalizationService,
        private readonly DataValidationService $validationService,
        private readonly DatasetColumnMappingRepository $columnMappingRepository,
        private readonly DataQualityReportRepository $qualityReportRepository,
        private readonly RawFileStorageService $rawFileStorageService,
        private readonly LoggerInterface $logger,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * @return array{
     *     run: IngestionRun,
     *     qualityReport: DataQualityReport,
     *     normalizedRows: int,
     *     stagingPath: string|null,
     *     message: string
     * }
     */
    public function executeTransformation(RawFile $rawFile): array
    {
        $run = $this->createTransformationRun($rawFile);

        try {
            $rawFile->setTransformationStatus(RawFile::TRANSFORMATION_RUNNING);
            $this->entityManager->flush();

            $run->addLog(['event' => 'transformation_iniciada', 'raw_file_id' => $rawFile->getId()]);

            $preview = $this->datasetPreviewService->generatePreview($rawFile);

            if (!$preview['previewAvailable']) {
                throw new \RuntimeException(sprintf('O arquivo RAW %d não tem preview disponível para transformação.', $rawFile->getId()));
            }

            $run->addLog(['event' => 'parser_concluido', 'total_rows' => $preview['totalRows'], 'total_columns' => $preview['totalColumns']]);

            $mappings = $this->columnMappingRepository->findActiveByPackage($rawFile->getProviderPackage());
            $run->addLog(['event' => 'mappings_carregados', 'count' => count($mappings)]);

            $normalizedRows = $this->normalizeDataset($preview['rows'], $mappings, $run);

            $run->addLog(['event' => 'normalizacao_concluida', 'rows_normalized' => count($normalizedRows)]);

            $qualityStats = $this->validationService->analyzeDataset($normalizedRows, $mappings);

            $run->addLog([
                'event' => 'validacao_concluida',
                'valid_rows' => $qualityStats['valid_rows'],
                'invalid_rows' => $qualityStats['invalid_rows'],
                'duplicated_rows' => $qualityStats['duplicated_rows'],
            ]);

            $qualityReport = $this->buildQualityReport($rawFile, $qualityStats);

            $stagingPath = $this->importToStaging($rawFile, $normalizedRows);
            $rawFile->setStagingPath($stagingPath);
            $rawFile->setTransformationStatus(RawFile::TRANSFORMATION_DONE);

            $run->addLog(['event' => 'staging_salvo', 'staging_path' => $stagingPath]);

            $message = sprintf(
                'Transformação concluída: %d linha(s) normalizadas, %d válidas, %d inválidas. Score de qualidade: %.1f%%.',
                count($normalizedRows),
                $qualityStats['valid_rows'],
                $qualityStats['invalid_rows'],
                $qualityReport->getQualityScore()
            );

            $run
                ->setStatus(IngestionRun::STATUS_SUCCESS)
                ->setFinishedAt(new \DateTimeImmutable())
                ->setMessage($message)
            ;

            $this->entityManager->flush();

            return [
                'run' => $run,
                'qualityReport' => $qualityReport,
                'normalizedRows' => count($normalizedRows),
                'stagingPath' => $stagingPath,
                'message' => $message,
            ];
        } catch (\Throwable $exception) {
            $rawFile->setTransformationStatus(RawFile::TRANSFORMATION_FAILED);

            $run
                ->setStatus(IngestionRun::STATUS_FAILED)
                ->setFinishedAt(new \DateTimeImmutable())
                ->setMessage($exception->getMessage())
                ->addLog(['event' => 'falha_transformacao', 'error' => $exception->getMessage()])
            ;

            $this->entityManager->flush();

            $this->logger->error('Falha na transformação do arquivo RAW {id}: {message}', [
                'id' => $rawFile->getId(),
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);

            throw $exception;
        }
    }

    public function generateQualityReport(RawFile $rawFile): DataQualityReport
    {
        $preview = $this->datasetPreviewService->generatePreview($rawFile);
        $mappings = $this->columnMappingRepository->findActiveByPackage($rawFile->getProviderPackage());
        $normalizedRows = $this->normalizeDataset($preview['rows'], $mappings, null);
        $stats = $this->validationService->analyzeDataset($normalizedRows, $mappings);

        $report = $this->buildQualityReport($rawFile, $stats);
        $this->entityManager->flush();

        return $report;
    }

    private function normalizeDataset(array $rows, array $mappings, ?IngestionRun $run): array
    {
        if (empty($mappings)) {
            return $rows;
        }

        $normalized = [];
        foreach ($rows as $row) {
            $normalized[] = $this->normalizationService->normalizeRow($row, $mappings);
        }

        return $normalized;
    }

    private function buildQualityReport(RawFile $rawFile, array $stats): DataQualityReport
    {
        $existing = $this->qualityReportRepository->findLatestForRawFile($rawFile);
        $report = $existing ?? new DataQualityReport();

        $report
            ->setRawFile($rawFile)
            ->setTotalRows($stats['total_rows'])
            ->setValidRows($stats['valid_rows'])
            ->setInvalidRows($stats['invalid_rows'])
            ->setDuplicatedRows($stats['duplicated_rows'])
            ->setNullFields($stats['null_fields'])
            ->setValidationErrors($stats['validation_errors'])
            ->setGeneratedAt(new \DateTimeImmutable())
        ;

        if (null === $existing) {
            $this->entityManager->persist($report);
        }

        return $report;
    }

    private function importToStaging(RawFile $rawFile, array $rows): string
    {
        $provider = $rawFile->getDataProvider();
        $package = $rawFile->getProviderPackage();

        $providerSlug = $this->slugify($provider->getName());
        $packageSlug = $this->slugify($package->getTitle() ?? $package->getPackageId());
        $now = new \DateTimeImmutable();

        $stagingDir = sprintf(
            '%s/storage/staging/%s/%s/%s/%s',
            $this->projectDir,
            $providerSlug,
            $packageSlug,
            $now->format('Y'),
            $now->format('m')
        );

        if (!is_dir($stagingDir)) {
            mkdir($stagingDir, 0755, true);
        }

        $filename = sprintf('raw_%d_%s.json', $rawFile->getId(), $now->format('Ymd_His'));
        $stagingPath = $stagingDir.'/'.$filename;

        file_put_contents($stagingPath, json_encode([
            'raw_file_id' => $rawFile->getId(),
            'provider' => $provider->getName(),
            'package' => $package->getTitle() ?? $package->getPackageId(),
            'generated_at' => $now->format('Y-m-d H:i:s'),
            'total_rows' => count($rows),
            'rows' => $rows,
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return str_replace($this->projectDir.'/', '', $stagingPath);
    }

    private function createTransformationRun(RawFile $rawFile): IngestionRun
    {
        $run = (new IngestionRun())
            ->setDataProvider($rawFile->getDataProvider())
            ->setProviderPackage($rawFile->getProviderPackage())
            ->setDatasetResource($rawFile->getDatasetResource())
            ->setStatus(IngestionRun::STATUS_RUNNING)
            ->setStartedAt(new \DateTimeImmutable())
            ->setMessage(sprintf('Transformação do arquivo RAW %d iniciada.', $rawFile->getId()))
        ;

        $this->entityManager->persist($run);
        $this->entityManager->flush();

        return $run;
    }

    private function slugify(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? $text;

        return trim($text, '-');
    }
}
