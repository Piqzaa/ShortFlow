<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\VideoAssetRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class DashboardController extends AbstractController
{
    #[Route('/studio', name: 'app_dashboard', methods: ['GET'])]
    public function index(#[CurrentUser] User $user, VideoAssetRepository $videos): Response
    {
        $response = $this->render('studio/index.html.twig', ['videos' => $videos->page($user, 1, 4), 'usage' => $videos->usage($user)]);
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
