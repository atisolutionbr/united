<?php

namespace App\Service;

final class PurchasingCatalog
{
    public const CARDS = [
        'requisitions' => 'Requisições', 'requests' => 'Solicitações', 'purchase-orders' => 'Ordens de Compra',
        'quotations' => 'Cotações', 'purchase-contracts' => 'Contrato de Compra', 'projects' => 'Projetos',
        'budgets' => 'Orçamentos/Propostas', 'invoices' => 'NF Entrada', 'payables' => 'C.Pagar',
        'receivables' => 'C.Receber', 'credit' => 'Análise de Crédito', 'sales-orders' => 'Pedido de Venda',
    ];

    public const STAGES = [
        'need' => 'Necessidade identificada', 'stock' => 'Verificação de estoque central',
        'request-approval' => 'Aprovação da solicitação', 'request-check' => 'Dupla verificação da SC e aprovação',
        'quotation' => 'Cotação', 'quotation-approval' => 'Aprovação da cotação',
        'quotation-check' => 'Dupla verificação da cotação e aprovação', 'purchase-order' => 'Geração de ordem de compra',
        'cash-flow' => 'Verificação de fluxo de caixa e OC', 'order-approval' => 'Aprovação das ordens de compra',
        'supplier' => 'Envio ao fornecedor / emissão de NF', 'invoice' => 'Entrada de NF vinculada à OC',
        'complete' => 'Concluído',
    ];

    public static function fields(string $kind): array
    {
        $fields = [
            'product' => ['label' => 'Produto / descrição do item', 'type' => 'text', 'required' => true],
            'quantity' => ['label' => 'Qtd requisitada', 'type' => 'number', 'required' => true],
            'requester' => ['label' => 'Solicitante', 'type' => 'text', 'required' => true],
            'delivery_address' => ['label' => 'Endereço de entrega', 'type' => 'textarea', 'required' => true],
            'project' => ['label' => 'Projeto', 'type' => 'text'], 'phase' => ['label' => 'Fase', 'type' => 'text'],
            'warehouse' => ['label' => 'Local (depósito)', 'type' => 'text'],
            'reserve_stock' => ['label' => 'Reserva de estoque?', 'type' => 'checkbox'],
            'document' => ['label' => 'Documento', 'type' => 'text'],
        ];
        if ('requests' === $kind) $fields += [
            'need' => ['label' => 'Necessidade da compra', 'type' => 'textarea', 'required' => true],
            'specification' => ['label' => 'Especificação da área / profissional solicitante', 'type' => 'textarea', 'required' => true],
            'cost_center' => ['label' => 'Centro de custo (CCU)', 'type' => 'text', 'required' => true],
            'desired_date' => ['label' => 'Data de entrega desejada', 'type' => 'date', 'required' => true],
            'central_stock' => ['label' => 'Estoque central / outros estoques consultados', 'type' => 'textarea'],
        ];
        return $fields;
    }

    public static function validate(array $input, array $fields): array
    {
        $data = [];
        foreach ($fields as $key => $field) {
            $value = $input[$key] ?? '';
            if (!is_scalar($value)) throw new \InvalidArgumentException('Valor inválido em '.$field['label']);
            $value = trim((string) $value);
            if (($field['required'] ?? false) && '' === $value) throw new \InvalidArgumentException('Preencha '.$field['label']);
            if (mb_strlen($value) > 4000) throw new \InvalidArgumentException('Texto muito longo em '.$field['label']);
            if ('number' === $field['type'] && '' !== $value && (!is_numeric($value) || !is_finite((float) $value) || (float) $value <= 0)) throw new \InvalidArgumentException('Informe uma quantidade positiva.');
            if ('date' === $field['type'] && '' !== $value) {
                $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
                if (!$date || $date->format('Y-m-d') !== $value) throw new \InvalidArgumentException('Data inválida.');
            }
            $data[$key] = 'checkbox' === $field['type'] ? ('1' === $value ? 1 : 0) : $value;
        }
        return $data;
    }
}
