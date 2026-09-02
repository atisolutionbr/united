<?php

namespace App\Service\AI;

use App\Entity\AI\AiPrompt;
use App\Repository\AI\AiPromptRepository;

/**
 * Renders prompt templates by replacing {{variable}} placeholders.
 */
class PromptTemplateService
{
    public function __construct(
        private readonly AiPromptRepository $promptRepository,
    ) {}

    public function render(AiPrompt $prompt, array $variables = []): string
    {
        $template = $prompt->getPromptTemplate();
        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', (string) $value, $template);
        }
        return $template;
    }

    public function renderByPurpose(string $purpose, array $variables = []): ?string
    {
        $prompt = $this->promptRepository->findByPurpose($purpose);
        if ($prompt === null) {
            return null;
        }
        return $this->render($prompt, $variables);
    }

    public function getByPurpose(string $purpose): ?AiPrompt
    {
        return $this->promptRepository->findByPurpose($purpose);
    }
}
