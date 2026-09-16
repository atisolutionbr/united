<?php

namespace App\Service;

use App\Entity\ErpConnection;

final class ErpConnectionProfile
{
    /** @var array<string, array{label: string, hint: string}> */
    private const PRODUCT_FIELDS = [
        'company' => ['label' => 'Empresa', 'hint' => 'Empresa ou filial proprietária do produto.'],
        'product_code' => ['label' => 'Código do produto', 'hint' => 'Identificador do produto no ERP.'],
        'barcode' => ['label' => 'Código de barras', 'hint' => 'Campo utilizado na pesquisa de produtos.'],
        'product_name' => ['label' => 'Nome do produto', 'hint' => 'Descrição comercial principal.'],
        'unit' => ['label' => 'Unidade de medida', 'hint' => 'Unidade de venda ou estoque.'],
        'ncm' => ['label' => 'NCM', 'hint' => 'Classificação fiscal de oito dígitos.'],
        'origin_code' => ['label' => 'Código da origem', 'hint' => 'Origem fiscal ou grupo de origem.'],
        'family' => ['label' => 'Família', 'hint' => 'Família ou agrupador do produto.'],
        'cst_pis' => ['label' => 'CST de PIS', 'hint' => 'Código de situação tributária de PIS.'],
        'cst_cofins' => ['label' => 'CST de COFINS', 'hint' => 'Código de situação tributária de COFINS.'],
        'ibs_rate' => ['label' => 'Alíquota IBS', 'hint' => 'Valor informado no ERP; revisar de acordo com a operação e vigência.'],
        'cbs_rate' => ['label' => 'Alíquota CBS', 'hint' => 'Valor informado no ERP; revisar de acordo com a operação e vigência.'],
        'cst_ibs_cbs' => ['label' => 'CST IBS/CBS', 'hint' => 'Código de situação tributária de IBS e CBS.'],
        'cclass_trib' => ['label' => 'cClassTrib', 'hint' => 'Classificação tributária conforme tabela oficial vigente.'],
        'tax_selective' => ['label' => 'Imposto Seletivo', 'hint' => 'Enquadramento do produto no ERP, quando aplicável.'],
        'cst_icms' => ['label' => 'CST de ICMS', 'hint' => 'Código de situação tributária de ICMS.'],
    ];

    public function __construct(private readonly ConnectionSecretCipher $secretCipher) {}

    /** @return array<string, string> */
    public function connectionMethods(): array
    {
        return [
            ErpConnection::METHOD_DATABASE => 'Banco de dados',
            ErpConnection::METHOD_API => 'API',
            ErpConnection::METHOD_WEBSERVICE => 'WebService',
        ];
    }

    /** @return array<string, string> */
    public function databaseDrivers(): array
    {
        return [
            'sqlserver' => 'SQL Server',
            'postgresql' => 'PostgreSQL',
            'mysql' => 'MySQL',
            'mariadb' => 'MariaDB',
            'oracle' => 'Oracle',
            'firebird' => 'Firebird',
            'mongodb' => 'MongoDB',
        ];
    }

    /** @return array<string, array{label: string, hint: string}> */
    public function productFields(): array
    {
        return self::PRODUCT_FIELDS;
    }

    /** @return array<string, array{label: string, fields: array<string, array{label: string, hint: string}>}> */
    public function databaseForms(): array
    {
        return [
            'products' => ['label' => 'Produtos', 'fields' => self::PRODUCT_FIELDS],
            'requests' => ['label' => 'Solicitações', 'fields' => array_map(static fn ($f) => $f + ['hint' => 'Informação do processo de compra.'], PurchasingCatalog::fields('requests'))],
            'requisitions' => ['label' => 'Requisições', 'fields' => array_map(static fn ($f) => $f + ['hint' => 'Informação da requisição.'], PurchasingCatalog::fields('requisitions'))],
            'customers' => ['label' => 'Clientes', 'fields' => [
                'company' => ['label' => 'Empresa', 'hint' => 'Empresa ou filial proprietária do cadastro.'],
                'customer_code' => ['label' => 'Código do cliente', 'hint' => 'Identificador do cliente no ERP.'],
                'name' => ['label' => 'Nome / razão social', 'hint' => 'Nome principal do cliente.'],
                'document' => ['label' => 'CPF / CNPJ', 'hint' => 'Documento fiscal do cliente.'],
                'state_registration' => ['label' => 'Inscrição estadual', 'hint' => 'Inscrição estadual para conferência com a base cadastral.'],
                'email' => ['label' => 'E-mail', 'hint' => 'E-mail principal de contato.'],
            ]],
            'suppliers' => ['label' => 'Fornecedores', 'fields' => [
                'company' => ['label' => 'Empresa', 'hint' => 'Empresa ou filial proprietária do cadastro.'],
                'supplier_code' => ['label' => 'Código do fornecedor', 'hint' => 'Identificador do fornecedor no ERP.'],
                'name' => ['label' => 'Nome / razão social', 'hint' => 'Nome principal do fornecedor.'],
                'document' => ['label' => 'CPF / CNPJ', 'hint' => 'Documento fiscal do fornecedor.'],
                'state_registration' => ['label' => 'Inscrição estadual', 'hint' => 'Inscrição estadual para conferência com a base cadastral.'],
                'email' => ['label' => 'E-mail', 'hint' => 'E-mail principal de contato.'],
            ]],
            'carriers' => ['label' => 'Transportadoras', 'fields' => [
                'carrier_code' => ['label' => 'Código da transportadora', 'hint' => 'Identificador da transportadora no ERP.'],
                'name' => ['label' => 'Nome / razão social', 'hint' => 'Nome principal da transportadora.'],
                'document' => ['label' => 'CPF / CNPJ', 'hint' => 'Documento fiscal da transportadora.'],
                'state_registration' => ['label' => 'Inscrição estadual', 'hint' => 'Inscrição estadual para conferência com a base cadastral.'],
                'phone' => ['label' => 'Telefone', 'hint' => 'Telefone principal de contato.'],
            ]],
        ];
    }

    /** @return array<string, mixed> */
    public function defaultSettings(string $erp, string $method): array
    {
        return match ($method) {
            ErpConnection::METHOD_API => [
                'endpoint' => '',
                'authentication' => 'bearer',
                'api_key_name' => 'Authorization',
                'client_id' => '',
                'token_encrypted' => '',
                'client_secret_encrypted' => '',
                'token_configured' => false,
            ],
            ErpConnection::METHOD_WEBSERVICE => [
                'connection_name' => sprintf('%s WebService', $erp),
                'environment' => 'HOMOLOGACAO',
                'endpoint' => '',
                'host' => '',
                'port' => '',
                'service_key' => 'product',
                'operation' => 'CadastrarProduto',
                'username' => '',
                'password_encrypted' => '',
                'encryption_type' => 'AES_256_GCM_DERIVED_KEY',
                'system' => 'Senior' === $erp ? 'Senior G5' : $erp,
                'timeout_seconds' => 20,
                'active' => true,
                'credentials_configured' => false,
                'last_test_at' => null,
                'last_test_status' => 'NAO_TESTADO',
                'last_test_message' => '',
                'webservices' => [],
                'form_services' => [],
            ],
            default => [
                'driver' => 'sqlserver',
                'host' => '',
                'port' => '1433',
                'database' => '',
                'username' => '',
                'password_encrypted' => '',
                'table' => '',
                'credentials_configured' => false,
                'bindings' => [],
            ],
        };
    }

    /** @return array<string, string> */
    public function defaultProductMapping(string $erp): array
    {
        if ('Senior' !== $erp) {
            return [];
        }

        return [
            'company' => 'CodEmp',
            'product_code' => 'CodPro',
            'product_name' => 'DesPro',
            'unit' => 'UniMed',
            'ncm' => '',
            'origin_code' => 'CodOri',
            'family' => 'CodFam',
            'cst_pis' => 'CstPis',
            'cst_cofins' => 'CstCof',
            'cst_icms' => '',
        ];
    }

    /** @return list<string> */
    public function availableColumns(string $erp, string $method): array
    {
        return [];
    }

    /** @param array<string, mixed> $input
     *  @param array<string, mixed> $existing
     *  @return array<string, mixed>
     */
    public function settingsFromInput(string $method, array $input, array $existing): array
    {
        $value = static fn (string $key): string => trim((string) ($input[$key] ?? ''));

        return array_replace($existing, match ($method) {
            ErpConnection::METHOD_WEBSERVICE => [
                'connection_name' => $this->bounded($value('connection_name'), 120),
                'environment' => in_array($value('environment'), ['PRODUCAO', 'HOMOLOGACAO', 'TESTE', 'DESENVOLVIMENTO', 'AVULSO'], true) ? $value('environment') : 'HOMOLOGACAO',
                'endpoint' => $value('endpoint'),
                'host' => $this->bounded($value('host'), 255),
                'port' => preg_match('/^\d{1,5}$/', $value('port')) ? $value('port') : '',
                'service_key' => $this->identifier($value('service_key')) ?: 'product',
                'operation' => $this->bounded($value('operation'), 120),
                'username' => $this->bounded($value('username'), 180),
                'password_encrypted' => '' !== $value('password') ? $this->secretCipher->encrypt($value('password')) : (string) ($existing['password_encrypted'] ?? ''),
                'encryption_type' => 'AES_256_GCM_DERIVED_KEY',
                'system' => $this->bounded($value('system'), 80),
                'timeout_seconds' => max(1, min(120, (int) $value('timeout_seconds'))),
                'active' => '1' === $value('active'),
                'credentials_configured' => '' !== $value('password') || '' !== (string) ($existing['password_encrypted'] ?? ''),
                'last_test_at' => $existing['last_test_at'] ?? null,
                'last_test_status' => $existing['last_test_status'] ?? 'NAO_TESTADO',
                'last_test_message' => $existing['last_test_message'] ?? '',
                'form_mappings' => is_array($existing['form_mappings'] ?? null) ? $existing['form_mappings'] : [],
                'custom_fields' => $existing['custom_fields'] ?? [],
                'form_catalog' => is_array($existing['form_catalog'] ?? null) ? $existing['form_catalog'] : [],
                'webservices' => is_array($existing['webservices'] ?? null) ? $existing['webservices'] : [],
                'form_services' => is_array($existing['form_services'] ?? null) ? $existing['form_services'] : [],
            ],
            ErpConnection::METHOD_API => [
                'endpoint' => $value('endpoint'),
                'authentication' => in_array($value('authentication'), ['bearer', 'api_key', 'oauth_client'], true) ? $value('authentication') : 'bearer',
                'api_key_name' => $this->bounded($value('api_key_name'), 120),
                'client_id' => $this->bounded($value('client_id'), 180),
                'token_encrypted' => '' !== $value('token') ? $this->secretCipher->encrypt($value('token')) : (string) ($existing['token_encrypted'] ?? ''),
                'client_secret_encrypted' => '' !== $value('client_secret') ? $this->secretCipher->encrypt($value('client_secret')) : (string) ($existing['client_secret_encrypted'] ?? ''),
                'token_configured' => '' !== $value('token') || '' !== $value('client_secret') || '' !== (string) ($existing['token_encrypted'] ?? '') || '' !== (string) ($existing['client_secret_encrypted'] ?? ''),
                'form_mappings' => is_array($existing['form_mappings'] ?? null) ? $existing['form_mappings'] : [],
                'custom_fields' => $existing['custom_fields'] ?? [],
                'form_catalog' => is_array($existing['form_catalog'] ?? null) ? $existing['form_catalog'] : [],
            ],
            default => [
                'driver' => array_key_exists($value('driver'), $this->databaseDrivers()) ? $value('driver') : 'sqlserver',
                'host' => $this->databaseHost($value('host')),
                'port' => preg_match('/^\d{1,5}$/', $value('port')) ? $value('port') : '',
                'database' => $this->bounded($value('database'), 128),
                'username' => $this->bounded($value('username'), 180),
                'password_encrypted' => '' !== $value('password') ? $this->secretCipher->encrypt($value('password')) : (string) ($existing['password_encrypted'] ?? ''),
                'table' => array_key_exists('table', $input) ? $this->identifier($value('table')) : (string) ($existing['table'] ?? ''),
                'credentials_configured' => '' !== $value('password') || '' !== (string) ($existing['password_encrypted'] ?? '') || (bool) ($existing['credentials_configured'] ?? false),
                'bindings' => is_array($existing['bindings'] ?? null) ? $existing['bindings'] : [],
                'custom_fields' => $existing['custom_fields'] ?? [],
                'form_catalog' => is_array($existing['form_catalog'] ?? null) ? $existing['form_catalog'] : [],
            ],
        });
    }

    /** @param array<string, mixed> $input
     *  @param list<string> $availableColumns
     *  @return array<string, string>
     */
    public function mappingFromInput(array $input, array $availableColumns): array
    {
        return $this->mappingForFields(array_keys(self::PRODUCT_FIELDS), $input, $availableColumns);
    }

    /** @param list<string> $fields
     *  @param array<string, mixed> $input
     *  @param list<string> $availableColumns
     *  @return array<string, string>
     */
    public function mappingForFields(array $fields, array $input, array $availableColumns): array
    {
        $columnLookup = array_fill_keys(array_map('strtolower', $availableColumns), true);
        $mapping = [];

        foreach ($fields as $field) {
            $source = $this->identifier(trim((string) ($input[$field] ?? '')));
            if ('' !== $source && ([] === $availableColumns || isset($columnLookup[strtolower($source)]))) {
                $mapping[$field] = $source;
            } else {
                $mapping[$field] = '';
            }
        }

        return $mapping;
    }

    private function identifier(string $value): string
    {
        return preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $value) ? $value : '';
    }

    private function bounded(string $value, int $length): string
    {
        return mb_substr($value, 0, $length);
    }

    public static function databaseHost(string $host): string
    {
        $host = mb_substr(trim($host), 0, 255);
        // The PHP application runs in Docker locally: localhost would point to
        // that container, never to SQL Server installed on Windows.
        if (in_array(strtolower($host), ['localhost', '127.0.0.1', 'localhost\\sqlexpress'], true)
            && strtolower((string) (getenv('APP_DEPLOYMENT_ENV') ?: $_SERVER['APP_DEPLOYMENT_ENV'] ?? $_ENV['APP_DEPLOYMENT_ENV'] ?? '')) === 'local') {
            return 'host.docker.internal';
        }
        return $host;
    }
}
