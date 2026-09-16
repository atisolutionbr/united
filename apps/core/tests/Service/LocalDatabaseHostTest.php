<?php
namespace App\Tests\Service;
use App\Service\ErpConnectionProfile;
use PHPUnit\Framework\TestCase;
final class LocalDatabaseHostTest extends TestCase {
 public function testLocalRuntimeResolvesLoopbackEvenWhenPhpRunsProduction(): void {
  $old=getenv('APP_DEPLOYMENT_ENV'); $php=getenv('APP_ENV');
  try {putenv('APP_DEPLOYMENT_ENV=local');putenv('APP_ENV=prod');foreach(['localhost','127.0.0.1','localhost\\sqlexpress'] as $host) self::assertSame('host.docker.internal',ErpConnectionProfile::databaseHost($host));self::assertSame('10.20.30.40',ErpConnectionProfile::databaseHost('10.20.30.40'));}
  finally{putenv($old===false?'APP_DEPLOYMENT_ENV':'APP_DEPLOYMENT_ENV='.$old);putenv($php===false?'APP_ENV':'APP_ENV='.$php);}
 }
 public function testProductionKeepsConfiguredEndpoint(): void {
  $old=getenv('APP_DEPLOYMENT_ENV');try {putenv('APP_DEPLOYMENT_ENV=production');self::assertSame('localhost',ErpConnectionProfile::databaseHost('localhost'));self::assertSame('187.125.70.46',ErpConnectionProfile::databaseHost('187.125.70.46'));}finally{putenv($old===false?'APP_DEPLOYMENT_ENV':'APP_DEPLOYMENT_ENV='.$old);}
 }
}
