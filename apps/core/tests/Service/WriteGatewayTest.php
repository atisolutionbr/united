<?php
namespace App\Tests\Service;
use App\Entity\{Company,ErpConnection};
use App\Service\{ProcessGateway,ConnectionSecretCipher,DatabaseSchemaInspector};
use Symfony\Component\HttpClient\{MockHttpClient,Response\MockResponse};
use PHPUnit\Framework\TestCase;
final class WriteGatewayTest extends TestCase
{
 private function gateway(MockHttpClient $http): ProcessGateway { $cipher=new ConnectionSecretCipher('test');return new ProcessGateway(new DatabaseSchemaInspector($cipher),$cipher,$http); }
 public function testMappedUpdateUsesConfiguredMethodKeysAndConfirmation(): void
 {
  $http=new MockHttpClient(function($method,$url,$options){self::assertSame('PATCH',$method);self::assertSame('https://erp.example/products',$url);$body=json_decode($options['body'],true);self::assertSame(['id'=>'P1','name'=>'NOVO'],$body);return new MockResponse('{"ok":true}');});
  $c=new ErpConnection(new Company('Test'),'Senior');$c->setSettingsForMethod('api',['endpoint'=>'https://erp.example']);
  $receipt=$this->gateway($http)->write($c,['method'=>'api','action'=>'update','key_columns'=>'id','mapping'=>['product_name'=>'name'],'write_operation'=>'/products','http_method'=>'PATCH','success_path'=>'ok','success_value'=>'true'],['product_name'=>'NOVO'],['id'=>'P1'],'request-test');
  self::assertStringContainsString('confirmou',$receipt);
 }
 public function testDeleteCannotRunWithoutCompleteKey(): void
 {
  $http=new MockHttpClient(function(){self::fail('No request without complete keys');});
  $c=new ErpConnection(new Company('Test'),'Senior');
  $this->expectException(\InvalidArgumentException::class);
  $this->gateway($http)->write($c,['method'=>'database','action'=>'delete','table'=>'products','key_columns'=>'company,id','mapping'=>[]],[],['id'=>'P1'],'test');
 }
 public function testDuplicateDestinationsRejected(): void
 {
  $this->expectException(\InvalidArgumentException::class);
  $this->gateway(new MockHttpClient())->validateWrite(['method'=>'database','action'=>'insert','table'=>'products','mapping'=>['a'=>'name','b'=>'name']]);
 }
 public function testLegacySettingsSurviveMethodSwitch(): void
 {
  $c=new ErpConnection(new Company('Test'),'Senior');$c->setConnectionMethod('database')->setConnectionSettings(['host'=>'saved-host','bindings'=>['products'=>['table'=>'products']]]);
  $c->setConnectionMethod('api')->setSettingsForMethod('api',['endpoint'=>'https://erp.example']);
  self::assertSame('saved-host',$c->getSettingsForMethod('database')['host']);
  self::assertSame('products',$c->getSettingsForMethod('database')['bindings']['products']['table']);
 }
}
