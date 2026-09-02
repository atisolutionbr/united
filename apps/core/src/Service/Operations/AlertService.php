<?php

namespace App\Service\Operations;

use App\Entity\Operations\Alert;
use App\Repository\Operations\AlertRepository;
use Doctrine\ORM\EntityManagerInterface;

class AlertService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AlertRepository $alertRepository,
    ) {}

    public function create(
        string $type,
        string $level,
        string $title,
        string $message,
        ?string $source = null,
        ?string $sourceId = null,
        array $metadata = [],
    ): Alert {
        $alert = new Alert();
        $alert->setType($type);
        $alert->setLevel($level);
        $alert->setTitle($title);
        $alert->setMessage($message);
        $alert->setSource($source);
        $alert->setSourceId($sourceId);
        $alert->setStatus(Alert::STATUS_ACTIVE);
        if (!empty($metadata)) { $alert->setMetadata($metadata); }
        $this->em->persist($alert);
        $this->em->flush();
        return $alert;
    }

    public function acknowledge(int $alertId, string $acknowledgedBy): bool
    {
        $alert = $this->em->find(Alert::class, $alertId);
        if ($alert === null) { return false; }
        $alert->setStatus(Alert::STATUS_ACKNOWLEDGED);
        $alert->setAcknowledgedBy($acknowledgedBy);
        $alert->setAcknowledgedAt(new \DateTimeImmutable());
        $this->em->flush();
        return true;
    }

    public function resolve(int $alertId): bool
    {
        $alert = $this->em->find(Alert::class, $alertId);
        if ($alert === null) { return false; }
        $alert->setStatus(Alert::STATUS_RESOLVED);
        $alert->setResolvedAt(new \DateTimeImmutable());
        $this->em->flush();
        return true;
    }

    public function pipelineFailed(string $pipelineName, string $error, ?string $executionId = null): Alert
    {
        return $this->create(
            type: Alert::TYPE_PIPELINE_FAILED,
            level: Alert::LEVEL_CRITICAL,
            title: "Pipeline falhou: {$pipelineName}",
            message: $error,
            source: 'pipeline',
            sourceId: $executionId,
            metadata: ['pipeline' => $pipelineName],
        );
    }

    public function serviceOffline(string $service): Alert
    {
        return $this->create(
            type: Alert::TYPE_AI_UNAVAILABLE,
            level: Alert::LEVEL_WARNING,
            title: "Serviço indisponível: {$service}",
            message: "O serviço {$service} não está respondendo. Verifique os containers Docker.",
            source: $service,
        );
    }

    public function getSummary(): array
    {
        return [
            'active' => $this->alertRepository->countActive(),
            'critical' => $this->alertRepository->countCritical(),
        ];
    }
}
