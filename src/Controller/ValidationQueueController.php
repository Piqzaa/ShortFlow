<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\ValidationQueue;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class ValidationQueueController extends AbstractController
{
    #[Route('/studio/a-valider', name: 'app_validation_queue', methods: ['GET'])]
    public function index(Request $request, #[CurrentUser] User $user, ValidationQueue $queue): Response
    {
        return $this->render('studio/validation.html.twig', ['queue' => $queue->page($user, $request->query->getString('filter'), $request->query->getInt('page', 1)), 'labels' => ValidationQueue::LABELS], new Response(headers: ['Cache-Control' => 'no-store, private']));
    }
}
