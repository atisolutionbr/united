<?php

namespace App\Twig;

use App\Entity\User;
use App\Service\KFlowAccess;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class KFlowContextExtension extends AbstractExtension
{
    public function __construct(private readonly KFlowAccess $access, private readonly Security $security) {}
    public function getFunctions(): array { return [new TwigFunction('kflow_can', $this->can(...)), new TwigFunction('kflow_active_company', $this->activeCompany(...)), new TwigFunction('kflow_companies', $this->companies(...))]; }
    public function can(string $menu): bool { return $this->access->can($this->user(), $menu); }
    public function activeCompany(): ?\App\Entity\Company { return $this->access->activeCompany($this->user()); }
    /** @return list<\App\Entity\Company> */
    public function companies(): array { return $this->access->companies($this->user()); }
    private function user(): ?User { $user = $this->security->getUser(); return $user instanceof User ? $user : null; }
}
