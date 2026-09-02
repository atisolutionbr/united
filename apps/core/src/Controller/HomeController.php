<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class HomeController extends AbstractController
{
    #[Route('/', name: 'homepage', methods: ['GET'])]
    public function index(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('kflow_dashboard');
        }

        return $this->redirectToRoute('security_login');
    }
}
