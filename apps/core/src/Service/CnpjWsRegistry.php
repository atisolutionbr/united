<?php

namespace App\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class CnpjWsRegistry
{
    private const BASE_URL = 'https://publica.cnpj.ws/cnpj/';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
    ) {
    }

    /** @return array<string, mixed> */
    public function lookup(string $document): array
    {
        $cnpj = preg_replace('/\D+/', '', $document) ?? '';
        if (14 !== strlen($cnpj)) {
            return ['ok' => false, 'supported' => false, 'message' => 'A consulta pública de dados cadastrais está disponível somente para CNPJ.'];
        }

        try {
            return $this->cache->get('kflow.cnpjws.'.$cnpj, function (ItemInterface $item) use ($cnpj): array {
                $payload = $this->httpClient->request('GET', self::BASE_URL.$cnpj, [
                    'headers' => ['Accept' => 'application/json'],
                    'timeout' => 7,
                    'max_duration' => 10,
                ])->toArray(false);

                if (!is_array($payload) || !isset($payload['razao_social'])) {
                    throw new \RuntimeException('Resposta cadastral inválida.');
                }

                $item->expiresAfter(86400);

                return ['ok' => true, 'supported' => true, 'source' => 'CNPJ.ws', 'registry' => $this->normalize($cnpj, $payload)];
            });
        } catch (TransportExceptionInterface) {
            return ['ok' => false, 'supported' => true, 'message' => 'Não foi possível consultar a base cadastral agora. Tente novamente em instantes.'];
        } catch (\Throwable) {
            return ['ok' => false, 'supported' => true, 'message' => 'A consulta cadastral não retornou dados para este CNPJ.'];
        }
    }

    /** @param array<string, mixed> $payload
     *  @return array<string, mixed>
     */
    private function normalize(string $cnpj, array $payload): array
    {
        $establishment = is_array($payload['estabelecimento'] ?? null) ? $payload['estabelecimento'] : [];
        $city = is_array($establishment['cidade'] ?? null) ? $establishment['cidade'] : [];
        $state = is_array($establishment['estado'] ?? null) ? $establishment['estado'] : [];
        $registrations = [];
        foreach (is_array($establishment['inscricoes_estaduais'] ?? null) ? $establishment['inscricoes_estaduais'] : [] as $registration) {
            if (!is_array($registration) || '' === trim((string) ($registration['inscricao_estadual'] ?? ''))) {
                continue;
            }
            $registrationState = is_array($registration['estado'] ?? null) ? $registration['estado'] : [];
            $registrations[] = [
                'number' => (string) $registration['inscricao_estadual'],
                'state' => (string) ($registrationState['sigla'] ?? ''),
                'active' => (bool) ($registration['ativo'] ?? false),
                'updatedAt' => (string) ($registration['atualizado_em'] ?? ''),
            ];
        }

        return [
            'document' => $cnpj,
            'legalName' => (string) ($payload['razao_social'] ?? ''),
            'tradeName' => (string) ($establishment['nome_fantasia'] ?? ''),
            'email' => (string) ($establishment['email'] ?? ''),
            'phone' => trim(implode(' ', array_filter([
                (string) ($establishment['ddd1'] ?? ''),
                (string) ($establishment['telefone1'] ?? ''),
            ]))),
            'address' => trim(implode(', ', array_filter([
                (string) ($establishment['tipo_logradouro'] ?? '').' '.(string) ($establishment['logradouro'] ?? ''),
                (string) ($establishment['numero'] ?? ''),
                (string) ($establishment['bairro'] ?? ''),
            ]))),
            'city' => (string) ($city['nome'] ?? ''),
            'state' => (string) ($state['sigla'] ?? ''),
            'status' => (string) ($establishment['situacao_cadastral'] ?? ''),
            'updatedAt' => (string) ($establishment['atualizado_em'] ?? $payload['atualizado_em'] ?? ''),
            'stateRegistrations' => $registrations,
        ];
    }
}
