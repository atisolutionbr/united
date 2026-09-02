<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'erp_connection_log')]
#[ORM\Index(columns: ['connection_id', 'created_at'], name: 'IDX_ERP_LOG_CONNECTION_DATE')]
class ErpConnectionLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ErpConnection::class)]
    #[ORM\JoinColumn(name: 'connection_id', nullable: true, onDelete: 'SET NULL')]
    private ?ErpConnection $connection;

    #[ORM\Column(name: 'service_name', length: 160)]
    private string $serviceName;

    #[ORM\Column(name: 'port_name', length: 120, nullable: true)]
    private ?string $portName;

    #[ORM\Column(length: 500)]
    private string $endpoint;

    #[ORM\Column(name: 'method_name', length: 80)]
    private string $methodName;

    /** @var array<string, mixed> */
    #[ORM\Column(name: 'payload_masked', type: Types::JSON)]
    private array $payloadMasked;

    #[ORM\Column(name: 'response_received', type: Types::TEXT, nullable: true)]
    private ?string $responseReceived;

    #[ORM\Column(name: 'status_http', type: Types::SMALLINT, nullable: true)]
    private ?int $statusHttp;

    #[ORM\Column]
    private bool $success;

    #[ORM\Column(name: 'error_code', length: 80, nullable: true)]
    private ?string $errorCode;

    #[ORM\Column(name: 'error_message', type: Types::TEXT, nullable: true)]
    private ?string $errorMessage;

    #[ORM\Column(name: 'response_time_ms')]
    private int $responseTimeMs;

    #[ORM\Column(name: 'started_at')]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(name: 'finished_at')]
    private \DateTimeImmutable $finishedAt;

    #[ORM\Column(name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    /** @param array<string, mixed> $payloadMasked */
    public function __construct(
        ?ErpConnection $connection,
        string $serviceName,
        ?string $portName,
        string $endpoint,
        string $methodName,
        array $payloadMasked,
        ?string $responseReceived,
        ?int $statusHttp,
        bool $success,
        ?string $errorCode,
        ?string $errorMessage,
        int $responseTimeMs,
        \DateTimeImmutable $startedAt,
        \DateTimeImmutable $finishedAt,
    ) {
        $this->connection = $connection;
        $this->serviceName = $serviceName;
        $this->portName = $portName;
        $this->endpoint = $endpoint;
        $this->methodName = $methodName;
        $this->payloadMasked = $payloadMasked;
        $this->responseReceived = $responseReceived;
        $this->statusHttp = $statusHttp;
        $this->success = $success;
        $this->errorCode = $errorCode;
        $this->errorMessage = $errorMessage;
        $this->responseTimeMs = $responseTimeMs;
        $this->startedAt = $startedAt;
        $this->finishedAt = $finishedAt;
        $this->createdAt = $finishedAt;
    }

    public function getServiceName(): string
    {
        return $this->serviceName;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getStatusHttp(): ?int
    {
        return $this->statusHttp;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getResponseTimeMs(): int
    {
        return $this->responseTimeMs;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
