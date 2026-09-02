<?php

namespace App\Service;

use App\Entity\ErpConnection;
use App\Entity\ErpConnectionLog;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class SeniorWebServiceManager
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
        private readonly ConnectionSecretCipher $secretCipher,
        private readonly SeniorWebServiceCatalog $catalog,
        private readonly LoggerInterface $logger,
    ) {
    }

    /** @return array{success: bool, status: string, message: string, errorCode: string, statusHttp: int|null, responseTimeMs: int} */
    public function testConnection(ErpConnection $connection): array
    {
        $settings = $connection->getSettingsForMethod(ErpConnection::METHOD_WEBSERVICE);
        $endpoint = trim((string) ($settings['endpoint'] ?? ''));
        $serviceKey = trim((string) ($settings['service_key'] ?? 'product'));
        $service = $this->catalog->get($serviceKey);
        $wsdlUrl = '' !== $endpoint ? $this->catalog->buildWsdlUrl($endpoint, $serviceKey) : '';
        $startedAt = new \DateTimeImmutable();
        $started = hrtime(true);
        $success = false;
        $statusHttp = null;
        $errorCode = '';
        $message = '';

        if ('' === $endpoint) {
            $errorCode = 'MISSING_ENDPOINT';
            $message = 'Informe a URL base do WebService Senior antes de testar.';
        } else {
            try {
                $response = $this->httpClient->request('GET', $wsdlUrl, [
                    'timeout' => $this->timeout($settings),
                    'max_duration' => $this->timeout($settings),
                ]);
                $statusHttp = $response->getStatusCode();
                $success = $statusHttp < 500;
                $message = sprintf('WSDL respondeu HTTP %d.', $statusHttp);
            } catch (TransportExceptionInterface $exception) {
                $errorCode = str_contains(strtolower($exception->getMessage()), 'timeout') ? 'TIMEOUT' : 'TRANSPORT_ERROR';
                $message = 'Erro técnico ao testar o endpoint WebService Senior.';
                $this->logger->warning('Senior WebService connection test failed.', ['exception' => $exception]);
            }
        }

        $finishedAt = new \DateTimeImmutable();
        $elapsedMs = (int) round((hrtime(true) - $started) / 1_000_000);
        $settings['last_test_at'] = $finishedAt->format(DATE_ATOM);
        $settings['last_test_status'] = $success ? 'SUCESSO' : 'ERRO';
        $settings['last_test_message'] = $message;
        $connection->setSettingsForMethod(ErpConnection::METHOD_WEBSERVICE, $settings);

        $this->writeLog(
            $connection,
            'TESTE_CONEXAO_WS_SENIOR',
            (string) ($settings['operation'] ?? $service['port']),
            $wsdlUrl ?: $endpoint,
            'GET',
            $this->maskPayload([
                'url_base_ws' => $endpoint,
                'porta_ws' => (string) ($settings['operation'] ?? ''),
                'usuario_ws' => (string) ($settings['username'] ?? ''),
                'senha_ws' => !empty($settings['password_encrypted']) ? '********' : '',
                'ambiente' => (string) ($settings['environment'] ?? ''),
            ]),
            $message,
            $statusHttp,
            $success,
            $errorCode,
            $success ? null : $message,
            $elapsedMs,
            $startedAt,
            $finishedAt,
        );
        $this->entityManager->flush();

        return [
            'success' => $success,
            'status' => $success ? 'SUCESSO' : 'ERRO',
            'message' => $message,
            'errorCode' => $errorCode,
            'statusHttp' => $statusHttp,
            'responseTimeMs' => $elapsedMs,
        ];
    }

    public function isProductUpdateAvailable(ErpConnection $connection): bool
    {
        $settings = $connection->getSettingsForMethod(ErpConnection::METHOD_WEBSERVICE);

        return (bool) ($settings['active'] ?? false)
            && '' !== trim((string) ($settings['endpoint'] ?? ''))
            && '' !== trim((string) ($settings['username'] ?? ''))
            && '' !== trim((string) ($settings['password_encrypted'] ?? ''));
    }

    /** @param array<string, string> $product
     *  @return array{updated: bool, message: string}
     */
    public function updateProductFiscal(ErpConnection $connection, array $product): array
    {
        if (!$this->isProductUpdateAvailable($connection)) {
            return ['updated' => false, 'message' => 'Configure e ative o WebService Senior antes de atualizar dados fiscais no ERP.'];
        }

        if (!class_exists(\SoapClient::class)) {
            return ['updated' => false, 'message' => 'O cliente SOAP não está disponível no container PHP.'];
        }

        $ncm = preg_replace('/\D/', '', $product['ncm'] ?? '') ?? '';
        if (8 !== strlen($ncm)) {
            return ['updated' => false, 'message' => 'Informe um NCM com oito dígitos.'];
        }

        $settings = $connection->getSettingsForMethod(ErpConnection::METHOD_WEBSERVICE);
        $service = $this->catalog->get('product');
        $wsdlUrl = $this->catalog->buildWsdlUrl((string) $settings['endpoint'], 'product');
        $payload = [
            'codEmp' => $product['company'] ?? '',
            'codPro' => $product['product_code'] ?? '',
            'descricaoPadronizada' => $product['product_name'] ?? '',
            'uniMed' => $product['unit'] ?? '',
            'ncm' => $ncm,
            'codOri' => $product['origin_code'] ?? '',
            'codFam' => $product['family'] ?? '',
            'cstPis' => $product['cst_pis'] ?? '',
            'cstCof' => $product['cst_cofins'] ?? '',
            'cstIcms' => $product['cst_icms'] ?? '',
        ];
        $startedAt = new \DateTimeImmutable();
        $started = hrtime(true);
        $success = false;
        $responseText = '';
        $errorCode = '';
        $errorMessage = null;

        try {
            $client = new \SoapClient($wsdlUrl, [
                'exceptions' => true,
                'trace' => true,
                'cache_wsdl' => WSDL_CACHE_NONE,
                'connection_timeout' => $this->timeout($settings),
            ]);
            $response = $client->__soapCall($service['method'], [[
                'usuario' => (string) $settings['username'],
                'senha' => $this->secretCipher->decrypt((string) $settings['password_encrypted']),
                'produto' => $payload,
            ]]);
            $normalized = json_decode((string) json_encode($response, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
            if (is_array($normalized) && false === ($normalized['sucesso'] ?? true)) {
                throw new \RuntimeException((string) ($normalized['mensagemErro'] ?? 'Erro de negócio retornado pelo ERP Senior.'));
            }
            $responseText = 'Produto atualizado pelo WebService Senior.';
            $success = true;
        } catch (\Throwable $exception) {
            $errorCode = $exception instanceof \SoapFault ? 'SOAP_FAULT' : 'SENIOR_INTEGRATION_ERROR';
            $errorMessage = 'Não foi possível atualizar o produto pelo WebService Senior.';
            $responseText = $errorMessage;
            $this->logger->warning('Senior product WebService update failed.', ['exception' => $exception]);
        }

        $finishedAt = new \DateTimeImmutable();
        $elapsedMs = (int) round((hrtime(true) - $started) / 1_000_000);
        $this->writeLog(
            $connection,
            $service['webservice'],
            $service['port'],
            $wsdlUrl,
            $service['method'],
            $this->maskPayload(['usuario' => (string) $settings['username'], 'senha' => '********', 'produto' => $payload]),
            $responseText,
            null,
            $success,
            $errorCode,
            $errorMessage,
            $elapsedMs,
            $startedAt,
            $finishedAt,
        );
        $this->entityManager->flush();

        return ['updated' => $success, 'message' => $responseText];
    }

    /** @param array<string, mixed> $settings */
    private function timeout(array $settings): int
    {
        return max(1, min(120, (int) ($settings['timeout_seconds'] ?? 20)));
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    private function maskPayload(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if (in_array(strtolower((string) $key), ['senha', 'password', 'senha_ws', 'token', 'authorization'], true) && '' !== $value) {
                $payload[$key] = '********';
            } elseif (is_array($value)) {
                $payload[$key] = $this->maskPayload($value);
            }
        }

        return $payload;
    }

    /** @param array<string, mixed> $payload */
    private function writeLog(
        ErpConnection $connection,
        string $service,
        ?string $port,
        string $endpoint,
        string $method,
        array $payload,
        ?string $response,
        ?int $statusHttp,
        bool $success,
        ?string $errorCode,
        ?string $errorMessage,
        int $elapsedMs,
        \DateTimeImmutable $startedAt,
        \DateTimeImmutable $finishedAt,
    ): void {
        $this->entityManager->persist(new ErpConnectionLog(
            $connection,
            $service,
            $port,
            $endpoint,
            $method,
            $payload,
            $response,
            $statusHttp,
            $success,
            '' !== $errorCode ? $errorCode : null,
            $errorMessage,
            $elapsedMs,
            $startedAt,
            $finishedAt,
        ));
    }
}
