<?php

namespace App\Tests\Service;

use App\Entity\{Company, ErpConnection};
use App\Service\{ProcessGateway, DatabaseSchemaInspector, ConnectionSecretCipher};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

final class ProcessGatewaySoapTest extends TestCase
{
    public function testSoapDocumentAndBusinessConfirmation(): void
    {
        if ('1' !== getenv('UNITED_LOCAL_SOAP_TEST')) self::markTestSkipped('Run the local SOAP fixture first.');
        $cipher = new ConnectionSecretCipher('test');
        $c = new ErpConnection(new Company('Fixture'), 'Fixture');
        $c->setSettingsForMethod('webservice', ['endpoint' => 'http://127.0.0.1/service.wsdl', 'port'=>'18083']);
        $gateway = new ProcessGateway(new DatabaseSchemaInspector($cipher), $cipher, new MockHttpClient());
        $config = ['method' => 'webservice', 'purpose' => 'create', 'mapping' => ['product' => 'product'], 'create_operation' => 'Create', 'success_path' => 'success', 'success_value' => 'true'];
        self::assertSame('ERP confirmou o resultado configurado.', $gateway->create($c, $config, ['product' => 'P1'], 'fixture-id'));
        $this->expectException(\RuntimeException::class);
        $gateway->create($c, $config, ['product' => 'invalid'], 'fixture-id-2');
    }
}
