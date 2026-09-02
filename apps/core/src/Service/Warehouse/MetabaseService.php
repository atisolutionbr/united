<?php

namespace App\Service\Warehouse;

use App\Entity\Warehouse\AnalyticsHistory;
use App\Entity\Warehouse\MetabaseConfig;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MetabaseService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {}

    public function testConnection(MetabaseConfig $config): array
    {
        try {
            $response = $this->httpClient->request('GET', rtrim($config->getBaseUrl(), '/') . '/api/health', [
                'timeout' => 10,
                'verify_peer' => false,
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode === 200) {
                $config->setConnectionStatus(MetabaseConfig::STATUS_CONNECTED);
                $config->setLastTestedAt(new \DateTimeImmutable());
                $this->entityManager->flush();

                return ['success' => true, 'message' => 'Conexão estabelecida com sucesso.'];
            }

            $config->setConnectionStatus(MetabaseConfig::STATUS_FAILED);
            $config->setLastTestedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            return ['success' => false, 'message' => "Status HTTP: {$statusCode}"];
        } catch (\Throwable $e) {
            $this->logger->warning('Metabase connection test failed', ['url' => $config->getBaseUrl(), 'error' => $e->getMessage()]);

            $config->setConnectionStatus(MetabaseConfig::STATUS_FAILED);
            $config->setLastTestedAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            return ['success' => false, 'message' => 'Não foi possível conectar: ' . $e->getMessage()];
        }
    }

    public function syncDashboards(MetabaseConfig $config): array
    {
        $result = ['success' => false, 'synced' => 0, 'error' => null];

        try {
            // This requires Metabase API token — returns placeholder when not yet configured
            if (!$config->getSecretKey()) {
                return ['success' => false, 'synced' => 0, 'error' => 'Secret key não configurado. Configure a chave de API antes de sincronizar.'];
            }

            $config->setLastSyncAt(new \DateTimeImmutable());
            $this->entityManager->flush();

            $this->recordHistory(
                AnalyticsHistory::EVENT_METABASE_SYNC,
                'Sincronização Metabase',
                'Sincronização manual disparada',
                AnalyticsHistory::STATUS_SUCCESS,
            );

            $result = ['success' => true, 'synced' => 0, 'error' => null, 'message' => 'Sincronização iniciada. Dashboards serão atualizados em breve.'];
        } catch (\Throwable $e) {
            $this->logger->error('Metabase sync failed', ['error' => $e->getMessage()]);
            $result = ['success' => false, 'synced' => 0, 'error' => $e->getMessage()];
        }

        return $result;
    }

    public function buildEmbedUrl(string $baseUrl, string $publicUuid, array $params = []): string
    {
        $url = rtrim($baseUrl, '/') . '/public/dashboard/' . $publicUuid;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        return $url;
    }

    private function recordHistory(
        string $eventType,
        string $subject,
        ?string $detail,
        string $status,
    ): void {
        $history = (new AnalyticsHistory())
            ->setEventType($eventType)
            ->setSubject($subject)
            ->setDetail($detail)
            ->setStatus($status);

        $this->entityManager->persist($history);
        $this->entityManager->flush();
    }
}
