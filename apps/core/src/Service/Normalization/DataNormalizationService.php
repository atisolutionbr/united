<?php

namespace App\Service\Normalization;

/**
 * Normalização de campos para padronização de dados governamentais brasileiros.
 */
final class DataNormalizationService
{
    private const UF_MAP = [
        'AC' => 'AC', 'AL' => 'AL', 'AP' => 'AP', 'AM' => 'AM', 'BA' => 'BA',
        'CE' => 'CE', 'DF' => 'DF', 'ES' => 'ES', 'GO' => 'GO', 'MA' => 'MA',
        'MT' => 'MT', 'MS' => 'MS', 'MG' => 'MG', 'PA' => 'PA', 'PB' => 'PB',
        'PR' => 'PR', 'PE' => 'PE', 'PI' => 'PI', 'RJ' => 'RJ', 'RN' => 'RN',
        'RS' => 'RS', 'RO' => 'RO', 'RR' => 'RR', 'SC' => 'SC', 'SP' => 'SP',
        'SE' => 'SE', 'TO' => 'TO',
    ];

    public function applyRule(?string $value, string $rule): ?string
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        return match ($rule) {
            'trim' => $this->trim($value),
            'uppercase' => $this->toUppercase($value),
            'lowercase' => $this->toLowercase($value),
            'normalize_uf' => $this->normalizeUf($value),
            'normalize_cnpj' => $this->normalizeCnpj($value),
            'normalize_cpf' => $this->normalizeCpf($value),
            'normalize_phone' => $this->normalizePhone($value),
            'normalize_date' => $this->normalizeDate($value),
            'normalize_city' => $this->normalizeCity($value),
            default => $value,
        };
    }

    public function castToType(mixed $value, string $type): mixed
    {
        if (null === $value || '' === $value) {
            return null;
        }

        return match ($type) {
            'integer' => $this->castInteger($value),
            'decimal' => $this->castDecimal($value),
            'boolean' => $this->castBoolean($value),
            'date' => $this->castDate($value),
            'datetime' => $this->castDatetime($value),
            'json' => $this->castJson($value),
            default => (string) $value,
        };
    }

    public function trim(?string $value): ?string
    {
        return null === $value ? null : trim($value);
    }

    public function toUppercase(?string $value): ?string
    {
        return null === $value ? null : mb_strtoupper(trim($value), 'UTF-8');
    }

    public function toLowercase(?string $value): ?string
    {
        return null === $value ? null : mb_strtolower(trim($value), 'UTF-8');
    }

    public function normalizeUf(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $candidate = mb_strtoupper(trim($value), 'UTF-8');
        $candidate = preg_replace('/[^A-Z]/', '', $candidate) ?? $candidate;

        return self::UF_MAP[$candidate] ?? null;
    }

    public function normalizeCnpj(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (14 !== strlen($digits)) {
            return null;
        }

        return sprintf('%s.%s.%s/%s-%s',
            substr($digits, 0, 2),
            substr($digits, 2, 3),
            substr($digits, 5, 3),
            substr($digits, 8, 4),
            substr($digits, 12, 2)
        );
    }

    public function normalizeCpf(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (11 !== strlen($digits)) {
            return null;
        }

        return sprintf('%s.%s.%s-%s',
            substr($digits, 0, 3),
            substr($digits, 3, 3),
            substr($digits, 6, 3),
            substr($digits, 9, 2)
        );
    }

    public function normalizePhone(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (11 === strlen($digits)) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7, 4));
        }

        if (10 === strlen($digits)) {
            return sprintf('(%s) %s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6, 4));
        }

        return $value;
    }

    public function normalizeDate(?string $value): ?string
    {
        if (null === $value || '' === trim($value)) {
            return null;
        }

        $value = trim($value);

        $formats = ['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/Y H:i:s', 'Y-m-d H:i:s', 'd-m-Y H:i:s'];
        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof \DateTimeImmutable) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    public function normalizeCity(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $normalized = mb_strtoupper(trim($value), 'UTF-8');
        $normalized = preg_replace('/\s*-\s*[A-Z]{2}$/', '', $normalized) ?? $normalized;
        $normalized = (string) preg_replace('/\s+/', ' ', $normalized);

        return trim($normalized);
    }

    public function normalizeRow(array $row, array $mappings): array
    {
        $normalized = [];

        foreach ($mappings as $mapping) {
            $original = $mapping->getOriginalColumn();
            $target = $mapping->getNormalizedColumn();
            $value = $row[$original] ?? null;

            if (null !== $mapping->getNormalizationRule() && null !== $value) {
                $value = $this->applyRule((string) $value, $mapping->getNormalizationRule());
            }

            if (null !== $value) {
                $value = $this->castToType($value, $mapping->getTargetDataType());
            }

            $normalized[$target] = $value;
        }

        return $normalized;
    }

    private function castInteger(mixed $value): ?int
    {
        $cleaned = preg_replace('/[^\d-]/', '', (string) $value);

        return is_numeric($cleaned) ? (int) $cleaned : null;
    }

    private function castDecimal(mixed $value): ?float
    {
        $str = str_replace(['.', ','], ['', '.'], (string) $value);
        $str = preg_replace('/[^\d.-]/', '', $str) ?? '';

        return is_numeric($str) ? (float) $str : null;
    }

    private function castBoolean(mixed $value): ?bool
    {
        $lower = mb_strtolower(trim((string) $value), 'UTF-8');

        return match ($lower) {
            'true', '1', 'sim', 'yes', 's', 'y' => true,
            'false', '0', 'não', 'nao', 'no', 'n' => false,
            default => null,
        };
    }

    private function castDate(mixed $value): ?string
    {
        return $this->normalizeDate((string) $value);
    }

    private function castDatetime(mixed $value): ?string
    {
        $str = trim((string) $value);

        $formats = ['d/m/Y H:i:s', 'Y-m-d H:i:s', 'd/m/Y H:i', 'Y-m-d H:i'];
        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, $str);
            if ($date instanceof \DateTimeImmutable) {
                return $date->format('Y-m-d H:i:s');
            }
        }

        return null;
    }

    private function castJson(mixed $value): mixed
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return JSON_ERROR_NONE === json_last_error() ? $decoded : null;
    }
}
