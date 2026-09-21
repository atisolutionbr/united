<?php
namespace App\Tests\Service;
use App\Entity\{Company,ErpConnection};
use App\Service\{ErpConnectionProfile,ConnectionSecretCipher};
use PHPUnit\Framework\TestCase;
final class CompanyProfilesDatabaseTest extends TestCase
{
 public function testEditsSurviveReloadAndRemainIsolatedByCompany(): void
 {
  if(getenv('UNITED_LOCAL_DB_TEST')!=='1') self::markTestSkipped('Local database opt-in required.');
  (new \Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__,2).'/.env');
  $kernel=new \App\Kernel('prod',false);$kernel->boot();$em=$kernel->getContainer()->get('doctrine')->getManager();$db=$em->getConnection();
  $db->beginTransaction();
  try {
   $profile=new ErpConnectionProfile(new ConnectionSecretCipher('test'));$ids=[];
   foreach(['Alpha','Beta'] as $name){
    $company=new Company('Fixture '.$name);$em->persist($company);$c=new ErpConnection($company,'Senior');$em->persist($c);
    foreach(['database','api','webservice'] as $method){$settings=$profile->settingsFromInput($method,['host'=>$name.'.example','endpoint'=>'https://'.$name.'.example','port'=>'8443','database'=>'fixture','username'=>'fixture'],['bindings'=>['products'=>['table'=>'items','mapping'=>['name'=>'name']]]]);$c->setConnectionMethod($method)->setSettingsForMethod($method,$settings);}
    $em->flush();$ids[$name]=$c->getId();
   }
   $em->clear();$alpha=$em->find(ErpConnection::class,$ids['Alpha']);
   foreach(['database','api','webservice'] as $method){$s=$alpha->getSettingsForMethod($method);$alpha->setSettingsForMethod($method,$profile->settingsFromInput($method,['host'=>'changed.example','endpoint'=>'https://changed.example','port'=>'9443','database'=>'fixture','username'=>'fixture'],$s));}
   $em->flush();$em->clear();
   foreach(['database','api','webservice'] as $method){$a=$em->find(ErpConnection::class,$ids['Alpha'])->getSettingsForMethod($method);$b=$em->find(ErpConnection::class,$ids['Beta'])->getSettingsForMethod($method);self::assertSame('9443',$a['port']);self::assertSame('8443',$b['port']);self::assertSame($b['bindings'],$a['bindings']);self::assertSame($method==='api'?'https://changed.example':'changed.example',$a[$method==='api'?'endpoint':'host']);}
  } finally {$db->rollBack();$em->clear();$kernel->shutdown();}
 }
}
