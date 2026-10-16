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
        return $this->render('legal/privacy.html.twig', [], new Response(headers: ['X-Robots-Tag' => 'noindex, nofollow', 'Cache-Control' => 'private, no-store']));
    }
}
