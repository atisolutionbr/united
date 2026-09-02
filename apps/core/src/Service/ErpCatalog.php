<?php

namespace App\Service;

final class ErpCatalog
{
    /** @var list<string> */
    private const ERPS = [
        'Senior',
        'Sankhya',
        'RM',
        'Protheus',
        'Winthor',
        'Cigam',
        'Mega',
        'WK',
        'Uniplus',
        'PowerLogic',
    ];

    /** @return list<string> */
    public function all(): array
    {
        return self::ERPS;
    }

    public function supports(string $erp): bool
    {
        return in_array($erp, self::ERPS, true);
    }
}
