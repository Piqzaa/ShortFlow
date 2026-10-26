<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ValidationQueueController extends AbstractController
{
    #[Route('/studio/a-valider', name: 'app_validation_queue', methods: ['GET'])]
    public function index(Request $request): Response
    {
        return $this->redirectToRoute('app_activity', ['filter' => $request->query->getString('filter'), 'page' => max(1, $request->query->getInt('page', 1)), '_fragment' => 'etapes'], 303);
    }
}
