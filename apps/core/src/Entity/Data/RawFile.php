<?php

namespace App\Entity\Data;

use App\Entity\DataProvider;
use App\Entity\DatasetResource;
use App\Entity\ProviderPackage;
use App\Repository\Data\RawFileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RawFileRepository::class)]
#[ORM\Table(name: 'raw_files')]
#[ORM\Index(name: 'idx_raw_files_hash', columns: ['file_hash'])]
#[ORM\Index(name: 'idx_raw_files_status', columns: ['download_status'])]
#[ORM\Index(name: 'idx_raw_files_downloaded_at', columns: ['downloaded_at'])]
#[ORM\HasLifecycleCallbacks]
class RawFile
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_DOWNLOADED = 'downloaded';
    public const STATUS_DUPLICATE = 'duplicate';
    public const STATUS_FAILED = 'failed';

    public const TRANSFORMATION_PENDING = 'pending';
    public const TRANSFORMATION_RUNNING = 'running';
    public const TRANSFORMATION_DONE = 'done';
    public const TRANSFORMATION_FAILED = 'failed';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'rawFiles')]
    #[ORM\JoinColumn(name: 'dataset_resource_id', nullable: false, onDelete: 'CASCADE')]
    private DatasetResource $datasetResource;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'provider_package_id', nullable: false, onDelete: 'CASCADE')]
    private ProviderPackage $providerPackage;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'data_provider_id', nullable: false, onDelete: 'CASCADE')]
    private DataProvider $dataProvider;

    #[ORM\Column(name: 'original_name', length: 255)]
    private string $originalName;

    #[ORM\Column(name: 'stored_name', length: 255)]
    private string $storedName;

    #[ORM\Column(name: 'local_path', length: 1024)]
    private string $localPath;

    #[ORM\Column(name: 'mime_type', length: 255, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $extension = null;

    #[ORM\Column(name: 'file_size', nullable: true)]
    private ?int $fileSize = null;

    #[ORM\Column(name: 'file_hash', length: 64, nullable: true)]
    private ?string $fileHash = null;

    #[ORM\Column(name: 'download_status', length: 30)]
    private string $downloadStatus = self::STATUS_PENDING;

    #[ORM\Column(name: 'already_processed', options: ['default' => false])]
    private bool $alreadyProcessed = false;

    #[ORM\Column(name: 'staging_path', length: 1024, nullable: true)]
    private ?string $stagingPath = null;

    #[ORM\Column(name: 'transformation_status', length: 30)]
    private string $transformationStatus = self::TRANSFORMATION_PENDING;

    #[ORM\Column(name: 'downloaded_at', nullable: true)]
    private ?\DateTimeImmutable $downloadedAt = null;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * @var Collection<int, DatasetSchema>
     */
    #[ORM\OneToMany(mappedBy: 'rawFile', targetEntity: DatasetSchema::class, orphanRemoval: true, cascade: ['persist'])]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $schemas;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->schemas = new ArrayCollection();
    }

    #[ORM\PreUpdate]
    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @return list<string>
     */
    public static function getAvailableStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_DOWNLOADED,
            self::STATUS_DUPLICATE,
            self::STATUS_FAILED,
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDatasetResource(): DatasetResource
    {
        return $this->datasetResource;
    }

    public function setDatasetResource(DatasetResource $datasetResource): self
    {
        $this->datasetResource = $datasetResource;

        return $this;
    }

    public function getProviderPackage(): ProviderPackage
    {
        return $this->providerPackage;
    }

    public function setProviderPackage(ProviderPackage $providerPackage): self
    {
        $this->providerPackage = $providerPackage;

        return $this;
    }

    public function getDataProvider(): DataProvider
    {
        return $this->dataProvider;
    }

    public function setDataProvider(DataProvider $dataProvider): self
    {
        $this->dataProvider = $dataProvider;

        return $this;
    }

    public function getOriginalName(): string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): self
    {
        $this->originalName = trim($originalName);

        return $this;
    }

    public function getStoredName(): string
    {
        return $this->storedName;
    }

    public function setStoredName(string $storedName): self
    {
        $this->storedName = trim($storedName);

        return $this;
    }

    public function getLocalPath(): string
    {
        return $this->localPath;
    }

    public function setLocalPath(string $localPath): self
    {
        $this->localPath = trim($localPath);

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): self
    {
        $this->mimeType = null === $mimeType ? null : trim($mimeType);

        return $this;
    }

    public function getExtension(): ?string
    {
        return $this->extension;
    }

    public function setExtension(?string $extension): self
    {
        $this->extension = null === $extension ? null : strtolower(trim($extension));

        return $this;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(?int $fileSize): self
    {
        $this->fileSize = $fileSize;

        return $this;
    }

    public function getFileHash(): ?string
    {
        return $this->fileHash;
    }

    public function setFileHash(?string $fileHash): self
    {
        $this->fileHash = null === $fileHash ? null : trim($fileHash);

        return $this;
    }

    public function getDownloadStatus(): string
    {
        return $this->downloadStatus;
    }

    public function setDownloadStatus(string $downloadStatus): self
    {
        $this->downloadStatus = trim($downloadStatus);

        return $this;
    }

    public function isAlreadyProcessed(): bool
    {
        return $this->alreadyProcessed;
    }

    public function setAlreadyProcessed(bool $alreadyProcessed): self
    {
        $this->alreadyProcessed = $alreadyProcessed;

        return $this;
    }

    public function getStagingPath(): ?string { return $this->stagingPath; }
    public function setStagingPath(?string $stagingPath): self { $this->stagingPath = $stagingPath; return $this; }

    public function getTransformationStatus(): string { return $this->transformationStatus; }
    public function setTransformationStatus(string $transformationStatus): self { $this->transformationStatus = $transformationStatus; return $this; }

    public function getDownloadedAt(): ?\DateTimeImmutable
    {
        return $this->downloadedAt;
    }

    public function setDownloadedAt(?\DateTimeImmutable $downloadedAt): self
    {
        $this->downloadedAt = $downloadedAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * @return Collection<int, DatasetSchema>
     */
    public function getSchemas(): Collection
    {
        return $this->schemas;
    }

    public function addSchema(DatasetSchema $schema): self
    {
        if (!$this->schemas->contains($schema)) {
            $this->schemas->add($schema);
            $schema->setRawFile($this);
        }

        return $this;
    }

    public function removeSchema(DatasetSchema $schema): self
    {
        $this->schemas->removeElement($schema);

        return $this;
    }
}