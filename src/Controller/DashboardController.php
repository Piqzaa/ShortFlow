<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/studio', name: 'app_dashboard', methods: ['GET'])]
    public function index(): Response
    {
        $response = $this->render('studio/index.html.twig');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
