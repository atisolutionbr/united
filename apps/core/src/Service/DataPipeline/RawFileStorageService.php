<?php

namespace App\Service\DataPipeline;

use App\Entity\Data\RawFile;
use App\Entity\DatasetResource;
use App\Repository\Data\RawFileRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class RawFileStorageService
{
    private const PREVIEWABLE_EXTENSIONS = ['csv', 'xlsx'];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly RawFileRepository $rawFileRepository,
        private readonly LoggerInterface $logger,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    /**
     * @return array{rawFile: RawFile, duplicate: bool, bytes: int, hash: string, message: string}
     */
    public function download(DatasetResource $resource): array
    {
        $timeout = (float) (getenv('CKAN_RESOURCE_TIMEOUT') ?: 90);

        try {
            $response = $this->httpClient->request('GET', $resource->getUrl(), [
                'headers' => ['Accept' => '*/*'],
                'max_redirects' => 5,
                'timeout' => $timeout,
            ]);
        } catch (ExceptionInterface $exception) {
            throw new \RuntimeException('Não foi possível conectar ao resource remoto para download.', 0, $exception);
        }

        $statusCode = $response->getStatusCode();
        if (200 !== $statusCode) {
            throw new \RuntimeException(sprintf('O resource respondeu com HTTP %d em vez de 200.', $statusCode));
        }

        $content = $response->getContent(false);
        if ('' === $content) {
            throw new \RuntimeException('O resource retornou conteúdo vazio.');
        }

        $headers = $response->getHeaders(false);
        $mimeType = $this->detectMimeType($headers, $content);
        $originalName = $this->resolveOriginalName($resource, $headers);
        $extension = $this->resolveExtension($resource, $originalName, $mimeType);
        $hash = hash('sha256', $content);
        $downloadedAt = new \DateTimeImmutable();
        $existing = $this->rawFileRepository->findOneByHash($hash);

        if (null !== $existing && $this->fileExists($existing)) {
            $rawFile = $this->createRawFileRecord($resource, $originalName, $existing->getStoredName(), $existing->getLocalPath(), $mimeType, $extension, strlen($content), $hash);
            $rawFile
                ->setDownloadStatus(RawFile::STATUS_DUPLICATE)
                ->setAlreadyProcessed(true)
                ->setDownloadedAt($downloadedAt)
            ;

            $this->entityManager->persist($rawFile);
            $this->entityManager->flush();
            $this->updateResourceMetadata($resource, $rawFile, $hash, strlen($content), true);
            $this->entityManager->flush();

            return [
                'rawFile' => $rawFile,
                'duplicate' => true,
                'bytes' => strlen($content),
                'hash' => $hash,
                'message' => sprintf('Arquivo já existia no storage RAW: %s.', $existing->getLocalPath()),
            ];
        }

        $relativeDirectory = $this->buildRelativeDirectory($resource, $downloadedAt);
        $absoluteDirectory = $this->resolveAbsoluteDirectory($relativeDirectory);
        $this->ensureDirectory($absoluteDirectory);

        $storedName = $this->buildStoredName($resource, $originalName, $extension, $downloadedAt);
        $absolutePath = $absoluteDirectory.'/'.$storedName;
        $relativePath = trim($relativeDirectory.'/'.$storedName, '/');

        $bytesWritten = file_put_contents($absolutePath, $content, LOCK_EX);
        if (false === $bytesWritten) {
            throw new \RuntimeException('Não foi possível gravar o arquivo baixado no storage RAW.');
        }

        $rawFile = $this->createRawFileRecord($resource, $originalName, $storedName, $relativePath, $mimeType, $extension, $bytesWritten, $hash);
        $rawFile
            ->setDownloadStatus(RawFile::STATUS_DOWNLOADED)
            ->setDownloadedAt($downloadedAt)
        ;

        $this->entityManager->persist($rawFile);
        $this->entityManager->flush();
        $this->updateResourceMetadata($resource, $rawFile, $hash, $bytesWritten, false);
        $this->entityManager->flush();

        return [
            'rawFile' => $rawFile,
            'duplicate' => false,
            'bytes' => $bytesWritten,
            'hash' => $hash,
            'message' => sprintf('Arquivo salvo em %s.', $relativePath),
        ];
    }

    public function resolveAbsolutePath(RawFile $rawFile): string
    {
        $relativePath = trim(str_replace('\\', '/', $rawFile->getLocalPath()), '/');
        $prefix = 'storage/raw/';
        $suffix = str_starts_with($relativePath, $prefix) ? substr($relativePath, strlen($prefix)) : $relativePath;

        return rtrim($this->getStorageRoot(), '/').'/'.ltrim($suffix, '/');
    }

    public function deleteFileIfUnused(RawFile $rawFile): void
    {
        $absolutePath = $this->resolveAbsolutePath($rawFile);

        if ($this->rawFileRepository->countByLocalPath($rawFile->getLocalPath()) > 1 || !is_file($absolutePath)) {
            return;
        }

        @unlink($absolutePath);
    }

    public function isPreviewable(RawFile $rawFile): bool
    {
        return in_array(strtolower((string) $rawFile->getExtension()), self::PREVIEWABLE_EXTENSIONS, true);
    }

    private function getStorageRoot(): string
    {
        $configured = trim((string) getenv('RAW_STORAGE_DIR'));
        if ('' !== $configured) {
            return rtrim(str_replace('\\', '/', $configured), '/');
        }

        return str_replace('\\', '/', dirname($this->projectDir, 2).'/storage/raw');
    }

    private function buildRelativeDirectory(DatasetResource $resource, \DateTimeImmutable $downloadedAt): string
    {
        $provider = $resource->getProviderPackage()->getDataProvider();
        $package = $resource->getProviderPackage();

        return sprintf(
            'storage/raw/%s/%s/%s/%s',
            $this->slugify($provider->getName()),
            $this->slugify($package->getDisplayTitle()),
            $downloadedAt->format('Y'),
            $downloadedAt->format('m')
        );
    }

    private function resolveAbsoluteDirectory(string $relativeDirectory): string
    {
        $relativeDirectory = trim(str_replace('\\', '/', $relativeDirectory), '/');
        $prefix = 'storage/raw/';
        $suffix = str_starts_with($relativeDirectory, $prefix) ? substr($relativeDirectory, strlen($prefix)) : $relativeDirectory;

        return rtrim($this->getStorageRoot(), '/').'/'.ltrim($suffix, '/');
    }

    private function ensureDirectory(string $directory): void
    {
        if ((is_dir($directory) || mkdir($directory, 0775, true)) && is_writable($directory)) {
            return;
        }

        throw new \RuntimeException(sprintf('O diretório %s não está disponível para escrita.', $directory));
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private function detectMimeType(array $headers, string $content): ?string
    {
        $headerType = $headers['content-type'][0] ?? null;
        if (is_string($headerType) && '' !== trim($headerType)) {
            return strtolower(trim(explode(';', $headerType)[0]));
        }

        $detected = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content);

        return false === $detected ? null : strtolower($detected);
    }

    /**
     * @param array<string, list<string>> $headers
     */
    private function resolveOriginalName(DatasetResource $resource, array $headers): string
    {
        $contentDisposition = $headers['content-disposition'][0] ?? null;

        if (is_string($contentDisposition)) {
            if (preg_match("/filename\\*=UTF-8''([^;]+)/i", $contentDisposition, $matches) === 1) {
                return rawurldecode(trim($matches[1], "\"' "));
            }

            if (preg_match('/filename="?([^";]+)"?/i', $contentDisposition, $matches) === 1) {
                return trim($matches[1]);
            }
        }

        $path = parse_url($resource->getUrl(), PHP_URL_PATH);
        $path = is_string($path) ? $path : '';
        $basename = basename($path);

        if ('' !== trim($basename) && '/' !== $basename) {
            return $basename;
        }

        $base = $resource->getName() ?: $resource->getResourceId();

        return $this->slugify($base).($resource->getFormat() ? '.'.strtolower($this->slugify($resource->getFormat())) : '');
    }

    private function resolveExtension(DatasetResource $resource, string $originalName, ?string $mimeType): string
    {
        $extension = strtolower((string) pathinfo($originalName, PATHINFO_EXTENSION));
        if ('' !== $extension) {
            return $extension;
        }

        $format = strtolower($this->slugify((string) $resource->getFormat()));
        if (in_array($format, ['csv', 'xlsx', 'json', 'zip'], true)) {
            return $format;
        }

        return match ($mimeType) {
            'text/csv', 'application/csv', 'application/vnd.ms-excel' => 'csv',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/json', 'application/ld+json' => 'json',
            'application/zip', 'application/x-zip-compressed' => 'zip',
            default => 'dat',
        };
    }

    private function buildStoredName(DatasetResource $resource, string $originalName, string $extension, \DateTimeImmutable $downloadedAt): string
    {
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $safeBaseName = $this->slugify('' === trim($baseName) ? $resource->getResourceId() : $baseName);

        return sprintf(
            '%s-%s-%s.%s',
            $downloadedAt->format('YmdHis'),
            $this->slugify($resource->getResourceId()),
            $safeBaseName,
            $extension
        );
    }

    private function createRawFileRecord(DatasetResource $resource, string $originalName, string $storedName, string $localPath, ?string $mimeType, string $extension, int $fileSize, string $hash): RawFile
    {
        return (new RawFile())
            ->setDatasetResource($resource)
            ->setProviderPackage($resource->getProviderPackage())
            ->setDataProvider($resource->getProviderPackage()->getDataProvider())
            ->setOriginalName($originalName)
            ->setStoredName($storedName)
            ->setLocalPath($localPath)
            ->setMimeType($mimeType)
            ->setExtension($extension)
            ->setFileSize($fileSize)
            ->setFileHash($hash);
    }

    private function updateResourceMetadata(DatasetResource $resource, RawFile $rawFile, string $hash, int $bytes, bool $duplicate): void
    {
        $metadata = $resource->getRawMetadata();
        $metadata['download'] = [
            'raw_file_id' => $rawFile->getId(),
            'stored_path' => $rawFile->getLocalPath(),
            'downloaded_at' => $rawFile->getDownloadedAt()?->format(DATE_ATOM),
            'mime_type' => $rawFile->getMimeType(),
            'extension' => $rawFile->getExtension(),
            'bytes' => $bytes,
            'checksum' => $hash,
            'duplicate' => $duplicate,
        ];

        $resource
            ->setRawMetadata($metadata)
            ->setHash($hash)
            ->setSize($bytes)
        ;
    }

    private function fileExists(RawFile $rawFile): bool
    {
        return is_file($this->resolveAbsolutePath($rawFile));
    }

    private function slugify(string $value): string
    {
        $normalized = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $normalized = false === $normalized ? $value : $normalized;
        $normalized = strtolower(trim($normalized));
        $normalized = preg_replace('/[^a-z0-9]+/i', '-', $normalized) ?? '';

        return '' === trim($normalized, '-') ? 'arquivo' : trim($normalized, '-');
    }
}