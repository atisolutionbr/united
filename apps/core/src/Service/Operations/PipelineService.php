<?php

namespace App\Service\Operations;

use App\Entity\Operations\Pipeline;
use App\Entity\Operations\PipelineExecution;
use App\Repository\Operations\PipelineRepository;
use App\Repository\Operations\PipelineExecutionRepository;
use App\Service\Governance\AuditService;
use App\Service\Kestra\KestraService;
use App\Service\Operations\AlertService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Orchestrates pipeline operations: trigger, sync status, update stats.
 */
class PipelineService
{
    public function __construct(
        private readonly KestraService $kestraService,
        private readonly AlertService $alertService,
        private readonly AuditService $auditService,
        private readonly PipelineRepository $pipelineRepository,
        private readonly PipelineExecutionRepository $executionRepository,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    public function trigger(Pipeline $pipeline, string $userIdentifier, ?Request $request = null, array $inputs = []): PipelineExecution
    {
        $execution = new PipelineExecution();
        $execution->setPipeline($pipeline);
        $execution->setTriggeredBy($userIdentifier);
        $execution->setTriggerType(PipelineExecution::TRIGGER_MANUAL);
        $execution->setStatus(PipelineExecution::STATUS_CREATED);
        $execution->setStartedAt(new \DateTimeImmutable());
        $execution->setInputs($inputs ?: null);

        // Try to trigger on Kestra if configured
        if ($pipeline->hasKestraFlow()) {
            $result = $this->kestraService->triggerExecution(
                $pipeline->getKestraNamespace(),
                $pipeline->getKestraFlowId(),
                $inputs,
            );
            if (isset($result['id'])) {
                $execution->setKestraExecutionId($result['id']);
                $execution->setStatus($result['state']['current'] ?? PipelineExecution::STATUS_RUNNING);
            } elseif (isset($result['error'])) {
                $execution->setStatus(PipelineExecution::STATUS_FAILED);
                $execution->setErrorMessage($result['error']);
                $execution->setFinishedAt(new \DateTimeImmutable());
            }
        } else {
            $execution->setStatus(PipelineExecution::STATUS_RUNNING);
        }

        $this->em->persist($execution);

        // Update pipeline metadata
        $pipeline->setLastExecutionStatus($execution->getStatus());
        $pipeline->setLastExecutedAt(new \DateTimeImmutable());

        $this->em->flush();

        // Audit
        $this->auditService->logPipelineRun($pipeline->getName(), $userIdentifier, $request);

        return $execution;
    }

    public function syncExecutionStatus(PipelineExecution $execution): void
    {
        if ($execution->isFinished() || empty($execution->getKestraExecutionId())) {
            return;
        }

        $data = $this->kestraService->getExecution($execution->getKestraExecutionId());
        if (empty($data) || isset($data['error'])) {
            return;
        }

        $kestraStatus = $data['state']['current'] ?? null;
        if ($kestraStatus) {
            $execution->setStatus($kestraStatus);
        }

        if (isset($data['state']['endDate'])) {
            $execution->setFinishedAt(new \DateTimeImmutable($data['state']['endDate']));
        }

        if ($execution->getStartedAt() && $execution->getFinishedAt()) {
            $durationMs = (int)(($execution->getFinishedAt()->getTimestamp() - $execution->getStartedAt()->getTimestamp()) * 1000);
            $execution->setDurationMs($durationMs);
        }

        if ($execution->getStatus() === PipelineExecution::STATUS_FAILED) {
            $pipeline = $execution->getPipeline();
            if ($pipeline) {
                $pipeline->setFailureCount($pipeline->getFailureCount() + 1);
                $pipeline->setLastExecutionStatus(PipelineExecution::STATUS_FAILED);
                $this->alertService->pipelineFailed($pipeline->getName(), 'Pipeline failed in Kestra', $execution->getKestraExecutionId());
            }
        } elseif ($execution->getStatus() === PipelineExecution::STATUS_SUCCESS) {
            $pipeline = $execution->getPipeline();
            if ($pipeline) {
                $pipeline->setSuccessCount($pipeline->getSuccessCount() + 1);
                $pipeline->setLastExecutionStatus(PipelineExecution::STATUS_SUCCESS);
            }
        }

        $this->em->flush();
    }

    public function getOperationsSummary(): array
    {
        $pipelines = $this->pipelineRepository->findAll();
        $executions = $this->executionRepository->countByStatus();

        return [
            'total_pipelines' => count($pipelines),
            'active_pipelines' => count(array_filter($pipelines, fn($p) => $p->isActive())),
            'failed_today' => $this->executionRepository->countFailedToday(),
            'executions_by_status' => $executions,
        ];
    }
}
