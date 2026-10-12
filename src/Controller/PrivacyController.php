<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PrivacyController extends AbstractController
{
    #[Route('/confidentialite', name: 'app_privacy', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('landing/privacy.html.twig');
    }
}
