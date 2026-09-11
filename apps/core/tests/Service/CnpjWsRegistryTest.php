<?php

namespace App\Tests\Service;

use App\Service\CnpjWsRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class CnpjWsRegistryTest extends TestCase
{
    public function testItNormalizesTheCnpjWsPayloadIncludingStateRegistration(): void
    {
        $client = new MockHttpClient([new MockResponse(json_encode([
            'razao_social' => 'ATI SOLUTION LTDA',
            'estabelecimento' => [
                'nome_fantasia' => 'Ati Solution',
                'email' => 'contato@atisolution.com.br',
                'ddd1' => '27',
                'telefone1' => '35350168',
                'tipo_logradouro' => 'Rua',
                'logradouro' => 'Exemplo',
                'numero' => '100',
                'bairro' => 'Centro',
                'cidade' => ['nome' => 'Vitoria'],
                'estado' => ['sigla' => 'ES'],
                'situacao_cadastral' => 'ATIVA',
                'inscricoes_estaduais' => [[
                    'inscricao_estadual' => '123456789',
                    'ativo' => true,
                    'atualizado_em' => '2026-09-09T00:00:00Z',
                    'estado' => ['sigla' => 'ES'],
                ]],
            ],
        ], JSON_THROW_ON_ERROR))]);
        $registry = new CnpjWsRegistry($client, new ArrayAdapter());

        $result = $registry->lookup('55.044.004/0001-63');

        self::assertTrue($result['ok']);
        self::assertSame('ATI SOLUTION LTDA', $result['registry']['legalName']);
        self::assertSame('ES', $result['registry']['state']);
        self::assertSame('27 35350168', $result['registry']['phone']);
        self::assertSame('123456789', $result['registry']['stateRegistrations'][0]['number']);
        self::assertTrue($result['registry']['stateRegistrations'][0]['active']);
    }

    public function testItDoesNotQueryAServiceForCpf(): void
    {
        $registry = new CnpjWsRegistry(new MockHttpClient(), new ArrayAdapter());

        $result = $registry->lookup('123.456.789-09');

        self::assertFalse($result['ok']);
        self::assertFalse($result['supported']);
    }
}
