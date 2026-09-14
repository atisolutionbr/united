<?php
namespace App\Tests\Service;
use App\Entity\{Company,ErpConnection};
use App\Service\{LookupCatalog,ProcessGateway,DatabaseSchemaInspector,ConnectionSecretCipher,FormBindingRegistry};
use Symfony\Component\HttpClient\MockHttpClient;
use PHPUnit\Framework\TestCase;
final class LookupRelationshipTest extends TestCase
{
    private function catalog(): LookupCatalog { $cipher=new ConnectionSecretCipher('test');$schema=new DatabaseSchemaInspector($cipher);return new LookupCatalog($schema,new ProcessGateway($schema,$cipher,new MockHttpClient())); }
    public function testBothDocumentsFollowTheSameProductMappingForEveryMethod(): void
    {
        foreach(['database','api','webservice'] as $method){
            $c=new ErpConnection(new Company('Fixture'),'Fixture');
            $mapping=['product_code'=>'code','product_name'=>'name','barcode'=>'ean'];
            $settings=['lookup_sources'=>['products'=>['product'=>['enabled'=>true,'mapping_form'=>'products','value'=>'obsolete','label'=>'obsolete']]],'bindings'=>['products'=>['table'=>'items','mapping'=>$mapping]],'form_mappings'=>['products'=>$mapping]];
            $c->setSettingsForMethod($method,$settings);
            foreach(['requests','requisitions'] as $form){$source=$this->catalog()->source($c,$form,'product');self::assertSame('code',$source['value']);self::assertSame('ean',$source['barcode']);self::assertSame($method,$source['method']);}
            $settings['bindings']['products']['mapping']['product_name']='new_name';$settings['form_mappings']['products']['product_name']='new_name';$c->setSettingsForMethod($method,$settings);
            self::assertSame('new_name',$this->catalog()->source($c,'requests','product')['label']);
        }
    }
    public function testDeletedAndUnrelatedFormsCannotSupplyProductMappings(): void
    {
        $s=['form_catalog'=>[['id'=>'form-client','template'=>'customers']], 'form_mappings'=>['form-client'=>['product_code'=>'id']]];
        self::assertSame([],LookupCatalog::productMapping($s,'api','form-client'));
        $s=['deleted_forms'=>['products'],'form_mappings'=>['products'=>['product_code'=>'id']]];
        self::assertSame([],LookupCatalog::productMapping($s,'webservice','products'));
    }
}
