<?php

namespace App\Service;

final class IntegrationFields
{
    public static function forForm(array $settings, string $form, array $base): array
    {
        foreach ($settings['hidden_fields'][$form] ?? [] as $key) unset($base[$key]);
        foreach ($settings['custom_fields'][$form] ?? [] as $key => $field) {
            if (preg_match('/^extra_[a-z0-9_]{1,50}$/', (string) $key) && is_array($field)) {
                $base[$key] = ['label' => (string) $field['label'], 'hint' => 'Campo adicional do formulário.', 'type' => $field['type'] ?? 'text', 'required' => (bool) ($field['required'] ?? false)];
            }
        }
        return $base;
    }

    public static function forConnection(\App\Entity\ErpConnection $connection, string $form, array $base): array
    {
        foreach (['database', 'api', 'webservice'] as $method) $base = self::forForm($connection->getSettingsForMethod($method), $form, $base);
        return $base;
    }

    public static function selectedColumns(array $mapping, array $columns): array
    {
        $selects = [];
        foreach ($mapping as $key => $source) {
            if (preg_match('/^(extra_[a-z0-9_]+|ibs_rate|cbs_rate|cst_ibs_cbs|cclass_trib|tax_selective)$/', (string) $key)
                && isset($columns[strtolower((string) $source)])) {
                $column = $columns[strtolower((string) $source)];
                $selects[] = '['.str_replace(']', ']]', $column).'] AS [X_'.$key.']';
            }
        }
        return $selects;
    }
}
