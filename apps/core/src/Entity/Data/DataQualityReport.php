<?php

namespace App\Entity\Data;

use App\Repository\Data\DataQualityReportRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DataQualityReportRepository::class)]
#[ORM\Table(name: 'dataset_quality_reports')]
#[ORM\Index(name: 'idx_quality_reports_raw_file', columns: ['raw_file_id'])]
#[ORM\Index(name: 'idx_quality_reports_generated_at', columns: ['generated_at'])]
class DataQualityReport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'raw_file_id', nullable: false, onDelete: 'CASCADE')]
    private RawFile $rawFile;

    #[ORM\Column(name: 'total_rows', options: ['default' => 0])]
    private int $totalRows = 0;

    #[ORM\Column(name: 'valid_rows', options: ['default' => 0])]
    private int $validRows = 0;

    #[ORM\Column(name: 'invalid_rows', options: ['default' => 0])]
    private int $invalidRows = 0;

    #[ORM\Column(name: 'duplicated_rows', options: ['default' => 0])]
    private int $duplicatedRows = 0;

    #[ORM\Column(name: 'null_fields', options: ['default' => 0])]
    private int $nullFields = 0;

    #[ORM\Column(name: 'validation_errors', type: Types::JSON)]
    private array $validationErrors = [];

    #[ORM\Column(name: 'generated_at')]
    private \DateTimeImmutable $generatedAt;

    public function __construct()
    {
        $this->generatedAt = new \DateTimeImmutable();
    }

    public function getQualityScore(): float
    {
        if ($this->totalRows === 0) {
            return 0.0;
        }

        return round(($this->validRows / $this->totalRows) * 100, 1);
    }

    public function getId(): ?int { return $this->id; }

    public function getRawFile(): RawFile { return $this->rawFile; }
    public function setRawFile(RawFile $rawFile): self { $this->rawFile = $rawFile; return $this; }

    public function getTotalRows(): int { return $this->totalRows; }
    public function setTotalRows(int $totalRows): self { $this->totalRows = $totalRows; return $this; }

    public function getValidRows(): int { return $this->validRows; }
    public function setValidRows(int $validRows): self { $this->validRows = $validRows; return $this; }

    public function getInvalidRows(): int { return $this->invalidRows; }
    public function setInvalidRows(int $invalidRows): self { $this->invalidRows = $invalidRows; return $this; }

    public function getDuplicatedRows(): int { return $this->duplicatedRows; }
    public function setDuplicatedRows(int $duplicatedRows): self { $this->duplicatedRows = $duplicatedRows; return $this; }

    public function getNullFields(): int { return $this->nullFields; }
    public function setNullFields(int $nullFields): self { $this->nullFields = $nullFields; return $this; }

    public function getValidationErrors(): array { return $this->validationErrors; }
    public function setValidationErrors(array $validationErrors): self { $this->validationErrors = $validationErrors; return $this; }

    public function getGeneratedAt(): \DateTimeImmutable { return $this->generatedAt; }
    public function setGeneratedAt(\DateTimeImmutable $generatedAt): self { $this->generatedAt = $generatedAt; return $this; }
}
