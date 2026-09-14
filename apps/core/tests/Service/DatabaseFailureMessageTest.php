<?php
namespace App\Tests\Service;
use App\Service\DatabaseSchemaInspector;
use PHPUnit\Framework\TestCase;
final class DatabaseFailureMessageTest extends TestCase
{
    public function testCommunicationFailurePreservesMappingAndDoesNotLeakDetails(): void
    {
        $error = new \PDOException('host=private-host password=private-password', 20009);
        $message = DatabaseSchemaInspector::queryFailure($error);
        self::assertStringContainsString('não está acessível', $message);
        self::assertStringContainsString('preservados', $message);
        self::assertStringNotContainsString('private-', $message);
    }
    public function testInvalidColumnIsDistinguishedFromUnavailableServer(): void
    {
        $error = new \PDOException('private SQL'); $error->errorInfo = ['42S22', 207, 'private SQL'];
        self::assertStringContainsString('coluna vinculada', DatabaseSchemaInspector::queryFailure($error));
    }
}
