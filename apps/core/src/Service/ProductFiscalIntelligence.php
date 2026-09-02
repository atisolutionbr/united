<?php

namespace App\Service;

final class ProductFiscalIntelligence
{
    /** @return array{ready: bool, ncm: string, chapter: string, guidance: string} */
    public function review(?string $ncm): array
    {
        $digits = preg_replace('/\D/', '', (string) $ncm) ?? '';
        if (8 !== strlen($digits)) {
            return [
                'ready' => false,
                'ncm' => '',
                'chapter' => 'NCM pendente',
                'guidance' => 'Informe um NCM de oito dígitos para preparar a revisão fiscal do produto.',
            ];
        }

        $chapter = match (substr($digits, 0, 2)) {
            '33' => 'Capítulo 33: perfumaria, cosméticos e preparações de toucador.',
            '39' => 'Capítulo 39: plásticos e suas obras.',
            '40' => 'Capítulo 40: borracha e suas obras.',
            '68' => 'Capítulo 68: obras de pedra, gesso, cimento, amianto, mica ou matérias semelhantes.',
            '73' => 'Capítulo 73: obras de ferro fundido, ferro ou aço.',
            '82' => 'Capítulo 82: ferramentas e utensílios de metais comuns.',
            default => sprintf('Capítulo %s da NCM.', substr($digits, 0, 2)),
        };

        return [
            'ready' => true,
            'ncm' => $digits,
            'chapter' => $chapter,
            'guidance' => 'Revise os CSTs de ICMS, PIS e COFINS conforme a UF, o regime tributário e a operação antes de salvar no ERP.',
        ];
    }
}
