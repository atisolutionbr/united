<?php
namespace App\Tests\Service;
use App\Service\ConnectionSecretCipher;
use App\Service\ErpConnectionProfile;
use PHPUnit\Framework\TestCase;
final class ConnectionSettingsTest extends TestCase
{
    public function testEditingEndpointKeepsPasswordAndAllExistingBindings(): void
    {
        $cipher = new ConnectionSecretCipher('test-secret');
        $profile = new ErpConnectionProfile($cipher);
        $existing = ['table'=>'dbo.e075pro', 'password_encrypted'=>$cipher->encrypt('test-password'), 'bindings'=>['products'=>['table'=>'dbo.e075pro','mapping'=>['product_code'=>'codpro']]], 'custom_fields'=>['products'=>['custom'=>['label'=>'Custom']]], 'lookup_sources'=>['requisitions'=>['product'=>['enabled'=>true]]]];
        $saved = $profile->settingsFromInput('database', ['driver'=>'sqlserver','host'=>'192.0.2.1','port'=>'1433','database'=>'sample','username'=>'sample','password'=>''], $existing);
        self::assertSame('192.0.2.1', $saved['host']);
        self::assertSame('test-password', $cipher->decrypt($saved['password_encrypted']));
        foreach (['table','bindings','custom_fields','lookup_sources'] as $key) self::assertSame($existing[$key], $saved[$key]);
    }
}
