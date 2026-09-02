<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/integrations')]
#[IsGranted('ROLE_ADMIN')]
class IntegrationsHubController extends AbstractController
{
    #[Route('', name: 'app_admin_integrations_hub', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/integrations/hub.html.twig');
    }
}
