<?php

namespace App\Entity\AI;

use App\Repository\AI\AiEmbeddingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AiEmbeddingRepository::class)]
#[ORM\Table(name: 'ai_embeddings')]
#[ORM\Index(name: 'idx_ai_embeddings_source', columns: ['source_type', 'source_id'])]
class AiEmbedding
{
    public const SOURCE_DATASET = 'dataset';
    public const SOURCE_INDICATOR = 'indicator';
    public const SOURCE_WAREHOUSE_TABLE = 'warehouse_table';
    public const SOURCE_DOCUMENT = 'document';
    public const SOURCE_QUALITY_REPORT = 'quality_report';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'source_type', length: 50)]
    private string $sourceType;

    #[ORM\Column(name: 'source_id', length: 255)]
    private string $sourceId;

    #[ORM\Column(name: 'chunk_text', type: Types::TEXT)]
    private string $chunkText;

    #[ORM\Column(name: 'embedding_provider', length: 50)]
    private string $embeddingProvider;

    #[ORM\Column(name: 'embedding_model', length: 100)]
    private string $embeddingModel;

    #[ORM\Column(name: 'vector_id', length: 255, nullable: true)]
    private ?string $vectorId = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getSourceType(): string { return $this->sourceType; }
    public function setSourceType(string $sourceType): static { $this->sourceType = $sourceType; return $this; }
    public function getSourceId(): string { return $this->sourceId; }
    public function setSourceId(string $sourceId): static { $this->sourceId = $sourceId; return $this; }
    public function getChunkText(): string { return $this->chunkText; }
    public function setChunkText(string $chunkText): static { $this->chunkText = $chunkText; return $this; }
    public function getEmbeddingProvider(): string { return $this->embeddingProvider; }
    public function setEmbeddingProvider(string $embeddingProvider): static { $this->embeddingProvider = $embeddingProvider; return $this; }
    public function getEmbeddingModel(): string { return $this->embeddingModel; }
    public function setEmbeddingModel(string $embeddingModel): static { $this->embeddingModel = $embeddingModel; return $this; }
    public function getVectorId(): ?string { return $this->vectorId; }
    public function setVectorId(?string $vectorId): static { $this->vectorId = $vectorId; return $this; }
    public function getMetadata(): ?array { return $this->metadata; }
    public function setMetadata(?array $metadata): static { $this->metadata = $metadata; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
