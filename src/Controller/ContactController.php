<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render($this->isGranted('ROLE_VERIFIED') ? 'contact/studio.html.twig' : 'contact/index.html.twig', [], new Response(headers: ['Cache-Control' => 'private, no-store']));
    }
}
