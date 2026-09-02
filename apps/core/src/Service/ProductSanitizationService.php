<?php

namespace App\Service;

final class ProductSanitizationService
{
    /** @param list<array<string, mixed>> $products
     *  @return array{duplicates: list<array{left: array<string, mixed>, right: array<string, mixed>, score: int}>, abbreviations: list<array{code: string, description: string, suggestion: string}>}
     */
    public function analyze(array $products): array
    {
        $duplicates = [];
        $abbreviations = [];
        $normalized = [];

        foreach ($products as $index => $product) {
            $description = trim((string) ($product['DesPro'] ?? ''));
            $normalized[$index] = $this->normalize($description);
            $suggestion = $this->standardizeAbbreviations($description);

            if ($suggestion !== $description) {
                $abbreviations[] = [
                    'code' => (string) ($product['CodPro'] ?? ''),
                    'description' => $description,
                    'suggestion' => $suggestion,
                ];
            }
        }

        $count = count($products);
        for ($left = 0; $left < $count; ++$left) {
            for ($right = $left + 1; $right < $count; ++$right) {
                if ('' === $normalized[$left] || '' === $normalized[$right]) {
                    continue;
                }

                similar_text($normalized[$left], $normalized[$right], $similarity);
                if ($similarity >= 88.0) {
                    $duplicates[] = ['left' => $products[$left], 'right' => $products[$right], 'score' => (int) round($similarity)];
                }
            }
        }

        usort($duplicates, static fn (array $first, array $second): int => $second['score'] <=> $first['score']);

        return [
            'duplicates' => array_slice($duplicates, 0, 8),
            'abbreviations' => array_slice($abbreviations, 0, 8),
        ];
    }

    private function normalize(string $description): string
    {
        $description = $this->standardizeAbbreviations($description);
        $description = preg_replace('/[^A-Z0-9]+/u', ' ', mb_strtoupper($description)) ?? '';

        return trim(preg_replace('/\s+/', ' ', $description) ?? '');
    }

    private function standardizeAbbreviations(string $description): string
    {
        return str_ireplace(
            ['GR.', ' C/', ' PÇ', ' UND', 'UNID.'],
            ['GR', ' COM ', ' PEÇA', ' UN', 'UN'],
            $description,
        );
    }
}
