<?php

namespace App\Tests\Service;

use App\Service\{FormBindingRegistry, ErpConnectionProfile, ConnectionSecretCipher};
use PHPUnit\Framework\TestCase;

final class FormBindingRegistryTest extends TestCase
{
    public function testNativeFormCanBeDeletedWithoutReappearing(): void
    {
        $settings = ['table' => 'products', 'bindings' => ['products' => ['table' => 'products', 'mapping' => ['product_code' => 'id']]], 'form_mappings' => ['products' => ['product_code' => 'id']]];
        $settings = FormBindingRegistry::remove($settings, 'products');
        self::assertSame([], FormBindingRegistry::forms($settings, ['products' => ['label' => 'Produtos']]));
        self::assertSame([], FormBindingRegistry::resolve($settings, 'products'));
        self::assertArrayNotHasKey('products', $settings['form_mappings']);
    }
    public function testUniqueAdditionalBindingIsUsedByItsRegistration(): void
    {
        $settings = ['bindings' => ['customers' => ['table' => 'clients', 'mapping' => []], 'form-a' => ['table' => 'clients', 'mapping' => ['name' => 'name']]], 'form_catalog' => [['id' => 'form-a', 'template' => 'customers', 'label' => 'Clientes vinculados']]];
        self::assertSame('form-a', FormBindingRegistry::resolve($settings, 'customers')['form']);
    }
    public function testConnectionEditPreservesLookupAndDeletedForms(): void
    {
        $settings = ['deleted_forms' => ['suppliers'], 'lookup_sources' => ['requisitions' => ['project' => ['enabled' => true]]]];
        $profile = new ErpConnectionProfile(new ConnectionSecretCipher('test'));
        foreach (['database', 'api', 'webservice'] as $method) {
            $updated = $profile->settingsFromInput($method, [], $settings);
            self::assertSame($settings['deleted_forms'], $updated['deleted_forms']);
            self::assertSame($settings['lookup_sources'], $updated['lookup_sources']);
        }
    }
}
