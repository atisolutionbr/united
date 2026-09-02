<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class MetabaseSessionBridge
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(METABASE_API_URL)%')]
        private readonly string $apiUrl,
        #[Autowire('%env(METABASE_SERVICE_EMAIL)%')]
        private readonly string $serviceEmail,
        #[Autowire('%env(METABASE_SERVICE_PASSWORD)%')]
        private readonly string $servicePassword,
    ) {
    }

    public function isConfigured(): bool
    {
        return '' !== trim($this->serviceEmail) && '' !== trim($this->servicePassword);
    }

    public function createSession(): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $response = $this->httpClient->request('POST', rtrim($this->apiUrl, '/').'/api/session', [
                'json' => [
                    'username' => $this->serviceEmail,
                    'password' => $this->servicePassword,
                ],
                'timeout' => 3,
                'max_duration' => 5,
            ]);
            $payload = $response->toArray(false);
            $sessionId = $payload['id'] ?? null;

            if (is_string($sessionId) && '' !== $sessionId) {
                return $sessionId;
            }

            $this->logger->warning('Metabase did not return a usable session for the KFlow bridge.', [
                'status_code' => $response->getStatusCode(),
            ]);
        } catch (\Throwable $exception) {
            $this->logger->warning('The KFlow Metabase session bridge could not reach Metabase.', [
                'exception' => $exception,
            ]);
        }

        return null;
    }
}
