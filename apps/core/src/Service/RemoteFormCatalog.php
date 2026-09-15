<?php
namespace App\Service;

use App\Entity\ErpConnection;

/** Reads the selected remote profile only; never falls back to SQL. */
final class RemoteFormCatalog
{
    public function __construct(private readonly ProcessGateway $gateway) {}

    public static function binding(ErpConnection $connection, string $type): array
    {
        $settings = $connection->getSettingsForMethod($connection->getConnectionMethod() ?? '');
        $form = $settings['primary_forms'][$type] ?? $type;
        if (in_array($form, $settings['deleted_forms'] ?? [], true)) return [];
        if (empty(array_filter($settings['form_mappings'][$form] ?? []))) {
            $matches = [];
            foreach ($settings['form_catalog'] ?? [] as $entry) {
                if (($entry['template'] ?? '') === $type && !in_array($entry['id'], $settings['deleted_forms'] ?? [], true) && array_filter($settings['form_mappings'][$entry['id']] ?? [])) $matches[] = $entry['id'];
            }
            if (count($matches) === 1) $form = $matches[0];
        }
        return ['form' => $form, 'mapping' => $settings['form_mappings'][$form] ?? [], 'query' => $settings['form_queries'][$form] ?? []];
    }

    public function list(ErpConnection $connection, string $type, int $page): array
    {
        $method = $connection->getConnectionMethod();
        $label = $method === 'api' ? 'API' : 'WebService';
        $key = match ($type) { 'products' => 'products', 'customers' => 'customers', default => 'parties' };
        $result = ['configured' => false, $key => [], 'error' => null, 'sourceTable' => $label, 'recordCount' => 0, 'page' => max(1, $page), 'perPage' => 10, 'pageCount' => 1, 'missingAddressCount' => 0, 'staleCount' => 0, 'ncmUpdateAvailable' => false];
        try {
            if (!in_array($method, ['api', 'webservice'], true)) throw new \InvalidArgumentException('Selecione o método de conexão do ERP.');
            $binding = self::binding($connection, $type);
            $query = $binding['query'] ?? [];
            if (empty(array_filter($binding['mapping'] ?? []))) throw new \InvalidArgumentException('Vincule os campos deste formulário no perfil '.$label.'.');
            if (empty($query['operation']) || empty($query['items_path']) || empty($query['total_path'])) throw new \InvalidArgumentException('Configure a consulta de leitura (operação, coleção e total) no vínculo '.$label.' deste formulário.');
            $result['configured'] = true;
            $settings = $connection->getSettingsForMethod($method);
            $target = clone $connection;
            if ($method === 'webservice') {
                $id = $settings['form_services'][$binding['form']] ?? '';
                $service = null;
                foreach ($settings['webservices'] ?? [] as $entry) if (($entry['id'] ?? '') === $id && ($entry['active'] ?? true)) $service = $entry;
                if (!$service) throw new \InvalidArgumentException('Selecione um WebService ativo para este formulário.');
                $endpoint = trim($service['endpoint'] ?? '');
                if (!preg_match('#^https?://#i', $endpoint)) $endpoint = 'http://'.$endpoint;
                if (!empty($service['port']) && !parse_url($endpoint, PHP_URL_PORT)) $endpoint = preg_replace('#^(https?://[^/]+)#', '$1:'.$service['port'], $endpoint);
                $settings['endpoint'] = $endpoint;
                $target->setSettingsForMethod($method, $settings);
            }
            $response = $this->gateway->readForm($target, $query + ['method' => $method], $page);
            $rows = ProcessGateway::readPath($response, $query['items_path']);
            $total = ProcessGateway::readPath($response, $query['total_path']);
            if (!is_array($rows) || !array_is_list($rows) || count($rows) > 10 || !is_numeric($total) || $total < 0) throw new \InvalidArgumentException('O retorno '.$label.' deve conter a coleção paginada (até 10 registros) e o total nos caminhos vinculados.');
            $aliases = match ($type) {
                'products' => ['company'=>'CodEmp','product_code'=>'CodPro','product_name'=>'DesPro','unit'=>'UniMed','ncm'=>'Ncm','origin_code'=>'CodOri','family'=>'CodFam','cst_pis'=>'CstPis','cst_cofins'=>'CstCofins','cst_icms'=>'CstIcms'],
                'customers' => ['company'=>'CodEmp','customer_code'=>'CodCli','name'=>'NomCli','document'=>'CgcCpf','state_registration'=>'InsEst','email'=>'EmlCli'],
                default => ['supplier_code'=>'Code','carrier_code'=>'Code','name'=>'Name','document'=>'Document','state_registration'=>'StateRegistration','email'=>'Email','phone'=>'Phone'],
            };
            $defaults = match ($type) { 'products' => ['CplPro','DesNFv','TipPro'], 'customers' => ['EndCli','CidCli','SigUfs','DatAlt'], default => ['Address','City','State','UpdatedAt'] };
            foreach ($rows as $row) {
                if (!is_array($row)) throw new \InvalidArgumentException('Registro inválido na coleção '.$label.'.');
                $record = array_fill_keys([...array_values($aliases), ...$defaults], '');
                foreach ($binding['mapping'] as $field => $path) {
                    if (!$path) continue;
                    $value = ProcessGateway::readPath($row, $path);
                    if (!is_scalar($value) && $value !== null) throw new \InvalidArgumentException('O campo vinculado '.$field.' deve retornar um valor simples.');
                    $record[$aliases[$field] ?? 'X_'.$field] = $value ?? '';
                }
                $result[$key][] = $record;
            }
            $result['recordCount'] = (int) $total;
            $result['pageCount'] = max(1, (int) ceil($total / 10));
        } catch (\InvalidArgumentException $e) {
            $result[$key] = []; $result['error'] = $e->getMessage();
        } catch (\Throwable $e) {
            $result[$key] = []; $result['error'] = 'Não foi possível consultar o '.$label.'. Verifique endereço, autenticação, operação e retorno vinculados. Os vínculos foram preservados.';
        }
        return $result;
    }
}
