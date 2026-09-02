<?php

namespace App\Service;

final class SeniorWebServiceCatalog
{
    /** @var array<string, array{label: string, webservice: string, servicePath: string, port: string, method: string, purpose: string, implemented: bool}> */
    private const SERVICES = [
        'unit' => [
            'label' => 'Unidade de medida',
            'webservice' => 'com.senior.g5.co.cad.unidadedemedida',
            'servicePath' => 'com_senior_g5_co_cad_unidadedemedida?wsdl',
            'port' => 'exportar',
            'method' => 'Exportar',
            'purpose' => 'Sincroniza unidades de medida do Senior.',
            'implemented' => true,
        ],
        'origin' => [
            'label' => 'Origem do produto',
            'webservice' => 'com.senior.g5.co.ger.cad.produto.origemProduto',
            'servicePath' => 'com_senior_g5_co_ger_cad_produto_origemproduto?wsdl',
            'port' => 'exportar_2',
            'method' => 'Exportar_2',
            'purpose' => 'Sincroniza origens e grupos de produtos.',
            'implemented' => true,
        ],
        'family' => [
            'label' => 'Famílias',
            'webservice' => 'com.senior.g5.co.cad.familias',
            'servicePath' => 'com_senior_g5_co_cad_familias?wsdl',
            'port' => 'exportar_3',
            'method' => 'Exportar_3',
            'purpose' => 'Sincroniza famílias de produtos.',
            'implemented' => true,
        ],
        'warehouse' => [
            'label' => 'Depósitos',
            'webservice' => 'com.senior.g5.co.cad.deposito',
            'servicePath' => 'com_senior_g5_co_cad_deposito?wsdl',
            'port' => '',
            'method' => '',
            'purpose' => 'Cliente previsto na integração original; operação ainda não implementada.',
            'implemented' => false,
        ],
        'product' => [
            'label' => 'Cadastro de produto',
            'webservice' => 'com.senior.g5.co.ger.cad.produto',
            'servicePath' => 'com_senior_g5_co_ger_cad_produto?wsdl',
            'port' => 'Produto',
            'method' => 'CadastrarProduto',
            'purpose' => 'Envia e atualiza o cadastro de produtos no ERP.',
            'implemented' => true,
        ],
        'service' => [
            'label' => 'Cadastro de serviço',
            'webservice' => 'com.senior.g5.co.ger.cad.servico',
            'servicePath' => 'com_senior_g5_co_ger_cad_servico?wsdl',
            'port' => 'Servico',
            'method' => 'CadastrarServico',
            'purpose' => 'Envia cadastros de serviços ao ERP.',
            'implemented' => true,
        ],
    ];

    /** @return array<string, array{label: string, webservice: string, servicePath: string, port: string, method: string, purpose: string, implemented: bool}> */
    public function all(): array
    {
        return self::SERVICES;
    }

    /** @return array{label: string, webservice: string, servicePath: string, port: string, method: string, purpose: string, implemented: bool} */
    public function get(string $key): array
    {
        return self::SERVICES[$key] ?? self::SERVICES['product'];
    }

    public function buildWsdlUrl(string $baseUrl, string $serviceKey): string
    {
        if (str_contains(strtolower($baseUrl), '?wsdl')) {
            return $baseUrl;
        }

        return rtrim($baseUrl, '/').'/'.ltrim($this->get($serviceKey)['servicePath'], '/');
    }
}
