<?php
namespace App\Tests\Service;
use App\Service\ErpUserPassword;
use PHPUnit\Framework\TestCase;
final class ErpUserPasswordTest extends TestCase
{
 public function testPlainOriginIsHashed(): void { $hash=ErpUserPassword::hash('erp-secret','plain','');self::assertNotSame('erp-secret',$hash);self::assertTrue(password_verify('erp-secret',$hash)); }
 public function testCompatibleHashIsPreserved(): void { $hash=password_hash('erp-secret',PASSWORD_BCRYPT);self::assertSame($hash,ErpUserPassword::hash($hash,'php_hash','')); }
 public function testUnsupportedOriginUsesManualPassword(): void { self::assertTrue(password_verify('manual-secret',ErpUserPassword::hash('proprietary','php_hash','manual-secret'))); }
 public function testMissingPasswordCannotCreateAccount(): void { $this->expectException(\InvalidArgumentException::class);ErpUserPassword::hash('','manual',''); }
}
