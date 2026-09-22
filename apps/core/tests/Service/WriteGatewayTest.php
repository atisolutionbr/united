<?php
namespace App\Tests\Service;
use App\Entity\{Company,ErpConnection};
use App\Service\{ProcessGateway,ConnectionSecretCipher,DatabaseSchemaInspector};
use Symfony\Component\HttpClient\{MockHttpClient,Response\MockResponse};
use PHPUnit\Framework\TestCase;
final class WriteGatewayTest extends TestCase
{
 public function testReadRecoversWithoutChangingCompanySettings(): void
 {
  $calls=0;$http=new MockHttpClient(function()use(&$calls){return ++$calls===1?new MockResponse('{}',['http_code'=>503]):new MockResponse('{"items":[{"id":1}]}');});
  $gateway=$this->gateway($http);$c=new ErpConnection(new Company('Recovery'),'Senior');$c->setSettingsForMethod('api',['endpoint'=>'https://erp.example','port'=>'8443']);
  $saved=$c->getConnectionSettings();$query=['method'=>'api','operation'=>'/items'];
  try {$gateway->readForm($c,$query,1);self::fail('First request should fail');}catch(\RuntimeException $e){self::assertStringContainsString('503',$e->getMessage());}
  self::assertSame(['items'=>[['id'=>1]]],$gateway->readForm($c,$query,1));self::assertSame($saved,$c->getConnectionSettings());
 }
 public function testCreationUsesPostAndCompanySpecificPort(): void
 {
  $seen=[];$http=new MockHttpClient(function($method,$url,$options)use(&$seen){$seen[]=[$method,$url,json_decode($options['body'],true)];return new MockResponse('{"ok":true}');});
  $gateway=$this->gateway($http);
  foreach(['Alpha'=>8443,'Beta'=>9443] as $name=>$port){
   $c=new ErpConnection(new Company($name),'Senior');$c->setSettingsForMethod('api',['endpoint'=>'https://erp.example','port'=>(string)$port]);
   $gateway->create($c,['purpose'=>'create','method'=>'api','mapping'=>['product'=>'item'],'create_operation'=>'/requests','success_path'=>'ok','success_value'=>'true'],['product'=>$name],'request-'.$name);
  }
  self::assertSame([['POST','https://erp.example:8443/requests',['item'=>'Alpha']],['POST','https://erp.example:9443/requests',['item'=>'Beta']]],$seen);
 }
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
