<?php

namespace App\Tests\Service;

use App\Entity\{Company, ErpConnection};
use App\Service\{ProcessGateway, DatabaseSchemaInspector, ConnectionSecretCipher};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ProcessGatewayTest extends TestCase
{
    private function connection(): ErpConnection
    {
        $c = new ErpConnection(new Company('Fixture'), 'Fixture');
        $c->setSettingsForMethod('api', ['endpoint' => 'https://fixture.invalid', 'authentication' => 'bearer']);
        return $c;
    }
    private function gateway(MockHttpClient $http): ProcessGateway
    {
        $cipher = new ConnectionSecretCipher('test-only');
        return new ProcessGateway(new DatabaseSchemaInspector($cipher), $cipher, $http);
    }
    private function config(): array
    {
        return ['method' => 'api', 'purpose' => 'approval', 'mapping' => ['id' => 'id', 'description' => 'name'], 'key_columns' => 'company,id', 'status_column' => 'status', 'pending_value' => 'P', 'approved_value' => 'A', 'rejected_value' => 'R', 'list_operation' => '/orders', 'decision_operation' => '/decision', 'items_path' => 'data.items', 'total_path' => 'data.total', 'success_path' => 'success', 'success_value' => 'true'];
    }
    public function testApiUsesFifteenRowsAndMappedCompositeKey(): void
    {
        $http = new MockHttpClient(function ($method, $url, $options) {
            self::assertSame('GET', $method);
            self::assertStringContainsString('page_size=15', $url);
            return new MockResponse('{"data":{"items":[{"company":1,"id":3,"name":"Cotação"}],"total":31}}');
        });
        $result = $this->gateway($http)->list($this->connection(), $this->config(), 2);
        self::assertSame(31, $result['total']);
        self::assertSame(['company' => 1, 'id' => 3], $result['rows'][0]['keys']);
    }
    public function testDecisionSendsExpectedStatusAndChecksBusinessSuccess(): void
    {
        $http = new MockHttpClient(function ($method, $url, $options) {
            self::assertSame('POST', $method);
            $body = json_decode($options['body'], true);
            self::assertSame('A', $body['status']);
            self::assertSame('P', $body['expected_status']);
            return new MockResponse('{"success":false}');
        });
        $this->expectException(\RuntimeException::class);
        $this->gateway($http)->decide($this->connection(), $this->config(), ['company' => 1, 'id' => 3], 'approve', 'test-id');
    }
    public function testIncompleteCompositeKeyNeverSends(): void
    {
        $http = new MockHttpClient(static function () { self::fail('Incomplete keys must never reach the ERP.'); });
        $this->expectException(\InvalidArgumentException::class);
        $this->gateway($http)->decide($this->connection(), $this->config(), ['id' => 3], 'approve', 'test-id');
    }
    public function testCreateMapsOnlySelectedFieldsAndChecksConfirmation(): void
    {
        $config = ['method' => 'api', 'purpose' => 'create', 'mapping' => ['product' => 'request.product'], 'create_operation' => '/requests', 'success_path' => 'ok', 'success_value' => 'true'];
        $http = new MockHttpClient(function ($method, $url, $options) {
            self::assertSame(['request' => ['product' => 'P1']], json_decode($options['body'], true));
            return new MockResponse('{"ok":true}');
        });
        self::assertSame('ERP confirmou o resultado configurado.', $this->gateway($http)->create($this->connection(), $config, ['product' => 'P1', 'ignored' => 'secret'], 'test-id'));
    }
}
