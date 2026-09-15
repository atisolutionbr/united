<?php
namespace App\Tests\Service;
use App\Entity\{Company, ErpConnection};
use App\Service\{RemoteFormCatalog, ProcessGateway, ConnectionSecretCipher, DatabaseSchemaInspector};
use Symfony\Component\HttpClient\{MockHttpClient, Response\MockResponse};
use PHPUnit\Framework\TestCase;
final class RemoteFormCatalogTest extends TestCase
{
    private function connection(string $method = 'api'): ErpConnection
    {
        $c = new ErpConnection(new Company('Test'), 'Senior');
        $c->setConnectionMethod($method);
        $c->setSettingsForMethod('database', ['host'=>'unreachable.invalid','table'=>'products','bindings'=>['products'=>['table'=>'products','mapping'=>['product_code'=>'id']]]]);
        $c->setSettingsForMethod($method, ['endpoint'=>'https://erp.example', 'form_mappings'=>['products'=>['product_code'=>'id','product_name'=>'name','ncm'=>'tax.ncm']], 'form_queries'=>['products'=>['operation'=>'/products','items_path'=>'data.items','total_path'=>'data.total','page_param'=>'page','size_param'=>'limit','parameters'=>'{}']]]);
        return $c;
    }
    private function reader(MockHttpClient $http): RemoteFormCatalog
    {
        $cipher = new ConnectionSecretCipher('test');
        return new RemoteFormCatalog(new ProcessGateway(new DatabaseSchemaInspector($cipher), $cipher, $http));
    }
    public function testApiReadsMappedFieldsAndPaginationDespiteUnavailableDatabase(): void
    {
        $http = new MockHttpClient(function($method, $url) {
            self::assertSame('GET', $method);
            self::assertStringStartsWith('https://erp.example/products?', $url);
            parse_str(parse_url($url, PHP_URL_QUERY), $query);
            self::assertSame('2', $query['page']); self::assertSame('10', $query['limit']);
            return new MockResponse(json_encode(['data'=>['items'=>[['id'=>'P1','name'=>'Produto','tax'=>['ncm'=>'12345678']]],'total'=>12]]));
        });
        $result = $this->reader($http)->list($this->connection(), 'products', 2);
        self::assertNull($result['error']); self::assertSame('P1', $result['products'][0]['CodPro']);
        self::assertSame('12345678', $result['products'][0]['Ncm']); self::assertSame(2,$result['pageCount']);
    }
    public function testRemoteFailureDoesNotBecomeSqlError(): void
    {
        $result = $this->reader(new MockHttpClient(new MockResponse('', ['http_code'=>401])))->list($this->connection(), 'products', 1);
        self::assertStringContainsString('API', $result['error']); self::assertStringNotContainsString('banco', $result['error']); self::assertSame([], $result['products']);
    }
    public function testMissingWebserviceReadBindingNeverFallsBackToDatabase(): void
    {
        $c = $this->connection('webservice'); $settings = $c->getSettingsForMethod('webservice'); unset($settings['form_queries']); $c->setSettingsForMethod('webservice',$settings);
        $http = new MockHttpClient(function() { self::fail('No remote request before a read binding exists.'); });
        $result = $this->reader($http)->list($c, 'products', 1);
        self::assertStringContainsString('WebService', $result['error']); self::assertFalse($result['configured']);
    }
    public function testInvalidCollectionIsNotReportedAsEmptySuccess(): void
    {
        $result = $this->reader(new MockHttpClient(new MockResponse('{"data":{"items":null,"total":0}}')))->list($this->connection(), 'products', 1);
        self::assertNotNull($result['error']); self::assertSame([], $result['products']);
    }
    public function testAdditionalFormAndDeletedFormResolution(): void
    {
        $c=$this->connection(); $s=$c->getSettingsForMethod('api');
        $s['form_mappings']['custom']=$s['form_mappings']['products']; unset($s['form_mappings']['products']);
        $s['form_catalog']=[['id'=>'custom','template'=>'products']]; $c->setSettingsForMethod('api',$s);
        self::assertSame('custom',RemoteFormCatalog::binding($c,'products')['form']);
        $s['deleted_forms']=['custom'];$c->setSettingsForMethod('api',$s);
        self::assertSame([],RemoteFormCatalog::binding($c,'products')['mapping']);
    }
}
