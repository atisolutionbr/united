<?php

namespace App\Service;

final class FormBindingRegistry
{
    public static function forms(array $settings, array $definitions): array
    {
        $result = [];
        foreach ($settings['form_catalog'] ?? [] as $form) {
            if (!is_array($form) || !isset($definitions[$form['template'] ?? '']) || !preg_match('/^[A-Za-z0-9-]{1,40}$/D', $form['id'] ?? '')) continue;
            if (in_array($form['id'], $settings['deleted_forms'] ?? [], true)) continue;
            $result[$form['id']] = ['id' => $form['id'], 'template' => $form['template'], 'label' => $form['label'] ?? $definitions[$form['template']]['label']];
        }
        foreach ($definitions as $id => $definition) {
            if (!isset($result[$id]) && !in_array($id, $settings['deleted_forms'] ?? [], true)) $result[$id] = ['id' => $id, 'template' => $id, 'label' => $definition['label']];
        }
        return array_values($result);
    }

    public static function resolve(array $settings, string $template, array $legacy = []): array
    {
        $bindings = $settings['bindings'] ?? [];
        $primary = $settings['primary_forms'][$template] ?? $template;
        $direct = $bindings[$primary] ?? [];
        if (!empty($direct['table']) && array_filter($direct['mapping'] ?? [])) return $direct + ['form' => $primary];
        $matches = [];
        foreach ($settings['form_catalog'] ?? [] as $form) {
            if (($form['template'] ?? '') !== $template || in_array($form['id'], $settings['deleted_forms'] ?? [], true)) continue;
            $binding = $bindings[$form['id']] ?? [];
            if (!empty($binding['table']) && array_filter($binding['mapping'] ?? [])) $matches[] = $binding + ['form' => $form['id']];
        }
        if (1 === count($matches)) return $matches[0];
        if (!empty($direct['table'])) return $direct + ['form' => $primary];
        if ('products' === $template && !in_array($template, $settings['deleted_forms'] ?? [], true) && !empty($settings['table'])) return ['table' => $settings['table'], 'mapping' => $legacy, 'form' => $template];
        return [];
    }

    public static function remove(array $settings, string $form): array
    {
        $settings['form_catalog'] = array_values(array_filter($settings['form_catalog'] ?? [], static fn ($item) => ($item['id'] ?? '') !== $form));
        $settings['deleted_forms'] = array_values(array_unique([...($settings['deleted_forms'] ?? []), $form]));
        foreach (['bindings', 'form_mappings', 'form_queries', 'form_services', 'custom_fields', 'hidden_fields', 'lookup_sources', 'write_bindings'] as $key) unset($settings[$key][$form]);
        foreach ($settings['primary_forms'] ?? [] as $template => $selected) if ($selected === $form) unset($settings['primary_forms'][$template]);
        if ('products' === $form) $settings['table'] = '';
        return $settings;
    }
}
