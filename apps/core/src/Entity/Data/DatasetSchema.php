<?php

namespace App\Entity\Data;

use App\Repository\Data\DatasetSchemaRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DatasetSchemaRepository::class)]
#[ORM\Table(name: 'dataset_schemas')]
class DatasetSchema
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'schemas')]
    #[ORM\JoinColumn(name: 'raw_file_id', nullable: false, onDelete: 'CASCADE')]
    private RawFile $rawFile;

    #[ORM\Column(name: 'column_name', length: 255)]
    private string $columnName;

    #[ORM\Column(name: 'detected_type', length: 50)]
    private string $detectedType;

    #[ORM\Column(options: ['default' => true])]
    private bool $nullable = true;

    #[ORM\Column(name: 'sample_value', type: Types::TEXT, nullable: true)]
    private ?string $sampleValue = null;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRawFile(): RawFile
    {
        return $this->rawFile;
    }

    public function setRawFile(RawFile $rawFile): self
    {
        $this->rawFile = $rawFile;

        return $this;
    }

    public function getColumnName(): string
    {
        return $this->columnName;
    }

    public function setColumnName(string $columnName): self
    {
        $this->columnName = trim($columnName);

        return $this;
    }

    public function getDetectedType(): string
    {
        return $this->detectedType;
    }

    public function setDetectedType(string $detectedType): self
    {
        $this->detectedType = trim($detectedType);

        return $this;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    public function setNullable(bool $nullable): self
    {
        $this->nullable = $nullable;

        return $this;
    }

    public function getSampleValue(): ?string
    {
        return $this->sampleValue;
    }

    public function setSampleValue(?string $sampleValue): self
    {
        $this->sampleValue = null === $sampleValue ? null : trim($sampleValue);

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}