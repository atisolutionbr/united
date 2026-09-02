<?php

namespace App\Service\Validation;

/**
 * Validação de campos para dados governamentais brasileiros.
 */
final class DataValidationService
{
    private const VALID_UFS = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA',
        'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN',
        'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
    ];

    /**
     * @return array{valid: bool, errors: list<string>}
     */
    public function validateRow(array $row, array $mappings): array
    {
        $errors = [];

        foreach ($mappings as $mapping) {
            $target = $mapping->getNormalizedColumn();
            $value = $row[$target] ?? null;
            $original = $mapping->getOriginalColumn();

            if ($mapping->isRequiredField() && (null === $value || '' === $value)) {
                $errors[] = sprintf('Campo obrigatório "%s" está vazio.', $original);
                continue;
            }

            if (null === $value || '' === $value) {
                continue;
            }

            $typeErrors = $this->validateType($value, $mapping->getTargetDataType(), $original);
            array_push($errors, ...$typeErrors);

            $ruleErrors = $this->validateByRule($value, $mapping->getNormalizationRule(), $original);
            array_push($errors, ...$ruleErrors);
        }

        return ['valid' => empty($errors), 'errors' => $errors];
    }

    public function validateCnpj(?string $value): bool
    {
        if (null === $value) {
            return false;
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (14 !== strlen($digits) || preg_match('/^(\d)\1{13}$/', $digits)) {
            return false;
        }

        return $this->checkCnpjDigits($digits);
    }

    public function validateCpf(?string $value): bool
    {
        if (null === $value) {
            return false;
        }

        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (11 !== strlen($digits) || preg_match('/^(\d)\1{10}$/', $digits)) {
            return false;
        }

        return $this->checkCpfDigits($digits);
    }

    public function validateUf(?string $value): bool
    {
        return null !== $value && in_array(mb_strtoupper(trim($value), 'UTF-8'), self::VALID_UFS, true);
    }

    public function validateDate(?string $value): bool
    {
        if (null === $value || '' === trim($value)) {
            return false;
        }

        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y'];
        foreach ($formats as $format) {
            $date = \DateTimeImmutable::createFromFormat($format, trim($value));
            if ($date instanceof \DateTimeImmutable) {
                return true;
            }
        }

        return false;
    }

    public function validateInteger(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_INT) !== false;
    }

    public function validateDecimal(mixed $value): bool
    {
        if (is_float($value) || is_int($value)) {
            return true;
        }

        $str = str_replace(['.', ','], ['', '.'], (string) $value);

        return filter_var($str, FILTER_VALIDATE_FLOAT) !== false;
    }

    public function validateMaxLength(string $value, int $max): bool
    {
        return mb_strlen($value, 'UTF-8') <= $max;
    }

    /**
     * @return array{
     *     total_rows: int,
     *     valid_rows: int,
     *     invalid_rows: int,
     *     duplicated_rows: int,
     *     null_fields: int,
     *     validation_errors: list<array{row: int, errors: list<string>}>
     * }
     */
    public function analyzeDataset(array $rows, array $mappings): array
    {
        $totalRows = count($rows);
        $validRows = 0;
        $invalidRows = 0;
        $nullFields = 0;
        $validationErrors = [];
        $seenHashes = [];
        $duplicatedRows = 0;

        foreach ($rows as $index => $row) {
            $nullFields += $this->countNullFields($row);
            $rowHash = md5(serialize(array_values($row)));

            if (isset($seenHashes[$rowHash])) {
                ++$duplicatedRows;
            } else {
                $seenHashes[$rowHash] = true;
            }

            $result = $this->validateRow($row, $mappings);

            if ($result['valid']) {
                ++$validRows;
            } else {
                ++$invalidRows;
                $validationErrors[] = ['row' => $index + 1, 'errors' => $result['errors']];
            }
        }

        return [
            'total_rows' => $totalRows,
            'valid_rows' => $validRows,
            'invalid_rows' => $invalidRows,
            'duplicated_rows' => $duplicatedRows,
            'null_fields' => $nullFields,
            'validation_errors' => array_slice($validationErrors, 0, 100),
        ];
    }

    private function validateType(mixed $value, string $type, string $fieldName): array
    {
        $errors = [];

        switch ($type) {
            case 'integer':
                if (!$this->validateInteger($value)) {
                    $errors[] = sprintf('"%s" não é um inteiro válido.', $fieldName);
                }
                break;
            case 'decimal':
                if (!$this->validateDecimal($value)) {
                    $errors[] = sprintf('"%s" não é um decimal válido.', $fieldName);
                }
                break;
            case 'date':
                if (!$this->validateDate((string) $value)) {
                    $errors[] = sprintf('"%s" não é uma data válida.', $fieldName);
                }
                break;
            case 'boolean':
                if (!in_array(mb_strtolower((string) $value, 'UTF-8'), ['true', 'false', '1', '0', 'sim', 'não', 'nao', 'yes', 'no', 's', 'n'], true)) {
                    $errors[] = sprintf('"%s" não é um booleano reconhecido.', $fieldName);
                }
                break;
        }

        return $errors;
    }

    private function validateByRule(mixed $value, ?string $rule, string $fieldName): array
    {
        if (null === $rule) {
            return [];
        }

        return match ($rule) {
            'normalize_cnpj' => $this->validateCnpj((string) $value) ? [] : [sprintf('"%s" contém CNPJ inválido.', $fieldName)],
            'normalize_cpf' => $this->validateCpf((string) $value) ? [] : [sprintf('"%s" contém CPF inválido.', $fieldName)],
            'normalize_uf' => $this->validateUf((string) $value) ? [] : [sprintf('"%s" contém UF inválida.', $fieldName)],
            default => [],
        };
    }

    private function countNullFields(array $row): int
    {
        return count(array_filter($row, static fn($v) => null === $v || '' === trim((string) $v)));
    }

    private function checkCnpjDigits(string $digits): bool
    {
        $weights1 = [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];
        $weights2 = [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2];

        $sum1 = 0;
        for ($i = 0; $i < 12; ++$i) {
            $sum1 += (int) $digits[$i] * $weights1[$i];
        }
        $remainder1 = $sum1 % 11;
        $digit1 = $remainder1 < 2 ? 0 : 11 - $remainder1;

        if ((int) $digits[12] !== $digit1) {
            return false;
        }

        $sum2 = 0;
        for ($i = 0; $i < 13; ++$i) {
            $sum2 += (int) $digits[$i] * $weights2[$i];
        }
        $remainder2 = $sum2 % 11;
        $digit2 = $remainder2 < 2 ? 0 : 11 - $remainder2;

        return (int) $digits[13] === $digit2;
    }

    private function checkCpfDigits(string $digits): bool
    {
        $sum1 = 0;
        for ($i = 0; $i < 9; ++$i) {
            $sum1 += (int) $digits[$i] * (10 - $i);
        }
        $remainder1 = ($sum1 * 10) % 11;
        $digit1 = 10 === $remainder1 || 11 === $remainder1 ? 0 : $remainder1;

        if ((int) $digits[9] !== $digit1) {
            return false;
        }

        $sum2 = 0;
        for ($i = 0; $i < 10; ++$i) {
            $sum2 += (int) $digits[$i] * (11 - $i);
        }
        $remainder2 = ($sum2 * 10) % 11;
        $digit2 = 10 === $remainder2 || 11 === $remainder2 ? 0 : $remainder2;

        return (int) $digits[10] === $digit2;
    }
}
