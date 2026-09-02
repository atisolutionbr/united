<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/data')]
#[IsGranted('ROLE_ADMIN')]
class DataHubController extends AbstractController
{
    #[Route('', name: 'app_admin_data_hub', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/data_management/hub.html.twig');
    }
}
