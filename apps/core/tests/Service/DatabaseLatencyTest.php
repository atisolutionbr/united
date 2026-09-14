<?php
namespace App\Tests\Service;
use App\Service\{ConnectionSecretCipher,DatabaseSchemaInspector};
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use PHPUnit\Framework\TestCase;
final class DatabaseLatencyTest extends TestCase
{
    public function testUnresponsiveSqlServerHasBoundedWaitAndSharedCooldown(): void
    {
        if(!in_array('dblib',\PDO::getAvailableDrivers(),true))self::markTestSkipped('Requires dblib');
        $proc=proc_open([PHP_BINARY,__DIR__.'/../Fixtures/slow-sql.php'],[['pipe','r'],['pipe','w'],['pipe','w']],$pipes);
        self::assertIsResource($proc);
        try {
            $address=trim(fgets($pipes[1]));$port=(int)substr(strrchr($address,':'),1);
            $cipher=new ConnectionSecretCipher('fixture');$cache=new ArrayAdapter();
            $settings=['driver'=>'sqlserver','host'=>'127.0.0.1','port'=>$port,'database'=>'fixture','username'=>'fixture','password_encrypted'=>$cipher->encrypt('fixture')];
            $start=microtime(true);
            try {(new DatabaseSchemaInspector($cipher,$cache))->open($settings);self::fail('Unresponsive server accepted connection');}catch(\PDOException $e){}
            self::assertLessThan(7,microtime(true)-$start,'SQL login must not block navigation for minutes');
            $start=microtime(true);
            try {(new DatabaseSchemaInspector($cipher,$cache))->open($settings);self::fail('Cooldown missing');}catch(\PDOException $e){}
            self::assertLessThan(0.3,microtime(true)-$start,'Other requests must reuse the cooldown');
        } finally {proc_terminate($proc);foreach($pipes as $pipe)fclose($pipe);proc_close($proc);}
    }
}
