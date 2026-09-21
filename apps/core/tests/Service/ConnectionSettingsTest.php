<?php
namespace App\Tests\Service;
use App\Service\ConnectionSecretCipher;
use App\Service\ErpConnectionProfile;
use PHPUnit\Framework\TestCase;
final class ConnectionSettingsTest extends TestCase
{
    public function testLocalEndpointEditsAreStoredLiterally(): void
    {
        $old = getenv('APP_DEPLOYMENT_ENV');
        putenv('APP_DEPLOYMENT_ENV=local');
        try {
            $profile = new ErpConnectionProfile(new ConnectionSecretCipher('test-secret'));
            $settings = ['host'=>'host.docker.internal', 'port'=>'1433', 'bindings'=>['products'=>['table'=>'dbo.e075pro']]];
            foreach (['localhost', '127.0.0.1', 'localhost\\sqlexpress', '192.0.2.20'] as $host) {
                $saved = $profile->settingsFromInput('database', ['host'=>$host, 'port'=>'15433'], $settings);
                self::assertSame($host, $saved['host']);
                self::assertSame('15433', $saved['port']);
                self::assertSame($settings['bindings'], $saved['bindings']);
                $settings = $saved;
            }
        } finally {
            putenv($old === false ? 'APP_DEPLOYMENT_ENV' : 'APP_DEPLOYMENT_ENV='.$old);
        }
    }
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
