<?php

namespace App\Twig;

use App\Service\KFlowRelease;
use Twig\Attribute\AsTwigFunction;

final class KFlowExtension
{
    public function __construct(private readonly KFlowRelease $release)
    {
    }

    /** @return array{version: string, build: string} */
    #[AsTwigFunction('kflow_release')]
    public function release(): array
    {
        return $this->release->current();
    }
}
