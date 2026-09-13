<?php

namespace App\Tests\Service;

use App\Entity\{Company, ErpConnection};
use App\Service\{ProcessGateway, DatabaseSchemaInspector, ConnectionSecretCipher, ProductQualityReview};
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;

/** Opt-in test: creates only a random schema in the isolated local United Postgres. */
final class ProcessGatewayDatabaseTest extends TestCase
{
    public function testPaginationCreateOptimisticApprovalAndRollback(): void
    {
        if ('1' !== getenv('UNITED_LOCAL_DB_TEST')) self::markTestSkipped('Explicit local test opt-in required.');
        (new \Symfony\Component\Dotenv\Dotenv())->bootEnv(dirname(__DIR__, 2).'/.env');
        $url = parse_url($_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? getenv('DATABASE_URL'));
        if (!in_array($url['host'] ?? '', ['postgres', 'united-postgres', '127.0.0.1'], true)) self::fail('This test only supports the isolated local database.');
        $schemaName = 'united_test_'.bin2hex(random_bytes(6));
        $cipher = new ConnectionSecretCipher('test-only');
        $settings = ['driver' => 'postgresql', 'host' => $url['host'], 'port' => (string) ($url['port'] ?? 5432), 'database' => ltrim($url['path'], '/'), 'username' => rawurldecode($url['user']), 'password_encrypted' => $cipher->encrypt(rawurldecode($url['pass']))];
        $inspector = new DatabaseSchemaInspector($cipher);
        $pdo = $inspector->open($settings);
        $pdo->exec('CREATE SCHEMA '.$schemaName);
        try {
            $table = $schemaName.'.orders';
            $pdo->exec('CREATE TABLE '.$table.' (company INT NOT NULL, id INT NOT NULL, description TEXT NOT NULL, status VARCHAR(1) NOT NULL)');
            $insert = $pdo->prepare('INSERT INTO '.$table.' VALUES (?, ?, ?, ?)');
            for ($i = 1; $i <= 31; ++$i) $insert->execute([1, $i, 'Item '.$i, 'P']);
            $connection = new ErpConnection(new Company('Isolated fixture'), 'Fixture');
            $connection->setSettingsForMethod('database', $settings);
            $gateway = new ProcessGateway($inspector, $cipher, new MockHttpClient());
            $config = ['method' => 'database', 'purpose' => 'approval', 'table' => $table, 'mapping' => ['id' => 'id', 'description' => 'description'], 'key_columns' => 'company,id', 'status_column' => 'status', 'pending_value' => 'P', 'approved_value' => 'A', 'rejected_value' => 'R'];
            $gateway->validate($connection, $config);
            self::assertCount(15, $gateway->list($connection, $config, 1)['rows']);
            self::assertCount(15, $gateway->list($connection, $config, 2)['rows']);
            self::assertCount(1, $gateway->list($connection, $config, 3)['rows']);
            $gateway->decide($connection, $config, ['company' => 1, 'id' => 1], 'approve', 'fixture-1');
            self::assertSame('A', $pdo->query('SELECT status FROM '.$table.' WHERE id = 1')->fetchColumn());
            try { $gateway->decide($connection, $config, ['company' => 1, 'id' => 1], 'reject', 'fixture-2'); self::fail('Stale decisions must fail.'); } catch (\RuntimeException $e) { self::assertStringContainsString('alterado', $e->getMessage()); }
            $insert->execute([1, 2, 'Duplicate key', 'P']);
            try { $gateway->decide($connection, $config, ['company' => 1, 'id' => 2], 'approve', 'fixture-3'); self::fail('Non-unique keys must roll back.'); } catch (\RuntimeException $e) { self::assertSame(2, (int) $pdo->query("SELECT COUNT(*) FROM $table WHERE id = 2 AND status = 'P'")->fetchColumn()); }
            $create = ['method' => 'database', 'purpose' => 'create', 'table' => $table, 'mapping' => ['company' => 'company', 'id' => 'id', 'description' => 'description', 'status' => 'status']];
            $gateway->create($connection, $create, ['company' => 2, 'id' => 99, 'description' => "Produto d'água", 'status' => 'P'], 'fixture-create');
            self::assertSame("Produto d'água", $pdo->query('SELECT description FROM '.$table.' WHERE id = 99')->fetchColumn());
            $connection->setSettingsForMethod('database', $settings + ['bindings' => ['products' => ['table' => $table, 'mapping' => ['product_code' => 'id', 'product_name' => 'description', 'barcode' => 'status']]]]);
            $lookups = new \App\Service\LookupCatalog($inspector, $gateway);
            self::assertCount(25, $lookups->search($connection, 'requisitions', 'product', '', 1)['items']);
            self::assertTrue($lookups->search($connection, 'requisitions', 'product', '', 1)['more']);
            self::assertSame('99', $lookups->search($connection, 'requests', 'product', '99', 1)['items'][0]['value']);
            self::assertSame('99', $lookups->search($connection, 'requests', 'product', 'água', 1)['items'][0]['value']);
            self::assertNotEmpty($lookups->search($connection, 'requests', 'product', 'P', 1)['items']);
            self::assertSame([], $lookups->search($connection, 'requests', 'product', "' OR 1=1 --", 1)['items']);
            $quality = new ProductQualityReview($inspector);
            $binding = ['table' => $table, 'mapping' => ['company' => 'company', 'product_code' => 'id', 'product_name' => 'description']];
            $quality->normalize($settings, $binding, ['company' => 2, 'product_code' => 99, 'product_name' => "Produto d'água"], 'upper');
            self::assertSame("PRODUTO D'ÁGUA", $pdo->query('SELECT description FROM '.$table.' WHERE id = 99')->fetchColumn());
        } finally {
            if (!preg_match('/^united_test_[a-f0-9]{12}$/D', $schemaName)) throw new \LogicException('Unexpected fixture schema.');
            $pdo->exec('DROP SCHEMA '.$schemaName.' CASCADE');
        }
    }
}
