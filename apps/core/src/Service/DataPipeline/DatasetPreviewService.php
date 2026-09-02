<?php

namespace App\Service\DataPipeline;

use App\Entity\Data\DatasetSchema;
use App\Entity\Data\RawFile;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Reader;
use PhpOffice\PhpSpreadsheet\IOFactory;

final class DatasetPreviewService
{
    public function __construct(
        private readonly RawFileStorageService $rawFileStorageService,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{
     *     previewAvailable: bool,
     *     previewMessage: string|null,
     *     datasetName: string,
     *     providerName: string,
     *     packageName: string,
     *     fileType: string,
     *     totalRows: int,
     *     totalColumns: int,
     *     nullFields: int,
     *     encoding: string,
     *     detectedType: string,
     *     headers: list<string>,
     *     rows: list<array<string, scalar|null>>,
     *     schemas: list<array{columnName: string, detectedType: string, nullable: bool, sampleValue: ?string}>
     * }
     */
    public function generatePreview(RawFile $rawFile, int $limit = 50): array
    {
        $absolutePath = $this->rawFileStorageService->resolveAbsolutePath($rawFile);
        if (!is_file($absolutePath)) {
            throw new \RuntimeException('O arquivo RAW solicitado não existe mais no storage local.');
        }

        $extension = strtolower((string) $rawFile->getExtension());

        return match ($extension) {
            'csv' => $this->analyzeCsv($rawFile, $absolutePath, $limit),
            'xlsx' => $this->analyzeXlsx($rawFile, $absolutePath, $limit),
            default => $this->buildUnsupportedPreview($rawFile),
        };
    }

    public function syncSchema(RawFile $rawFile, array $preview): int
    {
        foreach ($rawFile->getSchemas()->toArray() as $schema) {
            $this->entityManager->remove($schema);
        }

        $persisted = 0;
        foreach ($preview['schemas'] as $column) {
            $schema = (new DatasetSchema())
                ->setRawFile($rawFile)
                ->setColumnName($column['columnName'])
                ->setDetectedType($column['detectedType'])
                ->setNullable($column['nullable'])
                ->setSampleValue($column['sampleValue'])
            ;

            $this->entityManager->persist($schema);
            ++$persisted;
        }

        return $persisted;
    }

    private function analyzeCsv(RawFile $rawFile, string $absolutePath, int $limit): array
    {
        $delimiter = $this->detectCsvDelimiter($absolutePath);
        $encoding = $this->detectEncoding($absolutePath);
        $reader = Reader::createFromPath($absolutePath, 'r');
        $reader->setDelimiter($delimiter);

        $headers = [];
        $rows = [];
        $stats = [];
        $totalRows = 0;
        $nullFields = 0;

        foreach ($reader->getRecords() as $index => $record) {
            if (0 === $index) {
                $headers = $this->normalizeHeaders(array_map(fn (mixed $value): string => $this->normalizeTextValue($value, $encoding), $record));
                $stats = $this->initializeColumnStats($headers);
                continue;
            }

            ++$totalRows;
            $mappedRow = $this->mapRowToHeaders($headers, $record, $encoding);
            $nullFields += $this->collectColumnStats($stats, $mappedRow);

            if (count($rows) < $limit) {
                $rows[] = $mappedRow;
            }
        }

        return $this->buildPreviewPayload($rawFile, 'CSV', $encoding, $headers, $rows, $totalRows, $nullFields, $stats);
    }

    private function analyzeXlsx(RawFile $rawFile, string $absolutePath, int $limit): array
    {
        $spreadsheet = IOFactory::load($absolutePath);
        $sheet = $spreadsheet->getSheet(0);
        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();
        $headerRow = $sheet->rangeToArray(sprintf('A1:%s1', $highestColumn), null, true, false)[0] ?? [];
        $headers = $this->normalizeHeaders(array_map(fn (mixed $value): string => $this->normalizeTextValue($value, 'UTF-8'), $headerRow));
        $stats = $this->initializeColumnStats($headers);
        $rows = [];
        $nullFields = 0;

        for ($rowNumber = 2; $rowNumber <= $highestRow; ++$rowNumber) {
            $rowValues = $sheet->rangeToArray(sprintf('A%d:%s%d', $rowNumber, $highestColumn, $rowNumber), null, true, false)[0] ?? [];
            $mappedRow = $this->mapRowToHeaders($headers, $rowValues, 'UTF-8');
            $nullFields += $this->collectColumnStats($stats, $mappedRow);

            if (count($rows) < $limit) {
                $rows[] = $mappedRow;
            }
        }

        return $this->buildPreviewPayload($rawFile, 'XLSX', 'UTF-8 worksheet', $headers, $rows, max(0, $highestRow - 1), $nullFields, $stats);
    }

    /**
     * @param list<string> $headers
     * @param list<array<string, scalar|null>> $rows
     * @param array<string, array{values: list<string>, nullable: bool, sampleValue: ?string}> $stats
     *
     * @return array{
     *     previewAvailable: bool,
     *     previewMessage: string|null,
     *     datasetName: string,
     *     providerName: string,
     *     packageName: string,
     *     fileType: string,
     *     totalRows: int,
     *     totalColumns: int,
     *     nullFields: int,
     *     encoding: string,
     *     detectedType: string,
     *     headers: list<string>,
     *     rows: list<array<string, scalar|null>>,
     *     schemas: list<array{columnName: string, detectedType: string, nullable: bool, sampleValue: ?string}>
     * }
     */
    private function buildPreviewPayload(RawFile $rawFile, string $detectedType, string $encoding, array $headers, array $rows, int $totalRows, int $nullFields, array $stats): array
    {
        $schemas = [];
        foreach ($headers as $header) {
            $columnStats = $stats[$header] ?? ['values' => [], 'nullable' => true, 'sampleValue' => null];
            $schemas[] = [
                'columnName' => $header,
                'detectedType' => $this->detectType($columnStats['values']),
                'nullable' => $columnStats['nullable'],
                'sampleValue' => $columnStats['sampleValue'],
            ];
        }

        return [
            'previewAvailable' => true,
            'previewMessage' => null,
            'datasetName' => $rawFile->getDatasetResource()->getName() ?: $rawFile->getProviderPackage()->getDisplayTitle(),
            'providerName' => $rawFile->getDataProvider()->getName(),
            'packageName' => $rawFile->getProviderPackage()->getDisplayTitle(),
            'fileType' => strtoupper((string) $rawFile->getExtension()),
            'totalRows' => $totalRows,
            'totalColumns' => count($headers),
            'nullFields' => $nullFields,
            'encoding' => $encoding,
            'detectedType' => $detectedType,
            'headers' => $headers,
            'rows' => $rows,
            'schemas' => $schemas,
        ];
    }

    /**
     * @return array<string, array{values: list<string>, nullable: bool, sampleValue: ?string}>
     */
    private function initializeColumnStats(array $headers): array
    {
        $stats = [];

        foreach ($headers as $header) {
            $stats[$header] = [
                'values' => [],
                'nullable' => false,
                'sampleValue' => null,
            ];
        }

        return $stats;
    }

    /**
     * @param list<string> $headers
     * @param array<int, mixed> $rowValues
     *
     * @return array<string, scalar|null>
     */
    private function mapRowToHeaders(array $headers, array $rowValues, string $encoding): array
    {
        $mapped = [];

        foreach ($headers as $index => $header) {
            $value = $rowValues[$index] ?? null;
            $normalizedValue = $this->normalizeCellValue($value, $encoding);
            $mapped[$header] = '' === $normalizedValue ? null : $normalizedValue;
        }

        return $mapped;
    }

    /**
     * @param array<string, array{values: list<string>, nullable: bool, sampleValue: ?string}> $stats
     * @param array<string, scalar|null> $mappedRow
     */
    private function collectColumnStats(array &$stats, array $mappedRow): int
    {
        $nullFields = 0;

        foreach ($mappedRow as $header => $value) {
            if (null === $value || '' === $value) {
                $stats[$header]['nullable'] = true;
                ++$nullFields;

                continue;
            }

            $stringValue = trim((string) $value);
            if (null === $stats[$header]['sampleValue']) {
                $stats[$header]['sampleValue'] = $stringValue;
            }

            if (count($stats[$header]['values']) < 200) {
                $stats[$header]['values'][] = $stringValue;
            }
        }

        return $nullFields;
    }

    /**
     * @param list<string> $headers
     *
     * @return list<string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $normalized = [];
        $counts = [];

        foreach ($headers as $index => $header) {
            $name = '' === trim($header) ? sprintf('coluna_%d', $index + 1) : $header;
            $counts[$name] = ($counts[$name] ?? 0) + 1;
            $normalized[] = 1 === $counts[$name] ? $name : sprintf('%s_%d', $name, $counts[$name]);
        }

        return $normalized;
    }

    /**
     * @param list<string> $values
     */
    private function detectType(array $values): string
    {
        if ([] === $values) {
            return 'texto';
        }

        if ($this->allValuesMatch($values, static fn (string $value): bool => in_array(strtolower($value), ['true', 'false', 'sim', 'nao', 'não', '0', '1'], true))) {
            return 'boolean';
        }

        if ($this->allValuesMatch($values, static fn (string $value): bool => (bool) preg_match('/^-?\d+$/', $value))) {
            return 'inteiro';
        }

        if ($this->allValuesMatch($values, static fn (string $value): bool => is_numeric(str_replace(',', '.', $value)))) {
            return 'decimal';
        }

        if ($this->allValuesMatch($values, function (string $value): bool {
            try {
                new \DateTimeImmutable($value);

                return true;
            } catch (\Throwable) {
                return false;
            }
        })) {
            return 'data';
        }

        return 'texto';
    }

    /**
     * @param list<string> $values
     * @param callable(string): bool $callback
     */
    private function allValuesMatch(array $values, callable $callback): bool
    {
        foreach ($values as $value) {
            if (!$callback($value)) {
                return false;
            }
        }

        return true;
    }

    private function detectCsvDelimiter(string $absolutePath): string
    {
        $handle = fopen($absolutePath, 'r');
        $firstLine = false === $handle ? '' : (string) fgets($handle);

        if (is_resource($handle)) {
            fclose($handle);
        }

        $delimiters = [',', ';', "\t", '|'];
        $scores = [];

        foreach ($delimiters as $delimiter) {
            $scores[$delimiter] = substr_count($firstLine, $delimiter);
        }

        arsort($scores);

        return (string) array_key_first($scores);
    }

    private function detectEncoding(string $absolutePath): string
    {
        $sample = file_get_contents($absolutePath, false, null, 0, 4096);
        if (false === $sample || '' === $sample) {
            return 'desconhecido';
        }

        $detected = mb_detect_encoding($sample, ['UTF-8', 'ISO-8859-1', 'Windows-1252', 'ASCII'], true);

        return false === $detected ? 'desconhecido' : $detected;
    }

    private function normalizeTextValue(mixed $value, string $encoding): string
    {
        if (!is_scalar($value)) {
            return '';
        }

        $text = trim((string) $value);
        if ('' === $text) {
            return '';
        }

        if ('desconhecido' === $encoding || 'UTF-8' === strtoupper($encoding) || 'ASCII' === strtoupper($encoding)) {
            return $text;
        }

        return trim((string) mb_convert_encoding($text, 'UTF-8', $encoding));
    }

    private function normalizeCellValue(mixed $value, string $encoding): string|int|float|bool|null
    {
        if (null === $value || !is_scalar($value)) {
            return null;
        }

        if (is_string($value)) {
            return $this->normalizeTextValue($value, $encoding);
        }

        return $value;
    }

    /**
     * @return array{
     *     previewAvailable: bool,
     *     previewMessage: string,
     *     datasetName: string,
     *     providerName: string,
     *     packageName: string,
     *     fileType: string,
     *     totalRows: int,
     *     totalColumns: int,
     *     nullFields: int,
     *     encoding: string,
     *     detectedType: string,
     *     headers: list<string>,
     *     rows: list<array<string, scalar|null>>,
     *     schemas: list<array{columnName: string, detectedType: string, nullable: bool, sampleValue: ?string}>
     * }
     */
    private function buildUnsupportedPreview(RawFile $rawFile): array
    {
        return [
            'previewAvailable' => false,
            'previewMessage' => sprintf('Preview ainda não disponível para arquivos %s.', strtoupper((string) $rawFile->getExtension())),
            'datasetName' => $rawFile->getDatasetResource()->getName() ?: $rawFile->getProviderPackage()->getDisplayTitle(),
            'providerName' => $rawFile->getDataProvider()->getName(),
            'packageName' => $rawFile->getProviderPackage()->getDisplayTitle(),
            'fileType' => strtoupper((string) $rawFile->getExtension()),
            'totalRows' => 0,
            'totalColumns' => 0,
            'nullFields' => 0,
            'encoding' => 'n/a',
            'detectedType' => strtoupper((string) $rawFile->getExtension()),
            'headers' => [],
            'rows' => [],
            'schemas' => [],
        ];
    }
}