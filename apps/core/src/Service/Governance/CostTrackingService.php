<?php

namespace App\Service\Governance;

use App\Entity\Governance\CostRecord;
use App\Repository\Governance\CostRecordRepository;
use Doctrine\ORM\EntityManagerInterface;

class CostTrackingService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CostRecordRepository $costRepository,
    ) {}

    public function record(
        string $service,
        float $quantity,
        float $unitCostUsd,
        string $unit = 'tokens',
        ?string $description = null,
        ?\DateTimeImmutable $periodDate = null,
        array $metadata = [],
        ?int $tenantId = null,
    ): CostRecord {
        $record = new CostRecord();
        $record->setService($service);
        $record->setQuantity($quantity);
        $record->setUnitCostUsd($unitCostUsd);
        $record->setTotalCostUsd(round($quantity * $unitCostUsd, 6));
        $record->setUnit($unit);
        $record->setDescription($description);
        $record->setPeriodDate($periodDate ?? new \DateTimeImmutable('today'));
        $record->setTenantId($tenantId);
        if (!empty($metadata)) { $record->setMetadata($metadata); }
        $this->em->persist($record);
        $this->em->flush();
        return $record;
    }

    public function recordOpenAiCost(int $tokensInput, int $tokensOutput, string $model, string $description = ''): CostRecord
    {
        $prices = [
            'gpt-4o' => ['input' => 5.0, 'output' => 15.0],
            'gpt-4o-mini' => ['input' => 0.15, 'output' => 0.60],
        ];
        $p = $prices[$model] ?? ['input' => 1.0, 'output' => 2.0];
        $totalTokens = $tokensInput + $tokensOutput;
        $costUsd = ($tokensInput * $p['input'] + $tokensOutput * $p['output']) / 1_000_000;

        return $this->record(
            service: CostRecord::SERVICE_OPENAI,
            quantity: $totalTokens,
            unitCostUsd: $totalTokens > 0 ? $costUsd / $totalTokens : 0,
            unit: 'tokens',
            description: $description ?: "OpenAI {$model} · {$tokensInput} input + {$tokensOutput} output tokens",
            metadata: ['model' => $model, 'tokens_input' => $tokensInput, 'tokens_output' => $tokensOutput],
        );
    }

    public function getDashboardData(): array
    {
        $thisMonth = $this->costRepository->getTotalThisMonth();
        $byService = $this->costRepository->getTotalByService(new \DateTimeImmutable('first day of this month'));
        $daily = $this->costRepository->getDailyTotals(30);

        return [
            'total_this_month_usd' => $thisMonth,
            'by_service' => $byService,
            'daily_series' => $daily,
        ];
    }
}
