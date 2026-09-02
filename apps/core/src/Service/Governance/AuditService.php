<?php

namespace App\Service\Governance;

use App\Entity\Governance\AuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Records audit trail entries for all significant platform actions.
 */
class AuditService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function log(
        string $action,
        string $description,
        ?string $entityType = null,
        ?string $entityId = null,
        ?string $userIdentifier = null,
        ?array $beforeValue = null,
        ?array $afterValue = null,
        ?Request $request = null,
        array $metadata = [],
        ?int $tenantId = null,
    ): AuditLog {
        $log = new AuditLog();
        $log->setAction($action);
        $log->setDescription($description);
        $log->setEntityType($entityType);
        $log->setEntityId($entityId);
        $log->setUserIdentifier($userIdentifier);
        $log->setBeforeValue($beforeValue);
        $log->setAfterValue($afterValue);
        $log->setTenantId($tenantId);
        if (!empty($metadata)) { $log->setMetadata($metadata); }

        if ($request !== null) {
            $log->setIpAddress($request->getClientIp());
            $log->setUserAgent($request->headers->get('User-Agent'));
        }

        $this->em->persist($log);
        $this->em->flush();
        return $log;
    }

    public function logPipelineRun(string $pipelineName, string $userIdentifier, ?Request $request = null): void
    {
        $this->log(
            action: AuditLog::ACTION_PIPELINE_RUN,
            description: "Pipeline '{$pipelineName}' executado por {$userIdentifier}",
            entityType: 'Pipeline',
            userIdentifier: $userIdentifier,
            request: $request,
        );
    }

    public function logAiQuery(string $provider, string $model, string $userIdentifier): void
    {
        $this->log(
            action: AuditLog::ACTION_AI_QUERY,
            description: "Consulta IA via {$provider} · Modelo: {$model} · Usuário: {$userIdentifier}",
            entityType: 'AiInteraction',
            userIdentifier: $userIdentifier,
        );
    }

    public function logConfigChange(string $entityType, string $entityId, string $userIdentifier, array $before = [], array $after = [], ?Request $request = null): void
    {
        $this->log(
            action: AuditLog::ACTION_CONFIG_CHANGE,
            description: "{$entityType} #{$entityId} alterado por {$userIdentifier}",
            entityType: $entityType,
            entityId: $entityId,
            userIdentifier: $userIdentifier,
            beforeValue: $before ?: null,
            afterValue: $after ?: null,
            request: $request,
        );
    }
}
