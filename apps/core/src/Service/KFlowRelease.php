<?php

namespace App\Service;

final class KFlowRelease
{
    // Increment this number on every delivered change. The month/year rolls over automatically.
    private const BUILD_SEQUENCE = 41;

    /** @return array{version: string, build: string} */
    public function current(): array
    {
        $period = (new \DateTimeImmutable('now', new \DateTimeZone('America/Sao_Paulo')))->format('m.y');

        return [
            'version' => $period,
            'build' => sprintf('%d.%s', self::BUILD_SEQUENCE, $period),
        ];
    }
}
